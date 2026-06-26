<?php
$page_title = "Control de Certificados";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar rol de administrador
require_rol(['admin', 'entrenador']);

try {
    // Consultar todos los alumnos con certificado mÃ©dico cargado
    $stmt = $pdo->query("
        SELECT ap.id AS alumno_id, ap.certificado_medico_url, ap.certificado_medico_estado, 
               ap.certificado_medico_comentario, ap.dni,
               u.nombre, u.apellido, u.email
        FROM alumno_perfil ap
        JOIN usuarios u ON ap.usuario_id = u.id
        WHERE ap.certificado_medico_url IS NOT NULL
        ORDER BY ap.certificado_medico_estado ASC, u.apellido ASC
    ");
    $certificados = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid': $error_msg = "AcciÃ³n o ID de alumno invÃ¡lido."; break;
        case 'empty_comment': $error_msg = "Debes ingresar un motivo de rechazo obligatoriamente."; break;
        case 'db': $error_msg = "Error interno al guardar los cambios."; break;
    }
}

$success_msg = "";
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'aprobado_ok': $success_msg = "Certificado mÃ©dico aprobado con Ã©xito."; break;
        case 'rechazado_ok': $success_msg = "Certificado mÃ©dico rechazado. Se notificÃ³ el comentario en el panel del alumno."; break;
    }
}

// Clasificar por estado
$pendientes = [];
$aprobados = [];
$rechazados = [];

foreach ($certificados as $c) {
    if ($c['certificado_medico_estado'] === 'pendiente') {
        $pendientes[] = $c;
    } elseif ($c['certificado_medico_estado'] === 'aprobado') {
        $aprobados[] = $c;
    } elseif ($c['certificado_medico_estado'] === 'rechazado') {
        $rechazados[] = $c;
    }
}
?>

