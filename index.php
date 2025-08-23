<?php
// index.php
session_start();
include('conexao.php');

// Se o usuário estiver logado e for visitante, atualiza para cliente
if (isset($_SESSION['userid']) && isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] == 1) {
    $userId = $_SESSION['userid'];
    $sql_update = "UPDATE clientes SET tipo_usuario = 0 WHERE id = $userId";
    if ($conn->query($sql_update) === TRUE) {
       $_SESSION['tipo_usuario'] = 0;
       $_SESSION['update_message'] = "Seu cadastro foi atualizado de visitante para cliente com sucesso!";
    }
}

// Consulta os eventos futuros aprovados usando data_inicio
$current_date = date("Y-m-d");
$sql = "SELECT * FROM eventos 
        WHERE data_inicio >= '$current_date' 
          AND status = 'aprovado'
        ORDER BY data_inicio ASC";
$result = $conn->query($sql);

$eventos = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $eventos[] = $row;
    }
}
$conn->close();

// ------ CARROSSEL ------
// Filtrar eventos com em_carrossel = 1 e ordenar
$carouselEventos = array_filter($eventos, function($ev) {
    return ($ev['em_carrossel'] == 1);
});
usort($carouselEventos, function($a, $b) {
    if ($a['prioridade'] == $b['prioridade']) {
        return strtotime($a['data_inicio']) - strtotime($b['data_inicio']);
    }
    return $a['prioridade'] - $b['prioridade'];
});
// Limite de 5
$carouselEventos = array_slice($carouselEventos, 0, 5);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket Sync - Página Inicial</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    
  </style>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const carouselInner = document.getElementById("carouselInner");
      const carouselIndicator = document.getElementById("carouselIndicator");
      const prevBtn = document.getElementById("prevBtn");
      const nextBtn = document.getElementById("nextBtn");

      if (carouselInner) {
        const items = carouselInner.querySelectorAll(".carousel-item");
        let currentIndex = 0;
        const totalItems = items.length;

        function updateCarousel() {
          items.forEach((item, index) => {
            item.classList.toggle("active", index === currentIndex);
          });
          carouselIndicator.textContent = (currentIndex + 1) + " / " + totalItems;
        }

        function slideNext() {
          currentIndex = (currentIndex + 1) % totalItems;
          updateCarousel();
        }

        function slidePrev() {
          currentIndex = (currentIndex - 1 + totalItems) % totalItems;
          updateCarousel();
        }

        updateCarousel();

        if (prevBtn) {
          prevBtn.addEventListener("click", function() {
            slidePrev();
            resetAutoSlide();
          });
        }
        if (nextBtn) {
          nextBtn.addEventListener("click", function() {
            slideNext();
            resetAutoSlide();
          });
        }

        let autoSlide = null;
        if (totalItems > 1) {
          autoSlide = setInterval(slideNext, 5000);
        }
        function resetAutoSlide() {
          if (autoSlide) {
            clearInterval(autoSlide);
            autoSlide = setInterval(slideNext, 5000);
          }
        }
      }
    });
  </script>
