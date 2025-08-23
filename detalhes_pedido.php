<?php
// detalhes_pedido.php
session_start();
include('conexao.php');

// Verifica se o usuário está logado
if (!isset($_SESSION['userid']) || empty($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Pega o order_id via GET
if (!isset($_GET['order_id'])) {
    header("Location: meus_ingressos.php");
    exit();
}

$orderId = $_GET['order_id'];

// Consulta o pedido + evento utilizando as novas colunas
$sql = "
    SELECT 
        p.id,
        p.order_id,
        p.nome,
        p.sobrenome,
        p.email,
        p.valor_total,
        p.status,
        p.created_at,
        p.ingresso_validado,
        p.forma_pagamento,
        p.itens_json,
        p.ticket_code,
        e.nome AS evento_nome,
        e.logo AS evento_logo,
        e.data_inicio AS evento_data,
        e.hora_inicio AS evento_horario,
        e.local AS evento_local
    FROM pedidos p
    JOIN eventos e ON p.evento_id = e.id
    WHERE p.order_id = '$orderId'
      AND p.email = '" . $_SESSION['email'] . "'
    LIMIT 1
";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    $conn->close();
    header("Location: meus_ingressos.php");
    exit();
}

$pedido = $result->fetch_assoc();

// Consulta os ingressos individuais já validados
$sql_validados = "
    SELECT ingresso_index 
    FROM validacoes_individuais 
    WHERE order_id = '{$orderId}'
";
$result_validados = $conn->query($sql_validados);
$ingressosValidados = [];
if ($result_validados && $result_validados->num_rows > 0) {
    while ($row = $result_validados->fetch_assoc()) {
        $ingressosValidados[] = $row['ingresso_index'];
    }
}

$itens = [];
$totalQty = 0;

if (!empty($pedido['itens_json'])) {
    $decoded = json_decode($pedido['itens_json'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            $itens[] = $item;
            $totalQty += intval($item['quantidade']);
        }
    }
}

$statusLower = strtolower($pedido['status']);
$statusTraduzido = 'Cancelado';
if ($statusLower === 'approved') {
    $statusTraduzido = 'Aprovado';
} elseif ($statusLower === 'pending') {
    $statusTraduzido = 'Pendente';
}

$validadoTexto = ($pedido['ingresso_validado'] == 1) ? 'Validado' : 'Não validado';

// Verifica se todos os ingressos estão validados
$todosValidados = (count($ingressosValidados) >= $totalQty);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Detalhes do Pedido #<?php echo htmlspecialchars($pedido['order_id']); ?> - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" 
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- Biblioteca QRCode.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <!-- CSS Especial -->
  <link rel="stylesheet" href="css/detalhes_pedidos.css">
  <style>
    /* Estilos para o componente de QR Code */
    .dp-qr-wrapper {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
      margin: 20px 0;
    }
    .dp-qr-box {
      max-width: 260px;
      margin: 10px;
      text-align: center;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      position: relative;
      transition: all 0.3s ease;
    }
    .dp-qr-code {
      width: 256px;
      height: 256px;
      margin: 0 auto;
    }
    .dp-qr-code-timer {
      width: 100%;
      height: 10px;
      background-color: #eaeaea;
      margin-top: 10px;
      border-radius: 5px;
      overflow: hidden;
    }
    .dp-qr-code-progress {
      height: 100%;
      background-color: #007bff;
      width: 100%;
      transition: width 1s linear;
    }
    .dp-qr-code-text {
      margin-top: 5px;
      font-size: 1rem;
      color: #333;
    }
    .dp-qr-ingresso-info {
      margin-top: 10px;
      font-weight: bold;
      color: #333;
    }
    .dp-qr-validado {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.7);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      color: white;
      font-size: 1.5rem;
      border-radius: 8px;
    }
    .dp-qr-validado i {
      font-size: 3rem;
      margin-bottom: 15px;
      color: #4CAF50;
    }
    .dp-qr-validado-count {
      margin-top: 15px;
      padding: 8px 16px;
      background-color: #4CAF50;
      color: white;
      border-radius: 4px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <?php 
    // Exibe header conforme o tipo de usuário
    if (isset($_SESSION['adminid'])) {
      include('header_admin.php');
    } else {
      include('header_cliente.php');
    }
  ?>

  <div id="dp-wrapper">
    <div class="dp-container">
      <h1 class="dp-title">
        Detalhes do pedido #<?php echo htmlspecialchars($pedido['order_id']); ?> - <?php echo htmlspecialchars($pedido['evento_nome']); ?>
      </h1>

      <!-- Exibe o QR Code somente se o ingresso estiver aprovado -->
      <?php if ($statusLower === 'approved' && $totalQty > 0): ?>
      <div class="dp-section" id="qrSection">
        <h2 class="dp-section-title">QR Codes dos Ingressos</h2>
        
        <?php if (!$todosValidados): ?>
          <div class="dp-qr-validado-count" id="validationCounter">
            <i class="fa-solid fa-check-circle"></i>
            <span id="validatedCount"><?= count($ingressosValidados) ?></span> de <?= $totalQty ?> ingressos validados
          </div>
        <?php else: ?>
          <div class="dp-qr-validado-count" style="background-color: #2196F3;">
            <i class="fa-solid fa-check-double"></i>
            Todos os ingressos foram validados!
          </div>
        <?php endif; ?>
        
        <div class="dp-qr-wrapper">
          <?php for ($i = 1; $i <= $totalQty; $i++): ?>
            <?php $isValidated = in_array($i, $ingressosValidados); ?>
            <div class="dp-qr-box" id="qrBox<?= $i ?>" <?= $isValidated ? 'style="opacity: 0.6;"' : '' ?>>
              <?php if ($isValidated): ?>
                <div class="dp-qr-validado">
                  <i class="fa-solid fa-check-circle"></i>
                  Validado
                </div>
              <?php endif; ?>
              <div class="dp-qr-ingresso-info">Ingresso #<?= $i ?></div>
              <div id="qr<?= $i ?>" class="dp-qr-code"></div>
              <div class="dp-qr-code-timer">
                <div id="qrProgress<?= $i ?>" class="dp-qr-code-progress"></div>
              </div>
              <div id="qrText<?= $i ?>" class="dp-qr-code-text">30 s</div>
            </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="dp-section">
        <h2 class="dp-section-title">Informações do Pedido</h2>
        <div class="dp-info-row">
          <span class="dp-label">Nome:</span>
          <span class="dp-info"><?php echo htmlspecialchars($pedido['nome'] . ' ' . $pedido['sobrenome']); ?></span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Email:</span>
          <span class="dp-info"><?php echo htmlspecialchars($pedido['email']); ?></span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Status:</span>
          <span class="dp-info"><?php echo $statusTraduzido; ?></span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Data da Compra:</span>
          <span class="dp-info"><?php echo date("d/m/Y H:i", strtotime($pedido['created_at'])); ?></span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Situação:</span>
          <span class="dp-info">
            <?php if ($todosValidados): ?>
              Todos os ingressos foram validados
            <?php else: ?>
              <?= count($ingressosValidados) ?> de <?= $totalQty ?> ingressos validados
            <?php endif; ?>
          </span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Evento:</span>
          <span class="dp-info">
            <?php echo htmlspecialchars($pedido['evento_nome']); ?> 
            (<?php echo date("d/m/Y", strtotime($pedido['evento_data'])); ?> às <?php echo date("H:i", strtotime($pedido['evento_horario'])); ?>)
          </span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Local:</span>
          <span class="dp-info"><?php echo htmlspecialchars($pedido['evento_local']); ?></span>
        </div>
      </div>

      <div class="dp-section">
        <h2 class="dp-section-title">Detalhes de Pagamento</h2>
        <div class="dp-info-row">
          <span class="dp-label">Forma de Pagamento:</span>
          <span class="dp-info"><?php echo htmlspecialchars($pedido['forma_pagamento'] ?: 'Pix'); ?></span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Valor:</span>
          <span class="dp-info">R$ <?php echo number_format($pedido['valor_total'], 2, ',', '.'); ?></span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Status do Pagamento:</span>
          <span class="dp-info"><?php echo $statusTraduzido; ?></span>
        </div>
        <div class="dp-info-row">
          <span class="dp-label">Data do Pagamento:</span>
          <span class="dp-info"><?php echo date("d/m/Y H:i", strtotime($pedido['created_at'])); ?></span>
        </div>
      </div>

      <div class="dp-section">
        <h2 class="dp-section-title">Itens do Pedido</h2>
        <?php if (!empty($itens)): ?>
          <?php foreach ($itens as $i => $item): ?>
            <div class="dp-info-row">
              <span class="dp-info">
                • <?php echo htmlspecialchars($item['descricao'] ?? 'Item'); ?> 
                  - R$ <?php echo number_format(($item['preco'] ?? 0), 2, ',', '.'); ?> 
                  x <?php echo $item['quantidade']; ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="dp-info">Nenhum item detalhado (itens_json vazio ou não configurado).</p>
        <?php endif; ?>
      </div>

      <!-- Botão de Imprimir -->
      <div class="dp-action">
        <a href="imprimir_ingresso.php?order_id=<?php echo urlencode($pedido['order_id']); ?>" class="dp-print-btn" target="_blank">
          <i class="fa-solid fa-print"></i> Imprimir
        </a>
      </div>
    </div>
  </div>

  <!-- Script para gerenciar os QR codes e verificação do status -->
  <script>
    (function(){
      const totalQR = <?= $totalQty ?>;
      const intervalSec = 30;
      const orderId = "<?php echo htmlspecialchars($pedido['order_id']); ?>";
      const baseTicket = "<?php echo htmlspecialchars($pedido['ticket_code']); ?>";
      let remaining = intervalSec;
      
      // Array com os ingressos já validados
      const validatedTickets = <?= json_encode($ingressosValidados) ?>;
      
      // Gera e injeta um QR Code dinâmico para o índice informado
      function renderQR(idx) {
        // Não renderiza QR se já foi validado
        if (validatedTickets.includes(idx)) {
          return;
        }
        
        const el = document.getElementById('qr' + idx);
        if (!el) return;
        
        el.innerHTML = '';
        // Código dinâmico que inclui o índice do ingresso
        const code = baseTicket + '-' + idx + '-' + (Math.floor(Date.now()/1000) % intervalSec);
        new QRCode(el, {
          text: code,
          width: 256,
          height: 256,
          colorDark : "#000000",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });
      }

      // Inicializa os QR Codes não validados
      for (let i = 1; i <= totalQR; i++) {
        if (!validatedTickets.includes(i)) {
          renderQR(i);
        }
      }

      // Atualiza barra de progresso e regenera quando chega a zero
      const timerInterval = setInterval(() => {
        remaining--;
        if (remaining < 0) {
          for (let i = 1; i <= totalQR; i++) {
            if (!validatedTickets.includes(i)) {
              renderQR(i);
            }
          }
          remaining = intervalSec;
        }
        
        const pct = (remaining / intervalSec) * 100;
        for (let i = 1; i <= totalQR; i++) {
          if (!validatedTickets.includes(i)) {
            const progressEl = document.getElementById('qrProgress' + i);
            const textEl = document.getElementById('qrText' + i);
            if (progressEl) progressEl.style.width = pct + '%';
            if (textEl) textEl.textContent = remaining + ' s';
          }
        }
      }, 1000);

      // Função para verificar o status de validação de cada ingresso
      function checkValidationStatus() {
        fetch('check_validacoes.php?order_id=' + orderId)
          .then(response => response.json())
          .then(data => {
            let newValidationsFound = false;
            
            data.validados.forEach(idx => {
              // Verifica se é uma nova validação
              if (!validatedTickets.includes(idx)) {
                newValidationsFound = true;
                validatedTickets.push(idx);
                
                // Atualiza visualmente o elemento
                const qrBox = document.getElementById('qrBox' + idx);
                if (qrBox) {
                  qrBox.style.opacity = '0.6';
                  
                  // Adiciona o marcador de validado
                  const validadoDiv = document.createElement('div');
                  validadoDiv.className = 'dp-qr-validado';
                  validadoDiv.innerHTML = '<i class="fa-solid fa-check-circle"></i>Validado';
                  qrBox.appendChild(validadoDiv);
                }
              }
            });
            
            // Atualiza o contador
            if (newValidationsFound) {
              const countEl = document.getElementById('validatedCount');
              if (countEl) {
                countEl.textContent = validatedTickets.length;
              }
              
              // Se todos validados, mostra mensagem e limpa o intervalo
              if (validatedTickets.length >= totalQR) {
                const counterEl = document.getElementById('validationCounter');
                if (counterEl) {
                  counterEl.style.backgroundColor = '#2196F3';
                  counterEl.innerHTML = '<i class="fa-solid fa-check-double"></i> Todos os ingressos foram validados!';
                }
                clearInterval(validationInterval);
              }
            }
          })
          .catch(error => {
            console.error("Erro ao verificar validações:", error);
          });
      }
      
      // Verificar a cada 5 segundos
      const validationInterval = setInterval(checkValidationStatus, 5000);
      
      // Verifica imediatamente ao carregar
      checkValidationStatus();
    })();
  </script>
</body>
</html>