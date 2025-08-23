<?php
require_once 'pagseguro_config.php';

use PagSeguro\Services\PaymentRequest;
use PagSeguro\Models\Item;

$paymentRequest = new PaymentRequest();
$paymentRequest->addItem('1', 'Produto Teste', 1, 100.00); // Defina o ID, nome, quantidade e preço

// Definir URLs de retorno após pagamento
$paymentRequest->setRedirectUrl('http://www.seusite.com/retorno.php');

// Adicionar os dados do comprador (se necessário)
$paymentRequest->setSender('Nome do Comprador', 'email@comprador.com.br');

// Gerar a URL de pagamento
$url = $paymentRequest->register($credentials);

echo "Clique no link para pagar: <a href='{$url}'>Pagar com PagSeguro</a>";
