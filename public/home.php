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

// Obtener opciones únicas para los filtros desde la vista o tablas
$sedes = db()->query("SELECT DISTINCT nombre_sede FROM VistaMisPostulaciones WHERE nombre_sede IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$regiones = db()->query("SELECT DISTINCT nombre_region_ejecucion FROM VistaMisPostulaciones WHERE nombre_region_ejecucion IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$estados = db()->query("SELECT DISTINCT nombre_estado_postulacion FROM VistaMisPostulaciones WHERE nombre_estado_postulacion IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$tamanios = db()->query("SELECT DISTINCT tamanio FROM tamanio_empresa")->fetchAll(PDO::FETCH_COLUMN);

/*
$sql = "SELECT * FROM VistaMisPostulaciones";
$parametros = [];

if ($tipoUsuario != 3) {
    // Consulta solo las postulaciones asignadas al usuario
    $sql .= " WHERE responsable_1 = :user1 OR responsable_2 = :user2";
    $parametros['user1'] = $usuarioActual;
    $parametros['user2'] = $usuarioActual;
}

$stmt = db()->prepare($sql);
$stmt->execute($parametros);
$mis_postulaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
*/
?>

<?php view('header', ['title' => 'Mis Postulaciones']) ?>

<h2>Bienvenido, <?= htmlspecialchars($usuarioActual) ?></h2>
<?php if ($tipoUsuario == 3): ?>
    <p>
        <strong>Presupuesto total de postulaciones:</strong>
        $<?= number_format($presupuestoTotal, 0, ',', '.') ?>
    </p>
<?php endif; ?>
<br>
<h3>Tus Postulaciones Asignadas (<?= count($mis_postulaciones) ?>)</h3>

<br>
<h3>Filtros de Búsqueda</h3>
<form method="GET" action="home.php" style="background: #f4f4f4; padding: 15px; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
    
    <div>
        <label style="display:block; font-size: 0.8em;">Sede:</label>
        <select name="sede">
            <option value="">Todas</option>
            <?php foreach($sedes as $s): ?>
                <option value="<?= $s ?>" <?= ($_GET['sede'] ?? '') == $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block; font-size: 0.8em;">Región:</label>
        <select name="region">
            <option value="">Todas</option>
            <?php foreach($regiones as $r): ?>
                <option value="<?= $r ?>" <?= ($_GET['region'] ?? '') == $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block; font-size: 0.8em;">Estado:</label>
        <select name="nombre_estado_postulacion">
            <option value="">Todos</option>
            <?php foreach($estados as $e): ?>
                <option value="<?= $e ?>" <?= ($_GET['estado'] ?? '') == $e ? 'selected' : '' ?>><?= $e ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block; font-size: 0.8em;">Tamaño Empresa:</label>
        <select name="tamanio">
            <option value="">Todos</option>
            <?php foreach($tamanios as $t): ?>
                <option value="<?= $t ?>" <?= ($_GET['tamanio'] ?? '') == $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label style="display:block; font-size: 0.8em;">Convenio USM:</label>
        <select name="convenio_usm">
            <option value="">Todos</option>
            <option value="Sí" <?= ($_GET['convenio'] ?? '') == 'Sí' ? 'selected' : '' ?>>Sí</option>
            <option value="No" <?= ($_GET['convenio'] ?? '') == 'No' ? 'selected' : '' ?>>No</option>
        </select>
    </div>

    <button type="submit" style="background: #7952b3; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px;">Filtrar</button>
    <a href="home.php" style="font-size: 0.8em; color: #666;">Limpiar</a>
</form>

<br>
<!-- Barra de busqueda -->
<div style="margin-bottom: 20px;">

    <label for="search"><strong>Buscar postulación:</strong></label>
    <input type="text" id="search" placeholder="Escribe un número, iniciativa o empresa..." style="padding: 8px; width: 310px;" />
</div>

<?php if (count($mis_postulaciones) > 0): ?>
    <table border="1">
        <thead>
            <tr>
                <th>Número de postulación</th>
                <th>Iniciativa</th>
                <th>Empresa</th>
                <th>Estado</th>
                <th>Sede</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mis_postulaciones as $postulacion): ?>
                <tr>
                    <td><?= htmlspecialchars($postulacion['numero_postulacion']) ?></td>
                    <td><?= htmlspecialchars($postulacion['nombre_iniciativa']) ?></td>
                    <td><?= htmlspecialchars($postulacion['nombre_empresa']) ?></td>
                    <td><?= htmlspecialchars($postulacion['nombre_estado_postulacion']) ?></td>
                    <td><?= htmlspecialchars($postulacion['nombre_sede']) ?></td>
                    <td><?= htmlspecialchars($postulacion['fecha_postulacion']) ?></td>
                    <td>
                        <a href="detalle_postulacion.php?codigo=<?= urlencode($postulacion['codigo_postulacion']) ?>">
                            Ver +
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No tienes postulaciones asignadas en este momento.</p>
<?php endif; ?>
<br><br>

<?php if ($tipoUsuario==1): ?>
<a href="crear_postulacion.php">
    Crear nueva postulación
</a>
<?php endif; ?>

<?php if ($tipoUsuario == 3): ?>
    <hr>

    <h3>Agregar nuevo evaluador</h3>

    <?php if (!empty($mensajeNuevoEvaluador)): ?>
        <p><strong><?= htmlspecialchars($mensajeNuevoEvaluador) ?></strong></p>
    <?php endif; ?>

    <form method="POST">
        <label for="nuevo_evaluador"><strong>Nombre de usuario:</strong></label>
        <input 
            type="text" 
            name="nuevo_evaluador" 
            id="nuevo_evaluador" 
            placeholder="Ej: evaluador3" 
            required
            style="padding: 8px; width: 220px;"
        >

        <button 
            type="submit" 
            name="agregar_evaluador"
            style="padding: 8px 15px; background-color: #7952b3; color: white; border: none; cursor: pointer;"
        >
            Agregar evaluador
        </button>
    </form>

    <br>
<?php endif; ?>

<?php if ($tipoUsuario == 3): ?>
    <hr>

    <h3>Agregar nueva empresa</h3>

    <?php if (!empty($mensajeNuevaEmpresa)): ?>
        <p><strong><?= htmlspecialchars($mensajeNuevaEmpresa) ?></strong></p>
    <?php endif; ?>

    <form method="POST">
        <input 
            type="text" 
            name="rut_empresa" 
            placeholder="RUT empresa"
            required
            style="padding: 8px;"
        >

        <input 
            type="text" 
            name="nombre_empresa" 
            placeholder="Nombre empresa"
            required
            style="padding: 8px;"
        >

        <input 
            type="text" 
            name="nombre_representante" 
            placeholder="Nombre representante"
            required
            style="padding: 8px;"
        >

        <input 
            type="number" 
            name="telefono_representante" 
            placeholder="Teléfono representante"
            required
            style="padding: 8px;"
        >

        <select name="id_tamanio" required style="padding: 8px;">
            <option value="">Tamaño empresa...</option>
            <option value="1">Pequeña</option>
            <option value="2">Mediana</option>
            <option value="3">Grande</option>
        </select>

        <select name="id_convenio_usm" required style="padding: 8px;">
            <option value="">Convenio USM...</option>
            <option value="1">Sí</option>
            <option value="2">No</option>
        </select>

        <button 
            type="submit" 
            name="agregar_empresa"
            style="padding: 8px 15px; background-color: #7952b3; color: white; border: none; cursor: pointer;"
        >
            Agregar empresa
        </button>
    </form>

    <br>
<?php endif; ?>

<br>
<a href="logout.php">Cerrar Sesión</a>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                
                const searchTerm = this.value.toLowerCase();
                
                const rows = document.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    
                    if (rowText.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
    });
</script>

<?php view('footer') ?>