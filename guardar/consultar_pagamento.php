<?php
// consultar_pagamento.php
require 'config_asaas.php';

$paymentId = 'pay_6catyiqtbispvd3b'; // Substitua pelo ID do pagamento desejado

$ch = curl_init(ASAAS_API_URL . "payments/" . $paymentId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . ASAAS_API_KEY
]);

$response = curl_exec($ch);
curl_close($ch);

$decodedResponse = json_decode($response, true);

echo "<pre>🚨 DADOS ATUALIZADOS DO PAGAMENTO 🚨\n";
print_r($decodedResponse);
echo "</pre>";
?>
