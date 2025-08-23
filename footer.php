<link rel="stylesheet" href="css/footer.css">

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
