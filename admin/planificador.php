<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de entrenador o administrador
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$page_title = "Planificador de Entrenamientos";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$alumno_id = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$alumno = null;
$proximo_objetivo = null;
$plantillas_entrenador = [];

if ($alumno_id > 0) {
    // Consultar datos del alumno seleccionado
    $stmt = $pdo->prepare("
        SELECT ap.*, u.nombre, u.apellido, u.email 
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ap.id = ?
    ");
    $stmt->execute([$alumno_id]);
    $alumno = $stmt->fetch();
    
    if ($alumno) {
        if ($_SESSION['user_rol'] === 'entrenador_limitado' && $alumno['entrenador_id'] != $_SESSION['user_id']) {
            header("Location: /admin/planificador.php?error=unauthorized");
            exit;
        }
        $stmtObj = $pdo->prepare("
            SELECT c.titulo, c.fecha, ac.objetivo, ac.distancia_elegida, DATEDIFF(c.fecha, CURDATE()) AS faltan_dias
            FROM alumno_carrera ac
            JOIN carreras c ON ac.carrera_id = c.id
            WHERE ac.alumno_id = ? AND c.fecha >= CURDATE()
            ORDER BY c.fecha ASC LIMIT 1
        ");
        $stmtObj->execute([$alumno_id]);
        $proximo_objetivo = $stmtObj->fetch();

        // Obtener plantillas del entrenador actual
        $stmtPlant = $pdo->prepare("SELECT id, titulo, duracion_dias, fecha_inicio FROM plantillas WHERE entrenador_id = ? ORDER BY titulo ASC");
        $stmtPlant->execute([$_SESSION['user_id']]);
        $plantillas_entrenador = $stmtPlant->fetchAll();
    }
}

$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty_fields': $error_msg = "Completa todos los campos obligatorios del entrenamiento."; break;
        case 'empty_content': $error_msg = "Completa todos los campos del recurso personalizado."; break;
        case 'empty_feedback': $error_msg = "La retroalimentación no puede estar vacía."; break;
        case 'invalid_rutina': $error_msg = "ID de rutina no válido."; break;
        case 'invalid_plantilla': $error_msg = "La plantilla seleccionada no es válida o está vacía."; break;
        case 'db': $error_msg = "Error interno de base de datos."; break;
    }
}

$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'rutina_ok': $success_msg = "Entrenamiento programado con éxito."; break;
        case 'delete_ok': $success_msg = "Entrenamiento eliminado del calendario."; break;
        case 'content_ok': $success_msg = "Contenido personalizado asignado correctamente."; break;
        case 'plantilla_aplicada': $success_msg = "Plantilla de entrenamientos aplicada al alumno con éxito."; break;
        case 'feedback_mensual_ok': $success_msg = "Retroalimentación mensual guardada correctamente."; break;
    }
}

