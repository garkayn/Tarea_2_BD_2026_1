<?php

/**
 * Se conecta a la base de datos y retorna una instancia de la clase PDO
 * o false si la conexión falla
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo;

    if (!$pdo) {
        $pdo = new PDO(
            sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", "localhost", "ct_usm_postulaciones"),
            "root",
            "",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    return $pdo;
}
