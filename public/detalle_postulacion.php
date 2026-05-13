<?php
require_once '../src/bootstrap.php';
require_once '../src/inc/auth.php';
require_login();

$codigo = $_GET['codigo'] ?? null;
$usuarioActual = current_user();
$tipoUsuario = $_SESSION['id_rol'] ?? null;

if (!$codigo) {
    die("Error: No se especificó ninguna postulación.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Acción evaluador: Aceptar o rechazar
    if (isset($_POST['decision_evaluador']) && $tipoUsuario == 2) {
        $nuevoEstado = ($_POST['decision_evaluador'] === 'aceptar') ? 3 : 4;
        $sqlEvaluar = "UPDATE postulacion SET id_estado_postulacion = :estado WHERE codigo_postulacion = :codigo";
        $stmtEvaluar = db()->prepare($sqlEvaluar);
        $stmtEvaluar->execute(['estado' => $nuevoEstado, 'codigo' => $codigo]);
    }
    
    // Acción administrador: Asignar evaluador
    if (isset($_POST['asignar_evaluador']) && $tipoUsuario == 3) {
        $evaluadorAsignado = $_POST['evaluador'];
        $sqlAsignar = "UPDATE postulacion SET responsable_2 = :evaluador, id_estado_postulacion = 2 WHERE codigo_postulacion = :codigo";
        $stmtAsignar = db()->prepare($sqlAsignar);
        $stmtAsignar->execute(['evaluador' => $evaluadorAsignado, 'codigo' => $codigo]);
    }

    // Recargar página
    redirect_to("detalle_postulacion.php?codigo=" . urlencode($codigo));
}

// Mostrar detalles principales de la postulación
$sql = "SELECT *
        FROM VistaMisPostulaciones
        WHERE codigo_postulacion = :codigo";

// Si no es administrador, exigimos que sea uno de los responsables
if ($tipoUsuario != 3) {
    $sql .= " AND (VistaMisPostulaciones.responsable_1 = :user OR VistaMisPostulaciones.responsable_2 = :user)";
}

$stmt = db()->prepare($sql);
$parametros = ['codigo' => $codigo];
if ($tipoUsuario != 3) {
    $parametros['user'] = $usuarioActual;
}
$stmt->execute($parametros);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$datos) {
    die("Acceso denegado: Esta postulación no existe o no tienes permisos para verla.");
}

// Si el usuario es admin, buscamos la lista de evaluadores para elegir
$listaEvaluadores = [];
if ($tipoUsuario == 3 && $datos['id_estado_postulacion'] == 1 && empty($datos['responsable_2'])) {
    $stmtEval = db()->query("SELECT username FROM usuario WHERE id_rol = 2");
    $listaEvaluadores = $stmtEval->fetchAll(PDO::FETCH_ASSOC);
}

// Traer el equipo de trabajo
$sqlEquipo = "SELECT integrante.rut_integrante, integrante.nombre_integrante, integrante.email_integrante, 
                     tipo_persona.nombre_tipo_persona, equipo.rol_integrante
              FROM equipo
              INNER JOIN integrante ON equipo.rut_integrante = integrante.rut_integrante
              INNER JOIN tipo_persona ON integrante.id_tipo_persona = tipo_persona.id_tipo_persona
              WHERE equipo.codigo_postulacion = :codigo";
$stmtEquipo = db()->prepare($sqlEquipo);
$stmtEquipo->execute(['codigo' => $codigo]);
$equipo = $stmtEquipo->fetchAll(PDO::FETCH_ASSOC);

// Traer el cronograma y sus etapas
$sqlEtapas = "SELECT etapa.nombre_etapa, etapa.plazo, etapa.entregable
              FROM cronograma
              INNER JOIN etapa ON cronograma.cod_etapa = etapa.cod_etapa
              WHERE cronograma.codigo_postulacion = :codigo";
$stmtEtapas = db()->prepare($sqlEtapas);
$stmtEtapas->execute(['codigo' => $codigo]);
$etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

?>

<?php view('header', ['title' => 'Detalle de Postulación']) ?>

