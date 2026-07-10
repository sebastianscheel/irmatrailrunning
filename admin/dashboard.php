<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador o entrenador
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$page_title = "Admin Dashboard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

try {
    // 1. Contador de Alumnos Totales
    $stmtAlumnos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'alumno'");
    $cant_alumnos = $stmtAlumnos->fetchColumn();

    // 2. Contador de Alumnos Activos
    $stmtActivos = $pdo->query("SELECT COUNT(*) FROM alumno_perfil WHERE activo IN (1, 3)");
    $cant_activos = $stmtActivos->fetchColumn();

    // 3. Pagos Pendientes
    $stmtPagosPend = $pdo->query("SELECT COUNT(*) FROM pago_registro WHERE estado = 'pendiente'");
    $cant_pagos_pend = $stmtPagosPend->fetchColumn();

    // 4. Certificados Pendientes
    $stmtCertPend = $pdo->query("SELECT COUNT(*) FROM alumno_perfil WHERE certificado_medico_estado = 'pendiente' AND certificado_medico_url IS NOT NULL");
    $cant_certs_pend = $stmtCertPend->fetchColumn();

    // 5. Últimos Pagos Reportados
    $stmtUltimosPagos = $pdo->query("
        SELECT pr.*, u.nombre, u.apellido 
        FROM pago_registro pr
        JOIN alumno_perfil ap ON pr.alumno_id = ap.id
        JOIN usuarios u ON ap.usuario_id = u.id
        ORDER BY pr.fecha_reporte DESC 
        LIMIT 5
    ");
    $ultimos_pagos = $stmtUltimosPagos->fetchAll();

    // 6. Últimos Certificados Subidos
    $stmtUltimosCerts = $pdo->query("
        SELECT ap.*, u.nombre, u.apellido 
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ap.certificado_medico_url IS NOT NULL
        ORDER BY ap.id DESC 
        LIMIT 5
    ");
    $ultimos_certs = $stmtUltimosCerts->fetchAll();

    // --- 7. PROCESAR NOTIFICACIONES DE CUMPLEAÑOS ---
    $stmtCumplesHoy = $pdo->query("
        SELECT ap.id AS alumno_id, ap.entrenador_id, u.nombre, u.apellido, ap.usuario_id AS alumno_usuario_id
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ap.activo IN (1, 3) 
          AND DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
    ");
    $cumples_hoy = $stmtCumplesHoy->fetchAll();

    foreach ($cumples_hoy as $ch) {
        $student_name = $ch['nombre'] . ' ' . $ch['apellido'];
        
        // A. Notificar al alumno
        $stmtCheckStudent = $pdo->prepare("
            SELECT COUNT(*) FROM notificaciones 
            WHERE usuario_id = ? AND titulo = '¡Feliz Cumpleaños! 🎂' AND YEAR(fecha) = YEAR(CURDATE())
        ");
        $stmtCheckStudent->execute([$ch['alumno_usuario_id']]);
        if ($stmtCheckStudent->fetchColumn() == 0) {
            require_once __DIR__ . '/../includes/audit_helper.php';
            crearNotificacion(
                $pdo, 
                $ch['alumno_usuario_id'], 
                "¡Feliz Cumpleaños! 🎂", 
                "Todo el equipo de Irma Trail Running te desea un excelente día y un gran año de entrenamientos.", 
                "/alumno/dashboard.php"
            );
        }

        // B. Notificar al entrenador asignado
        if (!empty($ch['entrenador_id'])) {
            $stmtCheckCoach = $pdo->prepare("
                SELECT COUNT(*) FROM notificaciones 
                WHERE usuario_id = ? AND titulo = 'Cumpleaños de Alumno' AND mensaje LIKE ? AND YEAR(fecha) = YEAR(CURDATE())
            ");
            $stmtCheckCoach->execute([$ch['entrenador_id'], "%" . $student_name . "%"]);
            if ($stmtCheckCoach->fetchColumn() == 0) {
                require_once __DIR__ . '/../includes/audit_helper.php';
                crearNotificacion(
                    $pdo, 
                    $ch['entrenador_id'], 
                    "Cumpleaños de Alumno", 
                    "¡Hoy cumple años " . $student_name . "! 🎂 Enviale un saludo.", 
                    "/admin/planificador.php?alumno_id=" . $ch['alumno_id']
                );
            }
        }
    }

    // --- 8. NOVEDADES FEED (CUMPLEAÑOS Y COMENTARIOS) ---
    $novedades = [];
    
    // Cumpleaños en rango de 7 días
    $sqlCumples = "
        SELECT 
            ap.id AS alumno_id, 
            u.nombre, 
            u.apellido, 
            ap.fecha_nacimiento,
            ap.entrenador_id,
            (CASE 
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') THEN 0
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '%m-%d') THEN 1
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '%m-%d') THEN 2
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '%m-%d') THEN 3
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 4 DAY), '%m-%d') THEN 4
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '%m-%d') THEN 5
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 6 DAY), '%m-%d') THEN 6
                WHEN DATE_FORMAT(ap.fecha_nacimiento, '%m-%d') = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d') THEN 7
                ELSE -1
             END) AS dias_para_cumple
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ap.activo IN (1, 3)
    ";
    
    if ($_SESSION['user_rol'] === 'entrenador_limitado') {
        $sqlCumples .= " AND ap.entrenador_id = ?";
        $stmtC = $pdo->prepare($sqlCumples);
        $stmtC->execute([$_SESSION['user_id']]);
    } else {
        $stmtC = $pdo->prepare($sqlCumples);
        $stmtC->execute();
    }
    
    $resCumples = $stmtC->fetchAll();
    foreach ($resCumples as $c) {
        if ($c['dias_para_cumple'] >= 0) {
            $student_name = $c['nombre'] . ' ' . $c['apellido'];
            $dias = $c['dias_para_cumple'];
            
            if ($dias == 0) {
                $mensaje = "¡Hoy es el cumpleaños de <strong>" . htmlspecialchars($student_name) . "</strong>! 🎂 Enviale un saludo.";
                $fecha_sort = date('Y-m-d H:i:s', strtotime("today + 23 hours"));
                $fecha_display = "Hoy";
                $extra_class = "cumple-hoy";
            } else {
                $mensaje = "El cumpleaños de <strong>" . htmlspecialchars($student_name) . "</strong> es en $dias " . ($dias == 1 ? "día" : "días") . " (" . date('d/m', strtotime("+$dias days")) . ").";
                $fecha_sort = date('Y-m-d H:i:s', strtotime("+$dias days"));
                $fecha_display = date('d/m', strtotime("+$dias days"));
                $extra_class = "cumple-proximo";
            }
            
            $novedades[] = [
                'tipo' => 'cumple',
                'fecha_sort' => $fecha_sort,
                'fecha_display' => $fecha_display,
                'titulo' => "Cumpleaños",
                'mensaje' => $mensaje,
                'icono' => "fa-solid fa-cake-candles text-warning",
                'enlace' => "/admin/planificador.php?alumno_id=" . $c['alumno_id'],
                'extra_class' => $extra_class
            ];
        }
    }

    // Comentarios en los últimos 7 días
    $sqlComments = "
        SELECT 
            ra.id AS rutina_id,
            ra.fecha,
            ra.tipo_sesion,
            ra.feedback_comentario,
            ra.fecha_registro_feedback,
            u.nombre AS alumno_nombre,
            u.apellido AS alumno_apellido,
            ap.id AS alumno_id
        FROM rutina_asignada ra
        JOIN alumno_perfil ap ON ra.alumno_id = ap.id
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ra.feedback_comentario IS NOT NULL 
          AND ra.feedback_comentario != '' 
          AND ra.fecha_registro_feedback >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";
    
    if ($_SESSION['user_rol'] === 'entrenador_limitado') {
        $sqlComments .= " AND ap.entrenador_id = ?";
        $stmtCom = $pdo->prepare($sqlComments);
        $stmtCom->execute([$_SESSION['user_id']]);
    } else {
        $stmtCom = $pdo->prepare($sqlComments);
        $stmtCom->execute();
    }
    
    $resComments = $stmtCom->fetchAll();
    foreach ($resComments as $com) {
        $student_name = $com['alumno_nombre'] . ' ' . $com['alumno_apellido'];
        $fecha_feedback = strtotime($com['fecha_registro_feedback']);
        
        $diff = time() - $fecha_feedback;
        if ($diff < 60) {
            $fecha_display = "Hace instantes";
        } elseif ($diff < 3600) {
            $mins = round($diff / 60);
            $fecha_display = "Hace $mins " . ($mins == 1 ? "minuto" : "minutos");
        } elseif ($diff < 86400) {
            $horas = round($diff / 3600);
            $fecha_display = "Hace $horas " . ($horas == 1 ? "hora" : "horas");
        } else {
            $dias = round($diff / 86400);
            $fecha_display = "Hace $dias " . ($dias == 1 ? "día" : "días");
        }
        
        $mensaje_corto = $com['feedback_comentario'];
        if (mb_strlen($mensaje_corto) > 100) {
            $mensaje_corto = mb_substr($mensaje_corto, 0, 97) . "...";
        }
        
        $tipo_sesion_badge = $com['tipo_sesion'];
        $fecha_entreno = date('d/m', strtotime($com['fecha']));
        $mensaje = "<strong>" . htmlspecialchars($student_name) . "</strong> dejó un comentario en la sesión de <strong>" . htmlspecialchars($tipo_sesion_badge) . "</strong> del $fecha_entreno: <span class='text-muted font-italic'>\"" . htmlspecialchars($mensaje_corto) . "\"</span>";
        
        $novedades[] = [
            'tipo' => 'comentario',
            'fecha_sort' => $com['fecha_registro_feedback'],
            'fecha_display' => $fecha_display,
            'titulo' => "Nuevo Comentario",
            'mensaje' => $mensaje,
            'icono' => "fa-solid fa-comment-dots text-info",
            'enlace' => "/admin/planificador.php?alumno_id=" . $com['alumno_id'] . "&fecha=" . $com['fecha'],
            'extra_class' => "novedad-comentario"
        ];
    }

    // Ordenar por fecha_sort DESC
    usort($novedades, function($a, $b) {
        return strcmp($b['fecha_sort'], $a['fecha_sort']);
    });
    
    // Cortar a los últimos 8
    $novedades = array_slice($novedades, 0, 8);

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}
?>

