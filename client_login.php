<?php
// client_login.php
session_start();
include('conexao.php'); // Certifique-se de que este arquivo conecta corretamente ao seu banco MySQL

// Se o cliente já estiver logado, redireciona para os ingressos
if (isset($_SESSION['cliente_email'])) {
    header("Location: client_tickets.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    if (empty($email) || empty($senha)) {
        $error = "Por favor, preencha todos os campos.";
    } else {
        // Consulta o cliente pela tabela "clientes"
        $stmt = $conn->prepare("SELECT id, nome, email, senha FROM clientes WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $cliente = $result->fetch_assoc();
            // Verifica se a senha está correta (supondo que a senha foi hasheada)
            if (password_verify($senha, $cliente['senha'])) {
                // Armazena os dados do cliente na sessão
                $_SESSION['cliente_id'] = $cliente['id'];
                $_SESSION['cliente_nome'] = $cliente['nome'];
                $_SESSION['cliente_email'] = $cliente['email'];
                header("Location: client_tickets.php");
                exit();
            } else {
                $error = "Email ou senha incorretos.";
            }
        } else {
            $error = "Email ou senha incorretos.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login do Cliente</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; margin-bottom: 20px; color: #002f6d; }
        label { display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; }
        button { width: 100%; padding: 10px; background-color: #002f6d; color: #fff; border: none; border-radius: 5px; font-size: 16px; }
        .error { color: #e74c3c; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login do Cliente</h2>
        <?php if ($error != ""): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="client_login.php" method="post">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>
            
            <label for="senha">Senha:</label>
            <input type="password" name="senha" id="senha" required>
            
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>