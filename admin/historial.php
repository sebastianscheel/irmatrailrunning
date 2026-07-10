<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol: Solo admin y entrenador_total pueden ver la auditoría
require_rol(['admin', 'entrenador_total']);

$page_title = "Historial de Auditoría";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Parámetros de filtros
$filtro_accion = $_GET['accion'] ?? '';
$filtro_entidad = $_GET['entidad'] ?? '';
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_alumno = $_GET['alumno'] ?? '';
$filtro_desde = $_GET['desde'] ?? '';
$filtro_hasta = $_GET['hasta'] ?? '';

// Paginación
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Construir la consulta SQL
$sql = "SELECT * FROM audit_log WHERE 1=1";
$params = [];

if (!empty($filtro_accion)) {
    $sql .= " AND accion = ?";
    $params[] = $filtro_accion;
}
if (!empty($filtro_entidad)) {
    $sql .= " AND entidad = ?";
    $params[] = $filtro_entidad;
}
if (!empty($filtro_usuario)) {
    $sql .= " AND (usuario_nombre LIKE ? OR usuario_id = ?)";
    $params[] = "%$filtro_usuario%";
    $params[] = (int)$filtro_usuario;
}
if (!empty($filtro_alumno)) {
    $sql .= " AND (alumno_nombre LIKE ? OR alumno_id = ?)";
    $params[] = "%$filtro_alumno%";
    $params[] = (int)$filtro_alumno;
}
if (!empty($filtro_desde)) {
    $sql .= " AND fecha >= ?";
    $params[] = $filtro_desde . " 00:00:00";
}
if (!empty($filtro_hasta)) {
    $sql .= " AND fecha <= ?";
    $params[] = $filtro_hasta . " 23:59:59";
}

// Obtener cantidad total de registros para paginación
$sqlCount = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total_rows = $stmtCount->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Agregar orden y límites
$sql .= " ORDER BY fecha DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener mensajes/errores de retorno
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

$msg_text = '';
if ($msg === 'restore_ok') $msg_text = "La rutina ha sido restaurada exitosamente en el calendario del alumno.";

