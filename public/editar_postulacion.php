<?php
require_once '../src/bootstrap.php';
require_once '../src/inc/auth.php';
require_login();

$codigo = $_GET['codigo'] ?? null;
$usuarioActual = current_user();

if (!$codigo) {
    die("Error: No se especificó ninguna postulación a editar.");
}

$sql = "SELECT p.*, i.nombre_iniciativa, i.descripcion, i.solucion, i.resultados_esperados, i.documentos, i.id_tipo_iniciativa 
        FROM postulacion p 
        LEFT JOIN iniciativa i ON p.codigo_postulacion = i.codigo_postulacion
        WHERE p.codigo_postulacion = :codigo 
        AND (p.responsable_1 = :user OR p.responsable_2 = :user)";

$stmt = db()->prepare($sql);
$stmt->execute(['codigo' => $codigo, 'user' => $usuarioActual]);
$datos_postulacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$datos_postulacion) {
    die("Acceso denegado: Esta postulación no existe o no tienes permisos para editarla.");
}

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();

        $sqlPostulacion = "UPDATE postulacion SET presupuesto = :presupuesto, rut_empresa = :rut_empresa, id_sede = :id_sede, id_region_ejecucion = :id_region_ejecucion, id_region_impacto = :id_region_impacto WHERE codigo_postulacion = :codigo";
        $stmtPost = db()->prepare($sqlPostulacion);
        $stmtPost->execute([
            'presupuesto' => $_POST['presupuesto'],
            'rut_empresa' => $_POST['rut_empresa'],
            'id_sede' => $_POST['id_sede'],
            'id_region_ejecucion' => $_POST['id_region_ejecucion'],
            'id_region_impacto' => $_POST['id_region_impacto'],
            'codigo' => $codigo
        ]);

        $sqlIniciativa = "UPDATE iniciativa SET nombre_iniciativa = :nombre, descripcion = :desc, solucion = :sol, resultados_esperados = :res, documentos = :docs, id_tipo_iniciativa = :tipo WHERE codigo_postulacion = :codigo";
        $stmtIni = db()->prepare($sqlIniciativa);
        $stmtIni->execute([
            'nombre' => $_POST['nombre_iniciativa'],
            'desc' => $_POST['descripcion'],
            'sol' => $_POST['solucion'],
            'res' => $_POST['resultados_esperados'],
            'docs' => $_POST['documentos'],
            'tipo' => $_POST['id_tipo_iniciativa'],
            'codigo' => $codigo
        ]);

        $stmtDelEq = db()->prepare("DELETE FROM equipo WHERE codigo_postulacion = :codigo");
        $stmtDelEq->execute(['codigo' => $codigo]);

        $ruts = $_POST['rut_integrante'] ?? [];
        $nombres = $_POST['nombre_integrante'] ?? [];
        $deptos = $_POST['depto_integrante'] ?? [];
        $emails = $_POST['email_integrante'] ?? [];
        $telefonos = $_POST['telefono_integrante'] ?? [];
        $sedes = $_POST['id_sede_integrante'] ?? [];
        $tipos = $_POST['tipo_integrante'] ?? [];
        $roles = $_POST['rol_integrante'] ?? [];

        for ($i = 0; $i < count($ruts); $i++) {
            $sqlIntegrante = "INSERT IGNORE INTO integrante (rut_integrante, nombre_integrante, depto_integrante, email_integrante, telefono_integrante, id_sede, id_tipo_persona) VALUES (:rut, :nombre, :depto, :email, :telefono, :sede, :tipo)";
            $stmtInt = db()->prepare($sqlIntegrante);
            $stmtInt->execute([
                'rut' => $ruts[$i],
                'nombre' => $nombres[$i],
                'depto' => $deptos[$i],
                'email' => $emails[$i],
                'telefono' => $telefonos[$i],
                'sede' => $sedes[$i],
                'tipo' => $tipos[$i]
            ]);

            $sqlEquipo = "INSERT INTO equipo (codigo_postulacion, rut_integrante, rol_integrante) VALUES (:codigo, :rut, :rol)";
            $stmtEq = db()->prepare($sqlEquipo);
            $stmtEq->execute([
                'codigo' => $codigo,
                'rut' => $ruts[$i],
                'rol' => $roles[$i]
            ]);
        }

        $stmtGetEtapas = db()->prepare("SELECT cod_etapa FROM cronograma WHERE codigo_postulacion = :codigo");
        $stmtGetEtapas->execute(['codigo' => $codigo]);
        $viejas_etapas = $stmtGetEtapas->fetchAll(PDO::FETCH_COLUMN);

        $stmtDelCron = db()->prepare("DELETE FROM cronograma WHERE codigo_postulacion = :codigo");
        $stmtDelCron->execute(['codigo' => $codigo]);

        if (!empty($viejas_etapas)) {
            $placeholders = implode(',', array_fill(0, count($viejas_etapas), '?'));
            $stmtDelEt = db()->prepare("DELETE FROM etapa WHERE cod_etapa IN ($placeholders)");
            $stmtDelEt->execute($viejas_etapas);
        }

        $nombres_etapas = $_POST['nombre_etapa'] ?? [];
        $plazos_etapas = $_POST['plazo_etapa'] ?? [];
        $entregables_etapas = $_POST['entregable_etapa'] ?? [];

        for ($j = 0; $j < count($nombres_etapas); $j++) {
            $sqlEtapa = "INSERT INTO etapa (nombre_etapa, plazo, entregable) VALUES (:nombre, :plazo, :entregable)";
            $stmtEt = db()->prepare($sqlEtapa);
            $stmtEt->execute([
                'nombre' => $nombres_etapas[$j],
                'plazo' => $plazos_etapas[$j],
                'entregable' => $entregables_etapas[$j]
            ]);

            $id_nueva_etapa = db()->lastInsertId();

            $sqlCronograma = "INSERT INTO cronograma (codigo_postulacion, cod_etapa) VALUES (:codigo, :etapa)";
            $stmtCron = db()->prepare($sqlCronograma);
            $stmtCron->execute([
                'codigo' => $codigo,
                'etapa' => $id_nueva_etapa
            ]);
        }

        //$sqlRevisarEquipo = "CALL VerificarRequisitoEquipo(:codigo)";
        //$stmtRevisarEq = db()->prepare($sqlRevisarEquipo);
        //$stmtRevisarEq->execute(['codigo' => $codigo]);

        $sqlRevisarCronograma = "CALL VerificarRequisitoCronograma(:codigo)";
        $stmtRevisarCr = db()->prepare($sqlRevisarCronograma);
        $stmtRevisarCr->execute(['codigo' => $codigo]);

        db()->commit();
        redirect_to("detalle_postulacion.php?codigo=" . urlencode($codigo));

    } catch (Exception $e) {
        db()->rollBack();
        
        $error_crudo = $e->getMessage();
        if ($e->getCode() == '45000' || strpos($error_crudo, '45000') !== false) {
            $partes = explode('1644', $error_crudo);
            if (isset($partes[1])) {
                $mensaje_error = trim($partes[1]); 
            } else {
                $mensaje_error = "Error: La postulación no cumple con los requisitos mínimos.";
            }
        } else {
            $mensaje_error = "Ocurrió un error inesperado en la base de datos: " . $error_crudo;
        }
    }
}

