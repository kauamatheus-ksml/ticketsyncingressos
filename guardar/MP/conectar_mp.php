<?php
session_start();

// Em produção, seu sistema de autenticação deve definir essas variáveis de sessão.
if (!isset($_SESSION['user_id'])) {
    // Se necessário, você pode redirecionar para a página de login.
    die("Acesso negado: Faça login para conectar sua conta do Mercado Pago.");
}

if ($_SESSION['is_promotor'] != 1) {
    die("Acesso negado: Você não tem permissão para conectar ao Mercado Pago.");
}

// Credenciais de produção
$client_id = "3202249576906512";  // Client ID de produção

// Atualize o Redirect URI para o seu domínio de produção (HTTPS obrigatório)
$redirect_uri = "https://seudominio.com/mp_callback.php";

// Monta o link de autorização para o fluxo OAuth
$link_autorizacao = "https://auth.mercadopago.com.br/authorization?client_id={$client_id}&response_type=code&platform_id=mp&redirect_uri=" . urlencode($redirect_uri);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Conectar Conta Mercado Pago</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        a.button { padding: 10px 20px; background-color: #009ee3; color: #fff; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>Conectar Conta Mercado Pago</h2>
    <p>Clique no botão abaixo para conectar sua conta Mercado Pago e receber pagamentos diretamente em sua conta.</p>
    <a href="<?= $link_autorizacao ?>" class="button">Conectar minha conta</a>
</body>
</html>
