<?php
// redefinir_senha.php
include('conexao.php');
session_start();

$token = isset($_GET['token']) ? $_GET['token'] : '';
$userType = isset($_GET['tipo']) ? $_GET['tipo'] : 'admin'; // Valor padrão 'admin' se não informado

if (empty($token)) {
    die("Token não fornecido.");
}

if ($userType == 'admin') {
    $sql = "SELECT * FROM administradores WHERE token_red = '$token' LIMIT 1";
} elseif ($userType == 'cliente') {
    $sql = "SELECT * FROM clientes WHERE token_red = '$token' LIMIT 1";
} else {
    die("Tipo de usuário inválido.");
}

$result = $conn->query($sql);
$erro = "";
$sucesso = "";
$user = null;

if (!$result || $result->num_rows == 0) {
    $erro = "Token inválido ou expirado.";
} else {
    $user = $result->fetch_assoc();
    $currentTime = date("Y-m-d H:i:s");
    if ($user['token_validade'] < $currentTime) {
        $erro = "Token expirado. Por favor, solicite uma nova redefinição de senha.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($erro)) {
    $nova_senha      = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    if ($nova_senha !== $confirmar_senha) {
        $erro = "As senhas não conferem. Tente novamente.";
    } else {
        $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        if ($userType == 'admin') {
            $update_query = "UPDATE administradores SET senha = '$nova_senha_hash', token_red = '', token_validade = '' WHERE id = " . $user['id'];
        } else {
            $update_query = "UPDATE clientes SET senha = '$nova_senha_hash', token_red = '', token_validade = '' WHERE id = " . $user['id'];
        }
        if ($conn->query($update_query) === TRUE) {
            $sucesso = "Senha redefinida com sucesso! Você já pode efetuar login.";
            // Exibe o toast e redireciona para login.php
            echo '
            <!DOCTYPE html>
            <html lang="pt-br">
            <head>
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <title>Redefinir Senha - Ticket Sync</title>
              <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
              <link rel="stylesheet" href="css/login.css">
              <style>
                .toast {
                  position: fixed;
                  top: 20px;
                  right: 20px;
                  background-color: #002F6D;
                  color: #fff;
                  padding: 16px;
                  border-radius: 4px;
                  z-index: 10000;
                  opacity: 0;
                  transition: opacity 0.5s ease;
                }
                .toast.show {
                  opacity: 1;
                }
              </style>
              <script>
                function showToast(message) {
                  var toast = document.getElementById("toast");
                  toast.textContent = message;
                  toast.classList.add("show");
                  setTimeout(function() {
                    toast.classList.remove("show");
                    window.location.href = "login.php";
                  }, 3000);
                }
                window.onload = function() {
                  showToast("Senha redefinida com sucesso! Você já pode efetuar login.");
                };
              </script>
            </head>
            <body class="login-page-body">
              <div id="toast" class="toast"></div>
            </body>
            </html>
            ';
            exit();
        } else {
            $erro = "Erro ao atualizar a senha: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redefinir Senha - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="css/login.css">
  <style>
    /* Estilo do Toast */
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      background-color: #002F6D;
      color: #fff;
      padding: 16px;
      border-radius: 4px;
      z-index: 10000;
      opacity: 0;
      transition: opacity 0.5s ease;
    }
    .toast.show {
      opacity: 1;
    }
    /* Estilo do Spinner */
    .spinner-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 9999;
    }
    .spinner {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      border: 8px solid #f3f3f3;
      border-top: 8px solid #002F6D;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: translate(-50%, -50%) rotate(0deg); }
      100% { transform: translate(-50%, -50%) rotate(360deg); }
    }
  </style>
  <script>
    // Exibe o spinner ao submeter o formulário
    function showSpinner() {
      document.getElementById('spinner-overlay').style.display = 'block';
    }
  </script>
</head>
<body class="login-page-body">
<!-- Spinner de carregamento -->
<div id="spinner-overlay" class="spinner-overlay">
  <div class="spinner"></div>
</div>
<!-- Container do Toast -->
<div id="toast" class="toast"></div>
<div class="login-container">
  <div class="login-logo">
    <a href="index.php">
      <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
    </a>
  </div>
  <h2 class="login-heading">Redefinir Senha</h2>
  <?php if (!empty($erro)): ?>
    <p style="color:red;"><?php echo $erro; ?></p>
  <?php endif; ?>
  <?php if (empty($erro) && empty($sucesso)) : ?>
    <form action="" method="post" class="login-form" onsubmit="showSpinner()">
      <label for="nova_senha" class="login-form-label">Nova Senha:</label>
      <input type="password" id="nova_senha" name="nova_senha" class="login-form-input" required>
      <label for="confirmar_senha" class="login-form-label">Confirmar Senha:</label>
      <input type="password" id="confirmar_senha" name="confirmar_senha" class="login-form-input" required>
      <input type="submit" value="Redefinir Senha" class="login-form-submit">
    </form>
  <?php endif; ?>
  <p class="login-register-p"><a href="login.php">Voltar ao Login</a></p>
</div>
</body>
</html>
