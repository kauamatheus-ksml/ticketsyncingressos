<?php
// header_cliente.php

// Determina qual página está ativa para destacar no menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_page = str_replace('.php', '', $current_page);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <!-- Roboto e Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/index.css">
  <title>Ticket Sync - Cliente</title>
</head>
<body>
  <!-- HEADER -->
  <header class="modern-header">
    <div class="modern-header-container">
      <!-- Logo -->
      <div class="header-logo">
        <a href="index.php">
          <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
        </a>
      </div>

      <!-- Saudação -->
      <div class="header-greeting">
        <?php if (isset($_SESSION['userid']) && !empty($_SESSION['nome'])): ?>
          <span>Olá, <?php echo htmlspecialchars($_SESSION['nome']); ?>!</span>
        <?php else: ?>
          <span>Olá, Visitante!</span>
        <?php endif; ?>
      </div>

      <!-- Barra de Pesquisa -->
      <div class="header-search">
        <div class="search-container">
          <input type="text" id="headerSearchInput" placeholder="Pesquisar eventos...">
          <i class="fas fa-search search-icon"></i>
        </div>
      </div>

      <!-- Menu Perfil -->
      <div class="header-profile">
        <div class="profile-dropdown">
          <button class="profile-btn" id="profileBtn">
            <i class="fas fa-user-circle"></i>
          </button>
          <div class="dropdown-menu" id="dropdownMenu">
            <?php if (isset($_SESSION['userid']) && !empty($_SESSION['nome'])): ?>
              <a href="index.php" class="dropdown-item <?php echo ($current_page=='index') ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Início
              </a>
              <a href="perfil.php" class="dropdown-item <?php echo ($current_page=='perfil') ? 'active' : '' ?>">
                <i class="fas fa-user"></i> Meu Perfil
              </a>
              <a href="meus_ingressos.php" class="dropdown-item <?php echo ($current_page=='meus_ingressos') ? 'active' : '' ?>">
                <i class="fas fa-ticket-alt"></i> Meus Ingressos
              </a>
              <div class="dropdown-divider"></div>
              <a href="logout.php" class="dropdown-item logout">
                <i class="fas fa-sign-out-alt"></i> Sair
              </a>
            <?php else: ?>
              <a href="login.php?redirect=perfil.php" class="dropdown-item">
                <i class="fas fa-user"></i> Meu Perfil
              </a>
              <a href="login.php?redirect=meus_ingressos.php" class="dropdown-item">
                <i class="fas fa-ticket-alt"></i> Meus Ingressos
              </a>
              <div class="dropdown-divider"></div>
              <a href="login.php" class="dropdown-item">
                <i class="fas fa-sign-in-alt"></i> Entrar
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- JavaScript do Header -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      // Busca (se existir um container de cards na página)
      const headerSearchInput = document.getElementById("headerSearchInput");
      const cardsContainer = document.getElementById("cardsContainer");
      const carouselContainer = document.getElementById("carouselContainer");

      if (headerSearchInput && cardsContainer) {
        headerSearchInput.addEventListener("input", function() {
          const filter = this.value.toLowerCase().trim();
          if (carouselContainer) {
            carouselContainer.style.display = filter ? "none" : "";
          }
          const cards = cardsContainer.querySelectorAll(".card");
          cards.forEach(function(card) {
            const text = card.textContent.toLowerCase();
            card.style.display = text.indexOf(filter) > -1 ? "" : "none";
          });
        });
      }

      // Dropdown do perfil
      const profileBtn = document.getElementById("profileBtn");
      const dropdownMenu = document.getElementById("dropdownMenu");

      if (profileBtn && dropdownMenu) {
        profileBtn.addEventListener("click", function(e) {
          e.stopPropagation();
          dropdownMenu.classList.toggle("show");
        });

        // Fecha dropdown ao clicar fora
        document.addEventListener("click", function() {
          dropdownMenu.classList.remove("show");
        });

        // Previne fechamento ao clicar dentro do dropdown
        dropdownMenu.addEventListener("click", function(e) {
          e.stopPropagation();
        });
      }
    });
  </script>
</body>
</html>