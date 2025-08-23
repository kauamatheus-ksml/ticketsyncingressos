<?php
// header_admin.php
// Determinar qual página está ativa para destacar no menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_page = str_replace('.php', '', $current_page);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/header_admin.css">
</head>
<body>
  <header class="admin-header-container">
    <div class="admin-container-inner">
      <!-- Logo: link para a área administrativa -->
      <a href="admin" class="admin-logo-link">
        <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo" class="admin-logo">
      </a>
      
      <!-- Menu Hamburguer para mobile -->
      <div class="menu-toggle" id="menuToggle">
        <span></span>
        <span></span>
        <span></span>
      </div>
      
      <nav class="admin-nav-menu" id="adminNav">
        <a href="admin" class="admin-nav-link <?php echo ($current_page == 'admin') ? 'active' : ''; ?>">
          <i class="fas fa-home"></i> Início
        </a>
        <a href="perfil" class="admin-nav-link <?php echo ($current_page == 'perfil') ? 'active' : ''; ?>">
          <i class="fas fa-user"></i> Meu Perfil
        </a>
        <?php
          // Se a página atual for index.php ou meus_ingressos.php, exibe o link adicional
          if ($current_page == 'index' || $current_page == 'meus_ingressos') {
              echo '<a href="meus_ingressos" class="admin-nav-link ' . ($current_page == 'meus_ingressos' ? 'active' : '') . '">
                      <i class="fas fa-ticket-alt"></i> Meus Ingressos
                    </a>';
          }
        ?>
        <a href="logout" class="admin-nav-link">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </nav>
    </div>
  </header>
  
  <script>
    // Toggle menu mobile
    document.getElementById('menuToggle').addEventListener('click', function() {
      this.classList.toggle('active');
      document.getElementById('adminNav').classList.toggle('active');
    });
    
    // Fechar menu quando clicar em um link
    document.querySelectorAll('.admin-nav-link').forEach(link => {
      link.addEventListener('click', function() {
        document.getElementById('menuToggle').classList.remove('active');
        document.getElementById('adminNav').classList.remove('active');
      });
    });
  </script>
</body>
</html>