<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    
    <h2>Detalle de Postulación: <?= htmlspecialchars($datos['numero_postulacion']) ?></h2>
    <br>
    
    <h3>1. Datos Generales</h3>
    <ul>
        <li><strong>Estado Actual:</strong> <?= htmlspecialchars($datos['nombre_estado_postulacion']) ?></li>
        <li><strong>Responsable Académico:</strong> <?= htmlspecialchars($datos['responsable_1']) ?></li>
        <li><strong>Evaluador Asignado:</strong> <?= htmlspecialchars($datos['responsable_2'] ?? 'No asignado') ?></li>
        <li><strong>Región de ejecución:</strong> <?= htmlspecialchars($datos['nombre_region_ejecucion']) ?></li>
        <li><strong>Region de impacto:</strong> <?= htmlspecialchars($datos['nombre_region_impacto']) ?></li>
        <li><strong>Sede USM:</strong> <?= htmlspecialchars($datos['nombre_sede']) ?></li>
        <li><strong>Empresa:</strong> <?= htmlspecialchars($datos['nombre_empresa']) ?> (RUT: <?= htmlspecialchars($datos['rut_empresa']) ?>)</li>
        <li><strong>Presupuesto:</strong> $<?= htmlspecialchars(number_format($datos['presupuesto'], 0, ',', '.')) ?></li>
    </ul>

    <hr style="margin: 30px 0;">

    <h3>2. Datos de la Iniciativa</h3>
    <div style="margin-bottom: 15px;">
        <strong>Nombre de la Iniciativa:</strong>
        <p><?= nl2br(htmlspecialchars($datos['nombre_iniciativa'] ?? 'No registrada')) ?></p>
    </div>
    
    <div style="margin-bottom: 15px;">
        <strong>Descripción del Problema:</strong>
        <p><?= nl2br(htmlspecialchars($datos['descripcion'] ?? 'No registrada')) ?></p>
    </div>

    <div style="margin-bottom: 15px;">
        <strong>Propuesta de Solución:</strong>
        <p><?= nl2br(htmlspecialchars($datos['solucion'] ?? 'No registrada')) ?></p>
    </div>

    <div style="margin-bottom: 15px;">
        <strong>Resultados Esperados:</strong>
        <p><?= nl2br(htmlspecialchars($datos['resultados_esperados'] ?? 'No registrada')) ?></p>
    </div>

    <div style="margin-bottom: 15px;">
        <strong>Documentos Adicionales:</strong>
        <p><?= nl2br(htmlspecialchars($datos['documentos'] ?? 'No registrada')) ?></p>
    </div>

    <div style="margin-bottom: 15px;">
        <strong>Tipo de Iniciativa:</strong>
        <p><?= nl2br(htmlspecialchars($datos['nombre_tipo_iniciativa'] ?? 'No registrada')) ?></p>
    </div>

    <hr style="margin: 30px 0;">

    <!-- NUEVA SECCIÓN: EQUIPO -->
    <h3>3. Equipo de Trabajo</h3>
    <?php if (count($equipo) > 0): ?>
        <table border="1" style="width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 20px;">
            <thead style="background-color: #f2f2f2;">
                <tr>
                    <th style="padding: 10px;">Nombre</th>
                    <th style="padding: 10px;">RUT</th>
                    <th style="padding: 10px;">Tipo</th>
                    <th style="padding: 10px;">Rol en Proyecto</th>
                    <th style="padding: 10px;">Email</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipo as $integrante): ?>
                    <tr>
                        <td style="padding: 10px;"><?= htmlspecialchars($integrante['nombre_integrante']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($integrante['rut_integrante']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($integrante['nombre_tipo_persona']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($integrante['rol_integrante']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($integrante['email_integrante']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">No hay integrantes registrados en el equipo.</p>
    <?php endif; ?>

    <hr style="margin: 30px 0;">

    <!-- NUEVA SECCIÓN: CRONOGRAMA -->
    <h3>4. Cronograma de Etapas</h3>
    <?php if (count($etapas) > 0): ?>
        <table border="1" style="width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 20px;">
            <thead style="background-color: #f2f2f2;">
                <tr>
                    <th style="padding: 10px;">Nombre de la Etapa</th>
                    <th style="padding: 10px; width: 15%;">Plazo (Días)</th>
                    <th style="padding: 10px;">Entregable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($etapas as $etapa): ?>
                    <tr>
                        <td style="padding: 10px;"><?= htmlspecialchars($etapa['nombre_etapa']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($etapa['plazo']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($etapa['entregable']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #666; font-style: italic;">No hay etapas registradas para el cronograma.</p>
    <?php endif; ?>

    <br>

     <!-- Acciones por rol -->
    <div style="padding: 15px; margin-bottom: 20px; background-color: #f8f9fa; border-radius: 5px;">

        <!-- Postulante: Botón de Edición -->
        <?php if ($tipoUsuario == 1): ?>
            <a href="editar_postulacion.php?codigo=<?= urlencode($codigo) ?>" style="padding: 10px 15px; background-color: #7952b3; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">
                Editar Detalles de Postulación
            </a>
        <?php endif; ?>

        <!-- Evaluador: Botones de Aceptar/Rechazar -->
        <?php if ($tipoUsuario == 2 && $datos['responsable_2'] == $usuarioActual && $datos['id_estado_postulacion'] == 2): ?>
            <form method="POST" style="display: inline-block;">
                <button type="submit" name="decision_evaluador" value="aceptar" style="background-color: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;">Aceptar Proyecto</button>
                <button type="submit" name="decision_evaluador" value="rechazar" style="background-color: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;">Rechazar Proyecto</button>
            </form>
        <?php endif; ?>

        <!-- Administrador: Formulario de Asignación -->
        <?php if ($tipoUsuario == 3 && $datos['id_estado_postulacion'] == 1 && empty($datos['responsable_2'])): ?>
            <form method="POST" style="display: inline-block;">
                <label style="font-weight: bold; margin-right: 10px;">Asignar Evaluador:</label>
                <select name="evaluador" required style="padding: 8px; border-radius: 4px;">
                    <option value="">-- Seleccione Evaluador --</option>
                    <?php foreach ($listaEvaluadores as $ev): ?>
                        <option value="<?= htmlspecialchars($ev['username']) ?>"><?= htmlspecialchars($ev['username']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="asignar_evaluador" style="background-color: #7952b3; color: white; border: none; padding: 9px 15px; border-radius: 4px; cursor: pointer;">Asignar</button>
            </form>
        <?php endif; ?>

    </div>
    <br>
    <a href="home.php" style="text-decoration: none;"> Volver</a>

</div>

<?php view('footer') ?>