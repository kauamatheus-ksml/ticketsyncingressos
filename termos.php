<?php
// termos.php
session_start();
include('conexao.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Termos de Uso - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    .termos-container {
      max-width: 900px;
      margin: 40px auto;
      padding: 0 20px;
    }
    
    .termos-header {
      text-align: center;
      margin-bottom: 40px;
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      padding: 40px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
    }
    
    .termos-header h1 {
      font-size: 2.5rem;
      margin-bottom: 15px;
      font-weight: 700;
    }
    
    .termos-header p {
      font-size: 1.1rem;
      opacity: 0.9;
    }
    
    .termos-content {
      background: white;
      padding: 40px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      line-height: 1.7;
    }
    
    .termos-content h2 {
      color: var(--primary-color);
      font-size: 1.5rem;
      margin: 30px 0 15px 0;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--accent-color);
    }
    
    .termos-content h3 {
      color: var(--primary-color);
      font-size: 1.2rem;
      margin: 20px 0 10px 0;
    }
    
    .termos-content p {
      color: var(--text-color);
      margin-bottom: 15px;
      text-align: justify;
    }
    
    .termos-content ul, .termos-content ol {
      margin: 15px 0 15px 30px;
      color: var(--text-color);
    }
    
    .termos-content li {
      margin-bottom: 8px;
    }
    
    .destaque {
      background: rgba(114, 9, 183, 0.1);
      padding: 20px;
      border-left: 4px solid var(--accent-color);
      margin: 20px 0;
      border-radius: 0 var(--border-radius) var(--border-radius) 0;
    }
    
    .info-box {
      background: rgba(0, 117, 235, 0.1);
      padding: 20px;
      border-radius: var(--border-radius);
      margin: 20px 0;
      border: 1px solid rgba(0, 117, 235, 0.2);
    }
    
    .contact-section {
      background: var(--background-color);
      padding: 30px;
      border-radius: var(--border-radius);
      margin-top: 30px;
      text-align: center;
    }
    
    .contact-section h3 {
      color: var(--primary-color);
      margin-bottom: 15px;
    }
    
    .contact-links {
      display: flex;
      gap: 20px;
      justify-content: center;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    
    .contact-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: var(--primary-color);
      color: white;
      text-decoration: none;
      border-radius: 25px;
      transition: var(--transition);
    }
    
    .contact-link:hover {
      background: var(--accent-color);
      transform: translateY(-2px);
    }
    
    @media (max-width: 768px) {
      .termos-container {
        padding: 0 15px;
      }
      
      .termos-header {
        padding: 30px 20px;
      }
      
      .termos-header h1 {
        font-size: 2rem;
      }
      
      .termos-content {
        padding: 25px;
      }
      
      .termos-content h2 {
        font-size: 1.3rem;
      }
      
      .contact-links {
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
  <div class="termos-container">
    <!-- Header -->
    <div class="termos-header">
      <h1>Termos de Uso</h1>
      <p>Última atualização: <?php echo date("d/m/Y"); ?></p>
    </div>

    <!-- Conteúdo -->
    <div class="termos-content">
      <div class="destaque">
        <p><strong>Importante:</strong> Ao utilizar os serviços da Ticket Sync, você concorda integralmente com estes Termos de Uso. Recomendamos a leitura atenta de todas as cláusulas.</p>
      </div>

      <h2>1. Definições</h2>
      <p>Para fins destes Termos de Uso, consideram-se as seguintes definições:</p>
      <ul>
        <li><strong>Ticket Sync:</strong> Plataforma digital de venda de ingressos, CNPJ 59.826.857/0001-08;</li>
        <li><strong>Usuário:</strong> Qualquer pessoa física que utilize os serviços da plataforma;</li>
        <li><strong>Evento:</strong> Qualquer atividade cultural, esportiva, educativa ou de entretenimento disponibilizada na plataforma;</li>
        <li><strong>Ingresso:</strong> Documento físico ou digital que garante o acesso a um evento;</li>
        <li><strong>Promotor:</strong> Pessoa física ou jurídica responsável pela organização do evento.</li>
      </ul>

      <h2>2. Aceitação dos Termos</h2>
      <p>Ao acessar e utilizar a Ticket Sync, o usuário declara ter lido, compreendido e aceito integralmente estes Termos de Uso, bem como nossa Política de Privacidade.</p>
      
      <div class="info-box">
        <p><strong>Atenção:</strong> Estes termos podem ser atualizados periodicamente. As alterações entrarão em vigor imediatamente após sua publicação na plataforma.</p>
      </div>

      <h2>3. Cadastro e Conta do Usuário</h2>
      <h3>3.1 Requisitos para Cadastro</h3>
      <p>Para utilizar nossos serviços, o usuário deve:</p>
      <ul>
        <li>Ser maior de 18 anos ou ter autorização dos responsáveis legais;</li>
        <li>Fornecer informações verdadeiras, precisas e atualizadas;</li>
        <li>Manter a confidencialidade de suas credenciais de acesso;</li>
        <li>Ser responsável por todas as atividades realizadas em sua conta.</li>
      </ul>

      <h3>3.2 Responsabilidades do Usuário</h3>
      <p>O usuário compromete-se a:</p>
      <ul>
        <li>Utilizar a plataforma de forma lícita e adequada;</li>
        <li>Não compartilhar suas credenciais de acesso;</li>
        <li>Notificar imediatamente sobre uso não autorizado de sua conta;</li>
        <li>Manter seus dados de contato atualizados.</li>
      </ul>

      <h2>4. Compra de Ingressos</h2>
      <h3>4.1 Processo de Compra</h3>
      <p>A compra de ingressos na Ticket Sync segue o seguinte processo:</p>
      <ol>
        <li>Seleção do evento e tipo de ingresso;</li>
        <li>Inserção dos dados pessoais e de pagamento;</li>
        <li>Confirmação da compra e processamento do pagamento;</li>
        <li>Recebimento do ingresso por e-mail ou disponibilização na conta.</li>
      </ol>

      <h3>4.2 Preços e Pagamento</h3>
      <p>Os preços exibidos na plataforma incluem todos os tributos aplicáveis. Aceitamos as seguintes formas de pagamento:</p>
      <ul>
        <li>Cartões de crédito (Visa, Mastercard, Elo, American Express);</li>
        <li>PIX;</li>
        <li>Boleto bancário (quando disponível).</li>
      </ul>

      <h3>4.3 Confirmação da Compra</h3>
      <p>A confirmação da compra está sujeita à aprovação do pagamento pelo sistema financeiro. Em caso de recusa, o usuário será notificado e poderá tentar novamente com outro meio de pagamento.</p>

      <h2>5. Política de Cancelamento e Reembolso</h2>
      <div class="destaque">
        <h3>5.1 Cancelamento pelo Usuário</h3>
        <p>O usuário pode solicitar o cancelamento de sua compra até 7 (sete) dias antes da data do evento, conforme o Código de Defesa do Consumidor. O reembolso será processado em até 10 dias úteis.</p>
      </div>

      <h3>5.2 Cancelamento do Evento</h3>
      <p>Em caso de cancelamento do evento pelo promotor, todos os compradores serão reembolsados integralmente. O prazo para reembolso é de até 30 dias corridos.</p>

      <h3>5.3 Transferência de Ingressos</h3>
      <p>A transferência de ingressos entre usuários é permitida através da plataforma, seguindo os procedimentos de segurança estabelecidos.</p>

      <h2>6. Uso da Plataforma</h2>
      <h3>6.1 Condutas Proibidas</h3>
      <p>É expressamente proibido:</p>
      <ul>
        <li>Revender ingressos por valores superiores ao valor nominal;</li>
        <li>Utilizar a plataforma para atividades ilegais ou fraudulentas;</li>
        <li>Tentar burlar sistemas de segurança;</li>
        <li>Criar contas falsas ou utilizar informações de terceiros;</li>
        <li>Reproduzir, modificar ou distribuir conteúdo da plataforma sem autorização.</li>
      </ul>

      <h2>7. Propriedade Intelectual</h2>
      <p>Todos os direitos de propriedade intelectual relacionados à Ticket Sync, incluindo marca, logotipo, design, textos e funcionalidades, são de propriedade exclusiva da empresa ou de seus licenciadores.</p>

      <h2>8. Limitação de Responsabilidade</h2>
      <p>A Ticket Sync não se responsabiliza por:</p>
      <ul>
        <li>Alterações nos eventos realizadas pelos promotores;</li>
        <li>Problemas técnicos temporários na plataforma;</li>
        <li>Danos indiretos ou lucros cessantes;</li>
        <li>Uso inadequado da plataforma pelo usuário.</li>
      </ul>

      <div class="info-box">
        <p><strong>Importante:</strong> Nossa responsabilidade limita-se ao valor pago pelo ingresso e aos serviços diretamente prestados pela plataforma.</p>
      </div>

      <h2>9. Proteção de Dados</h2>
      <p>A Ticket Sync compromete-se a proteger os dados pessoais dos usuários em conformidade com a Lei Geral de Proteção de Dados (LGPD). Para mais informações, consulte nossa <a href="ppts.php" style="color: var(--accent-color);">Política de Privacidade</a>.</p>

      <h2>10. Suspensão e Encerramento</h2>
      <p>A Ticket Sync reserva-se o direito de suspender ou encerrar contas de usuários que violem estes Termos de Uso, sem prejuízo de outras medidas legais cabíveis.</p>

      <h2>11. Alterações nos Termos</h2>
      <p>Estes Termos de Uso podem ser atualizados periodicamente. As alterações serão comunicadas através da plataforma e/ou por e-mail, entrando em vigor imediatamente após sua publicação.</p>

      <h2>12. Lei Aplicável e Foro</h2>
      <p>Estes Termos de Uso são regidos pela legislação brasileira. Fica eleito o foro da comarca onde está localizada a sede da Ticket Sync para dirimir quaisquer controvérsias decorrentes destes termos.</p>

      <h2>13. Disposições Finais</h2>
      <p>Caso alguma disposição destes Termos seja considerada inválida, as demais permanecerão em pleno vigor. A tolerância quanto ao descumprimento de qualquer condição não constitui renúncia ou novação.</p>

      <!-- Seção de Contato -->
      <div class="contact-section">
        <h3>Dúvidas sobre os Termos de Uso?</h3>
        <p>Entre em contato conosco através dos canais abaixo:</p>
        <div class="contact-links">
          <a href="https://wa.link/urnlmy" target="_blank" class="contact-link">
            <i class="fab fa-whatsapp"></i>
            WhatsApp
          </a>
          <a href="mailto:contato@ticketsync.com.br" class="contact-link">
            <i class="fas fa-envelope"></i>
            E-mail
          </a>
        </div>
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