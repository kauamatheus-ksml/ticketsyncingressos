<?php
// callback.php
session_start();
require 'conexao.php';
require __DIR__ . '/vendor/autoload.php';

// Verifica se o parâmetro do pedido foi enviado (order_id)
$orderId = $_GET['pedido'] ?? null;
if (!$orderId) {
    die("Pedido não especificado.");
}

// O Mercado Pago envia alguns parâmetros na URL quando o usuário é redirecionado.
// Geralmente, em auto_return, recebemos pelo menos "collection_status" e "payment_id".
// Se não houver o parâmetro, podemos definir um status genérico.
$collectionStatus = $_GET['collection_status'] ?? 'desconhecido';

// Define o status e a mensagem com base no collection_status recebido
switch ($collectionStatus) {
    case 'approved':
        $novoStatus   = 'aprovado';
        $mensagem     = "Pagamento aprovado com sucesso!";
        break;
    case 'pending':
        $novoStatus   = 'pendente';
        $mensagem     = "Pagamento pendente. Aguarde a confirmação.";
        break;
    case 'in_process':
        $novoStatus   = 'processando';
        $mensagem     = "Pagamento em processamento. Por favor, aguarde.";
        break;
    case 'rejected':
        $novoStatus   = 'falha';
        $mensagem     = "Pagamento rejeitado. Tente novamente.";
        break;
    default:
        $novoStatus   = 'desconhecido';
        $mensagem     = "Status de pagamento desconhecido.";
        break;
}

// Atualiza o status do pedido na tabela 'pedidos'
$stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE order_id = ?");
if (!$stmt) {
    die("Erro na preparação da consulta: " . $conn->error);
}
$stmt->bind_param("ss", $novoStatus, $orderId);
if (!$stmt->execute()) {
    die("Erro ao atualizar o pedido: " . $stmt->error);
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Status do Pagamento</title>
    <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f2f2; margin: 0; padding: 20px; }
        .container { background: #fff; max-width: 500px; margin: 50px auto; padding: 20px; border-radius: 8px; text-align: center; }
        h1 { color: #333; }
        p { color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($mensagem); ?></h1>
        <p>Identificador do pedido: <strong><?php echo htmlspecialchars($orderId); ?></strong></p>
        <p>Status atual: <strong><?php echo htmlspecialchars($novoStatus); ?></strong></p>
    </div>
</body>
</html>
