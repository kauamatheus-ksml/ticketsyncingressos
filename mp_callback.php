<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

use MercadoPago\SDK;

// Verifica se o código de autorização foi retornado
if (!isset($_GET['code'])) {
    die("Código de autorização não fornecido.");
}

$code = $_GET['code'];
$client_id = '3202249576906512';         // Substitua pelo seu Client ID
$client_secret = 'M2wDP6hn6Qcmqv61GzkJ5BKRWh7BX38Y'; // Substitua pelo seu Client Secret
$redirect_uri = 'https://ticketsync.com.br/mp_callback.php'; // Mesma URL registrada

// Prepara os parâmetros para trocar o código pelo token
$params = [
    "grant_type"    => "authorization_code",
    "client_id"     => $client_id,
    "client_secret" => $client_secret,
    "code"          => $code,
    "redirect_uri"  => $redirect_uri
];

// Utiliza cURL para fazer a requisição
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.mercadopago.com/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => http_build_query($params)
]);

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

if (isset($data['access_token'])) {
    // Define o token de acesso para futuras chamadas do SDK
    SDK::setAccessToken($data['access_token']);

    // Obtém informações da conta do usuário
    $userInfo = SDK::get("/users/me");
    
    if (isset($userInfo['id'])) {
        // Armazena o recipient_id (ID da conta Mercado Pago) na sessão ou no BD
        $_SESSION['mp_recipient_id'] = $userInfo['id'];

        echo "<h2>Conexão realizada com sucesso!</h2>";
        echo "<p>Seu Recipient ID é: <strong>" . htmlspecialchars($userInfo['id']) . "</strong></p>";
        echo "<p>Você pode fechar esta janela ou voltar ao painel.</p>";
        // Opcional: Redirecione ou atualize a página do painel
    } else {
        echo "Erro ao obter informações da conta do Mercado Pago.";
    }
} else {
    echo "Erro na autenticação com o Mercado Pago.";
}
?>
