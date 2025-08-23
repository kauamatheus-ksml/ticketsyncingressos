<?php
// criar_pagamento_pix.php
require 'config_asaas.php';

function criarCliente($nome, $email, $cpf) {
    $data = [
        "name"         => $nome,
        "email"        => $email,
        "cpfCnpj"      => $cpf,
        "phone"        => "3432212345",      // Telefone fixo (10 dígitos)
        "mobilePhone"  => "34991234567",     // Celular (11 dígitos)
        "address"      => "Rua Teste",
        "addressNumber"=> "123",
        "province"     => "Bairro Teste",
        "postalCode"   => "38700000",
        "city"         => "Patos de Minas",
        "state"        => "MG",
        "country"      => "BRA"
    ];

    $ch = curl_init(ASAAS_API_URL . "customers");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Habilita seguimento de redirecionamentos
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "User-Agent: MeuSistema/1.0",
        "Authorization: Bearer " . ASAAS_API_KEY
    ]);

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($response)) {
        $decodedResponse = json_decode($response, true);
    } else {
        $decodedResponse = ["error" => "Resposta da API está vazia."];
    }

    if ($http_code != 200) {
        echo "<pre>🚨 ERRO AO CRIAR CLIENTE 🚨\n";
        echo "🔴 Código HTTP: " . $http_code . "\n";
        echo "🔴 Erro CURL: " . ($curl_error ?: "Nenhum erro de conexão") . "\n";
        echo "🔴 Resposta da API:\n";
        print_r($decodedResponse);
        echo "</pre>";
        exit;
    }

    return $decodedResponse;
}

function criarPagamentoPix($clienteId, $valor, $descricao) {
    $data = [
        "customer"    => $clienteId,
        "billingType" => "PIX",
        "value"       => $valor,
        "dueDate"     => date("Y-m-d"),
        "description" => $descricao
    ];

    $ch = curl_init(ASAAS_API_URL . "payments");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Habilita redirecionamento
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "User-Agent: MeuSistema/1.0",
        "Authorization: Bearer " . ASAAS_API_KEY
    ]);

    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($response)) {
        $decodedResponse = json_decode($response, true);
    } else {
        $decodedResponse = ["error" => "Resposta da API está vazia."];
    }

    if ($http_code != 200) {
        echo "<pre>🚨 ERRO AO CRIAR PAGAMENTO PIX 🚨\n";
        echo "🔴 Código HTTP: " . $http_code . "\n";
        echo "🔴 Erro CURL: " . ($curl_error ?: "Nenhum erro de conexão") . "\n";
        echo "🔴 Resposta da API:\n";
        print_r($decodedResponse);
        echo "</pre>";
        exit;
    }

    return $decodedResponse;
}

// Criar um cliente automaticamente
$cliente = criarCliente("Cliente Teste", "kauanupix@gmail.com", "15692134616");

if (!isset($cliente['id'])) {
    die(json_encode(['error' => 'Erro ao criar cliente no Asaas.']));
}

$idCliente = $cliente['id'];

// Criar pagamento PIX
$valor     = 10.00; // Valor em reais
$descricao = "Compra de ingresso";

$pagamento = criarPagamentoPix($idCliente, $valor, $descricao);

if (!empty($pagamento['id']) && !empty($pagamento['invoiceUrl'])) {
    echo json_encode([
        'id'           => $pagamento['id'],
        'qrCode'       => $pagamento['bankSlipUrl'],  // Pode vir como null se o QR Code não for gerado automaticamente
        'pixCopiaCola' => $pagamento['invoiceUrl']
    ]);
} else {
    echo json_encode(['error' => 'Erro ao criar pagamento PIX.', 'detalhes' => $pagamento]);
}

echo "<pre>🚨 RESPOSTA COMPLETA DA API DO ASAAS 🚨\n";
print_r($pagamento);
echo "</pre>";
exit;
?>
