<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

// Validar estado de la DDJJ y actualizar sesión
check_alumno_status($pdo);

$page_title = "Mi Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Obtener datos del perfil del alumno
$stmt = $pdo->prepare("
    SELECT ap.*, u.foto_perfil_url, u.dni,
           ent.nombre AS ent_nombre, ent.apellido AS ent_apellido, ent.telefono AS ent_telefono
    FROM alumno_perfil ap
    JOIN usuarios u ON ap.usuario_id = u.id 
    LEFT JOIN usuarios ent ON ap.entrenador_id = ent.id
    WHERE ap.usuario_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$perfil = $stmt->fetch();

if (!$perfil) {
    header("Location: /logout.php");
    exit;
}

$alumno_id = $perfil['id'];

// --- NOTIFICACIÓN DE CUMPLEAÑOS ALUMNO ---
$es_cumple = false;
if (!empty($perfil['fecha_nacimiento'])) {
    $cumple_mes_dia = date('m-d', strtotime($perfil['fecha_nacimiento']));
    $hoy_mes_dia = date('m-d');
    if ($cumple_mes_dia === $hoy_mes_dia) {
        $es_cumple = true;
        // Verificar si ya se envió la notificación este año
        $stmtCheckCumple = $pdo->prepare("
            SELECT COUNT(*) FROM notificaciones 
            WHERE usuario_id = ? AND titulo = '¡Feliz Cumpleaños! 🎂' AND YEAR(fecha) = YEAR(CURDATE())
        ");
        $stmtCheckCumple->execute([$_SESSION['user_id']]);
        if ($stmtCheckCumple->fetchColumn() == 0) {
            require_once __DIR__ . '/../includes/audit_helper.php';
            crearNotificacion(
                $pdo, 
                $_SESSION['user_id'], 
                "¡Feliz Cumpleaños! 🎂", 
                "Todo el equipo de Irma Trail Running te desea un excelente día y un gran año de entrenamientos.", 
                "/alumno/dashboard.php"
            );
        }
    }
}
$esta_activo = (int)$perfil['activo'];
$rutinas_por_fecha = [];

// Verificar si está conectado a Strava
$stmtStrava = $pdo->prepare("SELECT fecha_conexion FROM strava_tokens WHERE alumno_id = ?");
$stmtStrava->execute([$alumno_id]);
$strava_conectado = $stmtStrava->fetchColumn() ? true : false;

// Consultar rutina de hoy para el saludo
$hoy_fecha = date('Y-m-d');
$stmtHoy = $pdo->prepare("SELECT tipo_sesion, completada FROM rutina_asignada WHERE alumno_id = ? AND fecha = ?");
$stmtHoy->execute([$alumno_id, $hoy_fecha]);
$rutina_hoy = $stmtHoy->fetch();

$saludo_hoy = "Hoy es un día de descanso.";
if ($rutina_hoy) {
    $tipo_hoy = strtolower($rutina_hoy['tipo_sesion']);
    if ($tipo_hoy !== 'descanso') {
        if ($rutina_hoy['completada']) {
            $saludo_hoy = "¡Entrenamiento de hoy completado!";
        } else {
            $saludo_hoy = "¡Hoy tenés entrenamiento!";
        }
    }
}
?>

<div class="container dashboard-container">
    <div class="row">
        <!-- Sidebar Perfil Rápido (Responsivo) -->
        <div class="col-lg-3 mb-4">
            <div class="card-premium p-3 text-center d-flex flex-column align-items-center">
                <div class="avatar-container mb-2 position-relative d-inline-block mx-auto">
                    <?php if (!empty($perfil['foto_perfil_url'])): ?>
                        <img src="<?php echo htmlspecialchars($perfil['foto_perfil_url']); ?>" alt="Perfil" class="rounded-circle shadow" style="width: 70px; height: 70px; object-fit: cover; border: 2px solid var(--trail-orange);">
                    <?php else: ?>
                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 70px; height: 70px; font-size: 1.8rem; font-family: var(--font-titles);">
                            <?php echo strtoupper(substr($_SESSION['user_nombre'], 0, 1) . substr($_SESSION['user_apellido'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <span class="position-absolute bottom-0 end-0 p-1 border border-light rounded-circle <?php echo $esta_activo === 1 ? 'bg-success' : 'bg-danger'; ?>" style="width: 15px; height: 15px;"></span>
                </div>
                
                <h5 class="fw-bold mb-0 text-center" style="color: #388e7a;"><?php echo htmlspecialchars($_SESSION['user_nombre'] . " " . $_SESSION['user_apellido']); ?></h5>
                <p class="small mb-2 text-muted text-center"><span style="color: #d16b5a; font-weight: 600;"><?php echo htmlspecialchars($perfil['plan_tipo']); ?></span> &bull; DNI: <?php echo htmlspecialchars($perfil['dni'] ?? ''); ?></p>
                
                <!-- Saludo del día integrado (Resumen) -->
                <div class="mb-3 py-2 px-2 border border-secondary rounded bg-dark w-100 text-center" style="background: rgba(255,255,255,0.02) !important;">
                    <i class="fa-solid fa-person-running text-warning me-1"></i>
                    <span class="small text-white fw-bold d-block" style="font-size: 0.8rem;"><?php echo $saludo_hoy; ?></span>
                </div>

                <!-- Entrenador Asignado -->
                <div class="mb-3 w-100">
                    <?php if (!empty($perfil['ent_nombre'])): ?>
                        <div class="p-2 border border-secondary rounded bg-dark text-center" style="background: rgba(255,255,255,0.02) !important;">
                            <small class="d-block text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.65rem;">Entrenador Asignado:</small>
                            <span class="text-white small fw-bold d-block"><i class="fa-solid fa-user-tie me-1 text-trail"></i><?php echo htmlspecialchars($perfil['ent_nombre'] . " " . $perfil['ent_apellido']); ?></span>
                            <?php if (!empty($perfil['ent_telefono'])): ?>
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $perfil['ent_telefono']); ?>" target="_blank" class="text-success small d-block mt-1 text-decoration-none"><i class="fa-brands fa-whatsapp me-1"></i>Contactar</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-2 border border-secondary rounded bg-dark text-center" style="background: rgba(255,255,255,0.02) !important;">
                            <small class="d-block text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.65rem;">Entrenador Asignado:</small>
                            <span class="text-muted small">Sin asignar</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-2 rounded bg-dark border border-secondary text-center mb-3 w-100">
                    <small class="d-block text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.65rem;">Estado Cuenta:</small>
                    <?php if ($esta_activo === 1): ?>
                        <span class="text-success small fw-bold"><i class="fa-solid fa-circle-check me-1"></i>Activo / Al día</span>
                    <?php elseif ($esta_activo === 3): ?>
                        <span class="text-warning small fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Activo / Falta de pago</span>
                    <?php elseif ($esta_activo === 2): ?>
                        <span class="text-danger small fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Inactivo / Falta de pago</span>
                    <?php else: ?>
                        <span class="text-secondary small fw-bold"><i class="fa-solid fa-circle-xmark me-1"></i>Inactivo</span>
                    <?php endif; ?>
                </div>

                <div class="d-grid gap-2 w-100">
                    <a href="/alumno/perfil.php" class="btn btn-trail-outline btn-sm"><i class="fa-solid fa-user-gear me-2"></i>Editar Perfil</a>
                    <?php if ($esta_activo !== 1): ?>
                        <a href="/alumno/reportar_pago.php" class="btn btn-trail btn-sm"><i class="fa-solid fa-receipt me-2"></i>Reportar Pago</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="col-lg-9">
            <?php if ($esta_activo === 0): ?>
                <!-- Alerta de Cuenta Inactiva -->
                <div class="card-premium p-4 border-secondary mb-4 text-center py-5">
                    <i class="fa-solid fa-user-slash text-secondary fa-4x mb-3"></i>
                    <h4 class="text-white fw-bold">Cuenta Inactiva</h4>
                    <p class="text-secondary max-width-600 mx-auto">
                        Tu cuenta se encuentra actualmente <strong>Inactiva</strong>. Por favor, ponte en contacto con tu entrenador para habilitar nuevamente tu acceso al sistema.
                    </p>
                    <?php if (!empty($perfil['ent_telefono'])): ?>
                        <div class="mt-4">
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $perfil['ent_telefono']); ?>" target="_blank" class="btn btn-success"><i class="fa-brands fa-whatsapp me-2"></i>Contactar Entrenador</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($esta_activo === 2): ?>
                <!-- Alerta de Falta de Pago (Bloqueo de Calendario) -->
                <div class="card-premium p-4 border-danger mb-4 text-center py-5" style="background: rgba(231, 111, 81, 0.05); border-left: 5px solid var(--danger-red) !important;">
                    <i class="fa-solid fa-triangle-exclamation text-danger fa-4x mb-3 animate-pulse"></i>
                    <h4 class="text-white fw-bold">Acceso restringido por falta de pago</h4>
                    <p class="text-secondary max-width-600 mx-auto mt-2">
                        Registre su pago para continuar con los beneficios de su membresía.
                    </p>
                    <p class="text-secondary small">
                        Puedes regularizar tu estado realizando el pago online o reportando una transferencia bancaria.
                    </p>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                        <!-- Botón Mercado Pago -->
                        <a href="https://link.mercadopago.com.ar/irinarma" target="_blank" class="btn btn-primary px-4 py-2" style="background-color: #009ee3; border-color: #009ee3;">
                            <i class="fa-solid fa-credit-card me-2"></i>Pagar con Mercado Pago
                        </a>
                        <!-- Botón Reportar Pago / Transferencia -->
                        <a href="/alumno/reportar_pago.php" class="btn btn-trail px-4 py-2">
                            <i class="fa-solid fa-receipt me-2"></i>Informar Transferencia
                        </a>
                        <!-- Botón Contactar Entrenador -->
                        <?php if (!empty($perfil['ent_telefono'])): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $perfil['ent_telefono']); ?>" target="_blank" class="btn btn-outline-success px-4 py-2">
                                <i class="fa-brands fa-whatsapp me-2"></i>Contactar Entrenador
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Dashboard Activo -->
                
                <?php if ($esta_activo === 3): ?>
                    <!-- Recordatorio de Falta de Pago -->
                    <div class="alert alert-warning border-warning shadow-sm mb-4 text-start d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: rgba(243, 156, 18, 0.1); color: var(--text-primary); border-left: 4px solid var(--trail-orange) !important;">
                        <div>
                            <h5 class="alert-heading text-warning fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-2"></i>Cuenta Activa con Falta de Pago</h5>
                            <p class="mb-0 small text-secondary">Tienes permiso para entrenar, pero tu pago mensual está pendiente. Por favor, regulariza tu situación lo antes posible.</p>
                        </div>
                        <div>
                            <a href="/alumno/reportar_pago.php" class="btn btn-warning text-dark btn-sm fw-bold"><i class="fa-solid fa-receipt me-1"></i>Reportar Pago</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Mensajes de feedback -->
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
                        <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i>
                        <?php 
                            if ($_GET['error'] === 'invalid_feedback') echo "Datos de registro inválidos. El tiempo debe ser mayor o igual a 0.";
                            elseif ($_GET['error'] === 'duplicate_date') echo "Ya existe una actividad o comentario registrado para este día.";
                            elseif ($_GET['error'] === 'db') echo "Ocurrió un error al guardar en la base de datos.";
                            else echo "Ocurrió un error inesperado.";
                        ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

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
                    <?php elseif ($_GET['msg'] === 'strava_synced'): ?>
                        <div class="alert alert-success-custom alert-dismissible fade show mb-4" style="border-color: #fc4c02; background: rgba(252, 76, 2, 0.1);" role="alert">
                            <i class="fa-brands fa-strava me-2" style="color: #fc4c02;"></i> ¡Se sincronizaron <?php echo (int)($_GET['count'] ?? 0); ?> actividades de Strava con éxito!
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php
                // Configurar vista (Mes/Semana)
                $vista = isset($_GET['vista']) && $_GET['vista'] === 'mes' ? 'mes' : 'semana';
                $fecha_actual = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
                if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fecha_actual)) {
                    $fecha_actual = date('Y-m-d');
                }
                
                $timestamp_actual = strtotime($fecha_actual);
                
                // Obtener retroalimentación mensual del entrenador si existe
                $mes_actual_db = date('Y-m', $timestamp_actual);
                $stmtFBM = $pdo->prepare("
                    SELECT fm.comentario, fm.fecha_creacion, u.nombre, u.apellido 
                    FROM feedback_mensual fm
                    JOIN usuarios u ON fm.entrenador_id = u.id
                    WHERE fm.alumno_id = ? AND fm.mes = ?
                ");
                $stmtFBM->execute([$alumno_id, $mes_actual_db]);
                $fbm = $stmtFBM->fetch();
                
                $hoy = date('Y-m-d');
                
                $meses_es = ['January'=>'Enero', 'February'=>'Febrero', 'March'=>'Marzo', 'April'=>'Abril', 'May'=>'Mayo', 'June'=>'Junio', 'July'=>'Julio', 'August'=>'Agosto', 'September'=>'Septiembre', 'October'=>'Octubre', 'November'=>'Noviembre', 'December'=>'Diciembre'];
                $meses_cortos_es = ['Jan'=>'Ene', 'Feb'=>'Feb', 'Mar'=>'Mar', 'Apr'=>'Abr', 'May'=>'May', 'Jun'=>'Jun', 'Jul'=>'Jul', 'Aug'=>'Ago', 'Sep'=>'Sep', 'Oct'=>'Oct', 'Nov'=>'Nov', 'Dec'=>'Dic'];
                $dias_es = ['Monday'=>'Lunes', 'Tuesday'=>'Martes', 'Wednesday'=>'Miércoles', 'Thursday'=>'Jueves', 'Friday'=>'Viernes', 'Saturday'=>'Sábado', 'Sunday'=>'Domingo'];

                if ($vista === 'semana') {
                    $lunes_timestamp = date('w', $timestamp_actual) == 1 ? $timestamp_actual : strtotime('last monday', $timestamp_actual);
                    $fecha_inicio = date('Y-m-d', $lunes_timestamp);
                    $fecha_fin = date('Y-m-d', strtotime('+6 days', $lunes_timestamp));
                    $prev_date = date('Y-m-d', strtotime('-7 days', $lunes_timestamp));
                    $next_date = date('Y-m-d', strtotime('+7 days', $lunes_timestamp));
                    
                    $t_ini = strtr(date('d M', $lunes_timestamp), $meses_cortos_es);
                    $t_fin = strtr(date('d M Y', strtotime($fecha_fin)), $meses_cortos_es);
                    $titulo_rango = "Semana: $t_ini - $t_fin";
                } else {
                    $primer_dia_mes = date('Y-m-01', $timestamp_actual);
                    $ultimo_dia_mes = date('Y-m-t', $timestamp_actual);
                    
                    $lunes_inicio = date('w', strtotime($primer_dia_mes)) == 1 ? strtotime($primer_dia_mes) : strtotime('last monday', strtotime($primer_dia_mes));
                    $domingo_fin = date('w', strtotime($ultimo_dia_mes)) == 0 ? strtotime($ultimo_dia_mes) : strtotime('next sunday', strtotime($ultimo_dia_mes));

                    $fecha_inicio = date('Y-m-d', $lunes_inicio);
                    $fecha_fin = date('Y-m-d', $domingo_fin);
                    
                    $prev_date = date('Y-m-d', strtotime('-1 month', strtotime($primer_dia_mes)));
                    $next_date = date('Y-m-d', strtotime('+1 month', strtotime($primer_dia_mes)));
                    
                    $titulo_rango = strtr(date('F Y', $timestamp_actual), $meses_es);
                }

                // Obtener rutinas asignadas en el rango
                $stmtRutinas = $pdo->prepare("SELECT * FROM rutina_asignada WHERE alumno_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha ASC");
                $stmtRutinas->execute([$alumno_id, $fecha_inicio, $fecha_fin]);
                $rutinas_db = $stmtRutinas->fetchAll();
                
                $rutinas_por_fecha = [];
                $completadas = 0;
                $total_asignadas = 0;
                foreach ($rutinas_db as $r) {
                    $rutinas_por_fecha[$r['fecha']] = $r;
                    $total_asignadas++;
                    if ($r['completada']) $completadas++;
                }

                $porcentaje_compliance = $total_asignadas > 0 ? round(($completadas / $total_asignadas) * 100) : 100;
                ?>

                <!-- Controles de Vista -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <a href="?vista=<?php echo $vista; ?>&fecha=<?php echo $prev_date; ?>" class="btn btn-outline-light btn-sm rounded-circle me-3"><i class="fa-solid fa-chevron-left"></i></a>
                        <h4 class="text-white fw-bold mb-0 text-center" style="min-width: 200px;"><?php echo $titulo_rango; ?></h4>
                        <a href="?vista=<?php echo $vista; ?>&fecha=<?php echo $next_date; ?>" class="btn btn-outline-light btn-sm rounded-circle ms-3"><i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($strava_conectado): ?>
                            <a href="/actions/strava_sync.php" class="btn btn-sm text-white fw-bold d-flex align-items-center shadow-sm" style="background-color: #fc4c02; border-color: #fc4c02;" title="Importar actividades recientes de Garmin/Coros">
                                <i class="fa-brands fa-strava me-1"></i>Sincronizar
                            </a>
                        <?php endif; ?>
                        <div class="btn-group">
                            <a href="?vista=semana&fecha=<?php echo $fecha_actual; ?>" class="btn btn-sm <?php echo $vista === 'semana' ? 'btn-trail' : 'btn-outline-secondary text-white'; ?>"><i class="fa-solid fa-list me-1"></i>Semana</a>
                            <a href="?vista=mes&fecha=<?php echo $fecha_actual; ?>" class="btn btn-sm <?php echo $vista === 'mes' ? 'btn-trail' : 'btn-outline-secondary text-white'; ?>"><i class="fa-solid fa-calendar-days me-1"></i>Mes</a>
                        </div>
                    </div>
                </div>

                <?php if ($es_cumple): ?>
                    <!-- Felicitación de Cumpleaños en Mis Rutinas -->
                    <div class="card-premium p-4 mb-4 border border-info" style="background: rgba(13, 202, 240, 0.05); border-left: 5px solid #0dcaf0 !important;">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fa-solid fa-cake-candles fa-3x" style="color: #0dcaf0;"></i>
                            <div>
                                <h4 class="text-white fw-bold mb-1">¡Feliz Cumpleaños, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>! 🎂</h4>
                                <p class="text-secondary small mb-0">Esperamos que tengas un gran día lleno de festejos. ¡Gracias por confiar en nosotros para acompañarte en tu entrenamiento!</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($fbm): ?>
                    <div class="card-premium p-4 mb-4 border border-warning" style="background: rgba(243, 156, 18, 0.05); border-left: 4px solid var(--trail-orange) !important;">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="text-warning"><i class="fa-solid fa-comments fa-lg"></i></span>
                            <span class="text-white fw-bold">Retroalimentación de tu Entrenador (<?php echo htmlspecialchars($titulo_rango); ?>)</span>
                        </div>
                        <div class="px-2 mb-3">
                            <p class="text-white small mb-0" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.95rem;"><?php echo htmlspecialchars($fbm['comentario']); ?></p>
                        </div>
                        <span class="text-secondary ms-2" style="font-size: 0.75rem;"><i class="fa-regular fa-clock me-1"></i>Dejado por <?php echo htmlspecialchars($fbm['nombre'] . ' ' . $fbm['apellido']); ?> el <?php echo date('d/m/Y H:i', strtotime($fbm['fecha_creacion'])); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Compliance Tracker -->
                <div class="card-premium p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-white fw-bold"><i class="fa-solid fa-chart-line text-warning me-2"></i>Cumplimiento del periodo</span>
                        <span class="text-secondary small"><?php echo "$completadas de $total_asignadas"; ?> completados (<?php echo $porcentaje_compliance; ?>%)</span>
                    </div>
                    <div class="progress" style="height: 10px; background-color: var(--bg-primary); border: 1px solid var(--border-color);">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $porcentaje_compliance; ?>%; background-color: var(--trail-orange);" aria-valuenow="<?php echo $porcentaje_compliance; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <?php if ($vista === 'semana'): ?>
                    <!-- CALENDARIO VISTA SEMANAL -->
                    <div class="routine-list">
                        <?php 
                        for ($i = 0; $i < 7; $i++) {
                            $current_timestamp = strtotime("+$i day", $lunes_timestamp);
                            $fecha_db = date('Y-m-d', $current_timestamp);
                            $rutina = $rutinas_por_fecha[$fecha_db] ?? null;
                            $es_hoy = ($fecha_db === $hoy);
                            
                            $nombre_dia = strtr(date('l', $current_timestamp), $dias_es);
                            $numero_dia = date('d', $current_timestamp);
                            
                            $card_class = "routine-day-card p-4";
                            if ($es_hoy) $card_class .= " today border-trail";
                            if ($rutina && $rutina['completada']) $card_class .= " completada border-success";
                            if (!$rutina) $card_class .= " descanso border-secondary opacity-75";
                        ?>
                            <div class="<?php echo $card_class; ?> mb-3 bg-dark rounded shadow-sm position-relative overflow-hidden" style="border: 2px solid; <?php if(!$es_hoy && (!$rutina || !$rutina['completada'])) echo 'border-color: var(--border-color);'; ?>">
                                <!-- Decorador lateral para el día -->
                                <div class="position-absolute top-0 bottom-0 start-0" style="width: 6px; background-color: <?php echo $rutina ? ($rutina['completada'] ? '#2a9d8f' : 'var(--trail-orange)') : 'var(--border-color)'; ?>;"></div>
                                
                                <div class="row align-items-center ms-2">
                                    <!-- FECHA GRANDE -->
                                    <div class="col-md-2 col-4 text-center border-end border-secondary">
                                        <span class="d-block text-secondary text-uppercase fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;"><?php echo $nombre_dia; ?></span>
                                        <h2 class="text-white fw-bold mb-0" style="font-size: 2.5rem;"><?php echo $numero_dia; ?></h2>
                                        <?php if ($es_hoy): ?>
                                            <span class="badge bg-warning text-dark mt-1">Hoy</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- CONTENIDO RUTINA -->
                                    <div class="col-md-7 col-8 py-2 ps-4">
                                        <?php if ($rutina): ?>
                                            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                                <span class="badge badge-tipo badge-<?php echo str_replace(' ', '-', strtolower($rutina['tipo_sesion'])); ?>">
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
                                            <h5 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($rutina['titulo']); ?></h5>
                                            <p class="text-secondary small mb-2"><?php echo nl2br(htmlspecialchars($rutina['descripcion'])); ?></p>
                                            
                                            <!-- Feedback si está completada -->
                                            <?php if ($rutina['completada']): ?>
                                                <div class="mt-3 p-3 bg-secondary bg-opacity-25 rounded border border-success border-opacity-50 text-light small">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <span class="fw-bold text-success"><i class="fa-solid fa-check-double me-1"></i>Entrenamiento Registrado</span>
                                                        <button class="btn btn-sm btn-link text-warning p-0" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $rutina['id']; ?>">
                                                            <i class="fa-solid fa-edit me-1"></i>Editar
                                                        </button>
                                                    </div>
                                                    <div class="d-flex gap-3 mb-2">
                                                        <span><i class="fa-regular fa-clock text-muted me-1"></i><?php echo $rutina['feedback_tiempo_minutos']; ?> min</span>
                                                        <span><i class="fa-solid fa-bolt text-muted me-1"></i>Esfuerzo: <?php echo $rutina['feedback_esfuerzo']; ?>/10</span>
                                                        <?php if ($rutina['distancia_real'] > 0): ?>
                                                            <span><i class="fa-solid fa-route text-muted me-1"></i><?php echo $rutina['distancia_real']; ?> km</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($rutina['feedback_comentario'])): ?>
                                                        <div class="fst-italic text-secondary border-start border-2 border-secondary ps-2 mb-2">"<?php echo htmlspecialchars($rutina['feedback_comentario']); ?>"</div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($rutina['strava_activity_id'])): ?>
                                                        <div class="mt-2 pt-2 border-top border-secondary d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                            <div class="d-flex align-items-center gap-3">
                                                                <span class="text-white fw-semibold small d-inline-flex align-items-center gap-1">
                                                                    <i class="fa-brands fa-strava" style="color: #fc4c02 !important;"></i> Sincronizado con Strava
                                                                </span>
                                                                <?php if (!empty($rutina['ritmo_real'])): ?>
                                                                    <span class="text-secondary small"><i class="fa-solid fa-gauge-high me-1 text-muted"></i>Ritmo: <?php echo htmlspecialchars($rutina['ritmo_real']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <a href="https://www.strava.com/activities/<?php echo urlencode($rutina['strava_activity_id']); ?>" target="_blank" class="btn btn-sm btn-outline-light py-0.5 px-2" style="font-size: 0.75rem; border-color: rgba(255,255,255,0.3);">
                                                                <i class="fa-solid fa-up-right-from-square me-1"></i> Ver Actividad
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <h5 class="text-muted fw-normal mb-1"><i class="fa-solid fa-mug-hot me-2"></i>Descanso Activo / Libre</h5>
                                            <p class="text-muted small mb-0">No hay rutina programada. Aprovecha para estirar, descansar o hacer yoga.</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- ACCIÓN -->
                                    <div class="col-md-3 mt-3 mt-md-0 text-md-end text-center">
                                        <?php if ($rutina): ?>
                                            <button class="btn btn-outline-warning w-100 mb-2 btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#verRutinaModal<?php echo $rutina['id']; ?>">
                                                <i class="fa-solid fa-eye me-1"></i> Ver
                                            </button>
                                            <?php if (!$rutina['completada']): ?>
                                                <button class="btn btn-trail w-100 btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $rutina['id']; ?>">
                                                    <i class="fa-solid fa-clipboard-check me-2"></i>Registrar
                                                </button>
                                            <?php else: ?>
                                                <div class="text-success fw-bold p-1.5 bg-success bg-opacity-10 rounded d-block border border-success small text-center">
                                                    <i class="fa-solid fa-medal me-1"></i>Completado
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-outline-warning btn-sm w-100" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalCustomFeedback" 
                                                    data-fecha="<?php echo $fecha_db; ?>" 
                                                    data-fecha-display="<?php echo $nombre_dia . ' ' . $numero_dia . ' de ' . strtr(date('F', $current_timestamp), $meses_es); ?>">
                                                <i class="fa-solid fa-plus me-1"></i>Agregar comentario
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                
                <?php else: ?>
                    <!-- CALENDARIO VISTA MENSUAL -->
                    <div class="card-premium p-0 overflow-hidden">
                        <div class="table-responsive">
                            <table class="table table-bordered table-dark mb-0 calendar-table" style="table-layout: fixed;">
                                <thead>
                                    <tr class="text-center bg-dark">
                                        <th class="py-2" style="width: 14.28%;">Lunes</th>
                                        <th class="py-2" style="width: 14.28%;">Martes</th>
                                        <th class="py-2" style="width: 14.28%;">Miérc</th>
                                        <th class="py-2" style="width: 14.28%;">Jueves</th>
                                        <th class="py-2" style="width: 14.28%;">Viernes</th>
                                        <th class="py-2" style="width: 14.28%;">Sábado</th>
                                        <th class="py-2" style="width: 14.28%;">Domingo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $current_timestamp = strtotime($fecha_inicio);
                                    while ($current_timestamp <= strtotime($fecha_fin)) {
                                        echo "<tr>";
                                        for ($i = 0; $i < 7; $i++) {
                                            $fecha_str = date('Y-m-d', $current_timestamp);
                                            $num_dia = date('j', $current_timestamp);
                                            $es_mes_actual = (date('m', $current_timestamp) === date('m', $timestamp_actual));
                                            $es_hoy = ($fecha_str === $hoy);
                                            $rutina = $rutinas_por_fecha[$fecha_str] ?? null;

                                            $cell_class = "calendar-cell p-2 position-relative align-top";
                                            if (!$es_mes_actual) $cell_class .= " opacity-50 bg-dark";
                                            if ($es_hoy) $cell_class .= " border-warning";

                                            echo "<td class='$cell_class' style='height: 120px;'>";
                                            
                                            // Decorador de día
                                            $day_badge = $es_hoy ? "<span class='badge bg-warning text-dark px-2 rounded-pill shadow-sm'>$num_dia</span>" : "<span class='fw-bold text-secondary'>$num_dia</span>";
                                            echo "<div class='text-end mb-1'>$day_badge</div>";

                                            if ($rutina) {
                                                $tipo = strtolower($rutina['tipo_sesion']);
                                                $bg_color = "bg-secondary"; // default
                                                if ($tipo === 'cuestas') $bg_color = "bg-danger";
                                                if ($tipo === 'pasadas') $bg_color = "bg-primary";
                                                if ($tipo === 'fondo') $bg_color = "bg-info text-dark";
                                                if ($tipo === 'cambios de ritmo') $bg_color = "bg-warning text-dark";
                                                if ($tipo === 'bici') $bg_color = "bg-success";
                                                
                                                if ($rutina['completada']) {
                                                    $bg_color .= " opacity-50"; // Dim si ya la hizo
                                                }

                                                echo "<div class='p-2 rounded mb-1 $bg_color text-white' style='font-size: 0.85rem; cursor:pointer;' data-bs-toggle='modal' data-bs-target='#feedbackModal" . $rutina['id'] . "'>";
                                                echo "<div class='fw-bold text-truncate'>" . htmlspecialchars($rutina['titulo']) . "</div>";
                                                if ($rutina['completada']) {
                                                    echo "<div class='mt-1 text-light'><i class='fa-solid fa-check-circle me-1'></i>Hecho</div>";
                                                }
                                                echo "</div>";
                                                
                                                // Modal Mensual removido (los modales se renderizan al final del archivo)
                                            } else {
                                                // Mostrar un pequeño botón "+" para registrar observaciones/comentario en día libre
                                                $fecha_display_mes = $num_dia . ' de ' . strtr(date('F', $current_timestamp), $meses_es);
                                                echo "<div class='text-center mt-3'>";
                                                echo "  <button class='btn btn-sm btn-outline-secondary p-0 px-2 border-0 text-secondary' style='font-size: 0.85rem; background: rgba(255,255,255,0.02);' ";
                                                echo "          data-bs-toggle='modal' data-bs-target='#modalCustomFeedback' ";
                                                echo "          data-fecha='$fecha_str' data-fecha-display='$fecha_display_mes'>";
                                                echo "      <i class='fa-solid fa-plus me-1'></i>Comentar";
                                                echo "  </button>";
                                                echo "</div>";
                                            }
                                            echo "</td>";
                                            $current_timestamp = strtotime("+1 day", $current_timestamp);
                                        }
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

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
                    <div class="p-4 rounded border border-secondary text-center text-secondary small mb-5">
                        <i class="fa-solid fa-box-open fa-2x mb-2 text-muted"></i>
                        <p class="mb-0">Tu entrenador no ha compartido materiales personalizados todavía.</p>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL PARA REGISTRO DE DÍA LIBRE / ACTIVIDAD EXTRA -->
<div class="modal fade" id="modalCustomFeedback" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fa-solid fa-comment-dots text-warning me-2"></i>Agregar comentario
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="/actions/alumno_feedback.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="rutina_id" value="0">
                    <input type="hidden" name="fecha" id="custom_feedback_fecha">
                    
                    <div class="bg-dark p-3 rounded mb-3 border border-secondary">
                        <span class="d-block text-secondary small fw-bold text-uppercase mb-1">Fecha Seleccionada</span>
                        <h6 class="text-white mb-0" id="custom_feedback_fecha_display">-</h6>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-custom">Título de la Actividad (Opcional)</label>
                        <input type="text" name="titulo" class="form-control form-control-custom" placeholder="Ej: Descanso activo, Entrenamiento de ritmo, etc.">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Tipo de Sesión *</label>
                            <select name="tipo_sesion" class="form-select form-control-custom" required>
                                <option value="Bici">Bici</option>
                                <option value="Cambios de Ritmo">Cambios de Ritmo</option>
                                <option value="Cuestas">Cuestas</option>
                                <option value="Fondo" selected>Fondo</option>
                                <option value="Pasadas">Pasadas</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Tiempo Total (Minutos)</label>
                            <input type="number" step="1" name="tiempo" class="form-control form-control-custom" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Distancia Real (Km)</label>
                            <input type="number" step="0.01" name="distancia_real" class="form-control form-control-custom" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Desnivel Positivo (m)</label>
                            <input type="number" step="1" name="desnivel_real" class="form-control form-control-custom" placeholder="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-custom d-flex justify-content-between">
                            <span>Esfuerzo Percibido (Sensaciones) *</span>
                            <span id="customEsfuerzoVal" class="badge bg-warning text-dark">5</span>
                        </label>
                        <input type="range" class="form-range" name="esfuerzo" min="1" max="10" value="5" oninput="document.getElementById('customEsfuerzoVal').innerText = this.value">
                        <div class="d-flex justify-content-between text-muted small mt-1">
                            <span>1 (Muy fácil)</span>
                            <span>10 (Máximo)</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-custom">Observaciones / Comentarios</label>
                        <textarea name="comentario" class="form-control form-control-custom" rows="3" placeholder="Ingresa tus sensaciones del día, molestias o cualquier observation para tu entrenador..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modalCustomFeedback = document.getElementById('modalCustomFeedback');
    if (modalCustomFeedback) {
        modalCustomFeedback.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var fecha = button.getAttribute('data-fecha');
            var fechaDisplay = button.getAttribute('data-fecha-display');
            
            var inputFecha = modalCustomFeedback.querySelector('#custom_feedback_fecha');
            var displayFecha = modalCustomFeedback.querySelector('#custom_feedback_fecha_display');
            
            inputFecha.value = fecha;
            displayFecha.textContent = fechaDisplay;
        });
    }
});
</script>

