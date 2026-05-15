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

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recibir datos ingresados por el usuario
    $presupuesto       = $_POST['presupuesto'];
    $rutEmpresa        = $_POST['rut_empresa']; // Ahora este valor viene del <select>
    
    $idSede            = $_POST['id_sede'];
    $idRegionEjecucion = $_POST['id_region_ejecucion'];
    $idRegionImpacto   = $_POST['id_region_impacto'];
    
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
        
        // Código de postulación: 1000 + numero_postulacion
        $siguienteNumero = ($resultadoMax['max_num'] ?? 0) + 1;
        $codigoGenerado = 1000 + $siguienteNumero;

        // Insertar postulación
        $sqlPostulacion = "INSERT INTO postulacion (
                            codigo_postulacion, fecha_postulacion, responsable_1, responsable_2, 
                            numero_postulacion, presupuesto, id_sede, id_region_ejecucion, 
                            id_region_impacto, rut_empresa, id_estado_postulacion
                          ) VALUES (
                            :codigo, :fecha, :user, NULL, 
                            :numero, :presupuesto, :sede, :region_ejecucion, 
                            :region_impacto, :rut, 1
                          )";
                          
        $stmtPost = db()->prepare($sqlPostulacion);
        $stmtPost->execute([
            'codigo'           => $codigoGenerado,
            'fecha'            => $fechaActual,
            'user'             => $usuarioActual,
            'numero'           => $siguienteNumero,
            'presupuesto'      => $presupuesto,
            'sede'             => $idSede,
            'region_ejecucion' => $idRegionEjecucion,
            'region_impacto'   => $idRegionImpacto,
            'rut'              => $rutEmpresa
        ]);

        // Insertar iniciativa
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

         // Se recibe la lista con todos los datos de los integrantes
        $ruts = $_POST['rut_integrante'] ?? [];
        $nombres = $_POST['nombre_integrante'] ?? [];
        $deptos = $_POST['depto_integrante'] ?? [];
        $emails = $_POST['email_integrante'] ?? [];
        $telefonos = $_POST['telefono_integrante'] ?? [];
        $sedes = $_POST['id_sede_integrante'] ?? [];
        $tipos = $_POST['tipo_integrante'] ?? [];
        $roles = $_POST['rol_integrante'] ?? [];

        // Se guarda cada persona en integrante y luego se asocia a la postulación en equipo
        for ($i = 0; $i < count($ruts); $i++) {
            $rut_actual = $ruts[$i];
            
            // Si el integrante ya existe, se ignora porque el RUT identifica a la persona
            $sqlIntegrante = "INSERT IGNORE INTO integrante (
                                rut_integrante, nombre_integrante, depto_integrante,
                                email_integrante, telefono_integrante, id_sede, id_tipo_persona
                            ) VALUES (
                                :rut, :nombre, :depto, :email, :telefono, :sede, :tipo
                            )";

            $stmtInt = db()->prepare($sqlIntegrante);
            $stmtInt->execute([
                'rut'      => $rut_actual,
                'nombre'   => $nombres[$i],
                'depto'    => $deptos[$i],
                'email'    => $emails[$i],
                'telefono' => $telefonos[$i],
                'sede'     => $sedes[$i],
                'tipo'     => $tipos[$i]
            ]);

            // El rol sí se guarda en equipo, porque depende de la postulación
            $sqlEquipo = "INSERT INTO equipo (codigo_postulacion, rut_integrante, rol_integrante) 
                          VALUES (:codigo, :rut, :rol)";

            $stmtEq = db()->prepare($sqlEquipo);
            $stmtEq->execute([
                'codigo' => $codigoGenerado,
                'rut'    => $rut_actual,
                'rol'    => $roles[$i]
            ]);
        }

        // Guardar etapas y enlazarlas con el cronograma de la postulación
        $nombres_etapas = $_POST['nombre_etapa'] ?? [];
        $plazos_etapas = $_POST['plazo_etapa'] ?? [];
        $entregables_etapas = $_POST['entregable_etapa'] ?? [];

        for ($j = 0; $j < count($nombres_etapas); $j++) {
            $sqlEtapa = "INSERT INTO etapa (nombre_etapa, plazo, entregable) 
                         VALUES (:nombre, :plazo, :entregable)";
            
            $stmtEt = db()->prepare($sqlEtapa);
            $stmtEt->execute([
                'nombre'     => $nombres_etapas[$j],
                'plazo'      => $plazos_etapas[$j],
                'entregable' => $entregables_etapas[$j]
            ]);

            $id_nueva_etapa = db()->lastInsertId();

            $sqlCronograma = "INSERT INTO cronograma (codigo_postulacion, cod_etapa) 
                              VALUES (:codigo, :etapa)";
            
            $stmtCron = db()->prepare($sqlCronograma);
            $stmtCron->execute([
                'codigo' => $codigoGenerado,
                'etapa'  => $id_nueva_etapa
            ]);
        }

        // Llamadas a los procedimientos almacenados
        //$sqlRevisarEquipo = "CALL VerificarRequisitoEquipo(:codigo)";
        //$stmtRevisarEq = db()->prepare($sqlRevisarEquipo);
        //$stmtRevisarEq->execute(['codigo' => $codigoGenerado]);

        $sqlRevisarCronograma = "CALL VerificarRequisitoCronograma(:codigo)";
        $stmtRevisarCr = db()->prepare($sqlRevisarCronograma);
        $stmtRevisarCr->execute(['codigo' => $codigoGenerado]);

        // Si todo pasó, marcamos la postulación como "Enviada" (1)
        $sqlEstado = "UPDATE postulacion SET id_estado_postulacion = 1 WHERE codigo_postulacion = :codigo";
        $stmtEstado = db()->prepare($sqlEstado);
        $stmtEstado->execute(['codigo' => $codigoGenerado]);

        db()->commit();
        redirect_to('home.php');

    } catch (Exception $e) {
        db()->rollBack();
        
        $error_crudo = $e->getMessage();
        if ($e->getCode() == '45000' || strpos($error_crudo, '45000') !== false) {
            $partes = explode('1644', $error_crudo);
            if (isset($partes[1])) {
                $mensaje_error = trim($partes[1]); 
            } else {
                $mensaje_error = "Error: La postulación no cumple con los requisitos mínimos.";
            }
        } else {
            $mensaje_error = "Ocurrió un error inesperado en la base de datos: " . $error_crudo;
        }
    }
}

