<?php
// processa_pagamento_cartao.php
session_start();
require 'conexao.php';
require 'config_mercadopago.php';

use MercadoPago\Payment;
use MercadoPago\SDK;

// Verifica se os dados necessários estão na sessão
if (!isset($_SESSION['order_id']) || !isset($_SESSION['total_pedido']) || !isset($_SESSION['form_nome']) || !isset($_SESSION['form_email'])) {
    die("Sessão inválida. Refaça o processo de compra.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Captura os dados do cartão enviados pelo formulário
        $token = $_POST['token'] ?? '';
        $installments = intval($_POST['installments'] ?? 1);
        $payment_method_id = $_POST['payment_method_id'] ?? '';
        $issuer_id = $_POST['issuer_id'] ?? '';
        
        if (!$token) {
            throw new Exception("Token do cartão não encontrado.");
        }

        // Dados do pedido
        $order_id = $_SESSION['order_id'];
        $total_pedido = $_SESSION['total_pedido'];
        $nome_completo = $_SESSION['form_nome'];
        $email = $_SESSION['form_email'];
        $telefone = $_SESSION['form_telefone'] ?? '';

        // Separa nome e sobrenome
        $partes_nome = explode(' ', $nome_completo, 2);
        $primeiro_nome = $partes_nome[0];
        $sobrenome = isset($partes_nome[1]) ? $partes_nome[1] : '';

        // Validações adicionais
        $cpf = $_POST['cpf'] ?? '';
        $cpf = preg_replace('/\D/', '', $cpf);
        
        // Se CPF não foi informado ou é inválido, usar um padrão para teste
        if (strlen($cpf) !== 11 || !$cpf) {
            $cpf = '11111111111'; // CPF de teste válido para Mercado Pago sandbox
        }

        if ($installments < 1 || $installments > 12) {
            throw new Exception("Número de parcelas inválido");
        }

        if ($total_pedido <= 0) {
            throw new Exception("Valor inválido");
        }

        // Cria o pagamento usando SDK v2.x
        $payment = new Payment();
        $payment->transaction_amount = (float)$total_pedido;
        $payment->token = $token;
        $payment->description = "Ingressos Ticket Sync - Pedido #" . $order_id;
        $payment->installments = $installments;
        $payment->payment_method_id = $payment_method_id;
        $payment->external_reference = $order_id;
        $payment->notification_url = "https://ticketsync.com.br/webhook_mercadopago.php";
        $payment->statement_descriptor = "TICKETSYNC";

        // Trata o telefone para separar área e número
        $telefone_limpo = preg_replace('/\D/', '', $telefone);
        $area_code = "";
        $phone_number = "";
        
        if (strlen($telefone_limpo) >= 10) {
            if (strlen($telefone_limpo) == 11) {
                // Remove o 9 inicial se for celular
                $area_code = substr($telefone_limpo, 2, 2);
                $phone_number = substr($telefone_limpo, 4);
            } else {
                $area_code = substr($telefone_limpo, 2, 2);
                $phone_number = substr($telefone_limpo, 4);
            }
        } else {
            // Telefone padrão para teste
            $area_code = "11";
            $phone_number = "999999999";
        }

        // Configura o pagador
        $payment->payer = array(
            "email" => $email,
            "first_name" => $primeiro_nome,
            "last_name" => $sobrenome,
            "phone" => array(
                "area_code" => $area_code,
                "number" => $phone_number
            ),
            "identification" => array(
                "type" => "CPF",
                "number" => $cpf
            )
        );

        // Adiciona issuer_id apenas se não estiver vazio
        if (!empty($issuer_id)) {
            $payment->issuer_id = $issuer_id;
        }

        // Debug: Log dos dados do pagamento antes de salvar
        error_log("=== DEBUG PAGAMENTO CARTÃO ===");
        error_log("Order ID: " . $order_id);
        error_log("Token: " . $token);
        error_log("Amount: " . $total_pedido);
        error_log("Email: " . $email);
        error_log("CPF: " . $cpf);
        error_log("Payment Method: " . $payment_method_id);
        
        // Salva o pagamento
        $payment->save();
        
        // Debug: Log do resultado
        if ($payment->error) {
            error_log("MP Error: " . json_encode($payment->error));
            throw new Exception("Erro do Mercado Pago: " . ($payment->error->message ?? 'Erro desconhecido'));
        }
        
        error_log("Payment ID: " . ($payment->id ?? 'N/A'));
        error_log("Payment Status: " . ($payment->status ?? 'N/A'));

        // Prepara dados para inserção conforme estrutura da tabela
        $evento_id = $_SESSION['evento_id'];
        $status_pedido = $payment->status ?? 'pending';
        $mp_payment_id = $payment->id ?? '';
        
        // Separar nome e sobrenome
        $partes_nome_final = explode(' ', $nome_completo, 2);
        $primeiro_nome_final = $partes_nome_final[0];
        $sobrenome_final = isset($partes_nome_final[1]) ? $partes_nome_final[1] : '';
        
        // Calcular quantidade total
        $ingSess = $_SESSION['ingressos'] ?? [];
        $quantTot = 0;
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
        
        $itensJson = json_encode($itemsJSON, JSON_UNESCAPED_UNICODE);
        
        // Salva o pagamento no banco de dados usando a estrutura exata do PIX
        $stmt = $conn->prepare("
            INSERT INTO pedidos (
                order_id,
                nome,
                sobrenome,
                email,
                valor_total,
                quantidade_total,
                status,
                created_at,
                evento_id,
                numero_whatsapp,
                itens_json
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssdisiss",
            $order_id,            // s
            $primeiro_nome_final, // s
            $sobrenome_final,     // s
            $email,               // s
            $total_pedido,        // d
            $quantTot,           // i
            $status_pedido,      // s
            $evento_id,          // i
            $telefone,           // s
            $itensJson           // s
        );

        // Debug: Log da query antes de executar
        error_log("=== DEBUG INSERÇÃO BANCO ===");
        error_log("Order ID: " . $order_id);
        error_log("Nome: " . $primeiro_nome_final);
        error_log("Sobrenome: " . $sobrenome_final);
        error_log("Email: " . $email);
        error_log("Valor: " . $total_pedido);
        error_log("Quantidade: " . $quantTot);
        error_log("Status: " . $status_pedido);
        error_log("Evento ID: " . $evento_id);
        error_log("Telefone: " . $telefone);
        error_log("MP Payment ID: " . $mp_payment_id);

        if ($stmt->execute()) {
            // Limpa a sessão
            unset($_SESSION['order_id'], $_SESSION['evento_id'], $_SESSION['total_pedido'], 
                  $_SESSION['form_nome'], $_SESSION['form_email'], $_SESSION['form_telefone'], 
                  $_SESSION['ingressos'], $_SESSION['tipo_pagamento']);

            // Redireciona baseado no status do pagamento
            if ($payment->status === 'approved') {
                header("Location: pagamento_sucesso.php?order_id=" . $order_id);
            } elseif ($payment->status === 'pending' || $payment->status === 'in_process') {
                header("Location: aguardar_pagamento.php?order_id=" . $order_id);
            } else {
                header("Location: pagamento_erro.php?error=" . urlencode("Pagamento rejeitado: " . $payment->status));
            }
            exit();
        } else {
            $sql_error = $stmt->error;
            error_log("SQL Error: " . $sql_error);
            throw new Exception("Erro ao salvar pedido no banco de dados: " . $sql_error);
        }

    } catch (Exception $e) {
        $error_message = "Erro no pagamento: " . $e->getMessage();
        error_log("Payment Error: " . $e->getMessage());
        
        // Log adicional para debug
        if (isset($payment) && $payment->error) {
            error_log("MP Error Details: " . json_encode($payment->error));
            $error_message .= " - " . $payment->error->message;
        }
    }

    if (isset($error_message)) {
        // Redireciona para página de erro
        header("Location: pagamento_erro.php?error=" . urlencode($error_message));
        exit();
    }
}

// Se chegou aqui sem POST, redireciona para compra
header("Location: comprar_ingresso.php");
exit();
?>