$err_text = '';
if ($error === 'invalid_log') $err_text = "ID de log de auditoría no válido.";
if ($error === 'not_restorable') $err_text = "Esta acción no es de tipo restaurable o carece de datos anteriores.";
if ($error === 'date_occupied') $err_text = "No se puede restaurar: Ya existe un entrenamiento programado para ese alumno en esa fecha.";
if ($error === 'db') $err_text = "Ocurrió un error en la base de datos.";
?>
<div class="container dashboard-container text-center">
    <div class="col-lg-11 mx-auto text-start">
        <h2 class="text-white fw-bold mb-2"><i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Historial de Auditoría</h2>
        <p class="text-secondary small mb-4">Registro completo de las operaciones de creación, edición y eliminación de datos por entrenadores y alumnos.</p>

        <?php if (!empty($msg_text)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="background: rgba(42, 157, 143, 0.15); border-color: var(--trail-orange); color: var(--text-primary);">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($msg_text); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($err_text)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($err_text); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Panel de Filtros -->
        <div class="card-premium p-3 mb-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2 col-6">
                    <label class="form-label form-label-custom">Entidad</label>
                    <select name="entidad" class="form-select form-control-custom bg-dark text-white">
                        <option value="">Todas</option>
                        <option value="rutina" <?php echo $filtro_entidad === 'rutina' ? 'selected' : ''; ?>>Rutinas</option>
                        <option value="plantilla" <?php echo $filtro_entidad === 'plantilla' ? 'selected' : ''; ?>>Plantillas</option>
                        <option value="usuario" <?php echo $filtro_entidad === 'usuario' ? 'selected' : ''; ?>>Usuarios</option>
                        <option value="perfil" <?php echo $filtro_entidad === 'perfil' ? 'selected' : ''; ?>>Perfil</option>
                        <option value="carrera" <?php echo $filtro_entidad === 'carrera' ? 'selected' : ''; ?>>Carreras</option>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label form-label-custom">Acción</label>
                    <select name="accion" class="form-select form-control-custom bg-dark text-white">
                        <option value="">Todas</option>
                        <option value="crear_rutina" <?php echo $filtro_accion === 'crear_rutina' ? 'selected' : ''; ?>>Crear Rutina</option>
                        <option value="editar_rutina" <?php echo $filtro_accion === 'editar_rutina' ? 'selected' : ''; ?>>Editar Rutina</option>
                        <option value="eliminar_rutina" <?php echo $filtro_accion === 'eliminar_rutina' ? 'selected' : ''; ?>>Eliminar Rutina</option>
                        <option value="restaurar_rutina" <?php echo $filtro_accion === 'restaurar_rutina' ? 'selected' : ''; ?>>Restaurar Rutina</option>
                        <option value="aplicar_plantilla" <?php echo $filtro_accion === 'aplicar_plantilla' ? 'selected' : ''; ?>>Aplicar Plantilla</option>
                        <option value="crear_alumno" <?php echo $filtro_accion === 'crear_alumno' ? 'selected' : ''; ?>>Crear Alumno</option>
                        <option value="eliminar_alumno" <?php echo $filtro_accion === 'eliminar_alumno' ? 'selected' : ''; ?>>Eliminar Alumno</option>
                        <option value="registrar_feedback" <?php echo $filtro_accion === 'registrar_feedback' ? 'selected' : ''; ?>>Registrar Feedback</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-custom">Autor / Ejecutor</label>
                    <input type="text" name="usuario" class="form-control form-control-custom bg-dark text-white" value="<?php echo htmlspecialchars($filtro_usuario); ?>" placeholder="ID o Nombre">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-custom">Alumno afectado</label>
                    <input type="text" name="alumno" class="form-control form-control-custom bg-dark text-white" value="<?php echo htmlspecialchars($filtro_alumno); ?>" placeholder="ID o Nombre">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label form-label-custom">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-custom bg-dark text-white" value="<?php echo htmlspecialchars($filtro_desde); ?>">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label form-label-custom">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-custom bg-dark text-white" value="<?php echo htmlspecialchars($filtro_hasta); ?>">
                </div>
                <div class="col-12 text-end mt-3">
                    <a href="/admin/historial.php" class="btn btn-outline-secondary btn-sm me-2"><i class="fa-solid fa-eraser me-1"></i>Limpiar</a>
                    <button type="submit" class="btn btn-trail btn-sm"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                </div>
            </form>
        </div>

        <!-- Listado de Auditoría -->
        <div class="card-premium p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover mb-0" style="vertical-align: middle;">
                    <thead>
                        <tr class="border-bottom border-secondary" style="font-size: 0.85rem;">
                            <th class="p-3">Fecha y Hora</th>
                            <th class="p-3">Ejecutor (Rol)</th>
                            <th class="p-3">Acción</th>
                            <th class="p-3">Alumno Afectado</th>
                            <th class="p-3">Detalle</th>
                            <th class="p-3 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.82rem;">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="p-4 text-center text-muted">No se encontraron registros de auditoría que coincidan con los filtros.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): 
                                $badge_class = 'bg-secondary';
                                if (strpos($log['accion'], 'crear') !== false || strpos($log['accion'], 'sync') !== false || $log['accion'] === 'aprobar_certificado') {
                                    $badge_class = 'bg-success bg-opacity-25 text-success border border-success border-opacity-50';
                                } elseif (strpos($log['accion'], 'eliminar') !== false || $log['accion'] === 'rechazar_certificado') {
                                    $badge_class = 'bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50';
                                } elseif (strpos($log['accion'], 'editar') !== false || strpos($log['accion'], 'actualizar') !== false || $log['accion'] === 'aplicar_plantilla' || $log['accion'] === 'registrar_feedback' || $log['accion'] === 'subir_certificado') {
                                    $badge_class = 'bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50';
                                } elseif ($log['accion'] === 'restaurar_rutina') {
                                    $badge_class = 'bg-info bg-opacity-25 text-info border border-info border-opacity-50';
                                }
                            ?>
                                <tr>
                                    <td class="p-3 text-nowrap"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>
                                    <td class="p-3">
                                        <div class="fw-bold text-white"><?php echo htmlspecialchars($log['usuario_nombre']); ?></div>
                                        <div class="text-secondary small" style="font-size: 0.7rem;">(ID: <?php echo $log['usuario_id']; ?> - <?php echo htmlspecialchars($log['usuario_rol']); ?>)</div>
                                    </td>
                                    <td class="p-3">
                                        <span class="badge rounded-pill px-2.5 py-1 <?php echo $badge_class; ?>" style="font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($log['accion']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <?php if ($log['alumno_id']): ?>
                                            <div class="fw-semibold text-white"><?php echo htmlspecialchars($log['alumno_nombre']); ?></div>
                                            <div class="text-muted small" style="font-size: 0.7rem;">ID Alumno: <?php echo $log['alumno_id']; ?></div>
                                        <?php else: ?>
                                            <span class="text-muted italic">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-secondary" style="max-width: 300px;"><?php echo htmlspecialchars($log['detalle']); ?></td>
                                    <td class="p-3 text-end text-nowrap">
                                        <button class="btn btn-sm btn-outline-light me-1" onclick="verDetalleAudit(<?php echo $log['id']; ?>)" title="Ver Datos JSON"><i class="fa-solid fa-eye"></i> Detalle</button>
                                        
                                        <?php if ($log['accion'] === 'eliminar_rutina' && !empty($log['datos_anteriores'])): ?>
                                            <form action="/actions/admin_rutina_action.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de restaurar esta planificación?');">
                                                <input type="hidden" name="action" value="restore_rutina">
                                                <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Restaurar Rutina"><i class="fa-solid fa-undo me-1"></i> Restaurar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
                <nav class="p-3 border-top border-secondary d-flex justify-content-center">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark text-white border-secondary" href="?page=<?php echo $page - 1; ?>&entidad=<?php echo $filtro_entidad; ?>&accion=<?php echo $filtro_accion; ?>&usuario=<?php echo $filtro_usuario; ?>&alumno=<?php echo $filtro_alumno; ?>&desde=<?php echo $filtro_desde; ?>&hasta=<?php echo $filtro_hasta; ?>"><i class="fa-solid fa-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link <?php echo $page == $i ? 'bg-trail border-trail text-white' : 'bg-dark text-white border-secondary'; ?>" href="?page=<?php echo $i; ?>&entidad=<?php echo $filtro_entidad; ?>&accion=<?php echo $filtro_accion; ?>&usuario=<?php echo $filtro_usuario; ?>&alumno=<?php echo $filtro_alumno; ?>&desde=<?php echo $filtro_desde; ?>&hasta=<?php echo $filtro_hasta; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark text-white border-secondary" href="?page=<?php echo $page + 1; ?>&entidad=<?php echo $filtro_entidad; ?>&accion=<?php echo $filtro_accion; ?>&usuario=<?php echo $filtro_usuario; ?>&alumno=<?php echo $filtro_alumno; ?>&desde=<?php echo $filtro_desde; ?>&hasta=<?php echo $filtro_hasta; ?>"><i class="fa-solid fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detalle Auditoría -->
