<?php
// validaringresso/login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../conexao.php';
session_start();

// Se já houver uma sessão ativa para administrador ou funcionário, redireciona para a validação
if (isset($_SESSION['adminid']) || isset($_SESSION['funcionarioid'])) {
    header("Location: validar_ingresso.php");
    exit;
}

$returnUrl = 'validar_ingresso.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    
    // Verifica se o usuário é um administrador
    $sql_admin = "SELECT * FROM administradores WHERE email='$email'";
    $result_admin = $conn->query($sql_admin);
    
    // Verifica se o usuário é um funcionário
    $sql_funcionario = "SELECT * FROM funcionarios WHERE email='$email'";
    $result_funcionario = $conn->query($sql_funcionario);
    
    if ($result_admin && $result_admin->num_rows > 0) {
        $row_admin = $result_admin->fetch_assoc();
        if (password_verify($senha, $row_admin['senha'])) {
            $_SESSION['adminid'] = $row_admin['id'];
            $_SESSION['nome'] = $row_admin['nome'];
            $_SESSION['email'] = $row_admin['email'];
            header("Location: $returnUrl");
            exit();
        } else {
            $erro = "Senha incorreta para administrador!";
        }
    } elseif ($result_funcionario && $result_funcionario->num_rows > 0) {
        $row_funcionario = $result_funcionario->fetch_assoc();
        if (password_verify($senha, $row_funcionario['senha'])) {
            $_SESSION['funcionarioid'] = $row_funcionario['id'];
            $_SESSION['nome'] = $row_funcionario['nome'];
            $_SESSION['email'] = $row_funcionario['email'];
            header("Location: $returnUrl");
            exit();
        } else {
            $erro = "Senha incorreta para funcionário!";
        }
    } else {
        $erro = "Usuário não encontrado ou não autorizado!";
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login App - Ticket Sync</title>
  <link rel="icon" href="../uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="../css/login.css">
</head>
<body class="login-page-body">
  <div class="login-container">
    <div class="login-logo">
      <a href="../index.php">
        <img src="../uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
      </a>
    </div>
    <h2 class="login-heading">Login Exclusivo para Validação</h2>
    <?php if(isset($erro)): ?>
      <p style="color:red;"><?php echo $erro; ?></p>
    <?php endif; ?>
    <form action="login.php" method="post" class="login-form">
      <label for="email" class="login-form-label">Email:</label>
      <input type="email" id="email" name="email" class="login-form-input" required>
      <label for="senha" class="login-form-label">Senha:</label>
      <input type="password" id="senha" name="senha" class="login-form-input" required>
      <input type="submit" value="Login" class="login-form-submit">
    </form>
  </div>
</body>
</html>
