<?php
require_once "auth.php";
header('Content-Type: application/json');
$user = clienteAutenticado($conn);
if (!$user) { http_response_code(401); exit(json_encode(['error'=>'Não autorizado'])); }
$id = intval($_GET['ticket_id'] ?? 0);
$stmt = $conn->prepare("UPDATE pedidos SET ingresso_validado=1 WHERE id=?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) echo json_encode(['success'=>true]);
else { http_response_code(500); echo json_encode(['error'=>'Erro']); }
