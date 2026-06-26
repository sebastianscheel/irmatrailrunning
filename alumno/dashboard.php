<?php
$page_title = "Mi Dashboard";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

// Validar estado de la DDJJ y actualizar sesión
check_alumno_status($pdo);

// Obtener datos del perfil del alumno
$stmt = $pdo->prepare("
    SELECT ap.*, u.foto_perfil_url 
    FROM alumno_perfil ap
    JOIN usuarios u ON ap.usuario_id = u.id 
    WHERE ap.usuario_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$perfil = $stmt->fetch();

if (!$perfil) {
    header("Location: /logout.php");
    exit;
}

$alumno_id = $perfil['id'];
$esta_activo = (int)$perfil['activo'];

// Si la cuenta está inactiva, bloqueamos las rutinas y mostramos la advertencia
?>

<div class="container dashboard-container">
    <div class="row">
        <!-- Sidebar Perfil Rápido (Responsivo) -->
        <div class="col-lg-3 mb-4">
            <div class="card-premium p-3 text-center">
                <div class="avatar-container mb-3 position-relative d-inline-block mx-auto">
                    <?php if (!empty($perfil['foto_perfil_url'])): ?>
                        <img src="<?php echo htmlspecialchars($perfil['foto_perfil_url']); ?>" alt="Perfil" class="rounded-circle shadow" style="width: 70px; height: 70px; object-fit: cover; border: 2px solid var(--trail-orange);">
                    <?php else: ?>
                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 70px; height: 70px; font-size: 1.8rem; font-family: var(--font-titles);">
                            <?php echo strtoupper(substr($_SESSION['user_nombre'], 0, 1) . substr($_SESSION['user_apellido'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <span class="position-absolute bottom-0 end-0 p-1 border border-light rounded-circle <?php echo $esta_activo ? 'bg-success' : 'bg-danger'; ?>" style="width: 15px; height: 15px;"></span>
                </div>
                
                <h5 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($_SESSION['user_nombre'] . " " . $_SESSION['user_apellido']); ?></h5>
                <p class="text-muted small mb-2"><?php echo htmlspecialchars($perfil['plan_tipo']); ?> &bull; DNI: <?php echo htmlspecialchars($perfil['dni']); ?></p>
                
                <div class="p-2 rounded bg-dark border border-secondary text-start mb-3">
                    <small class="d-block text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">Estado Cuenta:</small>
                    <?php if ($esta_activo): ?>
                        <span class="text-success small fw-bold"><i class="fa-solid fa-circle-check me-1"></i>Activo / Al día</span>
                    <?php else: ?>
                        <span class="text-danger small fw-bold"><i class="fa-solid fa-circle-xmark me-1"></i>Inactivo (Falta de Pago)</span>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2">
                    <a href="/alumno/perfil.php" class="btn btn-trail-outline btn-sm"><i class="fa-solid fa-user-gear me-2"></i>Editar Perfil</a>
                    <?php if (!$esta_activo): ?>
                        <a href="/alumno/reportar_pago.php" class="btn btn-trail btn-sm"><i class="fa-solid fa-receipt me-2"></i>Reportar Pago</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="col-lg-9">
            <?php if (!$esta_activo): ?>
                <!-- Alerta de Cuenta Inactiva -->
                <div class="card-premium p-4 border-danger mb-4" style="background: rgba(231, 111, 81, 0.05);">
                    <div class="text-center py-4">
                        <i class="fa-solid fa-lock text-danger fa-4x mb-3 animate-pulse"></i>
                        <h4 class="text-white fw-bold">Planificación Bloqueada</h4>
                        <p class="text-secondary max-width-600 mx-auto">
                            Tu cuenta se encuentra actualmente <strong>Inactiva</strong> debido a que no registramos el pago de la membresía del mes en curso. Para volver a habilitar tu calendario de entrenamientos, realiza la transferencia y sube tu comprobante.
                        </p>
                        <div class="mt-4">
                            <a href="/alumno/reportar_pago.php" class="btn btn-trail"><i class="fa-solid fa-wallet me-2"></i>Reportar Pago Mensual</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Dashboard Activo -->
                
                <!-- Mensajes de feedback -->
                <?php if (isset($_GET['msg'])): ?>
                    <?php if ($_GET['msg'] === 'feedback_ok'): ?>
                        <div class="alert alert-success-custom alert-dismissible fade show mb-4" role="alert">
                            <i class="fa-solid fa-circle-check me-2"></i> ¡Entrenamiento registrado con éxito! Tu entrenador ya puede ver tus tiempos y comentarios.
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif ($_GET['msg'] === 'ddjj_ok'): ?>
                        <div class="alert alert-success-custom alert-dismissible fade show mb-4" role="alert">
                            <i class="fa-solid fa-file-contract me-2"></i> Declaración jurada firmada digitalmente. ¡Bienvenido a IB Trailrunning!
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php
                // Fechas de la semana actual (Lunes a Domingo)
                $lunes_timestamp = strtotime('monday this week');
                $dias_semana = [];
                for ($i = 0; $i < 7; $i++) {
                    $timestamp = strtotime("+$i day", $lunes_timestamp);
                    $dias_semana[date('Y-m-d', $timestamp)] = [
                        'fecha_formateada' => date('d/m', $timestamp),
                        'dia_nombre' => date('N', $timestamp), // 1=Lunes, 7=Domingo
                        'nombre' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'][date('N', $timestamp) - 1],
                        'rutina' => null
                    ];
                }

                $fecha_inicio = date('Y-m-d', $lunes_timestamp);
                $fecha_fin = date('Y-m-d', strtotime('sunday this week'));

                // Obtener rutinas de la semana asignadas a este alumno
                $stmtRutinas = $pdo->prepare("
                    SELECT * FROM rutina_asignada 
                    WHERE alumno_id = ? AND fecha BETWEEN ? AND ? 
                    ORDER BY fecha ASC
                ");
                $stmtRutinas->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
                $rutinas = $stmtRutinas->fetchAll();

                // Contadores para compliance
                $total_rutinas = count($rutinas);
                $completadas = 0;

                foreach ($rutinas as $r) {
                    $fecha_r = $r['fecha'];
                    if (isset($dias_semana[$fecha_r])) {
                        $dias_semana[$fecha_r]['rutina'] = $r;
                        if ($r['completada'] == 1) {
                            $completadas++;
                        }
                    }
                }

                $porcentaje_compliance = $total_rutinas > 0 ? round(($completadas / $total_rutinas) * 100) : 100;
                ?>

                <!-- Compliance Tracker -->
                <div class="card-premium p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white fw-bold"><i class="fa-solid fa-chart-line text-warning me-2"></i>Cumplimiento Semanal</span>
                        <span class="text-secondary small"><?php echo "$completadas de $total_rutinas"; ?> completados (<?php echo $porcentaje_compliance; ?>%)</span>
                    </div>
                    <div class="progress" style="height: 10px; background-color: var(--bg-primary); border: 1px solid var(--border-color);">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $porcentaje_compliance; ?>%; background-color: var(--trail-orange);" aria-valuenow="<?php echo $porcentaje_compliance; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Calendario de Rutinas Semanal -->
                <h4 class="text-white mb-3 fw-bold"><i class="fa-solid fa-calendar-week text-warning me-2"></i>Rutinas de la Semana</h4>
                <p class="text-secondary small mb-4">Semana del <?php echo date('d/m', $lunes_timestamp); ?> al <?php echo date('d/m', strtotime('sunday this week')); ?></p>

                <div class="routine-list">
                    <?php 
                    $hoy = date('Y-m-d');
                    foreach ($dias_semana as $fecha_db => $dia): 
                        $rutina = $dia['rutina'];
                        $es_hoy = ($fecha_db === $hoy);
                        
                        $card_class = "routine-day-card";
                        if ($es_hoy) $card_class .= " today";
                        if ($rutina && $rutina['completada']) $card_class .= " completada";
                        if (!$rutina) $card_class .= " descanso";
                    ?>
                        <div class="<?php echo $card_class; ?>">
                            <div class="row align-items-center">
                                <div class="col-md-2 mb-2 mb-md-0 text-start text-md-center">
                                    <h5 class="text-white fw-bold mb-0"><?php echo htmlspecialchars($dia['nombre']); ?></h5>
                                    <span class="text-muted small"><?php echo $dia['fecha_formateada']; ?></span>
                                    <?php if ($es_hoy): ?>
                                        <span class="badge bg-warning text-dark d-block d-md-inline-block mt-1">Hoy</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-7 mb-3 mb-md-0">
                                    <?php if ($rutina): ?>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge badge-tipo badge-<?php echo strtolower($rutina['tipo_sesion']); ?>">
                                                <?php echo htmlspecialchars($rutina['tipo_sesion']); ?>
                                            </span>
                                            <span class="text-secondary small"><i class="fa-solid fa-map text-muted me-1"></i><?php echo htmlspecialchars($rutina['terreno']); ?></span>
                                            <?php if ($rutina['distancia_km'] > 0): ?>
                                                <span class="text-secondary small"><i class="fa-solid fa-route text-muted me-1"></i><?php echo $rutina['distancia_km']; ?> km</span>
                                            <?php endif; ?>
                                            <?php if (!empty($rutina['ritmo_sugerido'])): ?>
                                                <span class="text-secondary small"><i class="fa-solid fa-stopwatch text-muted me-1"></i><?php echo htmlspecialchars($rutina['ritmo_sugerido']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($rutina['titulo']); ?></h6>
                                        <p class="text-secondary small mb-0"><?php echo nl2br(htmlspecialchars($rutina['descripcion'])); ?></p>
                                        
                                        <?php if ($rutina['completada']): ?>
                                            <!-- Mostrar feedback registrado -->
                                            <div class="mt-2 p-2 bg-dark rounded border border-secondary text-secondary small">
                                                <i class="fa-solid fa-comment-dots me-1 text-success"></i> 
                                                <strong>Feedback:</strong> <?php echo $rutina['feedback_tiempo_minutos']; ?> min | Esfuerzo: <?php echo $rutina['feedback_esfuerzo']; ?>/10
                                                <?php if (!empty($rutina['feedback_comentario'])): ?>
                                                    <br><span class="italic">"<?php echo htmlspecialchars($rutina['feedback_comentario']); ?>"</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <h6 class="text-muted fw-normal mb-1"><i class="fa-solid fa-mug-hot me-2"></i>Día de Descanso o Libre</h6>
                                        <p class="text-muted small mb-0">Aprovecha para recuperar energía o realizar estiramientos suaves.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3 text-start text-md-end">
                                    <?php if ($rutina): ?>
                                        <?php if (!$rutina['completada']): ?>
                                            <button class="btn btn-trail btn-sm w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $rutina['id']; ?>">
                                                <i class="fa-solid fa-check me-1"></i> Registrar
                                            </button>
                                        <?php else: ?>
                                            <span class="text-success small fw-bold d-block"><i class="fa-solid fa-circle-check me-1"></i>Completado</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Modal de Registro de Feedback si hay rutina -->
                        <?php if ($rutina && !$rutina['completada']): ?>
                            <div class="modal fade" id="feedbackModal<?php echo $rutina['id']; ?>" tabindex="-1" aria-labelledby="feedbackModalLabel<?php echo $rutina['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content text-start bg-secondary border border-secondary" style="border-radius: 16px;">
                                        <div class="modal-header border-bottom border-dark">
                                            <h5 class="modal-title text-white fw-bold" id="feedbackModalLabel<?php echo $rutina['id']; ?>">
                                                <i class="fa-solid fa-stopwatch text-warning me-2"></i>Registrar Entrenamiento
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="/actions/alumno_feedback.php" method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="rutina_id" value="<?php echo $rutina['id']; ?>">
                                                <p class="text-secondary small mb-3">Rutina: <strong><?php echo htmlspecialchars($rutina['titulo']); ?></strong></p>
                                                
                                                <div class="mb-3">
                                                    <label for="tiempo<?php echo $rutina['id']; ?>" class="form-label form-label-custom">Tiempo Total (en minutos)</label>
                                                    <input type="number" step="0.1" name="tiempo" id="tiempo<?php echo $rutina['id']; ?>" class="form-control form-control-custom" placeholder="Ej: 55" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="esfuerzo<?php echo $rutina['id']; ?>" class="form-label form-label-custom d-flex justify-content-between">
                                                        <span>Esfuerzo Percibido</span>
                                                        <span id="esfuerzoVal<?php echo $rutina['id']; ?>" class="badge bg-warning text-dark">5</span>
                                                    </label>
                                                    <input type="range" class="form-range" name="esfuerzo" id="esfuerzo<?php echo $rutina['id']; ?>" min="1" max="10" value="5" oninput="document.getElementById('esfuerzoVal<?php echo $rutina['id']; ?>').innerText = this.value">
                                                    <div class="d-flex justify-content-between text-muted small mt-1">
                                                        <span>1 (Muy fácil)</span>
                                                        <span>10 (Máximo)</span>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label for="comentario<?php echo $rutina['id']; ?>" class="form-label form-label-custom">Comentarios del entrenamiento</label>
                                                    <textarea name="comentario" id="comentario<?php echo $rutina['id']; ?>" class="form-control form-control-custom" rows="3" placeholder="¿Cómo te sentiste? ¿Hubo molestias físicas?"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top border-dark">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-trail btn-sm">Guardar Registro</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Sección de Recursos Compartidos -->
                <h4 class="text-white mt-5 mb-3 fw-bold"><i class="fa-solid fa-folder-open text-warning me-2"></i>Contenido Personalizado</h4>
                <?php
                // Obtener contenido asignado a este alumno
                $stmtContenido = $pdo->prepare("
                    SELECT cr.* FROM contenido_recurso cr
                    JOIN contenido_asignado ca ON cr.id = ca.recurso_id
                    WHERE ca.alumno_id = ?
                    ORDER BY ca.fecha_asignacion DESC
                ");
                $stmtContenido->execute([$alumno_id]);
                $recursos = $stmtContenido->fetchAll();
                ?>

                <?php if (count($recursos) > 0): ?>
                    <div class="row g-3">
                        <?php foreach ($recursos as $rec): 
                            $icon = "fa-file-lines";
                            if ($rec['tipo'] === 'video') $icon = "fa-circle-play";
                            if ($rec['tipo'] === 'link') $icon = "fa-link";
                        ?>
                            <div class="col-md-6">
                                <div class="card-premium p-3 h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="text-warning"><i class="fa-solid <?php echo $icon; ?> fa-lg"></i></span>
                                            <span class="badge bg-dark border border-secondary text-uppercase small" style="font-size: 0.65rem;"><?php echo $rec['tipo']; ?></span>
                                        </div>
                                        <h6 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($rec['titulo']); ?></h6>
                                        <p class="text-secondary small mb-3"><?php echo htmlspecialchars($rec['descripcion']); ?></p>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($rec['url']); ?>" target="_blank" class="btn btn-trail-outline btn-sm w-100 mt-auto">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>Ver Recurso
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-4 rounded border border-secondary text-center text-secondary small">
                        <i class="fa-solid fa-box-open fa-2x mb-2 text-muted"></i>
                        <p class="mb-0">Tu entrenador no ha compartido materiales personalizados todavía.</p>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
