<?php
session_start();

// Verifica se o usuário está logado; se não estiver, interrompe a execução
if (!isset($_SESSION['userid'])) {
    die("Você precisa estar logado para conectar sua conta Mercado Pago.");
}

// Utiliza o ID do usuário logado
$promotor_id = $_SESSION['userid'];

// Dados da sua aplicação master – substitua pelos valores reais
$client_id    = '3202249576906512';
$redirect_uri = 'http://ticketsync.com.br/callback.php'; // Essa URL deve estar registrada no Mercado Pago

// Monta a URL de autorização do Mercado Pago Connect
$auth_url = "https://auth.mercadopago.com.br/authorization?client_id=" . $client_id .
            "&response_type=code&platform_id=mp&redirect_uri=" . urlencode($redirect_uri);

// Redireciona o usuário para a autorização
header("Location: " . $auth_url);
exit();
?>
