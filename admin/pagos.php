<?php
$page_title = "Control de Pagos";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador
require_rol('admin');

// Capturar filtros
$estado_filter = isset($_GET['estado_filter']) ? trim($_GET['estado_filter']) : 'pendiente';

// Consulta base
$sql = "
    SELECT pr.*, ap.id AS alumno_id, ap.dni, ap.activo, u.nombre, u.apellido
    FROM pago_registro pr
    JOIN alumno_perfil ap ON pr.alumno_id = ap.id
    JOIN usuarios u ON ap.usuario_id = u.id
";

$params = [];
if (!empty($estado_filter)) {
    $sql .= " WHERE pr.estado = ?";
    $params[] = $estado_filter;
}

$sql .= " ORDER BY pr.fecha_reporte DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al consultar pagos: " . $e->getMessage());
}

$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid': $error_msg = "Acción o ID de pago inválido."; break;
        case 'db': $error_msg = "Error interno al procesar los cambios de pago."; break;
    }
}

$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'aprobado_ok': $success_msg = "Pago aprobado correctamente. El alumno ha sido marcado como Activo."; break;
        case 'rechazado_ok': $success_msg = "El pago ha sido marcado como Rechazado."; break;
    }
}

$meses_es = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];
?>

<div class="container dashboard-container">
    <div class="mb-4">
        <h2 class="text-white fw-bold"><i class="fa-solid fa-file-invoice-dollar text-warning me-2"></i>Control de Pagos Mensuales</h2>
        <p class="text-secondary mb-0">Revisa los comprobantes de transferencia declarados por los alumnos para validar sus membresías.</p>
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

    <!-- Panel de Filtros Rápidos -->
    <div class="card-premium p-3 mb-4">
        <form method="GET" action="/admin/pagos.php" class="row g-3 align-items-center">
            <div class="col-md-6 col-lg-4">
                <label for="estado_filter" class="form-label form-label-custom small">Filtrar por Estado</label>
                <select name="estado_filter" id="estado_filter" class="form-select form-control-custom" onchange="this.form.submit()">
                    <option value="pendiente" <?php echo ($estado_filter === 'pendiente') ? 'selected' : ''; ?>>Pendientes de Revisión</option>
                    <option value="aprobado" <?php echo ($estado_filter === 'aprobado') ? 'selected' : ''; ?>>Aprobados</option>
                    <option value="rechazado" <?php echo ($estado_filter === 'rechazado') ? 'selected' : ''; ?>>Rechazados</option>
                    <option value="" <?php echo ($estado_filter === '') ? 'selected' : ''; ?>>Todos los Reportes</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3 d-flex align-items-end mt-4">
                <button type="submit" class="btn btn-trail-outline btn-sm"><i class="fa-solid fa-sync me-2"></i>Actualizar</button>
            </div>
        </form>
    </div>

    <!-- Listado de Pagos -->
    <div class="card-premium p-4">
        <?php if (count($pagos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border border-secondary" style="border-radius: 12px; overflow: hidden;">
                    <thead>
                        <tr class="bg-dark text-secondary">
                            <th class="border-secondary py-3">Alumno</th>
                            <th class="border-secondary py-3">Mes Declarado</th>
                            <th class="border-secondary py-3">Monto</th>
                            <th class="border-secondary py-3">Fecha Reporte</th>
                            <th class="border-secondary py-3 text-center">Comprobante</th>
                            <?php if ($estado_filter === 'pendiente'): ?>
                                <th class="border-secondary py-3 text-end">Acción</th>
                            <?php else: ?>
                                <th class="border-secondary py-3 text-end">Estado</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $p): 
                            $mes_array = explode('-', $p['mes_pagado']);
                            $mes_nombre = isset($meses_es[$mes_array[1]]) ? $meses_es[$mes_array[1]] . " " . $mes_array[0] : $p['mes_pagado'];
                        ?>
                            <tr>
                                <td class="border-secondary py-3">
                                    <div class="text-white fw-bold"><?php echo htmlspecialchars($p['apellido'] . ", " . $p['nombre']); ?></div>
                                    <div class="text-secondary small">DNI: <?php echo htmlspecialchars($p['dni']); ?></div>
                                </td>
                                <td class="border-secondary py-3 text-white fw-semibold"><?php echo $mes_nombre; ?></td>
                                <td class="border-secondary py-3 font-monospace">$<?php echo number_format($p['monto'], 2, ',', '.'); ?></td>
                                <td class="border-secondary py-3 text-secondary small"><?php echo date('d/m/Y H:i', strtotime($p['fecha_reporte'])); ?></td>
                                <td class="border-secondary py-3 text-center">
                                    <a href="<?php echo htmlspecialchars($p['comprobante_url']); ?>" target="_blank" class="btn btn-outline-warning btn-sm">
                                        <i class="fa-solid fa-eye me-1"></i> Ver Captura
                                    </a>
                                </td>
                                <td class="border-secondary py-3 text-end">
                                    <?php if ($p['estado'] === 'pendiente'): ?>
                                        <div class="d-inline-flex gap-2">
                                            <!-- Botón Aprobar -->
                                            <form action="/actions/admin_pago_action.php" method="POST">
                                                <input type="hidden" name="action" value="aprobar">
                                                <input type="hidden" name="pago_id" value="<?php echo $p['id']; ?>">
                                                <input type="hidden" name="alumno_id" value="<?php echo $p['alumno_id']; ?>">
                                                <button type="submit" class="btn btn-success-custom btn-sm">
                                                    <i class="fa-solid fa-check me-1"></i> Aprobar
                                                </button>
                                            </form>
                                            <!-- Botón Rechazar -->
                                            <form action="/actions/admin_pago_action.php" method="POST">
                                                <input type="hidden" name="action" value="rechazar">
                                                <input type="hidden" name="pago_id" value="<?php echo $p['id']; ?>">
                                                <input type="hidden" name="alumno_id" value="<?php echo $p['alumno_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fa-solid fa-xmark me-1"></i> Rechazar
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($p['estado'] === 'aprobado'): ?>
                                            <span class="badge bg-success text-white px-3 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>Aprobado</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white px-3 py-1.5"><i class="fa-solid fa-circle-xmark me-1"></i>Rechazado</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-secondary">
                <i class="fa-solid fa-receipt fa-3x mb-3 text-muted"></i>
                <p class="mb-0">No se encontraron reportes de pagos con el filtro seleccionado.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
