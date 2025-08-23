<?php
// aguardar_pagamento.php
session_start();

$order_id = $_GET['order_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aguardando Pagamento - Ticket Sync</title>
    <link rel="icon" href="uploads/Group 11.svg" type="image/x-icon"/>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .pending-icon {
            font-size: 60px;
            color: #ff9800;
            margin-bottom: 20px;
        }
        h1 {
            color: #ff9800;
            margin-bottom: 20px;
        }
        .order-info {
            background: #fff3e0;
            color: #e65100;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ff9800;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #002f6d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #001a3a;
        }
        .logo {
            margin-bottom: 30px;
        }
        .logo img {
            max-height: 60px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ff9800;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="uploads/ticketsyhnklogo1.png" alt="Ticket Sync Logo">
        </div>
        
        <div class="pending-icon">⏳</div>
        
        <h1>Aguardando Confirmação</h1>
        
        <div class="spinner"></div>
        
        <p>Seu pagamento está sendo processado pela operadora do cartão.</p>
        
        <?php if ($order_id): ?>
        <div class="order-info">
            <strong>Número do Pedido:</strong> #<?php echo htmlspecialchars($order_id); ?><br>
            <strong>Status:</strong> Processando...
        </div>
        <?php endif; ?>
        
        <p>A confirmação pode levar alguns minutos.</p>
        <p>Você receberá um e-mail assim que o pagamento for confirmado.</p>
        
        <a href="index.php" class="btn">Voltar ao Início</a>
        
        <script>
            // Recarrega a página a cada 30 segundos para verificar status
            setTimeout(function() {
                window.location.reload();
            }, 30000);
        </script>
    </div>
</body>
</html>