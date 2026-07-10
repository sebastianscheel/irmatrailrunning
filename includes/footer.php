    <!-- Footer Centrado y Simplificado -->
    <footer class="mt-auto py-4" style="background-color: var(--bg-secondary); border-top: 1px solid var(--border-color);">
        <div class="container text-center">
            <div class="mb-3">
                <a href="https://instagram.com/irmatrailrunning" target="_blank" rel="noopener noreferrer" class="text-white text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 1rem; transition: color 0.2s;" onmouseover="this.style.color='var(--trail-orange)'" onmouseout="this.style.color=''">
                    <i class="fab fa-instagram fa-lg"></i> @irmatrailrunning
                </a>
            </div>
            <p class="text-secondary small mb-0">&copy; <?php echo date("Y"); ?> Baricode. Todos los derechos reservados.</p>
            <div class="mt-1">
                <a href="https://bari-code.com/" target="_blank" rel="noopener noreferrer" class="text-secondary text-decoration-none small" style="font-size: 0.8rem; transition: color 0.2s;" onmouseover="this.style.color='var(--trail-orange)'" onmouseout="this.style.color=''">
                    www.bari-code.com
                </a>
            </div>
        </div>
    </footer>

    <!-- Botón Flotante Ir Arriba -->
    <a href="#" id="backToTopBtn" class="back-to-top" title="Volver arriba">
        <i class="fa-solid fa-chevron-up"></i>
    </a>

    <style>
    .back-to-top {
        position: fixed;
        width: 45px;
        height: 45px;
        bottom: 120px;
        right: 47px;
        background-color: var(--bg-tertiary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        box-shadow: 0px 4px 10px rgba(0,0,0,0.4);
        z-index: 999;
        transition: all 0.3s ease;
        text-decoration: none !important;
    }
    .back-to-top:hover {
        background-color: var(--trail-orange);
        color: #fff;
        transform: translateY(-5px);
    }
    </style>

    <script>
    // Mostrar/ocultar el botón de subir basado en scroll
    window.addEventListener('scroll', function() {
        const btn = document.getElementById('backToTopBtn');
        if (btn) {
            if (window.scrollY > 300) {
                btn.style.display = 'flex';
            } else {
                btn.style.display = 'none';
            }
        }
    });

    // Scroll suave hacia arriba
    document.getElementById('backToTopBtn').addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    </script>

    <!-- Bootstrap 5 Bundle JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="/assets/js/main.js"></script>
</body>
</html>
