<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Acesso negado: Faça login para conectar sua conta do Mercado Pago.");
}

// Credenciais de produção
$client_id     = "3202249576906512";
$client_secret = "YOUR_PRODUCTION_CLIENT_SECRET"; // Insira seu Client Secret de produção aqui
$redirect_uri  = "https://seudominio.com/mp_callback.php"; // Mesma URL cadastrada no painel

// Captura o código de autorização enviado pelo Mercado Pago
$code = isset($_GET['code']) ? $_GET['code'] : '';
if (empty($code)) {
    die("Código de autorização não recebido.");
}

// Prepara os dados para solicitar o access token via OAuth
$url = "https://api.mercadopago.com/oauth/token";
$data = [
    "grant_type"    => "authorization_code",
    "client_id"     => $client_id,
    "client_secret" => $client_secret,
    "code"          => $code,
    "redirect_uri"  => $redirect_uri
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/x-www-form-urlencoded",
        "method"  => "POST",
        "content" => http_build_query($data)
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($url, false, $context);
$result   = json_decode($response, true);

if (!isset($result['access_token']) || !isset($result['user_id'])) {
    die("Erro ao obter as credenciais do Mercado Pago: " . $response);
}

$mp_access_token = $result['access_token'];
$mp_user_id      = $result['user_id'];

// Conecta ao banco de dados e atualiza a tabela "administradores" para o promotor logado
$promotor_id = $_SESSION['user_id'];
try {
    // Atualize os parâmetros abaixo conforme seu ambiente de produção
    $conn = new PDO("mysql:host=localhost;dbname=SistemaIngressos", "root", "root");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("UPDATE administradores SET mp_user_id = ?, mp_access_token = ? WHERE id = ?");
    $stmt->execute([$mp_user_id, $mp_access_token, $promotor_id]);

    echo "Conta Mercado Pago conectada com sucesso!";
} catch (PDOException $e) {
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}
?>
