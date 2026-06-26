<?php
$page_title = "Irma Trail Running";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Hero Section -->
<section class="hero-section position-relative" style="background: linear-gradient(135deg, rgba(16, 21, 34, 0.95), rgba(16, 21, 34, 0.8)), url('https://images.unsplash.com/photo-1544198365-f5d60b6d8190?q=80&w=2070&auto=format&fit=crop'); background-size: cover; background-position: center; min-height: 85vh; display: flex; align-items: center;">
    <div class="container text-center text-lg-start">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-5 mb-lg-0">
                <div class="badge text-dark mb-4 px-3 py-2 fw-semibold text-uppercase" style="background-color: var(--trail-orange); border-radius: 30px; letter-spacing: 2px;">
                    <i class="fa-solid fa-fire me-1"></i> Entrena. Supera. Descubre.
                </div>
                <h1 class="display-3 fw-bold text-white mb-4" style="line-height: 1.2;">
                    Bienvenido a <br><span style="color: #663399; text-shadow: 0px 2px 10px rgba(102, 51, 153, 0.4);">Irma Trail Running</span>
                </h1>
                <p class="text-secondary fs-5 mb-5" style="max-width: 600px;">
                    Somos una comunidad apasionada por la montaña. Planes personalizados a distancia y entrenamientos grupales presenciales para que alcances tu máximo nivel.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <a href="#planes" class="btn btn-trail btn-lg px-5"><i class="fa-solid fa-arrow-right me-2"></i>Ver Planes</a>
                    <a href="/login.php" class="btn btn-outline-light btn-lg px-5"><i class="fa-solid fa-user me-2"></i>Ingresar</a>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <!-- Large Logo Display -->
                <img src="/assets/img/logo.jpg" alt="Irma Trail Running Logo" class="img-fluid rounded-circle shadow-lg" style="max-width: 400px; border: 5px solid rgba(255, 107, 53, 0.3);">
            </div>
        </div>
    </div>
</section>

<!-- Floating WhatsApp -->
<a href="https://wa.me/5491111111111" class="whatsapp-float" target="_blank" title="Contactar por WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Estilos para el floating button -->
<style>
.whatsapp-float {
    position: fixed;
    width: 60px;
    height: 60px;
    bottom: 40px;
    right: 40px;
    background-color: #25d366;
    color: #FFF;
    border-radius: 50px;
    text-align: center;
    font-size: 30px;
    box-shadow: 0px 4px 15px rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}
.whatsapp-float:hover {
    background-color: #128c7e;
    color: #fff;
    transform: scale(1.1);
}
</style>

<!-- Sobre Nosotros -->
<section id="sobre-nosotros" class="py-5" style="background-color: var(--bg-primary);">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <img src="/assets/img/foto1.png" alt="Trail Running Group" class="img-fluid rounded-4 shadow" style="border: 1px solid var(--border-color);">
            </div>
            <div class="col-lg-6 ps-lg-5">
                <h2 class="text-white fw-bold mb-4">¿Qué es <span style="color: #663399; text-shadow: 0px 2px 10px rgba(102, 51, 153, 0.4);">Irma Trail Running</span>?</h2>
                
                <p class="text-secondary mb-4 fs-5 fw-light">
                    Más que un grupo de entrenamiento, somos una familia que comparte la pasión por recorrer senderos, conquistar cumbres y superar obstáculos.
                </p>
                <p class="text-secondary mb-4">
                    En Irma Trail Running, transformamos tu relación con la montaña mediante una metodología integral basada en el alto rendimiento deportivo. Nuestra propuesta incluye:
                </p>

                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 mt-1">
                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center border border-secondary" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-users text-warning"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h5 class="text-white fw-bold mb-1">Comunidad Activa y Enfoque Humano</h5>
                        <p class="text-secondary mb-0">Entrenamientos en grupo donde la motivación y el compañerismo son fundamentales. No solo enseñamos técnica, fomentamos una comunidad donde compartimos la pasión.</p>
                    </div>
                </div>

                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 mt-1">
                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center border border-secondary" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-ranking-star text-warning"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h5 class="text-white fw-bold mb-1">Niveles Adaptados y Flexibilidad</h5>
                        <p class="text-secondary mb-0">Desde planes iniciales hasta alto rendimiento. Tu rutina se diseña para ti, adaptada a tu ubicación, disponibilidad y objetivos individuales.</p>
                    </div>
                </div>

                <div class="d-flex mb-4">
                    <div class="flex-shrink-0 mt-1">
                        <div class="bg-dark rounded-circle d-flex align-items-center justify-content-center border border-secondary" style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-mountain-sun text-warning"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <h5 class="text-white fw-bold mb-1">Inspiración Real</h5>
                        <p class="text-secondary mb-0">Nos mueve el ejemplo de superación, como el de Irma, quien demuestra que no hay límites de edad para disfrutar del movimiento. ¡Únete a nuestra familia y descubre paisajes inolvidables!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Planes de Entrenamiento -->
