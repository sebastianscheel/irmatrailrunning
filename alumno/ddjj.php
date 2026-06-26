<?php
$page_title = "Declaración Jurada";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado y sea alumno
require_rol('alumno');

// Si ya aceptó la DDJJ, no tiene por qué estar aquí
$stmt = $pdo->prepare("SELECT ddjj_aceptada FROM alumno_perfil WHERE usuario_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$perfil = $stmt->fetch();

if ($perfil && $perfil['ddjj_aceptada'] == 1) {
    header("Location: /alumno/dashboard.php");
    exit;
}
?>

<div class="container my-5 flex-grow-1">
    <div class="ddjj-container card-premium p-4">
        <div class="text-center mb-4">
            <i class="fa-solid fa-file-signature text-warning fa-3x mb-3"></i>
            <h3 class="text-white fw-bold">Declaración Jurada Obligatoria</h3>
            <p class="text-muted small">Por favor, lee y acepta las condiciones legales del grupo antes de acceder a tu plan de entrenamiento.</p>
        </div>

        <div class="ddjj-box mb-4">
            <h5 class="text-white mb-3 text-uppercase fw-semibold">1. Deslinde de Responsabilidad Civil</h5>
            <p>
                Yo declaro que me encuentro en perfectas condiciones de salud físicas y psíquicas para realizar entrenamientos y participar en actividades de montaña organizadas por <strong>IB Trailrunning</strong>. 
                Asumo plenamente toda responsabilidad por los riesgos que pudieran surgir durante los entrenamientos (lesiones, accidentes, caídas, inclemencias climáticas, etc.) deslindando de cualquier responsabilidad civil o penal a los entrenadores y organizadores.
            </p>

            <h5 class="text-white mb-3 text-uppercase fw-semibold">2. Uso y Cesión de Derechos de Imagen</h5>
            <p>
                Autorizo expresamente a <strong>IB Trailrunning</strong> a tomar fotografías, grabaciones de video y material multimedia durante los entrenamientos y eventos en los que participe, y a utilizar dicho material con fines promocionales en redes sociales oficiales (Instagram, Facebook, sitio web, etc.) de forma indefinida y sin contraprestación económica alguna.
            </p>

            <h5 class="text-white mb-3 text-uppercase fw-semibold">3. Cobertura de Seguro y Aptitud Médica</h5>
            <p>
                Declaro y certifico que cuento con una cobertura médica vigente (obra social o medicina prepaga) y que me comprometo a mantener al día mi certificado de aptitud física (apto médico), presentándolo de manera anual a la organización. 
                Reconozco que el grupo cuenta con un seguro de accidentes personales complementario para entrenamientos presenciales, pero que el mismo no reemplaza mi responsabilidad individual sobre mi estado de salud general.
            </p>

            <h5 class="text-white mb-3 text-uppercase fw-semibold">4. Derecho de Admisión y Permanencia</h5>
            <p>
                Comprendo y acepto que el cuerpo de entrenadores se reserva el derecho de admisión y permanencia al grupo de entrenamiento basado en el cumplimiento de las normas de convivencia, respeto mutuo, conducta deportiva y puntualidad en los pagos de la membresía.
            </p>
        </div>

        <form action="/actions/ddjj_action.php" method="POST">
            <div class="form-check mb-4 text-start">
                <input class="form-check-input" type="checkbox" name="accept_terms" id="accept_terms" required style="cursor: pointer;">
                <label class="form-check-label text-secondary small" for="accept_terms" style="cursor: pointer; user-select: none;">
                    He leído atentamente y **acepto los términos descritos**, la cesión de derechos de imagen para Instagram y las condiciones de admisión de IB Trailrunning.
                </label>
            </div>

            <button type="submit" class="btn btn-trail w-100 py-2.5 fw-bold">
                <i class="fa-solid fa-signature me-2"></i>Firmar Declaración Jurada y Acceder
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
