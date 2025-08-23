<?php
// eventos.php

// =========================================================
// Evita erro "Ignoring session_start()" se sessão já existir
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== INCLUDES & PERMISSÕES ====================
include_once('check_permissions.php');
if (!checkPermission("eventos")) {
    echo "Você não possui permissão para acessar esta página!";
    exit();
}

include('conexao.php');

// ==================== CHECA LOGIN ====================
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// ================== Verificar se o usuário logado é MASTER ==================
$usuarioId = $_SESSION['adminid'];
$isMaster  = false;

$stmtIsMaster = $conn->prepare("SELECT master FROM administradores WHERE id = ? LIMIT 1");
$stmtIsMaster->bind_param("i", $usuarioId);
$stmtIsMaster->execute();
$resIsMaster = $stmtIsMaster->get_result();
if ($resIsMaster && $resIsMaster->num_rows > 0) {
    $rowMaster = $resIsMaster->fetch_assoc();
    $isMaster  = ($rowMaster['master'] == 1);
}
$stmtIsMaster->close();
// ============================================================================

date_default_timezone_set('America/Sao_Paulo');

// ==================== FUNÇÕES ====================

/** Sanitiza strings para evitar XSS ou injeções */
function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES);
}

/** Upload de logo com validações */
function handleFileUpload($file, $targetDir = "uploads/") {
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    if (empty($file['name'])) {
        return null;
    }
    $filename   = basename($file['name']);
    $fileTmp    = $file['tmp_name'];
    $fileSize   = $file['size'];
    $fileError  = $file['error'];
    $allowedExtensions = ['jpg','jpeg','png','gif'];
    $maxFileSize = 2 * 1024 * 1024; // 2MB

    if ($fileError !== UPLOAD_ERR_OK) {
        return null;
    }
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedExtensions)) {
        return null;
    }
    if ($fileSize > $maxFileSize) {
        return null;
    }

    $uniqueName = uniqid('logo_', true) . '.' . $fileExt;
    $targetFile = $targetDir . $uniqueName;

    if (move_uploaded_file($fileTmp, $targetFile)) {
        return $targetFile;
    }
    return null;
}

// ==================== MENSAGENS VIA SESSÃO (TOAST) ====================
if (!isset($_SESSION['mensagem_sucesso'])) $_SESSION['mensagem_sucesso'] = "";
if (!isset($_SESSION['mensagem_erro']))    $_SESSION['mensagem_erro']    = "";

/** Salva mensagem de sucesso na sessão */
function setSuccess($msg) {
    $_SESSION['mensagem_sucesso'] = $msg;
}
/** Salva mensagem de erro na sessão */
function setError($msg) {
    $_SESSION['mensagem_erro'] = $msg;
}
/** Pega e limpa mensagem de sucesso da sessão */
function getSuccess() {
    $msg = $_SESSION['mensagem_sucesso'] ?? "";
    $_SESSION['mensagem_sucesso'] = "";
    return $msg;
}
/** Pega e limpa mensagem de erro da sessão */
function getError() {
    $msg = $_SESSION['mensagem_erro'] ?? "";
    $_SESSION['mensagem_erro'] = "";
    return $msg;
}