</head>
<body>
  <!-- HEADER -->
  <header class="modern-header">
    <div class="modern-header-container">
      <!-- Logo -->
      <div class="header-logo">
        <a href="index">
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
              <a href="perfil.php" class="dropdown-item">
                <i class="fas fa-user"></i> Meu Perfil
              </a>
              <a href="meus_ingressos.php" class="dropdown-item">
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

  <!-- CARROSSEL -->
  <?php if (!empty($carouselEventos)): ?>
    <div class="fullwidth-carousel" id="carouselContainer">
      <!-- Botões (prev/next) -->
      <button class="carousel-control prev" id="prevBtn">&#10094;</button>
      <button class="carousel-control next" id="nextBtn">&#10095;</button>

      <div class="carousel-inner" id="carouselInner">
        <?php foreach ($carouselEventos as $index => $evento): ?>
          <div class="carousel-item" style="cursor:pointer;" onclick="window.location.href='detalhes_evento.php?id=<?php echo $evento['id']; ?>'">
            <img src="<?php echo (!empty($evento['logo'])) ? htmlspecialchars($evento['logo']) : 'default-event.jpg'; ?>" alt="<?php echo htmlspecialchars($evento['nome']); ?>" style="width:100%; height:100%; object-fit: cover;">
            <div class="carousel-overlay">
              <h2><?php echo htmlspecialchars($evento['titulo_carrossel'] ?: $evento['nome']); ?></h2>
              <?php if (!empty($evento['descricao_curta'])): ?>
                <p><?php echo htmlspecialchars($evento['descricao_curta']); ?></p>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="carousel-indicator" id="carouselIndicator">1 / <?php echo count($carouselEventos); ?></div>
    </div>
  <?php endif; ?>

  <!-- CONTEÚDO PRINCIPAL -->
  <div class="container">
    <div class="welcome">
      <h2>Bem-vindo ao Ticket Sync</h2>
      <p>Confira os eventos mais próximos e garanta já o seu ingresso!</p>
    </div>
    
    <!-- Mensagem de atualização -->
    <?php if(isset($_SESSION['update_message'])): ?>
      <div class="alert alert-success">
        <?php
          echo $_SESSION['update_message'];
          unset($_SESSION['update_message']);
        ?>
      </div>
    <?php endif; ?>


    <!-- CARDS -->
    <div class="cards-container" id="cardsContainer">
      <?php if (!empty($eventos)): ?>
        <?php foreach ($eventos as $evento): ?>
          <div class="card" onclick="window.location.href='detalhes_evento?id=<?php echo $evento['id']; ?>'">
            <div class="card-image">
              <?php if (!empty($evento['logo'])): ?>
                <img src="<?php echo htmlspecialchars($evento['logo']); ?>" alt="Logo do Evento">
              <?php else: ?>
                <img src="default-event.jpg" alt="Logo Padrão">
              <?php endif; ?>
            </div>
            <div class="card-body">
              <div class="card-info">
                <h3><?php echo htmlspecialchars($evento['nome']); ?></h3>
                <div>
                  <p><i class="fa-regular fa-calendar"></i> <?php echo date("d/m/Y", strtotime($evento['data_inicio'])); ?></p>
                  <p><i class="fa-regular fa-clock"></i> <?php echo date("H:i", strtotime($evento['hora_inicio'])); ?></p>
                  <p><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($evento['local']); ?></p>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-center">Nenhum evento disponível no momento.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footer-container">
    <div class="footer-container__content">
      <!-- Seção Sobre -->
      <div class="footer-container__section footer-container__section--about">
        <img class="footer-logo" src="uploads/ticketsyhnklogo_branco.svg" alt="Ticket Sync Logo">
        <p class="footer-description">
          Facilitamos sua compra de ingressos e o acesso aos melhores eventos. Experimente uma nova forma de se conectar com o entretenimento!
        </p>
      </div>
      <!-- Seção Links Rápidos -->
      <div class="footer-container__section footer-container__section--links">
        <div class="footer-title" style="color:#FF9202;">Links Rápidos</div>
        <ul class="footer-links">
          <li><a href="index">Página Inicial</a></li>
          <li><a href="login">Entrar</a></li>
          <li><a href="sobre">Sobre</a></li>
          <li><a href="termos">Termos de Uso</a></li>
          <li><a href="ppts">Política de Privacidade</a></li>
        </ul>
      </div>
      
      <!-- Seção Contato -->
      <div class="footer-container__section footer-container__section--contact">
        <div class="footer-title" style="color:#FF9202;">Contato</div>
        <ul class="footer-contact">
          <li>
            <a href="https://wa.link/urnlmy" target="_blank">
              <i class="fab fa-whatsapp"></i> +55 34 99192-1872
            </a>
          </li>
          <li>
            <a href="mailto:contato@ticketsync.com.br">
              <i class="fa fa-envelope"></i> contato@ticketsync.com.br
            </a>
          </li>
          <li>
            <span style="color:#fff;"><i class="fa fa-id-card" style="color:#fff;"></i> CNPJ 59.826.857/0001-08</span>
          </li>
        </ul>
      </div>
    </div>
    <div class="footer-container__bottom">
      <p style="color:#FF9202;">&copy; <?php echo date("Y"); ?> Ticket Sync. Todos os direitos reservados.</p>
    </div>
    <!-- Botão Voltar ao Topo -->
    <img src="uploads/voltar.svg" alt="Voltar ao topo" class="back-to-top" id="backToTop">
  </footer>

  <!-- Script para o botão "Voltar ao Topo" -->
  <script>
    const backToTopButton = document.getElementById("backToTop");
    backToTopButton.addEventListener("click", function() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  </script>

  <!-- JavaScript da Nova Navbar -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      // Busca
      const headerSearchInput = document.getElementById("headerSearchInput");
      const cardsContainer = document.getElementById("cardsContainer");
      const carouselContainer = document.getElementById("carouselContainer");

      if (headerSearchInput) {
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
  <script src="jdkfront.js"></script>
</body>
</html>
