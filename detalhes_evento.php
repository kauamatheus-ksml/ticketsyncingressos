<?php
// detalhes_evento.php
session_start();
unset($_SESSION['ingressos']); // Zera os dados de ingressos para iniciar uma nova compra

include('conexao.php');

// Recebe o ID do evento via GET e valida
$eventoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$eventoId) {
    echo "Evento inválido!";
    exit();
}

// Consulta informações do evento
$sqlEvento = "SELECT * FROM eventos WHERE id = ?";
$stmt = $conn->prepare($sqlEvento);
$stmt->bind_param("i", $eventoId);
$stmt->execute();
$resultEvento = $stmt->get_result();
$evento = $resultEvento->fetch_assoc();
if (!$evento) {
    echo "Evento não encontrado!";
    exit();
}

// Consulta ingressos autorizados para este evento
$sqlIngressos = "SELECT * FROM ingressos WHERE evento_id = ? AND liberado = 1";
$stmt2 = $conn->prepare($sqlIngressos);
$stmt2->bind_param("i", $eventoId);
$stmt2->execute();
$ingressosResult = $stmt2->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($evento['nome']); ?> - Detalhes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <!-- Fonte moderna -->
  <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600&display=swap" rel="stylesheet">
  <!-- Seu CSS -->
  <link rel="stylesheet" href="css/detalhes_evento.css">
  <style>
    /* Oculta o resumo do pedido se nenhum ingresso for selecionado */
    .resumo-section {
      display: none;
    }
    /* Container para o cabeçalho do resumo com título e botão */
    .resumo-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }
    /* Cupom: o container do cupom fica oculto em telas móveis */
    #cupomContainer {
      display: none;
    }
    /* Botão para mostrar o cupom - exibido apenas em telas menores */
    @media (max-width: 768px) {
      #btnMostrarCupom {
        display: block;
        background-color: #007BFF;
        color: #fff;
        padding: 8px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
      }
    }
    /* Em telas maiores, exibe o cupom normalmente e oculta o botão */
    @media (min-width: 769px) {
      #cupomContainer {
        display: block;
      }
      #btnMostrarCupom {
        display: none;
      }
    }
  </style>
  <script>
    let cartData = {};

    function toggleCart() {
      const cartDropdown = document.getElementById('cartDropdown');
      cartDropdown.style.display = cartDropdown.style.display === 'block' ? 'none' : 'block';
    }

    function diminuirQuantidade(btn) {
      const input = btn.nextElementSibling;
      let valor = parseInt(input.value) || 0;
      if (valor > 0) {
        input.value = valor - 1;
        recalcularTotal();
      }
    }

    function aumentarQuantidade(btn) {
      const input = btn.previousElementSibling;
      let valor = parseInt(input.value) || 0;
      const ingressoItem = btn.closest('.ingresso-item');
      const available = parseInt(ingressoItem.getAttribute('data-available')) || 0;
      if (valor < available) {
        input.value = valor + 1;
        recalcularTotal();
      }
    }

    function recalcularTotal () {
      let subtotal = 0.0;
      cartData = {};                        // zera estrutura

      document.querySelectorAll('.ingresso-item').forEach((item, idx) => {
        const preco      = parseFloat(item.dataset.preco)       || 0;
        const qtd        = parseInt(item.querySelector('.quant-input').value) || 0;
        const descricao  = item.querySelector('[name^="ingressos"][name$="[descricao]"]').value;
        const id         = item.querySelector('[name^="ingressos"][name$="[id]"]').value;

        if (qtd > 0) {
          subtotal += preco * qtd;

          cartData['ingresso_' + idx] = {
            id,            // ← agora armazenamos o ID
            descricao,
            preco,
            quantidade: qtd
          };
        }
      });

      const taxa          = subtotal * 0.10;   // 10 %
      const totalComTaxa  = subtotal + taxa;

      atualizarCarrinho(totalComTaxa);
    }


    function atualizarCarrinho(total) {
      const cartItemsContainer = document.getElementById('cartItems');
      const cartTotalEl = document.getElementById('cartTotal');
      const badge = document.getElementById('cart-quantity-badge');

      const resumoItensContainer = document.getElementById('resumoItens');
      const resumoTotalEl = document.getElementById('resumoTotal');
      const resumoSection = document.querySelector('.resumo-section');

      cartItemsContainer.innerHTML = '';
      resumoItensContainer.innerHTML = '';

      let totalItens = 0;
      for (let itemId in cartData) {
        const item = cartData[itemId];
        totalItens += item.quantidade;

        const subtotal = item.preco * item.quantidade;
        const subtotalTxt = subtotal.toLocaleString('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });

        const itemRow = document.createElement('div');
        itemRow.classList.add('cart-item-row');
        itemRow.textContent = `${item.quantidade}x ${item.descricao} - R$ ${subtotalTxt}`;
        cartItemsContainer.appendChild(itemRow);

        const resumoRow = document.createElement('div');
        resumoRow.classList.add('resumo-item-row');
        resumoRow.textContent = `${item.quantidade}x ${item.descricao} - R$ ${subtotalTxt}`;
        resumoItensContainer.appendChild(resumoRow);
      }

      if (totalItens === 0) {
        cartItemsContainer.innerHTML = '<p style="text-align:center; color:#666;">Carrinho vazio</p>';
        resumoItensContainer.innerHTML = '<p style="text-align:center; color:#666;">Nenhum ingresso selecionado</p>';
        resumoSection.style.display = 'none';
      } else {
        resumoSection.style.display = 'block';
      }

      cartTotalEl.textContent = total.toLocaleString('pt-BR', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
      });
      badge.textContent = totalItens;
      resumoTotalEl.textContent = total.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    function finalizarCompra() {
      const compraForm = document.getElementById('compraForm');
      const hiddenQuantidadeCampos = compraForm.querySelectorAll('[type="hidden"][name^="ingressos"][name$="[quantidade]"]');
      hiddenQuantidadeCampos.forEach(campo => campo.value = 0);

      const items = document.querySelectorAll('.ingresso-item');
      items.forEach((item, idx) => {
        const qtd = parseInt(item.querySelector('.quant-input').value) || 0;
        const hiddenQuantidade = item.querySelector('[type="hidden"][name^="ingressos"][name$="[quantidade]"]');
        hiddenQuantidade.value = qtd;
      });

      document.getElementById('compraForm').submit();
    }

    function compartilharEvento() {
      const url = window.location.href;
      navigator.clipboard.writeText(url)
        .then(() => {
          alert('Link do evento copiado para compartilhamento!');
        })
        .catch(() => {
          alert('Falha ao copiar o link. Tente novamente.');
        });
    }

    function toggleCupom() {
      const cupomContainer = document.getElementById('cupomContainer');
      if (cupomContainer.style.display === 'block') {
        cupomContainer.style.display = 'none';
      } else {
        cupomContainer.style.display = 'block';
      }
    }

    window.onclick = function(event) {
      const cartDropdown = document.getElementById('cartDropdown');
      const cartBtn = document.querySelector('.cart-btn');
      if (event.target !== cartBtn && !cartBtn.contains(event.target)) {
        if (event.target !== cartDropdown && !cartDropdown.contains(event.target)) {
          cartDropdown.style.display = 'none';
        }
      }
    };
  </script>
</head>
<body>
  <!-- HEADER -->
  <header>
    <div class="logo">
      <a href="index.php">
        <img src="uploads/ticketsyhnklogo.svg" alt="Ticket Sync Logo">
      </a>
    </div>
    <div class="cart-container">
      <button class="cart-btn" type="button" onclick="toggleCart()">
        <img src="uploads/grocery-store.png" alt="Carrinho">
        <span id="cart-quantity-badge">0</span>
      </button>
      <div class="cart-dropdown" id="cartDropdown">
        <h2>Seu Carrinho</h2>
        <div class="cart-items" id="cartItems"></div>
        <div class="cart-total">Total: R$ <span id="cartTotal">0,00</span></div>
        <button class="btn-finalize" onclick="finalizarCompra()">Finalizar Compra</button>
      </div>
    </div>
  </header>
  <br><br>

  <!-- CONTEÚDO PRINCIPAL -->
  <div class="container">
    <div class="evento-header">
      <?php if (!empty($evento['logo'])): ?>
        <img src="<?php echo htmlspecialchars($evento['logo']); ?>" alt="Logo do Evento">
      <?php else: ?>
        <img src="default-event.jpg" alt="Logo Padrão">
      <?php endif; ?>
      <div class="evento-info">
        <h1><?php echo htmlspecialchars($evento['nome']); ?></h1>
        <p><strong>Data:</strong> <?php echo date('d/m/Y', strtotime($evento['data_inicio'])); ?></p>
        <p><strong>Horário:</strong> <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?></p>
        <p><strong>Local:</strong> <?php echo htmlspecialchars($evento['local']); ?></p>
        <button class="btn-share" onclick="compartilharEvento()">Compartilhar Evento</button>
      </div>
    </div>
    
    <!-- Formulário e Resumo Lateral/Inferior -->
    <form id="compraForm" action="comprar_ingresso" method="POST">
      <input type="hidden" name="evento_id" value="<?php echo $eventoId; ?>" />
      
      <div class="compra-container">
        <!-- COLUNA DE INGRESSOS -->
        <div class="ingressos-section">
          <h2>Ingressos Disponíveis</h2>
          <?php if ($ingressosResult->num_rows > 0): ?>
            <?php 
              $index = 0; 
              while($ing = $ingressosResult->fetch_assoc()):
                $precoBase = (float) $ing['preco'];
                $taxaValor = $precoBase * 0.10;
                $precoComTaxa = $precoBase + $taxaValor;
                $parcela12 = $precoComTaxa / 12;
            ?>
              <div class="ingresso-item" 
                   data-preco="<?php echo $precoBase; ?>" 
                   data-available="<?php echo $ing['quantidade']; ?>">
                <div class="info">
                  <p><strong>Tipo:</strong> <?php echo htmlspecialchars($ing['tipo_ingresso']); ?></p>
                  <p>
                    <strong>Preço:</strong> 
                    R$ <?php echo number_format($precoBase, 2, ',', '.'); ?>
                    (+ R$ <?php echo number_format($taxaValor, 2, ',', '.'); ?> taxa)
                  </p>
                  <p><strong>Disponível:</strong> <?php echo $ing['quantidade']; ?></p>
                </div>
                <div class="ingresso-quantidade">
                  <button type="button" class="quant-btn" onclick="diminuirQuantidade(this)">−</button>
                  <input type="number" class="quant-input" value="0" min="0" max="<?php echo $ing['quantidade']; ?>" oninput="recalcularTotal()" id="qtd_<?php echo $index; ?>">
                  <button type="button" class="quant-btn" onclick="aumentarQuantidade(this)">+</button>
                  <input type="hidden" name="ingressos[<?php echo $index; ?>][quantidade]" id="hidden_qtd_<?php echo $index; ?>" value="0">
                  <!-- INSERIR estes 3 inputs ocultos -->
                  <input type="hidden" name="ingressos[<?php echo $index; ?>][id]"        value="<?php echo $ing['id']; ?>">
                  <input type="hidden" name="ingressos[<?php echo $index; ?>][descricao]" value="<?php echo htmlspecialchars($ing['tipo_ingresso']); ?>">
                  <input type="hidden" name="ingressos[<?php echo $index; ?>][preco]"     value="<?php echo $precoBase; ?>">

                  <input type="hidden" name="ingressos[<?php echo $index; ?>][descricao]" value="<?php echo htmlspecialchars($ing['tipo_ingresso']); ?>">
                  <input type="hidden" name="ingressos[<?php echo $index; ?>][preco]" value="<?php echo $precoBase; ?>">
                </div>
              </div>
            <?php 
              $index++;
              endwhile; 
            ?>
          <?php else: ?>
            <p>Nenhum ingresso autorizado para este evento.</p>
          <?php endif; ?>

          <div class="info-section">
            <h3>Política do Evento</h3>
            <p><strong>Cancelamento de pedidos pagos:</strong> Cancelamentos aceitos até 7 dias após a compra, desde que a solicitação seja feita até 48 horas antes do início do evento.</p>
            <br>
            <p><strong>Edição de participantes:</strong> Você pode editar o participante de um ingresso até 24 horas antes do início do evento.</p>
          </div>
          <div class="info-section">
            <h3>Local</h3>
            <p><?php echo htmlspecialchars($evento['local']); ?></p>
            <br>
            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo rawurlencode($evento['local']); ?>" target="_blank" class="mapa-btn">Ver no mapa</a>
          </div>
          <div class="info-section">
            <h3>Métodos de Pagamento</h3>
            <br>
            <p>
              <img src="uploads/logo-pix-954x339.png" alt="Pix" style="height:30px; margin-right:10px;">
            </p>
            <br>
            <p>Compre com total segurança: dados criptografados, conformidade PCI-DSS, etc.</p>
          </div>
        </div>

        <!-- COLUNA DE RESUMO (desktop) / CARD FIXO (mobile) -->
        <div class="resumo-section">
          <div class="resumo-header">
            <h2>Resumo do Pedido</h2>
            <button type="button" id="btnMostrarCupom" onclick="toggleCupom()">Inserir Cupom</button>
          </div>
          <div class="resumo-itens" id="resumoItens">
            <!-- Será preenchido via JavaScript -->
          </div>
          <div class="resumo-total">
            <span>Total:</span>
            <span class="valor">R$ <span id="resumoTotal">0,00</span></span>
          </div>
          <div class="promocional" id="cupomContainer">
            <label for="cupom">Inserir código promocional</label>
            <input type="text" id="cupom" name="cupom" placeholder="Ex: PROMO10">
          </div>
          <button type="button" class="btn-comprar" onclick="finalizarCompra()">Comprar Ingressos</button>
        </div>
      </div>
    </form>
  </div>
  <script src="detalhes_evento.js"></script>
  <script src="jdkfront.js"></script>
</body>
</html>
