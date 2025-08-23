<?php
//verifica_pagamento.php
session_start();
require 'vendor/autoload.php';

use MercadoPago\SDK;
use MercadoPago\Payment;

// Configure seu Access Token
SDK::setAccessToken('APP_USR-2363265554596237-031207-969582c8df1a055e7f6db3113142e653-2320640278');

if (!isset($_SESSION['payment_id'])) {
    die("Pagamento não identificado.");
}

$paymentId = $_SESSION['payment_id'];

// Recarrega o pagamento para verificar o status
$payment = Payment::find_by_id($paymentId);

// Retorna o status do pagamento
if ($payment->status === 'approved') {
    // Aqui você pode atualizar seu banco de dados, se necessário
    echo "approved";
} else {
    echo $payment->status;
}
?>
