<?php
// webhook_asaas.php
require 'config_asaas.php';

$input = file_get_contents("php://input");
$data  = json_decode($input, true);

if (isset($data['event']) && $data['event'] == "PAYMENT_RECEIVED") {
    $idPagamento = $data['payment']['id'];
    // Aqui você pode atualizar o status do pagamento no seu banco de dados
    file_put_contents('pagamentos_confirmados.txt', "Pagamento confirmado: $idPagamento\n", FILE_APPEND);
}

http_response_code(200);
?>
