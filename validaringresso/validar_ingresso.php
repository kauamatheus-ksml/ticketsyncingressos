<?php
// validar_ingresso.php (AppView)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../conexao.php';

// ==========================================================
// Processa validação via GET (ação do botão "check" na listagem)
// ==========================================================
if (isset($_GET['validate_id']) && !empty($_SESSION['selected_event_code'])) {
    $pedidoId = intval($_GET['validate_id']);
    $stmtValidate = $conn->prepare("
        SELECT p.id, p.status, p.ingresso_validado, e.codigo_evento, p.ticket_code
        FROM pedidos p
        INNER JOIN eventos e ON p.evento_id = e.id
        WHERE p.id = ? LIMIT 1
    ");
    $stmtValidate->bind_param("i", $pedidoId);
    $stmtValidate->execute();
    $resultValidate = $stmtValidate->get_result();
    if ($resultValidate->num_rows > 0) {
        $rowValidate = $resultValidate->fetch_assoc();
        if ($rowValidate['codigo_evento'] == $_SESSION['selected_event_code']) {
            if ($rowValidate['ingresso_validado'] != 1 && $rowValidate['status'] == 'approved') {
                $stmtUpdate = $conn->prepare("UPDATE pedidos SET ingresso_validado = 1 WHERE id = ?");
                $stmtUpdate->bind_param("i", $pedidoId);
                if ($stmtUpdate->execute()) {
                    $_SESSION['flash_message'] = "Ingresso validado com sucesso.";
                    $_SESSION['flash_type'] = "success";
                } else {
                    $_SESSION['flash_message'] = "Erro ao validar ingresso.";
                    $_SESSION['flash_type'] = "error";
                }
                $stmtUpdate->close();
            } else {
                $_SESSION['flash_message'] = "Ingresso já validado ou não aprovado.";
                $_SESSION['flash_type'] = "warning";
            }
        } else {
            $_SESSION['flash_message'] = "Ingresso não pertence ao evento selecionado.";
            $_SESSION['flash_type'] = "error";
        }
    } else {
        $_SESSION['flash_message'] = "Ingresso não encontrado.";
        $_SESSION['flash_type'] = "error";
    }
    header("Location: validar_ingresso.php?app=true");
    exit;
}

// ---------------------------------------
// 1) Verifica se o usuário está logado (adm ou funcionário)
// ---------------------------------------
$isPromotor = false; // Mantido caso precise de checagem

if (isset($_SESSION['adminid'])) {
    $userId = $_SESSION['adminid'];
    $sql = "SELECT nome, is_promotor FROM administradores WHERE id = $userId";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['is_promotor']) {
            $isPromotor = true;
        }
        $_SESSION['nome'] = $row['nome'];
    } else {
        header("Location: logout.php");
        exit;
    }
} elseif (isset($_SESSION['funcionarioid'])) {
    $userId = $_SESSION['funcionarioid'];
    $sql = "SELECT nome FROM funcionarios WHERE id = $userId";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $_SESSION['nome'] = $row['nome'];
    } else {
        header("Location: logout.php");
        exit;
    }
} else {
    header("Location: logout.php");
    exit;
}

// ---------------------------------------
// 2) Lógica do "Trocar Evento"
// ---------------------------------------
if (isset($_GET['trocar_evento']) && $_GET['trocar_evento'] == 1) {
    unset($_SESSION['selected_event_code'], $_SESSION['selected_event_timestamp'], $_SESSION['selected_event_name']);
    header("Location: validar_ingresso.php?app=true");
    exit;
}

// ---------------------------------------
// 3) Verificação de expiração do código do evento (24h = 86400 seg)
// ---------------------------------------
if (
    isset($_SESSION['selected_event_code']) &&
    isset($_SESSION['selected_event_timestamp']) &&
    (time() - $_SESSION['selected_event_timestamp'] >= 86400)
) {
    unset($_SESSION['selected_event_code'], $_SESSION['selected_event_timestamp'], $_SESSION['selected_event_name']);
}

