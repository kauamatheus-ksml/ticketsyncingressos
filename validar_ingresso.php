<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'conexao.php'; // Ajuste se necessário

// ================================================================
// Processamento de validação de ingresso individual (via AJAX)
// ================================================================
if (isset($_POST['action']) && $_POST['action'] === 'validate_individual' && !empty($_SESSION['selected_event_code'])) {
    header('Content-Type: application/json');
    
    $ticket_code = trim($_POST['ticket_code'] ?? '');
    if (empty($ticket_code)) {
        echo json_encode(['success' => false, 'message' => 'Código de ingresso não fornecido']);
        exit;
    }
    
    // Verifica se é um código individual com formato baseTicket-index-timestamp
    $ingressoIndex = null;
    $baseTicket = null;
    if (strpos($ticket_code, '-') !== false) {
        $parts = explode('-', $ticket_code);
        if (count($parts) >= 2) {
            $baseTicket = $parts[0];
            $ingressoIndex = (int)$parts[1];
        }
    } else {
        $baseTicket = $ticket_code;
    }
    
    // Verifica se o ingresso existe e pertence ao evento selecionado
    $stmt = $conn->prepare("
        SELECT p.id, p.order_id, p.status, p.ingresso_validado, p.nome AS nome_comprador, 
               e.codigo_evento, p.ticket_code, p.itens_json
        FROM pedidos p
        INNER JOIN eventos e ON p.evento_id = e.id
        WHERE p.ticket_code = ? 
        LIMIT 1
    ");
    $stmt->bind_param("s", $baseTicket);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Ingresso não encontrado']);
        exit;
    }
    
    $pedido = $result->fetch_assoc();
    $stmt->close();
    
    // Verifica se o ingresso é do evento correto
    if ($pedido['codigo_evento'] != $_SESSION['selected_event_code']) {
        echo json_encode(['success' => false, 'message' => 'Este ingresso não pertence ao evento selecionado']);
        exit;
    }
    
    // Verifica se o pagamento está aprovado
    if ($pedido['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Este ingresso não está aprovado (status: ' . $pedido['status'] . ')']);
        exit;
    }
    
    // Verifica se já está completamente validado
    if ($pedido['ingresso_validado'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Este ingresso já foi completamente validado']);
        exit;
    }
    
    // Verifica a quantidade total de ingressos
    $totalIngressos = 0;
    if (!empty($pedido['itens_json'])) {
        $itens = json_decode($pedido['itens_json'], true);
        if (is_array($itens)) {
            foreach ($itens as $item) {
                $totalIngressos += intval($item['quantidade'] ?? 0);
            }
        }
    }
    
    // Se não tiver índice, considera como validação completa (compatibilidade)
    if ($ingressoIndex === null || $ingressoIndex <= 0 || $ingressoIndex > $totalIngressos) {
        // Marcando todos os ingressos como validados
        $stmtUpdate = $conn->prepare("UPDATE pedidos SET ingresso_validado = 1 WHERE id = ?");
        $stmtUpdate->bind_param("i", $pedido['id']);
        if ($stmtUpdate->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Todos os ingressos de ' . $pedido['nome_comprador'] . ' foram validados',
                'total_validados' => $totalIngressos,
                'nome_cliente' => $pedido['nome_comprador']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao validar o ingresso']);
        }
        $stmtUpdate->close();
        exit;
    }
    
    // Verifica se este ingresso específico já foi validado
    $stmtCheckValidado = $conn->prepare("
        SELECT id FROM validacoes_individuais 
        WHERE order_id = ? AND ingresso_index = ?
    ");
    $stmtCheckValidado->bind_param("si", $pedido['order_id'], $ingressoIndex);
    $stmtCheckValidado->execute();
    $resultCheckValidado = $stmtCheckValidado->get_result();
    
    if ($resultCheckValidado->num_rows > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Este ingresso específico já foi validado',
            'nome_cliente' => $pedido['nome_comprador']
        ]);
        $stmtCheckValidado->close();
        exit;
    }
    $stmtCheckValidado->close();
    
    // Registra a validação individual
    $stmtInsert = $conn->prepare("
        INSERT INTO validacoes_individuais (order_id, ingresso_index, validado_em, validado_por)
        VALUES (?, ?, NOW(), ?)
    ");
    $validadoPor = isset($_SESSION['adminid']) ? $_SESSION['adminid'] : 
                  (isset($_SESSION['funcionarioid']) ? $_SESSION['funcionarioid'] : 0);
    $stmtInsert->bind_param("sii", $pedido['order_id'], $ingressoIndex, $validadoPor);
    
    if ($stmtInsert->execute()) {
        // Conta quantos ingressos já foram validados
        $stmtCount = $conn->prepare("
            SELECT COUNT(*) as total FROM validacoes_individuais 
            WHERE order_id = ?
        ");
        $stmtCount->bind_param("s", $pedido['order_id']);
        $stmtCount->execute();
        $resultCount = $stmtCount->get_result();
        $rowCount = $resultCount->fetch_assoc();
        $totalValidados = $rowCount['total'] ?? 0;
        $stmtCount->close();
        
        // Se todos os ingressos foram validados, atualiza o status geral
        if ($totalValidados >= $totalIngressos) {
            $stmtUpdateCompleto = $conn->prepare("UPDATE pedidos SET ingresso_validado = 1 WHERE id = ?");
            $stmtUpdateCompleto->bind_param("i", $pedido['id']);
            $stmtUpdateCompleto->execute();
            $stmtUpdateCompleto->close();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Ingresso #' . $ingressoIndex . ' de ' . $pedido['nome_comprador'] . ' validado com sucesso',
            'validados' => $totalValidados,
            'total' => $totalIngressos,
            'todos_validados' => ($totalValidados >= $totalIngressos),
            'nome_cliente' => $pedido['nome_comprador'],
            'ingresso_index' => $ingressoIndex
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erro ao registrar validação: ' . $stmtInsert->error
        ]);
    }
    $stmtInsert->close();
    exit;
}

