<?php
// login.php
include('conexao.php');
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Detecta se o login veio do aplicativo (via parâmetro "app=true")
$appMode = (isset($_GET['app']) && $_GET['app'] === 'true');
$redirectPage = $_GET['redirect'] ?? $_GET['returnUrl'] ?? 'index.php';
$returnUrl = $appMode ? 'validar_ingresso.php?app=true' : $redirectPage;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo (isset($_GET['action']) && $_GET['action'] == 'reset') ? 'Redefinir Senha - Ticket Sync' : 'Login - Ticket Sync'; ?></title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="assets/css/login.css">
  <script>
    // Função para exibir o toast de notificação
    function showToast(message, type = 'info') {
      var toast = document.getElementById('ts-toast');
      toast.textContent = message;
      toast.className = 'ts-toast ts-show';
      if (type === 'success') {
        toast.classList.add('ts-success');
      } else if (type === 'error') {
        toast.classList.add('ts-error');
      }
      setTimeout(function() {
        toast.classList.remove('ts-show');
      }, 4000);
    }
    // Exibe o spinner ao submeter o formulário
    function showSpinner() {
      document.getElementById('ts-spinner-overlay').style.display = 'block';
    }
  </script>
</head>
<body class="ts-login-body">

<!-- Spinner de carregamento -->
<div id="ts-spinner-overlay" class="ts-spinner-overlay">
  <div class="ts-spinner"></div>
</div>
<!-- Container do Toast -->
<div id="ts-toast" class="ts-toast"></div>

<?php
// ----------------------------------------------------------------
// FLUXO DE REDEFINIÇÃO DE SENHA (quando a URL tiver ?action=reset)
// ----------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'reset') {

    // Se o formulário de redefinição foi submetido
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_submit'])) {
        $email_reset = $_POST['email_reset'];
        $userType = "";
        $reset_error = "";
        
        // Procura na tabela administradores
        $sql_admin_reset = "SELECT * FROM administradores WHERE email='$email_reset'";
        $result_admin_reset = $conn->query($sql_admin_reset);
        if ($result_admin_reset && $result_admin_reset->num_rows > 0) {
            $userType = "admin";
            $token = bin2hex(random_bytes(32));
            $token_validade = date("Y-m-d H:i:s", strtotime("+1 hour"));
            $update_query = "UPDATE administradores SET token_red = '$token', token_validade = '$token_validade' WHERE email='$email_reset'";
            if (!$conn->query($update_query)) {
                $reset_error = "Erro ao gerar token: " . $conn->error;
            }
        } else {
            // Procura na tabela clientes
            $sql_cliente_reset = "SELECT * FROM clientes WHERE email='$email_reset'";
            $result_cliente_reset = $conn->query($sql_cliente_reset);
            if ($result_cliente_reset && $result_cliente_reset->num_rows > 0) {
                $userType = "cliente";
                $token = bin2hex(random_bytes(32));
                $token_validade = date("Y-m-d H:i:s", strtotime("+1 hour"));
                $update_query = "UPDATE clientes SET token_red = '$token', token_validade = '$token_validade' WHERE email='$email_reset'";
                if (!$conn->query($update_query)) {
                    $reset_error = "Erro ao gerar token: " . $conn->error;
                }
            } else {
                $reset_error = "Email não encontrado no sistema.";
            }
        }
        
        if (empty($reset_error)) {
            // Envio de e‑mail com PHPMailer
            require 'vendor/autoload.php';
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.hostinger.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'kaua@ticketsync.com.br';
                $mail->Password   = 'Aaku_2004@';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('contato@ticketsync.com.br', 'Ticket Sync');
                $mail->addAddress($email_reset);
                
                // Gera o link de redefinição com o parâmetro "tipo"
                $reset_link = "https://ticketsync.com.br/redefinir_senha.php?token=" . $token . "&tipo=" . $userType;
                $mail->Subject = "Reinicialização de Senha - Ticket Sync";
                // Corpo do e‑mail HTML estruturado com logo e estilos
                $body = '
                <html>
                <head>
                  <style>
                    .email-container { font-family: Arial, sans-serif; color: #002F6D; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .header img { width: 200px; }
                    .content { font-size: 16px; }
                    .footer { margin-top: 20px; font-size: 12px; color: #555; }
                  </style>
                </head>
                <body>
                  <div class="email-container">
                    <div class="header">
                      <img src="https://ticketsync.com.br/uploads/ticketsyhnklogo_branco.png" alt="Ticket Sync Logo">
                    </div>
                    <div class="content">
                      <p>Olá,</p>
                      <p>Recebemos uma solicitação para redefinir sua senha.</p>
                      <p>Clique no link abaixo para redefini-la. Este link é válido por 1 hora:</p>
                      <p><a href="' . $reset_link . '" style="color:#002F6D; text-decoration:none;">Redefinir Senha</a></p>
                      <p>Se você não solicitou a redefinição, ignore este e‑mail.</p>
                    </div>
                    <div class="footer">
                      <p>Atenciosamente,</p>
                      <p>Equipe Ticket Sync</p>
                    </div>
                  </div>
                </body>
                </html>';
                $mail->Body = $body;
                $mail->isHTML(true);

                $mail->send();
                // Mostra o toast e redireciona para a página de login
                echo '
                <script>
                  document.addEventListener("DOMContentLoaded", function() {
                    showToast("Um e‑mail com as instruções para redefinir sua senha foi enviado.", "success");
                    setTimeout(function() {
                      window.location.href = "login.php";
                    }, 3000);
                  });
                </script>
                ';
                exit();
            } catch (Exception $e) {
                $reset_error = "Falha ao enviar o e‑mail: " . $mail->ErrorInfo;
            }
        }
    }
    ?>
    <!-- Tela de Redefinição de Senha -->
    <div class="ts-login-container">
      <div class="ts-login-logo">
        <a href="index.php">
          <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
        </a>
      </div>
      <h1 class="ts-login-heading">Redefinir Senha</h1>
      <p class="ts-login-subtitle">Digite seu e-mail para receber as instruções</p>
      <?php if (isset($reset_error) && !empty($reset_error)): ?>
        <div class="ts-error-message"><?php echo $reset_error; ?></div>
      <?php endif; ?>
      <form action="login.php?action=reset" method="post" class="ts-login-form" onsubmit="showSpinner()">
        <div class="ts-form-group">
          <label for="email_reset" class="ts-form-label">E-mail</label>
          <input type="email" id="email_reset" name="email_reset" class="ts-form-input" placeholder="Digite seu e-mail" required>
        </div>
        <button type="submit" name="reset_submit" class="ts-btn-primary">Enviar Redefinição</button>
      </form>
      <div class="ts-login-links">
        <a href="login.php" class="ts-login-link">← Voltar ao Login</a>
      </div>
    </div>
    <?php
    exit();
} // Fim do fluxo de redefinição