// Obtener lista completa de alumnos para el buscador inicial
try {
    $stmtList = $pdo->query("
        SELECT ap.id AS alumno_id, u.nombre, u.apellido, ap.plan_tipo, ap.nivel, ap.entrenador_id
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        ORDER BY u.apellido ASC, u.nombre ASC
    ");
    $alumnos_lista = $stmtList->fetchAll();
} catch (PDOException $e) {
    $alumnos_lista = [];
}

// Obtener entrenamientos individuales guardados
try {
    $stmtEI = $pdo->prepare("SELECT * FROM entrenamientos_individuales WHERE entrenador_id = ? ORDER BY titulo ASC");
    $stmtEI->execute([$_SESSION['user_id']]);
    $entrenamientos_individuales = $stmtEI->fetchAll();
} catch (PDOException $e) {
    $entrenamientos_individuales = [];
}
?>

<div class="container dashboard-container">
    <?php if (!$alumno): ?>
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <!-- SELECCIONADOR DE ALUMNO -->
                <div class="mb-4">
                    <h2 class="text-white fw-bold"><i class="fa-solid fa-calendar-alt text-warning me-2"></i>Planificador de Rutinas</h2>
                    <p class="text-secondary mb-0">Selecciona un corredor del equipo para planificar su calendario semanal de entrenamientos y compartir recursos personalizados.</p>
                </div>

                <div class="card-premium p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <h5 class="text-white fw-bold mb-0">Listado de Corredores</h5>
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-secondary" style="border-top-left-radius: 8px; border-bottom-left-radius: 8px;"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" id="buscadorPlanificador" class="form-control form-control-custom" placeholder="Buscar por nombre o apellido..." style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                            </div>
                        </div>
                    </div>
                    <?php if (count($alumnos_lista) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle border border-secondary" style="border-radius: 12px; overflow: hidden;">
                                <thead>
                                    <tr class="bg-dark text-secondary">
                                        <th class="border-secondary py-3">Alumno</th>
                                        <th class="border-secondary py-3">Plan</th>
                                        <th class="border-secondary py-3">Nivel</th>
                                        <th class="border-secondary py-3 text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alumnos_lista as $al): ?>
                                        <tr>
                                            <td class="border-secondary py-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 35px; height: 35px; font-family: var(--font-titles); font-size: 0.85rem;">
                                                        <?php echo strtoupper(substr($al['nombre'], 0, 1) . substr($al['apellido'], 0, 1)); ?>
                                                    </div>
                                                    <span class="text-white fw-bold"><?php echo htmlspecialchars($al['apellido'] . ", " . $al['nombre']); ?></span>
                                                </div>
                                            </td>
                                            <td class="border-secondary py-3">
                                                <span class="badge bg-warning text-dark text-uppercase small" style="font-size: 0.65rem;"><?php echo htmlspecialchars($al['plan_tipo']); ?></span>
                                            </td>
                                            <td class="border-secondary py-3 text-secondary small"><?php echo htmlspecialchars($al['nivel']); ?></td>
                                            <td class="border-secondary py-3 text-end">
                                                <a href="/admin/planificador.php?alumno_id=<?php echo $al['alumno_id']; ?>" class="btn btn-trail btn-sm">
                                                    <i class="fa-solid fa-calendar-check me-1"></i> Planificar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-secondary">
                            <i class="fa-solid fa-users-slash fa-3x mb-3 text-muted"></i>
                            <p class="mb-0">No hay alumnos registrados en el sistema. Registra un alumno primero desde la pestaña "Alumnos".</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- PLANIFICADOR DEL ALUMNO SELECCIONADO -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <a href="/admin/planificador.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-2"></i>Volver a la Lista</a>
                <h2 class="text-white fw-bold">Planificación de: <span style="color: var(--trail-orange);"><?php echo htmlspecialchars($alumno['nombre'] . " " . $alumno['apellido']); ?></span></h2>
                <p class="text-secondary mb-0">Plan: <strong><?php echo htmlspecialchars($alumno['plan_tipo']); ?></strong> &bull; Nivel: <strong><?php echo htmlspecialchars($alumno['nivel']); ?></strong></p>
            </div>
        </div>

        <!-- Alertas -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
                <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i> <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success-custom alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php
        // Configuración de la fecha y vista
        $vista = isset($_GET['vista']) && $_GET['vista'] === 'mes' ? 'mes' : 'semana';
        $fecha_actual = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fecha_actual)) {
            $fecha_actual = date('Y-m-d');
        }

        $timestamp_actual = strtotime($fecha_actual);
        $hoy = date('Y-m-d');

        if ($vista === 'semana') {
            $lunes_timestamp = date('w', $timestamp_actual) == 1 ? $timestamp_actual : strtotime('last monday', $timestamp_actual);
            $fecha_inicio = date('Y-m-d', $lunes_timestamp);
            $fecha_fin = date('Y-m-d', strtotime('+6 days', $lunes_timestamp));
            
            $prev_date = date('Y-m-d', strtotime('-7 days', $lunes_timestamp));
            $next_date = date('Y-m-d', strtotime('+7 days', $lunes_timestamp));
            
            // Meses en español
            $meses = ['Jan'=>'Ene', 'Feb'=>'Feb', 'Mar'=>'Mar', 'Apr'=>'Abr', 'May'=>'May', 'Jun'=>'Jun', 'Jul'=>'Jul', 'Aug'=>'Ago', 'Sep'=>'Sep', 'Oct'=>'Oct', 'Nov'=>'Nov', 'Dec'=>'Dic'];
            $t_ini = strtr(date('d M', $lunes_timestamp), $meses);
            $t_fin = strtr(date('d M Y', strtotime($fecha_fin)), $meses);
            $titulo_rango = "Semana: $t_ini - $t_fin";
        } else {
            $primer_dia_mes = date('Y-m-01', $timestamp_actual);
            $ultimo_dia_mes = date('Y-m-t', $timestamp_actual);
            
            // Para el calendario, buscamos el lunes anterior o igual al primer día del mes
            $lunes_inicio_calendario = date('w', strtotime($primer_dia_mes)) == 1 ? strtotime($primer_dia_mes) : strtotime('last monday', strtotime($primer_dia_mes));
            // Y el domingo posterior o igual al último día del mes
            $domingo_fin_calendario = date('w', strtotime($ultimo_dia_mes)) == 0 ? strtotime($ultimo_dia_mes) : strtotime('next sunday', strtotime($ultimo_dia_mes));

            $fecha_inicio = date('Y-m-d', $lunes_inicio_calendario);
            $fecha_fin = date('Y-m-d', $domingo_fin_calendario);
            
            $prev_date = date('Y-m-d', strtotime('-1 month', strtotime($primer_dia_mes)));
            $next_date = date('Y-m-d', strtotime('+1 month', strtotime($primer_dia_mes)));
            
            $meses = ['January'=>'Enero', 'February'=>'Febrero', 'March'=>'Marzo', 'April'=>'Abril', 'May'=>'Mayo', 'June'=>'Junio', 'July'=>'Julio', 'August'=>'Agosto', 'September'=>'Septiembre', 'October'=>'Octubre', 'November'=>'Noviembre', 'December'=>'Diciembre'];
            $titulo_rango = strtr(date('F Y', $timestamp_actual), $meses);
        }

        $stmtRutinas = $pdo->prepare("
            SELECT * FROM rutina_asignada 
            WHERE alumno_id = ? AND fecha BETWEEN ? AND ? 
            ORDER BY fecha ASC
        ");
        $stmtRutinas->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
        $rutinas_db = $stmtRutinas->fetchAll();

        // Indexar rutinas por fecha
        $rutinas_por_fecha = [];
        foreach ($rutinas_db as $r) {
            $rutinas_por_fecha[$r['fecha']] = $r;
        }
        
        // Generar estructura de días según la vista
        $dias_estructura = [];
        $current_timestamp = strtotime($fecha_inicio);
        $end_timestamp = strtotime($fecha_fin);
        
        while ($current_timestamp <= $end_timestamp) {
            $fecha_str = date('Y-m-d', $current_timestamp);
            $dias_estructura[$fecha_str] = [
                'fecha' => $fecha_str,
                'dia_numero' => date('d', $current_timestamp),
                'fecha_formateada' => date('d/m', $current_timestamp),
                'nombre' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][date('N', $current_timestamp) - 1],
                'es_mes_actual' => date('m', $current_timestamp) == date('m', $timestamp_actual),
                'es_hoy' => $fecha_str == $hoy,
                'rutina' => isset($rutinas_por_fecha[$fecha_str]) ? $rutinas_por_fecha[$fecha_str] : null
            ];
            $current_timestamp = strtotime('+1 day', $current_timestamp);
        }
        ?>

        <div class="row justify-content-center">
            <!-- Calendario Planificador -->
            <div class="col-md-8 mb-4">
                <div class="card-premium p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                        <div class="btn-group">
                            <a href="?alumno_id=<?php echo $alumno_id; ?>&vista=semana&fecha=<?php echo $fecha_actual; ?>" class="btn btn-sm <?php echo $vista == 'semana' ? 'btn-trail' : 'btn-outline-secondary'; ?>">Semana</a>
                            <a href="?alumno_id=<?php echo $alumno_id; ?>&vista=mes&fecha=<?php echo $fecha_actual; ?>" class="btn btn-sm <?php echo $vista == 'mes' ? 'btn-trail' : 'btn-outline-secondary'; ?>">Mes</a>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <a href="?alumno_id=<?php echo $alumno_id; ?>&vista=<?php echo $vista; ?>&fecha=<?php echo $prev_date; ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-left"></i></a>
                            <a href="?alumno_id=<?php echo $alumno_id; ?>&vista=<?php echo $vista; ?>&fecha=<?php echo $hoy; ?>" class="btn btn-sm btn-outline-secondary fw-bold">Hoy</a>
                            <a href="?alumno_id=<?php echo $alumno_id; ?>&vista=<?php echo $vista; ?>&fecha=<?php echo $next_date; ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-right"></i></a>
                        </div>
                    </div>
                    
                    <h5 class="text-white fw-bold mb-3 text-center" style="font-family: var(--font-titles);"><?php echo $titulo_rango; ?></h5>

                    <?php if ($vista === 'semana'): ?>
                        <!-- VISTA SEMANAL -->
                        <div class="routine-list">
                            <?php 
                            foreach ($dias_estructura as $fecha_db => $dia): 
                                $rutina = $dia['rutina'];
                                $card_class = "routine-day-card";
                                if ($rutina && $rutina['completada']) $card_class .= " completada";
                                if (!$rutina) $card_class .= " descanso";
                                if ($dia['es_hoy']) $card_class .= " today";
                            ?>
                                <div class="<?php echo $card_class; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-3 mb-2 mb-md-0 text-start">
                                            <h6 class="text-white fw-bold mb-0"><?php echo htmlspecialchars($dia['nombre']); ?> <?php echo $dia['dia_numero']; ?></h6>
                                            <?php if($dia['es_hoy']): ?><span class="badge bg-trail small mt-1">Hoy</span><?php endif; ?>
                                        </div>
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <?php if ($rutina): ?>
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <span class="badge badge-tipo badge-<?php echo str_replace(' ', '-', strtolower($rutina['tipo_sesion'])); ?>">
                                                        <?php echo htmlspecialchars($rutina['tipo_sesion']); ?>
                                                    </span>
                                                    <span class="text-secondary small"><i class="fa-solid fa-route text-muted me-1"></i><?php echo $rutina['distancia_km']; ?> km</span>
                                                    <span class="text-secondary small"><i class="fa-solid fa-map text-muted me-1"></i><?php echo htmlspecialchars($rutina['terreno']); ?></span>
                                                </div>
                                                <h6 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($rutina['titulo']); ?></h6>
                                                <p class="text-secondary small mb-0"><?php echo nl2br(htmlspecialchars($rutina['descripcion'])); ?></p>
                                                
                                                <!-- Feedback del Alumno si está completada -->
                                                <?php if ($rutina['completada']): ?>
                                                    <div class="mt-3 p-2.5 rounded border border-success border-opacity-25 small text-light" style="background: rgba(42, 157, 143, 0.05); font-size: 0.75rem;">
                                                        <div class="fw-bold text-success mb-1.5"><i class="fa-solid fa-circle-check me-1"></i>Feedback del Alumno:</div>
                                                        <div class="d-flex gap-3 mb-1.5 text-secondary">
                                                            <span><i class="fa-regular fa-clock me-1 text-muted"></i><?php echo $rutina['feedback_tiempo_minutos']; ?> min</span>
                                                            <span><i class="fa-solid fa-bolt me-1 text-muted"></i>Esfuerzo: <?php echo $rutina['feedback_esfuerzo']; ?>/10</span>
                                                            <?php if ($rutina['distancia_real'] > 0): ?>
                                                                <span><i class="fa-solid fa-route me-1 text-muted"></i><?php echo $rutina['distancia_real']; ?> km</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($rutina['feedback_comentario'])): ?>
                                                            <div class="fst-italic text-secondary border-start border-2 border-success border-opacity-50 ps-2 mb-1.5">"<?php echo htmlspecialchars($rutina['feedback_comentario']); ?>"</div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($rutina['strava_activity_id'])): ?>
                                                            <div class="pt-2 mt-2 border-top border-secondary border-opacity-50 d-flex align-items-center justify-content-between">
                                                                <span class="text-white small d-inline-flex align-items-center gap-1">
                                                                    <i class="fa-brands fa-strava text-danger" style="color: #fc4c02 !important;"></i> Sincronizado con Strava
                                                                    <?php if (!empty($rutina['ritmo_real'])): ?>
                                                                        <span class="text-secondary">(Ritmo: <?php echo htmlspecialchars($rutina['ritmo_real']); ?>)</span>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <a href="https://www.strava.com/activities/<?php echo urlencode($rutina['strava_activity_id']); ?>" target="_blank" class="text-warning text-decoration-none fw-bold" style="font-size: 0.7rem;">
                                                                    Ver en Strava <i class="fa-solid fa-up-right-from-square ms-0.5"></i>
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small italic"><i class="fa-solid fa-mug-hot me-1"></i>Día Libre</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 text-start text-md-end">
                                            <div class="d-flex justify-content-start justify-content-md-end gap-2">
                                                <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal<?php echo $dia['fecha']; ?>">
                                                    <i class="fa-solid fa-plus-circle me-1"></i> <?php echo $rutina ? 'Editar' : 'Agregar'; ?>
                                                </button>
                                                <?php if ($rutina): ?>
                                                    <form action="/actions/admin_rutina_action.php" method="POST">
                                                        <input type="hidden" name="action" value="delete_rutina">
                                                        <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                                                        <input type="hidden" name="rutina_id" value="<?php echo $rutina['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Seguro que deseas eliminar este entrenamiento?')"><i class="fa-solid fa-trash"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- VISTA MENSUAL -->
                        <div class="calendar-grid">
                            <div class="calendar-header d-flex mb-2">
                                <div class="flex-fill text-center text-muted fw-bold small py-2">Lun</div>
                                <div class="flex-fill text-center text-muted fw-bold small py-2">Mar</div>
                                <div class="flex-fill text-center text-muted fw-bold small py-2">Mié</div>
                                <div class="flex-fill text-center text-muted fw-bold small py-2">Jue</div>
                                <div class="flex-fill text-center text-muted fw-bold small py-2">Vie</div>
                                <div class="flex-fill text-center text-muted fw-bold small py-2">Sáb</div>
                                <div class="flex-fill text-center text-muted fw-bold small py-2">Dom</div>
                            </div>
                            <div class="calendar-body d-flex flex-wrap" style="border-left: 1px solid var(--border-color); border-top: 1px solid var(--border-color);">
                                <?php foreach ($dias_estructura as $fecha_db => $dia): 
                                    $rutina = $dia['rutina'];
                                    $cell_class = "calendar-cell flex-fill position-relative p-2";
                                    if (!$dia['es_mes_actual']) $cell_class .= " text-muted bg-dark";
                                    else $cell_class .= " text-white bg-secondary";
                                    if ($dia['es_hoy']) $cell_class .= " border border-warning shadow-inner";
                                ?>
                                    <div class="<?php echo $cell_class; ?>" style="width: 14.28%; min-height: 100px; border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); cursor: pointer;" data-bs-toggle="modal" data-bs-target="#scheduleModal<?php echo $dia['fecha']; ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold <?php echo $dia['es_hoy'] ? 'text-warning' : ''; ?>"><?php echo $dia['dia_numero']; ?></span>
                                            <?php if ($rutina && $rutina['completada']): ?>
                                                <i class="fa-solid fa-check-circle text-success small"></i>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($rutina): ?>
                                            <div class="badge badge-tipo badge-<?php echo str_replace(' ', '-', strtolower($rutina['tipo_sesion'])); ?> d-block text-truncate mb-1" style="font-size: 0.8rem;" title="<?php echo htmlspecialchars($rutina['titulo']); ?>">
                                                <?php echo htmlspecialchars($rutina['tipo_sesion']); ?>
                                            </div>
                                            <div class="small text-truncate text-secondary" style="font-size: 0.85rem;"><?php echo $rutina['distancia_km']; ?>km</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php foreach ($dias_estructura as $fecha_db => $dia): 
                $rutina = $dia['rutina'];
            ?>
                <!-- MODAL: Programar Entrenamiento -->
                <div class="modal fade" id="scheduleModal<?php echo $dia['fecha']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
                            <div class="modal-header border-bottom border-dark">
                                <h5 class="modal-title text-white fw-bold">
                                    <i class="fa-solid fa-stopwatch text-warning me-2"></i>Agregar: <?php echo $dia['nombre'] . " (" . $dia['fecha_formateada'] . ")"; ?>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="/actions/admin_rutina_action.php" method="POST">
                                <input type="hidden" name="action" value="create_rutina">
                                <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                                <input type="hidden" name="fecha" value="<?php echo $dia['fecha']; ?>">
                                
                                <div class="modal-body text-start">
                                     <?php if (count($entrenamientos_individuales) > 0): ?>
                                         <div class="mb-3 text-center">
                                             <button type="button" class="btn btn-warning btn-sm w-100 fw-bold py-2 shadow-sm" data-bs-toggle="collapse" data-bs-target="#collapseSesiones<?php echo $dia['fecha']; ?>" aria-expanded="false" style="border-radius: 8px;">
                                                 <i class="fa-solid fa-folder-open me-2"></i>Cargar Sesión Guardada
                                             </button>
                                             
                                             <div class="collapse mt-2 text-start" id="collapseSesiones<?php echo $dia['fecha']; ?>">
                                                 <div class="card card-body bg-dark border border-secondary p-2" style="border-radius: 8px;">
                                                     <input type="text" class="form-control form-control-custom form-control-sm mb-2" 
                                                            placeholder="🔍 Buscar sesión..." onkeyup="filtrarSesionesCollapse(this, '<?php echo $dia['fecha']; ?>')">
                                                     
                                                     <div class="list-group list-group-flush overflow-auto" style="max-height: 180px;" id="listSesiones<?php echo $dia['fecha']; ?>">
                                                         <?php foreach ($entrenamientos_individuales as $ei): ?>
                                                             <button type="button" class="list-group-item list-group-item-action bg-secondary bg-opacity-25 text-white border-bottom border-dark small py-2 d-flex justify-content-between align-items-center text-start"
                                                                     onclick="cargarEntrenamientoItem(this, '<?php echo $dia['fecha']; ?>')"
                                                                     data-titulo="<?php echo htmlspecialchars($ei['titulo']); ?>" 
                                                                     data-tipo="<?php echo htmlspecialchars($ei['tipo_sesion']); ?>" 
                                                                     data-terreno="<?php echo htmlspecialchars($ei['terreno']); ?>" 
                                                                     data-distancia="<?php echo $ei['distancia_km']; ?>" 
                                                                     data-ritmo="<?php echo htmlspecialchars($ei['ritmo_sugerido']); ?>" 
                                                                     data-desc="<?php echo htmlspecialchars($ei['descripcion']); ?>">
                                                                 <div style="flex: 1; min-width: 0;" class="pe-2">
                                                                     <strong class="text-warning text-truncate d-block" style="font-size: 0.8rem;"><?php echo htmlspecialchars($ei['titulo']); ?></strong>
                                                                     <span class="text-secondary d-block text-truncate" style="font-size: 0.7rem;"><?php echo htmlspecialchars($ei['tipo_sesion'] . ' - ' . $ei['terreno']); ?></span>
                                                                 </div>
                                                                 <span class="badge bg-secondary flex-shrink-0"><?php echo $ei['distancia_km']; ?> km</span>
                                                             </button>
                                                         <?php endforeach; ?>
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     <?php endif; ?>
                                     
                                    <div class="mb-3">
                                        <label for="titulo<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Título del Entrenamiento *</label>
                                        <input type="text" name="titulo" id="titulo<?php echo $dia['fecha']; ?>" class="form-control form-control-custom" placeholder="Ej: Pasadas de velocidad 5x1000m" value="<?php echo $rutina ? htmlspecialchars($rutina['titulo']) : ''; ?>" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label for="tipo_sesion<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Tipo de Sesión *</label>
                                            <select name="tipo_sesion" id="tipo_sesion<?php echo $dia['fecha']; ?>" class="form-select form-control-custom" required>
                                                <option value="Bici" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Bici') ? 'selected' : ''; ?>>Bici</option>
                                                <option value="Cambios de Ritmo" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Cambios de Ritmo') ? 'selected' : ''; ?>>Cambios de Ritmo</option>
                                                <option value="Cuestas" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Cuestas') ? 'selected' : ''; ?>>Cuestas</option>
                                                <option value="Fondo" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Fondo') ? 'selected' : ''; ?>>Fondo</option>
                                                <option value="Pasadas" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Pasadas') ? 'selected' : ''; ?>>Pasadas</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label for="terreno<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Terreno *</label>
                                            <select name="terreno" id="terreno<?php echo $dia['fecha']; ?>" class="form-select form-control-custom" required>
                                                <option value="Montaña" <?php echo ($rutina && $rutina['terreno'] === 'Montaña') ? 'selected' : ''; ?>>Montaña</option>
                                                <option value="Pista" <?php echo ($rutina && $rutina['terreno'] === 'Pista') ? 'selected' : ''; ?>>Pista</option>
                                                <option value="Plano" <?php echo ($rutina && $rutina['terreno'] === 'Plano') ? 'selected' : ''; ?>>Plano</option>
                                                <option value="Técnico" <?php echo ($rutina && $rutina['terreno'] === 'Técnico') ? 'selected' : ''; ?>>Técnico</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label for="distancia_km<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Distancia (km)</label>
                                            <input type="number" step="0.1" name="distancia_km" id="distancia_km<?php echo $dia['fecha']; ?>" class="form-control form-control-custom" placeholder="Ej: 10" value="<?php echo $rutina ? $rutina['distancia_km'] : '0'; ?>">
                                        </div>
                                        <div class="col-6">
                                            <label for="ritmo_sugerido<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Ritmo Sugerido</label>
                                            <input type="text" name="ritmo_sugerido" id="ritmo_sugerido<?php echo $dia['fecha']; ?>" class="form-control form-control-custom" placeholder="Ej: 5:45 min/km" value="<?php echo $rutina ? htmlspecialchars($rutina['ritmo_sugerido'] ?? '') : ''; ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="descripcion<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Instrucciones Detalladas *</label>
                                        <textarea name="descripcion" id="descripcion<?php echo $dia['fecha']; ?>" class="form-control form-control-custom" rows="4" placeholder="Especifica la entrada en calor, el bloque principal y la vuelta a la calma." required><?php echo $rutina ? htmlspecialchars($rutina['descripcion'] ?? '') : "Movilidad + +Elongacion\n\nNota:"; ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-top border-dark">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-trail btn-sm">Guardar Planificación</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Columna de Contenido Personalizado -->
            <div class="col-md-4 mb-4">
                <div class="d-flex flex-column align-items-center gap-4 w-100">
                    
                    <!-- Fila con Próximo Objetivo y Botones al mismo nivel de altura -->
                    <div class="d-flex flex-wrap justify-content-center align-items-center gap-3 w-100">
                        <?php if ($proximo_objetivo): ?>
                            <div class="bg-dark border border-trail rounded p-3 shadow-sm text-center flex-grow-1" style="min-width: 240px; max-width: 280px; min-height: 140px; display: flex; flex-direction: column; justify-content: center;">
                                <span class="d-block text-trail text-uppercase fw-bold small mb-2"><i class="fa-solid fa-mountain-sun me-1"></i>Próximo Objetivo</span>
                                <h5 class="text-white fw-bold mb-2" style="font-size: 1.05rem;"><?php echo htmlspecialchars($proximo_objetivo['titulo']); ?></h5>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($proximo_objetivo['distancia_elegida']); ?></span>
                                    <span class="text-warning small fw-bold" style="font-size: 0.75rem;"><i class="fa-regular fa-clock me-1"></i>Faltan <?php echo $proximo_objetivo['faltan_dias']; ?> días</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex flex-column gap-2 align-items-center">
                            <button class="btn btn-warning text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#aplicarPlantillaModal" style="width: 200px; font-size: 0.85rem; padding: 0.5rem 1rem;">
                                <i class="fa-solid fa-layer-group me-2"></i>Aplicar Plantilla
                            </button>
                            <button class="btn btn-success-custom text-white fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#cargarRecursoModal" style="width: 200px; font-size: 0.85rem; padding: 0.5rem 1rem;">
                                <i class="fa-solid fa-folder-plus me-2"></i>Cargar Recurso
                            </button>
                            <button class="btn btn-trail fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#retroalimentacionModal" style="width: 200px; font-size: 0.85rem; padding: 0.5rem 1rem;">
                                <i class="fa-solid fa-comments me-2"></i>Retroalimentación
                            </button>
                        </div>
                    </div>

                    <!-- Historial de Material Asignado (Recursos Compartidos) abajo, centrado -->
                    <div class="card-premium p-4 w-100 text-center" style="max-width: 400px;">
                        <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-folder-open text-warning me-2"></i>Material Compartido</h5>
                        
                        <?php
                        // Consultar contenido asignado a este alumno
                        $stmtRec = $pdo->prepare("
                            SELECT cr.* FROM contenido_recurso cr
                            JOIN contenido_asignado ca ON cr.id = ca.recurso_id
                            WHERE ca.alumno_id = ?
                            ORDER BY ca.fecha_asignacion DESC
                        ");
                        $stmtRec->execute([$alumno_id]);
                        $material = $stmtRec->fetchAll();
                        ?>

                        <?php if (count($material) > 0): ?>
                            <div class="list-group list-group-flush bg-transparent text-start">
                                <?php foreach ($material as $mat): 
                                    $icon = "fa-file-lines";
                                    if ($mat['tipo'] === 'video') $icon = "fa-circle-play";
                                    if ($mat['tipo'] === 'link') $icon = "fa-link";
                                ?>
                                    <div class="list-group-item bg-transparent text-secondary border-secondary px-0 py-3">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid <?php echo $icon; ?> text-warning"></i>
                                                <h6 class="text-white fw-bold mb-0 small"><?php echo htmlspecialchars($mat['titulo']); ?></h6>
                                            </div>
                                            <span class="badge bg-dark border border-secondary text-uppercase small" style="font-size: 0.55rem;"><?php echo $mat['tipo']; ?></span>
                                        </div>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($mat['descripcion']); ?></p>
                                        <a href="<?php echo htmlspecialchars($mat['url']); ?>" target="_blank" class="text-warning small text-decoration-none">
                                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Abrir Enlace
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0 py-3">No has compartido recursos todavía.</p>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- MODAL APLICAR PLANTILLA -->
        <div class="modal fade" id="aplicarPlantillaModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-custom">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-layer-group text-warning me-2"></i>Aplicar Plantilla</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="/actions/admin_plantilla_action.php" method="POST">
                        <input type="hidden" name="action" value="aplicar_plantilla">
                        <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                        <div class="modal-body">
                            <?php if (count($plantillas_entrenador) > 0): ?>
                                <div class="mb-3">
                                    <label class="form-label form-label-custom">Seleccionar Plantilla</label>
                                    <select name="plantilla_id" class="form-select form-select-custom bg-dark text-white border-secondary" required>
                                        <option value="">-- Elige una plantilla --</option>
                                        <?php foreach ($plantillas_entrenador as $p): ?>
                                            <option value="<?php echo $p['id']; ?>" data-fecha-inicio="<?php echo htmlspecialchars($p['fecha_inicio'] ?? ''); ?>"><?php echo htmlspecialchars($p['titulo']); ?> (<?php echo $p['duracion_dias']; ?> días)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-label-custom">Fecha de Inicio (Día 1)</label>
                                    <input type="date" name="fecha_inicio" class="form-control form-control-custom bg-dark text-white border-secondary" value="<?php echo $fecha_inicio; ?>" required>
                                    <div class="form-text text-secondary mt-2"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i>¡Atención! Si ya hay rutinas programadas en esas fechas, se sobreescribirán con las de la plantilla.</div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning text-dark border-0">
                                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Aún no has creado ninguna plantilla. Ve a la sección <strong>Plantillas</strong> en el menú superior para crear una.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                            <?php if (count($plantillas_entrenador) > 0): ?>
                                <button type="submit" class="btn btn-warning text-dark fw-bold">Aplicar a Calendario</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL CARGAR RECURSO -->
        <div class="modal fade" id="cargarRecursoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-custom">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-folder-plus text-warning me-2"></i>Asignar Recurso</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="/actions/admin_rutina_action.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="assign_content">
                        <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                        <div class="modal-body text-start">
                            <p class="text-secondary small mb-3">Comparte guías alimentarias, rutinas de gimnasio o links de videos instructivos.</p>
                            <div class="mb-3">
                                <label for="titulo_content" class="form-label form-label-custom">Título del Recurso *</label>
                                <input type="text" name="titulo" id="titulo_content" class="form-control form-control-custom" placeholder="Ej: Guía de Fortalecimiento Tobillos" required>
                            </div>
                            <div class="mb-3">
                                <label for="tipo_content" class="form-label form-label-custom">Tipo de Recurso *</label>
                                <select name="tipo" id="tipo_content" class="form-select form-control-custom" required>
                                    <option value="pdf">PDF / Documento</option>
                                    <option value="video">Video Instructivo</option>
                                    <option value="link">Enlace / Web URL</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-custom">Origen del Recurso</label>
                                <div class="d-flex gap-3 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="origen_recurso" id="origen_url" value="url" checked onclick="toggleOrigenRecurso('url')">
                                        <label class="form-check-label text-white small" for="origen_url">Ingresar URL</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="origen_recurso" id="origen_archivo" value="archivo" onclick="toggleOrigenRecurso('archivo')">
                                        <label class="form-check-label text-white small" for="origen_archivo">Subir Archivo</label>
                                    </div>
                                </div>
                            </div>
                            <div id="wrapper_url_recurso" class="mb-3">
                                <label for="url_content" class="form-label form-label-custom">Enlace (URL) *</label>
                                <input type="url" name="url" id="url_content" class="form-control form-control-custom" placeholder="https://example.com/archivo.pdf" required>
                            </div>
                            <div id="wrapper_archivo_recurso" class="mb-3 d-none">
                                <label for="archivo_content" class="form-label form-label-custom">Subir Archivo *</label>
                                <input type="file" name="recurso_archivo" id="archivo_content" class="form-control form-control-custom" accept="image/png, image/jpeg, image/jpg, application/pdf">
                                <div class="form-text text-secondary">Formatos permitidos: PDF, PNG, JPG, JPEG (Max 10MB).</div>
                            </div>
                            <div class="mb-3">
                                <label for="desc_content" class="form-label form-label-custom">Breve Descripción</label>
                                <textarea name="descripcion" id="desc_content" class="form-control form-control-custom" rows="3" placeholder="Indicaciones rápidas sobre el uso del recurso."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-trail btn-sm"><i class="fa-solid fa-share me-2"></i>Compartir Recurso</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL RETROALIMENTACIÓN MENSUAL -->
        <div class="modal fade" id="retroalimentacionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-custom">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-comments text-warning me-2"></i>Retroalimentación Mensual</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <?php
                    $mes_actual_db = date('Y-m', $timestamp_actual);
                    
                    // Buscar si ya existe feedback para este alumno y mes
                    $stmtFBM = $pdo->prepare("SELECT comentario FROM feedback_mensual WHERE alumno_id = ? AND mes = ?");
                    $stmtFBM->execute([$alumno_id, $mes_actual_db]);
                    $feedback_actual = $stmtFBM->fetchColumn();
                    ?>
                    <form action="/actions/admin_feedback_mensual.php" method="POST">
                        <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                        <input type="hidden" name="fecha_redirect" value="<?php echo $fecha_actual; ?>">
                        <div class="modal-body text-start">
                            <p class="text-secondary small mb-3">Deja un comentario o evaluación general. El alumno podrá visualizarlo en su panel de control en el mes correspondiente.</p>
                            <div class="mb-3">
                                <label class="form-label form-label-custom">Mes correspondiente *</label>
                                <input type="month" name="mes" class="form-control form-control-custom" value="<?php echo $mes_actual_db; ?>" required>
                            </div>
                            <div class="mb-3">
                                <textarea name="comentario" class="form-control form-control-custom" rows="5" placeholder="Ej: Excelente mes de entrenamiento, logramos el objetivo de volumen. Cuidado con la carga en cuestas..." required><?php echo htmlspecialchars($feedback_actual ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm">
                                <i class="fa-solid fa-save me-2"></i><?php echo $feedback_actual ? 'Actualizar' : 'Guardar'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const buscador = document.getElementById("buscadorPlanificador");
    if (buscador) {
        buscador.addEventListener("input", function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll("tbody tr");
            rows.forEach(row => {
                const nameCell = row.querySelector("td span.text-white");
                if (nameCell) {
                    const name = nameCell.textContent.toLowerCase();
                    if (name.includes(query)) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                }
            });
        });
    }

    // Actualizar la fecha de inicio según la plantilla seleccionada
    const selectPlantilla = document.querySelector("select[name='plantilla_id']");
    const inputFechaInicio = document.querySelector("input[name='fecha_inicio']");
    if (selectPlantilla && inputFechaInicio) {
        const fechaDefecto = inputFechaInicio.value;
        selectPlantilla.addEventListener("change", function() {
            const option = this.options[this.selectedIndex];
            const fechaInicioPlantilla = option.getAttribute("data-fecha-inicio");
            if (fechaInicioPlantilla) {
                inputFechaInicio.value = fechaInicioPlantilla;
            } else {
                inputFechaInicio.value = fechaDefecto;
            }
        });
    }
});

