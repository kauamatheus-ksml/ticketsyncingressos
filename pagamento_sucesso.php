<?php
// pagamento_sucesso.php
session_start();

$order_id = $_GET['order_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Aprovado - Ticket Sync</title>
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
        .success-icon {
            font-size: 60px;
            color: #4caf50;
            margin-bottom: 20px;
        }
        h1 {
            color: #4caf50;
            margin-bottom: 20px;
        }
        .order-info {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4caf50;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <img src="uploads/ticketsyhnklogo1.png" alt="Ticket Sync Logo">
        </div>
        
        <div class="success-icon">✅</div>
        
        <h1>Pagamento Aprovado!</h1>
        
        <p>Parabéns! Seu pagamento foi processado com sucesso.</p>
        
        <?php if ($order_id): ?>
        <div class="order-info">
            <strong>Número do Pedido:</strong> #<?php echo htmlspecialchars($order_id); ?><br>
            <strong>Status:</strong> Aprovado ✅
        </div>
        <?php endif; ?>
        
        <p>Seus ingressos serão enviados por e-mail em breve.</p>
        <p>Guarde o número do seu pedido para futuras consultas.</p>
        
        <a href="meus_ingressos.php" class="btn">Ver Meus Ingressos</a>
        <a href="index.php" class="btn">Voltar ao Início</a>
    </div>
</body>
</html>