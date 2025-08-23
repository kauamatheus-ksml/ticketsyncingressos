<?php 
session_start();
require 'vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;

// Verifica se o QR Code está disponível
if (!isset($_SESSION['qr_code_payload'])) {
    die("QR Code não disponível.");
}

$qrCodePayload = $_SESSION['qr_code_payload'];
// Recupera o valor total do pedido da sessão e formata (usa 0,00 se não definido)
$valorPago = isset($_SESSION['total_pedido']) ? number_format($_SESSION['total_pedido'], 2, ',', '.') : "0,00";

// Gera o QR Code com fundo transparente
$result = Builder::create()
    ->data($qrCodePayload)
    ->size(300)
    ->margin(10)
    ->foregroundColor(new Color(0, 0, 0))
    ->backgroundColor(new Color(255, 255, 255, 0)) // Fundo transparente
    ->build();

// Converte a imagem para Data URI
$imageData = $result->getString();
$mimeType = $result->getMimeType();
$dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Pagamento via PIX</title>
    <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/exibir_qrcode.css">
</head>
<body>
    <header>
        <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
    </header>
    <div class="container">
        <h2>Pagamento via PIX</h2>
        <p id="infoMessage">Escaneie o QR Code ou copie o código PIX para efetuar o pagamento.</p>
        <!-- Exibe o valor que o cliente está pagando -->
        <div class="valor-pago-container">
            Valor a Pagar: R$ <span id="valorPago"><?php echo htmlspecialchars($valorPago); ?></span>
        </div>
        <!-- Exibe o QR Code -->
        <img class="qrcode-img" src="<?php echo $dataUri; ?>" alt="QR Code Pix">
        <!-- Exibe o código PIX -->
        <div class="pix-code-container">
            <input type="text" id="pixCode" class="pix-code" value="<?php echo htmlspecialchars($qrCodePayload); ?>" readonly>
            <br>
            <button id="copyButton" class="copy-button" onclick="copyPixCode()">Copiar Código</button>
        </div>
        <!-- Área para notificações -->
        <div id="notification" class="notification"></div>
        <!-- Exibe o status do pagamento -->
        <div class="status-message" id="statusMessage">Status: Pendente</div>
        <!-- Container de aprovação -->
        <div class="approved-container" id="approvedContainer">
            <h3>Parabéns!</h3>
            <p>Seu pagamento foi aprovado com sucesso. Muito obrigado por confiar na Ticket Sync! Seu ingresso está garantido.</p>
            <button onclick="window.location.href='index.php'">Voltar ao Início</button>
            <button onclick="window.location.href='detalhes_evento.php?id=<?php echo isset($_SESSION['evento_id']) ? $_SESSION['evento_id'] : '' ?>'">Comprar Novamente</button>
            <button onclick="window.location.href='meus_ingressos.php'">Meus Ingressos</button>
        </div>
    </div>
    
    <script>
        // Função para copiar o código PIX
        function copyPixCode() {
            var copyText = document.getElementById("pixCode");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // Para dispositivos móveis
            document.execCommand("copy");
            var btn = document.getElementById("copyButton");
            btn.innerHTML = "Copiado!";
            setTimeout(function() {
                btn.innerHTML = "Copiar Código";
            }, 2000);
        }

        // Exibe notificações (sucesso ou erro)
        function showNotification(message, type) {
            var notification = document.getElementById("notification");
            notification.innerHTML = message;
            notification.className = "notification " + type;
            notification.style.display = "block";
            setTimeout(function() {
                notification.style.display = "none";
            }, 3000);
        }

        // Verifica periodicamente o status do pagamento via polling
        var pollingInterval = setInterval(function() {
            fetch('verifica_pagamento.php')
                .then(response => response.text())
                .then(data => {
                    var status = data.trim();
                    var statusEl = document.getElementById("statusMessage");
                    statusEl.innerHTML = "Status: " + status;
                    // Se aprovado, exibe notificação e altera layout
                    if (status === "approved") {
                        showNotification("Pagamento aprovado com sucesso!", "success");
                        // Oculta QR Code e código PIX
                        document.querySelector('.qrcode-img').style.display = 'none';
                        document.querySelector('.pix-code-container').style.display = 'none';
                        document.getElementById("infoMessage").innerHTML = "";
                        // Altera o fundo para um tom verde claro
                        document.body.style.background = "#d4edda";
                        // Exibe o container de aprovação
                        document.getElementById("approvedContainer").style.display = "block";
                        clearInterval(pollingInterval);
                        // Após 10 minutos, redireciona para a página inicial
                        setTimeout(function() {
                            window.location.href = "index.php";
                        }, 600000);
                    } else if (status === "rejected") {
                        showNotification("Pagamento rejeitado. Por favor, tente novamente.", "error");
                    }
                })
                .catch(error => {
                    console.error("Erro na verificação do pagamento:", error);
                    showNotification("Erro ao verificar o pagamento. Tente novamente.", "error");
                });
        }, 5000);
    </script>
    <script src="jdkfront.js"></script>
</body>
</html>
