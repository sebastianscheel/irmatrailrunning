<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';

try {
    echo "<h2>Ejecutando Migración de Mejoras - IB Trailrunning</h2>";

    // 1. Modificar ENUM de rol en usuarios
    try {
        $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado', 'alumno') NOT NULL");
        echo "✔ Columna 'rol' en tabla 'usuarios' actualizada (rol 'entrenador_intermedio' agregado).<br>";
    } catch (PDOException $e) {
        echo "ℹ Error al modificar 'rol' en usuarios: " . $e->getMessage() . "<br>";
    }

    // 2. Agregar columna sexo en alumno_perfil
    try {
        $pdo->exec("ALTER TABLE alumno_perfil ADD COLUMN sexo ENUM('M', 'F') DEFAULT NULL");
        echo "✔ Columna 'sexo' añadida a 'alumno_perfil'.<br>";
    } catch (PDOException $e) {
        echo "ℹ Columna 'sexo' ya existe o no pudo ser agregada: " . $e->getMessage() . "<br>";
    }

    // 3. Agregar columnas para tutor legal (menores de edad) en alumno_perfil
    $columnas_tutor = [
        'tutor_nombre' => "VARCHAR(150) DEFAULT NULL",
        'tutor_dni' => "VARCHAR(50) DEFAULT NULL",
        'tutor_parentesco' => "VARCHAR(50) DEFAULT NULL"
    ];
    foreach ($columnas_tutor as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE alumno_perfil ADD COLUMN $col $def");
            echo "✔ Columna '$col' añadida a 'alumno_perfil'.<br>";
        } catch (PDOException $e) {
            echo "ℹ Columna '$col' ya existe o no pudo ser agregada: " . $e->getMessage() . "<br>";
        }
    }

    // 4. Modificar tipo_sesion ENUM en rutina_asignada
    try {
        $pdo->exec("ALTER TABLE rutina_asignada MODIFY COLUMN tipo_sesion ENUM('Aeróbico', 'Bici', 'Cambios de Ritmo', 'Cuestas', 'Fondo', 'Mixto', 'Pasadas', 'Fuerza', 'Regenerativo', 'Descanso') NOT NULL");
        echo "✔ Columna 'tipo_sesion' en tabla 'rutina_asignada' actualizada.<br>";
    } catch (PDOException $e) {
        echo "ℹ Error al modificar 'tipo_sesion' en rutina_asignada: " . $e->getMessage() . "<br>";
    }

    // 5. Modificar tipo_sesion ENUM en plantilla_rutinas
    try {
        $pdo->exec("ALTER TABLE plantilla_rutinas MODIFY COLUMN tipo_sesion ENUM('Aeróbico', 'Bici', 'Cambios de Ritmo', 'Cuestas', 'Fondo', 'Mixto', 'Pasadas', 'Fuerza', 'Regenerativo', 'Descanso') NOT NULL");
        echo "✔ Columna 'tipo_sesion' en tabla 'plantilla_rutinas' actualizada.<br>";
    } catch (PDOException $e) {
        echo "ℹ Error al modificar 'tipo_sesion' en plantilla_rutinas: " . $e->getMessage() . "<br>";
    }

    // 6. Crear tabla de entrenamientos individuales
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS entrenamientos_individuales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entrenador_id INT NOT NULL,
                titulo VARCHAR(150) NOT NULL,
                tipo_sesion VARCHAR(50) NOT NULL,
                distancia_km DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                ritmo_sugerido VARCHAR(50) DEFAULT NULL,
                terreno VARCHAR(50) NOT NULL,
                descripcion TEXT NOT NULL,
                FOREIGN KEY (entrenador_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB;
        ");
        echo "✔ Tabla 'entrenamientos_individuales' creada correctamente.<br>";
    } catch (PDOException $e) {
        echo "ℹ Error al crear tabla 'entrenamientos_individuales': " . $e->getMessage() . "<br>";
    }

    // 7. Crear tabla de feedback mensual del entrenador
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS feedback_mensual (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alumno_id INT NOT NULL,
                entrenador_id INT NOT NULL,
                mes VARCHAR(7) NOT NULL, -- Formato 'YYYY-MM'
                comentario TEXT NOT NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (alumno_id) REFERENCES alumno_perfil(id) ON DELETE CASCADE,
                FOREIGN KEY (entrenador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                UNIQUE KEY unique_alumno_mes (alumno_id, mes)
            ) ENGINE=InnoDB;
        ");
        echo "✔ Tabla 'feedback_mensual' creada correctamente.<br>";
    } catch (PDOException $e) {
        echo "ℹ Error al crear tabla 'feedback_mensual': " . $e->getMessage() . "<br>";
    }

    echo "<h3 style='color:green;'>¡Migración finalizada con éxito!</h3>";

} catch (PDOException $e) {
    die("<br><h3 style='color:red;'>Error Crítico general de base de datos:</h3> " . $e->getMessage());
}
?>