<div class="container dashboard-container">
    <div class="mb-4">
        <h2 class="text-white fw-bold"><i class="fa-solid fa-file-medical text-warning me-2"></i>Control de Certificados MÃ©dicos</h2>
        <p class="text-secondary mb-0">Revisa los documentos de aptitud fÃ­sica cargados por los alumnos y autoriza su validez.</p>
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

    <!-- Tabs de Bootstrap -->
    <div class="card-premium p-4">
        <ul class="nav nav-tabs border-secondary mb-4" id="certTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active text-white position-relative" id="pendientes-tab" data-bs-toggle="tab" data-bs-target="#pendientes-pane" type="button" role="tab" aria-selected="true" style="border: none; background: transparent;">
                    Pendientes
                    <?php if (count($pendientes) > 0): ?>
                        <span class="badge bg-warning text-dark ms-1"><?php echo count($pendientes); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-secondary" id="aprobados-tab" data-bs-toggle="tab" data-bs-target="#aprobados-pane" type="button" role="tab" aria-selected="false" style="border: none; background: transparent;">
                    Aprobados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-secondary" id="rechazados-tab" data-bs-toggle="tab" data-bs-target="#rechazados-pane" type="button" role="tab" aria-selected="false" style="border: none; background: transparent;">
                    Rechazados
                </button>
            </li>
        </ul>

        <div class="tab-content" id="certTabsContent">
            <!-- PestaÃ±a PENDIENTES -->
            <div class="tab-pane fade show active" id="pendientes-pane" role="tabpanel" tabindex="0">
                <?php if (count($pendientes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border border-secondary">
                            <thead>
                                <tr class="bg-dark text-secondary">
                                    <th class="border-secondary py-3">Alumno</th>
                                    <th class="border-secondary py-3">DNI</th>
                                    <th class="border-secondary py-3 text-center">Documento</th>
                                    <th class="border-secondary py-3 text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendientes as $p): ?>
                                    <tr>
                                        <td class="border-secondary py-3 text-white fw-semibold"><?php echo htmlspecialchars($p['apellido'] . ", " . $p['nombre']); ?></td>
                                        <td class="border-secondary py-3"><?php echo htmlspecialchars($p['dni']); ?></td>
                                        <td class="border-secondary py-3 text-center">
                                            <a href="<?php echo htmlspecialchars($p['certificado_medico_url']); ?>" target="_blank" class="btn btn-outline-warning btn-sm">
                                                <i class="fa-solid fa-eye me-1"></i> Ver Certificado
                                            </a>
                                        </td>
                                        <td class="border-secondary py-3 text-end">
                                            <div class="d-inline-flex gap-2">
                                                <!-- Aprobar -->
                                                <form action="/actions/admin_certificado_action.php" method="POST">
                                                    <input type="hidden" name="action" value="aprobar">
                                                    <input type="hidden" name="alumno_id" value="<?php echo $p['alumno_id']; ?>">
                                                    <button type="submit" class="btn btn-success-custom btn-sm">
                                                        <i class="fa-solid fa-check me-1"></i> Aprobar
                                                    </button>
                                                </form>
                                                <!-- Rechazar (Abre modal) -->
                                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $p['alumno_id']; ?>">
                                                    <i class="fa-solid fa-xmark me-1"></i> Rechazar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- MODAL: Rechazar Certificado -->
                                    <div class="modal fade" id="rejectModal<?php echo $p['alumno_id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
                                                <div class="modal-header border-bottom border-dark">
                                                    <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-file-circle-xmark text-danger me-2"></i>Rechazar Certificado</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="/actions/admin_certificado_action.php" method="POST">
                                                    <input type="hidden" name="action" value="rechazar">
                                                    <input type="hidden" name="alumno_id" value="<?php echo $p['alumno_id']; ?>">
                                                    <div class="modal-body text-start">
                                                        <p class="text-white">Especifica el motivo de rechazo para el alumno <strong><?php echo htmlspecialchars($p['nombre'] . " " . $p['apellido']); ?></strong>:</p>
                                                        <div class="mb-3">
                                                            <label for="comentario<?php echo $p['alumno_id']; ?>" class="form-label form-label-custom">Motivo de Rechazo *</label>
                                                            <textarea name="comentario" id="comentario<?php echo $p['alumno_id']; ?>" class="form-control form-control-custom" rows="3" placeholder="Ej: Falta la firma del mÃ©dico, el documento estÃ¡ borroso o expirado." required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top border-dark">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-danger btn-sm">Enviar Rechazo</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="fa-solid fa-clipboard-check fa-3x mb-3 text-muted"></i>
                        <p class="mb-0">No hay certificados mÃ©dicos pendientes de revisiÃ³n.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PestaÃ±a APROBADOS -->
            <div class="tab-pane fade" id="aprobados-pane" role="tabpanel" tabindex="0">
                <?php if (count($aprobados) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border border-secondary">
                            <thead>
                                <tr class="bg-dark text-secondary">
                                    <th class="border-secondary py-3">Alumno</th>
                                    <th class="border-secondary py-3 text-center">Documento</th>
                                    <th class="border-secondary py-3 text-end">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aprobados as $a): ?>
                                    <tr>
                                        <td class="border-secondary py-3 text-white fw-semibold"><?php echo htmlspecialchars($a['apellido'] . ", " . $a['nombre']); ?></td>
                                        <td class="border-secondary py-3 text-center">
                                            <a href="<?php echo htmlspecialchars($a['certificado_medico_url']); ?>" target="_blank" class="btn btn-outline-warning btn-sm">
                                                <i class="fa-solid fa-eye me-1"></i> Ver Certificado
                                            </a>
                                        </td>
                                        <td class="border-secondary py-3 text-end">
                                            <span class="badge bg-success text-white px-3 py-1.5"><i class="fa-solid fa-circle-check me-1"></i>Aprobado</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-secondary">
                        <p class="mb-0">No hay certificados aprobados todavÃ­a.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PestaÃ±a RECHAZADOS -->
            <div class="tab-pane fade" id="rechazados-pane" role="tabpanel" tabindex="0">
                <?php if (count($rechazados) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle border border-secondary">
                            <thead>
                                <tr class="bg-dark text-secondary">
                                    <th class="border-secondary py-3">Alumno</th>
                                    <th class="border-secondary py-3">Comentario de Rechazo</th>
                                    <th class="border-secondary py-3 text-center">Documento</th>
                                    <th class="border-secondary py-3 text-end">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rechazados as $r): ?>
                                    <tr>
                                        <td class="border-secondary py-3 text-white fw-semibold"><?php echo htmlspecialchars($r['apellido'] . ", " . $r['nombre']); ?></td>
                                        <td class="border-secondary py-3 text-secondary small"><?php echo htmlspecialchars($r['certificado_medico_comentario']); ?></td>
                                        <td class="border-secondary py-3 text-center">
                                            <a href="<?php echo htmlspecialchars($r['certificado_medico_url']); ?>" target="_blank" class="btn btn-outline-warning btn-sm">
                                                <i class="fa-solid fa-eye me-1"></i> Ver
                                            </a>
                                        </td>
                                        <td class="border-secondary py-3 text-end">
                                            <span class="badge bg-danger text-white px-3 py-1.5"><i class="fa-solid fa-times-circle me-1"></i>Rechazado</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-secondary">
                        <p class="mb-0">No hay certificados rechazados en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

