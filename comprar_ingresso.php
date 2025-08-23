<?php
  // comprar_ingresso.php
  session_start();
  require 'conexao.php'; // Conexão com o banco de dados
  // ----------------------------------------------------------------
  // 0) Gera Order ID no formato 00000001, 00000002, ..., conforme último gerado
  // ----------------------------------------------------------------
  $stmt_last = $conn->prepare(
    "SELECT MAX(CAST(order_id AS UNSIGNED)) AS max_id FROM pedidos"
  );
  $stmt_last->execute();
  $res_last    = $stmt_last->get_result();
  $row_last    = $res_last->fetch_assoc();
  $next_number = intval($row_last['max_id']) + 1;
  $order_id    = str_pad($next_number, 8, '0', STR_PAD_LEFT);
  // Armazena em sessão para uso posterior
  $_SESSION['order_id'] = $order_id;
  $stmt_last->close();
  // ----------------------------------------------------------------
  // 1) Verifica o ID do evento (pode vir via POST ou GET)
  // ----------------------------------------------------------------
  $evento_id = filter_input(INPUT_POST, 'evento_id', FILTER_VALIDATE_INT);
  if (!$evento_id) {
      $evento_id = filter_input(INPUT_GET, 'evento_id', FILTER_VALIDATE_INT);
  }
  if (!$evento_id) {
      die("Evento não especificado.");
  }
  $_SESSION['evento_id'] = $evento_id;

  // ----------------------------------------------------------------
  // 2) Busca o promotor vinculado ao evento (e armazena na sessão)
  // ----------------------------------------------------------------
  $stmt_evento = $conn->prepare("SELECT promotor_id FROM eventos WHERE id = ?");
  $stmt_evento->bind_param("i", $evento_id);
  $stmt_evento->execute();
  $resultado_evento = $stmt_evento->get_result();
  if ($row = $resultado_evento->fetch_assoc()) {
      $_SESSION['promotor_id'] = $row['promotor_id'];
  } else {
      die("Evento não encontrado.");
  }
  $stmt_evento->close();

  // ----------------------------------------------------------------
  // 3) Recebe os ingressos do POST ou da sessão
  // ----------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ingressos'])) {
      $_SESSION['ingressos'] = $_POST['ingressos'];
  }
  if (empty($_SESSION['ingressos'])) {
      header("Location: index.php");
      exit();
  }
  $ingressosSelecionados = $_SESSION['ingressos'];

  // ----------------------------------------------------------------
  // 4) Monta o carrinho e calcula o total
  // ----------------------------------------------------------------
  $itens    = [];
  $subtotal = 0.0;
  foreach ($ingressosSelecionados as $i) {
      $desc  = $i['descricao'] ?? '';
      $preco = floatval($i['preco'] ?? 0);
      $qtd   = intval($i['quantidade'] ?? 0);
      if ($qtd > 0) {
          $itens[] = [
              'desc'  => $desc,
              'preco' => $preco,
              'qtd'   => $qtd
          ];
          $subtotal += ($preco * $qtd);
      }
  }
  $taxas = $subtotal * 0.10; // Taxa configurada (neste exemplo, 10%)
  $totalPedido = $subtotal + $taxas;

  // ----------------------------------------------------------------
  // 5) Gerencia os "passos" (step) do formulário (2 etapas)
  // ----------------------------------------------------------------
  $step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

  // Verifica se o usuário está logado (via sessão)
  $usuarioLogado          = false;
  $nomeUsuarioLogado      = "";
  $sobrenomeUsuarioLogado = "";
  $emailUsuarioLogado     = "";
  if (!empty($_SESSION['userid']) || !empty($_SESSION['nome'])) {
      $usuarioLogado = true;
      $nomeCompleto = $_SESSION['nome'] ?? '';
      $partes = explode(" ", $nomeCompleto);
      $nomeUsuarioLogado      = $partes[0] ?? '';
      $sobrenomeUsuarioLogado = (count($partes) > 1) ? implode(" ", array_slice($partes, 1)) : '';
      $emailUsuarioLogado     = $_SESSION['email'] ?? '';
  }

  // ----------------------------------------------------------------
  // 6) Processa o formulário da Etapa 1 (Informações dos Participantes)
  // ----------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'etapa1') {
      // Captura os dados enviados
      $nome_completo = trim($_POST['nome_completo'] ?? '');
      $email         = trim($_POST['email'] ?? '');
      $telefoneBruto = trim($_POST['telefone'] ?? '');
      
      // Checkbox de opt-in para WhatsApp (opcional)
      $whatsapp_optin = isset($_POST['whatsapp_optin']) ? 1 : 0;
      
      // Verifica campos obrigatórios
      if (!$nome_completo || !$email || !$telefoneBruto) {
          $erro = "Por favor, preencha todos os campos obrigatórios (Nome, E-mail e Telefone).";
      } else {
          // Converte o telefone para o formato E.164
          $telefoneLimpo = preg_replace('/\D/', '', $telefoneBruto);
          if (strlen($telefoneLimpo) === 11) {
              $telefoneE164 = '55' . $telefoneLimpo;
          } else {
              $telefoneE164 = $telefoneLimpo;
          }
          
          // Salva os dados na sessão para a Etapa 2
          $_SESSION['form_nome']     = $nome_completo;
          $_SESSION['form_email']    = $email;
          $_SESSION['form_telefone'] = $telefoneE164;
          
          // REGISTRA OU ATUALIZA O CLIENTE NA TABELA "clientes"
          if (!$usuarioLogado) {
              $stmtCliente = $conn->prepare("SELECT id FROM clientes WHERE email = ?");
              $stmtCliente->bind_param("s", $email);
              $stmtCliente->execute();
              $resultCliente = $stmtCliente->get_result();
              if ($resultCliente->num_rows > 0) {
                  $clientRow  = $resultCliente->fetch_assoc();
                  $cliente_id = $clientRow['id'];
                  $stmtUpdateTel = $conn->prepare("UPDATE clientes SET telefone = ?, whatsapp_optin = ? WHERE id = ?");
                  $stmtUpdateTel->bind_param("sii", $telefoneE164, $whatsapp_optin, $cliente_id);
                  $stmtUpdateTel->execute();
                  $stmtUpdateTel->close();
              } else {
                  $tipo_usuario = 1;
                  $senha        = "";
                  $foto         = "";
                  $stmtInsert = $conn->prepare("INSERT INTO clientes (nome, email, telefone, senha, foto, tipo_usuario, whatsapp_optin) VALUES (?, ?, ?, ?, ?, ?, ?)");
                  if (!$stmtInsert) {
                      die("Erro na preparação da query: " . $conn->error);
                  }
                  $stmtInsert->bind_param("sssssis", $nome_completo, $email, $telefoneE164, $senha, $foto, $tipo_usuario, $whatsapp_optin);
                  $stmtInsert->execute();
                  $cliente_id = $stmtInsert->insert_id;
                  $stmtInsert->close();
              }
              $stmtCliente->close();
              
              $promotor_id = $_SESSION['promotor_id'] ?? null;
              if ($promotor_id) {
                  $stmtAssoc = $conn->prepare("SELECT * FROM cliente_promotor WHERE cliente_id = ? AND promotor_id = ?");
                  $stmtAssoc->bind_param("ii", $cliente_id, $promotor_id);
                  $stmtAssoc->execute();
                  $resultAssoc = $stmtAssoc->get_result();
                  if ($resultAssoc->num_rows == 0) {
                      $stmtInsertAssoc = $conn->prepare("INSERT INTO cliente_promotor (cliente_id, promotor_id) VALUES (?, ?)");
                      $stmtInsertAssoc->bind_param("ii", $cliente_id, $promotor_id);
                      $stmtInsertAssoc->execute();
                      $stmtInsertAssoc->close();
                  }
                  $stmtAssoc->close();
              }
          }
          
          // Se o usuário marcou o opt-in, abre o WhatsApp em nova aba e redireciona a página atual para a etapa 2 após 5 segundos
          if ($whatsapp_optin === 1) {
              // Define a mensagem a ser enviada via WhatsApp
              $mensagem = "Autorizo Receber meu Ingresso.";
              // Número do seu WhatsApp Business (no formato sem sinais, ex: 5534991921872)
              $whatsappNumber = "5534991921872";
              // Monta a URL do WhatsApp (Click to Chat)
              $waUrl = "https://api.whatsapp.com/send?phone={$whatsappNumber}&text=" . urlencode($mensagem);
              ?>
              <!DOCTYPE html>
              <html lang="pt-br">
              <head>
                <meta charset="UTF-8">
                <title>Redirecionando...</title>
                <!-- Após 5 segundos, direciona automaticamente para a etapa 2 -->
                <meta http-equiv="refresh" content="5; url=comprar_ingresso?step=2&evento_id=<?php echo $evento_id; ?>">
              </head>
              <body>
                <p>Abrindo o WhatsApp para sua autorização...<br>
                Em breve, você será redirecionado para continuar sua compra.</p>

                <!-- Abre o WhatsApp em nova aba -->
                <script>
                  window.open("<?php echo $waUrl; ?>", "_blank");
                </script>
              </body>
              </html>
              <?php
              exit();
          } else {
              header("Location: comprar_ingresso?step=2&evento_id={$evento_id}");
              exit();
          }
      }
  }

  // ----------------------------------------------------------------
  // 7) Processa o formulário da Etapa 2 (Escolha do Pagamento)
  // ----------------------------------------------------------------
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'etapa2') {
      // Armazena o device_id da integração, se enviado
      if (isset($_POST['device_id'])) {
          $_SESSION['device_id'] = $_POST['device_id'];
      }
      
      $tipoPagamento = $_POST['tipo_pagamento'] ?? '';
      if (!$tipoPagamento) {
          $erro2 = "Erro ao definir o método de pagamento.";
          $step = 2;
      } else {
          /*
          // ---------- COMENTADO: parte que valida CPF para Pix ----------
          if ($tipoPagamento === 'pix' || $tipoPagamento === 'pix2') {
              $cpf = trim($_POST['cpf'] ?? '');
              if (!preg_match('/^\d{11}$/', $cpf)) {
                  $erro2 = "CPF inválido. Informe 11 dígitos numéricos.";
                  $step = 2;
              } else {
                  $_SESSION['cpf'] = $cpf;
              }
          }
          */
          
          if (!isset($erro2)) {
              $_SESSION['total_pedido']   = $totalPedido;
              $_SESSION['tipo_pagamento'] = $tipoPagamento;
              
              // Processa os diferentes tipos de pagamento:
              if ($tipoPagamento === 'pix') {
                  header("Location: processa_pagamento_pix2");
                  exit();
              } elseif ($tipoPagamento === 'cartao') {
                  header("Location: pagamento_cartao.php");
                  exit();
              }
          }
      }
  }
  ?>
  <!DOCTYPE html>
  <html lang="pt-br">
  <head>
    <meta charset="UTF-8">
    <title>Compra de Ingressos</title>
    <link rel="icon" href="uploads/Group 11.svg" type="image/x-icon"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSS atualizado -->
    <link rel="stylesheet" href="css/comprar_ingresso.css">

    <!-- Mercado Pago SDK -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script>
      // IMPORTANTE: Substitua pela sua public key real
      const mp = new MercadoPago('TEST-4962cd62-9963-4c90-9f3b-example'); // Sua public key aqui
    </script>

    <script>
      // Contador de tempo
      let timeLeft = 600;
      function updateTimer() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        const timerElement = document.getElementById("timer");
        if (timerElement) {
          timerElement.textContent = "Tempo restante para finalizar a compra: " +
            minutes.toString().padStart(2, '0') + ":" + seconds.toString().padStart(2, '0');
        }
        if (timeLeft <= 0) {
          window.location.href = "timeout.php";
        }
        timeLeft--;
      }
      window.onload = function() {
        updateTimer();
        setInterval(updateTimer, 1000);
      };
    </script>

    <script>
      // Função para mascarar telefone
      function maskPhone(value) {
        return value
          .replace(/\D/g, '')
          .replace(/^(\d{2})(\d)/, '($1) $2')
          .replace(/(\d)(\d{4})$/, '$1-$2');
      }
      document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('telefone');
        if (phoneInput) {
          phoneInput.addEventListener('input', function(e) {
            e.target.value = maskPhone(e.target.value);
          });
        }
      });

      // Mostrar que o Pix está selecionado
      function selectPix() {
        // Remove seleção do cartão
        document.getElementById('cartaoRadio').checked = false;
        document.getElementById('cartaoCheckmark').style.display = 'none';
        document.getElementById('btnCartao').classList.remove('selected');

        // Marca o radio
        document.getElementById('pixRadio').checked = true;

        // Exibe o checkmark
        document.getElementById('pixCheckmark').style.display = 'inline';

        // Aplica a classe "selected" ao botão Pix
        const pixButton = document.getElementById('btnPix');
        pixButton.classList.add('selected');
      }

      // Mostrar que o Cartão está selecionado
      function selectCartao() {
        // Remove seleção do Pix
        document.getElementById('pixRadio').checked = false;
        document.getElementById('pixCheckmark').style.display = 'none';
        document.getElementById('btnPix').classList.remove('selected');

        // Marca o radio
        document.getElementById('cartaoRadio').checked = true;

        // Exibe o checkmark
        document.getElementById('cartaoCheckmark').style.display = 'inline';

        // Aplica a classe "selected" ao botão Cartão
        const cartaoButton = document.getElementById('btnCartao');
        cartaoButton.classList.add('selected');
      }
    </script>
  </head>
  <body>
    <header>
      <div class="logo">
        <a href="index">
          <img src="uploads/ticketsyhnklogo1.png" alt="Ticket Sync Logo">
        </a>
      </div>
    </header>
    <div class="container">
      <div id="timer"></div>
      <div class="step-indicator">
        <div class="circle <?php echo ($step == 2) ? 'checked' : 'active'; ?>">
          <?php echo ($step == 2) ? '✔' : '1'; ?>
        </div>
        <div class="step-title">Informações dos Participantes</div>
        <div class="circle <?php echo ($step == 2) ? 'active' : ''; ?>">
          2
        </div>
        <div class="step-title">Pagamento e Dados do Comprador</div>
      </div>
      <?php if (empty($itens)): ?>
        <p>Nenhum ingresso selecionado.</p>
        <p><a class="link-voltar" href="detalhes_evento?id=<?php echo $evento_id; ?>">Voltar</a></p>
      <?php else: ?>
        <div class="form-container">
          <div class="form-column">
            <!-- ETAPA 1 -->
            <?php if ($step == 1): ?>
              <?php if (isset($erro) && !empty($erro)): ?>
                <div class="error"><?php echo htmlspecialchars($erro); ?></div>
              <?php endif; ?>
              <form method="post" action="">
                <input type="hidden" name="action" value="etapa1">
                <input type="hidden" name="evento_id" value="<?php echo $evento_id; ?>">
                
                <?php if (!$usuarioLogado): ?>
                  <div style="margin-bottom: 15px;">
                    <span style="font-weight: bold; color: #555;">Agilize o preenchimento:</span>
                    <a href="login?returnUrl=comprar_ingresso?evento_id=<?php echo $evento_id; ?>">
                      <button type="button" class="btn-login">Fazer Login</button>
                    </a>
                  </div>
                <?php endif; ?>

                <div class="form-group">
                  <label for="nome_completo">Nome Completo*:</label>
                  <input type="text" id="nome_completo" name="nome_completo" required 
                    <?php if ($usuarioLogado): ?>
                      value="<?php echo htmlspecialchars($nomeUsuarioLogado . ' ' . $sobrenomeUsuarioLogado); ?>" readonly
                    <?php else: ?>
                      value="<?php echo isset($_SESSION['form_nome']) ? htmlspecialchars($_SESSION['form_nome']) : ''; ?>"
                    <?php endif; ?>>
                </div>
                <div class="form-group">
                  <label for="email">E-mail*:</label>
                  <small>O ingresso será enviado para este endereço.</small>
                  <input type="email" id="email" name="email" required
                    <?php if ($usuarioLogado): ?>
                      value="<?php echo htmlspecialchars($emailUsuarioLogado); ?>" readonly
                    <?php else: ?>
                      value="<?php echo isset($_SESSION['form_email']) ? htmlspecialchars($_SESSION['form_email']) : ''; ?>"
                    <?php endif; ?>>
                </div>
                <div class="form-group">
                  <label for="telefone">Telefone*:</label>
                  <small>Utilize o formato: (99) 99999-9999</small>
                  <input type="text" id="telefone" name="telefone" required
                        value="<?php echo isset($_SESSION['form_telefone']) ? htmlspecialchars($_SESSION['form_telefone']) : ''; ?>">
                </div>
                <!-- Checkbox de opt-in para WhatsApp -->
                <div class="form-group">
                  <input type="checkbox" id="whatsapp_optin" name="whatsapp_optin" value="1">
                  <label for="whatsapp_optin">
                    Desejo receber meu ingresso e comunicações via WhatsApp.
                  </label>
                </div>
                <div style="text-align: right;">
                  <button type="submit" class="btn-proximo">Próximo</button>
                </div>
              </form>
            
            <!-- ETAPA 2 -->
            <?php elseif ($step == 2): ?>
              <?php if (isset($erro2) && !empty($erro2)): ?>
                <div class="error"><?php echo htmlspecialchars($erro2); ?></div>
              <?php endif; ?>
              <form method="post" action="">
                <input type="hidden" name="action" value="etapa2">
                <input type="hidden" name="evento_id" value="<?php echo $evento_id; ?>">
                <input type="hidden" name="device_id" id="device_id">

                <h2 style="margin-bottom: 10px; color: #002f6d;">Escolha o método de pagamento</h2>
                
                <!-- Botão exclusivo para Pix -->
                <div class="form-group pix-button-container">
                  <input type="radio" name="tipo_pagamento" id="pixRadio" value="pix" checked style="display: none;">
                  <button type="button" class="btn-pix-animated" id="btnPix" onclick="selectPix()">
                    <img src="uploads/pix.svg" alt="Pix Icon" class="pix-icon">
                    <span>Pix</span>
                    <span class="pix-checkmark" id="pixCheckmark" style="display: none; margin-left: 6px;">✔</span>
                  </button>
                </div>

                <!-- Botão para Cartão -->
                <div class="form-group card-button-container">
                  <input type="radio" name="tipo_pagamento" id="cartaoRadio" value="cartao" style="display: none;">
                  <button type="button" class="btn-card-animated" id="btnCartao" onclick="selectCartao()">
                    <img src="uploads/card.png" alt="Cartão Icon" class="card-icon">
                    <span>Cartão de Crédito</span>
                    <span class="card-checkmark" id="cartaoCheckmark" style="display: none; margin-left: 6px;">✔</span>
                  </button>
                </div>

                <div class="button-row">
                  <a class="btn-proximo btn-voltar" href="?step=1&evento_id=<?php echo $evento_id; ?>">Voltar</a>
                  <button type="submit" class="btn-proximo">Confirmar Pagamento</button>
                </div>
              </form>
            <?php endif; ?>

          </div>
          
          <div class="summary-column">
            <div class="resumo-pedido">
              <h3>Resumo do Pedido</h3>
              <?php foreach ($itens as $item): ?>
                <div class="resumo-item">
                  <?php echo $item['qtd']; ?> × <?php echo htmlspecialchars($item['desc']); ?><br>
                  <strong>R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?> cada</strong>
                </div>
              <?php endforeach; ?>
              <div class="resumo-item">
                Taxas (10,00%): R$ <?php echo number_format($taxas, 2, ',', '.'); ?>
              </div>
              <div class="resumo-total">
                Total: R$ <?php echo number_format($totalPedido, 2, ',', '.'); ?>
              </div>
            </div>
          </div>
        </div> <!-- /.form-container -->
      <?php endif; ?>
    </div> <!-- /.container -->

  </body>
  </html>
