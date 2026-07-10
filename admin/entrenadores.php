<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Solo admin y entrenadores con privilegios totales pueden gestionar entrenadores
require_rol(['admin', 'entrenador_total']);

$page_title = "Gestión de Entrenadores";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$sql = "
    SELECT id, nombre, apellido, email, telefono, rol, foto_perfil_url, dni
    FROM usuarios
    WHERE rol IN ('entrenador_total', 'entrenador_intermedio', 'entrenador_limitado')
    ORDER BY apellido ASC, nombre ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$entrenadores = $stmt->fetchAll();
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-1"><i class="fa-solid fa-user-tie text-trail me-2"></i>Entrenadores</h2>
            <p class="text-secondary">Gestiona a los profesores y sus niveles de acceso.</p>
        </div>
        <button class="btn btn-trail" data-bs-toggle="modal" data-bs-target="#modalEntrenador">
            <i class="fa-solid fa-plus me-2"></i>Nuevo Entrenador
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($_GET['err']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card-premium">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Entrenador</th>
                        <th>Contacto</th>
                        <th>Nivel de Acceso</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($entrenadores) > 0): ?>
                        <?php foreach ($entrenadores as $ent): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($ent['foto_perfil_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($ent['foto_perfil_url']); ?>" alt="Foto" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fa-solid fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($ent['nombre'] . ' ' . $ent['apellido']); ?></h6>
                                            <small class="text-secondary d-block"><?php echo htmlspecialchars($ent['email']); ?></small>
                                            <?php if (!empty($ent['dni'])): ?>
                                                <small class="text-secondary font-monospace" style="font-size: 0.75rem;"><i class="fa-solid fa-id-card me-1"></i>DNI: <?php echo htmlspecialchars($ent['dni']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($ent['telefono'])): ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $ent['telefono']); ?>" target="_blank" class="text-success text-decoration-none">
                                            <i class="fa-brands fa-whatsapp me-1"></i><?php echo htmlspecialchars($ent['telefono']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-secondary"><i class="fa-solid fa-phone-slash me-1"></i>Sin número</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ent['rol'] === 'entrenador_total'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-crown me-1"></i>Total</span>
                                    <?php elseif ($ent['rol'] === 'entrenador_intermedio'): ?>
                                        <span class="badge bg-info text-dark"><i class="fa-solid fa-user-shield me-1"></i>Intermedio</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-dark"><i class="fa-solid fa-user-shield me-1"></i>Limitado</span>
                                    <?php endif; ?>
                                </td>
                                 <td class="text-end">
                                     <div class="btn-group">
                                         <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEntrenadorReset_<?php echo $ent['id']; ?>" title="Restablecer Contraseña">
                                             <i class="fa-solid fa-key"></i>
                                         </button>
                                         <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#modalEntrenadorEdit_<?php echo $ent['id']; ?>" title="Editar">
                                             <i class="fa-solid fa-edit"></i>
                                         </button>
                                         <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalEntrenadorDelete_<?php echo $ent['id']; ?>" title="Eliminar">
                                             <i class="fa-solid fa-trash"></i>
                                         </button>
                                     </div>
                                 </td>
                            </tr>

                            <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-4">No hay entrenadores registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (count($entrenadores) > 0): foreach ($entrenadores as $ent): ?>
<!-- Modal Eliminar -->
                            <div class="modal fade" id="modalEntrenadorDelete_<?php echo $ent['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content modal-custom">
                                        <form action="/actions/admin_entrenador_action.php" method="POST">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $ent['id']; ?>">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Eliminar Entrenador</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body text-white">
                                                ¿Estás seguro de que deseas eliminar a <strong><?php echo htmlspecialchars($ent['nombre'] . ' ' . $ent['apellido']); ?></strong>? 
                                                Esta acción eliminará su cuenta, pero sus alumnos seguirán en el sistema.
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-danger">Eliminar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Restablecer Contraseña -->
                            <div class="modal fade" id="modalEntrenadorReset_<?php echo $ent['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content modal-custom">
                                        <form action="/actions/admin_entrenador_action.php" method="POST">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?php echo $ent['id']; ?>">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title text-white"><i class="fa-solid fa-key me-2 text-warning"></i>Restablecer Contraseña</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body text-white">
                                                ¿Estás seguro de que deseas restablecer la contraseña de <strong><?php echo htmlspecialchars($ent['nombre'] . ' ' . $ent['apellido']); ?></strong>? 
                                                La contraseña temporal para este entrenador será <strong>123456</strong>.
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-warning text-dark fw-bold">Sí, Restablecer Contraseña</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Editar -->
                            <div class="modal fade" id="modalEntrenadorEdit_<?php echo $ent['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content modal-custom">
                                        <form action="/actions/admin_entrenador_action.php" method="POST" autocomplete="off">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="id" value="<?php echo $ent['id']; ?>">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title text-white"><i class="fa-solid fa-edit me-2"></i>Editar Entrenador</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Nombre *</label>
                                                        <input type="text" name="nombre" class="form-control form-control-custom" value="<?php echo htmlspecialchars($ent['nombre']); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Apellido *</label>
                                                        <input type="text" name="apellido" class="form-control form-control-custom" value="<?php echo htmlspecialchars($ent['apellido']); ?>" required>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <label class="form-label form-label-custom">Email *</label>
                                                        <input type="email" name="email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($ent['email']); ?>" autocomplete="new-password" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">DNI / Pasaporte (Opcional)</label>
                                                        <input type="text" name="dni" class="form-control form-control-custom" value="<?php echo htmlspecialchars($ent['dni'] ?? ''); ?>" placeholder="Ej: 33280274">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Teléfono (WhatsApp)</label>
                                                        <input type="text" name="telefono" class="form-control form-control-custom" value="<?php echo htmlspecialchars($ent['telefono']); ?>" placeholder="Ej: +5491123456789">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label form-label-custom">Nivel de Privilegio *</label>
                                                        <select name="rol" class="form-select form-control-custom" required>
                                                            <option value="entrenador_total" <?php echo $ent['rol'] === 'entrenador_total' ? 'selected' : ''; ?>>Total (Gestión completa)</option>
                                                            <option value="entrenador_intermedio" <?php echo $ent['rol'] === 'entrenador_intermedio' ? 'selected' : ''; ?>>Intermedio (Sin finanzas/certificados)</option>
                                                            <option value="entrenador_limitado" <?php echo $ent['rol'] === 'entrenador_limitado' ? 'selected' : ''; ?>>Limitado (Solo visualiza alumnos asignados)</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-12 mt-3">
                                                        <hr class="border-secondary">
                                                        <label class="form-label form-label-custom">Nueva Contraseña (Opcional)</label>
                                                        <input type="password" name="password" class="form-control form-control-custom" placeholder="Dejar en blanco para no cambiar">
                                                    </div>
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
                        <?php endforeach; endif; ?>

<!-- Modal Nuevo Entrenador -->
<div class="modal fade" id="modalEntrenador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-custom">
            <form action="/actions/admin_entrenador_action.php" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white"><i class="fa-solid fa-user-plus me-2 text-trail"></i>Nuevo Entrenador</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Nombre *</label>
                            <input type="text" name="nombre" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Apellido *</label>
                            <input type="text" name="apellido" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label form-label-custom">Email *</label>
                            <input type="email" name="email" class="form-control form-control-custom" autocomplete="new-password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">DNI / Pasaporte (Opcional)</label>
                            <input type="text" name="dni" class="form-control form-control-custom" placeholder="Ej: 33280274">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Teléfono (WhatsApp)</label>
                            <input type="text" name="telefono" class="form-control form-control-custom" placeholder="Ej: +5491123456789">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-custom">Contraseña Inicial *</label>
                            <input type="password" name="password" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label form-label-custom">Nivel de Privilegio *</label>
                            <select name="rol" class="form-select form-control-custom" required>
                                                                <option value="entrenador_limitado">Limitado (Solo visualiza alumnos asignados)</option>
                                                                <option value="entrenador_intermedio">Intermedio (Sin finanzas/certificados)</option>
                                                                <option value="entrenador_total">Total (Gestión completa de entrenadores/alumnos)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail">Crear Entrenador</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
