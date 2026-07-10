<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea alumno
require_rol('alumno');

$page_title = "Calendario de Carreras";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Obtener el ID del alumno en base al usuario actual y verificar su estado
$stmtAlumno = $pdo->prepare("SELECT id, activo FROM alumno_perfil WHERE usuario_id = ?");
$stmtAlumno->execute([$_SESSION['user_id']]);
$perfil = $stmtAlumno->fetch();
$alumno_id = $perfil ? $perfil['id'] : null;
$esta_activo = $perfil ? (int)$perfil['activo'] : 0;

if (!$alumno_id) {
    header("Location: /logout.php");
    exit;
}

if ($esta_activo !== 1 && $esta_activo !== 3) {
    header("Location: /alumno/dashboard.php");
    exit;
}

// Obtener próximas carreras (que la fecha sea mayor o igual a hoy y que sean públicas o creadas por mí)
$sql = "
    SELECT c.*, 
           ac.id AS inscripcion_id, ac.objetivo, ac.distancia_elegida
    FROM carreras c
    LEFT JOIN alumno_carrera ac ON c.id = ac.carrera_id AND ac.alumno_id = ?
    WHERE c.fecha >= CURDATE() AND (c.alumno_creador_id IS NULL OR c.alumno_creador_id = ?)
    ORDER BY c.fecha ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$alumno_id, $alumno_id]);
$carreras = $stmt->fetchAll();

$success_msg = isset($_GET['msg']) ? $_GET['msg'] : '';
$error_msg = isset($_GET['err']) ? $_GET['err'] : '';
?>