// ---------------------------------------
// 4) Processa POST do modal para definir o código do evento
// ---------------------------------------
$erroCodigoEvento = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_event_code') {
    $inputCodigoEvento = trim($_POST['codigo_evento_modal']);
    $stmtEvento = $conn->prepare("
        SELECT id, nome, status
        FROM eventos
        WHERE codigo_evento = ?
        LIMIT 1
    ");
    $stmtEvento->bind_param("i", $inputCodigoEvento);
    $stmtEvento->execute();
    $resultadoEvento = $stmtEvento->get_result();
    if ($resultadoEvento->num_rows > 0) {
        $rowE = $resultadoEvento->fetch_assoc();
        if (strtolower($rowE['status']) !== 'approved' && strtolower($rowE['status']) !== 'aprovado') {
            $erroCodigoEvento = "Este evento ainda não está aprovado e não pode validar ingressos.";
        } else {
            $_SESSION['selected_event_code'] = $inputCodigoEvento;
            $_SESSION['selected_event_timestamp'] = time();
            $_SESSION['selected_event_name'] = $rowE['nome'];
        }
    } else {
        $erroCodigoEvento = "Código de evento inválido ou inexistente.";
    }
    $stmtEvento->close();
    if (empty($erroCodigoEvento)) {
        header("Location: validar_ingresso.php?app=true");
        exit;
    }
}

// ---------------------------------------
// 5) Processa POST para validar ingresso (ticket_code)
// ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_code']) && !empty($_SESSION['selected_event_code'])) {
    // Pega o código submetido e, se for dinâmico (contiver hífen), extrai a parte base
    $submittedTicketCode = trim($_POST['ticket_code']);
    if (strpos($submittedTicketCode, '-') !== false) {
        $parts = explode('-', $submittedTicketCode);
        $baseTicketCode = $parts[0];
    } else {
        $baseTicketCode = $submittedTicketCode;
    }
    $message = "";
    $messageType = "";

    $stmt = $conn->prepare("
        SELECT p.id, p.status, p.ingresso_validado, p.nome AS nome_comprador, e.codigo_evento, p.ticket_code
        FROM pedidos p
        INNER JOIN eventos e ON p.evento_id = e.id
        WHERE p.ticket_code = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $baseTicketCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $nomeClienteValido = $row['nome_comprador'];
        if ($row['codigo_evento'] != $_SESSION['selected_event_code']) {
            $message = "O ingresso a ser validado é de outro evento; você não está autorizado a validá-lo.";
            $messageType = "error";
        } else {
            if ($row['status'] !== 'approved') {
                $message = "Este ingresso não está aprovado (status: {$row['status']}).";
                $messageType = "error";
            } elseif ($row['ingresso_validado'] == 1) {
                $message = "O ingresso de {$nomeClienteValido} já foi validado.";
                $messageType = "warning";
            } else {
                $pedidoId = $row['id'];
                $stmtUpdate = $conn->prepare("UPDATE pedidos SET ingresso_validado = 1 WHERE id = ?");
                $stmtUpdate->bind_param("i", $pedidoId);
                if ($stmtUpdate->execute()) {
                    $message = "Bom Evento, {$nomeClienteValido}!";
                    $messageType = "success";
                } else {
                    $message = "Erro ao validar o ingresso.";
                    $messageType = "error";
                }
                $stmtUpdate->close();
            }
        }
    } else {
        $message = "Ingresso não encontrado ou código inválido.";
        $messageType = "error";
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;
    header("Location: validar_ingresso.php?app=true");
    exit;
}

// ---------------------------------------
// 6) Mensagens de retorno (flash)
// ---------------------------------------
$message = "";
$messageType = "";
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Validar Ingresso - AppView</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="icon" href="../uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#000000">

  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- Biblioteca html5-qrcode -->
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <!-- CSS reestruturado -->
  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Header mínimo: só o botão de logout -->
<header>
  <div class="logout-container">
    <a href="logout.php" title="Sair">
      <i class="fa-solid fa-right-from-bracket"></i>
    </a>
  </div>
</header>

