<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

require_rol(['admin', 'entrenador_total']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disciplina = $_POST['disciplina'] ?? '';
    $rol_entrenador = $_POST['rol_entrenador'] ?? '';
    $tipos_sesion = $_POST['tipos_sesion'] ?? '';
    $estructura_descripcion = $_POST['estructura_descripcion'] ?? '';
    $tono_respuesta = $_POST['tono_respuesta'] ?? '';

    if (empty($disciplina) || empty($rol_entrenador)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios.']);
        exit;
    }

    try {
        // Verificar si existe el registro
        $stmt = $pdo->query("SELECT COUNT(*) FROM configuracion_ia");
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE configuracion_ia SET 
                disciplina = :disciplina, 
                rol_entrenador = :rol_entrenador, 
                tipos_sesion = :tipos_sesion, 
                estructura_descripcion = :estructura_descripcion, 
                tono_respuesta = :tono_respuesta
            ");
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracion_ia (disciplina, rol_entrenador, tipos_sesion, estructura_descripcion, tono_respuesta) VALUES (:disciplina, :rol_entrenador, :tipos_sesion, :estructura_descripcion, :tono_respuesta)");
        }

        $stmt->execute([
            ':disciplina' => $disciplina,
            ':rol_entrenador' => $rol_entrenador,
            ':tipos_sesion' => $tipos_sesion,
            ':estructura_descripcion' => $estructura_descripcion,
            ':tono_respuesta' => $tono_respuesta
        ]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Error guardando configuración IA: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
