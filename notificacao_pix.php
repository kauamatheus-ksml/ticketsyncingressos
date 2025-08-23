<?php
// notificacao_pix.php
// Recebe a notificação da Gerencianet e atualiza o status do pedido

// Obtenha os dados da notificação (geralmente via POST, formato JSON)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Aqui você pode implementar a validação da notificação, por exemplo,
// verificando se o txid ou loc_id enviado é válido e corresponde a um pedido no seu sistema.

if (isset($data['txid']) && isset($data['status'])) {
    // Conecte ao seu banco de dados
    require 'conexao.php';

    // Exemplo: Atualiza o pedido baseado no txid recebido
    $txid = $data['txid'];
    $status = $data['status']; // Pode ser 'pago', 'cancelado', etc.

    $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE txid_pix = ?");
    $stmt->bind_param("ss", $status, $txid);
    $stmt->execute();
    $stmt->close();

    // Responda com sucesso para a Gerencianet
    http_response_code(200);
    echo "Notificação recebida e processada";
} else {
    http_response_code(400);
    echo "Dados insuficientes";
}
?>
