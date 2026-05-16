<?php
require_once '../src/bootstrap.php';
require_once 'filtros.php';

require_login();
//Se calcula el presupuesto total gastado en las postulaciones
$stmtPresupuesto = db()->query("SELECT CalcularPresupuestoTotal() AS presupuesto_total");
$resultadoPresupuesto = $stmtPresupuesto->fetch(PDO::FETCH_ASSOC);
$presupuestoTotal = $resultadoPresupuesto['presupuesto_total'];

$usuarioActual = current_user();
$tipoUsuario = $_SESSION['id_rol'] ?? null;

$mensajeNuevoEvaluador = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_evaluador']) && $tipoUsuario == 3) {
    $nuevoEvaluador = trim($_POST['nuevo_evaluador'] ?? '');

    if ($nuevoEvaluador !== '') {
        $sqlAgregarEvaluador = "
            INSERT IGNORE INTO usuario (username, id_rol)
            VALUES (:username, 2)
        ";

        $stmtAgregarEvaluador = db()->prepare($sqlAgregarEvaluador);
        $stmtAgregarEvaluador->execute([
            'username' => $nuevoEvaluador
        ]);

        if ($stmtAgregarEvaluador->rowCount() > 0) {
            $mensajeNuevoEvaluador = "Evaluador agregado correctamente.";
        } else {
            $mensajeNuevoEvaluador = "No se agregó el evaluador. Puede que ese usuario ya exista.";
        }
    } else {
        $mensajeNuevoEvaluador = "Debes ingresar un nombre de usuario.";
    }
}

$mensajeNuevaEmpresa = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_empresa']) && $tipoUsuario == 3) {
    $rutEmpresa = trim($_POST['rut_empresa'] ?? '');
    $nombreEmpresa = trim($_POST['nombre_empresa'] ?? '');
    $nombreRepresentante = trim($_POST['nombre_representante'] ?? '');
    $telefonoRepresentante = trim($_POST['telefono_representante'] ?? '');
    $idTamanio = $_POST['id_tamanio'] ?? '';
    $idConvenioUsm = $_POST['id_convenio_usm'] ?? '';

    if (
        $rutEmpresa !== '' &&
        $nombreEmpresa !== '' &&
        $nombreRepresentante !== '' &&
        $telefonoRepresentante !== '' &&
        $idTamanio !== '' &&
        $idConvenioUsm !== ''
    ) {
        $sqlAgregarEmpresa = "
            INSERT IGNORE INTO empresa (
                rut_empresa,
                id_tamanio,
                nombre_empresa,
                nombre_representante,
                telefono_representante,
                id_convenio_usm
            ) VALUES (
                :rut_empresa,
                :id_tamanio,
                :nombre_empresa,
                :nombre_representante,
                :telefono_representante,
                :id_convenio_usm
            )
        ";

        $stmtAgregarEmpresa = db()->prepare($sqlAgregarEmpresa);
        $stmtAgregarEmpresa->execute([
            'rut_empresa' => $rutEmpresa,
            'id_tamanio' => $idTamanio,
            'nombre_empresa' => $nombreEmpresa,
            'nombre_representante' => $nombreRepresentante,
            'telefono_representante' => $telefonoRepresentante,
            'id_convenio_usm' => $idConvenioUsm
        ]);

        if ($stmtAgregarEmpresa->rowCount() > 0) {
            $mensajeNuevaEmpresa = "Empresa agregada correctamente.";
        } else {
            $mensajeNuevaEmpresa = "No se agregó la empresa. Puede que ya exista una empresa con ese RUT.";
        }
    } else {
        $mensajeNuevaEmpresa = "Debes completar todos los campos de la empresa.";
    }
}

