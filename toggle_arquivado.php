<?php
// toggle_arquivado.php
session_start();
include('conexao.php');

// Verifica se veio via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['userid']) || empty($_SESSION['email'])) {
        echo json_encode(["status" => "error", "message" => "Usuário não autenticado."]);
        exit;
    }

    $userEmail = $_SESSION['email'];
    $orderId   = $_POST['order_id'] ?? '';
    $action    = $_POST['action']   ?? '';

    // Verificamos se a ação é 'arquivar' ou 'restaurar'
    if ($action !== 'arquivar' && $action !== 'restaurar') {
        echo json_encode(["status" => "error", "message" => "Ação inválida."]);
        exit;
    }

    // Primeiro, checa se o pedido pertence ao usuário logado
    $sqlCheck = "SELECT * FROM pedidos WHERE order_id = ? AND email = ?";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param('ss', $orderId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $pedido = $result->fetch_assoc();

    if (!$pedido) {
        echo json_encode(["status" => "error", "message" => "Pedido não encontrado para este usuário."]);
        exit;
    }

    // Define o valor de arquivado
    $arquivarValue = ($action === 'arquivar') ? 1 : 0;

    // Atualiza no banco
    $sqlUpdate = "UPDATE pedidos SET arquivado = ? WHERE order_id = ?";
    $stmtUp = $conn->prepare($sqlUpdate);
    $stmtUp->bind_param('is', $arquivarValue, $orderId);
    $stmtUp->execute();

    if ($stmtUp->affected_rows > 0) {
        $msg = ($action === 'arquivar') 
             ? "Ingresso arquivado com sucesso!"
             : "Ingresso restaurado com sucesso!";
        echo json_encode(["status" => "success", "message" => $msg]);
    } else {
        echo json_encode(["status" => "error", "message" => "Falha ao atualizar ingresso."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Método inválido."]);
}