<!-- ========================================== -->
<!-- SECCIÓN DE VENTANAS MODALES AL FINAL DEL BODY -->
<!-- ========================================== -->
<?php foreach ($rutinas_por_fecha as $fecha_str => $rutina): 
    $timestamp = strtotime($fecha_str);
    $nombre_dia = strtr(date('l', $timestamp), $dias_es);
    $numero_dia = date('d', $timestamp);
    $fecha_display_mes = $numero_dia . ' de ' . strtr(date('F', $timestamp), $meses_es);
?>
    <!-- 1. Cajón/Modal optimizado para celulares: Ver Rutina -->
    <div class="modal fade" id="verRutinaModal<?php echo $rutina['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
                <div class="modal-header border-bottom border-dark">
                    <h5 class="modal-title text-white fw-bold">
                        <i class="fa-solid fa-person-running text-warning me-2"></i>Detalle de Entrenamiento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start">
                    <div class="bg-dark p-3 rounded mb-3 border border-secondary">
                        <span class="d-block text-secondary small fw-bold text-uppercase mb-1">Día: <?php echo $nombre_dia . ' ' . $fecha_display_mes; ?></span>
                        <h4 class="text-white fw-bold mb-0"><?php echo htmlspecialchars($rutina['titulo']); ?></h4>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-dark p-2.5 rounded border border-secondary text-center">
                                <span class="d-block text-secondary small">Tipo</span>
                                <span class="badge badge-tipo badge-<?php echo str_replace(' ', '-', strtolower($rutina['tipo_sesion'])); ?> mt-1"><?php echo htmlspecialchars($rutina['tipo_sesion']); ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-dark p-2.5 rounded border border-secondary text-center h-100 d-flex flex-column justify-content-center">
                                <span class="d-block text-secondary small">Terreno</span>
                                <span class="text-white fw-bold small mt-1"><?php echo htmlspecialchars($rutina['terreno']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-dark p-2.5 rounded border border-secondary text-center">
                                <span class="d-block text-secondary small">Distancia</span>
                                <span class="text-white fw-bold mt-1 d-block"><?php echo $rutina['distancia_km'] > 0 ? $rutina['distancia_km'] . ' km' : '-'; ?></span>
                           </div>
                       </div>
                       <div class="col-6">
                           <div class="bg-dark p-2.5 rounded border border-secondary text-center">
                               <span class="d-block text-secondary small">Ritmo Sugerido</span>
                               <span class="text-white fw-bold mt-1 d-block"><?php echo !empty($rutina['ritmo_sugerido']) ? htmlspecialchars($rutina['ritmo_sugerido']) : '-'; ?></span>
                           </div>
                       </div>
                   </div>
                   
                   <div class="bg-dark p-3 rounded border border-secondary">
                       <span class="d-block text-secondary small fw-bold text-uppercase mb-2">Instrucciones:</span>
                       <div class="text-white small" style="white-space: pre-wrap; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($rutina['descripcion'])); ?></div>
                   </div>
               </div>
               <div class="modal-footer border-top border-dark">
                   <button type="button" class="btn btn-outline-light w-100" data-bs-dismiss="modal">Cerrar</button>
               </div>
           </div>
       </div>
    </div>

    <!-- 2. Modal Feedback (Edición / Creación) -->
    <div class="modal fade" id="feedbackModal<?php echo $rutina['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-custom">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white fw-bold">
                        <i class="fa-solid fa-stopwatch text-warning me-2"></i><?php echo $rutina['completada'] ? 'Editar Registro' : 'Registrar Entrenamiento'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="/actions/alumno_feedback.php" method="POST">
                    <div class="modal-body text-start">
                        <input type="hidden" name="rutina_id" value="<?php echo $rutina['id']; ?>">
                        
                        <div class="bg-dark p-3 rounded mb-3 border border-secondary">
                            <span class="d-block text-secondary small fw-bold text-uppercase mb-1">Día: <?php echo $nombre_dia . ' ' . $fecha_display_mes; ?></span>
                            <h6 class="text-white mb-0"><?php echo htmlspecialchars($rutina['titulo']); ?></h6>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label form-label-custom">Tiempo Total (Minutos)</label>
                                <input type="number" step="1" name="tiempo" class="form-control form-control-custom" value="<?php echo $rutina['feedback_tiempo_minutos'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-custom">Distancia Real (Km)</label>
                                <input type="number" step="0.01" name="distancia_real" class="form-control form-control-custom" value="<?php echo $rutina['distancia_real'] ?? ''; ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label form-label-custom">Desnivel Positivo (m)</label>
                                <input type="number" step="1" name="desnivel_real" class="form-control form-control-custom" value="<?php echo $rutina['desnivel_real'] ?? ''; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label form-label-custom d-flex justify-content-between">
                                <span>Esfuerzo Percibido (Sensaciones) *</span>
                                <span id="esfuerzoVal<?php echo $rutina['id']; ?>" class="badge bg-warning text-dark"><?php echo $rutina['feedback_esfuerzo'] ?? 5; ?></span>
                            </label>
                            <input type="range" class="form-range" name="esfuerzo" min="1" max="10" value="<?php echo $rutina['feedback_esfuerzo'] ?? 5; ?>" oninput="document.getElementById('esfuerzoVal<?php echo $rutina['id']; ?>').innerText = this.value">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>1 (Muy fácil)</span>
                                <span>10 (Máximo)</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label form-label-custom">Comentarios del entrenamiento</label>
                            <textarea name="comentario" class="form-control form-control-custom" rows="3" placeholder="¿Cómo te sentiste? ¿Hubo molestias físicas?"><?php echo htmlspecialchars($rutina['feedback_comentario'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-trail">Guardar Registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($es_cumple): ?>
<!-- Modal de Cumpleaños -->
<div class="modal fade" id="cumpleModal" tabindex="-1" aria-labelledby="cumpleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-info" style="border: 2px solid #0dcaf0;">
            <div class="modal-header border-0 pb-0 justify-content-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fa-solid fa-cake-candles fa-4x text-info mt-3"></i>
            </div>
            <div class="modal-body text-center pt-2 pb-4 px-4">
                <h3 class="text-white fw-bold mb-3" id="cumpleModalLabel">¡Feliz Cumpleaños, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>!</h3>
                <p class="text-secondary mb-0">Queremos desearte un excelente día en tu cumpleaños.<br>¡Que tengas un año lleno de kilómetros, salud y grandes metas cumplidas!<br><br><strong class="text-info">El equipo de IB Trail Running</strong></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-info px-4 fw-bold text-dark" data-bs-dismiss="modal">¡Gracias!</button>
            </div>
        </div>
    </div>
</div>

<!-- Script para el modal de cumpleaños y confeti -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var cumpleModal = new bootstrap.Modal(document.getElementById('cumpleModal'), {
            keyboard: true
        });
        cumpleModal.show();
        
        var duration = 3000;
        var end = Date.now() + duration;

        (function frame() {
            confetti({
                particleCount: 5,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#388e7a', '#d97d54', '#0dcaf0']
            });
            confetti({
                particleCount: 5,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#388e7a', '#d97d54', '#0dcaf0']
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        }());
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
