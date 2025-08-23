<?php
// gerar_ingresso.php

include('conexao.php');
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Verifica se o order_id foi informado via GET
if (!isset($_GET['order_id'])) {
    die("Order ID não informado.");
}

$order_id = $_GET['order_id'];

// Consulta os dados do pedido e informações do evento somente se o status for 'approved'
$stmt = $conn->prepare("
    SELECT p.order_id, p.nome, p.sobrenome, p.email, p.valor_total, p.status, p.created_at, 
           e.nome AS evento_nome, e.data, e.horario, e.local, e.atracoes 
    FROM pedidos p
    LEFT JOIN eventos e ON p.evento_id = e.id
    WHERE p.order_id = ? AND LOWER(p.status) = 'approved'
");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($pedido = $result->fetch_assoc()) {

    // Logo fixa definida: sempre será esta, sem depender do banco de dados
    $logo = "https://ticketsync.com.br/uploads/ticketsyhnklogo_branco.png";

    // Formata data, horário e atrações
    $dataEvento = date("d/m/Y", strtotime($pedido['data']));
    $horarioEvento = !empty($pedido['horario']) ? date("H:i", strtotime($pedido['horario'])) : "";
    $atracoes = !empty($pedido['atracoes']) ? $pedido['atracoes'] : "Não especificadas";

    // Gera Ticket Code (número aleatório de 6 dígitos)
    $ticketCode = sprintf("%06d", rand(1, 999999));

    // Gera o QR Code utilizando o Ticket Code
    $writer = new PngWriter();
    $qrCode = QrCode::create($ticketCode)
        ->setSize(150)
        ->setMargin(10);
    $resultQr = $writer->write($qrCode);
    $qrDataUri = $resultQr->getDataUri();

    // Monta o conteúdo HTML do ingresso (layout fixo conforme o exemplo padrão)
    $html = "
    <html>
    <head>
      <meta charset='UTF-8'>
      <title>Ingresso - Order " . htmlspecialchars($pedido['order_id']) . "</title>
      <style>
        body { 
          font-family: Arial, sans-serif; 
          background-color: #f4f4f4; 
          margin: 0; 
          padding: 0; 
        }
        .ticket-container { 
          max-width: 600px; 
          margin: 20px auto; 
          background: #fff; 
          border: 1px solid #ddd; 
          border-radius: 8px; 
          overflow: hidden; 
          box-shadow: 0 2px 15px rgba(0,0,0,0.1); 
        }
        .header { 
          background-color: #002f6d; 
          padding: 20px; 
          text-align: center; 
        }
        .header img { 
          max-width: 200px; 
        }
        .content { 
          padding: 20px; 
        }
        .content h2 { 
          color: #002f6d; 
          margin-bottom: 10px; 
        }
        .content .info { 
          margin-bottom: 10px; 
          line-height: 1.5; 
        }
        hr { 
          border: none; 
          border-top: 1px solid #ddd; 
          margin: 15px 0; 
        }
        .qrcode { 
          text-align: center; 
          margin-top: 20px; 
        }
        .qrcode p { 
          font-size: 12px; 
          color: #555; 
          margin: 5px 0 0; 
        }
        .footer { 
          background-color: #002f6d; 
          padding: 10px; 
          text-align: center; 
          color: #fff; 
          font-size: 12px; 
        }
      </style>
    </head>
    <body>
      <div class='ticket-container'>
        <div class='header'>
          <img src='" . htmlspecialchars($logo) . "' alt='Logo Evento'>
        </div>
        <div class='content'>
          <h2>Ingresso Confirmado</h2>
          <div class='info'>
            <strong>Evento:</strong> " . htmlspecialchars($pedido['evento_nome']) . "<br>
            <strong>Data:</strong> " . $dataEvento . ($horarioEvento ? " - " . $horarioEvento : "") . "<br>
            <strong>Local:</strong> " . htmlspecialchars($pedido['local']) . "<br>
            <strong>Atrações:</strong> " . htmlspecialchars($atracoes) . "
          </div>
          <hr>
          <div class='info'>
            <strong>Order ID:</strong> " . htmlspecialchars($pedido['order_id']) . "<br>
            <strong>Cliente:</strong> " . htmlspecialchars($pedido['nome']) . " " . htmlspecialchars($pedido['sobrenome']) . "<br>
            <strong>Email:</strong> " . htmlspecialchars($pedido['email']) . "<br>
            <strong>Valor:</strong> R$ " . number_format($pedido['valor_total'], 2, ',', '.') . "<br>
            <strong>Ticket Code:</strong> " . $ticketCode . "
          </div>
          <div class='qrcode'>
            <p>Escaneie o QR Code para validar seu ingresso:</p>
            <img src='" . htmlspecialchars($qrDataUri) . "' alt='QR Code' style='width:150px; height:150px;'/><br>
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

    // Cria uma instância do Dompdf e habilita imagens remotas
    $dompdf = new Dompdf();
    $options = $dompdf->getOptions();
    $options->setIsRemoteEnabled(true);
    $dompdf->setOptions($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('ingresso_' . htmlspecialchars($pedido['order_id']) . '.pdf', array("Attachment" => 1));
} else {
    echo "Pedido não encontrado ou não aprovado.";
}

$stmt->close();
$conn->close();
?>