// Consultas para traer datos de selectores
$stmtEmpresas = db()->query("SELECT DISTINCT rut_empresa, nombre_empresa FROM empresa ORDER BY nombre_empresa ASC");
$listaEmpresas = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);

$stmtRegiones = db()->query("SELECT id_region, nombre_region FROM region ORDER BY id_region ASC");
$listaRegiones = $stmtRegiones->fetchAll(PDO::FETCH_ASSOC);

$stmtSedes = db()->query("SELECT id_sede, nombre_sede FROM sede ORDER BY id_sede ASC");
$listaSedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

?>

<?php view('header', ['title' => 'Crear Nueva Postulación']) ?>

<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <h2>Registrar Nueva Postulación</h2>
    <p>Complete los siguientes datos.</p>
    
    <?php if (!empty($mensaje_error)): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 20px;">
            <strong>¡Atención!</strong> <?php echo htmlspecialchars($mensaje_error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="crear_postulacion.php">
        
        <h3>1. Datos Generales</h3>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Empresa Externa Asignada:</label>
            <select name="rut_empresa" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione una Empresa --</option>
                <?php foreach ($listaEmpresas as $empresa): ?>
                    <option value="<?= htmlspecialchars($empresa['rut_empresa']) ?>">
                        <?= htmlspecialchars($empresa['nombre_empresa']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Sede USM Responsable:</label>
            <select name="id_sede" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione Sede --</option>
                <?php foreach ($listaSedes as $sede): ?>
                    <option value="<?= htmlspecialchars($sede['id_sede']) ?>">
                        <?= htmlspecialchars($sede['nombre_sede']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Región de Ejecución:</label>
            <select name="id_region_ejecucion" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione Región --</option>
                <?php foreach ($listaRegiones as $region): ?>
                    <option value="<?= htmlspecialchars($region['id_region']) ?>">
                        <?= htmlspecialchars($region['nombre_region']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold;">Región de Impacto:</label>
            <select name="id_region_impacto" style="width: 100%; padding: 8px;" required>
                <option value="">-- Seleccione Región --</option>
                <?php foreach ($listaRegiones as $region): ?>
                    <option value="<?= htmlspecialchars($region['id_region']) ?>">
                        <?= htmlspecialchars($region['nombre_region']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
            <textarea name="descripcion" rows="4" style="width: 100%; padding: 8px;" required></textarea>
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
                <option value="1">Nueva</option>
                <option value="2">Existente</option>
            </select>
        </div>

        <hr style="margin: 30px 0;">

        <h3>3. Equipo de Trabajo</h3>
        <p style="font-size: 0.9em; color: #555;">Agregue a los profesores y alumnos del proyecto (mínimo 3 profesores y 5 alumnos).</p>
        
        <div id="contenedor-equipo"></div>

        <button type="button" id="btn-agregar-integrante" style="padding: 8px 15px; background-color: #28a745; color: white; border: none; cursor: pointer; border-radius: 4px; margin-bottom: 20px;">
            + Agregar Integrante
        </button>

        <hr style="margin: 30px 0;">

        <h3>4. Cronograma de Etapas</h3>
        <p style="font-size: 0.9em; color: #555;">Defina las etapas, plazos y entregables de su proyecto (Su cronograma completo no puede superar las 36 semanas).</p>
        
        <div id="contenedor-etapas"></div>

        <button type="button" id="btn-agregar-etapa" style="padding: 8px 15px; background-color: #28a745; color: white; border: none; cursor: pointer; border-radius: 4px; margin-bottom: 30px;">
            + Agregar Etapa
        </button>
        
        <button type="button" id="btn-datos-prueba" style="padding: 10px 20px; background-color: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 4px; margin-bottom: 20px;">
            Rellenar datos de prueba
        </button>

        <br>

        <button type="submit" style="padding: 10px 20px; background-color: #7952b3; color: white; border: none; cursor: pointer;">Guardar y Enviar Postulación</button>
        <br>
        <a href="home.php" style="margin-left: 15px; text-decoration: none; color: #dc3545;">Cancelar</a>
    </form>
</div>

<script>
// Manejo Dinámico de Integrantes
document.getElementById('btn-agregar-integrante').addEventListener('click', function() {
    var container = document.getElementById('contenedor-equipo');
    var row = document.createElement('div');
    
    row.style.display = 'flex';
    row.style.flexWrap = 'wrap';
    row.style.gap = '10px';
    row.style.marginBottom = '10px';
    
    row.innerHTML = `
        <input type="text" name="rut_integrante[]" placeholder="RUT (ej: 12345678-9)" style="flex: 1 1 150px; padding: 8px;" required>
        <input type="text" name="nombre_integrante[]" placeholder="Nombre completo" style="flex: 1 1 220px; padding: 8px;" required>
        <input type="text" name="depto_integrante[]" placeholder="Departamento" style="flex: 1 1 180px; padding: 8px;" required>
        <input type="email" name="email_integrante[]" placeholder="Correo institucional" style="flex: 1 1 220px; padding: 8px;" required>
        <input type="number" name="telefono_integrante[]" placeholder="Teléfono" style="flex: 1 1 130px; padding: 8px;" required>
        <select name="id_sede_integrante[]" style="flex: 1 1 220px; padding: 8px;" required>
            <option value="">Seleccione sede...</option>
            <option value="1">Campus Casa Central Valparaíso</option>
            <option value="2">Campus San Joaquín</option>
            <option value="3">Campus Vitacura</option>
            <option value="4">Sede Viña del Mar</option>
            <option value="5">Sede Concepción</option>
        </select>
        <select name="tipo_integrante[]" style="flex: 1 1 150px; padding: 8px;" required>
            <option value="">Tipo de persona...</option>
            <option value="1">Profesor</option>
            <option value="2">Estudiante</option>
        </select>
        <input type="text" name="rol_integrante[]" placeholder="Rol en el proyecto" style="flex: 1 1 180px; padding: 8px;" required>
        <button type="button" onclick="this.parentElement.remove()" style="background-color: #dc3545; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px;">X</button>
    `;
    container.appendChild(row);
});

// Manejo Dinámico de Etapas del Cronograma
document.getElementById('btn-agregar-etapa').addEventListener('click', function() {
    var container = document.getElementById('contenedor-etapas');
    var row = document.createElement('div');
    
    row.style.display = 'flex';
    row.style.flexWrap = 'wrap';
    row.style.gap = '10px';
    row.style.marginBottom = '10px';
    
    row.innerHTML = `
        <input type="text" name="nombre_etapa[]" placeholder="Nombre de la etapa" style="flex: 1 1 250px; padding: 8px;" required>
        <input type="number" name="plazo_etapa[]" placeholder="Plazo (semanas)" style="flex: 1 1 100px; padding: 8px;" required>
        <input type="text" name="entregable_etapa[]" placeholder="Entregable esperado" style="flex: 1 1 300px; padding: 8px;" required>
        <button type="button" onclick="this.parentElement.remove()" style="background-color: #dc3545; color: white; border: none; padding: 8px; cursor: pointer; border-radius: 4px;">X</button>
    `;
    container.appendChild(row);

    // Botón para rellenar datos de prueba manualmente
    document.getElementById('btn-datos-prueba').addEventListener('click', function() {

        function setCampo(nombre, valor) {
            var campo = document.querySelector(`[name="${nombre}"]`);
            if (campo) {
                campo.value = valor;
            }
        }

        function setSelect(nombre, valorPreferido) {
            var select = document.querySelector(`[name="${nombre}"]`);

            if (!select) {
                return;
            }

            var existeValor = Array.from(select.options).some(function(option) {
                return option.value === valorPreferido;
            });

            if (existeValor) {
                select.value = valorPreferido;
            } else {
                var primeraOpcionValida = Array.from(select.options).find(function(option) {
                    return option.value !== "";
                });

                if (primeraOpcionValida) {
                    select.value = primeraOpcionValida.value;
                }
            }
        }

        function agregarIntegrante(datos) {
            document.getElementById('btn-agregar-integrante').click();

            var contenedor = document.getElementById('contenedor-equipo');
            var fila = contenedor.lastElementChild;

            fila.querySelector('[name="rut_integrante[]"]').value = datos.rut;
            fila.querySelector('[name="nombre_integrante[]"]').value = datos.nombre;
            fila.querySelector('[name="depto_integrante[]"]').value = datos.depto;
            fila.querySelector('[name="email_integrante[]"]').value = datos.email;
            fila.querySelector('[name="telefono_integrante[]"]').value = datos.telefono;
            fila.querySelector('[name="id_sede_integrante[]"]').value = datos.sede;
            fila.querySelector('[name="tipo_integrante[]"]').value = datos.tipo;
            fila.querySelector('[name="rol_integrante[]"]').value = datos.rol;
        }

        function agregarEtapa(datos) {
            document.getElementById('btn-agregar-etapa').click();

            var contenedor = document.getElementById('contenedor-etapas');
            var fila = contenedor.lastElementChild;

            fila.querySelector('[name="nombre_etapa[]"]').value = datos.nombre;
            fila.querySelector('[name="plazo_etapa[]"]').value = datos.plazo;
            fila.querySelector('[name="entregable_etapa[]"]').value = datos.entregable;
        }

        // Limpia integrantes y etapas anteriores para no duplicar filas si aprietas el botón más de una vez
        document.getElementById('contenedor-equipo').innerHTML = "";
        document.getElementById('contenedor-etapas').innerHTML = "";

        // Datos generales
        setSelect('rut_empresa', '76.111.222-3');
        setSelect('id_sede', '1');
        setSelect('id_region_ejecucion', '5');
        setSelect('id_region_impacto', '13');
        setCampo('presupuesto', '12500000');

        // Datos de iniciativa
        setCampo('nombre_iniciativa', 'Sistema piloto de gestión académica con IA');
        setCampo('descripcion', 'La unidad académica necesita una herramienta que permita organizar postulaciones, equipos de trabajo, etapas y evaluaciones de proyectos de vinculación.');
        setCampo('solucion', 'Se propone construir una plataforma web que centralice el registro de postulaciones, la asignación de evaluadores y el seguimiento del cronograma.');
        setCampo('resultados_esperados', 'Prototipo funcional, reducción de tiempos de revisión y trazabilidad completa del proceso de postulación.');
        setCampo('documentos', 'Carta de compromiso, planificación inicial, antecedentes técnicos y presupuesto estimado.');
        setSelect('id_tipo_iniciativa', '1');

        // 3 profesores
        agregarIntegrante({
            rut: '11.900.100-1',
            nombre: 'Profesor Prueba Uno',
            depto: 'Informática',
            email: 'profesor.prueba1@usm.cl',
            telefono: '987650001',
            sede: '1',
            tipo: '1',
            rol: 'Director del proyecto'
        });

        agregarIntegrante({
            rut: '11.900.100-2',
            nombre: 'Profesora Prueba Dos',
            depto: 'Matemática',
            email: 'profesora.prueba2@usm.cl',
            telefono: '987650002',
            sede: '2',
            tipo: '1',
            rol: 'Investigadora principal'
        });

        agregarIntegrante({
            rut: '11.900.100-3',
            nombre: 'Profesor Prueba Tres',
            depto: 'Electrónica',
            email: 'profesor.prueba3@usm.cl',
            telefono: '987650003',
            sede: '3',
            tipo: '1',
            rol: 'Asesor técnico'
        });

        // 5 estudiantes
        // Este estudiante ya existe en el DML, pero le ponemos otros datos para probar INSERT IGNORE
        agregarIntegrante({
            rut: '20.111.111-1',
            nombre: 'Nombre Cambiado Prueba',
            depto: 'Departamento Cambiado',
            email: 'correo.cambiado.prueba@usm.cl',
            telefono: '900000001',
            sede: '5',
            tipo: '2',
            rol: 'QA Duplicado'
        });

        agregarIntegrante({
            rut: '21.900.100-2',
            nombre: 'Estudiante Prueba Dos',
            depto: 'Informática',
            email: 'estudiante.prueba2@usm.cl',
            telefono: '912340002',
            sede: '1',
            tipo: '2',
            rol: 'Desarrollador backend'
        });

        agregarIntegrante({
            rut: '21.900.100-3',
            nombre: 'Estudiante Prueba Tres',
            depto: 'Informática',
            email: 'estudiante.prueba3@usm.cl',
            telefono: '912340003',
            sede: '1',
            tipo: '2',
            rol: 'Desarrolladora frontend'
        });

        agregarIntegrante({
            rut: '21.900.100-4',
            nombre: 'Estudiante Prueba Cuatro',
            depto: 'Industrias',
            email: 'estudiante.prueba4@usm.cl',
            telefono: '912340004',
            sede: '2',
            tipo: '2',
            rol: 'Analista de datos'
        });

        agregarIntegrante({
            rut: '21.900.100-5',
            nombre: 'Estudiante Prueba Cinco',
            depto: 'Informática',
            email: 'estudiante.prueba5@usm.cl',
            telefono: '912340005',
            sede: '3',
            tipo: '2',
            rol: 'Documentador'
        });

        // Cronograma: suma 23 semanas, menor que 36
        agregarEtapa({
            nombre: 'Levantamiento de requerimientos',
            plazo: '4',
            entregable: 'Documento de requerimientos validado'
        });

        agregarEtapa({
            nombre: 'Diseño de la solución',
            plazo: '5',
            entregable: 'Modelo de datos y prototipo de interfaz'
        });

        agregarEtapa({
            nombre: 'Desarrollo del sistema',
            plazo: '8',
            entregable: 'Aplicación funcional desplegada'
        });

        agregarEtapa({
            nombre: 'Pruebas y validación',
            plazo: '6',
            entregable: 'Informe de pruebas y correcciones finales'
        });
    });
    });
</script>

<?php view('footer') ?>