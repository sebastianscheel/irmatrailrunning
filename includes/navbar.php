<?php
$current_user_name = isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : '';
$current_user_rol = isset($_SESSION['user_rol']) ? $_SESSION['user_rol'] : '';

$notif_count = 0;
$notificaciones_list = [];

if (!empty($current_user_rol)) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
    }
    try {
        // Consultar notificaciones no leídas
        $stmtNotifCount = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leido = 0 AND eliminada = 0");
        $stmtNotifCount->execute([$_SESSION['user_id']]);
        $notif_count = $stmtNotifCount->fetchColumn();

        // Obtener últimas 5 notificaciones
        $stmtNotifList = $pdo->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? AND eliminada = 0 ORDER BY fecha DESC LIMIT 5");
        $stmtNotifList->execute([$_SESSION['user_id']]);
        $notificaciones_list = $stmtNotifList->fetchAll();
    } catch (Exception $e) {
        error_log("Error al cargar notificaciones en navbar: " . $e->getMessage());
    }
}
?><div class="sticky-top navbar-header-wrapper">
    <!-- Barra Superior: Logo y Perfil -->
    <div class="navbar-top-bar d-flex align-items-center justify-content-between px-3 py-2">
        <a class="navbar-brand d-flex align-items-center text-decoration-none" href="/index.php">
            <img src="/assets/img/logo.jpeg" alt="Logo" class="rounded-circle me-2" style="width: 38px; height: 38px; object-fit: cover; border: 2px solid var(--trail-orange);">
            <span class="text-white fw-bold">IRMA</span><span class="ms-1" style="font-weight: 300; color: #ffffff;">TRAIL RUNNING</span>
        </a>

        <div class="d-flex align-items-center">
            <?php if (empty($current_user_rol)): ?>
                <a href="/login.php" class="btn btn-trail rounded-pill d-flex align-items-center px-4 py-2" title="Acceso Panel"><i class="fa-solid fa-user-lock me-2"></i> Acceso</a>
            <?php else: ?>
                <!-- Notificaciones -->
                <div class="dropdown me-3" id="notifDropdownContainer">
                    <button class="btn btn-link text-white position-relative p-0" type="button" id="notifDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none;">
                        <i class="fa-solid fa-bell fa-lg"></i>
                        <?php if ($notif_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem; padding: 0.25em 0.5em;">
                                <?php echo $notif_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-0 border border-secondary" aria-labelledby="notifDropdownBtn" style="width: 300px; font-size: 0.85rem; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                        <li class="p-2 border-bottom border-secondary d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Notificaciones</span>
                            <div class="d-flex gap-2">
                                <?php if ($notif_count > 0): ?>
                                    <button class="btn btn-link text-warning p-0 small text-decoration-none" onclick="marcarTodasNotif(event)" style="font-size: 0.75rem;">Marcar leídas</button>
                                <?php endif; ?>
                                <button class="btn btn-link text-danger p-0 small text-decoration-none btn-clear-all-notif" onclick="eliminarTodasNotif(event)" style="font-size: 0.75rem; <?php echo empty($notificaciones_list) ? 'display: none;' : ''; ?>">Borrar todas</button>
                            </div>
                        </li>
                        <div style="max-height: 250px; overflow-y: auto;">
                            <?php if (empty($notificaciones_list)): ?>
                                <li class="p-3 text-center text-muted small">No tienes notificaciones</li>
                            <?php else: ?>
                                <?php foreach ($notificaciones_list as $notif): ?>
                                    <li class="border-bottom border-secondary border-opacity-50">
                                        <a class="dropdown-item p-2 <?php echo $notif['leido'] == 0 ? 'bg-secondary bg-opacity-10 fw-semibold' : ''; ?> d-flex flex-column" href="<?php echo $notif['enlace'] ?? '#'; ?>" onclick="marcarLeida(<?php echo $notif['id']; ?>)">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-white text-truncate" style="max-width: 180px;"><?php echo htmlspecialchars($notif['titulo']); ?></span>
                                                <span class="text-muted small" style="font-size: 0.7rem;"><?php echo date('d/m H:i', strtotime($notif['fecha'])); ?></span>
                                            </div>
                                            <span class="text-secondary small text-wrap" style="font-size: 0.75rem;"><?php echo htmlspecialchars($notif['mensaje']); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </ul>
                </div>

                <?php 
                    $dashboard_url = ($current_user_rol === 'alumno') ? '/alumno/dashboard.php' : '/admin/dashboard.php';
                    $full_name = htmlspecialchars($_SESSION['user_nombre'] . " " . $_SESSION['user_apellido']);
                    $rol_label = '';
                    switch ($current_user_rol) {
                        case 'admin': $rol_label = 'Administrador'; break;
                        case 'entrenador_total':
                        case 'entrenador_intermedio':
                        case 'entrenador_limitado':
                            $rol_label = 'Entrenador'; break;
                        case 'alumno': $rol_label = 'Alumno'; break;
                    }
                ?>
                <span class="me-3 d-none d-sm-inline fw-semibold" style="font-size: 0.85rem;">
                    <i class="fa-solid fa-user-circle me-1" style="color: #388e7a;"></i>
                    <span style="color: #388e7a;"><?php echo $full_name; ?></span>
                    <span class="ms-1 small" style="color: #d16b5a; font-size: 0.75rem;">(<?php echo $rol_label; ?>)</span>
                </span>
                <a href="<?php echo $dashboard_url; ?>" class="btn btn-trail-outline rounded-circle d-flex align-items-center justify-content-center p-0 me-2" style="width: 36px; height: 36px;" title="Ir a mi panel"><i class="fa-solid fa-gauge-high"></i></a>
                <a href="/logout.php" class="btn btn-outline-danger rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 36px; height: 36px;" title="Salir"><i class="fa-solid fa-sign-out-alt"></i></a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Barra Inferior: Menú de Enlaces -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-1">
        <div class="container-fluid">
            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php if (empty($current_user_rol)): ?>
                        <!-- Menu publico -->
                        <li class="nav-item">
                            <a class="nav-link" href="/index.php#sobre-nosotros">Sobre Nosotros</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/index.php#planes">Planes</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/index.php#contacto">Contacto</a>
                        </li>
                    <?php elseif (in_array($current_user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado'])): ?>
                        <!-- Menu Administrador/Entrenador -->
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/dashboard.php">Resumen</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/alumnos.php">Alumnos</a>
                        </li>
                        <?php if (in_array($current_user_rol, ['admin', 'entrenador_total'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/entrenadores.php">Entrenadores</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/carreras.php">Carreras</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/metricas.php">Métricas</a>
                        </li>
                        <?php 
                        $planif_active = (
                            strpos($_SERVER['SCRIPT_NAME'], 'plantillas.php') !== false || 
                            strpos($_SERVER['SCRIPT_NAME'], 'entrenamientos.php') !== false || 
                            strpos($_SERVER['SCRIPT_NAME'], 'asistente.php') !== false
                        );
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo $planif_active ? 'active' : ''; ?>" href="#" id="navbarPlanifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Planificaciones
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark border-secondary" aria-labelledby="navbarPlanifDropdown">
                                <li><a class="dropdown-item <?php echo strpos($_SERVER['SCRIPT_NAME'], 'plantillas.php') !== false ? 'active' : ''; ?>" href="/admin/plantillas.php">Plantillas</a></li>
                                <li><a class="dropdown-item <?php echo strpos($_SERVER['SCRIPT_NAME'], 'entrenamientos.php') !== false ? 'active' : ''; ?>" href="/admin/entrenamientos.php">Sesiones</a></li>
                                <?php if (in_array($current_user_rol, ['admin', 'entrenador_total'])): ?>
                                    <li><hr class="dropdown-divider border-secondary"></li>
                                    <li><a class="dropdown-item text-warning fw-bold <?php echo strpos($_SERVER['SCRIPT_NAME'], 'asistente.php') !== false ? 'active' : ''; ?>" href="/admin/asistente.php"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Con Asistente</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/planificador.php">Planificador</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/pagos.php">Pagos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/certificados.php">Certificados</a>
                        </li>
                        <?php if (in_array($current_user_rol, ['admin', 'entrenador_total'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/historial.php">Historial</a>
                        </li>
                        <?php endif; ?>
                    <?php elseif ($current_user_rol === 'alumno'): ?>
                        <!-- Menu Alumno -->
                        <li class="nav-item">
                            <a class="nav-link" href="/alumno/dashboard.php">Mis Rutinas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/alumno/metricas.php">Mis Métricas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/alumno/calendario.php">Calendario de Carreras</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/alumno/reportar_pago.php">Reportar Pago</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/alumno/perfil.php">Mi Perfil</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</div>
<script>
function marcarLeida(id) {
    fetch('/actions/notificaciones_action.php?action=marcar_leida&id=' + id, {
        method: 'POST'
    });
}

function marcarTodasNotif(event) {
    event.preventDefault();
    event.stopPropagation();
    fetch('/actions/notificaciones_action.php?action=marcar_todas', {
        method: 'POST'
    }).then(res => res.json()).then(data => {
        if (data.success) {
            const badge = document.querySelector('#notifDropdownBtn .badge');
            if (badge) badge.remove();
            
            document.querySelectorAll('#notifDropdownContainer .dropdown-item').forEach(item => {
                item.classList.remove('bg-secondary', 'bg-opacity-10', 'fw-semibold');
            });
            const markAllBtn = event.target;
            if (markAllBtn) markAllBtn.remove();
        }
    });
}

function eliminarTodasNotif(event) {
    event.preventDefault();
    event.stopPropagation();
    if (!confirm('¿Estás seguro de que deseas borrar todas las notificaciones?')) {
        return;
    }
    fetch('/actions/notificaciones_action.php?action=eliminar_todas', {
        method: 'POST'
    }).then(res => res.json()).then(data => {
        if (data.success) {
            const badge = document.querySelector('#notifDropdownBtn .badge');
            if (badge) badge.remove();
            
            const listDiv = document.querySelector('#notifDropdownContainer div[style*="overflow-y"]');
            if (listDiv) {
                listDiv.innerHTML = '<li class="p-3 text-center text-muted small">No tienes notificaciones</li>';
            }
            
            const markAllBtn = document.querySelector('#notifDropdownContainer button[onclick*="marcarTodasNotif"]');
            if (markAllBtn) markAllBtn.remove();
            
            const clearAllBtn = document.querySelector('.btn-clear-all-notif');
            if (clearAllBtn) clearAllBtn.remove();
        }
    });
}
</script>