<div class="main-content">
  <div class="ingressos-container">
    <div class="welcome">
      <?php
        if (!empty($_SESSION['nome'])) {
            echo "Bem-vindo, " . htmlspecialchars($_SESSION['nome']) . "!";
        } else {
            echo "Bem-vindo!";
        }
      ?>
    </div>

    <?php if (!empty($_SESSION['selected_event_code']) && !empty($_SESSION['selected_event_name'])): ?>
      <div class="evento-selecionado-info">
        <strong>Evento selecionado:</strong>
        <?php echo htmlspecialchars($_SESSION['selected_event_name']); ?>
        (Cód: <?php echo htmlspecialchars($_SESSION['selected_event_code']); ?>)
        <a href="?trocar_evento=1" class="trocar-evento-btn">Trocar Evento</a>
      </div>
    <?php endif; ?>

    <h2>Validação de Ingresso</h2>

    <?php if (!empty($_SESSION['selected_event_code'])): ?>
      <form method="POST" action="">
        <label for="ticket_code">Digite o Ticket Code:</label>
        <input type="text" name="ticket_code" id="ticket_code" required autofocus>
        <button type="submit">
          <i class="fa-solid fa-check"></i> Validar
        </button>
      </form>

      <button class="button-default qr-btn" id="openQrBtn">
        <i class="fa-solid fa-camera"></i> Escanear QR Code
      </button>

      <!-- Botão: Listar Participantes -->
      <button class="button-default" id="listarParticipantesBtn" style="margin-top: 10px;">
        <i class="fa-solid fa-users"></i> Listar Participantes
      </button>
    <?php else: ?>
      <p style="color: red; font-weight: bold;">
        É necessário informar o <em>código do evento</em> antes de validar ingressos.
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: QR Code (se houver evento selecionado) -->
<?php if (!empty($_SESSION['selected_event_code'])): ?>
  <div id="qrModal">
    <div id="qrModalContent">
      <span id="qrModalClose">&times;</span>
      <h3>Escaneie o QR Code</h3>
      <div id="qr-reader"></div>
    </div>
  </div>
<?php endif; ?>

<!-- Modal: Definir Código do Evento -->
<?php $showEventCodeModal = empty($_SESSION['selected_event_code']); ?>
<div class="event-code-modal-overlay"
     id="eventCodeModalOverlay"
     style="display: <?php echo $showEventCodeModal ? 'flex' : 'none'; ?>;">
  <div class="event-code-modal">
    <h3>Informe o Código do Evento</h3>
    <form method="POST" action="">
      <input type="hidden" name="action" value="set_event_code">
      <label for="codigo_evento_modal">Código (4 dígitos):</label>
      <input type="number"
             name="codigo_evento_modal"
             id="codigo_evento_modal"
             required min="1000" max="9999"
             placeholder="Ex: 1234">
      <?php if (!empty($erroCodigoEvento)): ?>
        <p class="error"><?php echo $erroCodigoEvento; ?></p>
      <?php endif; ?>
      <button type="submit">
        <i class="fa-solid fa-check"></i> Confirmar
      </button>
    </form>
  </div>
</div>

