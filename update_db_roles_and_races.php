<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/db.php';

try {
    echo "<h2>Actualizando Base de Datos...</h2>";

    // 1. Modificar tabla usuarios (añadir telefono y cambiar enum)
    $sql1 = "ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(50) DEFAULT NULL";
    try {
        $pdo->exec($sql1);
        echo "✔ Columna 'telefono' añadida a 'usuarios'.<br>";
    } catch (PDOException $e) {
        echo "ℹ Columna 'telefono' ya existe o error: " . $e->getMessage() . "<br>";
    }

    $sql2 = "ALTER TABLE usuarios MODIFY COLUMN rol ENUM('admin', 'entrenador_total', 'entrenador_limitado', 'alumno') NOT NULL DEFAULT 'alumno'";
    try {
        $pdo->exec($sql2);
        echo "✔ ENUM 'rol' modificado en 'usuarios'.<br>";
    } catch (PDOException $e) {
        echo "ℹ Error al modificar 'rol': " . $e->getMessage() . "<br>";
    }

    // 2. Actualizar usuarios existentes (entrenador -> entrenador_total)
    $sql3 = "UPDATE usuarios SET rol = 'entrenador_total' WHERE rol = 'entrenador'";
    $pdo->exec($sql3);
    echo "✔ Usuarios migrados a 'entrenador_total'.<br>";

    // 3. Añadir entrenador_id a alumno_perfil
    $sql4 = "ALTER TABLE alumno_perfil ADD COLUMN entrenador_id INT DEFAULT NULL";
    try {
        $pdo->exec($sql4);
        echo "✔ Columna 'entrenador_id' añadida a 'alumno_perfil'.<br>";
    } catch (PDOException $e) {
        echo "ℹ Columna 'entrenador_id' ya existe.<br>";
    }

    $sql5 = "ALTER TABLE alumno_perfil ADD CONSTRAINT fk_entrenador FOREIGN KEY (entrenador_id) REFERENCES usuarios(id) ON DELETE SET NULL";
    try {
        $pdo->exec($sql5);
        echo "✔ Foreign Key 'fk_entrenador' añadida.<br>";
    } catch (PDOException $e) {
        echo "ℹ Foreign Key ya existe o no se pudo agregar.<br>";
    }

    // 4. Crear tabla carreras
    $sql6 = "
    CREATE TABLE IF NOT EXISTS carreras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(150) NOT NULL,
        fecha DATE NOT NULL,
        lugar VARCHAR(150),
        distancias VARCHAR(100),
        url_info VARCHAR(255),
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sql6);
    echo "✔ Tabla 'carreras' creada.<br>";

    // 5. Crear tabla alumno_carrera
    $sql7 = "
    CREATE TABLE IF NOT EXISTS alumno_carrera (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alumno_id INT NOT NULL,
        carrera_id INT NOT NULL,
        objetivo VARCHAR(255),
        distancia_elegida VARCHAR(50),
        fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (alumno_id) REFERENCES alumno_perfil(id) ON DELETE CASCADE,
        FOREIGN KEY (carrera_id) REFERENCES carreras(id) ON DELETE CASCADE,
        UNIQUE(alumno_id, carrera_id)
    ) ENGINE=InnoDB;
    ";
    $pdo->exec($sql7);
    echo "✔ Tabla 'alumno_carrera' creada.<br>";

    echo "<h3 style='color:green;'>¡Actualización completada!</h3>";

} catch (PDOException $e) {
    die("<br><h3 style='color:red;'>Error Crítico:</h3> " . $e->getMessage());
}
?>
