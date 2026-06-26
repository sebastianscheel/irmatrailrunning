<?php
$current_user_name = isset($_SESSION['user_nombre']) ? $_SESSION['user_nombre'] : '';
$current_user_rol = isset($_SESSION['user_rol']) ? $_SESSION['user_rol'] : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="/index.php">
            <img src="/assets/img/logo.jpg" alt="Logo" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover; border: 2px solid var(--trail-orange);">
            <span>IRMA</span><span class="ms-1" style="font-weight: 300;">TRAIL RUNNING</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (empty($current_user_rol)): ?>
                    <!-- Menu publico -->
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php#sobre-nosotros">Sobre Nosotros</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php#planes">Planes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/index.php#contacto">Contacto</a>
                    </li>
                <?php elseif ($current_user_rol === 'admin' || $current_user_rol === 'entrenador'): ?>
                    <!-- Menu Administrador/Entrenador -->
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/dashboard.php">Resumen</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/alumnos.php">Alumnos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/planificador.php">Planificador</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/pagos.php">Pagos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/certificados.php">Certificados</a>
                    </li>
                <?php elseif ($current_user_rol === 'alumno'): ?>
                    <!-- Menu Alumno -->
                    <li class="nav-item">
                        <a class="nav-link" href="/alumno/dashboard.php">Mis Rutinas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/alumno/reportar_pago.php">Reportar Pago</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/alumno/perfil.php">Mi Perfil</a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center">
                <?php if (empty($current_user_rol)): ?>
                    <a href="/login.php" class="btn btn-trail rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 40px; height: 40px;" title="Acceso Panel"><i class="fa-solid fa-user"></i></a>
                <?php else: ?>
                    <?php 
                        $dashboard_url = ($current_user_rol === 'alumno') ? '/alumno/dashboard.php' : '/admin/dashboard.php';
                    ?>
                    <a href="<?php echo $dashboard_url; ?>" class="btn btn-trail-outline rounded-circle d-flex align-items-center justify-content-center p-0 me-2" style="width: 40px; height: 40px;" title="Ir a mi panel"><i class="fa-solid fa-gauge-high"></i></a>
                    <a href="/logout.php" class="btn btn-outline-danger rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 40px; height: 40px;" title="Salir"><i class="fa-solid fa-sign-out-alt"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
