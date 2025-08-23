<?php
// processa_pagamento_pix.php
session_start();
require 'conexao.php';

// Carrega a SDK GerenciaNet (instalada via Composer)
require __DIR__ . '/vendor/autoload.php';

// Carrega a configuração do Pix (produção ou sandbox)
$config = require __DIR__ . '/gerencianet_config.php';

// Verifica se o evento está na sessão
if (!isset($_SESSION['evento_id'])) {
    die("Evento não especificado.");
}
$evento_id = $_SESSION['evento_id'];

// Recupera dados do pedido
$nomeCompleto   = trim($_SESSION['form_nome'] ?? '');
$emailComprador = trim($_SESSION['form_email'] ?? '');
$totalPedido    = floatval($_SESSION['total_pedido'] ?? 0);
$cpfComprador   = $_SESSION['cpf'] ?? ''; // CPF informado pelo cliente

// Gera um ID único para referência do pedido
$orderId = uniqid('pedido_');

// Separa nome e sobrenome
$nomePartes   = explode(" ", $nomeCompleto);
$primeiroNome = $nomePartes[0] ?? 'Cliente';
$sobrenome    = (count($nomePartes) > 1) ? implode(" ", array_slice($nomePartes, 1)) : '';

// 1) Inicializa a SDK GerenciaNet com as opções definidas
$options = [
    'client_id'     => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'sandbox'       => $config['sandbox'],       // false para produção
    'timeout'       => $config['timeout'],
    'pix_cert'      => $config['pix_cert']         // Caminho do certificado
];

try {
    $api = new Gerencianet\Gerencianet($options);

    // Monta os dados da cobrança Pix
    $body_cobranca = [
        "calendario" => [
            "expiracao" => 3600 // Expira em 1 hora
        ],
        "devedor" => [
            "nome" => $nomeCompleto,
            "cpf"  => $cpfComprador
        ],
        "valor" => [
            "original" => number_format($totalPedido, 2, '.', '')
        ],
        "chave" => "e2958669-a56c-4a3c-95fb-1926582aaa91", // Sua chave Pix de produção
        "solicitacaoPagador" => "Ingresso para o evento XYZ"
    ];
    
    // 2) Cria a cobrança immediateCharge
    $cobranca = $api->pixCreateImmediateCharge([], $body_cobranca);
    
    // Obtém os dados da resposta
    $locId = $cobranca['loc']['id'];
    $txid  = $cobranca['txid'];
    
    // 3) Gera o QR Code (imagem em Base64 com prefixo "data:image/png;base64,")
    $qrCode = $api->pixGenerateQRCode(['id' => $locId], []);
    $qrImage = $qrCode['imagemQrcode'];
    
    // 4) Insere no banco o pedido com status 'pendente'
    $stmt = $conn->prepare("
        INSERT INTO pedidos (
            order_id, nome, sobrenome, email, valor_total, status, created_at, evento_id, txid_pix, loc_id_pix, cpf
        ) VALUES (
            ?, ?, ?, ?, ?, 'pendente', NOW(), ?, ?, ?, ?
        )
    ");
    if (!$stmt) {
        die("Erro na preparação da query: " . $conn->error);
    }
    $stmt->bind_param(
        "ssssdisis",
        $orderId,
        $primeiroNome,
        $sobrenome,
        $emailComprador,
        $totalPedido,
        $evento_id,
        $txid,
        $locId,
        $cpfComprador
    );
    $stmt->execute();
    $stmt->close();
    
} catch (Exception $e) {
    die("Erro ao criar cobrança Pix: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Pagamento Pix</title>
  <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
</head>
<body>
  <h1>Pagamento via Pix (GerenciaNet)</h1>
  <p>Olá, <?php echo htmlspecialchars($nomeCompleto); ?>. Seu pedido é <strong><?php echo $orderId; ?></strong>.</p>
  <p>Valor total: R$ <?php echo number_format($totalPedido, 2, ',', '.'); ?></p>

  <div>
    <h3>Escaneie o QR Code abaixo para pagar:</h3>
    <img src="<?php echo $qrImage; ?>" alt="Pix QR Code" />
  </div>

  <p>Após efetuar o pagamento, aguarde a confirmação.</p>
  <p>Você será notificado via e-mail assim que o pagamento for aprovado.</p>
</body>
</html>



