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

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redireccionar = false;

    // Acción postulante: Enviar postulación
    if (isset($_POST['enviar_postulacion']) && $tipoUsuario == 1) {
        try {
            db()->beginTransaction();

            // Llamadas a los procedimientos almacenados
            $sqlRevisarEquipo = "CALL VerificarRequisitoEquipo(:codigo)";
            $stmtRevisarEq = db()->prepare($sqlRevisarEquipo);
            $stmtRevisarEq->execute(['codigo' => $codigo]);

            $sqlRevisarCronograma = "CALL VerificarRequisitoCronograma(:codigo)";
            $stmtRevisarCr = db()->prepare($sqlRevisarCronograma);
            $stmtRevisarCr->execute(['codigo' => $codigo]);

            // Si todo pasó, marcamos la postulación como "Enviada" (2)
            $sqlEstado = "UPDATE postulacion SET id_estado_postulacion = 2 WHERE codigo_postulacion = :codigo";
            $stmtEstado = db()->prepare($sqlEstado);
            $stmtEstado->execute(['codigo' => $codigo]);

            db()->commit();
            $redireccionar = true;

        } catch (Exception $e) {
            db()->rollBack();
            
            // Extraer el mensaje de error
            $error_crudo = $e->getMessage();
            if ($e->getCode() == '45000' || strpos($error_crudo, '45000') !== false) {
                $partes = explode('1644', $error_crudo);
                if (isset($partes[1])) {
                    $mensaje_error = trim(preg_replace('/\[.*?\]/', '', $partes[1])); 
                } else {
                    $mensaje_error = "La postulación no cumple con los requisitos mínimos.";
                }
            } else {
                $mensaje_error = "Ocurrió un error inesperado al validar: " . $error_crudo;
            }
        }
    }

    // Acción postulante: Eliminar postulación
    if (isset($_POST['eliminar_postulacion']) && $tipoUsuario == 1) {
        
        $stmtDelEq = db()->prepare("DELETE FROM equipo WHERE codigo_postulacion = :codigo");
        $stmtDelEq->execute(['codigo' => $codigo]);

        $stmtDelCron = db()->prepare("DELETE FROM cronograma WHERE codigo_postulacion = :codigo");
        $stmtDelCron->execute(['codigo' => $codigo]);

        $stmtDelIni = db()->prepare("DELETE FROM iniciativa WHERE codigo_postulacion = :codigo");
        $stmtDelIni->execute(['codigo' => $codigo]);

        // Eliminar la postulación principal
        $sqlEliminar = "DELETE FROM postulacion WHERE codigo_postulacion = :codigo";
        $stmtEliminar = db()->prepare($sqlEliminar);
        $stmtEliminar->execute(['codigo' => $codigo]);

        redirect_to("home.php");
        exit();
    }

    // Acción evaluador: Aceptar o rechazar
    if (isset($_POST['decision_evaluador']) && $tipoUsuario == 2) {
        $nuevoEstado = ($_POST['decision_evaluador'] === 'aceptar') ? 4 : 5;
        $sqlEvaluar = "UPDATE postulacion SET id_estado_postulacion = :estado WHERE codigo_postulacion = :codigo";
        $stmtEvaluar = db()->prepare($sqlEvaluar);
        $stmtEvaluar->execute(['estado' => $nuevoEstado, 'codigo' => $codigo]);
        $redireccionar = true;
    }
    
    // Acción administrador: Asignar evaluador
    if (isset($_POST['asignar_evaluador']) && $tipoUsuario == 3) {
        $evaluadorAsignado = $_POST['evaluador'];
        $sqlAsignar = "UPDATE postulacion SET responsable_2 = :evaluador, id_estado_postulacion = 3 WHERE codigo_postulacion = :codigo";
        $stmtAsignar = db()->prepare($sqlAsignar);
        $stmtAsignar->execute(['evaluador' => $evaluadorAsignado, 'codigo' => $codigo]);
        $redireccionar = true;
    }

    // Acción administrador: Cerrar postulación
    if (isset($_POST['cerrar_postulacion']) && $tipoUsuario == 3) {
        $sqlEvaluar = "UPDATE postulacion SET id_estado_postulacion = 6 WHERE codigo_postulacion = :codigo";
        $stmtEvaluar = db()->prepare($sqlEvaluar);
        $stmtEvaluar->execute(['codigo' => $codigo]);
        $redireccionar = true;
    }

    // Recargar página sólo si no hay errores
    if ($redireccionar) {
        redirect_to("detalle_postulacion.php?codigo=" . urlencode($codigo));
        exit();
    }
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
if ($tipoUsuario == 3 && $datos['id_estado_postulacion'] == 2 && empty($datos['responsable_2'])) {
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

<div class="container py-5">
    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <h2 class="fw-bold mb-0">Detalle de Postulación: <span class="text-primary">#<?= htmlspecialchars($datos['numero_postulacion']) ?></span></h2>
                <a href="home.php" class="btn btn-outline-secondary btn-sm">Volver al Inicio</a>
            </div>

            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-danger d-flex align-items-center shadow-sm mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>¡No se pudo enviar la postulación!</strong><br>
                        <?= htmlspecialchars($mensaje_error) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <h4 class="text-secondary fw-bold mt-4 mb-3"><i class="bi bi-info-circle"></i> 1. Datos Generales</h4>
            <div class="row g-3 bg-light p-4 rounded-3 mb-5">
                <div class="col-md-6">
                    <span class="d-block small fw-bold text-muted mb-1">Estado Actual:</span>
                    <?php 
                        $estado = $datos['nombre_estado_postulacion'];
                        $clase_badge = '';
                        if ($estado === 'Aprobada') {
                            $clase_badge = 'bg-success';
                        } elseif ($estado === 'Rechazada'){
                            $clase_badge = 'bg-danger';
                        } else {
                            $clase_badge = 'bg-secondary';
                        }
                    ?>
                    <span class="badge <?= $clase_badge ?> fs-6"><?= htmlspecialchars($estado) ?></span>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="d-block small fw-bold text-muted mb-1">Presupuesto Solicitado:</span>
                    <span class="fs-5 fw-bold text-success">$<?= htmlspecialchars(number_format($datos['presupuesto'], 0, ',', '.')) ?></span>
                </div>
                
                <div class="col-md-4 mt-4">
                    <span class="d-block small fw-bold text-muted">Responsable Académico:</span>
                    <span class="text-dark"><?= htmlspecialchars($datos['responsable_1']) ?></span>
                </div>
                <div class="col-md-4 mt-4">
                    <span class="d-block small fw-bold text-muted">Evaluador Asignado:</span>
                    <span class="text-dark"><?= htmlspecialchars($datos['responsable_2'] ?? 'No asignado') ?></span>
                </div>
                <div class="col-md-4 mt-4">
                    <span class="d-block small fw-bold text-muted">Empresa Vinculada:</span>
                    <span class="text-dark"><?= htmlspecialchars($datos['nombre_empresa']) ?> (RUT: <?= htmlspecialchars($datos['rut_empresa']) ?>)</span>
                </div>

                <div class="col-md-4 mt-3">
                    <span class="d-block small fw-bold text-muted">Sede USM:</span>
                    <span class="text-dark"><?= htmlspecialchars($datos['nombre_sede']) ?></span>
                </div>
                <div class="col-md-4 mt-3">
                    <span class="d-block small fw-bold text-muted">Región de Ejecución:</span>
                    <span class="text-dark"><?= htmlspecialchars($datos['nombre_region_ejecucion']) ?></span>
                </div>
                <div class="col-md-4 mt-3">
                    <span class="d-block small fw-bold text-muted">Región de Impacto:</span>
                    <span class="text-dark"><?= htmlspecialchars($datos['nombre_region_impacto']) ?></span>
                </div>
            </div>

            <h4 class="text-secondary fw-bold mb-3"><i class="bi bi-file-text"></i> 2. Datos de la Iniciativa</h4>
            <div class="row g-4 mb-5">
                <div class="col-md-8">
                    <span class="d-block small fw-bold text-muted">Nombre de la Iniciativa:</span>
                    <p class="mb-0 fs-5 text-dark"><?= nl2br(htmlspecialchars($datos['nombre_iniciativa'] ?? 'No registrada')) ?></p>
                </div>
                <div class="col-md-4">
                    <span class="d-block small fw-bold text-muted">Tipo de Iniciativa:</span>
                    <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($datos['nombre_tipo_iniciativa'] ?? 'No registrada')) ?></p>
                </div>
                
                <div class="col-12">
                    <span class="d-block small fw-bold text-muted">Descripción del Problema:</span>
                    <div class="p-3 bg-light border rounded-3 text-dark"><?= nl2br(htmlspecialchars($datos['descripcion'] ?? 'No registrada')) ?></div>
                </div>

                <div class="col-12">
                    <span class="d-block small fw-bold text-muted">Propuesta de Solución:</span>
                    <div class="p-3 bg-light border rounded-3 text-dark"><?= nl2br(htmlspecialchars($datos['solucion'] ?? 'No registrada')) ?></div>
                </div>

                <div class="col-md-6">
                    <span class="d-block small fw-bold text-muted">Resultados Esperados:</span>
                    <div class="p-3 bg-light border rounded-3 h-100 text-dark"><?= nl2br(htmlspecialchars($datos['resultados_esperados'] ?? 'No registrada')) ?></div>
                </div>

                <div class="col-md-6">
                    <span class="d-block small fw-bold text-muted">Documentos Adicionales:</span>
                    <div class="p-3 bg-light border rounded-3 h-100 text-dark"><?= nl2br(htmlspecialchars($datos['documentos'] ?? 'No registrada')) ?></div>
                </div>
            </div>

            <h4 class="text-secondary fw-bold mb-3"><i class="bi bi-people"></i> 3. Equipo de Trabajo</h4>
            <?php if (count($equipo) > 0): ?>
                <div class="table-responsive mb-5">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center small text-muted text-uppercase">
                            <tr>
                                <th>Nombre Completo</th>
                                <th>RUT</th>
                                <th>Tipo</th>
                                <th>Rol en Proyecto</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipo as $integrante): ?>
                                <tr>
                                    <td class="fw-bold text-secondary"><?= htmlspecialchars($integrante['nombre_integrante']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($integrante['rut_integrante']) ?></td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars($integrante['nombre_tipo_persona']) ?></span></td>
                                    <td><?= htmlspecialchars($integrante['rol_integrante']) ?></td>
                                    <td><a <?= htmlspecialchars($integrante['email_integrante']) ?>><?= htmlspecialchars($integrante['email_integrante']) ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary text-center font-italic mb-5">No hay integrantes registrados en el equipo.</div>
            <?php endif; ?>

            <h4 class="text-secondary fw-bold mb-3"><i class="bi bi-calendar3"></i> 4. Cronograma de Etapas</h4>
            <?php if (count($etapas) > 0): ?>
                <div class="table-responsive mb-5">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center small text-muted text-uppercase">
                            <tr>
                                <th style="width: 30%;">Nombre de la Etapa</th>
                                <th style="width: 15%;">Plazo (Semanas)</th>
                                <th>Entregable Esperado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($etapas as $etapa): ?>
                                <tr>
                                    <td class="fw-bold text-secondary"><?= htmlspecialchars($etapa['nombre_etapa']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($etapa['plazo']) ?></td>
                                    <td><?= htmlspecialchars($etapa['entregable']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary text-center font-italic mb-5">No hay etapas registradas para el cronograma.</div>
            <?php endif; ?>

                <div>
                    <?php if ($tipoUsuario == 1 && $datos['id_estado_postulacion'] == 1): ?>
                        <a href="editar_postulacion.php?codigo=<?= urlencode($codigo) ?>" class="btn btn-primary fw-bold px-4">
                            Editar Postulación
                        </a>
                        <form method="POST" class="d-inline-block">
                            <button type="submit" name="enviar_postulacion" class="btn btn-success fw-bold px-4 me-2">Envíar</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($tipoUsuario == 1): ?>    
                        <form method="POST" class="d-inline-block" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta postulación? Esta acción no se puede deshacer.');">
                            <button type="submit" name="eliminar_postulacion" class="btn btn-danger fw-bold px-4">Eliminar</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($tipoUsuario == 2 && $datos['responsable_2'] == $usuarioActual && $datos['id_estado_postulacion'] == 3): ?>
                        <form method="POST" class="d-inline-block">
                            <button type="submit" name="decision_evaluador" value="aceptar" class="btn btn-success fw-bold px-4 me-2">Aceptar Proyecto</button>
                            <button type="submit" name="decision_evaluador" value="rechazar" class="btn btn-danger fw-bold px-4">Rechazar Proyecto</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($tipoUsuario == 3 && $datos['id_estado_postulacion'] == 2 && empty($datos['responsable_2'])): ?>
                        <form method="POST" class="d-flex align-items-center gap-2">
                            <select name="evaluador" class="form-select" required>
                                <option value="">-- Seleccione Evaluador --</option>
                                <?php foreach ($listaEvaluadores as $ev): ?>
                                    <option value="<?= htmlspecialchars($ev['username']) ?>"><?= htmlspecialchars($ev['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="asignar_evaluador" class="btn btn-primary fw-bold px-4">Asignar</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($tipoUsuario == 3 && ($datos['id_estado_postulacion'] == 4 || $datos['id_estado_postulacion'] == 5)): ?>
                        <form method="POST" class="d-flex align-items-center gap-2">
                            <button type="submit" name="cerrar_postulacion" class="btn btn-primary fw-bold px-4">Cerrar</button>
                        </form>
                    <?php endif; ?>
                </div>

        </div>
    </div>
</div>

<?php view('footer') ?>