// ==================== PROCESSA FORM (CADASTRO/EDIÇÃO) ====================
$promotorId = $_SESSION['adminid'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verifica se é edição (ID > 0) ou novo cadastro
    if (isset($_POST['id']) && intval($_POST['id']) > 0) {
        // =========== EDIÇÃO ===========
        $id           = intval($_POST['id']);
        $nome         = sanitize($_POST['nome'] ?? '');
        $data_inicio  = sanitize($_POST['data_inicio'] ?? '');
        $hora_inicio  = sanitize($_POST['hora_inicio'] ?? '');
        $data_termino = sanitize($_POST['data_termino'] ?? '');
        $hora_termino = sanitize($_POST['hora_termino'] ?? '');
        $local        = sanitize($_POST['local'] ?? '');
        $atracoes     = sanitize($_POST['atracoes'] ?? '');
        $desc_evento  = sanitize($_POST['descricao_evento'] ?? '');

        // Busca dados originais (se não for master, manter campos de carrossel)
        $stmtSelect = $conn->prepare("
            SELECT logo, em_carrossel, prioridade, proporcao, titulo_carrossel, descricao_curta, codigo_evento
            FROM eventos
            WHERE id=?
        ");
        $stmtSelect->bind_param("i", $id);
        $stmtSelect->execute();
        $resSel = $stmtSelect->get_result();
        if (!$resSel || $resSel->num_rows < 1) {
            setError("Evento não encontrado para edição.");
            header("Location: eventos.php");
            exit();
        }
        $rowOld = $resSel->fetch_assoc();
        $stmtSelect->close();

        // Upload da logo (se enviado)
        $newLogo   = handleFileUpload($_FILES['logo'], "uploads/");
        $logoAtual = $rowOld['logo'];
        $logo      = ($newLogo !== null) ? $newLogo : $logoAtual;

        // Se for Master, atualiza também carrossel, prioridade etc.
        if ($isMaster) {
            $em_carrossel     = intval($_POST['em_carrossel'] ?? $rowOld['em_carrossel']);
            $prioridade       = intval($_POST['prioridade']   ?? $rowOld['prioridade']);
            $proporcao        = sanitize($_POST['proporcao']  ?? $rowOld['proporcao']);
            $titulo_carrossel = sanitize($_POST['titulo_carrossel'] ?? $rowOld['titulo_carrossel']);
            $descricao_curta  = sanitize($_POST['descricao_curta']   ?? $rowOld['descricao_curta']);
        } else {
            $em_carrossel     = $rowOld['em_carrossel'];
            $prioridade       = $rowOld['prioridade'];
            $proporcao        = $rowOld['proporcao'];
            $titulo_carrossel = $rowOld['titulo_carrossel'];
            $descricao_curta  = $rowOld['descricao_curta'];
        }

        // Código do evento
        $codigoEvento = intval($_POST['codigo_evento'] ?? $rowOld['codigo_evento']);
        if ($codigoEvento < 1000) {
            $codigoEvento = rand(1000, 9999);
        }

        // Prepara UPDATE
        $stmtUp = $conn->prepare("
            UPDATE eventos
            SET
                logo=?,
                data_inicio=?,
                hora_inicio=?,
                data_termino=?,
                hora_termino=?,
                local=?,
                nome=?,
                atracoes=?,
                descricao_evento=?,
                codigo_evento=?,
                em_carrossel=?,
                prioridade=?,
                proporcao=?,
                titulo_carrossel=?,
                descricao_curta=?
            WHERE id=?
        ");
        if (!$stmtUp) {
            setError("Erro ao preparar edição: ".$conn->error);
            header("Location: eventos.php");
            exit();
        }
        $stmtUp->bind_param(
            "sssssssssiiisssi",
            $logo,
            $data_inicio,
            $hora_inicio,
            $data_termino,
            $hora_termino,
            $local,
            $nome,
            $atracoes,
            $desc_evento,
            $codigoEvento,
            $em_carrossel,
            $prioridade,
            $proporcao,
            $titulo_carrossel,
            $descricao_curta,
            $id
        );
        if ($stmtUp->execute()) {
            setSuccess("Evento atualizado com sucesso!");
        } else {
            setError("Erro ao atualizar evento: ".$conn->error);
        }
        $stmtUp->close();
        header("Location: eventos.php");
        exit();

    } else {
        // =========== CADASTRO NOVO ===========
        if (
            !empty($_POST['nome']) &&
            !empty($_POST['data_inicio']) &&
            !empty($_POST['hora_inicio']) &&
            !empty($_POST['data_termino']) &&
            !empty($_POST['hora_termino']) &&
            !empty($_POST['local']) &&
            !empty($_POST['atracoes']) &&
            !empty($_POST['descricao_evento'])
        ) {
            $nome         = sanitize($_POST['nome']);
            $data_inicio  = sanitize($_POST['data_inicio']);
            $hora_inicio  = sanitize($_POST['hora_inicio']);
            $data_termino = sanitize($_POST['data_termino']);
            $hora_termino = sanitize($_POST['hora_termino']);
            $local        = sanitize($_POST['local']);
            $atracoes     = sanitize($_POST['atracoes']);
            $desc_evento  = sanitize($_POST['descricao_evento']);
            $lat          = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
            $lng          = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
            $logoPath     = handleFileUpload($_FILES['logo'], "uploads/");

            // Se for Master, pode definir carrossel
            if ($isMaster) {
                $em_carrossel     = intval($_POST['em_carrossel'] ?? 0);
                $prioridade       = intval($_POST['prioridade']   ?? 0);
                $proporcao        = sanitize($_POST['proporcao']  ?? '16:9');
                $titulo_carrossel = sanitize($_POST['titulo_carrossel'] ?? '');
                $descricao_curta  = sanitize($_POST['descricao_curta']   ?? '');
            } else {
                $em_carrossel     = 0;
                $prioridade       = 0;
                $proporcao        = '16:9';
                $titulo_carrossel = '';
                $descricao_curta  = '';
            }

            $codigoEvento  = rand(1000, 9999);
            $defaultStatus = "pendente";

            $stmtIn = $conn->prepare("
              INSERT INTO eventos
              (
                  logo,
                  data_inicio,
                  hora_inicio,
                  data_termino,
                  hora_termino,
                  local,
                  lat,
                  lng,
                  nome,
                  atracoes,
                  descricao_evento,
                  promotor_id,
                  status,
                  codigo_evento,
                  em_carrossel,
                  prioridade,
                  proporcao,
                  titulo_carrossel,
                  descricao_curta
              )
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmtIn->bind_param(
                "ssssssddsssisiiisss",
                $logoPath,
                $data_inicio,
                $hora_inicio,
                $data_termino,
                $hora_termino,
                $local,
                $lat,
                $lng,
                $nome,
                $atracoes,
                $desc_evento,
                $promotorId,
                $defaultStatus,
                $codigoEvento,
                $em_carrossel,
                $prioridade,
                $proporcao,
                $titulo_carrossel,
                $descricao_curta
            );

            if ($stmtIn->execute()) {
                setSuccess("Evento registrado com sucesso!");
            } else {
                setError("Erro ao inserir evento: " . $conn->error);
            }
            $stmtIn->close();
            header("Location: eventos.php");
            exit();
        } else {
            setError("Por favor, preencha todos os campos obrigatórios (Datas, Horas, Nome, Local, Atrações e Descrição).");
            header("Location: eventos.php");
            exit();
        }
    }
}

// =========== AJAX de edição (carrega form) ===========
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM eventos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultAjax = $stmt->get_result();
    if ($resultAjax->num_rows > 0) {
        $evento = $resultAjax->fetch_assoc();
    } else {
        echo "<div style='padding:20px;'>Evento não encontrado!</div>";
        exit();
    }
    $stmt->close();

    // ==========================
    // Formulário de edição (HTML que será injetado via AJAX no modal)
    // ==========================
    ?>
    <div class="modal-form-content">
      <h2 class="event-form-title">Editar Evento</h2>
      <form action="eventos.php" method="post" enctype="multipart/form-data" class="modal-form">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($evento['id']); ?>">

          <label class="event-form-label">Nome do Evento:</label>
          <input type="text" name="nome" class="event-form-input"
                 value="<?php echo htmlspecialchars($evento['nome']); ?>" required>

          <label class="event-form-label">Data de Início:</label>
          <input type="date" name="data_inicio" class="event-form-input"
                 value="<?php echo htmlspecialchars($evento['data_inicio']); ?>" required>

          <label class="event-form-label">Hora de Início:</label>
          <input type="time" name="hora_inicio" class="event-form-input"
                 value="<?php echo htmlspecialchars($evento['hora_inicio']); ?>" required>

          <label class="event-form-label">Data de Término:</label>
          <input type="date" name="data_termino" class="event-form-input"
                 value="<?php echo htmlspecialchars($evento['data_termino']); ?>" required>

          <label class="event-form-label">Hora de Término:</label>
          <input type="time" name="hora_termino" class="event-form-input"
                 value="<?php echo htmlspecialchars($evento['hora_termino']); ?>" required>

          <label class="event-form-label">Local (Mapbox - pesquise e selecione):</label>
          <div id="geocoder-container-edit"></div>
          <input type="text" id="local-edit" name="local" class="event-form-input"
                 value="<?php echo htmlspecialchars($evento['local']); ?>" readonly required>
          <input type="hidden" id="lat-edit" name="lat">
          <input type="hidden" id="lng-edit" name="lng">

          <label class="event-form-label">Atrações:</label>
          <textarea name="atracoes" class="event-form-input" required><?php
             echo htmlspecialchars($evento['atracoes']);
          ?></textarea>

          <label class="event-form-label">Descrição do Evento:</label>
          <textarea name="descricao_evento" class="event-form-input" required><?php
             echo htmlspecialchars($evento['descricao_evento']);
          ?></textarea>

          <label class="event-form-label">Logo do Evento (opcional):</label>
          <input type="file" name="logo" class="event-file-input">
          <?php if (!empty($evento['logo'])): ?>
            <div style="margin: 10px 0;">
              <strong>Logo atual:</strong><br>
              <img src="<?php echo htmlspecialchars($evento['logo']); ?>" alt="Logo"
                   style="max-width: 120px; border-radius: 5px;">
            </div>
          <?php endif; ?>

          <?php if ($GLOBALS['isMaster']): ?>
            <hr>
            <label>Em Carrossel (0 ou 1):</label>
            <input type="number" name="em_carrossel" min="0" max="1"
                   value="<?php echo (int)$evento['em_carrossel']; ?>">

            <label>Prioridade (menor número = mais destaque):</label>
            <input type="number" name="prioridade" value="<?php echo (int)$evento['prioridade']; ?>">

            <label>Proporção (16:9, 4:3, 1:1):</label>
            <input type="text" name="proporcao"
                   value="<?php echo htmlspecialchars($evento['proporcao']); ?>">

            <label>Título Carrossel:</label>
            <input type="text" name="titulo_carrossel"
                   value="<?php echo htmlspecialchars($evento['titulo_carrossel']); ?>">

            <label>Descrição Curta (Carrossel):</label>
            <textarea name="descricao_curta"><?php
               echo htmlspecialchars($evento['descricao_curta']);
            ?></textarea>
            <hr>
          <?php endif; ?>

          <?php if (empty($evento['codigo_evento'])): ?>
              <label>Código do Evento (4 dígitos):</label>
              <input type="number" name="codigo_evento" min="1000" max="9999">
          <?php else: ?>
              <p><strong>Código do Evento:</strong> <?php echo htmlspecialchars($evento['codigo_evento']); ?></p>
              <input type="hidden" name="codigo_evento"
                     value="<?php echo htmlspecialchars($evento['codigo_evento']); ?>">
          <?php endif; ?>

          <button type="submit" class="event-form-submit">Salvar Alterações</button>
      </form>
    </div>

    <script>
      // ===================== MAPBOX no modal de Edição =====================
      mapboxgl.accessToken = 'SUA_MAPBOX_ACCESS_TOKEN_AQUI'; // Altere para sua key
      let geocoderEdit;
      function initMapboxGeocoderEdit() {
        if (geocoderEdit) return;
        geocoderEdit = new MapboxGeocoder({
          accessToken: mapboxgl.accessToken,
          placeholder: 'Digite o endereço do evento',
          language: 'pt-BR',
          marker: false
        });
        const container = document.getElementById('geocoder-container-edit');
        if (container) {
          container.innerHTML = '';
          container.appendChild(geocoderEdit.onAdd());
        }
        geocoderEdit.on('result', function(e) {
          const coords = e.result.center;
          const placeName = e.result.place_name;
          document.getElementById('local-edit').value = placeName;
          document.getElementById('lat-edit').value   = coords[1];
          document.getElementById('lng-edit').value   = coords[0];
        });
      }
      initMapboxGeocoderEdit();
    </script>
    <?php
    exit();
}

// ==================== EXIBE PÁGINA PRINCIPAL (LISTAGEM E CADASTRO) ====================
$stmt = $conn->prepare("SELECT * FROM eventos WHERE promotor_id=? ORDER BY data_inicio ASC");
if (!$stmt) {
    die("Erro na preparação da query: " . $conn->error);
}
$stmt->bind_param("i", $promotorId);
$stmt->execute();
$result = $stmt->get_result();

// Data atual para separar futuros/passados
$currentDateTime = new DateTime('now'); // Objeto DateTime de agora
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Gerenciamento de Eventos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>

  <!-- MAPBOX -->
  <link href="https://api.mapbox.com/mapbox-gl-js/v2.13.0/mapbox-gl.css" rel="stylesheet">
  <script src="https://api.mapbox.com/mapbox-gl-js/v2.13.0/mapbox-gl.js"></script>
  <link rel="stylesheet"
        href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.7.2/mapbox-gl-geocoder.css" />
  <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v4.7.2/mapbox-gl-geocoder.min.js"></script>

  <!-- ESTILOS GERAIS (NOVA FOLHA DE ESTILO) -->
  <link rel="stylesheet" href="css/eventos.css">
</head>
<body class="eventos-body">

<?php include('header_admin.php'); ?>

<!-- Container de Toasts -->
<div class="toast-container" id="toastContainer"></div>

<div class="page-container">
  <h1 class="page-title">Painel de Eventos</h1>

  <!-- BOTÃO P/ EXIBIR FORM -->
  <div class="top-buttons">
    <button class="open-form-button" onclick="toggleCreateForm()">+ Cadastrar Novo Evento</button>
  </div>

  <!-- FORM DE CADASTRO -->
  <div class="create-form-area" id="createForm" style="display:none;">
    <div class="form-panel">
      <h2 class="create-form-title">Novo Evento</h2>

      <form action="eventos.php" method="post" enctype="multipart/form-data" oninput="calcDuration()">
        <div class="form-group">
          <label class="create-form-label">Nome do Evento *</label>
          <input type="text" name="nome" class="create-form-input" required>
        </div>

        <div class="form-row">
          <div class="form-col">
            <label class="create-form-label">Data de Início *</label>
            <input type="date" name="data_inicio" id="data_inicio" class="create-form-input" required>
          </div>
          <div class="form-col">
            <label class="create-form-label">Hora de Início *</label>
            <input type="time" name="hora_inicio" id="hora_inicio" class="create-form-input" required>
          </div>
          <div class="form-col">
            <label class="create-form-label">Data de Término *</label>
            <input type="date" name="data_termino" id="data_termino" class="create-form-input" required>
          </div>
          <div class="form-col">
            <label class="create-form-label">Hora de Término *</label>
            <input type="time" name="hora_termino" id="hora_termino" class="create-form-input" required>
          </div>
        </div>

        <div class="event-duration-info" id="durationInfo"></div>

        <div class="form-group">
          <label class="create-form-label">Local (Mapbox - pesquise e selecione) *</label>
          <div id="geocoder-container"></div>
          <input type="text" name="local" id="local" class="create-form-input" placeholder="Endereço do evento" readonly required>
          <input type="hidden" name="lat" id="lat">
          <input type="hidden" name="lng" id="lng">
        </div>

        <div class="form-group">
          <label class="create-form-label">Atrações *</label>
          <textarea name="atracoes" class="create-form-textarea" required></textarea>
        </div>

        <div class="form-group">
          <label class="create-form-label">Descrição do Evento *</label>
          <textarea name="descricao_evento" class="create-form-textarea" required
                    placeholder="Conte todos os detalhes do seu evento, como a programação e os diferenciais da sua produção!">
          </textarea>
        </div>

        <div class="form-group">
          <label class="create-form-label">Logo do Evento (até 2MB)</label>
          <input type="file" name="logo" class="create-form-input">
        </div>

        <?php if ($isMaster): ?>
          <div class="form-divider"></div>
          <div class="form-group">
            <label>Em Carrossel (0 ou 1):</label>
            <input type="number" name="em_carrossel" min="0" max="1" value="0" class="create-form-input">
          </div>
          <div class="form-group">
            <label>Prioridade (menor número = mais destaque):</label>
            <input type="number" name="prioridade" value="0" class="create-form-input">
          </div>
          <div class="form-group">
            <label>Proporção (16:9, 4:3, 1:1):</label>
            <input type="text" name="proporcao" value="16:9" class="create-form-input">
          </div>
          <div class="form-group">
            <label>Título Carrossel:</label>
            <input type="text" name="titulo_carrossel" class="create-form-input" value="">
          </div>
          <div class="form-group">
            <label>Descrição Curta (Carrossel):</label>
            <textarea name="descricao_curta" class="create-form-textarea"></textarea>
          </div>
          <div class="form-divider"></div>
        <?php endif; ?>

        <div class="form-actions">
          <button type="submit" class="create-form-submit">Registrar Evento</button>
          <button type="button" class="cancel-button" onclick="toggleCreateForm()">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EVENTOS FUTUROS -->
  <h2 class="event-section-title">Eventos Futuros</h2>
  <div class="event-list">
    <?php
    // reset pointer
    $result->data_seek(0);
    $temFuturos = false;
    while($row = $result->fetch_assoc()):
      $startStr = $row['data_inicio'].' '.$row['hora_inicio'];
      $startDT  = new DateTime($startStr);
      if ($startDT >= $currentDateTime):
        $temFuturos = true;
    ?>
    <div class="event-item">
      <?php if (!empty($row['logo'])): ?>
        <img src="<?php echo htmlspecialchars($row['logo']); ?>" alt="Logo" class="event-logo">
      <?php else: ?>
        <div class="event-logo-placeholder">Sem Imagem</div>
      <?php endif; ?>
      <div class="event-info">
        <span><strong>Nome:</strong> <?php echo htmlspecialchars($row['nome']); ?></span>
        <span><strong>Código do Evento:</strong> <?php echo htmlspecialchars($row['codigo_evento']); ?></span>
        <span>
          <strong>Status:</strong>
          <?php
            $status = strtolower($row['status']);
            if     ($status === 'aprovado')   echo '<span class="status-approved">Aprovado</span>';
            elseif ($status === 'desaprovado') echo '<span class="status-disapproved">Desaprovado</span>';
            else                               echo '<span class="status-pending">Pendente</span>';
          ?>
        </span>
        <span><strong>Data de Início:</strong>
          <?php echo date("d/m/Y", strtotime($row['data_inicio'])); ?>
          às <?php echo date("H:i", strtotime($row['hora_inicio'])); ?>
        </span>
        <span><strong>Data de Término:</strong>
          <?php echo date("d/m/Y", strtotime($row['data_termino'])); ?>
          às <?php echo date("H:i", strtotime($row['hora_termino'])); ?>
        </span>
        <span><strong>Local:</strong> <?php echo htmlspecialchars($row['local']); ?></span>
        <span><strong>Atrações:</strong> <?php echo nl2br(htmlspecialchars($row['atracoes'])); ?></span>
        <span><strong>Descrição do Evento:</strong> <?php echo nl2br(htmlspecialchars($row['descricao_evento'])); ?></span>

        <?php if ($isMaster): ?>
          <div class="master-info">
            <span><strong>Em Carrossel:</strong> <?php echo (int)$row['em_carrossel']; ?></span>
            <span><strong>Prioridade:</strong> <?php echo (int)$row['prioridade']; ?></span>
            <span><strong>Proporção:</strong> <?php echo htmlspecialchars($row['proporcao']); ?></span>
            <span><strong>Título (Carrossel):</strong> <?php echo htmlspecialchars($row['titulo_carrossel']); ?></span>
            <span><strong>Descrição Curta:</strong> <?php echo nl2br(htmlspecialchars($row['descricao_curta'])); ?></span>
          </div>
        <?php endif; ?>

        <div class="event-actions">
          <button class="edit-button" onclick="openEditModal(<?php echo (int)$row['id']; ?>)">Editar</button>
          <button class="delete-button"
            onclick="if(confirm('Tem certeza que deseja excluir este evento?')) {
              window.location.href='excluir_evento.php?id=<?php echo (int)$row['id']; ?>';
            }">
            Excluir
          </button>
        </div>
      </div>
    </div>
    <?php endif; endwhile; ?>

    <?php if (!$temFuturos): ?>
      <p class="no-events">Nenhum evento futuro cadastrado.</p>
    <?php endif; ?>
  </div>

  <!-- EVENTOS PASSADOS -->
  <h2 class="event-section-title">Eventos Passados</h2>
  <div class="event-list">
    <?php
    $result->data_seek(0);
    $temPassados = false;
    while($row = $result->fetch_assoc()):
      $startStr = $row['data_inicio'].' '.$row['hora_inicio'];
      $startDT  = new DateTime($startStr);
      if ($startDT < $currentDateTime):
        $temPassados = true;
    ?>
    <div class="event-item">
      <?php if (!empty($row['logo'])): ?>
        <img src="<?php echo htmlspecialchars($row['logo']); ?>" alt="Logo" class="event-logo">
      <?php else: ?>
        <div class="event-logo-placeholder">Sem Imagem</div>
      <?php endif; ?>
      <div class="event-info">
        <span><strong>Nome:</strong> <?php echo htmlspecialchars($row['nome']); ?></span>
        <span><strong>Código do Evento:</strong> <?php echo htmlspecialchars($row['codigo_evento']); ?></span>
        <span>
          <strong>Status:</strong>
          <?php
            $status = strtolower($row['status']);
            if     ($status === 'aprovado')   echo '<span class="status-approved">Aprovado</span>';
            elseif ($status === 'desaprovado') echo '<span class="status-disapproved">Desaprovado</span>';
            else                               echo '<span class="status-pending">Pendente</span>';
          ?>
        </span>
        <span><strong>Data de Início:</strong>
          <?php echo date("d/m/Y", strtotime($row['data_inicio'])); ?>
          às <?php echo date("H:i", strtotime($row['hora_inicio'])); ?>
        </span>
        <span><strong>Data de Término:</strong>
          <?php echo date("d/m/Y", strtotime($row['data_termino'])); ?>
          às <?php echo date("H:i", strtotime($row['hora_termino'])); ?>
        </span>
        <span><strong>Local:</strong> <?php echo htmlspecialchars($row['local']); ?></span>
        <span><strong>Atrações:</strong> <?php echo nl2br(htmlspecialchars($row['atracoes'])); ?></span>
        <span><strong>Descrição do Evento:</strong> <?php echo nl2br(htmlspecialchars($row['descricao_evento'])); ?></span>

        <?php if ($isMaster): ?>
          <div class="master-info">
            <span><strong>Em Carrossel:</strong> <?php echo (int)$row['em_carrossel']; ?></span>
            <span><strong>Prioridade:</strong> <?php echo (int)$row['prioridade']; ?></span>
            <span><strong>Proporção:</strong> <?php echo htmlspecialchars($row['proporcao']); ?></span>
            <span><strong>Título (Carrossel):</strong> <?php echo htmlspecialchars($row['titulo_carrossel']); ?></span>
            <span><strong>Descrição Curta:</strong> <?php echo nl2br(htmlspecialchars($row['descricao_curta'])); ?></span>
          </div>
        <?php endif; ?>

        <div class="event-actions">
          <button class="edit-button" onclick="openEditModal(<?php echo (int)$row['id']; ?>)">Editar</button>
          <button class="delete-button"
            onclick="if(confirm('Tem certeza que deseja excluir este evento?')) {
              window.location.href='excluir_evento.php?id=<?php echo (int)$row['id']; ?>';
            }">
            Excluir
          </button>
        </div>
      </div>
    </div>
    <?php endif; endwhile; ?>

    <?php if (!$temPassados): ?>
      <p class="no-events">Nenhum evento passado cadastrado.</p>
    <?php endif; ?>
  </div>