$stmtEmpresas = db()->query("SELECT DISTINCT rut_empresa, nombre_empresa FROM empresa ORDER BY nombre_empresa ASC");
$listaEmpresas = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);

$stmtRegiones = db()->query("SELECT id_region, nombre_region FROM region ORDER BY id_region ASC");
$listaRegiones = $stmtRegiones->fetchAll(PDO::FETCH_ASSOC);

$stmtSedes = db()->query("SELECT id_sede, nombre_sede FROM sede ORDER BY id_sede ASC");
$listaSedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

$sqlEquipoQuery = "SELECT i.rut_integrante, i.nombre_integrante, i.depto_integrante, i.email_integrante, i.telefono_integrante, i.id_sede, i.id_tipo_persona, e.rol_integrante FROM equipo e INNER JOIN integrante i ON e.rut_integrante = i.rut_integrante WHERE e.codigo_postulacion = :codigo";
$stmtEquipo = db()->prepare($sqlEquipoQuery);
$stmtEquipo->execute(['codigo' => $codigo]);
$equipoActual = $stmtEquipo->fetchAll(PDO::FETCH_ASSOC);

$sqlEtapasQuery = "SELECT e.nombre_etapa, e.plazo, e.entregable FROM cronograma c INNER JOIN etapa e ON c.cod_etapa = e.cod_etapa WHERE c.codigo_postulacion = :codigo";
$stmtEtapas = db()->prepare($sqlEtapasQuery);
$stmtEtapas->execute(['codigo' => $codigo]);
$etapasActuales = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);
?>

