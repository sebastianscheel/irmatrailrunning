<?php
// actions/notificaciones_action.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$usuario_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    try {
        if ($action === 'marcar_leida') {
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$id, $usuario_id]);
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
        } elseif ($action === 'marcar_todas') {
            $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'eliminar_todas') {
            $stmt = $pdo->prepare("UPDATE notificaciones SET eliminada = 1 WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Acción inválida']);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}
