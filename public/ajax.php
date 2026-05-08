<?php
require_once '../src/bootstrap.php'; 

// Verificar que AJAX envió el dato 'search'
if (isset($_POST['search'])) {
    
    $nameFromAjax = $_POST['search'];
    $searchTerm = '%' . $nameFromAjax . '%';

    $sql = "SELECT p.codigo_postulacion, i.nombre_iniciativa, e.nombre_empresa
            FROM postulacion p
            JOIN iniciativa i ON i.codigo_postulacion = p.codigo_postulacion
            JOIN empresa e ON e.rut_empresa = p.rut_empresa
            WHERE p.codigo_postulacion LIKE :search 
               OR i.nombre_iniciativa LIKE :search 
               OR e.nombre_empresa LIKE :search
            LIMIT 5";

    $stmt = db()->prepare($sql); 
    $stmt->execute(['search' => $searchTerm]);
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($resultados) {
        foreach ($resultados as $fila) {

            echo "<div class='resultado-item'>";
            echo "<strong>" . htmlspecialchars($fila['codigo_postulacion']) . "</strong> - ";
            echo htmlspecialchars($fila['nombre_iniciativa']) . " (";
            echo htmlspecialchars($fila['nombre_empresa']) . ")";
            echo "</div>";
        }
    } else {
        echo "<div class='resultado-item'>No se encontraron resultados.</div>";
    }
}
?>