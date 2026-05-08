<?php
require_once '../src/bootstrap.php';
require_login();

$usuarioActual = current_user();
$tipoUsuario = $_SESSION['id_rol'] ?? null;

$sql = "SELECT VistaMisPostulaciones.*
        FROM VistaMisPostulaciones
        JOIN usuario ON (VistaMisPostulaciones.responsable_1 = usuario.username 
                      OR VistaMisPostulaciones.responsable_2 = usuario.username)
        WHERE usuario.username = :username";

$stmt = db()->prepare($sql);
$stmt->execute(['username' => $usuarioActual]);
$mis_postulaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<?php view('header', ['title' => 'Mis Postulaciones']) ?>

<h2>Bienvenido, <?= htmlspecialchars($usuarioActual) ?></h2>
<h3>Tus Postulaciones Asignadas (<?= count($mis_postulaciones) ?>)</h3>

<?php if (count($mis_postulaciones) > 0): ?>
    <table border="1">
        <thead>
            <tr>
                <th>Código</th>
                <th>Iniciativa</th>
                <th>Estado</th>
                <th>Sede</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($mis_postulaciones as $postulacion): ?>
                <tr>
                    <td><?= htmlspecialchars($postulacion['codigo_postulacion']) ?></td>
                    <td><?= htmlspecialchars($postulacion['nombre_iniciativa']) ?></td>
                    <td><?= htmlspecialchars($postulacion['nombre_estado_postulacion']) ?></td>
                    <td><?= htmlspecialchars($postulacion['nombre_sede']) ?></td>
                    <td><?= htmlspecialchars($postulacion['fecha_postulacion']) ?></td>
                    <td>
                        <a href="editar_postulacion.php?codigo=<?= urlencode($postulacion['codigo_postulacion']) ?>">
                            Editar
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

    <br><br>
    <!-- Barra de busqueda -->
    <input type="text" id="search" placeholder="Search" />
    <br>
    <br />
    <div id="display"></div>

<?php view('footer') ?>