// ==========================================================
// 1) Processa validação via GET (quando clica no check)
// ==========================================================
if (isset($_GET['validate_id']) && !empty($_SESSION['selected_event_code'])) {
    $pedidoId = intval($_GET['validate_id']);
    $stmtValidate = $conn->prepare("
        SELECT p.id, p.status, p.ingresso_validado, e.codigo_evento, p.order_id, p.itens_json
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
                // Valida o ingresso - modo completo
                $stmtUpdate = $conn->prepare("UPDATE pedidos SET ingresso_validado = 1 WHERE id = ?");
                $stmtUpdate->bind_param("i", $pedidoId);
                if ($stmtUpdate->execute()) {
                    // Também registra validações individuais para cada ingresso
                    $validadoPor = isset($_SESSION['adminid']) ? $_SESSION['adminid'] : 
                                 (isset($_SESSION['funcionarioid']) ? $_SESSION['funcionarioid'] : 0);
                    
                    // Calcula a quantidade total de ingressos
                    $totalIngressos = 0;
                    if (!empty($rowValidate['itens_json'])) {
                        $itens = json_decode($rowValidate['itens_json'], true);
                        if (is_array($itens)) {
                            foreach ($itens as $item) {
                                $totalIngressos += intval($item['quantidade'] ?? 0);
                            }
                        }
                    }
                    
                    // Limpa validações anteriores
                    $stmtDelete = $conn->prepare("DELETE FROM validacoes_individuais WHERE order_id = ?");
                    $stmtDelete->bind_param("s", $rowValidate['order_id']);
                    $stmtDelete->execute();
                    $stmtDelete->close();
                    
                    // Insere validações para cada ingresso
                    $stmtInsertIndiv = $conn->prepare("
                        INSERT INTO validacoes_individuais (order_id, ingresso_index, validado_em, validado_por)
                        VALUES (?, ?, NOW(), ?)
                    ");
                    
                    for ($i = 1; $i <= $totalIngressos; $i++) {
                        $stmtInsertIndiv->bind_param("sii", $rowValidate['order_id'], $i, $validadoPor);
                        $stmtInsertIndiv->execute();
                    }
                    $stmtInsertIndiv->close();
                    
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
    header("Location: validar_ingresso.php");
    exit;
}

// ==========================================================
// 2) Verifica se o usuário está logado
// ==========================================================
$isPromotor = false;

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
} elseif (isset($_SESSION['userid'])) {
    $userId = $_SESSION['userid'];
    $sql = "SELECT nome FROM clientes WHERE id = $userId";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $_SESSION['nome'] = $row['nome'];
    }
} else {
    // Não logado
    header("Location: login.php");
    exit;
}

// ==========================================================
// 3) Lógica do "Trocar Evento"
// ==========================================================
if (isset($_GET['trocar_evento']) && $_GET['trocar_evento'] == 1) {
    unset($_SESSION['selected_event_code'], $_SESSION['selected_event_timestamp'], $_SESSION['selected_event_name']);
    header("Location: validar_ingresso.php");
    exit;
}

// ==========================================================
// 4) Verificação de expiração do código do evento (24h = 86400 seg)
// ==========================================================
if (
    isset($_SESSION['selected_event_code']) &&
    isset($_SESSION['selected_event_timestamp']) &&
    (time() - $_SESSION['selected_event_timestamp'] >= 86400)
) {
    // Expirou
    unset($_SESSION['selected_event_code'], $_SESSION['selected_event_timestamp'], $_SESSION['selected_event_name']);
}

// ==========================================================
// 5) Se não houver código de evento na sessão, processa POST do modal
// ==========================================================
$erroCodigoEvento = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_event_code') {
    $inputCodigoEvento = trim($_POST['codigo_evento_modal']);
    // Verifica se esse código existe na tabela de eventos (e se está aprovado)
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
        // Exigir status 'aprovado' ou 'approved'
        if (strtolower($rowE['status']) !== 'aprovado' && strtolower($rowE['status']) !== 'approved') {
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
        header("Location: validar_ingresso.php");
        exit;
    }
}

