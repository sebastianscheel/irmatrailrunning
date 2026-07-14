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

    $proveedor_ia = $_POST['proveedor_ia'] ?? 'Gemini';
    $api_key = $_POST['api_key'] ?? '';
    $borrar_clave = isset($_POST['borrar_clave']) && $_POST['borrar_clave'] === '1';

    if (empty($disciplina) || empty($rol_entrenador)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios.']);
        exit;
    }

    try {
        // Verificar si existe el registro
        $stmt = $pdo->query("SELECT api_key FROM configuracion_ia LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $exists = $row !== false;

        $final_api_key = $exists ? $row['api_key'] : null;
        if ($borrar_clave) {
            $final_api_key = null;
        } elseif (!empty($api_key)) {
            $final_api_key = $api_key;
        }

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE configuracion_ia SET 
                disciplina = :disciplina, 
                rol_entrenador = :rol_entrenador, 
                tipos_sesion = :tipos_sesion, 
                estructura_descripcion = :estructura_descripcion, 
                tono_respuesta = :tono_respuesta,
                proveedor_ia = :proveedor_ia,
                api_key = :api_key
            ");
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracion_ia (disciplina, rol_entrenador, tipos_sesion, estructura_descripcion, tono_respuesta, proveedor_ia, api_key) VALUES (:disciplina, :rol_entrenador, :tipos_sesion, :estructura_descripcion, :tono_respuesta, :proveedor_ia, :api_key)");
        }

        $stmt->execute([
            ':disciplina' => $disciplina,
            ':rol_entrenador' => $rol_entrenador,
            ':tipos_sesion' => $tipos_sesion,
            ':estructura_descripcion' => $estructura_descripcion,
            ':tono_respuesta' => $tono_respuesta,
            ':proveedor_ia' => $proveedor_ia,
            ':api_key' => $final_api_key
        ]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Error guardando configuración IA: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
