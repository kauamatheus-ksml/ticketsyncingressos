<?php
// pagar_um_centavo.php

// Credenciais de produção (Access Token)
$access_token = "APP_USR-3202249576906512-021808-18ecbdd40b7ecc9e5cca38b33bebb103-1061301725";

// Dados da transação: venda de 1 centavo via PIX
$data = [
    "transaction_amount" => 0.01, // R$ 0,01 (1 centavo)
    "description"        => "Venda teste de 1 centavo via PIX - Produção",
    "payment_method_id"  => "pix", // Método de pagamento PIX
    "payer"              => [
        "email"          => "cliente@exemplo.com", // Utilize um email válido
        "first_name"     => "Cliente",
        "last_name"      => "Exemplo",
        "identification" => [
            "type"   => "CPF",
            "number" => "19119119100" // CPF de teste
        ]
    ]
];

// Gera uma chave única para idempotência, evitando transações duplicadas
$idempotency_key = uniqid("mp_", true);

// Inicializa o cURL para enviar a requisição à API do Mercado Pago
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

// Converte a resposta JSON em array
$result = json_decode($response, true);

// Exibe o código HTTP e os detalhes da transação
echo "<h3>HTTP Code: $httpCode</h3>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Se disponível, exibe o código PIX (Copy & Paste)
if (isset($result['point_of_interaction']['transaction_data']['qr_code'])) {
    echo "<h3>PIX Copy & Paste:</h3>";
    echo "<p>" . $result['point_of_interaction']['transaction_data']['qr_code'] . "</p>";
} else {
    echo "<p>Código PIX não disponível.</p>";
}
?>
