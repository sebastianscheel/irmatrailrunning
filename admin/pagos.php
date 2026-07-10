<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador o entrenador total
require_rol(['admin', 'entrenador_total']);

$page_title = "Control de Pagos";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Capturar filtros
$estado_filter = isset($_GET['estado_filter']) ? trim($_GET['estado_filter']) : 'pendiente';
$mes_filter = isset($_GET['mes_filter']) ? trim($_GET['mes_filter']) : '';
$buscar_filter = isset($_GET['buscar_filter']) ? trim($_GET['buscar_filter']) : '';

// Obtener todos los meses cargados para el filtro
try {
    $stmtMeses = $pdo->query("SELECT DISTINCT mes_pagado FROM pago_registro ORDER BY mes_pagado DESC");
    $todos_meses = $stmtMeses->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $todos_meses = [];
}

// Consulta base
$sql = "
    SELECT pr.*, ap.id AS alumno_id, u.dni, ap.activo, u.nombre, u.apellido
    FROM pago_registro pr
    JOIN alumno_perfil ap ON pr.alumno_id = ap.id
    JOIN usuarios u ON ap.usuario_id = u.id
    WHERE 1=1
";

$params = [];
if (!empty($estado_filter)) {
    $sql .= " AND pr.estado = ?";
    $params[] = $estado_filter;
}
if (!empty($mes_filter)) {
    $sql .= " AND pr.mes_pagado = ?";
    $params[] = $mes_filter;
}
if (!empty($buscar_filter)) {
    $sql .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.dni LIKE ?)";
    $params[] = "%$buscar_filter%";
    $params[] = "%$buscar_filter%";
    $params[] = "%$buscar_filter%";
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
        <form method="GET" action="/admin/pagos.php" class="row g-3 align-items-end">
            <div class="col-md-4 col-lg-3">
                <label for="estado_filter" class="form-label form-label-custom small">Filtrar por Estado</label>
                <select name="estado_filter" id="estado_filter" class="form-select form-control-custom" onchange="this.form.submit()">
                    <option value="pendiente" <?php echo ($estado_filter === 'pendiente') ? 'selected' : ''; ?>>Pendientes de Revisión</option>
                    <option value="aprobado" <?php echo ($estado_filter === 'aprobado') ? 'selected' : ''; ?>>Aprobados</option>
                    <option value="rechazado" <?php echo ($estado_filter === 'rechazado') ? 'selected' : ''; ?>>Rechazados</option>
                    <option value="" <?php echo ($estado_filter === '') ? 'selected' : ''; ?>>Todos los Reportes</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-3">
                <label for="mes_filter" class="form-label form-label-custom small">Filtrar por Mes</label>
                <select name="mes_filter" id="mes_filter" class="form-select form-control-custom" onchange="this.form.submit()">
                    <option value="">Todos los Meses</option>
                    <?php foreach ($todos_meses as $m): 
                        $m_arr = explode('-', $m);
                        $m_lbl = isset($meses_es[$m_arr[1]]) ? $meses_es[$m_arr[1]] . " " . $m_arr[0] : $m;
                    ?>
                        <option value="<?php echo htmlspecialchars($m); ?>" <?php echo ($mes_filter === $m) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m_lbl); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-4">
                <label for="buscar_filter" class="form-label form-label-custom small">Buscar Alumno / DNI</label>
                <div class="input-group">
                    <input type="text" name="buscar_filter" id="buscar_filter" class="form-control form-control-custom" placeholder="Ej: Juan o DNI" value="<?php echo htmlspecialchars($buscar_filter); ?>">
                    <button type="submit" class="btn btn-trail btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </div>
            <div class="col-md-2 col-lg-2">
                <a href="/admin/pagos.php" class="btn btn-trail-outline btn-sm w-100"><i class="fa-solid fa-sync"></i> Limpiar</a>
            </div>
        </form>
    </div>

    <!-- Listado de Pagos -->
    <div class="card-premium p-4">
        <?php
        $pagos_agrupados = [];
        foreach ($pagos as $p) {
            $mes_array = explode('-', $p['mes_pagado']);
            $mes_nombre = isset($meses_es[$mes_array[1]]) ? $meses_es[$mes_array[1]] . " " . $mes_array[0] : $p['mes_pagado'];
            $pagos_agrupados[$mes_nombre][] = $p;
        }
        ?>

        <?php if (count($pagos_agrupados) > 0): ?>
            <?php foreach ($pagos_agrupados as $mes_grupo => $pagos_del_mes): ?>
                <div class="mb-4">
                    <h5 class="text-warning fw-bold mb-3"><i class="fa-solid fa-calendar-days me-2"></i><?php echo htmlspecialchars($mes_grupo); ?></h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border border-secondary mb-3" style="border-radius: 12px; overflow: hidden;">
                            <thead>
                                <tr class="bg-dark text-secondary">
                                    <th class="border-secondary py-3">Alumno</th>
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
                                <?php foreach ($pagos_del_mes as $p): ?>
                                    <tr>
                                        <td class="border-secondary py-3">
                                            <div class="text-white fw-bold"><?php echo htmlspecialchars($p['apellido'] . ", " . $p['nombre']); ?></div>
                                            <div class="text-secondary small">DNI: <?php echo htmlspecialchars($p['dni']); ?></div>
                                        </td>
                                        <td class="border-secondary py-3 font-monospace">$<?php echo number_format($p['monto'], 2, ',', '.'); ?></td>
                                        <td class="border-secondary py-3 text-secondary small"><?php echo date('d/m/Y H:i', strtotime($p['fecha_reporte'])); ?></td>
                                        <td class="border-secondary py-3 text-center">
                                            <?php if ($p['comprobante_url'] === 'BECA-RENOVADA'): ?>
                                                <span class="badge bg-info text-dark px-2 py-1"><i class="fa-solid fa-gift me-1"></i>Beca</span>
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars($p['comprobante_url']); ?>" target="_blank" class="btn btn-outline-warning btn-sm">
                                                    <i class="fa-solid fa-eye me-1"></i> Ver Captura
                                                </a>
                                            <?php endif; ?>
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
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5 text-secondary">
                <i class="fa-solid fa-receipt fa-3x mb-3 text-muted"></i>
                <p class="mb-0">No se encontraron reportes de pagos con el filtro seleccionado.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
