<?php
// processa_pagamento_pix2.php
session_start();
require 'conexao.php';
require __DIR__ . '/vendor/autoload.php';

use MercadoPago\SDK;
use MercadoPago\Payment;

// -----------------------------------------------------
// 1) Configuração do Mercado Pago (Produção)
// -----------------------------------------------------
SDK::setAccessToken('APP_USR-5930515674148384-041709-d31039828b8d5e4b797962c85c996278-2320640278');

// -----------------------------------------------------
// 2) Verifica se o evento está disponível na sessão
// -----------------------------------------------------
if (!isset($_SESSION['evento_id'])) {
    die("Evento não especificado.");
}
$evento_id = $_SESSION['evento_id'];

// -----------------------------------------------------
// 2.1) Recupera os detalhes do evento para uso na descrição do pagamento
// -----------------------------------------------------
$stmt_event = $conn->prepare("SELECT * FROM eventos WHERE id = ?");
$stmt_event->bind_param("i", $evento_id);
$stmt_event->execute();
$result_event = $stmt_event->get_result();
if ($result_event->num_rows > 0) {
    $evento_details = $result_event->fetch_assoc();
} else {
    die("Evento não encontrado.");
}
$stmt_event->close();

// -----------------------------------------------------
// 3) Recupera dados básicos do pedido (armazenados na sessão)
// -----------------------------------------------------
$nomeCompleto   = trim($_SESSION['form_nome']     ?? '');
$emailComprador = trim($_SESSION['form_email']    ?? '');
$telefone       = trim($_SESSION['form_telefone'] ?? ''); 

/* ---------- NOVO BLOCO: monta itens, total, quantidade_total ---------- */
$ingSess   = $_SESSION['ingressos'] ?? [];
$quantTot  = 0;
$itemsJSON = [];

foreach ($ingSess as $it) {
    $q = intval($it['quantidade']);
    if ($q > 0) {
        $quantTot += $q;
        $itemsJSON[] = [
            'ingresso_id' => intval($it['id']),
            'descricao'   => $it['descricao'],
            'preco'       => floatval($it['preco']),
            'quantidade'  => $q
        ];
    }
}

$totalPedido = 0;
foreach ($itemsJSON as $it) {
    $totalPedido += $it['preco'] * $it['quantidade'];
}
$totalPedido += $totalPedido * 0.10;           // taxa de 10 %
// <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
// Garante que o valor seja um float válido com duas casas decimais
$totalPedido = round((float)$totalPedido, 2);

// Valida para não enviar zero ou negativo ao Mercado Pago
if ($totalPedido <= 0) {
    die("Erro: valor total do pedido inválido ({$totalPedido}).");
}
// >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>

$itensJson = json_encode($itemsJSON, JSON_UNESCAPED_UNICODE);
/* ---------------------------------------------------------------------- */


// Trata o nome para obter primeiro nome e sobrenome
$nomePartes   = explode(" ", $nomeCompleto);
$primeiroNome = $nomePartes[0] ?? 'Cliente';
$sobrenome    = (count($nomePartes) > 1) ? implode(" ", array_slice($nomePartes, 1)) : '';

// -----------------------------------------------------
// 4) Gera um ID único para referência do pedido (8 dígitos sequenciais)
// -----------------------------------------------------
$sqlOrder = "SELECT MAX(CAST(order_id AS UNSIGNED)) AS last_order FROM pedidos";
$resultOrder = $conn->query($sqlOrder);
if ($resultOrder && $row = $resultOrder->fetch_assoc()) {
    $lastOrder = (int)$row['last_order'];
    $newOrder = $lastOrder + 1;
} else {
    $newOrder = 1;
}
$orderId = str_pad($newOrder, 8, "0", STR_PAD_LEFT);

// -----------------------------------------------------
// 5) Insere o pedido como 'pendente' no banco de dados
// -----------------------------------------------------
$statusInicio = 'pendente';
$stmt = $conn->prepare("
    INSERT INTO pedidos (
        order_id,
        nome,
        sobrenome,
        email,
        valor_total,
        quantidade_total,          /* NOVO */
        status,
        created_at,
        evento_id,
        numero_whatsapp,
        itens_json                 /* NOVO */
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
");

$stmt->bind_param(
    "ssssdisiss",
    $orderId,            // s
    $primeiroNome,       // s
    $sobrenome,          // s
    $emailComprador,     // s
    $totalPedido,        // d
    $quantTot,           // i   ← quantidade_total
    $statusInicio,       // s
    $evento_id,          // i
    $telefone,           // s
    $itensJson           // s   ← JSON com todos os ingressos
);

$stmt->execute();
$stmt->close();

// Armazena o order_id na sessão para uso no pagamento e no webhook
$_SESSION['order_id'] = $orderId;

// -----------------------------------------------------
// 6) Recupera o device ID armazenado na sessão (se houver)
// -----------------------------------------------------
$device_id = $_SESSION['device_id'] ?? "";

// -----------------------------------------------------
// 7) Cria o pagamento via PIX utilizando o SDK do Mercado Pago
// -----------------------------------------------------
$payment = new Payment();
$payment->transaction_amount = $totalPedido;
$payment->description = "Compra de ingresso para " 
    . $evento_details['nome'] 
    . " em " . date("d/m/Y", strtotime($evento_details['data_inicio'])) 
    . " às " . date("H:i", strtotime($evento_details['hora_inicio']));
$payment->payment_method_id = "pix";

// Dados do Comprador (obrigatórios para aumentar o índice de aprovação)
$payment->payer = [
    "email"      => $emailComprador,
    "first_name" => $primeiroNome,
    "last_name"  => $sobrenome,
    "phone" => [
        "area_code" => "11",
        "number"    => "987654321"  // Substitua pelo número real, se disponível
    ],
    "address" => [
        "street_name"   => "Rua Exemplo",
        "street_number" => 123,
        "zip_code"      => "12345678"
    ]
];

// Itens do pedido enviados via additional_info e adiciona também o device_id
$payment->additional_info = [
    "items" => [
        [
            "id"          => "ingresso_001",                    
            "title"       => "Ingresso para Evento",              
            "description" => "Ingresso com acesso completo",      
            "category_id" => "ingressos",                         
            "quantity"    => 1,                                 
            "unit_price"  => $totalPedido                       
        ]
    ],
    "device_id" => $device_id
];

// Meio de pagamento: Código do emissor (se aplicável)
$payment->issuer_id = "123"; // Substitua pelo ID correto do emissor, se necessário

// Dados adicionais para melhorar a aprovação dos pagamentos:
$payment->statement_descriptor = "NOMEEMFATURA";
$payment->binary_mode = true;  // Se necessário para resposta binária imediata

// Define o external_reference com o identificador único do pedido
$payment->external_reference = $orderId;

// Salva o pagamento
$payment->save();

// Verifica se houve erro na criação do pagamento
if (isset($payment->error)) {
    die("Erro ao criar o pagamento: " . json_encode($payment->error));
}

// Armazena o ID do pagamento na sessão para verificação
$_SESSION['payment_id'] = $payment->id;

// Obtém dados do QR Code
$qrCodePayload = $payment->point_of_interaction->transaction_data->qr_code ?? null;
if (!$qrCodePayload) {
    die("Não foi possível obter os dados do QR Code.");
}
$_SESSION['qr_code_payload'] = $qrCodePayload;

// -----------------------------------------------------
// 8) Redireciona para a página que exibe o QR Code para pagamento
// -----------------------------------------------------
header("Location: exibe_qrcode.php");
exit();
?>
