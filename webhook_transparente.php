<?php
// webhook_transparente.php

// Exibir erros no ambiente de desenvolvimento (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define o caminho absoluto para o arquivo de log
$logFile = __DIR__ . "/webhook_log_transparente.txt";
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Start webhook_transparente.php\n", FILE_APPEND);

// Inclui a conexão com o BD e o autoload do Composer
require 'conexao.php';
require __DIR__ . '/vendor/autoload.php';

use MercadoPago\SDK;
use MercadoPago\Payment;

// -----------------------------------------------------
// 1) Configuração do Mercado Pago (Produção)
// -----------------------------------------------------
SDK::setAccessToken('APP_USR-2363265554596237-031207-969582c8df1a055e7f6db3113142e653-2320640278');

// -----------------------------------------------------
// 2) Captura a notificação JSON enviada pelo Mercado Pago
// -----------------------------------------------------
$input = file_get_contents("php://input");
$evento = json_decode($input, true);

// Log: registra a notificação
file_put_contents(
    $logFile,
    date('Y-m-d H:i:s') . " - Notificação recebida: " . json_encode($evento, JSON_PRETTY_PRINT) . "\n",
    FILE_APPEND
);

// -----------------------------------------------------
// 3) Verifica se a notificação é do tipo "payment" e possui um ID válido
// -----------------------------------------------------
if (isset($evento["type"]) && $evento["type"] === "payment" && isset($evento["data"]["id"])) {
    $pagamento_id = $evento["data"]["id"];

    try {
        // Busca detalhes do pagamento via SDK do Mercado Pago
        $pagamento = Payment::find_by_id($pagamento_id);
        if (!$pagamento) {
            file_put_contents(
                $logFile,
                date('Y-m-d H:i:s') . " - ERRO: Pagamento ID $pagamento_id não encontrado.\n",
                FILE_APPEND
            );
            http_response_code(404);
            exit();
        }

        // Obtém o status do pagamento e a referência do pedido (external_reference)
        $status    = $pagamento->status ?? null;
        $reference = $pagamento->external_reference ?? null;

        // Recupera dados adicionais do PIX (caso queira armazenar)
        $txid = $pagamento->point_of_interaction->transaction_data->txid ?? "";
        $loc_id = isset($pagamento->point_of_interaction->transaction_data->loc_id) 
            ? intval($pagamento->point_of_interaction->transaction_data->loc_id) 
            : 0;
        $end_to_end_id = $pagamento->point_of_interaction->transaction_data->end_to_end_id ?? "";

        // Atualiza o pedido na tabela "pedidos"
        if (!empty($status) && !empty($reference)) {
            $stmt = $conn->prepare("
                UPDATE pedidos 
                SET status = ?, txid_pix = ?, loc_id_pix = ?, end_to_end_id = ? 
                WHERE order_id = ?
            ");
            $stmt->bind_param("ssiss", $status, $txid, $loc_id, $end_to_end_id, $reference);
            $stmt->execute();
            $stmt->close();

            file_put_contents(
                $logFile,
                date('Y-m-d H:i:s') 
                . " - SUCESSO: Pagamento $pagamento_id atualizado para '$status' (Pedido: $reference) | txid: $txid, loc_id: $loc_id, end_to_end_id: $end_to_end_id\n",
                FILE_APPEND
            );

            // Se o pagamento foi aprovado, subtrai estoque
            if ($status === "approved") {
                // Buscar dados do pedido e do evento
                $stmt = $conn->prepare("
                    SELECT 
                        pedidos.email, 
                        pedidos.nome, 
                        pedidos.sobrenome, 
                        pedidos.valor_total, 
                        pedidos.evento_id,
                        pedidos.numero_whatsapp,
                        pedidos.itens_json, 
                        eventos.logo, 
                        eventos.nome AS evento_nome, 
                        eventos.data_inicio AS data_evento, 
                        eventos.hora_inicio AS hora_inicio, 
                        eventos.local, 
                        eventos.atracoes
                    FROM pedidos
                    LEFT JOIN eventos ON pedidos.evento_id = eventos.id
                    WHERE pedidos.order_id = ?
                ");
                $stmt->bind_param("s", $reference);
                $stmt->execute();
                $result = $stmt->get_result();
                $pedido = $result->fetch_assoc();
                $stmt->close();

                if ($pedido) {
                    // SUBTRAI ESTOQUE DE INGRESSOS
                    if (!empty($pedido['itens_json'])) {
                        $itens = json_decode($pedido['itens_json'], true);
                        if (is_array($itens)) {
                            foreach ($itens as $item) {
                                if (isset($item['ingresso_id'], $item['quantidade'])) {
                                    $ingressoId = (int)$item['ingresso_id'];
                                    $comprado   = (int)$item['quantidade'];

                                    $upd = $conn->prepare("UPDATE ingressos SET quantidade = GREATEST(0, quantidade - ?) WHERE id = ?");
                                    $upd->bind_param("ii", $comprado, $ingressoId);
                                    if (!$upd->execute()) {
                                        file_put_contents(
                                            $logFile,
                                            date('Y-m-d H:i:s') . " - ERRO ao atualizar estoque do ingresso $ingressoId: " . $upd->error . "\n",
                                            FILE_APPEND
                                        );
                                    } else {
                                        file_put_contents(
                                            $logFile,
                                            date('Y-m-d H:i:s') . " - Sucesso: Estoque do ingresso $ingressoId -= $comprado\n",
                                            FILE_APPEND
                                        );
                                    }
                                    $upd->close();
                                }
                            }
                        }
                    }

                    // Gera Ticket Code (6 dígitos aleatórios)
                    $ticketCode = sprintf("%06d", rand(1, 999999));

                    // Salva Ticket Code no BD
                    $stmt_ticket = $conn->prepare("UPDATE pedidos SET ticket_code = ? WHERE order_id = ?");
                    $stmt_ticket->bind_param("ss", $ticketCode, $reference);
                    if (!$stmt_ticket->execute()) {
                        file_put_contents(
                            $logFile,
                            date('Y-m-d H:i:s') . " - ERRO AO ARMAZENAR Ticket Code: " . $stmt_ticket->error . "\n",
                            FILE_APPEND
                        );
                    }
                    $stmt_ticket->close();

                    // Dados básicos para processamento
                    $emailCliente    = $pedido['email'];
                    $nomeCliente     = $pedido['nome'] . ' ' . $pedido['sobrenome'];
                    $valorTotal      = number_format($pedido['valor_total'], 2, ',', '.');
                    $nomeEvento      = $pedido['evento_nome'] ?? "Evento";
                    $dataEvento      = isset($pedido['data_evento'])
                                      ? date("d/m/Y", strtotime($pedido['data_evento']))
                                      : "Data não informada";
                    $horarioEvento   = !empty($pedido['hora_inicio'])
                                      ? date("H:i", strtotime($pedido['hora_inicio']))
                                      : "";
                    $local           = $pedido['local'] ?? "Local não informado";
                    $numeroWhatsapp  = $pedido['numero_whatsapp'];

                    // Envia dados ao Make.com
                    $dadosParaMake = [
                        'order_id'       => $reference,
                        'nome_cliente'   => $nomeCliente,
                        'email_cliente'  => $emailCliente,
                        'whatsapp'       => $numeroWhatsapp,
                        'evento'         => $nomeEvento,
                        'data_evento'    => $dataEvento,
                        'horario_evento' => $horarioEvento,
                        'local'          => $local,
                        'valor'          => $valorTotal,
                        'ticket_code'    => $ticketCode
                    ];
                    $makeWebhookUrl = 'https://hook.us2.make.com/8gkjahwbas187xq1wn7tu96atai278qr';
                    $ch = curl_init($makeWebhookUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosParaMake));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $respostaMake = curl_exec($ch);
                    $erroCurl     = curl_error($ch);
                    curl_close($ch);

                    if ($erroCurl) {
                        file_put_contents(
                            $logFile,
                            date('Y-m-d H:i:s') . " - ERRO AO CHAMAR WEBHOOK MAKE: " . $erroCurl . "\n",
                            FILE_APPEND
                        );
                    } else {
                        file_put_contents(
                            $logFile,
                            date('Y-m-d H:i:s') . " - Enviado ao Make.com: " . $respostaMake . "\n",
                            FILE_APPEND
                        );
                    }
                }
            }
        } else {
            file_put_contents(
                $logFile,
                date('Y-m-d H:i:s') . " - ERRO: Status ou reference vazios (ID: $pagamento_id)\n",
                FILE_APPEND
            );
        }
        http_response_code(200);
        exit();
    } catch (Exception $e) {
        file_put_contents(
            $logFile,
            date('Y-m-d H:i:s') . " - ERRO CRÍTICO: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        http_response_code(500);
        exit();
    }
}

// Se não for notificação de payment válido
file_put_contents(
    $logFile,
    date('Y-m-d H:i:s') . " - Notificação ignorada (sem type=payment válido).\n",
    FILE_APPEND
);
http_response_code(200);
exit();