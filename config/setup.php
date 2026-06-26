<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

try {
    echo "<h2>Iniciando la configuración de la Base de Datos MySQL...</h2>";

    // 1. Tabla Usuarios
    $sqlUsuarios = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        foto_perfil_url VARCHAR(255) DEFAULT NULL,
        rol ENUM('admin', 'entrenador', 'alumno') NOT NULL DEFAULT 'alumno',
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sqlUsuarios);
    echo "✔ Tabla 'usuarios' creada o verificada.<br>";

    // 2. Tabla Alumno Perfil
    $sqlPerfil = "
    CREATE TABLE IF NOT EXISTS alumno_perfil (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNIQUE NOT NULL,
        dni VARCHAR(20) UNIQUE NOT NULL,
        telefono VARCHAR(50) NOT NULL,
        fecha_nacimiento DATE NOT NULL,
        plan_tipo VARCHAR(100) NOT NULL,
        nivel ENUM('Principiante', 'Intermedio', 'Avanzado', 'Elite') NOT NULL DEFAULT 'Principiante',
        observaciones_medicas TEXT,
        activo TINYINT(1) NOT NULL DEFAULT 0,
        ddjj_aceptada TINYINT(1) NOT NULL DEFAULT 0,
        ddjj_fecha_aceptacion DATETIME DEFAULT NULL,
        certificado_medico_url VARCHAR(255) DEFAULT NULL,
        certificado_medico_estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
        certificado_medico_comentario TEXT DEFAULT NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sqlPerfil);
    echo "✔ Tabla 'alumno_perfil' creada o verificada.<br>";

    // 3. Tabla Pagos
    $sqlPagos = "
    CREATE TABLE IF NOT EXISTS pago_registro (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        mes_pagado VARCHAR(7) NOT NULL, /* Formato: YYYY-MM */
        monto DECIMAL(10,2) NOT NULL,
        comprobante_url VARCHAR(255) NOT NULL,
        estado ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
        fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_aprobacion DATETIME DEFAULT NULL,
        FOREIGN KEY (alumno_id) REFERENCES alumno_perfil(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sqlPagos);
    echo "✔ Tabla 'pago_registro' creada o verificada.<br>";

    // 4. Tabla Rutinas Asignadas
    $sqlRutinas = "
    CREATE TABLE IF NOT EXISTS rutina_asignada (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        fecha DATE NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        descripcion TEXT NOT NULL,
        tipo_sesion ENUM('Fuerza', 'Pasadas', 'Fondo', 'Regenerativo', 'Descanso') NOT NULL,
        distancia_km DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        ritmo_sugerido VARCHAR(50) DEFAULT NULL,
        terreno VARCHAR(50) NOT NULL DEFAULT 'Sendero',
        completada TINYINT(1) NOT NULL DEFAULT 0,
        feedback_tiempo_minutos DECIMAL(5,2) DEFAULT NULL,
        feedback_esfuerzo INT DEFAULT NULL, /* 1 al 10 de Borg */
        feedback_comentario TEXT DEFAULT NULL,
        fecha_registro_feedback DATETIME DEFAULT NULL,
        FOREIGN KEY (alumno_id) REFERENCES alumno_perfil(id) ON DELETE CASCADE,
        INDEX idx_alumno_fecha (alumno_id, fecha)
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sqlRutinas);
    echo "✔ Tabla 'rutina_asignada' creada o verificada.<br>";

    // 5. Tabla Contenido Recurso (Videos, PDF, etc)
    $sqlRecursos = "
    CREATE TABLE IF NOT EXISTS contenido_recurso (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(150) NOT NULL,
        tipo ENUM('video', 'documento', 'enlace') NOT NULL,
        url VARCHAR(255) NOT NULL,
        descripcion TEXT,
        fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sqlRecursos);
    echo "✔ Tabla 'contenido_recurso' creada o verificada.<br>";

    // 6. Tabla Contenido Asignado (Relación N:M de recursos a alumnos)
    $sqlContenidoAsignado = "
    CREATE TABLE IF NOT EXISTS contenido_asignado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        recurso_id INT NOT NULL,
        fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (alumno_id) REFERENCES alumno_perfil(id) ON DELETE CASCADE,
        FOREIGN KEY (recurso_id) REFERENCES contenido_recurso(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sqlContenidoAsignado);
    echo "✔ Tabla 'contenido_asignado' creada o verificada.<br>";

    // 7. Crear directorios de subida si no existen
    $dirs = [
        __DIR__ . '/../uploads/certificados',
        __DIR__ . '/../uploads/comprobantes'
    ];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            echo "✔ Directorio creado: " . htmlspecialchars($dir) . "<br>";
        }
    }

    // 8. Crear Entrenador/Administrador Inicial por Defecto
    $adminEmail = 'admin@ibtrailrunning.com';
    $adminPass = 'admin123';
    
    // Verificamos si ya existe el usuario admin
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmtInsert = $pdo->prepare("
            INSERT INTO usuarios (email, password_hash, nombre, apellido, rol) 
            VALUES (?, ?, 'Admin', 'Entrenador', 'admin')
        ");
        $stmtInsert->execute([$adminEmail, $hash]);
        echo "<br><strong>✔ Administrador Inicial Creado con éxito:</strong><br>";
        echo "Email: <code>" . $adminEmail . "</code><br>";
        echo "Contraseña: <code>" . $adminPass . "</code><br>";
        echo "<em>Por favor, cambia esta contraseña una vez que inicies sesión por seguridad.</em><br>";
    } else {
        echo "<br>ℹ El usuario Administrador ya existe en la base de datos.<br>";
    }

    echo "<br><h3 style='color: green;'>¡Configuración de Base de Datos completada con éxito!</h3>";
    echo "<p><a href='/login.php'>Ir al Login de la aplicación</a></p>";

} catch (PDOException $e) {
    die("<br><h3 style='color: red;'>Error durante la instalación:</h3>" . $e->getMessage());
}
?>
