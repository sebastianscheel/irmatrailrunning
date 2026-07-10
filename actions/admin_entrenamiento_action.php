<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea entrenador o admin
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$entrenador_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        $tipo_sesion = isset($_POST['tipo_sesion']) ? trim($_POST['tipo_sesion']) : '';
        $terreno = isset($_POST['terreno']) ? trim($_POST['terreno']) : '';
        $distancia_km = isset($_POST['distancia_km']) ? (float)$_POST['distancia_km'] : 0.0;
        $ritmo_sugerido = isset($_POST['ritmo_sugerido']) ? trim($_POST['ritmo_sugerido']) : '';
        $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';

        if (empty($titulo) || empty($tipo_sesion) || empty($terreno) || empty($descripcion)) {
            header("Location: /admin/entrenamientos.php?error=empty");
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO entrenamientos_individuales (entrenador_id, titulo, tipo_sesion, distancia_km, ritmo_sugerido, terreno, descripcion) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$entrenador_id, $titulo, $tipo_sesion, $distancia_km, $ritmo_sugerido, $terreno, $descripcion]);
            header("Location: /admin/entrenamientos.php?msg=created");
            exit;
        } catch (PDOException $e) {
            error_log("Error al crear entrenamiento guardado: " . $e->getMessage());
            header("Location: /admin/entrenamientos.php?error=db");
            exit;
        }
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
        $tipo_sesion = isset($_POST['tipo_sesion']) ? trim($_POST['tipo_sesion']) : '';
        $terreno = isset($_POST['terreno']) ? trim($_POST['terreno']) : '';
        $distancia_km = isset($_POST['distancia_km']) ? (float)$_POST['distancia_km'] : 0.0;
        $ritmo_sugerido = isset($_POST['ritmo_sugerido']) ? trim($_POST['ritmo_sugerido']) : '';
        $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';

        if ($id <= 0 || empty($titulo) || empty($tipo_sesion) || empty($terreno) || empty($descripcion)) {
            header("Location: /admin/entrenamientos.php?error=empty");
            exit;
        }

        try {
            // Validar propiedad del entrenamiento
            $user_rol = $_SESSION['user_rol'];
            if (!in_array($user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
                $stmtCheck = $pdo->prepare("SELECT id FROM entrenamientos_individuales WHERE id = ? AND entrenador_id = ?");
                $stmtCheck->execute([$id, $entrenador_id]);
                if (!$stmtCheck->fetchColumn()) {
                    header("Location: /admin/entrenamientos.php?error=unauthorized");
                    exit;
                }
                $stmtUpdate = $pdo->prepare("
                    UPDATE entrenamientos_individuales 
                    SET titulo = ?, tipo_sesion = ?, distancia_km = ?, ritmo_sugerido = ?, terreno = ?, descripcion = ? 
                    WHERE id = ? AND entrenador_id = ?
                ");
                $stmtUpdate->execute([$titulo, $tipo_sesion, $distancia_km, $ritmo_sugerido, $terreno, $descripcion, $id, $entrenador_id]);
            } else {
                $stmtUpdate = $pdo->prepare("
                    UPDATE entrenamientos_individuales 
                    SET titulo = ?, tipo_sesion = ?, distancia_km = ?, ritmo_sugerido = ?, terreno = ?, descripcion = ? 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$titulo, $tipo_sesion, $distancia_km, $ritmo_sugerido, $terreno, $descripcion, $id]);
            }
            header("Location: /admin/entrenamientos.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            error_log("Error al actualizar entrenamiento guardado: " . $e->getMessage());
            header("Location: /admin/entrenamientos.php?error=db");
            exit;
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            header("Location: /admin/entrenamientos.php?error=empty");
            exit;
        }

        try {
            // Validar propiedad del entrenamiento
            $user_rol = $_SESSION['user_rol'];
            if (!in_array($user_rol, ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
                $stmtCheck = $pdo->prepare("SELECT id FROM entrenamientos_individuales WHERE id = ? AND entrenador_id = ?");
                $stmtCheck->execute([$id, $entrenador_id]);
                if (!$stmtCheck->fetchColumn()) {
                    header("Location: /admin/entrenamientos.php?error=unauthorized");
                    exit;
                }
                $stmtDelete = $pdo->prepare("DELETE FROM entrenamientos_individuales WHERE id = ? AND entrenador_id = ?");
                $stmtDelete->execute([$id, $entrenador_id]);
            } else {
                $stmtDelete = $pdo->prepare("DELETE FROM entrenamientos_individuales WHERE id = ?");
                $stmtDelete->execute([$id]);
            }
            header("Location: /admin/entrenamientos.php?msg=deleted");
            exit;
        } catch (PDOException $e) {
            error_log("Error al eliminar entrenamiento guardado: " . $e->getMessage());
            header("Location: /admin/entrenamientos.php?error=db");
            exit;
        }
    }
} else {
    header("Location: /admin/entrenamientos.php");
    exit;
}
?>
