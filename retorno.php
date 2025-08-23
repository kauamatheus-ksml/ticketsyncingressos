<?php
require_once 'pagseguro_config.php';

use PagSeguro\Services\Transactions;

$payment = Transactions::check($credentials, $_POST['transaction_id']);

if ($payment->getStatus() == 3) {
    echo 'Pagamento aprovado!';
} else {
    echo 'Pagamento não aprovado ou em andamento.';
}
