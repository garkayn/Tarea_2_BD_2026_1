<?php
$host = 'localhost'; 
$dbname = 'ct_usm_postulaciones'; // Aquí está el nombre exacto de tu base de datos
$username = 'root'; 
$password = ''; 

try {
    // Creamos la conexión segura usando PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configuramos el manejo de errores y el formato de los datos
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Mensaje en caso de que XAMPP esté apagado o el nombre de la BD sea incorrecto
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>