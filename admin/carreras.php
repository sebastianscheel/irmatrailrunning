<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea admin o cualquier entrenador
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$page_title = "Calendario de Carreras";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Filtrar carreras para mostrar sólo las públicas creadas por el admin/entrenador (alumno_creador_id IS NULL)
$sql = "SELECT * FROM carreras WHERE alumno_creador_id IS NULL ORDER BY fecha ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$carreras = $stmt->fetchAll();

// Mensajes
$success_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$error_msg = isset($_GET['err']) ? $_GET['err'] : '';
?>

<div class="container dashboard-container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold"><i class="fa-solid fa-calendar-check text-trail me-2"></i>Calendario de Carreras</h2>
            <p class="text-secondary mb-0">Publica los próximos eventos deportivos para que tus alumnos se anoten como objetivos.</p>
        </div>
        <button class="btn btn-trail mt-3 mt-md-0" data-bs-toggle="modal" data-bs-target="#modalNuevaCarrera">
            <i class="fa-solid fa-plus me-2"></i>Nueva Carrera
        </button>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php if (count($carreras) > 0): ?>
            <?php foreach ($carreras as $c): ?>
                <?php 
                    $is_past = strtotime($c['fecha']) < strtotime('today');
                    $card_class = $is_past ? 'card-premium border-secondary opacity-75' : 'card-carrera-glow';
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="h-100 p-0 overflow-hidden <?php echo $card_class; ?>" style="border-radius: 16px;">
                        <div class="bg-dark p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
                            <span class="badge <?php echo $is_past ? 'bg-secondary' : 'bg-trail text-dark'; ?> fs-6">
                                <i class="fa-regular fa-calendar me-2"></i><?php echo date('d M, Y', strtotime($c['fecha'])); ?>
                            </span>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-light border-0" data-bs-toggle="modal" data-bs-target="#modalEdit_<?php echo $c['id']; ?>" title="Editar"><i class="fa-solid fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger border-0" data-bs-toggle="modal" data-bs-target="#modalDelete_<?php echo $c['id']; ?>" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                        <div class="p-4 text-center">
                            <h5 class="text-white fw-bold mb-2"><?php echo htmlspecialchars($c['titulo']); ?></h5>
                            <p class="text-secondary small mb-3"><i class="fa-solid fa-location-dot me-2"></i><?php echo htmlspecialchars($c['lugar'] ?? 'Ubicación a confirmar'); ?></p>
                            
                            <div class="mb-3">
                                <span class="text-muted small d-block mb-2">Distancias:</span>
                                <div class="d-flex flex-wrap gap-1 justify-content-center">
                                    <?php 
                                    $distancias = explode(',', $c['distancias']);
                                    foreach ($distancias as $dist): 
                                        if (trim($dist) === '') continue;
                                    ?>
                                        <span class="badge bg-dark border border-secondary text-white"><?php echo htmlspecialchars(trim($dist)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if (!empty($c['url_info'])): ?>
                                <a href="<?php echo htmlspecialchars($c['url_info']); ?>" target="_blank" class="btn btn-sm btn-outline-info w-100 mt-2">
                                    <i class="fa-solid fa-link me-2"></i>Sitio Oficial
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Modal Editar -->
                <div class="modal fade" id="modalEdit_<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content modal-custom">
                            <form action="/actions/admin_carrera_action.php" method="POST">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title text-white"><i class="fa-solid fa-edit me-2 text-warning"></i>Editar Carrera</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label form-label-custom">Nombre de la Carrera *</label>
                                        <input type="text" name="titulo" class="form-control form-control-custom" value="<?php echo htmlspecialchars($c['titulo']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label form-label-custom">Fecha *</label>
                                        <input type="date" name="fecha" class="form-control form-control-custom" value="<?php echo htmlspecialchars($c['fecha']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label form-label-custom">Lugar</label>
                                        <input type="text" name="lugar" class="form-control form-control-custom" value="<?php echo htmlspecialchars($c['lugar']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label form-label-custom">Distancias (separadas por coma) *</label>
                                        <input type="text" name="distancias" class="form-control form-control-custom" value="<?php echo htmlspecialchars($c['distancias']); ?>" placeholder="Ej: 10k, 21k, 42k" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label form-label-custom">URL de Información (Opcional)</label>
                                        <input type="url" name="url_info" class="form-control form-control-custom" value="<?php echo htmlspecialchars($c['url_info']); ?>" placeholder="https://...">
                                    </div>
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-trail">Guardar Cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal Eliminar -->
                <div class="modal fade" id="modalDelete_<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content modal-custom">
                            <form action="/actions/admin_carrera_action.php" method="POST">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Eliminar Carrera</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-white">
                                    <p>¿Seguro que deseas eliminar <strong><?php echo htmlspecialchars($c['titulo']); ?></strong>?</p>
                                    <p class="small text-secondary mb-0">Esta acción borrará también a todos los alumnos que se hayan anotado a esta carrera como objetivo.</p>
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger">Eliminar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card-premium p-5 text-center text-secondary">
                    <i class="fa-solid fa-calendar-xmark fa-3x mb-3"></i>
                    <h5>No hay carreras publicadas</h5>
                    <p class="mb-0">Haz clic en "Nueva Carrera" para comenzar a armar el calendario de la temporada.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva Carrera -->
<div class="modal fade" id="modalNuevaCarrera" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-custom">
            <form action="/actions/admin_carrera_action.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white"><i class="fa-solid fa-calendar-plus me-2 text-trail"></i>Registrar Carrera</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Nombre de la Carrera *</label>
                        <input type="text" name="titulo" class="form-control form-control-custom" placeholder="Ej: Patagonia Run" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Fecha *</label>
                        <input type="date" name="fecha" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Lugar</label>
                        <input type="text" name="lugar" class="form-control form-control-custom" placeholder="Ej: San Martín de los Andes">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Distancias (separadas por coma) *</label>
                        <input type="text" name="distancias" class="form-control form-control-custom" placeholder="Ej: 10k, 21k, 42k, 110k" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">URL de Información Oficial (Opcional)</label>
                        <input type="url" name="url_info" class="form-control form-control-custom" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail">Publicar Carrera</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
