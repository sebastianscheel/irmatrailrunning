<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS strava_tokens (
        alumno_id INT PRIMARY KEY,
        access_token VARCHAR(255) NOT NULL,
        refresh_token VARCHAR(255) NOT NULL,
        expires_at INT NOT NULL,
        fecha_conexion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (alumno_id) REFERENCES alumno_perfil(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    echo "Tabla 'strava_tokens' creada exitosamente.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
