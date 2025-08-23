<?php   
// exemplo: conectar_promotor.php
$clientId = '2363265554596237';
$redirectUri = urlencode('https://ticketsync.com/oauth-callback.php');
$state      = $promotorId;                 // para saber quem autorizou

$url = "https://auth.mercadopago.com.br/authorization?response_type=code"
     . "&client_id={$clientId}&platform_id=mp&state={$state}&redirect_uri={$redirectUri}";
header("Location: {$url}");
exit;
?>