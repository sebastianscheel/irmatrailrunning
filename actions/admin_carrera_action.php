<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Validar rol
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /admin/carreras.php");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $titulo = trim($_POST['titulo']);
        $fecha = trim($_POST['fecha']);
        $lugar = trim($_POST['lugar']);
        $distancias = trim($_POST['distancias']);
        $url_info = trim($_POST['url_info'] ?? '');

        if (empty($titulo) || empty($fecha) || empty($distancias)) {
            throw new Exception("Los campos marcados con * son obligatorios.");
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO carreras (titulo, fecha, lugar, distancias, url_info) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$titulo, $fecha, $lugar, $distancias, $url_info]);
        $carrera_id = $pdo->lastInsertId();

        $datos_nuevos = [
            'id' => $carrera_id,
            'titulo' => $titulo,
            'fecha' => $fecha,
            'lugar' => $lugar,
            'distancias' => $distancias,
            'url_info' => $url_info
        ];

        // Registrar auditoría
        registrarAuditoria($pdo, [
            'accion' => 'crear_carrera',
            'entidad' => 'carrera',
            'entidad_id' => $carrera_id,
            'detalle' => "Creó la carrera '$titulo' programada para el $fecha.",
            'datos_nuevos' => $datos_nuevos
        ]);

        $pdo->commit();

        header("Location: /admin/carreras.php?msg=" . urlencode("Carrera agregada exitosamente."));
        exit;

    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $titulo = trim($_POST['titulo']);
        $fecha = trim($_POST['fecha']);
        $lugar = trim($_POST['lugar']);
        $distancias = trim($_POST['distancias']);
        $url_info = trim($_POST['url_info'] ?? '');

        if (empty($titulo) || empty($fecha) || empty($distancias) || $id <= 0) {
            throw new Exception("Datos incompletos.");
        }

        // Obtener datos antes de editar
        $stmtPrev = $pdo->prepare("SELECT * FROM carreras WHERE id = ?");
        $stmtPrev->execute([$id]);
        $carrera_prev = $stmtPrev->fetch();

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE carreras SET titulo = ?, fecha = ?, lugar = ?, distancias = ?, url_info = ? WHERE id = ?");
        $stmt->execute([$titulo, $fecha, $lugar, $distancias, $url_info, $id]);

        $datos_nuevos = [
            'id' => $id,
            'titulo' => $titulo,
            'fecha' => $fecha,
            'lugar' => $lugar,
            'distancias' => $distancias,
            'url_info' => $url_info
        ];

        // Registrar auditoría
        registrarAuditoria($pdo, [
            'accion' => 'editar_carrera',
            'entidad' => 'carrera',
            'entidad_id' => $id,
            'detalle' => "Modificó la información de la carrera '$titulo' ($fecha).",
            'datos_anteriores' => $carrera_prev,
            'datos_nuevos' => $datos_nuevos
        ]);

        $pdo->commit();

        header("Location: /admin/carreras.php?msg=" . urlencode("Carrera actualizada exitosamente."));
        exit;

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Obtener datos antes de borrar
        $stmtPrev = $pdo->prepare("SELECT * FROM carreras WHERE id = ?");
        $stmtPrev->execute([$id]);
        $carrera_prev = $stmtPrev->fetch();

        if ($carrera_prev) {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM carreras WHERE id = ?");
            $stmt->execute([$id]);

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'eliminar_carrera',
                'entidad' => 'carrera',
                'entidad_id' => $id,
                'detalle' => "Eliminó la carrera '" . $carrera_prev['titulo'] . "' ($carrera_prev[fecha]).",
                'datos_anteriores' => $carrera_prev
            ]);

            $pdo->commit();
        }

        header("Location: /admin/carreras.php?msg=" . urlencode("Carrera eliminada."));
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: /admin/carreras.php?err=" . urlencode($e->getMessage()));
    exit;
}
