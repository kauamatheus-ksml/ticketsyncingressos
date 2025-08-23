<?php
// processa_pagamento.php
session_start();
require 'conexao.php';
require __DIR__ . '/vendor/autoload.php'; // SDK Mercado Pago

// -----------------------------------------------------
// 1) Configuração do Mercado Pago (Produção)
// -----------------------------------------------------
MercadoPago\SDK::setAccessToken("APP_USR-8858227224543322-032218-f13cf5f906ba504b239cf9c16f42d1e3-2320640278");

// Verifica se o evento_id está disponível na sessão
if (!isset($_SESSION['evento_id'])) {
    die("Evento não especificado.");
}
$evento_id = $_SESSION['evento_id'];

// -----------------------------------------------------
// 2) Recupera dados básicos do pedido (ex. da sessão)
// -----------------------------------------------------
$nomeCompleto   = trim($_SESSION['form_nome']  ?? '');
$emailComprador = trim($_SESSION['form_email'] ?? '');
$totalPedido    = floatval($_SESSION['total_pedido'] ?? 0);

// Trata nome / sobrenome
$nomePartes   = explode(" ", $nomeCompleto);
$primeiroNome = $nomePartes[0] ?? 'Cliente';
$sobrenome    = (count($nomePartes) > 1) ? implode(" ", array_slice($nomePartes, 1)) : '';

// Gera um ID único para referência do pedido
$orderId = uniqid('pedido_');

// -----------------------------------------------------
// 3) Cria a preferência do Mercado Pago
// -----------------------------------------------------
$preference = new MercadoPago\Preference();

// Cria o item do carrinho
$item = new MercadoPago\Item();
$item->id          = $orderId;
$item->title       = "Compra de Ingressos";
$item->description = "Ingresso para evento exclusivo";
$item->category_id = "tickets";
$item->quantity    = 1;
$item->unit_price  = $totalPedido;

// Adiciona o item à preferência
$preference->items = [$item];

// Define os dados do comprador (payer)
$payer = new stdClass();
$payer->email      = $emailComprador;
$payer->first_name = $primeiroNome;
$payer->last_name  = $sobrenome;
$preference->payer = $payer;

// Define o external_reference (para identificar no webhook)
$preference->external_reference = $orderId;

// Define as URLs de retorno
$preference->back_urls = [
    "success" => "https://ticketsync.com.br/callback.php?pedido={$orderId}",
    "failure" => "https://ticketsync.com.br/callback.php?pedido={$orderId}",
    "pending" => "https://ticketsync.com.br/callback.php?pedido={$orderId}"
];
$preference->auto_return = "approved";

// Define a URL do webhook (notificações)
$preference->notification_url = "https://ticketsync.com.br/webhook.php";

// Salva a preferência
$preference->save();

// -----------------------------------------------------
// 4) Insere o pedido como 'pendente' no BD
//     Inclui created_at com NOW() explicitamente.
// -----------------------------------------------------
$stmt = $conn->prepare("
    INSERT INTO pedidos (order_id, nome, sobrenome, email, valor_total, status, created_at, evento_id)
    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
");
$statusInicio = 'pendente';
$stmt->bind_param("ssssdsi",
    $orderId,
    $primeiroNome,
    $sobrenome,
    $emailComprador,
    $totalPedido,
    $statusInicio,
    $evento_id
);
$stmt->execute();
$stmt->close();

// -----------------------------------------------------
// 5) Redireciona para o checkout do Mercado Pago
// -----------------------------------------------------
header("Location: " . $preference->init_point);
exit();
?>