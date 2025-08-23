<?php
// pagamento_erro.php
session_start();

$error_message = $_GET['error'] ?? 'Erro desconhecido no pagamento';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro no Pagamento - Ticket Sync</title>
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
        .error-icon {
            font-size: 60px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #e74c3c;
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
        
        <div class="error-icon">❌</div>
        
        <h1>Erro no Pagamento</h1>
        
        <p>Ops! Ocorreu um problema ao processar seu pagamento.</p>
        
        <div class="error-message">
            <strong>Detalhes do erro:</strong><br>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        
        <p>Por favor, verifique os dados do cartão e tente novamente.</p>
        
        <a href="comprar_ingresso.php" class="btn">Tentar Novamente</a>
        <a href="index.php" class="btn">Voltar ao Início</a>
    </div>
</body>
</html>