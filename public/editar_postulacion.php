<?php
require_once '../src/bootstrap.php';
require_once '../src/inc/auth.php';
require_login();

// Recibir el código de la postulación
$codigo = $_GET['codigo'] ?? null;
$usuarioActual = current_user();

if (!$codigo) {
    die("Error: No se especificó ninguna postulación a editar.");
}

// Buscar los datos de la postulación específica y validar propiedad
// *** NO Sé SI DEJAR ESTA QUERY, FALTA EL RESTO DE EDICIONES POSIBLES ***
$sql = "SELECT postulacion.*, iniciativa.nombre_iniciativa 
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

// Procesar si el usuario decide guardar el cambio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoNombre = $_POST['nombre_iniciativa'];

    $sqlIniciativa = "UPDATE iniciativa 
                      SET nombre_iniciativa = :nombre 
                      WHERE codigo_postulacion = :codigo";
    $stmtIniciativa = db()->prepare($sqlIniciativa);
    $stmtIniciativa->execute([
        'nombre' => $nuevoNombre,
        'codigo' => $codigo
    ]);
    
    redirect_to('home.php');
}
?>

<?php view('header', ['title' => 'Editar Postulación']) ?>

<h2>Editando Postulación: <?= htmlspecialchars($codigo) ?></h2>

<form method="POST" action="editar_postulacion.php?codigo=<?= urlencode($codigo) ?>">
    
    <label>Iniciativa:</label>
    <input type="text" name="nombre_iniciativa" value="<?= htmlspecialchars($datos_postulacion['nombre_iniciativa']) ?>" required>
    <br><br>

    <button type="submit">Guardar Cambios</button>
    <a href="home.php">Cancelar</a>
</form>

<?php view('footer') ?>