<?php
require 'config/db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion_ia (
        id INT AUTO_INCREMENT PRIMARY KEY,
        disciplina VARCHAR(100) NOT NULL DEFAULT 'Trail Running',
        rol_entrenador VARCHAR(255) NOT NULL DEFAULT 'preparador físico experto',
        tipos_sesion TEXT,
        estructura_descripcion TEXT,
        tono_respuesta VARCHAR(100) DEFAULT 'Profesional y motivador',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM configuracion_ia");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO configuracion_ia (disciplina, rol_entrenador, tipos_sesion, estructura_descripcion, tono_respuesta) VALUES (
            'Trail Running',
            'preparador físico experto',
            'Fondo|Fuerza|Pasadas|Regenerativo|Cuestas',
            'Entrada en calor:\nBloque principal:\nVuelta a la calma:',
            'Profesional y motivador'
        )");
        echo "Table created and default row inserted.\n";
    } else {
        echo "Table already exists and has data.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
