<?php
// Evitar acceso directo
if (count(get_included_files()) == 1) {
    http_response_code(403);
    exit("Acceso denegado.");
}

// Configuración de API Keys para servicios externos
define('GEMINI_API_KEY', 'TU_API_KEY_DE_GEMINI_AQUI');
