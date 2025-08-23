<?php
// Permite acesso de qualquer origem
header("Access-Control-Allow-Origin: *");
// Permite os métodos GET, POST e OPTIONS
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Permite os headers Content-Type e Authorization (adicione outros se necessário)
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include('conexao.php');
header('Content-Type: application/json');

// Sua lógica para buscar os ingressos:
$sql = "SELECT order_id, nome, sobrenome, email, valor_total, created_at, ticket_code, status FROM pedidos WHERE LOWER(status) = 'approved'";
$result = $conn->query($sql);

$ingressos = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ingressos[] = $row;
    }
}
echo json_encode($ingressos);
$conn->close();
?>