<?php view('header', ['title' => 'Editar Postulación']) ?>

<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h2>Editando Postulación: <?= htmlspecialchars($codigo) ?></h2>
    
    <?php if (!empty($mensaje_error)): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;">
            <strong>¡Atención!</strong> <?= htmlspecialchars($mensaje_error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="editar_postulacion.php?codigo=<?= urlencode($codigo) ?>">
        
        <h3>1. Datos Generales</h3>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Empresa Externa Asignada:</label>
            <select name="rut_empresa" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione una Empresa --</option>
                <?php foreach ($listaEmpresas as $empresa): ?>
                    <option value="<?= htmlspecialchars($empresa['rut_empresa']) ?>" <?= ($empresa['rut_empresa'] === $datos_postulacion['rut_empresa']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($empresa['nombre_empresa']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Sede USM Responsable:</label>
            <select name="id_sede" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione Sede --</option>
                <?php foreach ($listaSedes as $sede): ?>
                    <option value="<?= htmlspecialchars($sede['id_sede']) ?>" <?= ($sede['id_sede'] == $datos_postulacion['id_sede']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sede['nombre_sede']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Región de Ejecución:</label>
            <select name="id_region_ejecucion" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione Región --</option>
                <?php foreach ($listaRegiones as $region): ?>
                    <option value="<?= htmlspecialchars($region['id_region']) ?>" <?= ($region['id_region'] == $datos_postulacion['id_region_ejecucion']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($region['nombre_region']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Región de Impacto:</label>
            <select name="id_region_impacto" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione Región --</option>
                <?php foreach ($listaRegiones as $region): ?>
                    <option value="<?= htmlspecialchars($region['id_region']) ?>" <?= ($region['id_region'] == $datos_postulacion['id_region_impacto']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($region['nombre_region']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Presupuesto Solicitado ($):</label>
            <input type="number" name="presupuesto" style="width: 100%; padding: 8px;" value="<?= htmlspecialchars($datos_postulacion['presupuesto']) ?>" required>
        </div>

        <hr style="margin: 30px 0;">

        <h3>2. Datos de la Iniciativa</h3>
        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Nombre de la Iniciativa:</label>
            <input type="text" name="nombre_iniciativa" style="width: 100%; padding: 8px;" value="<?= htmlspecialchars($datos_postulacion['nombre_iniciativa']) ?>" required>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Descripción del Problema:</label>
            <textarea name="descripcion" rows="4" style="width: 100%; padding: 8px;" required><?= htmlspecialchars($datos_postulacion['descripcion']) ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Propuesta de Solución:</label>
            <textarea name="solucion" rows="4" style="width: 100%; padding: 8px;" required><?= htmlspecialchars($datos_postulacion['solucion']) ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Resultados Esperados:</label>
            <textarea name="resultados_esperados" rows="3" style="width: 100%; padding: 8px;" required><?= htmlspecialchars($datos_postulacion['resultados_esperados']) ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Documentos adicionales:</label>
            <textarea name="documentos" rows="3" style="width: 100%; padding: 8px;" required><?= htmlspecialchars($datos_postulacion['documentos']) ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold;">Tipo de Iniciativa:</label>
            <select name="id_tipo_iniciativa" style="width: 100%; padding: 8px;" required>
                <option value="1" <?= ($datos_postulacion['id_tipo_iniciativa'] == 1) ? 'selected' : '' ?>>Nueva</option>
                <option value="2" <?= ($datos_postulacion['id_tipo_iniciativa'] == 2) ? 'selected' : '' ?>>Existente</option>
            </select>
        </div>

        <hr style="margin: 30px 0;">

        <h3>3. Equipo de Trabajo</h3>
        <p style="font-size: 0.9em; color: #555;">Agregue a los profesores y alumnos del proyecto (mínimo 3 profesores y 5 alumnos).</p>

        <div id="contenedor-equipo">
            <?php foreach($equipoActual as $int): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                    <input type="text" name="rut_integrante[]" value="<?= htmlspecialchars($int['rut_integrante']) ?>" placeholder="RUT (ej: 12345678-9)" style="flex: 1 1 150px; padding: 8px;" required>
                    <input type="text" name="nombre_integrante[]" value="<?= htmlspecialchars($int['nombre_integrante']) ?>" placeholder="Nombre completo" style="flex: 1 1 220px; padding: 8px;" required>
                    <input type="text" name="depto_integrante[]" value="<?= htmlspecialchars($int['depto_integrante']) ?>" placeholder="Departamento" style="flex: 1 1 180px; padding: 8px;" required>
                    <input type="email" name="email_integrante[]" value="<?= htmlspecialchars($int['email_integrante']) ?>" placeholder="Correo institucional" style="flex: 1 1 220px; padding: 8px;" required>
                    <input type="number" name="telefono_integrante[]" value="<?= htmlspecialchars($int['telefono_integrante']) ?>" placeholder="Teléfono" style="flex: 1 1 130px; padding: 8px;" required>
                    <select name="id_sede_integrante[]" style="flex: 1 1 220px; padding: 8px;" required>
                        <option value="">Seleccione sede...</option>
                        <option value="1" <?= ($int['id_sede'] == 1) ? 'selected' : '' ?>>Campus Casa Central Valparaíso</option>
                        <option value="2" <?= ($int['id_sede'] == 2) ? 'selected' : '' ?>>Campus San Joaquín</option>
                        <option value="3" <?= ($int['id_sede'] == 3) ? 'selected' : '' ?>>Campus Vitacura</option>
                        <option value="4" <?= ($int['id_sede'] == 4) ? 'selected' : '' ?>>Sede Viña del Mar</option>
                        <option value="5" <?= ($int['id_sede'] == 5) ? 'selected' : '' ?>>Sede Concepción</option>
                    </select>
                    <select name="tipo_integrante[]" style="flex: 1 1 150px; padding: 8px;" required>
                        <option value="">Tipo de persona...</option>
                        <option value="1" <?= ($int['id_tipo_persona'] == 1) ? 'selected' : '' ?>>Profesor</option>
                        <option value="2" <?= ($int['id_tipo_persona'] == 2) ? 'selected' : '' ?>>Estudiante</option>
                    </select>
                    <input type="text" name="rol_integrante[]" value="<?= htmlspecialchars($int['rol_integrante']) ?>" placeholder="Rol en el proyecto" style="flex: 1 1 180px; padding: 8px;" required>
                    <button type="button" onclick="this.parentElement.remove()" style="background-color: #dc3545; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px;">X</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="btn-agregar-integrante" style="padding: 8px 15px; background-color: #28a745; color: white; border: none; cursor: pointer; border-radius: 4px; margin-bottom: 20px;">
            + Agregar Integrante
        </button>

        <hr style="margin: 30px 0;">

        <h3>4. Cronograma de Etapas</h3>
        <p style="font-size: 0.9em; color: #555;">Defina las etapas, plazos y entregables de su proyecto (Su cronograma completo no puede superar las 36 semanas).</p>
        
        <div id="contenedor-etapas">
            <?php foreach($etapasActuales as $et): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                    <input type="text" name="nombre_etapa[]" value="<?= htmlspecialchars($et['nombre_etapa']) ?>" placeholder="Nombre de la etapa" style="flex: 1 1 250px; padding: 8px;" required>
                    <input type="number" name="plazo_etapa[]" value="<?= htmlspecialchars($et['plazo']) ?>" placeholder="Plazo (semanas)" style="flex: 1 1 100px; padding: 8px;" required>
                    <input type="text" name="entregable_etapa[]" value="<?= htmlspecialchars($et['entregable']) ?>" placeholder="Entregable esperado" style="flex: 1 1 300px; padding: 8px;" required>
                    <button type="button" onclick="this.parentElement.remove()" style="background-color: #dc3545; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px;">X</button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="btn-agregar-etapa" style="padding: 8px 15px; background-color: #28a745; color: white; border: none; cursor: pointer; border-radius: 4px; margin-bottom: 30px;">
            + Agregar Etapa
        </button>
        <br>

        <button type="submit" style="padding: 10px 20px; background-color: #7952b3; color: white; border: none; cursor: pointer;">Guardar Cambios</button>
        <a href="detalle_postulacion.php?codigo=<?= urlencode($codigo) ?>" style="margin-left: 15px; text-decoration: none; color: #dc3545;">Cancelar</a>
    </form>
</div>

<script>
document.getElementById('btn-agregar-integrante').addEventListener('click', function() {
    var container = document.getElementById('contenedor-equipo');
    var row = document.createElement('div');
    
    row.style.display = 'flex';
    row.style.flexWrap = 'wrap';
    row.style.gap = '10px';
    row.style.marginBottom = '10px';
    
    row.innerHTML = `
        <input type="text" name="rut_integrante[]" placeholder="RUT (ej: 12345678-9)" style="flex: 1 1 150px; padding: 8px;" required>
        <input type="text" name="nombre_integrante[]" placeholder="Nombre completo" style="flex: 1 1 220px; padding: 8px;" required>
        <input type="text" name="depto_integrante[]" placeholder="Departamento" style="flex: 1 1 180px; padding: 8px;" required>
        <input type="email" name="email_integrante[]" placeholder="Correo institucional" style="flex: 1 1 220px; padding: 8px;" required>
        <input type="number" name="telefono_integrante[]" placeholder="Teléfono" style="flex: 1 1 130px; padding: 8px;" required>
        <select name="id_sede_integrante[]" style="flex: 1 1 220px; padding: 8px;" required>
            <option value="">Seleccione sede...</option>
            <option value="1">Campus Casa Central Valparaíso</option>
            <option value="2">Campus San Joaquín</option>
            <option value="3">Campus Vitacura</option>
            <option value="4">Sede Viña del Mar</option>
            <option value="5">Sede Concepción</option>
        </select>
        <select name="tipo_integrante[]" style="flex: 1 1 150px; padding: 8px;" required>
            <option value="">Tipo de persona...</option>
            <option value="1">Profesor</option>
            <option value="2">Estudiante</option>
        </select>
        <input type="text" name="rol_integrante[]" placeholder="Rol en el proyecto" style="flex: 1 1 180px; padding: 8px;" required>
        <button type="button" onclick="this.parentElement.remove()" style="background-color: #dc3545; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px;">X</button>
    `;
    container.appendChild(row);
});

document.getElementById('btn-agregar-etapa').addEventListener('click', function() {
    var container = document.getElementById('contenedor-etapas');
    var row = document.createElement('div');
    
    row.style.display = 'flex';
    row.style.flexWrap = 'wrap';
    row.style.gap = '10px';
    row.style.marginBottom = '10px';
    
    row.innerHTML = `
        <input type="text" name="nombre_etapa[]" placeholder="Nombre de la etapa" style="flex: 1 1 250px; padding: 8px;" required>
        <input type="number" name="plazo_etapa[]" placeholder="Plazo (semanas)" style="flex: 1 1 100px; padding: 8px;" required>
        <input type="text" name="entregable_etapa[]" placeholder="Entregable esperado" style="flex: 1 1 300px; padding: 8px;" required>
        <button type="button" onclick="this.parentElement.remove()" style="background-color: #dc3545; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px;">X</button>
    `;
    container.appendChild(row);
});
</script>

<?php view('footer') ?>