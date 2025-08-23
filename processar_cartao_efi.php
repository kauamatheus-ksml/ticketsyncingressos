<?php
session_start();
require 'conexao.php';
require 'vendor/autoload.php';

use Efi\Exception\EfiException;
use Efi\EfiPay;

// Avisa ao browser que a saída é JSON
header('Content-Type: application/json; charset=UTF-8');

try {
    // 1) Recebe e decodifica o JSON
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('JSON inválido');
    }

    // 2) Validação dos campos obrigatórios
    foreach (['charge_id','payment_token','amount'] as $f) {
        if (empty($data[$f])) {
            throw new Exception("Campo obrigatório faltando: $f");
        }
    }

    // 3) Configura o SDK EFI
    $options = [
        "client_id"     => "Client_Id_783a8110badd245440ce77e39b211235a9e2add8",
        "client_secret" => "Client_Secret_aca79cb5839468992c8ed9c60b706700a19b3a41",
        "certificate"   => __DIR__."/prod.pem",
        "sandbox"       => false,
        "timeout"       => 30
    ];
    $efi = new EfiPay($options);

    // 4) Monta o corpo da atualização de cobrança
    $params = ['id' => $data['charge_id']];
    $body = [
        "payment" => [
            "credit_card" => [
                "payment_token" => $data['payment_token'],
                "installments"  => intval($data['installments'] ?? 1),
                // opcional: informe o valor se a API exigir
                "value"         => intval($data['amount']),
                "customer"      => [
                    "name"  => $_SESSION['form_nome'],
                    "email" => $_SESSION['form_email'],
                    "cpf"   => $_SESSION['cpf']
                ]
            ]
        ]
    ];

    // 5) Executa a atualização/pagamento
    $response = $efi->updateCharge($params, $body);

    // 6) Verifica se foi aprovado
    if (!isset($response['data']['status']) || $response['data']['status'] !== 'approved') {
        $msg = $response['errors'][0]['message'] ?? "Status inesperado: {$response['data']['status']}";
        throw new Exception("Pagamento não aprovado: $msg");
    }

    // 7) Atualiza o pedido no banco
    $stmt = $conn->prepare("
        UPDATE pedidos
        SET status = 'approved',
            end_to_end_id = ?
        WHERE order_id = ?
    ");
    $stmt->bind_param(
        "ss",
        $response['data']['end_to_end_id'],
        $data['charge_id']
    );
    $stmt->execute();
    $stmt->close();

    // 8) Retorna sucesso
    echo json_encode(['success' => true]);

} catch (EfiException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => "ERRO EFI ({$e->code}): {$e->errorDescription}"
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
