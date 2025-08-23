<?php
// File: api/get_ticket_details.php

require_once "cors.php";
header("Content-Type: application/json; charset=utf-8");

require_once "auth.php";

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

$sql = "
    SELECT
        p.id,
        p.order_id,
        p.nome    AS comprador_nome,
        p.sobrenome AS comprador_sobrenome,
        p.valor_total,
        p.status,
        p.created_at,
        p.ticket_code,
        p.forma_pagamento,
        p.itens_json,
        p.ingresso_validado,
        e.id          AS evento_id,
        e.nome        AS evento_nome,
        e.logo        AS evento_logo,
        e.data_inicio AS evento_data,
        e.hora_inicio AS evento_horario,
        e.local       AS evento_local
    FROM pedidos p
    JOIN eventos e ON e.id = p.evento_id
    WHERE p.order_id = ? AND p.email = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $orderId, $user["email"]);
$stmt->execute();
$res = $stmt->get_result();

if ($ticket = $res->fetch_assoc()) {
    // Decodifica itens_json se precisar
    $ticket["itens"] = json_decode($ticket["itens_json"], true) ?: [];
    unset($ticket["itens_json"]);
    // Envia tudo (inclusive comprador_nome e comprador_sobrenome)
    echo json_encode($ticket, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit();
}

http_response_code(404);
echo json_encode(["error" => "Pedido não encontrado."]);
