<?php
require_once __DIR__ . '/config/db.php';

try {
    // 1. Crear tabla audit_log
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        usuario_nombre VARCHAR(150) NOT NULL,
        usuario_rol VARCHAR(50) NOT NULL,
        accion VARCHAR(50) NOT NULL,
        entidad VARCHAR(50) NOT NULL,
        entidad_id INT NULL,
        alumno_id INT NULL,
        alumno_nombre VARCHAR(150) NULL,
        detalle TEXT NOT NULL,
        datos_anteriores JSON NULL,
        datos_nuevos JSON NULL,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(usuario_id),
        INDEX(alumno_id),
        INDEX(fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabla 'audit_log' creada/verificada.\n";

    // 2. Crear tabla notificaciones
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        mensaje TEXT NOT NULL,
        enlace VARCHAR(255) NULL,
        leido TINYINT DEFAULT 0,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX(usuario_id),
        INDEX(leido)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabla 'notificaciones' creada/verificada.\n";

} catch (PDOException $e) {
    die("Error en migracion: " . $e->getMessage() . "\n");
}
