<?php
// includes/audit_helper.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Registra una accion en la tabla de auditoria.
 */
function registrarAuditoria($pdo, $params) {
    try {
        // Valores por defecto desde la sesion si no se proveen
        $usuario_id = $params['usuario_id'] ?? ($_SESSION['user_id'] ?? 0);
        $usuario_nombre = $params['usuario_nombre'] ?? '';
        $usuario_rol = $params['usuario_rol'] ?? ($_SESSION['user_rol'] ?? 'sistema');

        if (empty($usuario_nombre) && $usuario_id > 0) {
            // Recuperar el nombre completo de la base de datos
            $stmt = $pdo->prepare("SELECT nombre, apellido FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $u = $stmt->fetch();
            if ($u) {
                $usuario_nombre = trim($u['nombre'] . ' ' . $u['apellido']);
            }
        }
        
        if (empty($usuario_nombre)) {
            $usuario_nombre = 'Sistema / API';
        }

        $alumno_id = $params['alumno_id'] ?? null;
        $alumno_nombre = $params['alumno_nombre'] ?? null;

        if ($alumno_id && empty($alumno_nombre)) {
            // Buscar nombre del alumno por su alumno_perfil.id
            $stmtA = $pdo->prepare("SELECT u.nombre, u.apellido FROM usuarios u JOIN alumno_perfil p ON p.usuario_id = u.id WHERE p.id = ?");
            $stmtA->execute([$alumno_id]);
            $a = $stmtA->fetch();
            if ($a) {
                $alumno_nombre = trim($a['nombre'] . ' ' . $a['apellido']);
            }
        }

        $accion = $params['accion'] ?? '';
        $entidad = $params['entidad'] ?? '';
        $entidad_id = $params['entidad_id'] ?? null;
        $detalle = $params['detalle'] ?? '';
        
        $datos_anteriores = isset($params['datos_anteriores']) ? json_encode($params['datos_anteriores'], JSON_UNESCAPED_UNICODE) : null;
        $datos_nuevos = isset($params['datos_nuevos']) ? json_encode($params['datos_nuevos'], JSON_UNESCAPED_UNICODE) : null;

        $stmtInsert = $pdo->prepare("
            INSERT INTO audit_log (usuario_id, usuario_nombre, usuario_rol, accion, entidad, entidad_id, alumno_id, alumno_nombre, detalle, datos_anteriores, datos_nuevos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([
            $usuario_id,
            $usuario_nombre,
            $usuario_rol,
            $accion,
            $entidad,
            $entidad_id,
            $alumno_id,
            $alumno_nombre,
            $detalle,
            $datos_anteriores,
            $datos_nuevos
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Error al registrar auditoria: " . $e->getMessage());
        return false;
    }
}

/**
 * Crea una notificacion para un usuario especifico.
 */
function crearNotificacion($pdo, $usuario_id, $titulo, $mensaje, $enlace = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones (usuario_id, titulo, mensaje, enlace, leido)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$usuario_id, $titulo, $mensaje, $enlace]);
        return true;
    } catch (Exception $e) {
        error_log("Error al crear notificacion: " . $e->getMessage());
        return false;
    }
}
