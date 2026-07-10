<?php
$file = 'admin/entrenadores.php';
$content = file_get_contents($file);

$start = strpos($content, '<!-- Modal Eliminar -->');
$end = strpos($content, '<?php endforeach; ?>', $start);
if ($start !== false && $end !== false) {
    $modals = substr($content, $start, $end - $start);
    $content = substr_replace($content, '', $start, $end - $start);
    
    $insert_pos = strpos($content, '<!-- Modal Nuevo Entrenador -->');
    if ($insert_pos !== false) {
        $wrapped = "<?php if (count(\$entrenadores) > 0): foreach (\$entrenadores as \$ent): ?>\n" . $modals . "<?php endforeach; endif; ?>\n\n";
        $content = substr_replace($content, $wrapped, $insert_pos, 0);
    }
}
file_put_contents($file, $content);
echo "Fixed entrenadores";
?>