<div class="container dashboard-container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold"><i class="fa-solid fa-map-location-dot text-trail me-2"></i>Calendario de Objetivos</h2>
            <p class="text-secondary mb-0">Explora el calendario oficial y anótate a tus próximas carreras para que tu entrenador pueda planificar hacia tu meta.</p>
        </div>
        <button class="btn btn-trail mt-3 mt-md-0" data-bs-toggle="modal" data-bs-target="#modalNuevoObjetivoPersonal">
            <i class="fa-solid fa-circle-plus me-2"></i>Agregar Objetivo Personal
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
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="h-100 p-0 overflow-hidden card-carrera-glow" style="border-radius: 16px;">
                        <div class="bg-dark p-3 border-bottom border-secondary position-relative text-center">
                            <?php if ($c['inscripcion_id']): ?>
                                <span class="position-absolute top-0 end-0 bg-trail text-dark px-3 py-1 fw-bold rounded-bottom-start shadow-sm" style="font-size: 0.8rem; right: 0;">
                                    <i class="fa-solid fa-thumbtack me-1"></i><?php echo !empty($c['alumno_creador_id']) ? 'Personal' : 'Anotado'; ?>
                                </span>
                            <?php endif; ?>
                            <span class="text-white fw-bold fs-6">
                                <i class="fa-regular fa-calendar-days text-trail me-2"></i><?php echo date('d M, Y', strtotime($c['fecha'])); ?>
                            </span>
                        </div>
                        <div class="p-4 d-flex flex-column align-items-center text-center h-100">
                            <h5 class="text-white fw-bold mb-2"><?php echo htmlspecialchars($c['titulo']); ?></h5>
                            <p class="text-secondary small mb-3"><i class="fa-solid fa-location-dot me-2"></i><?php echo htmlspecialchars($c['lugar'] ?? 'Ubicación a confirmar'); ?></p>
                            
                            <div class="mb-4">
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

                            <div class="mt-auto w-100">
                                <?php if ($c['inscripcion_id']): ?>
                                    <div class="bg-dark p-3 rounded border border-secondary mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-secondary text-uppercase fw-bold" style="font-size: 0.7rem;">Tu Inscripción</small>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($c['distancia_elegida']); ?></span>
                                        </div>
                                        <p class="text-white small mb-0 fst-italic">"<?php echo htmlspecialchars($c['objetivo']); ?>"</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if (!empty($c['url_info'])): ?>
                                            <a href="<?php echo htmlspecialchars($c['url_info']); ?>" target="_blank" class="btn btn-sm btn-outline-info flex-grow-1">
                                                <i class="fa-solid fa-link me-1"></i>Sitio Oficial
                                            </a>
                                        <?php endif; ?>
                                        <form action="/actions/alumno_carrera_action.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="inscripcion_id" value="<?php echo $c['inscripcion_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Bajarme de la carrera"><i class="fa-solid fa-xmark"></i></button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <button class="btn btn-trail w-100 mb-2" data-bs-toggle="modal" data-bs-target="#modalAnotarse_<?php echo $c['id']; ?>">
                                        <i class="fa-solid fa-hand-sparkles me-2"></i>¡Me Anoto!
                                    </button>
                                    <?php if (!empty($c['url_info'])): ?>
                                        <a href="<?php echo htmlspecialchars($c['url_info']); ?>" target="_blank" class="btn btn-sm btn-outline-light w-100 text-secondary">
                                            Más información
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Anotarse -->
                <?php if (!$c['inscripcion_id']): ?>
                <div class="modal fade" id="modalAnotarse_<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content modal-custom">
                            <form action="/actions/alumno_carrera_action.php" method="POST">
                                <input type="hidden" name="action" value="register">
                                <input type="hidden" name="carrera_id" value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                                
                                <div class="modal-header border-secondary">
                                    <h5 class="modal-title text-white"><i class="fa-solid fa-flag-checkered me-2 text-warning"></i>Inscribirse a <?php echo htmlspecialchars($c['titulo']); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label form-label-custom">¿Qué distancia vas a correr? *</label>
                                        <select name="distancia_elegida" class="form-select form-control-custom" required>
                                            <option value="" disabled selected>Selecciona tu distancia...</option>
                                            <?php 
                                            foreach ($distancias as $dist): 
                                                if (trim($dist) === '') continue;
                                            ?>
                                                <option value="<?php echo htmlspecialchars(trim($dist)); ?>"><?php echo htmlspecialchars(trim($dist)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label form-label-custom">Tu objetivo personal *</label>
                                        <textarea name="objetivo" class="form-control form-control-custom" rows="3" placeholder="Ej: Bajar de las 5 horas, Llegar entero, Correr de noche..." required></textarea>
                                        <div class="form-text text-secondary mt-2">Esta información la verá tu entrenador al momento de armarte la rutina.</div>
                                    </div>
                                </div>
                                <div class="modal-footer border-secondary">
                                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Pensarlo mejor</button>
                                    <button type="submit" class="btn btn-trail">¡Confirmar Objetivo!</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card-premium p-5 text-center text-secondary">
                    <i class="fa-solid fa-face-frown-open fa-3x mb-3"></i>
                    <h5>No hay próximas carreras publicadas</h5>
                    <p class="mb-0">Consulta con tu entrenador cuándo publicarán los próximos eventos.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nuevo Objetivo Personal -->
<div class="modal fade" id="modalNuevoObjetivoPersonal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-custom">
            <form action="/actions/alumno_carrera_action.php" method="POST">
                <input type="hidden" name="action" value="create_custom">
                <input type="hidden" name="alumno_id" value="<?php echo $alumno_id; ?>">
                
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white"><i class="fa-solid fa-trophy text-warning me-2"></i>Nuevo Objetivo Personal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Nombre de la Carrera / Objetivo *</label>
                        <input type="text" name="titulo" class="form-control form-control-custom" placeholder="Ej: Maratón de Buenos Aires, Carrera de Montaña 25k" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Fecha del Evento *</label>
                        <input type="date" name="fecha" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Lugar (Opcional)</label>
                        <input type="text" name="lugar" class="form-control form-control-custom" placeholder="Ej: Córdoba, Bariloche">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Distancia (Opcional)</label>
                        <input type="text" name="distancia_elegida" class="form-control form-control-custom" placeholder="Ej: 21k, 42k, 100k">
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-custom">Tu objetivo personal *</label>
                        <textarea name="objetivo" class="form-control form-control-custom" rows="3" placeholder="Ej: Bajar de las 4 horas, Terminar entero..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-trail">Guardar Objetivo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
