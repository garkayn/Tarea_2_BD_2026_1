<?php
require_once '../src/bootstrap.php';
require_once '../src/inc/auth.php';
require_login();

// Conseguir usuario y rol
$usuarioActual = current_user();
$tipoUsuario = $_SESSION['id_rol'] ?? null;

// Solo el Responsable Académico (Rol 1) puede crear postulaciones
if ($tipoUsuario != 1) {
    die("Acceso denegado: Solo los responsables académicos pueden crear postulaciones.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recibir datos ingresados por el usuario
    $presupuesto       = $_POST['presupuesto'];
    $rutEmpresa        = $_POST['rut_empresa'];
    
    $nombre      = $_POST['nombre_iniciativa'];
    $descripcion = $_POST['descripcion'];
    $solucion    = $_POST['solucion'];
    $resultados  = $_POST['resultados_esperados'];
    $documentos  = $_POST['documentos'];
    $tipo        = $_POST['id_tipo_iniciativa'];
    
    $fechaActual = date('Y-m-d');
    
    try {
        db()->beginTransaction();

        // Generación incremental de numero_postulacion
        $sqlMax = "SELECT MAX(numero_postulacion) as max_num FROM postulacion";
        $stmtMax = db()->query($sqlMax);
        $resultadoMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
        
        $siguienteNumero = ($resultadoMax['max_num'] ?? 0) + 1;
        
        // Código de postulación: 1000 + numero_postulacion
        $codigoGenerado = 1000 + $siguienteNumero;

        $sqlPostulacion = "INSERT INTO postulacion (
                            codigo_postulacion, fecha_postulacion, responsable_1, responsable_2, 
                            numero_postulacion, presupuesto, id_sede, id_region_ejecucion, 
                            id_region_impacto, rut_empresa, id_estado_postulacion
                          ) VALUES (
                            :codigo, :fecha, :user, 'Juan Carlos', 
                            :numero, :presupuesto, 1, 8, 
                            8, :rut, 1
                          )";
                          
        $stmtPost = db()->prepare($sqlPostulacion);
        $stmtPost->execute([
            'codigo'      => $codigoGenerado,
            'fecha'       => $fechaActual,
            'user'        => $usuarioActual,
            'numero'      => $siguienteNumero,
            'presupuesto' => $presupuesto,
            'rut'         => $rutEmpresa
        ]);

        $sqlIniciativa = "INSERT INTO iniciativa (
                            nombre_iniciativa, descripcion, solucion, 
                            resultados_esperados, documentos, codigo_postulacion, id_tipo_iniciativa
                          ) VALUES (
                            :nombre, :desc, :sol, 
                            :res, :docs, :codigo, :tipo
                          )";
        
        $stmtIni = db()->prepare($sqlIniciativa);
        $stmtIni->execute([
            'nombre' => $nombre,
            'desc'   => $descripcion,
            'sol'    => $solucion,
            'res'    => $resultados,
            'docs'   => $documentos,
            'codigo' => $codigoGenerado, 
            'tipo'   => $tipo
        ]);

        db()->commit();
        redirect_to('home.php');

    } catch (Exception $e) {
        db()->rollBack();
        die("Error crítico al guardar en la base de datos: " . $e->getMessage());
    }
}
?>

<?php view('header', ['title' => 'Crear Nueva Postulación']) ?>

<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h2>Registrar Nueva Postulación</h2>
    <p>Complete los siguientes datos.</p>

    <form method="POST" action="crear_postulacion.php">
        
        <h3>1. Datos Generales</h3>
        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">RUT Empresa Externa:</label>
            <input type="text" name="rut_empresa" placeholder="12.345.678-9" style="width: 100%; padding: 8px;" required>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Presupuesto Solicitado ($):</label>
            <input type="number" name="presupuesto" style="width: 100%; padding: 8px;" required>
        </div>

        <hr style="margin: 30px 0;">

        <h3>2. Datos de la Iniciativa</h3>
        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Nombre de la Iniciativa:</label>
            <input type="text" name="nombre_iniciativa" style="width: 100%; padding: 8px;" required>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Descripción del Problema:</label>
            <textarea name="descipcion" rows="4" style="width: 100%; padding: 8px;" required></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Propuesta de Solución:</label>
            <textarea name="solucion" rows="4" style="width: 100%; padding: 8px;" required></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Resultados Esperados:</label>
            <textarea name="resultados_esperados" rows="3" style="width: 100%; padding: 8px;" required></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Documentos adicionales:</label>
            <textarea name="documentos" rows="3" style="width: 100%; padding: 8px;" required></textarea>
        </div>

        <div class="form-group" style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold;">Tipo de Iniciativa:</label>
            <select name="id_tipo_iniciativa" style="width: 100%; padding: 8px;">
                <option value="1">Académica</option>
                <option value="2">Investigación</option>
                <option value="3">Vinculación</option>
            </select>
        </div>

        <button type="submit" style="padding: 10px 20px; background-color; color: white; border: none; cursor: pointer;">Guardar y Enviar Postulación</button>
        <br><br>
        <a href="home.php" style="margin-left: 15px; text-decoration: none; ">Cancelar</a>
    </form>
</div>

<?php view('footer') ?>