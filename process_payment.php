<?php
require 'vendor/autoload.php';              // composer require mercadopago/sdk:^2
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Exceptions\MPValidationException;

header('Content-Type: application/json');

MercadoPagoConfig::setAccessToken('APP_USR-858149184757368-041708-ab1049bc8d4063c24b6e0feba072793b-2320640278');

$body = json_decode(file_get_contents('php://input'), true);

try {
  $client  = new PaymentClient();
  $payment = $client->create([
    "token"             => $body['token']             ?? '',
    "issuer_id"         => $body['issuer_id']         ?? '',
    "payment_method_id" => $body['paymentMethodId']   ?? '',
    "transaction_amount"=> (float)($body['transactionAmount'] ?? 0),
    "installments"      => $body['installments']      ?? 1,
    "payer" => [
      "email" => $body['email'] ?? '',
      "identification" => [
        "type"   => $body['identificationType'] ?? '',
        "number" => $body['number']             ?? ''
      ]
    ]
  ]);
  echo json_encode($payment);
} catch (MPApiException|MPValidationException $e) {
  http_response_code(400);
  echo json_encode(['error'=>$e->getMessage()]);
}
?>
