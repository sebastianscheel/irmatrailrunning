<?php
$file = 'admin/alumnos.php';
$content = file_get_contents($file);

// 1. Mover los modales fuera del <tbody>
$start_modal = strpos($content, '<!-- Modal Editar Alumno -->');
if ($start_modal !== false) {
    // Buscar el final de los modales (antes del endforeach de la tabla)
    $end_modal = strpos($content, '<?php endforeach; ?>', $start_modal);
    
    if ($end_modal !== false) {
        $modals = substr($content, $start_modal, $end_modal - $start_modal);
        // Remover de la tabla
        $content = substr_replace($content, '', $start_modal, $end_modal - $start_modal);
        
        // Insertarlos al fondo de la página
        $new_modal_pos = strpos($content, '<!-- Modal Nuevo Alumno -->');
        if ($new_modal_pos !== false) {
            $modals_wrapped = "<?php if (count(\$alumnos) > 0): foreach (\$alumnos as \$alumno): ?>\n" . $modals . "\n<?php endforeach; endif; ?>\n\n";
            $content = substr_replace($content, $modals_wrapped, $new_modal_pos, 0);
        }
    }
}

// 2. Agregar indicador de certificado médico
$badge_search = '<?php if ($alumno[\'ddjj_aceptada\']): ?>
                                            <span class="badge bg-secondary" title="DDJJ Aceptada"><i class="fa-solid fa-file-signature text-success"></i> DDJJ</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="DDJJ Pendiente"><i class="fa-solid fa-file-signature text-danger"></i> DDJJ</span>
                                        <?php endif; ?>';
                                        
$badge_replace = '<?php if ($alumno[\'ddjj_aceptada\']): ?>
                                            <span class="badge bg-secondary" title="DDJJ Aceptada"><i class="fa-solid fa-file-signature text-success"></i> DDJJ</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="DDJJ Pendiente"><i class="fa-solid fa-file-signature text-danger"></i> DDJJ</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($alumno[\'certificado_medico_estado\'] === \'Aprobado\'): ?>
                                            <span class="badge bg-secondary" title="Certificado Aprobado"><i class="fa-solid fa-notes-medical text-success"></i> Méd.</span>
                                        <?php elseif ($alumno[\'certificado_medico_estado\'] === \'Rechazado\'): ?>
                                            <span class="badge bg-secondary" title="Certificado Rechazado"><i class="fa-solid fa-notes-medical text-danger"></i> Méd.</span>
                                        <?php elseif (!empty($alumno[\'certificado_medico_url\'])): ?>
                                            <span class="badge bg-secondary" title="Certificado Pendiente"><i class="fa-solid fa-notes-medical text-warning"></i> Méd.</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Falta Certificado Médico"><i class="fa-solid fa-notes-medical text-danger"></i> Méd.</span>
                                        <?php endif; ?>';

$content = str_replace($badge_search, $badge_replace, $content);

file_put_contents($file, $content);
echo "Fixed";
?>
