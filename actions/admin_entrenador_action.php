<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Validar que solo admin o entrenador_total puedan gestionar entrenadores
require_rol(['admin', 'entrenador_total']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /admin/entrenadores.php");
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $dni = trim($_POST['dni'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'];
        $rol = $_POST['rol'];

        if (!in_array($rol, ['entrenador_total', 'entrenador_intermedio', 'entrenador_limitado'])) {
            throw new Exception("Rol inválido.");
        }

        // Verificar si el email ya existe
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            throw new Exception("El email ya está registrado.");
        }

        // Verificar si el DNI ya existe
        if (!empty($dni)) {
            $stmtCheckDni = $pdo->prepare("SELECT id FROM usuarios WHERE dni = ?");
            $stmtCheckDni->execute([$dni]);
            if ($stmtCheckDni->fetch()) {
                throw new Exception("El DNI ya está registrado por otro usuario.");
            }
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, telefono, password_hash, rol, dni, debe_cambiar_password) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$nombre, $apellido, $email, $telefono, $hash, $rol, !empty($dni) ? $dni : null]);
        $nuevo_id = $pdo->lastInsertId();

        // Registrar auditoría
        $datos_nuevos = [
            'id' => $nuevo_id,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'telefono' => $telefono,
            'rol' => $rol,
            'dni' => $dni
        ];
        registrarAuditoria($pdo, [
            'accion' => 'crear_entrenador',
            'entidad' => 'usuario',
            'entidad_id' => $nuevo_id,
            'detalle' => "Creó la cuenta de entrenador para $nombre $apellido ($rol).",
            'datos_nuevos' => $datos_nuevos
        ]);

        $pdo->commit();

        header("Location: /admin/entrenadores.php?msg=" . urlencode("Entrenador creado con éxito."));
        exit;

    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $dni = trim($_POST['dni'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $rol = $_POST['rol'];
        $password = $_POST['password'] ?? '';

        if (!in_array($rol, ['entrenador_total', 'entrenador_intermedio', 'entrenador_limitado'])) {
            throw new Exception("Rol inválido.");
        }

        // Verificar si el email pertenece a otro usuario
        $stmtTarget = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
        $stmtTarget->execute([$id]);
        $target_rol = $stmtTarget->fetchColumn();

        if ($target_rol === 'admin' && $_SESSION['user_rol'] !== 'admin') {
            throw new Exception("No tienes permiso para modificar al Administrador.");
        }

        if ($id === $_SESSION['user_id'] && $_SESSION['user_rol'] !== 'admin' && !empty($password)) {
            throw new Exception("Solo el Administrador puede modificar su propia contraseña desde el panel.");
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmtCheck->execute([$email, $id]);
        if ($stmtCheck->fetch()) {
            throw new Exception("El email ya está siendo usado por otro usuario.");
        }

        // Verificar si el DNI pertenece a otro usuario
        if (!empty($dni)) {
            $stmtCheckDni = $pdo->prepare("SELECT id FROM usuarios WHERE dni = ? AND id != ?");
            $stmtCheckDni->execute([$dni, $id]);
            if ($stmtCheckDni->fetch()) {
                throw new Exception("El DNI ya está siendo usado por otro usuario.");
            }
        }

        // Obtener datos antes de editar
        $stmtPrev = $pdo->prepare("SELECT id, nombre, apellido, email, telefono, rol, dni FROM usuarios WHERE id = ?");
        $stmtPrev->execute([$id]);
        $entrenador_prev = $stmtPrev->fetch();

        $pdo->beginTransaction();

        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, telefono = ?, rol = ?, password_hash = ?, dni = ? WHERE id = ?");
            $stmt->execute([$nombre, $apellido, $email, $telefono, $rol, $hash, !empty($dni) ? $dni : null, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, telefono = ?, rol = ?, dni = ? WHERE id = ?");
            $stmt->execute([$nombre, $apellido, $email, $telefono, $rol, !empty($dni) ? $dni : null, $id]);
        }

        $datos_nuevos = [
            'id' => $id,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'telefono' => $telefono,
            'rol' => $rol,
            'dni' => $dni
        ];

        // Registrar auditoría
        registrarAuditoria($pdo, [
            'accion' => 'editar_entrenador',
            'entidad' => 'usuario',
            'entidad_id' => $id,
            'detalle' => "Modificó la información del perfil del entrenador $nombre $apellido.",
            'datos_anteriores' => $entrenador_prev,
            'datos_nuevos' => $datos_nuevos
        ]);

        $pdo->commit();

        header("Location: /admin/entrenadores.php?msg=" . urlencode("Entrenador actualizado con éxito."));
        exit;

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Evitar que el admin se borre a sí mismo
        if ($id === $_SESSION['user_id']) {
            throw new Exception("No puedes eliminar tu propia cuenta.");
        }

        // Obtener datos antes de borrar
        $stmtTarget = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
        $stmtTarget->execute([$id]);
        $target_rol = $stmtTarget->fetchColumn();

        if ($target_rol === 'admin') {
            throw new Exception("No se puede eliminar al Administrador.");
        }

        $stmtPrev = $pdo->prepare("SELECT id, nombre, apellido, email, telefono, rol, dni FROM usuarios WHERE id = ?");
        $stmtPrev->execute([$id]);
        $entrenador_prev = $stmtPrev->fetch();

        $pdo->beginTransaction();

        // Primero ponemos en NULL el entrenador_id de sus alumnos
        $stmtUpdateAlumnos = $pdo->prepare("UPDATE alumno_perfil SET entrenador_id = NULL WHERE entrenador_id = ?");
        $stmtUpdateAlumnos->execute([$id]);

        // Luego eliminamos al usuario
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND rol IN ('entrenador_total', 'entrenador_intermedio', 'entrenador_limitado')");
        $stmt->execute([$id]);

        $nombre_completo = ($entrenador_prev['nombre'] ?? '') . ' ' . ($entrenador_prev['apellido'] ?? '');

        // Registrar auditoría
        registrarAuditoria($pdo, [
            'accion' => 'eliminar_entrenador',
            'entidad' => 'usuario',
            'entidad_id' => $id,
            'detalle' => "Eliminó la cuenta del entrenador $nombre_completo.",
            'datos_anteriores' => $entrenador_prev
        ]);

        $pdo->commit();

        header("Location: /admin/entrenadores.php?msg=" . urlencode("Entrenador eliminado con éxito."));
        exit;

    } elseif ($action === 'reset_password') {
        $id = (int)$_POST['id'];

        if ($id <= 0) {
            throw new Exception("Entrenador no válido.");
        }

        // Obtener datos del entrenador
        $stmtTarget = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
        $stmtTarget->execute([$id]);
        $target_rol = $stmtTarget->fetchColumn();

        if ($target_rol === 'admin') {
            throw new Exception("No se puede restablecer la contraseña del Administrador.");
        }

        if ($_SESSION['user_rol'] !== 'admin') {
            throw new Exception("Solo el Administrador puede restablecer contraseñas de entrenadores.");
        }

        $stmtPrev = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE id = ?");
        $stmtPrev->execute([$id]);
        $usr = $stmtPrev->fetch();
        $nombre_completo = $usr ? ($usr['nombre'] . ' ' . $usr['apellido']) : 'Entrenador';

        $hash = password_hash('123456', PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ? AND rol IN ('entrenador_total', 'entrenador_intermedio', 'entrenador_limitado')");
        $stmt->execute([$hash, $id]);

        // Registrar auditoría
        registrarAuditoria($pdo, [
            'accion' => 'reset_password_entrenador',
            'entidad' => 'usuario',
            'entidad_id' => $id,
            'detalle' => "Restableció la contraseña del entrenador $nombre_completo a '123456' por defecto."
        ]);

        // Notificar al entrenador
        crearNotificacion($pdo, $id, "Contraseña Restablecida", "Tu administrador restableció tu contraseña a '123456' por defecto. Por seguridad, cámbiala de inmediato en tu perfil.", "/logout.php");

        $pdo->commit();

        header("Location: /admin/entrenadores.php?msg=" . urlencode("Contraseña de entrenador restablecida a '123456' con éxito."));
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: /admin/entrenadores.php?err=" . urlencode($e->getMessage()));
    exit;
}
