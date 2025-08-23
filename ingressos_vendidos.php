<?php
// ingressos_vendidos.php
session_start();

if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

include_once('check_permissions.php');
if (!checkPermission("ingressos_vendidos")) {
    echo "Você não tem permissão para acessar esta página.";
    exit();
}

include('conexao.php');

// Verifica se foi solicitada a ação de download do PDF
if (isset($_GET['action']) && $_GET['action'] === 'download_pdf' && isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // Consulta o PDF armazenado no banco de dados
    $stmtDownload = $conn->prepare("SELECT pdf FROM pedidos WHERE order_id = ? AND pdf IS NOT NULL");
    $stmtDownload->bind_param("s", $order_id);
    $stmtDownload->execute();
    $stmtDownload->store_result();
    
    if ($stmtDownload->num_rows > 0) {
        $stmtDownload->bind_result($pdfData);
        $stmtDownload->fetch();
        // Define os cabeçalhos para forçar o download do PDF
        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=ingresso_{$order_id}.pdf");
        header("Content-Length: " . strlen($pdfData));
        echo $pdfData;
        $stmtDownload->close();
        $conn->close();
        exit();
    } else {
        echo "Ingresso não encontrado ou indisponível para download.";
        $stmtDownload->close();
        $conn->close();
        exit();
    }
}

$promotor_id = $_SESSION['adminid'];
$evento_id = filter_input(INPUT_GET, 'evento_id', FILTER_VALIDATE_INT);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Ingressos Vendidos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="css/ingressos_vendidos.css">
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <style>
    /* Estilo moderno para a área de seleção de evento */
    .filtro-container {
      margin-bottom: 20px;
      padding: 15px;
      background: #fff;
      border: 1px solid #eaeaea;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .filtro-container label {
      font-size: 16px;
      color: #555;
      margin-bottom: 8px;
      display: block;
    }
    .filtro-container select {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: #f9f9f9;
      font-size: 16px;
      color: #333;
      transition: all 0.3s ease;
    }
    .filtro-container select:focus {
      outline: none;
      border-color: #002f6d;
      box-shadow: 0 0 5px rgba(52,152,219,0.5);
    }
    /* Botões */
    .imprimir-lista-btn {
      padding: 8px 12px;
      font-size: 0.9em;
      background-color: #002f6d;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-left: 15px;
    }
    .imprimir-lista-btn:hover {
      opacity: 0.9;
    }
    .toggle-columns-btn {
      padding: 8px 12px;
      font-size: 0.9em;
      background-color: #e74c3c; /* Vermelho */
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      margin-left: 10px;
    }
    .toggle-columns-btn:hover {
      opacity: 0.9;
    }
    /* Modal Genérico */
    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    .modal-content {
      background-color: #fff;
      margin: 10% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 600px;
      border-radius: 8px;
    }
    .modal-content h2 {
      margin-top: 0;
    }
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }
    .close:hover,
    .close:focus {
      color: black;
      text-decoration: none;
    }
    /* Lista simples para checkboxes */
    .checkbox-list {
      margin-bottom: 10px;
    }
    .checkbox-list label {
      display: block;
      margin: 8px 0;
      font-weight: 500;
      cursor: pointer;
    }
    .checkbox-list input[type="checkbox"] {
      appearance: none;
      -webkit-appearance: none;
      background-color: #fff;
      border: 2px solid #002f6d;
      border-radius: 4px;
      width: 20px;
      height: 20px;
      vertical-align: middle;
      margin-right: 10px;
      cursor: pointer;
      position: relative;
    }
    .checkbox-list input[type="checkbox"]:checked {
      background-color: #002f6d;
    }
    .checkbox-list input[type="checkbox"]:checked::after {
      content: "\2713";
      color: #fff;
      font-size: 16px;
      position: absolute;
      top: -2px;
      left: 4px;
    }
    /* Subcaixas para Status com indentação */
    .sub-options {
      margin-left: 20px;
      display: none;
    }
    .sub-options label {
      display: block;
      margin: 5px 0;
      font-weight: 500;
      cursor: pointer;
    }
    /* Diferenciação de cores para cada status */
    .status-option.status-approved {
      color: #27ae60;
    }
    .status-option.status-pending {
      color: #f39c12;
    }
    .status-option.status-paid {
      color: #2980b9;
    }
    .status-option.status-cancelled {
      color: #e74c3c;
    }
    /* Tabela de pedidos */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    table th, table td {
      padding: 8px;
      text-align: left;
      border: none;
    }
    table th {
      font-weight: bold;
    }
    tbody tr {
      border-bottom: 1px solid rgba(0,0,0,0.1);
    }
    /* Classes para colunas a serem controladas */
    .col-order_id {}
    .col-nome_completo {}
    .col-email {}
    .col-valor {}
    .col-status {}
    .col-created_at {}
    .col-ingresso {}
  </style>
