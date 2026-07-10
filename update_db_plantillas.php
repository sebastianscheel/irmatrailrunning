<?php
require_once __DIR__ . '/config/db.php';
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entrenador_id INT NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        descripcion TEXT,
        duracion_dias INT NOT NULL DEFAULT 7,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (entrenador_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS plantilla_rutinas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plantilla_id INT NOT NULL,
        dia_offset INT NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        descripcion TEXT NOT NULL,
        tipo_sesion ENUM('Fuerza', 'Pasadas', 'Fondo', 'Regenerativo', 'Descanso') NOT NULL,
        distancia_km DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        ritmo_sugerido VARCHAR(50) DEFAULT NULL,
        terreno VARCHAR(50) NOT NULL DEFAULT 'Sendero',
        FOREIGN KEY (plantilla_id) REFERENCES plantillas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ");

    echo "Tablas de plantillas creadas exitosamente.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
