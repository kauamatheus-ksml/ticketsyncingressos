<?php
// webhook_pix.php
// Arquivo que recebe as notificações de mudança de status do Pix via GerenciaNet

ini_set('log_errors', 1);
// Define o caminho para registrar os erros de depuração
ini_set('error_log', __DIR__ . '/pix_webhook_errors.log');

require 'conexao.php';

// Lê o corpo da requisição (JSON)
$input = file_get_contents('php://input');
$data  = json_decode($input, true);

// -----------------------------------------------------------
// 1) (Opcional) Validação de autenticidade
// Você pode validar se a notificação vem de fato da GerenciaNet por meio de um token ou cabeçalho.
// Exemplo:
// $headers = getallheaders();
// if (!isset($headers['Authorization']) || $headers['Authorization'] !== 'Bearer SEU_TOKEN') {
//     http_response_code(403);
//     echo "Acesso não autorizado.";
//     exit;
// }
// -----------------------------------------------------------

// -----------------------------------------------------------
// 2) Verifica se o JSON contém o array 'pix'
// -----------------------------------------------------------
if (!$data || !isset($data['pix'])) {
    http_response_code(400);
    echo "Dados de notificação inválidos.";
    exit;
}

// -----------------------------------------------------------
// 3) Itera sobre cada transação Pix na notificação e atualiza o status do pedido
// -----------------------------------------------------------
foreach ($data['pix'] as $pixPayment) {
    // Extraímos os campos relevantes
    $txid       = $pixPayment['txid'] ?? null;
    $endToEndId = $pixPayment['endToEndId'] ?? null;
    $valor      = $pixPayment['valor'] ?? null;
    $horario    = $pixPayment['horario'] ?? null; // Data/hora do pagamento

    // Caso o txid esteja presente, atualizamos o pedido
    if ($txid) {
        $stmt = $conn->prepare("UPDATE pedidos SET status = 'approved' WHERE txid_pix = ? AND status <> 'approved'");
        if ($stmt) {
            $stmt->bind_param("s", $txid);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Erro na preparação do statement: " . $conn->error);
        }
    }
}

// -----------------------------------------------------------
// 4) Retorna 200 (OK) para confirmar que a notificação foi processada
// -----------------------------------------------------------
http_response_code(200);
echo "Notificação processada com sucesso.";
// -----------------------------------------------------------  
// ?>