// ----------------------------------------------------------------
// FLUXO DE LOGIN NORMAL
// ----------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    if (isset($_POST['returnUrl'])) {
        $returnUrl = $_POST['returnUrl'];
    }
    $erro = "";
    // Verifica usuário nas diferentes tabelas
    $sql_cliente = "SELECT * FROM clientes WHERE email='$email'";
    $result_cliente = $conn->query($sql_cliente);

    $sql_funcionario = "SELECT * FROM funcionarios WHERE email='$email'";
    $result_funcionario = $conn->query($sql_funcionario);

    $sql_admin = "SELECT * FROM administradores WHERE email='$email'";
    $result_admin = $conn->query($sql_admin);

    if ($result_cliente && $result_cliente->num_rows > 0) {
        $row_cliente = $result_cliente->fetch_assoc();
        if (password_verify($senha, $row_cliente['senha'])) {
            $_SESSION['userid'] = $row_cliente['id'];
            $_SESSION['nome']   = $row_cliente['nome'];
            $_SESSION['email']  = $row_cliente['email'];
            header("Location: " . $returnUrl);
            exit();
        } else {
            $erro = "Senha incorreta para cliente!";
        }
    } elseif ($result_funcionario && $result_funcionario->num_rows > 0) {
        $row_funcionario = $result_funcionario->fetch_assoc();
        if (password_verify($senha, $row_funcionario['senha'])) {
            $_SESSION['funcionarioid'] = $row_funcionario['id'];
            $_SESSION['nome']          = $row_funcionario['nome'];
            $_SESSION['email']         = $row_funcionario['email'];
            header("Location: " . $returnUrl);
            exit();
        } else {
            $erro = "Senha incorreta para funcionário!";
        }
    } elseif ($result_admin && $result_admin->num_rows > 0) {
        $row_admin = $result_admin->fetch_assoc();
        if (password_verify($senha, $row_admin['senha'])) {
            $_SESSION['adminid'] = $row_admin['id'];
            $_SESSION['nome']    = $row_admin['nome'];
            $_SESSION['email']   = $row_admin['email'];
            header("Location: admin.php");
            exit();
        } else {
            $erro = "Senha incorreta para administrador!";
        }
    } else {
        $erro = "Usuário não encontrado!";
    }
    $conn->close();
}
?>
<!-- Tela de Login -->
<div class="ts-login-container">
  <div class="ts-login-logo">
    <a href="index.php">
      <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
    </a>
  </div>
  <h1 class="ts-login-heading">Bem-vindo</h1>
  <p class="ts-login-subtitle">Faça login em sua conta</p>
  <?php if (isset($erro) && !empty($erro)): ?>
    <div class="ts-error-message"><?php echo $erro; ?></div>
  <?php endif; ?>
  <form action="login.php<?php echo $appMode ? '?app=true' : ''; ?>" method="post" class="ts-login-form" onsubmit="showSpinner()">
    <input type="hidden" name="returnUrl" value="<?php echo htmlspecialchars($returnUrl); ?>">
    <div class="ts-form-group">
      <label for="email" class="ts-form-label">E-mail</label>
      <input type="email" id="email" name="email" class="ts-form-input" placeholder="Digite seu e-mail" required>
    </div>
    <div class="ts-form-group">
      <label for="senha" class="ts-form-label">Senha</label>
      <input type="password" id="senha" name="senha" class="ts-form-input" placeholder="Digite sua senha" required>
    </div>
    <button type="submit" class="ts-btn-primary">Entrar</button>
  </form>
  <div class="ts-login-links">
    <p class="ts-register-text">
      Não tem uma conta? <a href="registro.php" class="ts-login-link">Registre-se aqui</a>
    </p>
    <a href="login.php?action=reset" class="ts-login-link">Esqueceu sua senha?</a>
  </div>
</div>
</body>
</html>
