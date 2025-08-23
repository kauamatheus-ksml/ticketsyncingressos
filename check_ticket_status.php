<?php
// check_ticket_status.php
session_start();
require_once 'conexao.php';

$orderId = $_GET['order_id'] ?? '';
if ($orderId != '') {
    $stmt = $conn->prepare("SELECT ingresso_validado FROM pedidos WHERE order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
         echo json_encode(["ingresso_validado" => $row["ingresso_validado"]]);
    } else {
         echo json_encode(["ingresso_validado" => 0]);
    }
    $stmt->close();
}
$conn->close();
?>
