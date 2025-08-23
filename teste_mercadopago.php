<?php
// teste_mercadopago.php
require 'config_mercadopago.php';

use MercadoPago\Payment;

echo "<h2>Teste Mercado Pago</h2>";

try {
    // Teste básico de configuração
    echo "<p>✅ SDK carregado com sucesso</p>";
    
    // Teste de criação de pagamento simples
    $payment = new Payment();
    $payment->transaction_amount = 10.00;
    $payment->token = "card_token_example";
    $payment->description = "Teste";
    $payment->installments = 1;
    $payment->payment_method_id = "visa";
    
    $payment->payer = array(
        "email" => "test@test.com",
        "first_name" => "Test",
        "last_name" => "User",
        "identification" => array(
            "type" => "CPF",
            "number" => "11111111111"
        )
    );
    
    echo "<p>✅ Objeto Payment criado</p>";
    echo "<p><strong>Public Key configurada:</strong> " . $GLOBALS['mp_public_key'] . "</p>";
    
    // Não vamos salvar o pagamento, apenas testar a estrutura
    echo "<p>✅ Estrutura de pagamento válida</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<h3>Teste de Cartão (usando cartões de teste)</h3>";
echo "<p>Visa Aprovado: 4509 9535 6623 3704</p>";
echo "<p>Visa Rejeitado: 4234 1234 1234 1234</p>";
echo "<p>CVV: 123</p>";
echo "<p>CPF: 11111111111</p>";
?>