<?php
// redefinir.php

include('conexao.php');

$message = "";

// Processa a redefinição de senha sem token
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recebe o e-mail, nova senha e confirmação de senha
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $novaSenha = trim($_POST['nova_senha']);
    $confirmarSenha = trim($_POST['confirmar_senha']);
    
    if ($novaSenha !== $confirmarSenha) {
        $message = "As senhas não conferem. Tente novamente.";
    } elseif (strlen($novaSenha) < 6) {
        $message = "A senha deve ter no mínimo 6 caracteres.";
    } else {
        // Busca o promotor pelo e-mail
        $stmt = $conn->prepare("SELECT id FROM administradores WHERE email = ? AND is_promotor = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $message = "E-mail não encontrado para um promotor.";
        } else {
            $promotor = $result->fetch_assoc();
            $idPromotor = $promotor['id'];
            // Gera o hash da nova senha
            $hashed = password_hash($novaSenha, PASSWORD_DEFAULT);
            
            // Atualiza a senha do promotor
            $stmtUpdate = $conn->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $hashed, $idPromotor);
            if ($stmtUpdate->execute()) {
                $message = "Senha redefinida com sucesso! Você já pode <a href='login.php'>acessar o sistema</a>.";
            } else {
                $message = "Erro ao atualizar a senha: " . $conn->error;
            }
            $stmtUpdate->close();
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
  <title>Redefinir Senha</title>
  <style>
    body {
      background-color: #f4f4f4;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 500px;
      margin: 50px auto;
      background-color: #fff;
      border: 1px solid #ddd;
      border-radius: 4px;
      overflow: hidden;
    }
    .header {
      background-color: #002f6d;
      padding: 20px;
      text-align: center;
    }
    .header img {
      max-width: 150px;
      height: auto;
    }
    .content {
      padding: 20px;
      color: #333;
    }
    .content h2 {
      color: #002f6d;
    }
    .content form {
      margin-top: 20px;
    }
    .content label {
      display: block;
      margin-bottom: 5px;
    }
    .content input[type="email"],
    .content input[type="password"] {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    .content input[type="submit"] {
      background-color: #002f6d;
      color: #fff;
      border: none;
      padding: 10px 20px;
      border-radius: 4px;
      cursor: pointer;
    }
    .content input[type="submit"]:hover {
      background-color: #001f4d;
    }
    .message {
      text-align: center;
      padding: 10px;
      color: red;
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
  <div class="container">
    <div class="header">
      <!-- Altere para a URL pública da sua logo -->
      <img src="https://ticketsync.com.br/uploads/ticketsyhnklogo_branco.png" alt="Logo">
    </div>
    <div class="content">
      <h2>Redefinir Senha</h2>
      <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
      <?php endif; ?>
      <?php if (empty($message) || strpos($message, "Erro") !== false): ?>
      <form action="redefinir.php" method="post">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        
        <label for="nova_senha">Nova Senha:</label>
        <input type="password" id="nova_senha" name="nova_senha" required>
        
        <label for="confirmar_senha">Confirmar Senha:</label>
        <input type="password" id="confirmar_senha" name="confirmar_senha" required>
        
        <input type="submit" value="Redefinir Senha">
      </form>
      <?php endif; ?>
    </div>
    <div class="footer">
      © <?php echo date('Y'); ?> Ticket Sync. Todos os direitos reservados.
    </div>
  </div>
</body>
</html>
