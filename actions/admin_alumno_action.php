<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/audit_helper.php';

// Validar que sea admin o entrenador
require_rol(['admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado']);

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action === 'download_template') {
    if (!in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
        header("Location: /admin/alumnos.php?error=unauthorized");
        exit;
    }
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $filename = "plantilla_alumnos.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    // Opcional: BOM para que Excel detecte UTF-8
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Nombre', 'Apellido', 'DNI', 'Email', 'Telefono', 'FechaNacimiento_YYYY_MM_DD', 'Sexo_M_F', 'Nivel', 'TipoPlan', 'DniEntrenador']);
    fputcsv($output, ['Juan', 'Perez', '12345678', 'juan.perez@ejemplo.com', '1122334455', '1990-05-15', 'M', 'Principiante', 'A Distancia', '87654321']);
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'toggle_active_ajax') {
        header('Content-Type: application/json');
        $alumno_id = isset($_POST['alumno_id']) ? (int)$_POST['alumno_id'] : 0;
        $is_checked = isset($_POST['checked']) ? (int)$_POST['checked'] : 0;

        if ($alumno_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de alumno no válido.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT activo, usuario_id FROM alumno_perfil WHERE id = ?");
            $stmt->execute([$alumno_id]);
            $perfil = $stmt->fetch();

            if (!$perfil) {
                echo json_encode(['success' => false, 'error' => 'Alumno no encontrado.']);
                exit;
            }

            $current_state = (int)$perfil['activo'];
            $new_state = 0;

            if ($is_checked === 1) {
                if ($current_state === 0) {
                    $new_state = 1;
                } elseif ($current_state === 2) {
                    $new_state = 3;
                } else {
                    $new_state = $current_state; 
                }
            } else {
                if ($current_state === 1) {
                    $new_state = 0;
                } elseif ($current_state === 3) {
                    $new_state = 2;
                } else {
                    $new_state = $current_state;
                }
            }

            $stmtUpdate = $pdo->prepare("UPDATE alumno_perfil SET activo = ? WHERE id = ?");
            $stmtUpdate->execute([$new_state, $alumno_id]);

            registrarAuditoria($pdo, [
                'accion' => 'toggle_activo_ajax',
                'entidad' => 'alumno_perfil',
                'entidad_id' => $alumno_id,
                'detalle' => "Cambió estado de actividad rápida del alumno de $current_state a $new_state.",
                'datos_anteriores' => ['activo' => $current_state],
                'datos_nuevos' => ['activo' => $new_state]
            ]);

            $badge_html = '';
            if ($new_state == 1) {
                $badge_html = '<span class="badge bg-success bg-opacity-25 text-success border border-success"><i class="fa-solid fa-check-circle me-1"></i>Activo</span>';
            } elseif ($new_state == 2) {
                $badge_html = '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>Falta de pago</span>';
            } elseif ($new_state == 3) {
                $badge_html = '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning"><i class="fa-solid fa-clock me-1"></i>Activo, falta pago</span>';
            } else {
                $badge_html = '<span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary"><i class="fa-solid fa-times-circle me-1"></i>Inactivo</span>';
            }

            echo json_encode([
                'success' => true, 
                'new_state' => $new_state,
                'badge_html' => $badge_html
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'create') {
        if (!in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
            header("Location: /admin/alumnos.php?error=unauthorized");
            exit;
        }
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $email = trim($_POST['email']);
        $password = isset($_POST['password']) ? trim($_POST['password']) : trim($_POST['dni']);
        $dni = trim($_POST['dni']);
        $telefono = trim($_POST['telefono']);
        $fecha_nacimiento = trim($_POST['fecha_nacimiento']);
        $plan_tipo = trim($_POST['plan_tipo']);
        $nivel = trim($_POST['nivel']);
        $observaciones_medicas = trim($_POST['observaciones_medicas']);
        $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;
        $entrenador_id = !empty($_POST['entrenador_id']) ? (int)$_POST['entrenador_id'] : null;
        $sexo = isset($_POST['sexo']) ? trim($_POST['sexo']) : 'M';

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

            $stmtCheckDni = $pdo->prepare("SELECT id FROM usuarios WHERE dni = ?");
            $stmtCheckDni->execute([$dni]);
            if ($stmtCheckDni->fetch()) {
                header("Location: /admin/alumnos.php?error=dni_exists");
                exit;
            }

            // 1. Insertar en usuarios (con DNI y debe_cambiar_password = 1)
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("
                INSERT INTO usuarios (email, password_hash, nombre, apellido, rol, dni, debe_cambiar_password) 
                VALUES (?, ?, ?, ?, 'alumno', ?, 1)
            ");
            $stmtUser->execute([$email, $hash, $nombre, $apellido, $dni]);
            $usuario_id = $pdo->lastInsertId();

            // 2. Insertar en alumno_perfil (sin dni)
            $stmtPerfil = $pdo->prepare("
                INSERT INTO alumno_perfil (usuario_id, telefono, fecha_nacimiento, plan_tipo, nivel, observaciones_medicas, activo, entrenador_id, sexo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtPerfil->execute([$usuario_id, $telefono, $fecha_nacimiento, $plan_tipo, $nivel, $observaciones_medicas, $activo, $entrenador_id, $sexo]);
            $alumno_id = $pdo->lastInsertId();

            // Registrar auditoría
            $datos_nuevos = [
                'usuario_id' => $usuario_id,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'dni' => $dni,
                'telefono' => $telefono,
                'fecha_nacimiento' => $fecha_nacimiento,
                'plan_tipo' => $plan_tipo,
                'nivel' => $nivel,
                'observaciones_medicas' => $observaciones_medicas,
                'activo' => $activo,
                'entrenador_id' => $entrenador_id,
                'sexo' => $sexo
            ];
            registrarAuditoria($pdo, [
                'accion' => 'crear_alumno',
                'entidad' => 'usuario',
                'entidad_id' => $usuario_id,
                'alumno_id' => $alumno_id,
                'detalle' => "Creó la cuenta del alumno $nombre $apellido.",
                'datos_nuevos' => $datos_nuevos
            ]);

            $pdo->commit();
            header("Location: /admin/alumnos.php?msg=create_ok");
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al crear alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }

    } elseif ($action === 'edit') {
        if (!in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
            header("Location: /admin/alumnos.php?error=unauthorized");
            exit;
        }
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
        $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;
        $entrenador_id = !empty($_POST['entrenador_id']) ? (int)$_POST['entrenador_id'] : null;
        $sexo = isset($_POST['sexo']) ? trim($_POST['sexo']) : 'M';
        $change_password = trim($_POST['password']); // Opcional

        if ($usuario_id <= 0 || $alumno_id <= 0 || empty($nombre) || empty($apellido) || empty($email) || empty($dni) || empty($telefono) || empty($fecha_nacimiento) || empty($plan_tipo)) {
            header("Location: /admin/alumnos.php?error=empty");
            exit;
        }

        try {
            // Respaldar datos anteriores del alumno
            $stmtUserPrev = $pdo->prepare("SELECT email, nombre, apellido, dni, rol FROM usuarios WHERE id = ?");
            $stmtUserPrev->execute([$usuario_id]);
            $u_prev = $stmtUserPrev->fetch() ?: [];

            $stmtPerfilPrev = $pdo->prepare("SELECT * FROM alumno_perfil WHERE id = ?");
            $stmtPerfilPrev->execute([$alumno_id]);
            $p_prev = $stmtPerfilPrev->fetch() ?: [];

            $datos_anteriores = array_merge($u_prev, $p_prev);

            // Si no es admin ni entrenador total, no puede cambiar el estado activo
            if (!in_array($_SESSION['user_rol'], ['admin', 'entrenador_total'])) {
                $activo = (int)($p_prev['activo'] ?? 1);
            }

            $pdo->beginTransaction();

            // Verificar duplicados de Email excluyendo al propio usuario
            $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $usuario_id]);
            if ($stmtCheck->fetch()) {
                header("Location: /admin/alumnos.php?error=email_exists");
                exit;
            }

            // Verificar duplicados de DNI excluyendo al propio alumno (en usuarios)
            $stmtCheckDni = $pdo->prepare("SELECT id FROM usuarios WHERE dni = ? AND id != ?");
            $stmtCheckDni->execute([$dni, $usuario_id]);
            if ($stmtCheckDni->fetch()) {
                header("Location: /admin/alumnos.php?error=dni_exists");
                exit;
            }

            // 1. Actualizar usuario (incluyendo DNI)
            if (!empty($change_password)) {
                $hash = password_hash($change_password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("
                    UPDATE usuarios SET email = ?, password_hash = ?, nombre = ?, apellido = ?, dni = ? WHERE id = ?
                ");
                $stmtUser->execute([$email, $hash, $nombre, $apellido, $dni, $usuario_id]);
            } else {
                $stmtUser = $pdo->prepare("
                    UPDATE usuarios SET email = ?, nombre = ?, apellido = ?, dni = ? WHERE id = ?
                ");
                $stmtUser->execute([$email, $nombre, $apellido, $dni, $usuario_id]);
            }

            // 2. Actualizar perfil (sin columna DNI)
            $stmtPerfil = $pdo->prepare("
                UPDATE alumno_perfil 
                SET telefono = ?, fecha_nacimiento = ?, plan_tipo = ?, nivel = ?, observaciones_medicas = ?, activo = ?, entrenador_id = ?, sexo = ? 
                WHERE id = ?
            ");
            $stmtPerfil->execute([$telefono, $fecha_nacimiento, $plan_tipo, $nivel, $observaciones_medicas, $activo, $entrenador_id, $sexo, $alumno_id]);

            $datos_nuevos = [
                'email' => $email,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'dni' => $dni,
                'telefono' => $telefono,
                'fecha_nacimiento' => $fecha_nacimiento,
                'plan_tipo' => $plan_tipo,
                'nivel' => $nivel,
                'observaciones_medicas' => $observaciones_medicas,
                'activo' => $activo,
                'entrenador_id' => $entrenador_id,
                'sexo' => $sexo
            ];

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'editar_alumno',
                'entidad' => 'usuario',
                'entidad_id' => $usuario_id,
                'alumno_id' => $alumno_id,
                'detalle' => "Actualizó la información de perfil del alumno $nombre $apellido.",
                'datos_anteriores' => $datos_anteriores,
                'datos_nuevos' => $datos_nuevos
            ]);

            $pdo->commit();
            header("Location: /admin/alumnos.php?msg=edit_ok");
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al editar alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }

    } elseif ($action === 'reset_password') {
        $usuario_id = (int)$_POST['usuario_id'];

        if ($usuario_id <= 0) {
            header("Location: /admin/alumnos.php?error=invalid");
            exit;
        }

        try {
            // Obtener DNI del alumno para usarlo como clave por defecto (desde usuarios)
            $stmtDni = $pdo->prepare("SELECT id, dni, nombre, apellido FROM usuarios WHERE id = ?");
            $stmtDni->execute([$usuario_id]);
            $usr = $stmtDni->fetch();

            if (!$usr) {
                header("Location: /admin/alumnos.php?error=db");
                exit;
            }

            $dni = $usr['dni'];
            $nombre_completo = $usr['nombre'] . ' ' . $usr['apellido'];

            // Obtener el alumno_id
            $stmtA = $pdo->prepare("SELECT id FROM alumno_perfil WHERE usuario_id = ?");
            $stmtA->execute([$usuario_id]);
            $alumno_id = $stmtA->fetchColumn();

            // Hashing del DNI como clave temporal
            $hash = password_hash($dni, PASSWORD_DEFAULT);
            
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ?, debe_cambiar_password = 1 WHERE id = ? AND rol = 'alumno'");
            $stmt->execute([$hash, $usuario_id]);

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'reset_password',
                'entidad' => 'usuario',
                'entidad_id' => $usuario_id,
                'alumno_id' => $alumno_id ?: null,
                'detalle' => "Restableció la contraseña del alumno $nombre_completo a su DNI por defecto."
            ]);

            // Notificar al alumno
            crearNotificacion($pdo, $usuario_id, "Contraseña Restablecida", "Tu contraseña fue restablecida a tu número de DNI por tu entrenador. Deberás cambiarla en tu próximo inicio de sesión.", "/logout.php");

            $pdo->commit();

            header("Location: /admin/alumnos.php?msg=reset_ok");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al resetear contraseña de alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }

    } elseif ($action === 'delete') {
        if (!in_array($_SESSION['user_rol'], ['admin', 'entrenador_total'])) {
            header("Location: /admin/alumnos.php?error=unauthorized");
            exit;
        }
        $usuario_id = (int)$_POST['usuario_id'];

        if ($usuario_id <= 0) {
            header("Location: /admin/alumnos.php?error=invalid_delete");
            exit;
        }

        try {
            // Obtener datos antes del borrado
            $stmtUserPrev = $pdo->prepare("SELECT email, nombre, apellido, dni, rol FROM usuarios WHERE id = ?");
            $stmtUserPrev->execute([$usuario_id]);
            $u_prev = $stmtUserPrev->fetch() ?: [];

            $stmtPerfilPrev = $pdo->prepare("SELECT * FROM alumno_perfil WHERE usuario_id = ?");
            $stmtPerfilPrev->execute([$usuario_id]);
            $p_prev = $stmtPerfilPrev->fetch() ?: [];

            $datos_anteriores = array_merge($u_prev, $p_prev);
            $nombre_completo = ($u_prev['nombre'] ?? '') . ' ' . ($u_prev['apellido'] ?? '');

            $pdo->beginTransaction();

            // Eliminar al usuario. CASCADE eliminará su perfil, rutinas, comprobantes, etc.
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND rol = 'alumno'");
            $stmt->execute([$usuario_id]);

            // Registrar auditoría
            registrarAuditoria($pdo, [
                'accion' => 'eliminar_alumno',
                'entidad' => 'usuario',
                'entidad_id' => $usuario_id,
                'alumno_id' => $p_prev['id'] ?? null,
                'detalle' => "Eliminó definitivamente la cuenta del alumno $nombre_completo.",
                'datos_anteriores' => $datos_anteriores
            ]);

            $pdo->commit();

            header("Location: /admin/alumnos.php?msg=delete_ok");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al eliminar alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }
    } elseif ($action === 'becar') {
        if (!in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
            header("Location: /admin/alumnos.php?error=unauthorized");
            exit;
        }
        $alumno_id = (int)$_POST['alumno_id'];

        if ($alumno_id <= 0) {
            header("Location: /admin/alumnos.php?error=invalid");
            exit;
        }

        try {
            $pdo->beginTransaction();
            $mes_actual = date('Y-m');

            // Verificar si ya existe un pago o beca para el mes actual
            $stmtCheck = $pdo->prepare("SELECT id FROM pago_registro WHERE alumno_id = ? AND mes_pagado = ? AND estado IN ('pendiente', 'aprobado')");
            $stmtCheck->execute([$alumno_id, $mes_actual]);
            if (!$stmtCheck->fetch()) {
                // Registrar la beca como pago aprobado
                $stmtInsert = $pdo->prepare("
                    INSERT INTO pago_registro (alumno_id, mes_pagado, monto, comprobante_url, estado, fecha_aprobacion)
                    VALUES (?, ?, 0.00, 'BECA-RENOVADA', 'aprobado', NOW())
                ");
                $stmtInsert->execute([$alumno_id, $mes_actual]);
            }

            // Actualizar estado del alumno a activo
            $stmtUpdate = $pdo->prepare("UPDATE alumno_perfil SET activo = 1 WHERE id = ?");
            $stmtUpdate->execute([$alumno_id]);

            // Obtener el ID de usuario asociado para la auditoria y notificacion
            $stmtUsr = $pdo->prepare("SELECT u.id, u.nombre, u.apellido FROM usuarios u JOIN alumno_perfil ap ON u.id = ap.usuario_id WHERE ap.id = ?");
            $stmtUsr->execute([$alumno_id]);
            $usr = $stmtUsr->fetch();

            if ($usr) {
                $usuario_id = $usr['id'];
                $nombre_completo = $usr['nombre'] . ' ' . $usr['apellido'];
                
                registrarAuditoria($pdo, [
                    'accion' => 'becar_alumno',
                    'entidad' => 'alumno_perfil',
                    'entidad_id' => $alumno_id,
                    'alumno_id' => $alumno_id,
                    'detalle' => "Otorgó beca mensual a $nombre_completo para el mes $mes_actual."
                ]);

                crearNotificacion($pdo, $usuario_id, "Beca de Entrenamiento", "Tu entrenador te ha liberado del pago de este mes ($mes_actual). ¡A entrenar con todo!", "/alumno/dashboard.php");
            }

            $pdo->commit();
            header("Location: /admin/alumnos.php?msg=beca_ok");
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al becar alumno: " . $e->getMessage());
            header("Location: /admin/alumnos.php?error=db");
            exit;
        }
    } elseif ($action === 'import_csv') {
        if (!in_array($_SESSION['user_rol'], ['admin', 'entrenador_total', 'entrenador_intermedio'])) {
            header("Location: /admin/alumnos.php?error=unauthorized");
            exit;
        }

        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['csv_file']['tmp_name'];
            $fileName = $_FILES['csv_file']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($fileExtension !== 'csv') {
                header("Location: /admin/alumnos.php?error=invalid_format");
                exit;
            }

            if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                // Auto-detectar delimitador (, o ;)
                $delimiter = ",";
                $firstLine = fgets($handle);
                if ($firstLine !== FALSE) {
                    if (strpos($firstLine, ';') !== FALSE && strpos($firstLine, ',') === FALSE) {
                        $delimiter = ";";
                    } elseif (strpos($firstLine, ';') !== FALSE && strpos($firstLine, ',') !== FALSE) {
                        $semicolons = substr_count($firstLine, ';');
                        $commas = substr_count($firstLine, ',');
                        $delimiter = ($semicolons > $commas) ? ";" : ",";
                    }
                    rewind($handle);
                }

                // Saltar la cabecera
                fgetcsv($handle, 1000, $delimiter);
                
                $importados = 0;
                $errores = 0;

                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    // Validar que la fila tenga datos mínimos
                    if (count($data) < 4 || empty($data[0]) || empty($data[1]) || empty($data[2]) || empty($data[3])) {
                        $errores++;
                        continue;
                    }

                    $nombre = trim($data[0]);
                    $apellido = trim($data[1]);
                    $dni = trim($data[2]);
                    $email = trim($data[3]);
                    $telefono = isset($data[4]) ? trim($data[4]) : '';
                    $fecha_nac = isset($data[5]) && !empty($data[5]) ? trim($data[5]) : '1990-01-01';

                    // Nuevas columnas opcionales
                    $sexo = isset($data[6]) ? strtoupper(trim($data[6])) : null;
                    if (!in_array($sexo, ['M', 'F'])) {
                        $sexo = null;
                    }

                    $nivel = isset($data[7]) ? trim($data[7]) : 'Principiante';
                    $nivel = ucfirst(strtolower($nivel));
                    if (!in_array($nivel, ['Principiante', 'Intermedio', 'Avanzado', 'Elite'])) {
                        $nivel = 'Principiante';
                    }

                    $plan_tipo = isset($data[8]) ? trim($data[8]) : 'A Distancia';
                    if (strcasecmp($plan_tipo, 'presencial') === 0) {
                        $plan_tipo = 'Presencial';
                    } else {
                        $plan_tipo = 'A Distancia';
                    }

                    $dni_entrenador = isset($data[9]) ? trim($data[9]) : '';
                    $entrenador_id = null;
                    if (!empty($dni_entrenador)) {
                        $stmtEnt = $pdo->prepare("
                            SELECT id FROM usuarios 
                            WHERE dni = ? AND rol IN ('admin', 'entrenador_total', 'entrenador_intermedio', 'entrenador_limitado')
                        ");
                        $stmtEnt->execute([$dni_entrenador]);
                        $ent_id = $stmtEnt->fetchColumn();
                        if ($ent_id) {
                            $entrenador_id = (int)$ent_id;
                        }
                    }

                    // Verificar si ya existe email o dni
                    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR dni = ?");
                    $stmtCheck->execute([$email, $dni]);
                    if ($stmtCheck->fetch()) {
                        $errores++;
                        continue;
                    }

                    try {
                        $pdo->beginTransaction();
                        
                        $hash = password_hash($dni, PASSWORD_DEFAULT);
                        $stmtUsr = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, email, telefono, password_hash, rol, dni, debe_cambiar_password) VALUES (?, ?, ?, ?, ?, 'alumno', ?, 1)");
                        $stmtUsr->execute([$nombre, $apellido, $email, $telefono, $hash, $dni]);
                        $nuevo_usuario_id = $pdo->lastInsertId();

                        $stmtPerf = $pdo->prepare("
                            INSERT INTO alumno_perfil (usuario_id, dni, telefono, fecha_nacimiento, plan_tipo, nivel, activo, sexo, entrenador_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmtPerf->execute([$nuevo_usuario_id, $dni, $telefono, $fecha_nac, $plan_tipo, $nivel, 1, $sexo, $entrenador_id]);
                        
                        $pdo->commit();
                        $importados++;
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log("Error al importar fila: " . $e->getMessage());
                        $errores++;
                    }
                }
                fclose($handle);
                header("Location: /admin/alumnos.php?msg=import_ok&importados=$importados&errores=$errores");
                exit;
            } else {
                header("Location: /admin/alumnos.php?error=read_file");
                exit;
            }
        } else {
            header("Location: /admin/alumnos.php?error=upload_failed");
            exit;
        }
    }
} else {
    header("Location: /admin/alumnos.php");
    exit;
}
?>

