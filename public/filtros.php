<?php
require_once '../src/bootstrap.php';
require_once '../src/inc/auth.php';
require_login();

$usuarioActual = current_user();
$tipoUsuario = $_SESSION['id_rol'] ?? null;

$condiciones = [];
$parametros = [];
$joins_extra = "";

// Gestión de acceso según tipo de usuario
if ($tipoUsuario != 3) {
    $condiciones[] = "(responsable_1 = :user1 OR responsable_2 = :user2)";
    $parametros['user1'] = $usuarioActual;
    $parametros['user2'] = $usuarioActual;
} else {
    $condiciones[] = "id_estado_postulacion != 1";
}

// Buscador
if (!empty($_GET['buscar'])) {
    $condiciones[] = "(
        numero_postulacion LIKE :buscar OR 
        nombre_iniciativa LIKE :buscar OR 
        nombre_empresa LIKE :buscar
    )";
    $parametros['buscar'] = '%' . $_GET['buscar'] . '%';
}

// Filtros combinables
if (!empty($_GET['id_region_ejecucion'])) {
    $condiciones[] = "id_region_ejecucion = :id_ejec";
    $parametros['id_ejec'] = $_GET['id_region_ejecucion'];
}

if (!empty($_GET['id_region_impacto'])) {
    $condiciones[] = "id_region_impacto = :id_imp";
    $parametros['id_imp'] = $_GET['id_region_impacto'];
}

if (!empty($_GET['sede'])) {
    $condiciones[] = "nombre_sede = :sede";
    $parametros['sede'] = $_GET['sede'];
}

if (!empty($_GET['nombre_tipo_iniciativa'])) {
    $condiciones[] = "nombre_tipo_iniciativa = :tipo_iniciativa";
    $parametros['tipo_iniciativa'] = $_GET['nombre_tipo_iniciativa'];
}

if (!empty($_GET['nombre_estado_postulacion'])) {
    $condiciones[] = "nombre_estado_postulacion = :estado";
    $parametros['estado'] = $_GET['nombre_estado_postulacion'];
}

if (!empty($_GET['responsable_2'])) {
    $condiciones[] = "responsable_2 = :evaluador";
    $parametros['evaluador'] = $_GET['responsable_2'];
}

if (!empty($_GET['tamanio']) || !empty($_GET['convenio_usm'])) {
    $joins_extra = " LEFT JOIN empresa ON VistaMisPostulaciones.rut_empresa = empresa.rut_empresa 
                        LEFT JOIN tamanio_empresa ON empresa.id_tamanio = tamanio_empresa.id_tamanio 
                        LEFT JOIN convenio_usm ON empresa.id_convenio_usm = convenio_usm.id_convenio_usm ";
                        
    if (!empty($_GET['tamanio'])) {
        $condiciones[] = "tamanio_empresa.tamanio = :tamanio";
        $parametros['tamanio'] = $_GET['tamanio'];
    }

    if (!empty($_GET['convenio_usm'])) {
        $condiciones[] = "convenio_usm.convenio_usm = :convenio";
        $parametros['convenio'] = $_GET['convenio_usm'];
    }
}

// Armado final de la consulta
$sql = "SELECT VistaMisPostulaciones.* FROM VistaMisPostulaciones " . $joins_extra;

if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}

$stmt = db()->prepare($sql);
$stmt->execute($parametros);
$mis_postulaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>