</div>

<!-- MODAL EDIT (conteúdo via AJAX) -->
<div id="editModalOverlay" class="modal-overlay" style="display:none;">
  <div class="modal-content">
    <button class="close-modal-button" onclick="closeEditModal()">×</button>
    <div id="editModalContent"></div>
  </div>
</div>

<?php
$result->close();
$stmt->close();
$conn->close();
?>

<script>
  // =================== TOASTS ===================
  const toastContainer = document.getElementById('toastContainer');
  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.classList.add('toast');
    if (type === 'success') toast.classList.add('toast-success');
    if (type === 'error')   toast.classList.add('toast-error');
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, 5000);
  }

  // Mensagens de sessão (PHP) -> Toast
  const msgSucesso = "<?php echo getSuccess(); ?>";
  const msgErro    = "<?php echo getError(); ?>";
  if (msgSucesso.trim() !== "") showToast(msgSucesso, 'success');
  if (msgErro.trim()    !== "") showToast(msgErro,    'error');

  // =================== FORM DE CADASTRO (mostrar/ocultar) ===================
  function toggleCreateForm() {
    const formArea = document.getElementById('createForm');
    const display  = formArea.style.display;
    formArea.style.display = (display === 'none' || display === '') ? 'block' : 'none';
  }

  // =================== MAPBOX (cadastro) ===================
  mapboxgl.accessToken = 'pk.eyJ1Ijoia2F1YW1hdGhldXM5MjAiLCJhIjoiY203OGZvbmRyMWxkMzJqb2l6bXQ2NDZpMSJ9.WjRV7tH7lbvR1wVDDvQD0g'; // Substitua pela sua token
  let geocoderCreate;
  function initCreateGeocoder() {
    if (geocoderCreate) return;
    geocoderCreate = new MapboxGeocoder({
      accessToken: mapboxgl.accessToken,
      placeholder: 'Digite o endereço do evento',
      language: 'pt-BR',
      marker: false
    });
    const container = document.getElementById('geocoder-container');
    if (container) {
      container.innerHTML = '';
      container.appendChild(geocoderCreate.onAdd());
    }
    geocoderCreate.on('result', function(e) {
      const coords = e.result.center;
      const placeName = e.result.place_name;
      document.getElementById('local').value = placeName;
      document.getElementById('lat').value   = coords[1];
      document.getElementById('lng').value   = coords[0];
    });
  }
  // Inicializa assim que a página carrega
  window.onload = () => {
    initCreateGeocoder();
  };

  // =================== CALCULAR DURAÇÃO (dias/horas) ===================
  function calcDuration() {
    const di = document.getElementById('data_inicio').value;
    const hi = document.getElementById('hora_inicio').value;
    const df = document.getElementById('data_termino').value;
    const hf = document.getElementById('hora_termino').value;

    const durationEl = document.getElementById('durationInfo');
    if (!di || !hi || !df || !hf) {
      durationEl.textContent = "";
      return;
    }

    const startStr = di + 'T' + hi; // ISO format
    const endStr   = df + 'T' + hf;
    const start    = new Date(startStr);
    const end      = new Date(endStr);

    if (isNaN(start.getTime()) || isNaN(end.getTime())) {
      durationEl.textContent = "";
      return;
    }
    if (end <= start) {
      durationEl.textContent = "⚠ Atenção: a data final é anterior ou igual à inicial!";
      return;
    }
    const diffMs = end - start;
    let diffDays  = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    let diffHours = Math.floor(diffMs / (1000 * 60 * 60)) % 24;
    let diffMins  = Math.floor(diffMs / (1000 * 60)) % 60;

    let result = "Seu evento vai durar ";
    if (diffDays > 0) {
      result += diffDays + (diffDays === 1 ? " dia" : " dias");
      if (diffHours > 0) result += " e " + diffHours + "h";
    } else {
      if (diffHours > 0) {
        result += diffHours + "h";
        if (diffMins > 0) result += " e " + diffMins + "min";
      } else {
        result += diffMins + " min";
      }
    }
    durationEl.textContent = result;
  }

  // =================== MODAL EDITAR ===================
  function openEditModal(id) {
    fetch('eventos.php?action=edit&id=' + id)
      .then(response => response.text())
      .then(html => {
        document.getElementById('editModalContent').innerHTML = html;
        document.getElementById('editModalOverlay').style.display = 'flex';
        document.body.style.overflow = 'hidden';
      })
      .catch(err => console.error('Erro ao carregar formulário de edição:', err));
  }
  function closeEditModal() {
    document.getElementById('editModalOverlay').style.display = 'none';
    document.body.style.overflow = 'auto';
  }
  window.addEventListener('click', function(e) {
    const editOverlay = document.getElementById('editModalOverlay');
    if (e.target === editOverlay) {
      closeEditModal();
    }
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeEditModal();
    }
  });
</script>

</body>
</html>