<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-custom">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-code text-warning me-2"></i>Detalle de Transacción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-danger fw-bold"><i class="fa-solid fa-history me-1"></i>Estado Anterior (Antes)</h6>
                        <pre id="preBefore" class="p-3 text-white rounded bg-dark border border-secondary" style="font-size: 0.75rem; max-height: 350px; overflow: auto;"></pre>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-success fw-bold"><i class="fa-solid fa-circle-plus me-1"></i>Estado Nuevo (Después)</h6>
                        <pre id="preAfter" class="p-3 text-white rounded bg-dark border border-secondary" style="font-size: 0.75rem; max-height: 350px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Guardar logs completos en JavaScript para visualización rápida en modal
const logsData = <?php echo json_encode($logs); ?>;
let auditModal = null;

function verDetalleAudit(logId) {
    const log = logsData.find(l => l.id == logId);
    if (!log) return;

    const preBefore = document.getElementById('preBefore');
    const preAfter = document.getElementById('preAfter');

    try {
        const beforeObj = log.datos_anteriores ? JSON.parse(log.datos_anteriores) : null;
        preBefore.textContent = beforeObj ? JSON.stringify(beforeObj, null, 4) : "No existen registros previos.";
    } catch(e) {
        preBefore.textContent = log.datos_anteriores || "No existen registros previos.";
    }

    try {
        const afterObj = log.datos_nuevos ? JSON.parse(log.datos_nuevos) : null;
        preAfter.textContent = afterObj ? JSON.stringify(afterObj, null, 4) : "No existen modificaciones posteriores.";
    } catch(e) {
        preAfter.textContent = log.datos_nuevos || "No existen modificaciones posteriores.";
    }

    if (!auditModal) {
        auditModal = new bootstrap.Modal(document.getElementById('auditDetailModal'));
    }
    auditModal.show();
}
</script>
<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>
