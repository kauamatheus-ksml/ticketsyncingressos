<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

include('conexao.php');
$email = $_GET['email'] ?? '';
$stmt = $conn->prepare("SELECT order_id, nome, sobrenome, email, valor_total, created_at, ticket_code, status FROM pedidos WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$ingressos = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ingressos[] = $row;
    }
}
echo json_encode($ingressos);
$stmt->close();
$conn->close();
?>
