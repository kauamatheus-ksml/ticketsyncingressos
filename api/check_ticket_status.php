<?php
// File: api/check_ticket_status.php
header("Content-Type: application/json; charset=utf-8");
require_once "auth.php";

require_once "cors.php";

$user = clienteAutenticado($conn);
if (!$user) {
    http_response_code(401);
    exit(json_encode(["error" => "Não autorizado."]));
}

$orderId = $_GET["order_id"] ?? "";
if (!$orderId) {
    http_response_code(400);
    exit(json_encode(["error" => "order_id é obrigatório."]));
}

$stmt = $conn->prepare("
    SELECT ingresso_validado
    FROM pedidos
    WHERE order_id = ? AND email = ? LIMIT 1
");
$stmt->bind_param("ss", $orderId, $user["email"]);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Pedido não encontrado."]);
}
