<?php
// error.php
$msg = $_GET['msg'] ?? 'Ocorreu um erro inesperado.';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
  <title>Erro</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 20px;
    }
    .error-box {
      border: 1px solid #ccc;
      padding: 15px;
      background: #f8d7da;
      color: #721c24;
      margin-top: 50px;
      border-radius: 5px;
      max-width: 600px;
    }
    .error-box h1 {
      margin-top: 0;
    }
    .btn-voltar {
      display: inline-block;
      margin-top: 10px;
      padding: 6px 12px;
      background: #007bff;
      color: #fff;
      text-decoration: none;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <div class="error-box">
    <h1>Ops!</h1>
    <p><?php echo htmlspecialchars($msg); ?></p>
    <a class="btn-voltar" href="index.php">Voltar para Início</a>
  </div>
</body>
</html>
    