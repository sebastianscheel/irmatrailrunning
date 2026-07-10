<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Validar que esté logueado como alumno
require_rol('alumno');

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';
$external_reference = isset($_GET['external_reference']) ? trim($_GET['external_reference']) : '';

if ($status === 'approved' && !empty($payment_id) && !empty($external_reference)) {
    // Desglosar external_reference (alumno_id::mes_pagado::monto)
    $parts = explode('::', $external_reference);
    
    if (count($parts) === 3) {
        $alumno_id = (int)$parts[0];
        $mes_pagado = trim($parts[1]);
        $monto = (float)$parts[2];
        $comprobante_url = 'MERCADOPAGO-' . $payment_id;

        try {
            $pdo->beginTransaction();

            // 1. Evitar duplicación en caso de recargas de página
            $stmtCheck = $pdo->prepare("SELECT id FROM pago_registro WHERE comprobante_url = ?");
            $stmtCheck->execute([$comprobante_url]);
            $pago_existente = $stmtCheck->fetchColumn();

            if (!$pago_existente) {
                // 2. Insertar registro de pago aprobado
                $stmtInsert = $pdo->prepare("
                    INSERT INTO pago_registro (alumno_id, mes_pagado, monto, comprobante_url, estado, fecha_aprobacion) 
                    VALUES (?, ?, ?, ?, 'aprobado', NOW())
                ");
                $stmtInsert->execute([$alumno_id, $mes_pagado, $monto, $comprobante_url]);

                // 3. Activar perfil del alumno automáticamente
                $stmtActivate = $pdo->prepare("UPDATE alumno_perfil SET activo = 1 WHERE id = ?");
                $stmtActivate->execute([$alumno_id]);
            }

            $pdo->commit();
            header("Location: /alumno/reportar_pago.php?msg=pago_ok");
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al procesar el retorno de Mercado Pago: " . $e->getMessage());
            header("Location: /alumno/reportar_pago.php?error=db");
            exit;
        }
    } else {
        header("Location: /alumno/reportar_pago.php?error=empty");
        exit;
    }
} else {
    header("Location: /alumno/reportar_pago.php?error=upload_err");
    exit;
}
?>
