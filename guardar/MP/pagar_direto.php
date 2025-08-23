<?php
session_start();

// Em produção, o usuário deve estar logado; caso não esteja, o sistema deve redirecionar para o login.
if (!isset($_SESSION['user_id']) || $_SESSION['is_promotor'] != 1) {
    die("Acesso negado: Você precisa estar logado como promotor para acessar esta página.");
}

$promotor_id = $_SESSION['user_id'];

// Conecta ao banco de dados para recuperar as credenciais do Mercado Pago do promotor
try {
    $conn = new PDO("mysql:host=localhost;dbname=SistemaIngressos", "root", "root");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT mp_access_token FROM administradores WHERE id = ?");
    $stmt->execute([$promotor_id]);
    $promotor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotor || empty($promotor['mp_access_token'])) {
        die("Credenciais do Mercado Pago não encontradas para este promotor.");
    }
    $access_token = $promotor['mp_access_token'];
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// Dados da transação: venda de 1 centavo via PIX
$transaction_amount = 0.01; // R$ 0,01
$description = "Venda teste de 1 centavo via PIX";

// Dados do pagador (exemplo de teste)
$payer_email = "cliente@exemplo.com";
$payer_first_name = "Cliente";
$payer_last_name = "Exemplo";

// Usamos um CPF de teste
$identification = [
    "type"   => "CPF",
    "number" => "19119119100"
];

$data = [
    "transaction_amount" => $transaction_amount,
    "description"        => $description,
    "payment_method_id"  => "pix",
    "payer"              => [
        "email"          => $payer_email,
        "first_name"     => $payer_first_name,
        "last_name"      => $payer_last_name,
        "identification" => $identification
    ]
];

// Gera uma chave única para idempotência (para evitar transações duplicadas)
$idempotency_key = uniqid("mp_", true);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json",
    "X-Idempotency-Key: $idempotency_key"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

echo "<h3>HTTP Code: $httpCode</h3>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Exibe o código PIX (Copy & Paste), se disponível
if (isset($result['point_of_interaction']['transaction_data']['qr_code'])) {
    echo "<h3>PIX Copy & Paste:</h3>";
    echo "<p>" . $result['point_of_interaction']['transaction_data']['qr_code'] . "</p>";
} else {
    echo "<p>Código PIX não disponível.</p>";
}
?>
