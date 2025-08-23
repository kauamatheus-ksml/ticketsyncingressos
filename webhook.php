<?php
// webhook.php

// Inclui a conexão com o BD e o autoload do Composer (incluindo Dompdf, Endroid QR Code e PHPMailer)
require 'conexao.php';
require __DIR__ . '/vendor/autoload.php';

use MercadoPago\Payment;
use MercadoPago\SDK;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// -----------------------------------------------------
// 1) Configuração do Mercado Pago (Produção)
// -----------------------------------------------------
SDK::setAccessToken("APP_USR-8858227224543322-032218-f13cf5f906ba504b239cf9c16f42d1e3-2320640278");

// -----------------------------------------------------
// 2) Captura a notificação JSON do Mercado Pago
// -----------------------------------------------------
$input = file_get_contents("php://input");
$evento = json_decode($input, true);

// Log: registra todas as notificações (opcional)
file_put_contents(
    "webhook_log.txt",
    date('Y-m-d H:i:s') . " - Notificação recebida: " . json_encode($evento, JSON_PRETTY_PRINT) . "\n",
    FILE_APPEND
);

// -----------------------------------------------------
// 3) Verifica se a notificação é "payment" e se há um ID válido
// -----------------------------------------------------
if (isset($evento["type"]) && $evento["type"] === "payment" && isset($evento["data"]["id"])) {
    $pagamento_id = $evento["data"]["id"];

    try {
        // Busca detalhes do pagamento pelo SDK do Mercado Pago
        $pagamento = Payment::find_by_id($pagamento_id);
        if (!$pagamento) {
            file_put_contents(
                "webhook_log.txt",
                date('Y-m-d H:i:s') . " - ERRO: Pagamento ID $pagamento_id não encontrado.\n",
                FILE_APPEND
            );
            http_response_code(404);
            exit();
        }

        // Pega status do pagamento e referência do pedido
        $status    = $pagamento->status ?? null;          // Ex: "approved", "pending", "rejected"
        $reference = $pagamento->external_reference ?? null; // order_id do pedido no sistema

        // Atualiza o pedido na tabela "pedidos" com o status
        if (!empty($status) && !empty($reference)) {
            $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE order_id = ?");
            $stmt->bind_param("ss", $status, $reference);
            $stmt->execute();
            $stmt->close();

            file_put_contents(
                "webhook_log.txt",
                date('Y-m-d H:i:s') . " - SUCESSO: Pagamento $pagamento_id atualizado para '$status' (Pedido: $reference)\n",
                FILE_APPEND
            );

            // Se o pagamento foi aprovado, envia e-mail com ingresso e armazena o PDF e o Ticket Code no banco
            if ($status === "approved") {
                // Buscar dados do pedido e informações do evento
                $stmt = $conn->prepare("
                    SELECT pedidos.email, pedidos.nome, pedidos.valor_total, pedidos.evento_id,
                           eventos.logo, eventos.nome AS evento_nome, eventos.data AS data_evento, eventos.horario, eventos.local, eventos.atracoes
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
                    $emailCliente = $pedido['email'];
                    $nomeCliente  = $pedido['nome'];
                    $valorTotal   = number_format($pedido['valor_total'], 2, ',', '.');
                    $nomeEvento   = $pedido['evento_nome'] ?? "Evento";
                    $dataEvento   = isset($pedido['data_evento']) ? date("d/m/Y", strtotime($pedido['data_evento'])) : "Data não informada";
                    $horarioEvento = !empty($pedido['horario']) ? date("H:i", strtotime($pedido['horario'])) : "";
                    $local        = $pedido['local'] ?? "Local não informado";
                    $atracoes     = !empty($pedido['atracoes']) ? $pedido['atracoes'] : "Não especificadas";

                    // Prepara a logo do evento
                    $logoDB = trim($pedido['logo']);
                    if (!empty($logoDB)) {
                        if (stripos($logoDB, "http") !== 0) {
                            $logo = "https://ticketsync.com.br/" . $logoDB;
                        } else {
                            $logo = $logoDB;
                        }
                    } else {
                        $logo = 'https://ticketsync.com.br/uploads/ticketsyhnklogo.png';
                    }

                    // Gera um Ticket Code (número aleatório de 6 dígitos)
                    $ticketCode = sprintf("%06d", rand(1, 999999));

                    // Gera o QR Code contendo o Ticket Code
                    $writer = new PngWriter();
                    $qrCode = QrCode::create($ticketCode)
                        ->setSize(150)
                        ->setMargin(10);
                    $resultQr = $writer->write($qrCode);
                    $qrDataUri = $resultQr->getDataUri();

                    // Monta o conteúdo HTML para o ingresso
                    $html = "
                    <html>
                    <head>
                      <meta charset='UTF-8'>
                      <title>Ingresso - Order {$reference}</title>
                      <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                        .ticket-container { max-width: 600px; margin: 20px auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
                        .header { background-color: #002f6d; padding: 20px; text-align: center; }
                        .header img { max-width: 200px; }
                        .content { padding: 20px; }
                        .content h2 { color: #002f6d; margin-bottom: 10px; }
                        .content .info { margin-bottom: 10px; line-height: 1.5; }
                        .qrcode { text-align: center; margin-top: 20px; }
                        .qrcode p { font-size: 12px; color: #555; margin: 5px 0 0; }
                        .footer { background-color: #002f6d; padding: 10px; text-align: center; color: #fff; font-size: 12px; }
                      </style>
                    </head>
                    <body>
                      <div class='ticket-container'>
                        <div class='header'>
                          <img src='{$logo}' alt='Logo Evento'>
                        </div>
                        <div class='content'>
                          <h2>Ingresso Confirmado</h2>
                          <div class='info'>
                            <strong>Evento:</strong> " . htmlspecialchars($nomeEvento) . "<br>
                            <strong>Data:</strong> $dataEvento" . ($horarioEvento ? " - $horarioEvento" : "") . "<br>
                            <strong>Local:</strong> " . htmlspecialchars($local) . "<br>
                            <strong>Atrações:</strong> " . htmlspecialchars($atracoes) . "
                          </div>
                          <hr>
                          <div class='info'>
                            <strong>Order ID:</strong> " . htmlspecialchars($reference) . "<br>
                            <strong>Cliente:</strong> " . htmlspecialchars($nomeCliente) . "<br>
                            <strong>Email:</strong> " . htmlspecialchars($emailCliente) . "<br>
                            <strong>Valor:</strong> R$ " . $valorTotal . "<br>
                            <strong>Ticket Code:</strong> " . $ticketCode . "
                          </div>
                          <div class='qrcode'>
                            <p>Escaneie o QR Code para validar seu ingresso:</p>
                            <img src='{$qrDataUri}' alt='QR Code' style='width:150px; height:150px;'/>
                          </div>
                          <p style='text-align: center; font-size: 14px;'>Apresente este ingresso na entrada do evento.</p>
                        </div>
                        <div class='footer'>
                          © " . date("Y") . " Ticket Sync. Todos os direitos reservados.
                        </div>
                      </div>
                    </body>
                    </html>
                    ";

                    // Gera o PDF usando Dompdf
                    $dompdf = new Dompdf();
                    $options = $dompdf->getOptions();
                    $options->setIsRemoteEnabled(true);
                    $dompdf->setOptions($options);
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $pdfContent = $dompdf->output();

                    // ENVIA E-MAIL COM PHPMailer
                    $assunto = "Confirmação de Pagamento e Ingresso do $nomeEvento";
                    $mensagemHTML = "
                        <h2>Olá, {$nomeCliente}!</h2>
                        <p>Seu pagamento no valor de <strong>R$ {$valorTotal}</strong> foi aprovado com sucesso!</p>
                        <p>Segue em anexo seu ingresso em PDF.</p>
                        <p><strong>Ticket Code:</strong> {$ticketCode}</p>
                        <p><strong>Equipe Ticket Sync</strong></p>
                    ";

                    $mail = new PHPMailer(true);
                    $mail->CharSet = 'UTF-8';
                    try {
                        /*
                        // Configuração SMTP (descomente se necessário)
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.seudominio.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'usuario@seudominio.com';
                        $mail->Password   = 'senha';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;
                        */
                        $mail->setFrom('contato@ticketsync.com', 'Ticket Sync');
                        $mail->addAddress($emailCliente, $nomeCliente);
                        $mail->isHTML(true);
                        $mail->Subject = $assunto;
                        $mail->Body    = $mensagemHTML;
                        // Anexa o PDF
                        $mail->addStringAttachment($pdfContent, "ingresso_{$reference}.pdf", 'base64', 'application/pdf');

                        $mail->send();
                        file_put_contents(
                            "webhook_log.txt",
                            date('Y-m-d H:i:s') . " - E-MAIL enviado para $emailCliente (INGRESSO APROVADO).\n",
                            FILE_APPEND
                        );
                    } catch (Exception $e) {
                        file_put_contents(
                            "webhook_log.txt",
                            date('Y-m-d H:i:s') . " - ERRO AO ENVIAR E-MAIL: " . $mail->ErrorInfo . "\n",
                            FILE_APPEND
                        );
                    }

                    // Armazena o PDF e o Ticket Code no banco de dados
                    // Certifique-se de que as colunas 'pdf' (LONGBLOB) e 'ticket_code' (VARCHAR) existam na tabela 'pedidos'
                    $stmt_pdf = $conn->prepare("UPDATE pedidos SET pdf = ?, ticket_code = ? WHERE order_id = ?");
                    $stmt_pdf->bind_param("sss", $pdfContent, $ticketCode, $reference);
                    if(!$stmt_pdf->execute()){
                        file_put_contents(
                            "webhook_log.txt",
                            date('Y-m-d H:i:s') . " - ERRO AO ARMAZENAR PDF/Ticket Code: " . $stmt_pdf->error . "\n",
                            FILE_APPEND
                        );
                    }
                    $stmt_pdf->close();
                }
            }
        } else {
            file_put_contents(
                "webhook_log.txt",
                date('Y-m-d H:i:s') . " - ERRO: Status ou referência vazios (ID: $pagamento_id)\n",
                FILE_APPEND
            );
        }
        http_response_code(200);
        exit();
    } catch (Exception $e) {
        file_put_contents(
            "webhook_log.txt",
            date('Y-m-d H:i:s') . " - ERRO CRÍTICO: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        http_response_code(500);
        exit();
    }
}

// Se não for uma notificação de pagamento válida
file_put_contents(
    "webhook_log.txt",
    date('Y-m-d H:i:s') . " - Notificação ignorada (sem type=payment válido).\n",
    FILE_APPEND
);
http_response_code(200);
exit();
?>