<section id="planes" class="py-5" style="background-color: var(--bg-secondary);">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h2 class="text-white fw-bold display-5">Nuestros <span style="color: var(--trail-orange);">Planes</span></h2>
            <p class="text-secondary mx-auto fs-5" style="max-width: 600px;">Elige la modalidad que mejor se adapte a tus horarios, objetivos y ubicación.</p>
        </div>
        
        <div class="row g-4 justify-content-center">
            <!-- Plan Distancia -->
            <div class="col-md-5">
                <div class="card-premium h-100 p-1 plan-card-hover" style="background: linear-gradient(to bottom, var(--bg-secondary), var(--bg-primary)); transition: all 0.3s ease;">
                    <div class="p-5 text-center">
                        <div class="mb-4">
                            <i class="fa-solid fa-earth-americas fa-3x icon-plan" style="color: var(--text-muted); transition: color 0.3s ease;"></i>
                        </div>
                        <h3 class="text-white fw-bold mb-3">Plan a Distancia</h3>
                        <p class="text-secondary mb-4">Lleva tu entrenamiento a cualquier parte del mundo. Planificación 100% personalizada a través de nuestra plataforma web.</p>
                        
                        <ul class="text-start text-secondary list-unstyled mb-5">
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Rutinas semanales en tu panel</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Registro de feedback post-entreno</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Adaptado a tus horarios locales</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Comunicación vía WhatsApp</li>
                        </ul>
                        
                        <a href="https://wa.me/5491111111111?text=Hola,%20quiero%20info%20sobre%20el%20Plan%20a%20Distancia" target="_blank" class="btn btn-outline-warning w-100 py-3 fw-bold" style="border-radius: 8px;">Consultar Info</a>
                    </div>
                </div>
            </div>
            
            <!-- Plan Presencial -->
            <div class="col-md-5">
                <div class="card-premium h-100 p-1 plan-card-hover" style="background: linear-gradient(to bottom, var(--bg-secondary), var(--bg-primary)); transition: all 0.3s ease;">
                    <div class="p-5 text-center mt-3">
                        <div class="mb-4">
                            <i class="fa-solid fa-people-group fa-3x icon-plan" style="color: var(--text-muted); transition: color 0.3s ease;"></i>
                        </div>
                        <h3 class="text-white fw-bold mb-3">Plan Presencial</h3>
                        <p class="text-secondary mb-4">Suma la energía del grupo a tus entrenamientos. Clases presenciales con corrección técnica y seguimiento en vivo.</p>
                        
                        <ul class="text-start text-secondary list-unstyled mb-5">
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Entrenamientos presenciales guiados</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Todo lo incluido en el Plan Distancia</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Trabajo de fuerza, técnica y cuestas</li>
                            <li class="mb-3"><i class="fa-solid fa-check text-success me-3"></i>Logística para carreras en grupo</li>
                        </ul>
                        
                        <a href="https://wa.me/5491111111111?text=Hola,%20quiero%20info%20sobre%20el%20Plan%20Presencial" target="_blank" class="btn btn-outline-warning w-100 py-3 fw-bold" style="border-radius: 8px;">Consultar Cupos</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contacto Corto -->
<section id="contacto" class="py-5 text-center" style="background: linear-gradient(rgba(16,21,34,0.9), rgba(16,21,34,0.9)), url('https://images.unsplash.com/photo-1473283147055-e39c51470b0c?q=80&w=2070&auto=format&fit=crop'); background-attachment: fixed; background-size: cover;">
    <div class="container py-5">
        <h2 class="text-white display-5 fw-bold mb-4">Súmate a la manada</h2>
        <p class="text-secondary fs-5 mb-5 mx-auto" style="max-width: 600px;">Sigue nuestras aventuras en Instagram y escríbenos para coordinar tu primer entrenamiento.</p>
        
        <div class="d-flex flex-wrap justify-content-center gap-4">
            <a href="https://instagram.com/irmatrailrunning" target="_blank" class="btn btn-lg px-5 py-3 shadow-lg" style="background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); color: white; border: none; font-weight: bold; border-radius: 30px; transition: transform 0.3s ease;">
                <i class="fab fa-instagram me-2"></i> @irmatrailrunning
            </a>
        </div>
    </div>
</section>

<style>
/* Animaciones hover para cards e instagram */
.card-premium:hover { transform: translateY(-5px); }
.btn[href*="instagram"]:hover { transform: scale(1.05); }

/* Plan card hover effect */
.plan-card-hover:hover {
    border-color: #663399; /* Usa el color del logo */
    box-shadow: 0 0 20px rgba(102, 51, 153, 0.3);
}
.plan-card-hover:hover .icon-plan {
    color: #663399 !important;
}
.plan-card-hover:hover .btn-outline-warning {
    background-color: #663399;
    border-color: #663399;
    color: white;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
