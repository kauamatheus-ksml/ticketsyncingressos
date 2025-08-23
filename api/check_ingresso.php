<?php
// File: api/check_ingresso.php

require_once "cors.php"; // ← Adicione isto
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// Lê o orderId via GET
$orderId = isset($_GET['order_id']) ? $conn->real_escape_string($_GET['order_id']) : '';
if (empty($orderId)) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID inválido.']);
    exit();
}

// Verifica o status do ingresso
$sql = "SELECT ingresso_validado FROM pedidos WHERE order_id = '$orderId' LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'ingresso_validado' => $row['ingresso_validado']]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Pedido não encontrado.']);
}

$conn->close();