$sedes = db()->query("SELECT nombre_sede FROM sede ORDER BY id_sede ASC")->fetchAll(PDO::FETCH_COLUMN);
$regiones = db()->query("
    SELECT id_region, nombre_region 
    FROM region 
    ORDER BY CASE id_region
        WHEN 15 THEN 1  /* Arica y Parinacota */
        WHEN 1 THEN 2   /* Tarapacá */
        WHEN 2 THEN 3   /* Antofagasta */
        WHEN 3 THEN 4   /* Atacama */
        WHEN 4 THEN 5   /* Coquimbo */
        WHEN 5 THEN 6   /* Valparaíso */
        WHEN 13 THEN 7  /* Metropolitana */
        WHEN 6 THEN 8   /* O'Higgins */
        WHEN 7 THEN 9   /* Maule */
        WHEN 16 THEN 10 /* Ñuble */
        WHEN 8 THEN 11  /* Biobío */
        WHEN 9 THEN 12  /* La Araucanía */
        WHEN 14 THEN 13 /* Los Ríos */
        WHEN 10 THEN 14 /* Los Lagos */
        WHEN 11 THEN 15 /* Aysén */
        WHEN 12 THEN 16 /* Magallanes */
    END ASC
")->fetchAll(PDO::FETCH_ASSOC);
$estados = db()->query("SELECT nombre_estado_postulacion FROM estado_postulacion ORDER BY id_estado_postulacion ASC")->fetchAll(PDO::FETCH_COLUMN);
$tamanios = db()->query("SELECT tamanio FROM tamanio_empresa ORDER BY id_tamanio ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container py-5">
    <div class="card shadow-lg border-0 rounded-4">
        <div class="card-body p-4 p-md-5">
            <?php view('header', ['title' => 'Mis Postulaciones']) ?>
            
            <div class="text-center mb-5">
                <h2 class="fw-bold">Bienvenido, <span class="text-primary"><?= htmlspecialchars($usuarioActual) ?></span></h2>
            </div>

            <h3 class="h5 mb-3 text-secondary">Filtros y Búsqueda</h3>
            
            <!-- Filtros Combinables -->
            <form method="GET" action="home.php" class="bg-light p-4 rounded-3 mb-5">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Sede:</label>
                        <select name="sede" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($sedes as $s): ?>
                                <option value="<?= htmlspecialchars($s) ?>" <?= ($_GET['sede'] ?? '') == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Región Ejecución:</label>
                        <select name="id_region_ejecucion" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($regiones as $r): ?>
                                <option value="<?= $r['id_region'] ?>" <?= ($_GET['id_region_ejecucion'] ?? '') == $r['id_region'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['nombre_region']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Región Impacto:</label>
                        <select name="id_region_impacto" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($regiones as $r): ?>
                                <option value="<?= $r['id_region'] ?>" <?= ($_GET['id_region_impacto'] ?? '') == $r['id_region'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['nombre_region']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Estado:</label>
                        <select name="nombre_estado_postulacion" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($estados as $e): ?>
                                <option value="<?= htmlspecialchars($e) ?>" <?= ($_GET['nombre_estado_postulacion'] ?? '') == $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Tamaño Empresa:</label>
                        <select name="tamanio" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach($tamanios as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= ($_GET['tamanio'] ?? '') == $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Convenio USM:</label>
                        <select name="convenio_usm" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="Sí" <?= ($_GET['convenio_usm'] ?? '') == 'Sí' ? 'selected' : '' ?>>Sí</option>
                            <option value="No" <?= ($_GET['convenio_usm'] ?? '') == 'No' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4 justify-content-end">
                    <a href="home.php" class="btn btn-outline-secondary btn-sm px-4">Limpiar todo</a>
                    <button type="submit" class="btn btn-primary btn-sm px-5">Aplicar Filtros</button>
                </div>
            </form>
            
            <!-- Barra de busqueda -->
            <form method="GET" action="" class="mb-4" >
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white text-primary border-end-0">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="buscar" class="form-control border-start-0" 
                        placeholder="Buscar por N° de postulación, iniciativa o empresa..." 
                        value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
                    <button type="submit" class="btn btn-primary fw-bold px-4">Buscar</button>
                    
                    <?php if (!empty($_GET['buscar'])): ?>
                        <a href="?" class="btn btn-outline-danger px-3 fw-bold" title="Limpiar búsqueda">
                            X
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 mb-0 text-secondary">Tus Postulaciones Asignadas <span class="badge bg-primary"><?= count($mis_postulaciones) ?></span></h3>
            </div>
            
            <?php if (count($mis_postulaciones) > 0): ?>
                <div class="table-responsive mb-5">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>N°</th>
                                <th>Iniciativa</th>
                                <th>Empresa</th>
                                <th>Estado</th>
                                <th>Sede</th>
                                <th>Fecha</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mis_postulaciones as $postulacion): ?>
                                <tr>
                                    <td class="text-center fw-bold text-secondary"><?= htmlspecialchars($postulacion['numero_postulacion']) ?></td>
                                    <td><?= htmlspecialchars($postulacion['nombre_iniciativa']) ?></td>
                                    <td><?= htmlspecialchars($postulacion['nombre_empresa']) ?></td>
                                    <td class="text-center">
                                        <?php 
                                            $estado = $postulacion['nombre_estado_postulacion'];
                                            $clase_badge = '';
                                            if ($estado === 'Aprobada') {
                                                $clase_badge = 'bg-success';
                                            } elseif ($estado === 'Rechazada'){
                                                $clase_badge = 'bg-danger';
                                            } else {
                                                $clase_badge = 'bg-secondary';
                                            }
                                        ?>
                                        <span class="badge <?= $clase_badge ?>">
                                            <?= htmlspecialchars($estado) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($postulacion['nombre_sede']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($postulacion['fecha_postulacion']) ?></td>
                                    <td class="text-center">
                                        <a href="detalle_postulacion.php?codigo=<?= urlencode($postulacion['codigo_postulacion']) ?>" class="btn btn-sm btn-outline-primary">
                                            Ver +
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-5">
                    No tienes postulaciones asignadas que coincidan con tu búsqueda.
                </div>
            <?php endif; ?>
            
            <?php if ($tipoUsuario == 3): ?>
                <div class="alert alert-info mb-4">
                    <h4 class="alert-heading h6 fw-bold">Presupuesto total de postulaciones:</h4>
                    <p class="mb-0 fs-5">$<?= number_format($presupuestoTotal, 0, ',', '.') ?></p>
                </div>
            <?php endif; ?>

            <?php if ($tipoUsuario==1): ?>
                <div class="mb-4">
                    <a href="crear_postulacion.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Crear nueva postulación
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($tipoUsuario == 3): ?>
                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <div class="card h-100 border-primary">
                            <div class="card-header bg-primary text-white fw-bold">Agregar nuevo evaluador</div>
                            <div class="card-body">
                                <?php if (!empty($mensajeNuevoEvaluador)): ?>
                                    <div class="alert alert-secondary small py-2"><?= htmlspecialchars($mensajeNuevoEvaluador) ?></div>
                                <?php endif; ?>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="nuevo_evaluador" class="form-label small">Nombre de usuario:</label>
                                        <input type="text" name="nuevo_evaluador" id="nuevo_evaluador" class="form-control" placeholder="Ej: evaluador3" required>
                                    </div>
                                    <button type="submit" name="agregar_evaluador" class="btn btn-primary w-100">Agregar evaluador</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 border-primary">
                            <div class="card-header bg-primary text-white fw-bold">Agregar nueva empresa</div>
                            <div class="card-body">
                                <?php if (!empty($mensajeNuevaEmpresa)): ?>
                                    <div class="alert alert-secondary small py-2"><?= htmlspecialchars($mensajeNuevaEmpresa) ?></div>
                                <?php endif; ?>
                                <form method="POST" class="row g-2">
                                    <div class="col-6">
                                        <input type="text" name="rut_empresa" class="form-control form-control-sm" placeholder="RUT" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="nombre_empresa" class="form-control form-control-sm" placeholder="Nombre" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="nombre_representante" class="form-control form-control-sm" placeholder="Representante" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="number" name="telefono_representante" class="form-control form-control-sm" placeholder="Teléfono" required>
                                    </div>
                                    <div class="col-6">
                                        <select name="id_tamanio" class="form-select form-select-sm" required>
                                            <option value="">Tamaño...</option>
                                            <option value="1">Pequeña</option>
                                            <option value="2">Mediana</option>
                                            <option value="3">Grande</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <select name="id_convenio_usm" class="form-select form-select-sm" required>
                                            <option value="">Convenio...</option>
                                            <option value="1">Sí</option>
                                            <option value="2">No</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button type="submit" name="agregar_empresa" class="btn btn-primary btn-sm w-100">Agregar empresa</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <hr>
            <div class="text-end mt-4">
                <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</div>
<?php view('footer') ?>