function cargarEntrenamientoItem(button, suffix) {
    const titulo = button.getAttribute('data-titulo') || '';
    const tipo = button.getAttribute('data-tipo') || '';
    const terreno = button.getAttribute('data-terreno') || '';
    const distancia = button.getAttribute('data-distancia') || '0.00';
    const ritmo = button.getAttribute('data-ritmo') || '';
    let desc = button.getAttribute('data-desc') || '';

    // Limpiar prefijos o sufijos de plantilla por defecto para no duplicarlos ni cargarlos innecesariamente
    const pattern = /Movilidad\s*\+\s*\+?\s*Elongaci(?:ó|o)n\s*(?:\r?\n)*\s*Nota:\s*/gi;
    desc = desc.replace(pattern, '').trim();
    const oldPattern = /Movilidad\s*\+\s*(?:\r?\n)*\s*Elongaci(?:ó|o)n\s*(?:\r?\n)*\s*Nota:\s*/gi;
    desc = desc.replace(oldPattern, '').trim();

    const inputTitulo = document.getElementById('titulo' + suffix);
    const inputTipo = document.getElementById('tipo_sesion' + suffix);
    const inputTerreno = document.getElementById('terreno' + suffix);
    const inputDistancia = document.getElementById('distancia_km' + suffix);
    const inputRitmo = document.getElementById('ritmo_sugerido' + suffix);
    const inputDesc = document.getElementById('descripcion' + suffix);

    if (inputTitulo) inputTitulo.value = titulo;
    if (inputTipo) inputTipo.value = tipo;
    if (inputTerreno) inputTerreno.value = terreno;
    if (inputDistancia) inputDistancia.value = distancia;
    if (inputRitmo) inputRitmo.value = ritmo;
    if (inputDesc) inputDesc.value = desc;

    // Colapsar el panel después de seleccionar
    const collapseEl = document.getElementById('collapseSesiones' + suffix);
    if (collapseEl) {
        const bsCollapse = bootstrap.Collapse.getInstance(collapseEl) || new bootstrap.Collapse(collapseEl);
        bsCollapse.hide();
    }
}

function filtrarSesionesCollapse(input, suffix) {
    const filter = input.value.toLowerCase();
    const list = document.getElementById('listSesiones' + suffix);
    if (!list) return;
    const buttons = list.getElementsByTagName('button');
    for (let i = 0; i < buttons.length; i++) {
        const text = buttons[i].innerText.toLowerCase();
        if (text.includes(filter)) {
            buttons[i].style.setProperty('display', 'flex', 'important');
        } else {
            buttons[i].style.setProperty('display', 'none', 'important');
        }
    }
}

function toggleOrigenRecurso(origen) {
    const wrapperUrl = document.getElementById('wrapper_url_recurso');
    const wrapperArchivo = document.getElementById('wrapper_archivo_recurso');
    const inputUrl = document.getElementById('url_content');
    const inputArchivo = document.getElementById('archivo_content');

    if (origen === 'url') {
        wrapperUrl.classList.remove('d-none');
        wrapperArchivo.classList.add('d-none');
        inputUrl.setAttribute('required', 'true');
        inputArchivo.removeAttribute('required');
    } else {
        wrapperUrl.classList.add('d-none');
        wrapperArchivo.classList.remove('d-none');
        inputUrl.removeAttribute('required');
        inputArchivo.setAttribute('required', 'true');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
