<?php
require_once '../src/bootstrap.php'; 

// Verificar que AJAX envió el dato 'search'
if (isset($_POST['search'])) {
    
    $nameFromAjax = $_POST['search'];
    $searchTerm = '%' . $nameFromAjax . '%';

    $sql = "SELECT postulacion.codigo_postulacion, iniciativa.nombre_iniciativa, empresa.nombre_empresa
            FROM postulacion
            JOIN iniciativa ON iniciativa.codigo_postulacion = postulacion.codigo_postulacion
            JOIN empresa ON empresa.rut_empresa = postulacion.rut_empresa
            WHERE postulacion.codigo_postulacion LIKE :search 
               OR iniciativa.nombre_iniciativa LIKE :search 
               OR empresa.nombre_empresa LIKE :search
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