// ==========================================================
// 6) Processa POST para validar ingresso (ticket_code)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_code']) && !empty($_SESSION['selected_event_code'])) {
    $submittedTicketCode = trim($_POST['ticket_code']);
    
    // Extraímos as partes do código (formato: baseTicket-index-timestamp)
    $parts = explode('-', $submittedTicketCode);
    $ticketCode = $parts[0]; // Base ticket é sempre a primeira parte
    $ingressoIndex = (count($parts) > 1) ? (int)$parts[1] : null; // Índice é segunda parte, se houver
    
    $message = "";
    $messageType = "";

    // Busca o pedido pelo código base
    $stmt = $conn->prepare("
        SELECT p.id, p.order_id, p.status, p.ingresso_validado, p.nome AS nome_comprador, 
               e.codigo_evento, p.itens_json
        FROM pedidos p
        INNER JOIN eventos e ON p.evento_id = e.id
        WHERE p.ticket_code = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $ticketCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();

        $nomeClienteValido = $row['nome_comprador'];
        $orderId = $row['order_id'];
        
        // Verifica se o ingresso pertence ao evento correto
        if ($row['codigo_evento'] != $_SESSION['selected_event_code']) {
            $message = "O ingresso a ser validado é de outro evento.";
            $messageType = "error";
        } else if ($row['status'] !== 'approved') {
            $message = "Este ingresso não está aprovado (status: {$row['status']}).";
            $messageType = "error";
        } else {
            // Verifica a quantidade total de ingressos no pedido
            $totalIngressos = 0;
            if (!empty($row['itens_json'])) {
                $itens = json_decode($row['itens_json'], true);
                if (is_array($itens)) {
                    foreach ($itens as $item) {
                        $totalIngressos += intval($item['quantidade'] ?? 0);
                    }
                }
            }
            
            // Conta ingressos já validados
            $stmtValidados = $conn->prepare("
                SELECT COUNT(*) as total FROM validacoes_individuais 
                WHERE order_id = ?
            ");
            $stmtValidados->bind_param("s", $orderId);
            $stmtValidados->execute();
            $resultValidados = $stmtValidados->get_result();
            $rowValidados = $resultValidados->fetch_assoc();
            $totalValidados = $rowValidados['total'] ?? 0;
            $stmtValidados->close();
            
            // Caso 1: Já está totalmente validado
            if ($row['ingresso_validado'] == 1) {
                $message = "({$nomeClienteValido}) já validou todos os ingressos deste pedido.";
                $messageType = "warning";
            }
            // Caso 2: Código sem índice ou índice inválido - valida todo o pedido
            else if ($ingressoIndex === null || $ingressoIndex <= 0 || $ingressoIndex > $totalIngressos) {
                $stmtUpdate = $conn->prepare("UPDATE pedidos SET ingresso_validado = 1 WHERE id = ?");
                $stmtUpdate->bind_param("i", $row['id']);
                if ($stmtUpdate->execute()) {
                    // Registra validações individuais para cada ingresso
                    $validadoPor = isset($_SESSION['adminid']) ? $_SESSION['adminid'] : 
                                 (isset($_SESSION['funcionarioid']) ? $_SESSION['funcionarioid'] : 0);
                    
                    // Limpa validações existentes para evitar duplicidade
                    $stmtClear = $conn->prepare("DELETE FROM validacoes_individuais WHERE order_id = ?");
                    $stmtClear->bind_param("s", $orderId);
                    $stmtClear->execute();
                    $stmtClear->close();
                    
                    // Insere uma validação para cada ingresso
                    $stmtInsert = $conn->prepare("
                        INSERT INTO validacoes_individuais (order_id, ingresso_index, validado_em, validado_por)
                        VALUES (?, ?, NOW(), ?)
                    ");
                    
                    for ($i = 1; $i <= $totalIngressos; $i++) {
                        $stmtInsert->bind_param("sii", $orderId, $i, $validadoPor);
                        $stmtInsert->execute();
                    }
                    $stmtInsert->close();
                    
                    $message = "Bom evento, {$nomeClienteValido}! Todos os ingressos validados.";
                    $messageType = "success";
                    $_SESSION['flash_nomeCliente'] = $nomeClienteValido;
                } else {
                    $message = "Erro ao validar o ingresso.";
                    $messageType = "error";
                }
                $stmtUpdate->close();
            }
            // Caso 3: Código com índice específico - valida apenas um ingresso
            else {
                // Verifica se este ingresso específico já foi validado
                $stmtCheckInd = $conn->prepare("
                    SELECT id FROM validacoes_individuais 
                    WHERE order_id = ? AND ingresso_index = ?
                ");
                $stmtCheckInd->bind_param("si", $orderId, $ingressoIndex);
                $stmtCheckInd->execute();
                $resultCheckInd = $stmtCheckInd->get_result();
                
                if ($resultCheckInd->num_rows > 0) {
                    $message = "O ingresso #{$ingressoIndex} de {$nomeClienteValido} já foi validado.";
                    $messageType = "warning";
                } else {
                    // Registra a validação individual
                    $validadoPor = isset($_SESSION['adminid']) ? $_SESSION['adminid'] : 
                                 (isset($_SESSION['funcionarioid']) ? $_SESSION['funcionarioid'] : 0);
                    
                    $stmtInsertInd = $conn->prepare("
                        INSERT INTO validacoes_individuais (order_id, ingresso_index, validado_em, validado_por)
                        VALUES (?, ?, NOW(), ?)
                    ");
                    $stmtInsertInd->bind_param("sii", $orderId, $ingressoIndex, $validadoPor);
                    
                    if ($stmtInsertInd->execute()) {
                        // Verifica se todos foram validados
                        $novoTotal = $totalValidados + 1;
                        if ($novoTotal >= $totalIngressos) {
                            // Marca o pedido todo como validado
                            $stmtUpdateComplete = $conn->prepare("UPDATE pedidos SET ingresso_validado = 1 WHERE id = ?");
                            $stmtUpdateComplete->bind_param("i", $row['id']);
                            $stmtUpdateComplete->execute();
                            $stmtUpdateComplete->close();
                            
                            $message = "Bom evento, {$nomeClienteValido}! Ingresso #{$ingressoIndex} validado. Todos os ingressos agora estão validados.";
                        } else {
                            $message = "Bom evento, {$nomeClienteValido}! Ingresso #{$ingressoIndex} validado ({$novoTotal} de {$totalIngressos}).";
                        }
                        $messageType = "success";
                        $_SESSION['flash_nomeCliente'] = $nomeClienteValido;
                    } else {
                        $message = "Erro ao validar o ingresso #{$ingressoIndex}.";
                        $messageType = "error";
                    }
                    $stmtInsertInd->close();
                }
                $stmtCheckInd->close();
            }
        }
    } else {
        $message = "Ingresso não encontrado ou código inválido.";
        $messageType = "error";
    }
    
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;
    header("Location: validar_ingresso.php");
    exit;
}

// ==========================================================
// 7) Mensagens de retorno (flash)
// ==========================================================
$message      = "";
$messageType  = "";
$flashNomeCliente = "";

if (isset($_SESSION['flash_message'])) {
    $message     = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    if (isset($_SESSION['flash_nomeCliente'])) {
        $flashNomeCliente = $_SESSION['flash_nomeCliente'];
        unset($_SESSION['flash_nomeCliente']);
    }
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Validar Ingresso - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#000000">

  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <!-- Biblioteca html5-qrcode -->
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

  <!-- CSS: completamente refeito para um layout moderno e responsivo -->
  <link rel="stylesheet" href="css/validar_ingresso.css">
</head>
<body>

<?php if ($isPromotor): ?>
  <?php include('header_admin.php'); ?>
<?php else: ?>
  <!-- Cabeçalho (navbar) para clientes ou funcionários -->
  <header>
    <div class="logo">
      <a href="<?php 
          if (isset($_SESSION['adminid'])) {
              echo 'admin.php';
          } elseif (isset($_SESSION['funcionarioid'])) {
              echo 'validar_ingresso.php';
          } else {
              echo 'index.php';
          }
      ?>">
        <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
      </a>
    </div>
    <nav>
      <?php if (isset($_SESSION['adminid']) || isset($_SESSION['funcionarioid'])): ?>
        <div class="user-container">
          <?php if (!isset($_SESSION['funcionarioid'])): ?>
            <a href="meus_ingressos.php" class="meus-ingressos-btn">
              <i class="fa-solid fa-ticket"></i> <span class="btn-text">Meus Ingressos</span>
            </a>
          <?php endif; ?>
          <a href="meu_perfil.php" class="user-btn">
            <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['nome']); ?>
          </a>
          <div class="dropdown">
            <span class="dropdown-btn"><i class="fa-solid fa-caret-down"></i></span>
            <div class="dropdown-content">
              <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="login-btn">
          <i class="fa-solid fa-right-to-bracket"></i> Entrar
        </a>
      <?php endif; ?>
    </nav>
  </header>
<?php endif; ?>

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
      <form method="POST" action="" id="validateForm">
        <label for="ticket_code">Digite o Ticket Code:</label>
        <input type="text" name="ticket_code" id="ticket_code" required autofocus>
        <button type="submit">Validar</button>
      </form>

      <button class="button-default qr-btn" id="openQrBtn">
        <i class="fa-solid fa-camera"></i> Escanear QR Code
      </button>

      <!-- Botão: Listar Participantes -->
      <button class="button-default" id="listarParticipantesBtn" style="margin-top: 10px;">
        <i class="fa-solid fa-users"></i> Listar Participantes
      </button>

      <!-- Área de status da validação -->
      <div id="validationResult" class="validation-result" style="display: none;">
        <div class="validation-icon"></div>
        <div class="validation-message"></div>
      </div>
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

<!-- Modal: Código do Evento (abre se não houver evento selecionado) -->
<?php $showEventCodeModal = empty($_SESSION['selected_event_code']); ?>
<div class="event-code-modal-overlay"
     id="eventCodeModalOverlay"
     style="display: <?php echo $showEventCodeModal ? 'flex' : 'none'; ?>;">
  <div class="event-code-modal">
    <h3>Informe o Código do Evento</h3>
    <form method="POST" action="">
      <input type="hidden" name="action" value="set_event_code">
      <label for="codigo_evento_modal">Código (4 dígitos):</label>
      <input type="number" name="codigo_evento_modal"
             id="codigo_evento_modal"
             required min="1000" max="9999"
             placeholder="Ex: 1234">
      <?php if (!empty($erroCodigoEvento)): ?>
        <p class="error"><?php echo $erroCodigoEvento; ?></p>
      <?php endif; ?>
      <button type="submit">Confirmar</button>
    </form>
  </div>
</div>

<!-- Modal: Listar Participantes -->
<div class="listar-participantes-modal-overlay" id="listarParticipantesModalOverlay">
  <div class="listar-participantes-modal">
    <span class="close-modal" id="closeParticipantesModal">&times;</span>
    <h3>Listar Participantes</h3>
    <!-- Barra de pesquisa -->
    <input type="text" id="participantSearch" class="search-bar" placeholder="Pesquisar participantes...">
    <div class="modal-content" id="participantContent">
      <?php
      if (!empty($_SESSION['selected_event_code'])) {
          $selectedEventCode = $_SESSION['selected_event_code'];
          $stmtList = $conn->prepare("
              SELECT 
                  p.id, p.nome, p.sobrenome, p.ticket_code, p.ingresso_validado, p.order_id, 
                  p.itens_json
              FROM pedidos p
              INNER JOIN eventos e ON p.evento_id = e.id
              WHERE e.codigo_evento = ? AND p.status = 'approved'
              ORDER BY p.nome, p.sobrenome
          ");
          $stmtList->bind_param("i", $selectedEventCode);
          $stmtList->execute();
          $resultList = $stmtList->get_result();
          if ($resultList->num_rows > 0) {
             echo "<table id='participantTable'>";
             echo "<thead><tr><th>Nome Completo</th><th>Ticket Code</th><th>Status</th><th>Ação</th></tr></thead>";
             echo "<tbody>";
             while($row = $resultList->fetch_assoc()) {
               $nomeCompleto = $row['nome'] . " " . $row['sobrenome'];
               $orderId = $row['order_id'];
               
               // Calcula total de ingressos e validados
               $totalIngressos = 0;
               if (!empty($row['itens_json'])) {
                   $itens = json_decode($row['itens_json'], true);
                   if (is_array($itens)) {
                       foreach ($itens as $item) {
                           $totalIngressos += intval($item['quantidade'] ?? 0);
                       }
                   }
               }
               
               // Busca quantos já foram validados
               $validados = 0;
               $stmtValidados = $conn->prepare("
                   SELECT COUNT(*) as total FROM validacoes_individuais 
                   WHERE order_id = ?
               ");
               $stmtValidados->bind_param("s", $orderId);
               $stmtValidados->execute();
               $resultValidados = $stmtValidados->get_result();
               if ($resultValidados->num_rows > 0) {
                   $rowVal = $resultValidados->fetch_assoc();
                   $validados = $rowVal['total'];
               }
               $stmtValidados->close();
               
               $statusText = ($validados == 0) ? "Não validado" : 
                            (($validados < $totalIngressos) ? "Parcial ($validados/$totalIngressos)" : "Completo");
               
               echo "<tr>";
               echo "<td>" . htmlspecialchars($nomeCompleto) . "</td>";
               echo "<td>" . htmlspecialchars($row['ticket_code'] ?? '') . "</td>";
               echo "<td>";
               if ($validados == 0) {
                   echo "<span style='color:red;'>Não validado</span>";
               } else if ($validados < $totalIngressos) {
                   echo "<span style='color:orange;'>Parcial ($validados/$totalIngressos)</span>";
               } else {
                   echo "<span style='color:green;'>Completo</span>";
               }
               echo "</td>";
               echo "<td style='text-align:center;'>";
               if ($row['ingresso_validado'] == 1 || $validados >= $totalIngressos) {
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

<!-- Toast para notificações -->
<div id="toast" class="toast-notification" style="display: none;">
  <div class="toast-icon"></div>
  <div class="toast-message"></div>
</div>

<!-- Exibe Flash Message -->
<script>
  (function() {
    let msg = "<?php echo $message; ?>";
    let type = "<?php echo $messageType; ?>";
    if (msg) {
      showToast(msg, type);
    }
  })();
  
  function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastIcon = document.querySelector('.toast-icon');
    const toastMessage = document.querySelector('.toast-message');
    
    if (!toast) return;
    
    // Define ícone e cores baseado no tipo
    let icon = '';
    switch(type) {
      case 'success':
        icon = '<i class="fa-solid fa-check-circle"></i>';
        toast.className = 'toast-notification success';
        break;
      case 'error':
        icon = '<i class="fa-solid fa-times-circle"></i>';
        toast.className = 'toast-notification error';
        break;
      case 'warning':
        icon = '<i class="fa-solid fa-exclamation-triangle"></i>';
        toast.className = 'toast-notification warning';
        break;
      default:
        icon = '<i class="fa-solid fa-info-circle"></i>';
        toast.className = 'toast-notification info';
    }
    
    toastIcon.innerHTML = icon;
    toastMessage.textContent = message;
    
    toast.style.display = 'flex';
    
    // Auto-ocultar após 5 segundos
    setTimeout(() => {
      toast.classList.add('toast-fade-out');
      setTimeout(() => {
        toast.style.display = 'none';
        toast.classList.remove('toast-fade-out');
      }, 500);
    }, 5000);
  }
</script>

<!-- Script do QR Code -->
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
        validateTicketAjax(qrCodeMessage);
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

<!-- Script para abrir/fechar modal de participantes e para filtrar -->
<script>
  const listarParticipantesBtn            = document.getElementById("listarParticipantesBtn");
  const listarParticipantesModalOverlay   = document.getElementById("listarParticipantesModalOverlay");
  const closeParticipantesModal           = document.getElementById("closeParticipantesModal");

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
    if (confirm("Deseja validar todos os ingressos deste participante?")) {
      window.location.href = "validar_ingresso.php?validate_id=" + id;
    }
  }

  // Filtro da tabela
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

<!-- Se o modal do código do evento estiver aberto, trava a rolagem -->
<script>
  if (<?php echo $showEventCodeModal ? 'true' : 'false'; ?>) {
    document.body.style.overflow = 'hidden';
  }
</script>

<!-- Registro do Service Worker -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('service-worker.js')
        .then(function(reg) {
          console.log('Service Worker registrado:', reg.scope);
        })
        .catch(function(err) {
          console.log('Falha no registro do Service Worker:', err);
        });
    });
  }
</script>

<!-- Detecta se a página está em modo standalone (PWA) e desabilita o link da logo -->
<script>
  function isPWA() {
    return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
           || window.navigator.standalone;
  }
  if (isPWA()) {
    const logoLink = document.querySelector('.logo a');
    if (logoLink) {
      logoLink.removeAttribute('href');
      logoLink.style.pointerEvents = 'none';
      logoLink.style.cursor = 'default';
    }
  }
</script>

<!-- Script para validação via AJAX -->
<script>
  // Função para validar ingresso via AJAX
  function validateTicketAjax(ticketCode) {
    // Desabilita o campo e botão durante a validação
    const ticketInput = document.getElementById('ticket_code');
    const submitButton = document.querySelector('form button[type="submit"]');
    
    if (ticketInput) ticketInput.disabled = true;
    if (submitButton) submitButton.disabled = true;
    
    // Cria e envia a requisição AJAX
    const formData = new FormData();
    formData.append('action', 'validate_individual');
    formData.append('ticket_code', ticketCode);
    
    fetch('validar_ingresso.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      // Exibe o resultado via toast
      if (data.success) {
        showToast(data.message, 'success');
        
        // Se a opção de listar participantes estiver aberta, atualiza a tabela
        updateParticipantTable(data);
      } else {
        showToast(data.message, 'error');
      }
    })
    .catch(error => {
      console.error('Erro na validação:', error);
      showToast('Erro ao processar a validação. Tente novamente.', 'error');
    })
    .finally(() => {
      // Reativa o campo e botão
      if (ticketInput) {
        ticketInput.disabled = false;
        ticketInput.value = '';
        ticketInput.focus();
      }
      if (submitButton) submitButton.disabled = false;
    });
  }

  // Versão melhorada da função updateParticipantTable
  function updateParticipantTable(data) {
      const participantTable = document.getElementById('participantTable');
      if (!participantTable) return;
      
      // Busca todas as linhas da tabela
      const rows = participantTable.querySelectorAll('tbody tr');
      
      rows.forEach(row => {
          if (!row.cells || row.cells.length < 4) return; // Verifica se há células suficientes
          
          const nameCell = row.cells[0];
          const statusCell = row.cells[2];
          const actionCell = row.cells[3];
          
          // Se o nome do cliente estiver contido na célula de nome
          if (nameCell && nameCell.textContent && 
              data.nome_cliente && nameCell.textContent.includes(data.nome_cliente)) {
              
              // Atualiza o status conforme a validação
              if (statusCell) {
                  if (data.todos_validados) {
                      statusCell.innerHTML = "<span style='color:green;'>Completo</span>";
                  } else {
                      statusCell.innerHTML = `<span style='color:orange;'>Parcial (${data.validados}/${data.total})</span>`;
                  }
              }
              
              // Se todos validados, atualiza a ação também
              if (actionCell && data.todos_validados) {
                  actionCell.innerHTML = '<span style="color:green;"><i class="fa-solid fa-check"></i></span>';
              }
          }
      });
  }

  // Intercepta o formulário para validar via AJAX
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('validateForm');
    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const ticketCode = document.getElementById('ticket_code').value.trim();
        if (ticketCode) {
          validateTicketAjax(ticketCode);
        }
      });
    }
  });
</script>

</body>
</html>