<!-- Modal: Listar Participantes -->
<div class="listar-participantes-modal-overlay" id="listarParticipantesModalOverlay">
  <div class="listar-participantes-modal">
    <span class="close-modal" id="closeParticipantesModal">&times;</span>
    <h3>Listar Participantes</h3>
    <input type="text" id="participantSearch" class="search-bar" placeholder="Pesquisar participantes...">
    <div class="modal-content" id="participantContent">
      <?php
      if (!empty($_SESSION['selected_event_code'])) {
          $selectedEventCode = $_SESSION['selected_event_code'];
          $stmtList = $conn->prepare("
              SELECT p.id, p.nome, p.sobrenome, p.ticket_code, p.ingresso_validado
              FROM pedidos p
              INNER JOIN eventos e ON p.evento_id = e.id
              WHERE e.codigo_evento = ? AND p.status = 'approved'
              ORDER BY p.id ASC
          ");
          $stmtList->bind_param("i", $selectedEventCode);
          $stmtList->execute();
          $resultList = $stmtList->get_result();
          if ($resultList->num_rows > 0) {
             echo "<table id='participantTable'>";
             echo "<thead><tr><th>Nome Completo</th><th>Ticket Code</th><th>Ação</th></tr></thead>";
             echo "<tbody>";
             while ($row = $resultList->fetch_assoc()) {
               $nomeCompleto = $row['nome'] . " " . $row['sobrenome'];
               echo "<tr>";
               echo "<td>" . htmlspecialchars($nomeCompleto) . "</td>";
               echo "<td>" . htmlspecialchars($row['ticket_code'] ?? '') . "</td>";
               echo "<td style='text-align:center;'>";
               if ($row['ingresso_validado'] == 1) {
                 echo "<span style='color:green;'><i class='fa-solid fa-check'></i></span>";
               } else {
                 echo "<button onclick='confirmValidation(" . $row['id'] . ")' class='button-default' style='padding:4px 8px; font-size:0.9rem;'>";
                 echo "<i class='fa-solid fa-check'></i>";
                 echo "</button>";
               }
               echo "</td>";
               echo "</tr>";
             }
             echo "</tbody></table>";
          } else {
             echo "<p>Nenhum participante aprovado encontrado para este evento.</p>";
          }
          $stmtList->close();
      } else {
          echo "<p>Nenhum evento selecionado.</p>";
      }
      ?>
    </div>
  </div>
</div>

<!-- Exibe Flash Message (se houver) -->
<script>
(function(){
  let msg = "<?php echo $message; ?>";
  if (msg) {
    alert(msg);
  }
})();
</script>

<!-- Script do QR Code (se houver evento selecionado) -->
<?php if (!empty($_SESSION['selected_event_code'])): ?>
<script>
  const qrModal       = document.getElementById("qrModal");
  const openQrBtn     = document.getElementById("openQrBtn");
  const closeQr       = document.getElementById("qrModalClose");
  let html5QrcodeScanner;

  openQrBtn.addEventListener("click", function() {
    qrModal.style.display = "flex";
    startQrScanner();
  });

  closeQr.addEventListener("click", function() {
    qrModal.style.display = "none";
    stopQrScanner();
  });

  window.addEventListener("click", function(event) {
    if (event.target == qrModal) {
      qrModal.style.display = "none";
      stopQrScanner();
    }
  });

  function startQrScanner() {
    html5QrcodeScanner = new Html5Qrcode("qr-reader");
    const config = { fps: 10, qrbox: 250 };
    html5QrcodeScanner.start(
      { facingMode: "environment" },
      config,
      qrCodeMessage => {
        document.getElementById("ticket_code").value = qrCodeMessage;
        document.querySelector("form").submit();
        stopQrScanner();
        qrModal.style.display = "none";
      },
      errorMessage => {
        console.log("QR Code scan error:", errorMessage);
      }
    ).catch(err => {
      console.error("Erro ao iniciar o scanner:", err);
    });
  }

  function stopQrScanner() {
    if (html5QrcodeScanner) {
      html5QrcodeScanner.stop().then(() => {
        html5QrcodeScanner.clear();
      }).catch(err => {
        console.error("Erro ao parar o scanner:", err);
      });
    }
  }
</script>
<?php endif; ?>

<!-- Script para abrir/fechar modal de participantes e filtro -->
<script>
const listarParticipantesBtn          = document.getElementById("listarParticipantesBtn");
const listarParticipantesModalOverlay = document.getElementById("listarParticipantesModalOverlay");
const closeParticipantesModal         = document.getElementById("closeParticipantesModal");

if (listarParticipantesBtn) {
  listarParticipantesBtn.addEventListener("click", function() {
    listarParticipantesModalOverlay.style.display = "flex";
  });
}
if (closeParticipantesModal) {
  closeParticipantesModal.addEventListener("click", function() {
    listarParticipantesModalOverlay.style.display = "none";
  });
}
window.addEventListener("click", function(event) {
  if (event.target == listarParticipantesModalOverlay) {
    listarParticipantesModalOverlay.style.display = "none";
  }
});

function confirmValidation(id) {
  if (confirm("Deseja validar o ingresso deste participante?")) {
    window.location.href = "validar_ingresso.php?validate_id=" + id;
  }
}

const searchBar = document.getElementById("participantSearch");
if (searchBar) {
  searchBar.addEventListener("keyup", function() {
    const filter = this.value.toLowerCase();
    const rows   = document.querySelectorAll("#participantTable tbody tr");
    rows.forEach(function(row) {
      const text = row.textContent.toLowerCase();
      row.style.display = text.indexOf(filter) > -1 ? "" : "none";
    });
  });
}
</script>

<script>
if (<?php echo $showEventCodeModal ? 'true' : 'false'; ?>) {
  document.body.style.overflow = 'hidden';
}
</script>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('../service-worker.js')
      .then(function(registration) {
        console.log('Service Worker registrado:', registration.scope);
      })
      .catch(function(err) {
        console.log('Falha no registro do Service Worker:', err);
      });
  });
}
</script>
</body>
</html>
