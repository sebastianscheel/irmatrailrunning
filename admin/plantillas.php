<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$page_title = "Gestión de Plantillas";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$entrenador_id = $_SESSION['user_id'];

// Obtener todas las plantillas del entrenador (o de todos si es admin/superior)
$user_rol = $_SESSION['user_rol'];
if (in_array($user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
    $stmtP = $pdo->prepare("SELECT * FROM plantillas ORDER BY fecha_creacion DESC");
    $stmtP->execute();
} else {
    $stmtP = $pdo->prepare("SELECT * FROM plantillas WHERE entrenador_id = ? ORDER BY fecha_creacion DESC");
    $stmtP->execute([$entrenador_id]);
}
$plantillas = $stmtP->fetchAll();
?>

<div class="container dashboard-container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold"><i class="fa-solid fa-layer-group text-warning me-2"></i>Plantillas de Rutinas</h2>
            <p class="text-secondary mb-0">Crea y edita planes genéricos de entrenamiento en un solo paso y asígnalos a tus alumnos.</p>
        </div>
        <a href="plantilla_editor.php" class="btn btn-trail"><i class="fa-solid fa-plus me-2"></i>Nueva Plantilla</a>
    </div>

    <!-- Alertas con Código de Color (Verde = Aceptar/Guardar, Rojo = Cancelación/Error) -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'plantilla_ok'): ?>
            <div class="alert alert-success-custom mb-4">
                <i class="fa-solid fa-circle-check me-2"></i>Plantilla guardada con éxito.
            </div>
        <?php elseif ($_GET['msg'] === 'delete_ok'): ?>
            <div class="alert alert-success-custom mb-4">
                <i class="fa-solid fa-check me-2"></i>Plantilla eliminada correctamente.
            </div>
        <?php elseif ($_GET['msg'] === 'plantilla_aplicada'): ?>
            <div class="alert alert-success-custom mb-4">
                <i class="fa-solid fa-circle-check me-2"></i>Plantilla cargada al alumno con éxito.
            </div>
        <?php elseif ($_GET['msg'] === 'cancel_ok'): ?>
            <div class="alert alert-danger mb-4" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
                <i class="fa-solid fa-circle-xmark me-2 text-danger"></i>Creación/edición de plantilla cancelada.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger mb-4" style="background: rgba(231, 111, 81, 0.15); border-color: var(--danger-red); color: var(--text-primary);">
            <i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Hubo un error al procesar la plantilla. Inténtalo nuevamente.
        </div>
    <?php endif; ?>

    <div class="card-premium p-4">
        <h5 class="text-white fw-bold mb-4">Mis Plantillas Guardadas</h5>
        
        <?php if (count($plantillas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border border-secondary" style="border-radius: 12px; overflow: hidden;">
                    <thead>
                        <tr class="bg-dark text-secondary">
                            <th class="border-secondary py-3">Título</th>
                            <th class="border-secondary py-3">Duración</th>
                            <th class="border-secondary py-3">Descripción</th>
                            <th class="border-secondary py-3 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plantillas as $p): 
                            $weeks = ceil($p['duracion_dias'] / 7);
                        ?>
                            <tr>
                                <td class="border-secondary py-3 text-white fw-bold"><?php echo htmlspecialchars($p['titulo']); ?></td>
                                <td class="border-secondary py-3">
                                    <span class="badge bg-secondary"><i class="fa-regular fa-calendar me-1"></i><?php echo $weeks; ?> <?php echo $weeks == 1 ? 'Semana' : 'Semanas'; ?></span>
                                </td>
                                <td class="border-secondary py-3 text-secondary small" style="max-width: 250px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"><?php echo htmlspecialchars($p['descripcion']); ?></td>
                                <td class="border-secondary py-3 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-warning text-dark fw-bold btn-sm" onclick="abrirAsignarQuick(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['titulo'], ENT_QUOTES); ?>')" title="Asignar a Alumno">
                                            <i class="fa-solid fa-user-plus"></i>
                                        </button>
                                        <a href="plantilla_editor.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-warning btn-sm" title="Editar plantilla">
                                            <i class="fa-solid fa-edit"></i>
                                        </a>
                                        <a href="/actions/admin_plantilla_action.php?action=delete_plantilla&id=<?php echo $p['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Seguro que deseas eliminar esta plantilla?');" title="Eliminar">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="col-12">
                <div class="text-center py-5 text-secondary">
                    <i class="fa-solid fa-box-open fa-3x mb-3 text-muted"></i>
                    <p class="mb-0">No has creado ninguna plantilla. Empieza creando tu primer bloque de entrenamiento.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL QUICK ASIGNAR A ALUMNO -->
<div class="modal fade" id="quickAsignarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-custom">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-user-plus text-warning me-2"></i>Asignar Plantilla</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="/actions/admin_plantilla_action.php" method="POST">
                <input type="hidden" name="action" value="aplicar_plantilla">
                <input type="hidden" name="plantilla_id" id="quick_plantilla_id">
                <input type="hidden" name="redirect_source" value="plantillas">
                
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label class="form-label text-secondary small">Plantilla Seleccionada</label>
                        <input type="text" id="quick_plantilla_titulo" class="form-control form-control-custom" readonly>
                    </div>

                    <?php
                    // Obtener listado de alumnos activos
                    try {
                        $stmtAl = $pdo->query("
                            SELECT ap.id AS alumno_id, u.nombre, u.apellido 
                            FROM alumno_perfil ap
                            JOIN usuarios u ON ap.usuario_id = u.id
                            WHERE ap.activo IN (1, 3)
                            ORDER BY u.apellido ASC, u.nombre ASC
                        ");
                        $alumnos_dropdown = $stmtAl->fetchAll();
                    } catch (PDOException $e) {
                        $alumnos_dropdown = [];
                    }
                    ?>
                    
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Seleccionar Alumno *</label>
                        <select name="alumno_id" class="form-select form-control-custom" required>
                            <option value="">-- Seleccionar alumno --</option>
                            <?php foreach ($alumnos_dropdown as $ad): ?>
                                <option value="<?php echo $ad['alumno_id']; ?>"><?php echo htmlspecialchars($ad['apellido'] . ", " . $ad['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form-label-custom">Fecha de Inicio *</label>
                        <input type="date" name="fecha_inicio" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                        <div class="form-text text-secondary mt-1">La plantilla de entrenamientos se aplicará en el calendario del alumno a partir de este día.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm">Aplicar a Calendario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let quickModalObj = null;

function abrirAsignarQuick(plantillaId, plantillaTitulo) {
    document.getElementById('quick_plantilla_id').value = plantillaId;
    document.getElementById('quick_plantilla_titulo').value = plantillaTitulo;

    if (!quickModalObj) {
        quickModalObj = new bootstrap.Modal(document.getElementById('quickAsignarModal'));
    }
    quickModalObj.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
