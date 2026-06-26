<?php
$page_title = "Admin Dashboard";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador
require_rol(['admin', 'entrenador']);

try {
    // 1. Contador de Alumnos Totales
    $stmtAlumnos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'alumno'");
    $cant_alumnos = $stmtAlumnos->fetchColumn();

    // 2. Contador de Alumnos Activos
    $stmtActivos = $pdo->query("SELECT COUNT(*) FROM alumno_perfil WHERE activo = 1");
    $cant_activos = $stmtActivos->fetchColumn();

    // 3. Pagos Pendientes
    $stmtPagosPend = $pdo->query("SELECT COUNT(*) FROM pago_registro WHERE estado = 'pendiente'");
    $cant_pagos_pend = $stmtPagosPend->fetchColumn();

    // 4. Certificados Pendientes
    $stmtCertPend = $pdo->query("SELECT COUNT(*) FROM alumno_perfil WHERE certificado_medico_estado = 'pendiente' AND certificado_medico_url IS NOT NULL");
    $cant_certs_pend = $stmtCertPend->fetchColumn();

    // 5. Ãšltimos Pagos Reportados
    $stmtUltimosPagos = $pdo->query("
        SELECT pr.*, u.nombre, u.apellido 
        FROM pago_registro pr
        JOIN alumno_perfil ap ON pr.alumno_id = ap.id
        JOIN usuarios u ON ap.usuario_id = u.id
        ORDER BY pr.fecha_reporte DESC 
        LIMIT 5
    ");
    $ultimos_pagos = $stmtUltimosPagos->fetchAll();

    // 6. Ãšltimos Certificados Subidos
    $stmtUltimosCerts = $pdo->query("
        SELECT ap.*, u.nombre, u.apellido 
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ap.certificado_medico_url IS NOT NULL
        ORDER BY ap.id DESC 
        LIMIT 5
    ");
    $ultimos_certs = $stmtUltimosCerts->fetchAll();

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}
?>

<div class="container dashboard-container">
    <div class="row mb-4">
        <div class="col">
            <h2 class="text-white fw-bold"><i class="fa-solid fa-gauge-high text-warning me-2"></i>Panel del Entrenador</h2>
            <p class="text-secondary mb-0">Vista general de alumnos, pagos y documentaciÃ³n del team.</p>
        </div>
    </div>

    <!-- Tarjetas de MÃ©tricas -->
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
                                    <th class="border-secondary py-3 text-end">AcciÃ³n</th>
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
                        <p class="mb-0">No hay certificados mÃ©dicos cargados en el sistema.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

