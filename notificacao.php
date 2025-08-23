<?php
// notificacao.php
// Endpoint para receber notificações (webhooks) do Mercado Pago

// Captura o corpo da requisição (geralmente JSON)
$input = file_get_contents("php://input");

// Registra o conteúdo recebido para fins de depuração (opcional)
$logEntry = date('Y-m-d H:i:s') . " - " . $input . PHP_EOL;
file_put_contents("notificacoes.log", $logEntry, FILE_APPEND);

// Decodifica o JSON recebido em um array associativo
$data = json_decode($input, true);

// Verifica se a notificação contém um identificador de pagamento
if (isset($data['data']['id'])) {
    $payment_id = $data['data']['id'];

    // Aqui você pode:
    // 1. Consultar a API do Mercado Pago para obter mais detalhes do pagamento
    // 2. Atualizar o status do pagamento no seu banco de dados com base no identificador
    // 3. Processar regras de negócio, enviar e-mails, etc.

    // Exemplo: consulta à API para obter detalhes do pagamento (opcional)
    // require 'vendor/autoload.php';
    // MercadoPago\SDK::setAccessToken("SEU_ACCESS_TOKEN");
    // $payment = MercadoPago\Payment::find_by_id($payment_id);
    // Se desejar, atualize seu banco de dados com os dados de $payment
}

// Retorna um status 200 OK para confirmar o recebimento da notificação
http_response_code(200);
?>
