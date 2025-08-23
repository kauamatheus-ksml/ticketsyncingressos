<?php
/**
 * get_permissions_time.php
 */
$permissionsFile = "permissoes_geral.json";
$modTime = file_exists($permissionsFile) ? filemtime($permissionsFile) : 0;
header('Content-Type: application/json');
echo json_encode(['mod_time' => $modTime]);
?>
