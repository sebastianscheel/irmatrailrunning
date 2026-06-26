<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que sea admin
require_rol(['admin', 'entrenador']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $dni = trim($_POST['dni']);
        $telefono = trim($_POST['telefono']);
        $fecha_nacimiento = trim($_POST['fecha_nacimiento']);
        $plan_tipo = trim($_POST['plan_tipo']);
        $nivel = trim($_POST['nivel']);
        $observaciones_medicas = trim($_POST['observaciones_medicas']);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (empty($nombre) || empty($apellido) || empty($email) || empty($password) || empty($dni) || empty($telefono) || empty($fecha_nacimiento) || empty($plan_tipo)) {
            header("Location: /admin/alumnos.php?error=empty");
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Verificar si el email o el DNI ya existen
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                header("Location: /admin/alumnos.php?error=email_exists");
                exit;
            }

            $stmtCheckDni = $pdo->prepare("SELECT id FROM alumno_perfil WHERE dni = ?");
            $stmtCheckDni->execute([$dni]);
            if ($stmtCheckDni->fetch()) {
                header("Location: /admin/alumnos.php?error=dni_exists");
                exit;
            }

            // 1. Insertar en usuarios
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("
                INSERT INTO usuarios (email, password_hash, nombre, apellido, rol) 
                VALUES (?, ?, ?, ?, 'alumno')
            ");
            $stmtUser->execute([$email, $hash, $nombre, $apellido]);
            $usuario_id = $pdo->lastInsertId();

            // 2. Insertar en alumno_perfil
            $stmtPerfil = $pdo->prepare("
                INSERT INTO alumno_perfil (usuario_id, dni, telefono, fecha_nacimiento, plan_tipo, nivel, observaciones_medicas, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtPerfil->execute([$usuario_id, $dni, $telefono, $fecha_nacimiento, $plan_tipo, $nivel, $observaciones_medicas, $activo]);

            $pdo->commit();
            header("Location: /admin/alumnos.php?msg=create_ok");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al crear alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }

    } elseif ($action === 'edit') {
        $usuario_id = (int)$_POST['usuario_id'];
        $alumno_id = (int)$_POST['alumno_id'];
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $dni = trim($_POST['dni']);
        $telefono = trim($_POST['telefono']);
        $fecha_nacimiento = trim($_POST['fecha_nacimiento']);
        $plan_tipo = trim($_POST['plan_tipo']);
        $nivel = trim($_POST['nivel']);
        $observaciones_medicas = trim($_POST['observaciones_medicas']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $change_password = trim($_POST['password']); // Opcional

        if ($usuario_id <= 0 || $alumno_id <= 0 || empty($nombre) || empty($apellido) || empty($email) || empty($dni) || empty($telefono) || empty($fecha_nacimiento) || empty($plan_tipo)) {
            header("Location: /admin/alumnos.php?error=empty");
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Verificar duplicados de Email excluyendo al propio usuario
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $usuario_id]);
            if ($stmtCheck->fetch()) {
                header("Location: /admin/alumnos.php?error=email_exists");
                exit;
            }

            // Verificar duplicados de DNI excluyendo al propio alumno
            $stmtCheckDni = $pdo->prepare("SELECT id FROM alumno_perfil WHERE dni = ? AND id != ?");
            $stmtCheckDni->execute([$dni, $alumno_id]);
            if ($stmtCheckDni->fetch()) {
                header("Location: /admin/alumnos.php?error=dni_exists");
                exit;
            }

            // 1. Actualizar usuario
            if (!empty($change_password)) {
                $hash = password_hash($change_password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("
                    UPDATE usuarios SET email = ?, password_hash = ?, nombre = ?, apellido = ? WHERE id = ?
                ");
                $stmtUser->execute([$email, $hash, $nombre, $apellido, $usuario_id]);
            } else {
                $stmtUser = $pdo->prepare("
                    UPDATE usuarios SET email = ?, nombre = ?, apellido = ? WHERE id = ?
                ");
                $stmtUser->execute([$email, $nombre, $apellido, $usuario_id]);
            }

            // 2. Actualizar perfil
            $stmtPerfil = $pdo->prepare("
                UPDATE alumno_perfil 
                SET dni = ?, telefono = ?, fecha_nacimiento = ?, plan_tipo = ?, nivel = ?, observaciones_medicas = ?, activo = ? 
                WHERE id = ?
            ");
            $stmtPerfil->execute([$dni, $telefono, $fecha_nacimiento, $plan_tipo, $nivel, $observaciones_medicas, $activo, $alumno_id]);

            $pdo->commit();
            header("Location: /admin/alumnos.php?msg=edit_ok");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error al editar alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }

    } elseif ($action === 'delete') {
        $usuario_id = (int)$_POST['usuario_id'];

        if ($usuario_id <= 0) {
            header("Location: /admin/alumnos.php?error=invalid_delete");
            exit;
        }

        try {
            // Eliminar al usuario. CASCADE eliminarÃ¡ su perfil, rutinas, comprobantes, etc.
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND rol = 'alumno'");
            $stmt->execute([$usuario_id]);

            header("Location: /admin/alumnos.php?msg=delete_ok");
            exit;
        } catch (PDOException $e) {
            error_log("Error al eliminar alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }
    }
} else {
    header("Location: /admin/alumnos.php");
    exit;
}
?>

