<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea entrenador o admin
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$page_title = "Entrenamientos Guardados";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$entrenador_id = $_SESSION['user_id'];

try {
    $user_rol = $_SESSION['user_rol'];
    if (in_array($user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
        $stmt = $pdo->prepare("SELECT * FROM entrenamientos_individuales ORDER BY titulo ASC");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM entrenamientos_individuales WHERE entrenador_id = ? ORDER BY titulo ASC");
        $stmt->execute([$entrenador_id]);
    }
    $entrenamientos = $stmt->fetchAll();
} catch (PDOException $e) {
    $entrenamientos = [];
}

$success_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';
?>

<div class="container dashboard-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold"><i class="fa-solid fa-dumbbell text-warning me-2"></i>Entrenamientos Guardados</h2>
            <p class="text-secondary mb-0">Crea sesiones individuales y cárgalas rápidamente desde el planificador.</p>
        </div>
        <button class="btn btn-trail shadow-sm mt-3 mt-sm-0" data-bs-toggle="modal" data-bs-target="#nuevoEntrenamientoModal">
            <i class="fa-solid fa-plus me-2"></i>Nueva Sesión
        </button>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success-custom alert-dismissible fade show mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            <?php
            if ($success_msg === 'created') echo "Entrenamiento guardado correctamente.";
            elseif ($success_msg === 'updated') echo "Entrenamiento actualizado correctamente.";
            elseif ($success_msg === 'deleted') echo "Entrenamiento eliminado correctamente.";
            ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
            <i class="fa-solid fa-circle-exclamation me-2 text-danger"></i>
            Error al procesar la solicitud. Inténtalo de nuevo.
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card-premium p-4">
        <h5 class="text-white fw-bold mb-4">Mis Sesiones Guardadas</h5>
        
        <?php if (count($entrenamientos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border border-secondary" style="border-radius: 12px; overflow: hidden;">
                    <thead>
                        <tr class="bg-dark text-secondary">
                            <th class="border-secondary py-3">Título</th>
                            <th class="border-secondary py-3">Tipo de Sesión</th>
                            <th class="border-secondary py-3">Terreno</th>
                            <th class="border-secondary py-3">Distancia (km)</th>
                            <th class="border-secondary py-3">Ritmo Sugerido</th>
                            <th class="border-secondary py-3 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entrenamientos as $e): ?>
                            <tr>
                                <td class="border-secondary py-3 text-white fw-bold"><?php echo htmlspecialchars($e['titulo']); ?></td>
                                <td class="border-secondary py-3">
                                    <span class="badge badge-tipo badge-<?php echo str_replace(' ', '-', strtolower($e['tipo_sesion'])); ?>">
                                        <?php echo htmlspecialchars($e['tipo_sesion']); ?>
                                    </span>
                                </td>
                                <td class="border-secondary py-3 text-secondary small"><?php echo htmlspecialchars($e['terreno']); ?></td>
                                <td class="border-secondary py-3 text-secondary"><?php echo $e['distancia_km'] > 0 ? $e['distancia_km'] . ' km' : '-'; ?></td>
                                <td class="border-secondary py-3 text-secondary"><?php echo !empty($e['ritmo_sugerido']) ? htmlspecialchars($e['ritmo_sugerido']) : '-'; ?></td>
                                <td class="border-secondary py-3 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $e['id']; ?>">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form action="/actions/admin_entrenamiento_action.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta sesión guardada?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <!-- MODAL EDITAR ENTRENAMIENTO -->
                            <div class="modal fade" id="editModal<?php echo $e['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
                                        <div class="modal-header border-bottom border-dark">
                                            <h5 class="modal-title text-white fw-bold">
                                                <i class="fa-solid fa-dumbbell text-warning me-2"></i>Editar Sesión
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="/actions/admin_entrenamiento_action.php" method="POST">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                            
                                            <div class="modal-body text-start">
                                                <div class="mb-3">
                                                    <label class="form-label form-label-custom">Título del Entrenamiento *</label>
                                                    <input type="text" name="titulo" class="form-control form-control-custom" value="<?php echo htmlspecialchars($e['titulo']); ?>" required>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label form-label-custom">Tipo de Sesión *</label>
                                                        <select name="tipo_sesion" class="form-select form-control-custom" required>
                                                            <option value="Bici" <?php echo ($e['tipo_sesion'] === 'Bici') ? 'selected' : ''; ?>>Bici</option>
                                                            <option value="Cambios de Ritmo" <?php echo ($e['tipo_sesion'] === 'Cambios de Ritmo') ? 'selected' : ''; ?>>Cambios de Ritmo</option>
                                                            <option value="Cuestas" <?php echo ($e['tipo_sesion'] === 'Cuestas') ? 'selected' : ''; ?>>Cuestas</option>
                                                            <option value="Fondo" <?php echo ($e['tipo_sesion'] === 'Fondo') ? 'selected' : ''; ?>>Fondo</option>
                                                            <option value="Pasadas" <?php echo ($e['tipo_sesion'] === 'Pasadas') ? 'selected' : ''; ?>>Pasadas</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label form-label-custom">Terreno *</label>
                                                        <select name="terreno" class="form-select form-control-custom" required>
                                                            <option value="Montaña" <?php echo ($e['terreno'] === 'Montaña') ? 'selected' : ''; ?>>Montaña</option>
                                                            <option value="Pista" <?php echo ($e['terreno'] === 'Pista') ? 'selected' : ''; ?>>Pista</option>
                                                            <option value="Plano" <?php echo ($e['terreno'] === 'Plano') ? 'selected' : ''; ?>>Plano</option>
                                                            <option value="Técnico" <?php echo ($e['terreno'] === 'Técnico') ? 'selected' : ''; ?>>Técnico</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label form-label-custom">Distancia (km)</label>
                                                        <input type="number" step="0.1" name="distancia_km" class="form-control form-control-custom" value="<?php echo $e['distancia_km']; ?>">
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="form-label form-label-custom">Ritmo Sugerido</label>
                                                        <input type="text" name="ritmo_sugerido" class="form-control form-control-custom" value="<?php echo htmlspecialchars($e['ritmo_sugerido']); ?>">
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label form-label-custom">Instrucciones Detalladas *</label>
                                                    <textarea name="descripcion" class="form-control form-control-custom" rows="5" required><?php echo htmlspecialchars($e['descripcion']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-top border-dark">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-trail btn-sm">Guardar Cambios</button>
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
                <i class="fa-solid fa-dumbbell fa-3x mb-3 text-muted"></i>
                <p class="mb-0">No tienes entrenamientos guardados. ¡Crea el primero usando el botón de arriba!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL NUEVO ENTRENAMIENTO -->
<div class="modal fade" id="nuevoEntrenamientoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-secondary border border-secondary" style="border-radius: 16px;">
            <div class="modal-header border-bottom border-dark">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fa-solid fa-dumbbell text-warning me-2"></i>Nueva Sesión Guardada
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="/actions/admin_entrenamiento_action.php" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Título del Entrenamiento *</label>
                        <input type="text" name="titulo" class="form-control form-control-custom" placeholder="Ej: Fondo Progresivo 12k" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label form-label-custom">Tipo de Sesión *</label>
                             <select name="tipo_sesion" class="form-select form-control-custom" required>
                                 <option value="Bici">Bici</option>
                                 <option value="Cambios de Ritmo">Cambios de Ritmo</option>
                                 <option value="Cuestas">Cuestas</option>
                                 <option value="Fondo" selected>Fondo</option>
                                 <option value="Pasadas">Pasadas</option>
                             </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-custom">Terreno *</label>
                             <select name="terreno" class="form-select form-control-custom" required>
                                 <option value="Montaña" selected>Montaña</option>
                                 <option value="Pista">Pista</option>
                                 <option value="Plano">Plano</option>
                                 <option value="Técnico">Técnico</option>
                             </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label form-label-custom">Distancia (km)</label>
                            <input type="number" step="0.1" name="distancia_km" class="form-control form-control-custom" placeholder="Ej: 12.0" value="0.0">
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-custom">Ritmo Sugerido</label>
                            <input type="text" name="ritmo_sugerido" class="form-control form-control-custom" placeholder="Ej: 5:15 min/km">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-custom">Instrucciones Detalladas *</label>
                        <textarea name="descripcion" class="form-control form-control-custom" rows="5" required><?php echo "Movilidad + +Elongacion\n\nNota:"; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-dark">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail btn-sm">Guardar Sesión</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
