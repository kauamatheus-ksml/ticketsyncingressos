<?php
// compra_teste.php

// Credenciais de teste do Mercado Pago
$access_token = "TEST-3492580629956999-021808-7dd7a57a50421e9ca534b23bd19fb387-1061301725";

// Dados da transação de teste
$data = [
    "transaction_amount" => 50.00,                // Valor da transação (ex: R$ 50,00)
    "description"        => "Compra Teste - Ingresso para Evento XYZ",
    "payment_method_id"  => "pix",                // Método de pagamento: pix
    "payer" => [
        "email"          => "cliente_teste@exemplo.com",
        "first_name"     => "Cliente",
        "last_name"      => "Teste",
        "identification" => [
            "type"     => "CPF",
            "number"   => "19119119100"            // CPF de teste
        ]
    ]
];

// Gera uma chave única para idempotência (para evitar transações duplicadas)
$idempotency_key = uniqid("mp_", true);

// Inicializa o cURL para enviar a requisição de pagamento
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json",
    "X-Idempotency-Key: $idempotency_key"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// Executa a requisição e captura a resposta
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Exibe o resultado da transação para análise
echo "<h3>HTTP Code: $httpCode</h3>";
echo "<pre>" . print_r(json_decode($response, true), true) . "</pre>";
?>
