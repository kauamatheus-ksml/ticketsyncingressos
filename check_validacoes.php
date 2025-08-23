<?php
// check_validacoes.php - Verifica quais ingressos foram validados individualmente
session_start();
require 'conexao.php';

header('Content-Type: application/json');

// Verifica se está autenticado
if (!isset($_SESSION['userid']) && !isset($_SESSION['adminid']) && !isset($_SESSION['funcionarioid'])) {
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

// Verifica se o order_id foi fornecido
if (!isset($_GET['order_id'])) {
    echo json_encode(['error' => 'Order ID não fornecido']);
    exit;
}

$orderId = $_GET['order_id'];

// Se for cliente, verifica se o pedido pertence a ele
if (isset($_SESSION['userid']) && isset($_SESSION['email'])) {
    $stmtCheck = $conn->prepare("SELECT id FROM pedidos WHERE order_id = ? AND email = ?");
    $stmtCheck->bind_param("ss", $orderId, $_SESSION['email']);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    if ($resultCheck->num_rows == 0) {
        echo json_encode(['error' => 'Pedido não encontrado ou não pertence a este usuário']);
        exit;
    }
    $stmtCheck->close();
}

// Busca os ingressos validados para este pedido
$validados = [];
$sql = "SELECT ingresso_index FROM validacoes_individuais WHERE order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $validados[] = (int)$row['ingresso_index'];
    }
}
$stmt->close();

// Busca a quantidade total de ingressos no pedido
$totalIngressos = 0;
$sqlTotal = "SELECT itens_json FROM pedidos WHERE order_id = ?";
$stmtTotal = $conn->prepare($sqlTotal);
$stmtTotal->bind_param("s", $orderId);
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();

if ($resultTotal->num_rows > 0) {
    $row = $resultTotal->fetch_assoc();
    if (!empty($row['itens_json'])) {
        $itens = json_decode($row['itens_json'], true);
        if (is_array($itens)) {
            foreach ($itens as $item) {
                $totalIngressos += intval($item['quantidade'] ?? 0);
            }
        }
    }
}
$stmtTotal->close();

// Atualiza o status geral se todos os ingressos foram validados
if (count($validados) >= $totalIngressos && $totalIngressos > 0) {
    $sqlUpdate = "UPDATE pedidos SET ingresso_validado = 1 WHERE order_id = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("s", $orderId);
    $stmtUpdate->execute();
    $stmtUpdate->close();
}

$conn->close();

// Retorna o resultado
echo json_encode([
    'validados' => $validados,
    'total' => $totalIngressos,
    'todos_validados' => (count($validados) >= $totalIngressos && $totalIngressos > 0)
]);