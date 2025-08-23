<?php
// ppts.php
session_start();
include('conexao.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Política de Privacidade - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/index.css">
  <style>
    .politica-container {
      max-width: 900px;
      margin: 40px auto;
      padding: 0 20px;
    }
    
    .politica-header {
      text-align: center;
      margin-bottom: 40px;
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      padding: 40px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
    }
    
    .politica-header h1 {
      font-size: 2.5rem;
      margin-bottom: 15px;
      font-weight: 700;
    }
    
    .politica-header p {
      font-size: 1.1rem;
      opacity: 0.9;
    }
    
    .politica-content {
      background: white;
      padding: 40px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      line-height: 1.7;
    }
    
    .politica-content h2 {
      color: var(--primary-color);
      font-size: 1.5rem;
      margin: 30px 0 15px 0;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--accent-color);
    }
    
    .politica-content h3 {
      color: var(--primary-color);
      font-size: 1.2rem;
      margin: 20px 0 10px 0;
    }
    
    .politica-content p {
      color: var(--text-color);
      margin-bottom: 15px;
      text-align: justify;
    }
    
    .politica-content ul, .politica-content ol {
      margin: 15px 0 15px 30px;
      color: var(--text-color);
    }
    
    .politica-content li {
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
    
    .direitos-box {
      background: rgba(76, 175, 80, 0.1);
      padding: 20px;
      border-radius: var(--border-radius);
      margin: 20px 0;
      border: 1px solid rgba(76, 175, 80, 0.3);
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
    
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
      background: white;
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .data-table th, .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    
    .data-table th {
      background: var(--primary-color);
      color: white;
      font-weight: 600;
    }
    
    .data-table tr:hover {
      background: rgba(0, 47, 109, 0.05);
    }
    
    @media (max-width: 768px) {
      .politica-container {
        padding: 0 15px;
      }
      
      .politica-header {
        padding: 30px 20px;
      }
      
      .politica-header h1 {
        font-size: 2rem;
      }
      
      .politica-content {
        padding: 25px;
      }
      
      .politica-content h2 {
        font-size: 1.3rem;
      }
      
      .contact-links {
        flex-direction: column;
        align-items: center;
      }
      
      .data-table {
        font-size: 0.9rem;
      }
      
      .data-table th, .data-table td {
        padding: 10px 8px;
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
  <div class="politica-container">
    <!-- Header -->
    <div class="politica-header">
      <h1>Política de Privacidade</h1>
      <p>Última atualização: <?php echo date("d/m/Y"); ?></p>
    </div>

    <!-- Conteúdo -->
    <div class="politica-content">
      <div class="destaque">
        <p><strong>Compromisso com sua Privacidade:</strong> A Ticket Sync valoriza e respeita a privacidade de todos os usuários. Esta política explica como coletamos, usamos e protegemos suas informações pessoais.</p>
      </div>

      <h2>1. Informações Gerais</h2>
      <p>Esta Política de Privacidade descreve como a <strong>Ticket Sync</strong>, inscrita no CNPJ 59.826.857/0001-08, coleta, usa, armazena e protege as informações pessoais dos usuários de nossa plataforma digital de venda de ingressos.</p>
      
      <p>Estamos comprometidos com a transparência e o cumprimento da <strong>Lei Geral de Proteção de Dados (LGPD - Lei 13.709/2018)</strong> e outras legislações aplicáveis de proteção de dados.</p>

      <h2>2. Dados Pessoais Coletados</h2>
      <h3>2.1 Dados Fornecidos Voluntariamente</h3>
      <p>Coletamos as seguintes informações quando você se cadastra ou utiliza nossos serviços:</p>
      
      <table class="data-table">
        <thead>
          <tr>
            <th>Tipo de Dado</th>
            <th>Exemplos</th>
            <th>Finalidade</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Dados de Identificação</td>
            <td>Nome completo, CPF, RG, data de nascimento</td>
            <td>Identificação e validação da conta</td>
          </tr>
          <tr>
            <td>Dados de Contato</td>
            <td>E-mail, telefone, endereço</td>
            <td>Comunicação e entrega de ingressos</td>
          </tr>
          <tr>
            <td>Dados de Pagamento</td>
            <td>Informações do cartão, dados bancários</td>
            <td>Processamento de pagamentos</td>
          </tr>
          <tr>
            <td>Dados de Navegação</td>
            <td>IP, cookies, logs de acesso</td>
            <td>Melhorar a experiência do usuário</td>
          </tr>
        </tbody>
      </table>

      <h3>2.2 Dados Coletados Automaticamente</h3>
      <p>Também coletamos automaticamente informações sobre sua interação com nossa plataforma:</p>
      <ul>
        <li>Endereço IP e localização geográfica;</li>
        <li>Tipo de dispositivo e navegador utilizado;</li>
        <li>Páginas visitadas e tempo de permanência;</li>
        <li>Preferências e histórico de compras;</li>
        <li>Dados de performance e uso da plataforma.</li>
      </ul>

      <h2>3. Finalidades do Tratamento</h2>
      <div class="info-box">
        <h3>3.1 Finalidades Principais</h3>
        <p>Utilizamos seus dados pessoais para as seguintes finalidades:</p>
      </div>

      <ol>
        <li><strong>Prestação de Serviços:</strong> Processar compras, emitir ingressos e fornecer suporte;</li>
        <li><strong>Comunicação:</strong> Enviar confirmações, atualizações e informações sobre eventos;</li>
        <li><strong>Segurança:</strong> Prevenir fraudes e garantir a segurança da plataforma;</li>
        <li><strong>Melhoria dos Serviços:</strong> Analisar uso da plataforma para aprimorar funcionalidades;</li>
        <li><strong>Marketing:</strong> Enviar ofertas personalizadas (com seu consentimento);</li>
        <li><strong>Cumprimento Legal:</strong> Atender obrigações legais e regulatórias.</li>
      </ol>

      <h2>4. Base Legal para o Tratamento</h2>
      <p>O tratamento de seus dados pessoais é fundamentado nas seguintes bases legais da LGPD:</p>
      <ul>
        <li><strong>Execução de Contrato:</strong> Para cumprir nossos Termos de Uso;</li>
        <li><strong>Consentimento:</strong> Para envio de comunicações promocionais;</li>
        <li><strong>Legítimo Interesse:</strong> Para melhoria dos serviços e segurança;</li>
        <li><strong>Cumprimento de Obrigação Legal:</strong> Para atender requisitos fiscais e regulatórios.</li>
      </ul>

      <h2>5. Compartilhamento de Dados</h2>
      <h3>5.1 Quando Compartilhamos</h3>
      <p>Seus dados pessoais podem ser compartilhados nas seguintes situações:</p>
      
      <div class="info-box">
        <p><strong>Importante:</strong> Nunca vendemos seus dados pessoais a terceiros. O compartilhamento sempre ocorre para fins específicos e necessários.</p>
      </div>

      <ul>
        <li><strong>Processadores de Pagamento:</strong> Para processar transações financeiras;</li>
        <li><strong>Promotores de Eventos:</strong> Informações necessárias para organização do evento;</li>
        <li><strong>Prestadores de Serviços:</strong> Empresas que nos auxiliam na operação da plataforma;</li>
        <li><strong>Autoridades Competentes:</strong> Quando exigido por lei ou ordem judicial;</li>
        <li><strong>Transferência de Negócios:</strong> Em caso de fusão, aquisição ou venda de ativos.</li>
      </ul>

      <h2>6. Cookies e Tecnologias Similares</h2>
      <p>Utilizamos cookies e tecnologias similares para:</p>
      <ul>
        <li>Manter você logado na plataforma;</li>
        <li>Lembrar suas preferências;</li>
        <li>Analisar o uso da plataforma;</li>
        <li>Personalizar conteúdo e anúncios;</li>
        <li>Garantir a segurança da plataforma.</li>
      </ul>
      
      <p>Você pode gerenciar suas preferências de cookies através das configurações do seu navegador.</p>

      <h2>7. Segurança dos Dados</h2>
      <div class="destaque">
        <h3>Medidas de Segurança</h3>
        <p>Implementamos rigorosas medidas de segurança para proteger seus dados:</p>
      </div>

      <ul>
        <li><strong>Criptografia:</strong> Dados sensíveis são criptografados em trânsito e em repouso;</li>
        <li><strong>Controle de Acesso:</strong> Acesso restrito aos dados por colaboradores autorizados;</li>
        <li><strong>Monitoramento:</strong> Sistemas de monitoramento contínuo contra ameaças;</li>
        <li><strong>Backups Seguros:</strong> Cópias de segurança regulares e protegidas;</li>
        <li><strong>Auditorias:</strong> Revisões periódicas de segurança e conformidade;</li>
        <li><strong>Treinamento:</strong> Capacitação regular da equipe em proteção de dados.</li>
      </ul>

      <h2>8. Retenção de Dados</h2>
      <p>Mantemos seus dados pessoais pelo tempo necessário para:</p>
      <ul>
        <li>Cumprir as finalidades para as quais foram coletados;</li>
        <li>Atender obrigações legais (até 5 anos para dados fiscais);</li>
        <li>Resolver disputas e fazer cumprir nossos acordos;</li>
        <li>Exercer direitos em processos administrativos ou judiciais.</li>
      </ul>
      
      <p>Após esse período, os dados são excluídos de forma segura ou anonimizados.</p>

      <h2>9. Seus Direitos</h2>
      <div class="direitos-box">
        <h3>Direitos do Titular de Dados</h3>
        <p>De acordo com a LGPD, você possui os seguintes direitos:</p>
      </div>

      <ul>
        <li><strong>Confirmação e Acesso:</strong> Saber se tratamos seus dados e acessá-los;</li>
        <li><strong>Correção:</strong> Solicitar correção de dados incompletos ou inexatos;</li>
        <li><strong>Anonimização/Bloqueio:</strong> Solicitar anonimização ou bloqueio dos dados;</li>
        <li><strong>Eliminação:</strong> Solicitar exclusão de dados desnecessários;</li>
        <li><strong>Portabilidade:</strong> Receber seus dados em formato estruturado;</li>
        <li><strong>Informação:</strong> Saber sobre compartilhamento de dados com terceiros;</li>
        <li><strong>Revogação do Consentimento:</strong> Retirar consentimento a qualquer momento;</li>
        <li><strong>Revisão:</strong> Solicitar revisão de decisões automatizadas.</li>
      </ul>

      <h3>9.1 Como Exercer seus Direitos</h3>
      <p>Para exercer qualquer um dos direitos acima, entre em contato conosco através dos canais disponibilizados nesta política. Responderemos sua solicitação em até 15 dias.</p>

      <h2>10. Transferência Internacional de Dados</h2>
      <p>Alguns de nossos prestadores de serviços estão localizados fora do Brasil. Quando isso ocorre, garantimos que:</p>
      <ul>
        <li>A transferência está em conformidade com a LGPD;</li>
        <li>O país ou organização oferece grau adequado de proteção;</li>
        <li>Existem cláusulas contratuais de proteção de dados;</li>
        <li>Você é informado sobre tais transferências.</li>
      </ul>

      <h2>11. Menores de Idade</h2>
      <p>Nossa plataforma não é destinada a menores de 18 anos. Caso identifiquemos dados de menores coletados sem autorização dos responsáveis, tomaremos medidas para excluí-los imediatamente.</p>

      <h2>12. Alterações nesta Política</h2>
      <p>Esta Política de Privacidade pode ser atualizada periodicamente. Quando isso ocorrer:</p>
      <ul>
        <li>A nova versão será publicada na plataforma;</li>
        <li>Você será notificado por e-mail sobre mudanças significativas;</li>
        <li>A data da última atualização será modificada;</li>
        <li>Alterações entram em vigor imediatamente após a publicação.</li>
      </ul>

      <h2>13. Encarregado de Proteção de Dados (DPO)</h2>
      <p>Nosso Encarregado de Proteção de Dados está disponível para esclarecer dúvidas sobre esta política e sobre o tratamento de seus dados pessoais.</p>
      
      <div class="info-box">
        <p><strong>Contato do DPO:</strong><br>
        E-mail: dpo@ticketsync.com.br<br>
        Telefone: +55 34 99192-1872</p>
      </div>

      <h2>14. Legislação Aplicável</h2>
      <p>Esta Política de Privacidade é regida pela legislação brasileira, especialmente:</p>
      <ul>
        <li>Lei Geral de Proteção de Dados (LGPD - Lei 13.709/2018);</li>
        <li>Marco Civil da Internet (Lei 12.965/2014);</li>
        <li>Código de Defesa do Consumidor (Lei 8.078/1990);</li>
        <li>Constituição Federal de 1988.</li>
      </ul>

      <!-- Seção de Contato -->
      <div class="contact-section">
        <h3>Dúvidas sobre nossa Política de Privacidade?</h3>
        <p>Nossa equipe está pronta para esclarecer qualquer questão sobre o tratamento de seus dados:</p>
        <div class="contact-links">
          <a href="https://wa.link/urnlmy" target="_blank" class="contact-link">
            <i class="fab fa-whatsapp"></i>
            WhatsApp
          </a>
          <a href="mailto:contato@ticketsync.com.br" class="contact-link">
            <i class="fas fa-envelope"></i>
            E-mail Geral
          </a>
          <a href="mailto:dpo@ticketsync.com.br" class="contact-link">
            <i class="fas fa-shield-alt"></i>
            DPO - Proteção de Dados
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
