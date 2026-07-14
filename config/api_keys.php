<?php
// Evitar acceso directo
if (count(get_included_files()) == 1) {
    http_response_code(403);
    exit("Acceso denegado.");
}

// Función simple para cargar variables de entorno
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Configuración de API Keys para servicios externos
$geminiKey = getenv('GEMINI_API_KEY') ?: 'TU_API_KEY_DE_GEMINI_AQUI';
define('GEMINI_API_KEY', $geminiKey);
