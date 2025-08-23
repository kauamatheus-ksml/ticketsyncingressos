<?php
// sobre.php
session_start();
include('conexao.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sobre - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    .sobre-container {
      max-width: 1000px;
      margin: 40px auto;
      padding: 0 20px;
    }
    
    .sobre-hero {
      text-align: center;
      margin-bottom: 50px;
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      padding: 60px 40px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
    }
    
    .sobre-hero h1 {
      font-size: 3rem;
      margin-bottom: 20px;
      font-weight: 700;
    }
    
    .sobre-hero p {
      font-size: 1.3rem;
      line-height: 1.6;
      max-width: 600px;
      margin: 0 auto;
    }
    
    .sobre-content {
      display: grid;
      gap: 40px;
      margin-bottom: 50px;
    }
    
    .sobre-section {
      background: white;
      padding: 40px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      transition: var(--transition);
    }
    
    .sobre-section:hover {
      box-shadow: var(--hover-shadow);
      transform: translateY(-2px);
    }
    
    .sobre-section h2 {
      color: var(--primary-color);
      font-size: 2rem;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .sobre-section h2 i {
      font-size: 1.5rem;
      color: var(--accent-color);
    }
    
    .sobre-section p {
      color: var(--text-color);
      font-size: 1.1rem;
      line-height: 1.7;
      margin-bottom: 15px;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 30px;
      margin: 40px 0;
    }
    
    .stat-card {
      background: white;
      padding: 30px;
      border-radius: var(--border-radius);
      text-align: center;
      box-shadow: var(--card-shadow);
      transition: var(--transition);
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--hover-shadow);
    }
    
    .stat-card i {
      font-size: 3rem;
      color: var(--accent-color);
      margin-bottom: 15px;
    }
    
    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 1rem;
      color: var(--light-text);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    .team-section {
      background: var(--background-color);
      padding: 60px 40px;
      margin: 50px -20px;
      text-align: center;
    }
    
    .team-section h2 {
      color: var(--primary-color);
      font-size: 2.5rem;
      margin-bottom: 20px;
    }
    
    .team-description {
      font-size: 1.2rem;
      color: var(--light-text);
      max-width: 600px;
      margin: 0 auto 40px;
      line-height: 1.6;
    }
    
    .contact-cta {
      background: var(--primary-color);
      color: white;
      padding: 40px;
      border-radius: var(--border-radius);
      text-align: center;
      margin-top: 50px;
    }
    
    .contact-cta h3 {
      font-size: 1.8rem;
      margin-bottom: 15px;
    }
    
    .contact-cta p {
      font-size: 1.1rem;
      margin-bottom: 25px;
    }
    
    .contact-buttons {
      display: flex;
      gap: 20px;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn-contact {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 25px;
      background: white;
      color: var(--primary-color);
      text-decoration: none;
      border-radius: 25px;
      font-weight: 600;
      transition: var(--transition);
    }
    
    .btn-contact:hover {
      background: var(--accent-color);
      color: white;
      transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
      .sobre-hero {
        padding: 40px 20px;
      }
      
      .sobre-hero h1 {
        font-size: 2.2rem;
      }
      
      .sobre-hero p {
        font-size: 1.1rem;
      }
      
      .sobre-section {
        padding: 25px;
      }
      
      .sobre-section h2 {
        font-size: 1.6rem;
      }
      
      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
      }
      
      .team-section {
        padding: 40px 20px;
        margin: 30px -20px;
      }
      
      .contact-buttons {
        flex-direction: column;
        align-items: center;
      }
    }
  </style>
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
          <input type="text" placeholder="Pesquisar eventos..." onclick="window.location.href='index'">
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

  <!-- CONTEÚDO PRINCIPAL -->
  <div class="sobre-container">
    <!-- Hero Section -->
    <div class="sobre-hero">
      <h1>Sobre o Ticket Sync</h1>
      <p>Conectamos pessoas aos melhores eventos com uma experiência de compra simples, segura e inovadora.</p>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
      <div class="stat-card">
        <i class="fas fa-ticket-alt"></i>
        <div class="stat-number">10k+</div>
        <div class="stat-label">Ingressos Vendidos</div>
      </div>
      <div class="stat-card">
        <i class="fas fa-calendar-alt"></i>
        <div class="stat-number">500+</div>
        <div class="stat-label">Eventos Realizados</div>
      </div>
      <div class="stat-card">
        <i class="fas fa-users"></i>
        <div class="stat-number">5k+</div>
        <div class="stat-label">Clientes Satisfeitos</div>
      </div>
      <div class="stat-card">
        <i class="fas fa-handshake"></i>
        <div class="stat-number">100+</div>
        <div class="stat-label">Parcerias</div>
      </div>
    </div>

    <!-- Conteúdo sobre -->
    <div class="sobre-content">
      <div class="sobre-section">
        <h2><i class="fas fa-rocket"></i>Nossa Missão</h2>
        <p>
          Revolucionar a forma como as pessoas descobrem, compram e vivenciam eventos. 
          Acreditamos que cada evento é uma oportunidade única de criar memórias inesquecíveis, 
          e nossa missão é tornar essa jornada o mais simples e prazerosa possível.
        </p>
        <p>
          Através da tecnologia, eliminamos as barreiras entre você e os eventos que ama, 
          proporcionando uma experiência de compra intuitiva e segura.
        </p>
      </div>

      <div class="sobre-section">
        <h2><i class="fas fa-eye"></i>Nossa Visão</h2>
        <p>
          Ser a plataforma líder em venda de ingressos no Brasil, reconhecida pela excelência 
          no atendimento, inovação tecnológica e pelo compromisso em conectar pessoas aos 
          eventos que transformam vidas.
        </p>
        <p>
          Visionamos um futuro onde a compra de ingressos seja tão simples quanto um clique, 
          e onde cada evento seja uma porta de entrada para novas experiências e conexões.
        </p>
      </div>

      <div class="sobre-section">
        <h2><i class="fas fa-heart"></i>Nossos Valores</h2>
        <p><strong>Transparência:</strong> Acreditamos na honestidade em todas as nossas relações, 
        oferecendo preços claros e informações precisas sobre todos os eventos.</p>
        
        <p><strong>Inovação:</strong> Estamos sempre buscando novas formas de melhorar a experiência 
        dos nossos usuários através da tecnologia.</p>
        
        <p><strong>Segurança:</strong> Protegemos seus dados e garantimos transações seguras, 
        para que você possa focar apenas em se divertir.</p>
        
        <p><strong>Excelência:</strong> Buscamos a perfeição em cada detalhe, desde a interface 
        até o atendimento ao cliente.</p>
      </div>

      <div class="sobre-section">
        <h2><i class="fas fa-shield-alt"></i>Segurança e Confiabilidade</h2>
        <p>
          Sua segurança é nossa prioridade. Utilizamos as mais avançadas tecnologias de 
          criptografia e protocolos de segurança para proteger suas informações pessoais 
          e financeiras.
        </p>
        <p>
          Todos os pagamentos são processados através de gateways seguros e certificados, 
          garantindo que suas transações sejam sempre protegidas. Além disso, nossos ingressos 
          possuem sistemas anti-fraude que garantem sua autenticidade.
        </p>
      </div>
    </div>

    <!-- Seção da Equipe -->
    <div class="team-section">
      <h2>Nossa Equipe</h2>
      <p class="team-description">
        Somos uma equipe apaixonada por eventos e tecnologia, dedicada a criar a melhor 
        experiência possível para nossos usuários. Cada membro da nossa equipe contribui 
        com sua expertise para tornar o Ticket Sync a plataforma mais confiável e inovadora 
        do mercado.
      </p>
    </div>

    <!-- Call to Action -->
    <div class="contact-cta">
      <h3>Entre em Contato</h3>
      <p>Tem alguma dúvida ou sugestão? Nossa equipe está sempre pronta para ajudar!</p>
      <div class="contact-buttons">
        <a href="https://wa.link/urnlmy" target="_blank" class="btn-contact">
          <i class="fab fa-whatsapp"></i>
          WhatsApp
        </a>
        <a href="mailto:contato@ticketsync.com.br" class="btn-contact">
          <i class="fas fa-envelope"></i>
          E-mail
        </a>
        <a href="index" class="btn-contact">
          <i class="fas fa-calendar"></i>
          Ver Eventos
        </a>
      </div>
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

  <!-- JavaScript da Navbar -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
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