</head>
<body>
  <?php include('header_admin.php'); ?>
  <div class="container">
    <h1><i class="fas fa-ticket-alt"></i> Ingressos Vendidos</h1>
    
    <div class="filtro-container">
      <form method="get" action="ingressos_vendidos.php" id="eventoForm">
        <label for="evento">Selecione um Evento:</label>
        <select name="evento_id" id="evento" required onchange="this.form.submit();">
          <option value="">-- Selecione --</option>
          <?php
          // Ajustado aqui para usar data_inicio:
          $stmtEventos = $conn->prepare("
              SELECT id, nome, data_inicio, local
                FROM eventos
               WHERE promotor_id = ?
               ORDER BY data_inicio ASC
          ");
          $stmtEventos->bind_param("i", $promotor_id);
          $stmtEventos->execute();
          $resultEventos = $stmtEventos->get_result();
          while ($evento = $resultEventos->fetch_assoc()):
              $dataFormatada = date("d/m/Y", strtotime($evento['data_inicio']));
              $selected = ($evento_id == $evento['id']) ? "selected" : "";
          ?>
            <option value="<?= $evento['id'] ?>" <?= $selected ?>>
              <?= htmlspecialchars($evento['nome']) . " - " . $dataFormatada . " - " . htmlspecialchars($evento['local']) ?>
            </option>
          <?php 
          endwhile;
          $stmtEventos->close();
          ?>
        </select>
      </form>
    </div>
    
    <?php if ($evento_id): ?>
      <div class="tabela-container">
        <?php
        $stmtCheck = $conn->prepare("SELECT id, nome FROM eventos WHERE id = ? AND promotor_id = ?");
        $stmtCheck->bind_param("ii", $evento_id, $promotor_id);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        
        if ($checkResult->num_rows === 0) {
          echo "<p class='nenhum-resultado'>Evento inválido ou não pertence a você.</p>";
        } else {
          $eventoInfo = $checkResult->fetch_assoc();
          echo "<h2>Pedidos para: " . htmlspecialchars($eventoInfo['nome']) . " 
                <button class='imprimir-lista-btn' id='openModalBtn'><i class='fas fa-print'></i> IMPRIMIR LISTA</button> 
                <button class='toggle-columns-btn' id='toggleColumnsBtn'><i class='fas fa-eye'></i></button></h2>";
          
          // Consulta os pedidos, combinando "nome" e "sobrenome" em "nome_completo"
          $stmtPedidos = $conn->prepare("
              SELECT order_id,
                     CONCAT(nome, ' ', sobrenome) AS nome_completo,
                     email,
                     valor_total,
                     status,
                     created_at
                FROM pedidos
               WHERE evento_id = ?
          ");
          $stmtPedidos->bind_param("i", $evento_id);
          $stmtPedidos->execute();
          $resultPedidos = $stmtPedidos->get_result();
          
          if ($resultPedidos->num_rows > 0) {
            echo "<table>";
            echo "<tr>
                    <th class='col-order_id'>Order ID</th>
                    <th class='col-nome_completo'>Nome Completo</th>
                    <th class='col-email'>Email</th>
                    <th class='col-valor'>Valor</th>
                    <th class='col-status'>Status</th>
                    <th class='col-created_at'>Data</th>
                    <th class='col-ingresso'>Ingresso</th>
                  </tr>";
            while ($pedido = $resultPedidos->fetch_assoc()) {
              echo "<tr>";
              echo "<td class='col-order_id'>" . htmlspecialchars($pedido['order_id']) . "</td>";
              echo "<td class='col-nome_completo'>" . htmlspecialchars($pedido['nome_completo']) . "</td>";
              echo "<td class='col-email'>" . htmlspecialchars($pedido['email']) . "</td>";
              echo "<td class='col-valor'>R$ " . number_format($pedido['valor_total'], 2, ',', '.') . "</td>";
              echo "<td class='col-status'><span class='status' data-status='" . strtolower($pedido['status']) . "'>" . htmlspecialchars($pedido['status']) . "</span></td>";
              // Ajustar para seu timezone. Exemplo: -3 horas
              echo "<td class='col-created_at'>" . date("d/m/Y H:i", strtotime($pedido['created_at']) - 10800) . "</td>";
              
              if (strtolower($pedido['status']) == 'approved') {
                // Link de download do PDF do ingresso (PDF armazenado na coluna pdf)
                echo "<td class='col-ingresso'>
                        <a class='action-btn promotor-resent-button' href='ingressos_vendidos.php?action=download_pdf&order_id=" . urlencode($pedido['order_id']) . "' target='_blank'>
                          <i class='fas fa-file-pdf'></i> Baixar Ingresso
                        </a>
                      </td>";
              } else {
                echo "<td class='col-ingresso'></td>";
              }
              
              echo "</tr>";
            }
            echo "</table>";
          } else {
            echo "<p class='nenhum-resultado'>Nenhum pedido encontrado para este evento.</p>";
          }
          $stmtPedidos->close();
        }
        $stmtCheck->close();
        ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Modal para seleção de colunas para impressão -->
  <div id="printModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Selecione as colunas para imprimir</h2>
      <form id="printForm" action="gerar_pdf.php" method="POST">
        <input type="hidden" name="evento_id" value="<?php echo $evento_id; ?>">
        <div class="checkbox-list">
          <label>
            <input type="checkbox" name="columns[]" value="order_id"> Order ID
          </label>
          <label>
            <input type="checkbox" name="columns[]" value="nome_completo" checked disabled> Nome Completo
            <input type="hidden" name="columns[]" value="nome_completo">
          </label>
          <label>
            <input type="checkbox" name="columns[]" value="email"> Email
          </label>
          <label>
            <input type="checkbox" name="columns[]" value="valor_total"> Valor
          </label>
          <label>
            <input type="checkbox" name="columns[]" value="status" id="statusCheckbox"> Status
          </label>
          <label>
            <input type="checkbox" name="columns[]" value="created_at"> Data
          </label>
          <label>
            <input type="checkbox" name="columns[]" value="ingresso"> Ingresso
          </label>
        </div>
        <div class="sub-options" id="statusSubOptions">
          <div class="checkbox-list">
            <label class="status-option status-approved">
              <input type="checkbox" name="status_options[]" value="approved"> Aprovado
            </label>
            <label class="status-option status-pending">
              <input type="checkbox" name="status_options[]" value="pending"> Pendente
            </label>
            <label class="status-option status-paid">
              <input type="checkbox" name="status_options[]" value="paid"> Pago
            </label>
            <label class="status-option status-cancelled">
              <input type="checkbox" name="status_options[]" value="cancelled"> Cancelado
            </label>
          </div>
        </div>
        <button type="submit" class="imprimir-lista-btn">Imprimir</button>
      </form>
    </div>
  </div>

  <!-- Modal para controle de visibilidade das colunas -->
  <div id="toggleModal" class="modal">
    <div class="modal-content">
      <span class="close" id="toggleClose">&times;</span>
      <h2>Controle de Visibilidade das Colunas</h2>
      <p>Selecione as colunas que deseja ocultar:</p>
      <div class="checkbox-list">
        <label>
          <input type="checkbox" class="toggle-column" data-col="col-order_id"> Order ID
        </label>
        <label>
          <input type="checkbox" class="toggle-column" data-col="col-nome_completo"> Nome Completo
        </label>
        <label>
          <input type="checkbox" class="toggle-column" data-col="col-email"> Email
        </label>
        <label>
          <input type="checkbox" class="toggle-column" data-col="col-valor"> Valor
        </label>
        <label>
          <input type="checkbox" class="toggle-column" data-col="col-status"> Status
        </label>
        <label>
          <input type="checkbox" class="toggle-column" data-col="col-created_at"> Data
        </label>
        <label>
          <input type="checkbox" class="toggle-column" data-col="col-ingresso"> Ingresso
        </label>
      </div>
      <button type="button" class="toggle-columns-btn" id="saveToggleBtn">Salvar Visibilidade</button>
    </div>
  </div>

  <script>
    // Modal de impressão
    var printModal = document.getElementById("printModal");
    var openPrintBtn = document.getElementById("openModalBtn");
    var closePrintBtn = document.querySelector("#printModal .close");

    if(openPrintBtn) {
      openPrintBtn.addEventListener("click", function(){
        printModal.style.display = "block";
      });
    }
    if(closePrintBtn) {
      closePrintBtn.addEventListener("click", function(){
        printModal.style.display = "none";
      });
    }
    window.addEventListener("click", function(event) {
      if (event.target == printModal) {
        printModal.style.display = "none";
      }
    });

    // Modal de controle de visibilidade das colunas
    var toggleModal = document.getElementById("toggleModal");
    var openToggleBtn = document.getElementById("toggleColumnsBtn");
    var closeToggleBtn = document.getElementById("toggleClose");

    if(openToggleBtn) {
      openToggleBtn.addEventListener("click", function(){
        toggleModal.style.display = "block";
      });
    }
    if(closeToggleBtn) {
      closeToggleBtn.addEventListener("click", function(){
        toggleModal.style.display = "none";
      });
    }
    window.addEventListener("click", function(event) {
      if (event.target == toggleModal) {
        toggleModal.style.display = "none";
      }
    });

    // Controle de visibilidade das colunas com armazenamento dinâmico por usuário
    var adminId = "<?php echo $_SESSION['adminid']; ?>";
    function applyColumnVisibility() {
      var toggles = document.querySelectorAll(".toggle-column");
      toggles.forEach(function(toggle) {
        var colClass = toggle.getAttribute("data-col");
        var key = adminId + "_" + colClass;
        if (toggle.checked) {
          document.querySelectorAll("." + colClass).forEach(function(el) {
            el.style.display = "none";
          });
          localStorage.setItem(key, "hidden");
        } else {
          document.querySelectorAll("." + colClass).forEach(function(el) {
            el.style.display = "";
          });
          localStorage.removeItem(key);
        }
      });
    }
    var saveToggleBtn = document.getElementById("saveToggleBtn");
    if(saveToggleBtn) {
      saveToggleBtn.addEventListener("click", function(){
        applyColumnVisibility();
        toggleModal.style.display = "none";
      });
    }
    window.addEventListener("load", function(){
      var toggles = document.querySelectorAll(".toggle-column");
      toggles.forEach(function(toggle) {
        var colClass = toggle.getAttribute("data-col");
        var key = adminId + "_" + colClass;
        if (localStorage.getItem(key) === "hidden") {
          toggle.checked = true;
          document.querySelectorAll("." + colClass).forEach(function(el) {
            el.style.display = "none";
          });
        }
      });
    });
  </script>
  
</body>
</html>
<?php 
$conn->close();
?>