<div class="container dashboard-container">
    <div class="row mb-4">
        <div class="col">
            <h2 class="text-white fw-bold"><i class="fa-solid fa-gauge-high text-warning me-2"></i>Panel del Entrenador</h2>
            <p class="text-secondary mb-0">Vista general de alumnos, pagos y documentación del team.</p>
        </div>
    </div>

    <!-- Tarjetas de Métricas -->
    <div class="row g-3 mb-5">
        <!-- Tarjeta Alumnos Totales -->
        <div class="col-md-3">
            <div class="card-premium p-3 h-100 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Alumnos Totales</h6>
                    <h2 class="text-white fw-extrabold mb-0"><?php echo $cant_alumnos; ?></h2>
                </div>
                <div class="bg-dark text-warning p-3 rounded-circle border border-secondary">
                    <i class="fa-solid fa-users fa-xl"></i>
                </div>
            </div>
        </div>

        <!-- Tarjeta Alumnos Activos -->
        <div class="col-md-3">
            <div class="card-premium p-3 h-100 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Alumnos Activos</h6>
                    <h2 class="text-white fw-extrabold mb-0"><?php echo $cant_activos; ?> <small class="text-muted fs-6">/ <?php echo $cant_alumnos; ?></small></h2>
                </div>
                <div class="bg-dark text-success p-3 rounded-circle border border-secondary">
                    <i class="fa-solid fa-user-check fa-xl"></i>
                </div>
            </div>
        </div>

        <!-- Tarjeta Pagos Pendientes -->
        <div class="col-md-3">
            <a href="/admin/pagos.php" class="text-decoration-none h-100 d-block">
                <div class="card-premium p-3 h-100 d-flex align-items-center justify-content-between <?php echo $cant_pagos_pend > 0 ? 'border-warning' : ''; ?>" style="transition: all 0.2s;">
                    <div>
                        <h6 class="text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Pagos por Revisar</h6>
                        <h2 class="text-white fw-extrabold mb-0"><?php echo $cant_pagos_pend; ?></h2>
                    </div>
                    <div class="bg-dark text-warning p-3 rounded-circle border border-secondary">
                        <i class="fa-solid fa-file-invoice-dollar fa-xl"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Tarjeta Certificados Pendientes -->
        <div class="col-md-3">
            <a href="/admin/certificados.php" class="text-decoration-none h-100 d-block">
                <div class="card-premium p-3 h-100 d-flex align-items-center justify-content-between <?php echo $cant_certs_pend > 0 ? 'border-warning' : ''; ?>" style="transition: all 0.2s;">
                    <div>
                        <h6 class="text-secondary text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Aptos por Revisar</h6>
                        <h2 class="text-white fw-extrabold mb-0"><?php echo $cant_certs_pend; ?></h2>
                    </div>
                    <div class="bg-dark text-info p-3 rounded-circle border border-secondary">
                        <i class="fa-solid fa-file-medical fa-xl"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Novedades de la Academia (Feed de Actividad) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card-premium p-4">
                <h5 class="text-white fw-bold mb-3"><i class="fa-solid fa-bell text-warning me-2"></i>Novedades</h5>
                <?php if (count($novedades) > 0): ?>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php foreach ($novedades as $nov): ?>
                            <a href="<?php echo $nov['enlace']; ?>" class="list-group-item list-group-item-action bg-transparent text-white border-0 py-3 px-0 d-flex align-items-center justify-content-between novelty-item <?php echo $nov['extra_class']; ?>" style="transition: background 0.2s;">
                                <div class="d-flex align-items-center">
                                    <div class="bg-dark rounded-circle border border-secondary me-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; flex-shrink: 0;">
                                        <i class="<?php echo $nov['icono']; ?> fa-lg"></i>
                                    </div>
                                    <div>
                                        <span class="d-block text-secondary small fw-bold text-uppercase" style="font-size: 0.7rem;"><?php echo $nov['titulo']; ?></span>
                                        <span class="text-white-50" style="font-size: 0.9rem;"><?php echo $nov['mensaje']; ?></span>
                                    </div>
                                </div>
                                <span class="badge bg-secondary-custom text-secondary small" style="flex-shrink: 0; margin-left: 10px;"><?php echo $nov['fecha_display']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-secondary">
                        <i class="fa-solid fa-circle-info fa-2x mb-2 text-muted"></i>
                        <p class="mb-0">No hay novedades recientes en el equipo.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Columna de Pagos Recientes -->
        <div class="col-lg-6 mb-4">
            <div class="card-premium p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-white fw-bold mb-0"><i class="fa-solid fa-cash-register text-warning me-2"></i>Reportes de Pagos Recientes</h5>
                    <a href="/admin/pagos.php" class="btn btn-trail-outline btn-sm">Ver Todos</a>
                </div>

                <?php if (count($ultimos_pagos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border border-secondary">
                            <thead>
                                <tr class="bg-dark text-secondary">
                                    <th class="border-secondary py-3">Alumno</th>
                                    <th class="border-secondary py-3">Mes</th>
                                    <th class="border-secondary py-3 text-end">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos_pagos as $p): ?>
                                    <tr>
                                        <td class="border-secondary py-3 text-white fw-semibold"><?php echo htmlspecialchars($p['nombre'] . " " . $p['apellido']); ?></td>
                                        <td class="border-secondary py-3"><?php echo htmlspecialchars($p['mes_pagado']); ?></td>
                                        <td class="border-secondary py-3 text-end">
                                            <?php if ($p['estado'] === 'pendiente'): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">Pendiente</span>
                                            <?php elseif ($p['estado'] === 'aprobado'): ?>
                                                <span class="badge bg-success text-white px-2 py-1">Aprobado</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger text-white px-2 py-1">Rechazado</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-secondary">
                        <i class="fa-solid fa-receipt fa-2x mb-2 text-muted"></i>
                        <p class="mb-0">No se han registrado reportes de pago.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Columna de Certificados Recientes -->
        <div class="col-lg-6 mb-4">
            <div class="card-premium p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-white fw-bold mb-0"><i class="fa-solid fa-clipboard-user text-warning me-2"></i>Historial de Certificados</h5>
                    <a href="/admin/certificados.php" class="btn btn-trail-outline btn-sm">Ver Todos</a>
                </div>

                <?php if (count($ultimos_certs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border border-secondary">
                            <thead>
                                <tr class="bg-dark text-secondary">
                                    <th class="border-secondary py-3">Alumno</th>
                                    <th class="border-secondary py-3">Estado</th>
                                    <th class="border-secondary py-3 text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos_certs as $c): ?>
                                    <tr>
                                        <td class="border-secondary py-3 text-white fw-semibold"><?php echo htmlspecialchars($c['nombre'] . " " . $c['apellido']); ?></td>
                                        <td class="border-secondary py-3">
                                            <?php if ($c['certificado_medico_estado'] === 'pendiente'): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">Revisar</span>
                                            <?php elseif ($c['certificado_medico_estado'] === 'aprobado'): ?>
                                                <span class="badge bg-success text-white px-2 py-1">Aprobado</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger text-white px-2 py-1">Rechazado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="border-secondary py-3 text-end">
                                            <a href="<?php echo htmlspecialchars($c['certificado_medico_url']); ?>" target="_blank" class="btn btn-outline-warning btn-sm py-0.5 px-2">
                                                <i class="fa-solid fa-eye me-1"></i> Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-secondary">
                        <i class="fa-solid fa-file-prescription fa-2x mb-2 text-muted"></i>
                        <p class="mb-0">No hay certificados médicos cargados en el sistema.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

