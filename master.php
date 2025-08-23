<?php
session_start();
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Página do Administrador - Master</title>
  <!-- Link para o CSS do administrador -->
  <link rel="stylesheet" href="css/admin.css">
  <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
  <!-- Font Awesome para os ícones -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-page-body">
  <?php include('header_admin.php'); ?>
  <div class="admin-container">
    <div class="admin-welcome">
      <h2 class="admin-welcome-heading">
        Bem-vindo, Master <?php echo $_SESSION['nome']; ?>!
      </h2>
      <p>Você está logado na página do Master.</p>
    </div>
    <!-- Novo botão exclusivo para Promotores -->
    <div class="admin-menu-container">
      <a href="promotores.php" class="admin-menu-card">
        <i class="fas fa-user-tie admin-card-icon"></i>
        <span class="admin-card-title">Promotores</span>
      </a>
    </div>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const cards = document.querySelectorAll('.admin-menu-card');
      cards.forEach(card => {
        card.addEventListener('mouseenter', () => {
          card.style.transform = 'translateY(-10px)';
          card.style.boxShadow = '0 12px 20px rgba(0, 0, 0, 0.2)';
        });
        card.addEventListener('mouseleave', () => {
          card.style.transform = 'translateY(0)';
          card.style.boxShadow = '0 6px 10px rgba(0, 0, 0, 0.1)';
        });
      });
    });
  </script>
</body>
</html>
