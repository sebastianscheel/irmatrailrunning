<?php
$page_title = "Planificador de Rutinas";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador
require_rol(['admin', 'entrenador']);

$alumno_id = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$alumno = null;

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
}

$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty_fields': $error_msg = "Completa todos los campos obligatorios del entrenamiento."; break;
        case 'empty_content': $error_msg = "Completa todos los campos del recurso personalizado."; break;
        case 'invalid_rutina': $error_msg = "ID de rutina no vÃ¡lido."; break;
        case 'db': $error_msg = "Error interno de base de datos."; break;
    }
}

$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'rutina_ok': $success_msg = "Entrenamiento programado con Ã©xito."; break;
        case 'delete_ok': $success_msg = "Entrenamiento eliminado del calendario."; break;
        case 'content_ok': $success_msg = "Contenido personalizado asignado correctamente."; break;
    }
}

// Obtener lista completa de alumnos para el buscador inicial
try {
    $stmtList = $pdo->query("
        SELECT ap.id AS alumno_id, u.nombre, u.apellido, ap.plan_tipo, ap.nivel
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        ORDER BY u.apellido ASC, u.nombre ASC
    ");
    $alumnos_lista = $stmtList->fetchAll();
} catch (PDOException $e) {
    $alumnos_lista = [];
}
?>

<div class="container dashboard-container">
    <?php if (!$alumno): ?>
        <!-- SELECCIONADOR DE ALUMNO -->
        <div class="mb-4">
            <h2 class="text-white fw-bold"><i class="fa-solid fa-calendar-alt text-warning me-2"></i>Planificador de Rutinas</h2>
            <p class="text-secondary mb-0">Selecciona un corredor del equipo para planificar su calendario semanal de entrenamientos y compartir recursos personalizados.</p>
        </div>

        <div class="card-premium p-4">
            <h5 class="text-white fw-bold mb-4">Listado de Corredores</h5>
            <?php if (count($alumnos_lista) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle border border-secondary" style="border-radius: 12px; overflow: hidden;">
                        <thead>
                            <tr class="bg-dark text-secondary">
                                <th class="border-secondary py-3">Alumno</th>
                                <th class="border-secondary py-3">Plan</th>
                                <th class="border-secondary py-3">Nivel</th>
                                <th class="border-secondary py-3 text-end">AcciÃ³n</th>
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
                    <p class="mb-0">No hay alumnos registrados en el sistema. Registra un alumno primero desde la pestaÃ±a "Alumnos".</p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- PLANIFICADOR DEL ALUMNO SELECCIONADO -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <a href="/admin/planificador.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="fa-solid fa-arrow-left me-2"></i>Volver a la Lista</a>
                <h2 class="text-white fw-bold">PlanificaciÃ³n de: <span style="color: var(--trail-orange);"><?php echo htmlspecialchars($alumno['nombre'] . " " . $alumno['apellido']); ?></span></h2>
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

        <div class="row">
            <!-- Calendario Planificador -->
            <div class="col-md-7 mb-4">
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
                                                    <span class="badge badge-tipo badge-<?php echo strtolower($rutina['tipo_sesion']); ?>">
                                                        <?php echo htmlspecialchars($rutina['tipo_sesion']); ?>
                                                    </span>
                                                    <span class="text-secondary small"><i class="fa-solid fa-route text-muted me-1"></i><?php echo $rutina['distancia_km']; ?> km</span>
                                                    <span class="text-secondary small"><i class="fa-solid fa-map text-muted me-1"></i><?php echo htmlspecialchars($rutina['terreno']); ?></span>
                                                </div>
                                                <h6 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($rutina['titulo']); ?></h6>
                                                <p class="text-secondary small mb-0"><?php echo nl2br(htmlspecialchars($rutina['descripcion'])); ?></p>
                                            <?php else: ?>
                                                <span class="text-muted small italic"><i class="fa-solid fa-mug-hot me-1"></i>Día Libre</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 text-start text-md-end">
                                            <div class="d-flex justify-content-start justify-content-md-end gap-2">
                                                <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal<?php echo $dia['fecha']; ?>">
                                                    <i class="fa-solid fa-plus-circle me-1"></i> <?php echo $rutina ? 'Editar' : 'Programar'; ?>
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
                                            <div class="badge badge-tipo badge-<?php echo strtolower($rutina['tipo_sesion']); ?> d-block text-truncate mb-1" style="font-size: 0.6rem;" title="<?php echo htmlspecialchars($rutina['titulo']); ?>">
                                                <?php echo htmlspecialchars($rutina['tipo_sesion']); ?>
                                            </div>
                                            <div class="small text-truncate text-secondary" style="font-size: 0.7rem;"><?php echo $rutina['distancia_km']; ?>km</div>
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
                                    <i class="fa-solid fa-stopwatch text-warning me-2"></i>Programar: <?php echo $dia['nombre'] . " (" . $dia['fecha_formateada'] . ")"; ?>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="/actions/admin_rutina_action.php" method="POST">
                                <input type="hidden" name="action" value="create_rutina">
                                <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                                <input type="hidden" name="fecha" value="<?php echo $dia['fecha']; ?>">
                                
                                <div class="modal-body text-start">
                                    <div class="mb-3">
                                        <label for="titulo<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Título del Entrenamiento *</label>
                                        <input type="text" name="titulo" id="titulo<?php echo $dia['fecha']; ?>" class="form-control form-control-custom" placeholder="Ej: Pasadas de velocidad 5x1000m" value="<?php echo $rutina ? htmlspecialchars($rutina['titulo']) : ''; ?>" required>
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label for="tipo_sesion<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Tipo de Sesión *</label>
                                            <select name="tipo_sesion" id="tipo_sesion<?php echo $dia['fecha']; ?>" class="form-select form-control-custom" required>
                                                <option value="Fondo" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Fondo') ? 'selected' : ''; ?>>Fondo</option>
                                                <option value="Cuestas" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Cuestas') ? 'selected' : ''; ?>>Cuestas</option>
                                                <option value="Pasadas" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Pasadas') ? 'selected' : ''; ?>>Pasadas</option>
                                                <option value="Fuerza" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Fuerza') ? 'selected' : ''; ?>>Fuerza</option>
                                                <option value="Descanso" <?php echo ($rutina && $rutina['tipo_sesion'] === 'Descanso') ? 'selected' : ''; ?>>Descanso</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label for="terreno<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Terreno *</label>
                                            <select name="terreno" id="terreno<?php echo $dia['fecha']; ?>" class="form-select form-control-custom" required>
                                                <option value="Sendero" <?php echo ($rutina && $rutina['terreno'] === 'Sendero') ? 'selected' : ''; ?>>Sendero</option>
                                                <option value="Calle" <?php echo ($rutina && $rutina['terreno'] === 'Calle') ? 'selected' : ''; ?>>Calle</option>
                                                <option value="Técnico" <?php echo ($rutina && $rutina['terreno'] === 'Técnico') ? 'selected' : ''; ?>>Técnico</option>
                                                <option value="Mixto" <?php echo ($rutina && $rutina['terreno'] === 'Mixto') ? 'selected' : ''; ?>>Mixto</option>
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
                                            <input type="text" name="ritmo_sugerido" id="ritmo_sugerido<?php echo $dia['fecha']; ?>" class="form-control form-control-custom" placeholder="Ej: 5:45 min/km" value="<?php echo $rutina ? htmlspecialchars($rutina['ritmo_sugerido']) : ''; ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="descripcion<?php echo $dia['fecha']; ?>" class="form-label form-label-custom">Instrucciones Detalladas *</label>
                                        <textarea name="descripcion" id="descripcion<?php echo $dia['fecha']; ?>" class="form-control form-control-custom" rows="4" placeholder="Especifica la entrada en calor, el bloque principal y la vuelta a la calma." required><?php echo $rutina ? htmlspecialchars($rutina['descripcion']) : ''; ?></textarea>
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
                    </div>
                </div>
            </div>

            <!-- Columna de Contenido Personalizado -->
            <div class="col-md-5 mb-4">
                <!-- Formulario para Asignar Material -->
                <div class="card-premium p-4 mb-4">
                    <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-folder-plus text-warning me-2"></i>Asignar Recurso</h5>
                    <p class="text-secondary small mb-4">Comparte guÃ­as alimentarias, rutinas de gimnasio o links de videos instructivos.</p>
                    
                    <form action="/actions/admin_rutina_action.php" method="POST">
                        <input type="hidden" name="action" value="assign_content">
                        <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">

                        <div class="mb-3">
                            <label for="titulo_content" class="form-label form-label-custom">TÃ­tulo del Recurso *</label>
                            <input type="text" name="titulo" id="titulo_content" class="form-control form-control-custom" placeholder="Ej: GuÃ­a de Fortalecimiento Tobillos" required>
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
                            <label for="url_content" class="form-label form-label-custom">Enlace (URL) *</label>
                            <input type="url" name="url" id="url_content" class="form-control form-control-custom" placeholder="https://example.com/archivo.pdf" required>
                        </div>

                        <div class="mb-4">
                            <label for="desc_content" class="form-label form-label-custom">Breve DescripciÃ³n</label>
                            <textarea name="descripcion" id="desc_content" class="form-control form-control-custom" rows="2" placeholder="Indicaciones rÃ¡pidas sobre el uso del recurso."></textarea>
                        </div>

                        <button type="submit" class="btn btn-trail w-100"><i class="fa-solid fa-share me-2"></i>Compartir Recurso</button>
                    </form>
                </div>

                <!-- Historial de Material Asignado -->
                <div class="card-premium p-4">
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
                        <div class="list-group list-group-flush bg-transparent">
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
                        <p class="text-muted small text-center mb-0 py-3">No has compartido recursos todavÃ­a.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

