<?php
require_once '../src/bootstrap.php';
require_once '../src/inc/auth.php';
require_login();

$codigo = $_GET['codigo'] ?? null;
$usuarioActual = current_user();

if (!$codigo) {
    die("Error: No se especificó ninguna postulación a editar.");
}

$sql = "SELECT postulacion.*, iniciativa.nombre_iniciativa, iniciativa.descripcion, iniciativa.solucion, iniciativa.resultados_esperados, iniciativa.documentos, iniciativa.id_tipo_iniciativa 
        FROM postulacion 
        LEFT JOIN iniciativa ON postulacion.codigo_postulacion = iniciativa.codigo_postulacion
        WHERE postulacion.codigo_postulacion = :codigo 
        AND (postulacion.responsable_1 = :user OR postulacion.responsable_2 = :user)";

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

        $sqlRevisarEquipo = "CALL VerificarRequisitoEquipo(:codigo)";
        $stmtRevisarEq = db()->prepare($sqlRevisarEquipo);
        $stmtRevisarEq->execute(['codigo' => $codigo]);

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

<div class="container py-5">
    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <h2 class="fw-bold mb-0">Editando Postulación: <span class="text-primary">#<?= htmlspecialchars($datos_postulacion['numero_postulacion']) ?></span></h2>
            </div>
            
            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>
                        <strong>¡Atención!</strong> <?= htmlspecialchars($mensaje_error) ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="editar_postulacion.php?codigo=<?= urlencode($codigo) ?>">
                
                <h4 class="text-secondary fw-bold mt-4 mb-3"><i class="bi bi-building"></i> 1. Datos Generales</h4>
                <div class="row g-3 bg-light p-3 rounded-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Empresa Externa Asignada:</label>
                        <select name="rut_empresa" class="form-select" required>
                            <option value="">-- Seleccione una Empresa --</option>
                            <?php foreach ($listaEmpresas as $empresa): ?>
                                <option value="<?= htmlspecialchars($empresa['rut_empresa']) ?>" <?= ($empresa['rut_empresa'] === $datos_postulacion['rut_empresa']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($empresa['nombre_empresa']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Sede USM Responsable:</label>
                        <select name="id_sede" class="form-select" required>
                            <option value="">-- Seleccione Sede --</option>
                            <?php foreach ($listaSedes as $sede): ?>
                                <option value="<?= htmlspecialchars($sede['id_sede']) ?>" <?= ($sede['id_sede'] == $datos_postulacion['id_sede']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sede['nombre_sede']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Región de Ejecución:</label>
                        <select name="id_region_ejecucion" class="form-select" required>
                            <option value="">-- Seleccione Región --</option>
                            <?php foreach ($listaRegiones as $region): ?>
                                <option value="<?= htmlspecialchars($region['id_region']) ?>" <?= ($region['id_region'] == $datos_postulacion['id_region_ejecucion']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($region['nombre_region']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Región de Impacto:</label>
                        <select name="id_region_impacto" class="form-select" required>
                            <option value="">-- Seleccione Región --</option>
                            <?php foreach ($listaRegiones as $region): ?>
                                <option value="<?= htmlspecialchars($region['id_region']) ?>" <?= ($region['id_region'] == $datos_postulacion['id_region_impacto']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($region['nombre_region']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Presupuesto Solicitado ($):</label>
                        <input type="number" name="presupuesto" class="form-control" value="<?= htmlspecialchars($datos_postulacion['presupuesto']) ?>" required>
                    </div>
                </div>

                <h4 class="text-secondary fw-bold mt-5 mb-3"><i class="bi bi-file-text"></i> 2. Datos de la Iniciativa</h4>
                <div class="row g-3 mb-4">
                    <div class="col-md-9">
                        <label class="form-label fw-bold small text-muted">Nombre de la Iniciativa:</label>
                        <input type="text" name="nombre_iniciativa" class="form-control" value="<?= htmlspecialchars($datos_postulacion['nombre_iniciativa']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Tipo de Iniciativa:</label>
                        <select name="id_tipo_iniciativa" class="form-select" required>
                            <option value="1" <?= ($datos_postulacion['id_tipo_iniciativa'] == 1) ? 'selected' : '' ?>>Nueva</option>
                            <option value="2" <?= ($datos_postulacion['id_tipo_iniciativa'] == 2) ? 'selected' : '' ?>>Existente</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Descripción del Problema:</label>
                        <textarea name="descripcion" class="form-control" rows="4" required><?= htmlspecialchars($datos_postulacion['descripcion']) ?></textarea>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Propuesta de Solución:</label>
                        <textarea name="solucion" class="form-control" rows="4" required><?= htmlspecialchars($datos_postulacion['solucion']) ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Resultados Esperados:</label>
                        <textarea name="resultados_esperados" class="form-control" rows="3" required><?= htmlspecialchars($datos_postulacion['resultados_esperados']) ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Documentos adicionales:</label>
                        <textarea name="documentos" class="form-control" rows="3" required><?= htmlspecialchars($datos_postulacion['documentos']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-5 mb-3">
                    <h4 class="text-secondary fw-bold mb-0"><i class="bi bi-people"></i> 3. Equipo de Trabajo</h4>
                    <button type="button" id="btn-agregar-integrante" class="btn btn-success btn-sm fw-bold">
                        + Agregar Integrante
                    </button>
                </div>
                <p class="text-muted small mb-3">Agregue a los profesores y alumnos del proyecto (mínimo 3 profesores y 5 alumnos).</p>

                <div id="contenedor-equipo">
                    <?php foreach($equipoActual as $int): ?>
                        <div class="row g-2 mb-3 align-items-end bg-light border p-3 rounded-3 position-relative">
                            <div class="col-md-2">
                                <input type="text" name="rut_integrante[]" class="form-control form-control-sm" value="<?= htmlspecialchars($int['rut_integrante']) ?>" placeholder="RUT (ej: 12345678-9)" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="nombre_integrante[]" class="form-control form-control-sm" value="<?= htmlspecialchars($int['nombre_integrante']) ?>" placeholder="Nombre completo" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="depto_integrante[]" class="form-control form-control-sm" value="<?= htmlspecialchars($int['depto_integrante']) ?>" placeholder="Departamento" required>
                            </div>
                            <div class="col-md-4">
                                <input type="email" name="email_integrante[]" class="form-control form-control-sm" value="<?= htmlspecialchars($int['email_integrante']) ?>" placeholder="Correo electrónico" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="telefono_integrante[]" class="form-control form-control-sm" value="<?= htmlspecialchars($int['telefono_integrante']) ?>" placeholder="Teléfono" required>
                            </div>
                            <div class="col-md-3">
                                <select name="id_sede_integrante[]" class="form-select form-select-sm" required>
                                    <option value="">Sede...</option>
                                    <option value="1" <?= ($int['id_sede'] == 1) ? 'selected' : '' ?>>Casa Central Valparaíso</option>
                                    <option value="2" <?= ($int['id_sede'] == 2) ? 'selected' : '' ?>>Campus San Joaquín</option>
                                    <option value="3" <?= ($int['id_sede'] == 3) ? 'selected' : '' ?>>Campus Vitacura</option>
                                    <option value="4" <?= ($int['id_sede'] == 4) ? 'selected' : '' ?>>Sede Viña del Mar</option>
                                    <option value="5" <?= ($int['id_sede'] == 5) ? 'selected' : '' ?>>Sede Concepción</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="tipo_integrante[]" class="form-select form-select-sm" required>
                                    <option value="">Tipo...</option>
                                    <option value="1" <?= ($int['id_tipo_persona'] == 1) ? 'selected' : '' ?>>Profesor</option>
                                    <option value="2" <?= ($int['id_tipo_persona'] == 2) ? 'selected' : '' ?>>Estudiante</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="rol_integrante[]" class="form-control form-control-sm" value="<?= htmlspecialchars($int['rol_integrante']) ?>" placeholder="Rol en el proyecto" required>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" onclick="this.closest('.row').remove()" class="btn btn-danger btn-sm w-100" title="Eliminar Integrante">X</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-5 mb-3">
                    <h4 class="text-secondary fw-bold mb-0"><i class="bi bi-calendar3"></i> 4. Cronograma de Etapas</h4>
                    <button type="button" id="btn-agregar-etapa" class="btn btn-success btn-sm fw-bold">
                        + Agregar Etapa
                    </button>
                </div>
                <p class="text-muted small mb-3">Defina las etapas, plazos y entregables de su proyecto (Su cronograma completo no puede superar las 36 semanas).</p>
                
                <div id="contenedor-etapas">
                    <?php foreach($etapasActuales as $et): ?>
                        <div class="row g-2 mb-2 align-items-center">
                            <div class="col-md-4">
                                <input type="text" name="nombre_etapa[]" class="form-control form-control-sm" value="<?= htmlspecialchars($et['nombre_etapa']) ?>" placeholder="Nombre de la etapa" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="plazo_etapa[]" class="form-control form-control-sm" value="<?= htmlspecialchars($et['plazo']) ?>" placeholder="Semanas" required>
                            </div>
                            <div class="col-md-5">
                                <input type="text" name="entregable_etapa[]" class="form-control form-control-sm" value="<?= htmlspecialchars($et['entregable']) ?>" placeholder="Entregable esperado" required>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" onclick="this.closest('.row').remove()" class="btn btn-danger btn-sm w-100">X</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-5 d-flex gap-3 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold">Guardar Cambios</button>
                    <a href="detalle_postulacion.php?codigo=<?= urlencode($codigo) ?>" class="btn btn-outline-danger btn-lg px-4">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// El código JS se adaptó para generar exactamente las mismas clases de Bootstrap que usamos arriba
document.getElementById('btn-agregar-integrante').addEventListener('click', function() {
    var container = document.getElementById('contenedor-equipo');
    var row = document.createElement('div');
    row.className = 'row g-2 mb-3 align-items-end bg-light border p-3 rounded-3';
    
    row.innerHTML = `
        <div class="col-md-2"><input type="text" name="rut_integrante[]" class="form-control form-control-sm" placeholder="RUT (ej: 12345678-9)" required></div>
        <div class="col-md-3"><input type="text" name="nombre_integrante[]" class="form-control form-control-sm" placeholder="Nombre completo" required></div>
        <div class="col-md-3"><input type="text" name="depto_integrante[]" class="form-control form-control-sm" placeholder="Departamento" required></div>
        <div class="col-md-4"><input type="email" name="email_integrante[]" class="form-control form-control-sm" placeholder="Correo electrónico" required></div>
        <div class="col-md-2"><input type="number" name="telefono_integrante[]" class="form-control form-control-sm" placeholder="Teléfono" required></div>
        <div class="col-md-3">
            <select name="id_sede_integrante[]" class="form-select form-select-sm" required>
                <option value="">Sede...</option>
                <option value="1">Casa Central Valparaíso</option>
                <option value="2">Campus San Joaquín</option>
                <option value="3">Campus Vitacura</option>
                <option value="4">Sede Viña del Mar</option>
                <option value="5">Sede Concepción</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="tipo_integrante[]" class="form-select form-select-sm" required>
                <option value="">Tipo...</option>
                <option value="1">Profesor</option>
                <option value="2">Estudiante</option>
            </select>
        </div>
        <div class="col-md-4"><input type="text" name="rol_integrante[]" class="form-control form-control-sm" placeholder="Rol en el proyecto" required></div>
        <div class="col-md-1 text-end"><button type="button" onclick="this.closest('.row').remove()" class="btn btn-danger btn-sm w-100">X</button></div>
    `;
    container.appendChild(row);
});

document.getElementById('btn-agregar-etapa').addEventListener('click', function() {
    var container = document.getElementById('contenedor-etapas');
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-center';
    
    row.innerHTML = `
        <div class="col-md-4"><input type="text" name="nombre_etapa[]" class="form-control form-control-sm" placeholder="Nombre de la etapa" required></div>
        <div class="col-md-2"><input type="number" name="plazo_etapa[]" class="form-control form-control-sm" placeholder="Semanas" required></div>
        <div class="col-md-5"><input type="text" name="entregable_etapa[]" class="form-control form-control-sm" placeholder="Entregable esperado" required></div>
        <div class="col-md-1 text-end"><button type="button" onclick="this.closest('.row').remove()" class="btn btn-danger btn-sm w-100">X</button></div>
    `;
    container.appendChild(row);
});
</script>

<?php view('footer') ?>