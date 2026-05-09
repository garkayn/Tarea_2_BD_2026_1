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

    // Acción evaluador: Aceptar o Rechazar
    if (isset($_POST['decision_evaluador']) && $tipoUsuario == 2) {
        // Asumimos que Estado 3 es Aceptada y Estado 4 es Rechazada
        $nuevoEstado = ($_POST['decision_evaluador'] === 'aceptar') ? 4 : 5;
        $sqlEvaluar = "UPDATE postulacion SET id_estado_postulacion = :estado WHERE codigo_postulacion = :codigo";
        $stmtEvaluar = db()->prepare($sqlEvaluar);
        $stmtEvaluar->execute(['estado' => $nuevoEstado, 'codigo' => $codigo]);
    }
    
    // Acción administrador: Asignar Evaluador
    if (isset($_POST['asignar_evaluador']) && $tipoUsuario == 3) {
        $evaluadorAsignado = $_POST['evaluador'];
        $sqlAsignar = "UPDATE postulacion SET responsable_2 = :evaluador WHERE codigo_postulacion = :codigo";
        $stmtAsignar = db()->prepare($sqlAsignar);
        $stmtAsignar->execute(['evaluador' => $evaluadorAsignado, 'codigo' => $codigo]);
    }

    // Recargar página
    redirect_to("detalle_postulacion.php?codigo=" . urlencode($codigo));
}

// Mostrar detalles postulación
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
if ($tipoUsuario == 3 && $datos['id_estado_postulacion'] == 2) {
    $stmtEval = db()->query("SELECT username FROM usuario WHERE id_rol = 2");
    $listaEvaluadores = $stmtEval->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php view('header', ['title' => 'Detalle de Postulación']) ?>

<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    
    <h2>Detalle de Postulación: <?= htmlspecialchars($datos['numero_postulacion']) ?></h2>
    <br>
    <h3>1. Datos Generales</h3>
    <ul>
        <li><strong>Estado Actual:</strong> <?= htmlspecialchars($datos['nombre_estado_postulacion']) ?></li>
        <li><strong>Responsable Académico:</strong> <?= htmlspecialchars($datos['responsable_1']) ?></li>
        <li><strong>Evaluador Asignado:</strong> <?= htmlspecialchars($datos['responsable_2'] ?? 'Ninguno aún') ?></li>
        <li><strong>Región de ejecución:</strong> <?= htmlspecialchars($datos['nombre_region_ejecucion']) ?></li>
        <li><strong>Region de impacto:</strong> <?= htmlspecialchars($datos['nombre_region_impacto']) ?></li>
        <li><strong>Sede USM:</strong> <?= htmlspecialchars($datos['nombre_sede']) ?></li>
        <li><strong>Empresa:</strong> <?= htmlspecialchars($datos['nombre_empresa']) ?> (RUT: <?= htmlspecialchars($datos['rut_empresa']) ?>)</li>
        <li><strong>Presupuesto:</strong> $<?= htmlspecialchars(number_format($datos['presupuesto'], 0, ',', '.')) ?></li>
    </ul>

    <hr>

    <h3>2. Datos de la Iniciativa</h3>
    <div style="margin-bottom: 15px;">
        <strong>Nombre de la Iniciativa:</strong>
        <p><?= htmlspecialchars($datos['nombre_iniciativa'] ?? 'No registrada') ?></p>
    </div>
    
    <div style="margin-bottom: 15px;">
        <strong>Descripción del Problema:</strong>
        <p><?= htmlspecialchars($datos['descripcion'] ?? 'No registrada') ?></p>
    </div>

    <div style="margin-bottom: 15px;">
        <strong>Propuesta de Solución:</strong>
        <p><?= htmlspecialchars($datos['solucion'] ?? 'No registrada') ?></p>
    </div>

    <div style="margin-bottom: 15px;">
        <strong>Resultados Esperados:</strong>
        <p><?= htmlspecialchars($datos['resultados_esperados'] ?? 'No registrada') ?></p>
    </div>

    <br>

     <!-- Acciones por rol -->
    <div style="padding: 15px; margin-bottom: 20px;">

        <!-- Postulante -->
        <?php if ($tipoUsuario == 1): ?>
            <a href="editar_postulacion.php?codigo=<?= urlencode($codigo) ?>" style="padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none;">
                Editar Postulación
            </a>
        <?php endif; ?>

        <!-- Evaluador -->
        <?php if ($tipoUsuario == 2 && $datos['responsable_2'] == $usuarioActual && $datos['id_estado_postulacion'] == 2): ?>
            <form method="POST" style="display: inline-block;">
                <button type="submit" name="decision_evaluador" value="aceptar" style="background-color: #28a745; color: white; border: none; padding: 10px 15px;">Aceptar Proyecto</button>
                <button type="submit" name="decision_evaluador" value="rechazar" style="background-color: #dc3545; color: white; border: none; padding: 10px 15px;">Rechazar Proyecto</button>
            </form>
        <?php endif; ?>

        <!-- Administrador -->
        <?php if ($tipoUsuario == 3 && $datos['id_estado_postulacion'] == 1): ?>
            <form method="POST" style="display: inline-block;">
                <label>Asignar Evaluador:</label>
                <select name="evaluador" required>
                    <option value="">-- Seleccione Evaluador --</option>
                    <?php foreach ($listaEvaluadores as $ev): ?>
                        <option value="<?= htmlspecialchars($ev['username']) ?>"><?= htmlspecialchars($ev['username']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="asignar_evaluador" style="background-color: #28a745; color: white; border: none; padding: 8px;">Asignar</button>
            </form>
        <?php endif; ?>

    </div>
    <br>
    <a href="home.php">Volver</a>

</div>

<?php view('footer') ?>