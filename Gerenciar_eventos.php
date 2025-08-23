<?php
// Gerenciar_eventos.php
session_start();
include('conexao.php');

if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// =============================================
// =========== PROCESSAMENTO via AJAX ==========
// =============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // === (1) Atualizar STATUS (Aprovado/Desaprovado) ===
        if ($action == 'status') {
            $event_id = intval($_POST['event_id']);
            $newStatus = ($_POST['status'] === '1') ? 'aprovado' : 'desaprovado';
            
            $stmt = $conn->prepare("UPDATE eventos SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $event_id);
            if ($stmt->execute()) {
                echo "Status atualizado com sucesso!";
            } else {
                echo "Erro ao atualizar status: " . $conn->error;
            }
            $stmt->close();
            $conn->close();
            exit();
        }
        
        // === (2) Atualizar CARROSSEL (em_carrossel 0 ou 1) ===
        elseif ($action == 'carrossel') {
            $event_id = intval($_POST['event_id']);
            $carrosselValue = ($_POST['carrossel'] === '1') ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE eventos SET em_carrossel = ? WHERE id = ?");
            $stmt->bind_param("ii", $carrosselValue, $event_id);
            if ($stmt->execute()) {
                echo "Carrossel atualizado com sucesso!";
            } else {
                echo "Erro ao atualizar carrossel: " . $conn->error;
            }
            $stmt->close();
            $conn->close();
            exit();
        }
        
        // === (3) Editar evento (upload imagem se necessário) ===
        elseif ($action == 'edit') {
            $id              = intval($_POST['id']);
            $nome            = $_POST['nome'];
            $data_inicio     = $_POST['data_inicio'];   // Substitui "data"
            $hora_inicio     = $_POST['hora_inicio'];   // Substitui "horario"
            $local           = $_POST['local'];
            $atracoes        = $_POST['atracoes'];
            $promotor_id     = intval($_POST['promotor_id']);
            
            // Campos extras
            $em_carrossel     = isset($_POST['em_carrossel'])     ? intval($_POST['em_carrossel'])     : 0;
            $prioridade       = isset($_POST['prioridade'])       ? intval($_POST['prioridade'])       : 0;
            $proporcao        = isset($_POST['proporcao'])        ? $_POST['proporcao']                : '16:9';
            $titulo_carrossel = isset($_POST['titulo_carrossel']) ? $_POST['titulo_carrossel']         : '';
            $descricao_curta  = isset($_POST['descricao_curta'])  ? $_POST['descricao_curta']          : '';
            
            // Upload de nova imagem
            $logo = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir  = 'uploads/';
                $filename   = time() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $logo = $targetPath;
                }
            }
            
            // Monta a query
            if ($logo !== null) {
                // LOGO foi alterado
                $stmt = $conn->prepare("
                    UPDATE eventos
                       SET nome           = ?,
                           data_inicio    = ?,
                           hora_inicio    = ?,
                           local          = ?,
                           atracoes       = ?,
                           promotor_id    = ?,
                           logo           = ?,
                           em_carrossel   = ?,
                           prioridade     = ?,
                           proporcao      = ?,
                           titulo_carrossel = ?,
                           descricao_curta  = ?
                     WHERE id = ?
                ");
                
                // 13 parâmetros
                // 1)  nome -> s
                // 2)  data_inicio -> s
                // 3)  hora_inicio -> s
                // 4)  local -> s
                // 5)  atracoes -> s
                // 6)  promotor_id -> i
                // 7)  logo -> s
                // 8)  em_carrossel -> i
                // 9)  prioridade -> i
                // 10) proporcao -> s
                // 11) titulo_carrossel -> s
                // 12) descricao_curta -> s
                // 13) id -> i
                $stmt->bind_param(
                    "sssssi sii sss i",  // Substituir pelos 13 caracteres sem espaços
                    $nome,
                    $data_inicio,
                    $hora_inicio,
                    $local,
                    $atracoes,
                    $promotor_id,
                    $logo,
                    $em_carrossel,
                    $prioridade,
                    $proporcao,
                    $titulo_carrossel,
                    $descricao_curta,
                    $id
                );
                
                /*
                  Precisamos que fique sem espaços, algo como:
                  "sssssisiisssi"
                  Vamos separar p/ confirmar:
                  s (nome)
                  s (data_inicio)
                  s (hora_inicio)
                  s (local)
                  s (atracoes)
                  i (promotor_id)
                  s (logo)
                  i (em_carrossel)
                  i (prioridade)
                  s (proporcao)
                  s (titulo_carrossel)
                  s (descricao_curta)
                  i (id)
                */
                
            } else {
                // SEM trocar a logo
                $stmt = $conn->prepare("
                    UPDATE eventos
                       SET nome           = ?,
                           data_inicio    = ?,
                           hora_inicio    = ?,
                           local          = ?,
                           atracoes       = ?,
                           promotor_id    = ?,
                           em_carrossel   = ?,
                           prioridade     = ?,
                           proporcao      = ?,
                           titulo_carrossel = ?,
                           descricao_curta  = ?
                     WHERE id = ?
                ");
                
                // 12 parâmetros
                // 1)  nome -> s
                // 2)  data_inicio -> s
                // 3)  hora_inicio -> s
                // 4)  local -> s
                // 5)  atracoes -> s
                // 6)  promotor_id -> i
                // 7)  em_carrossel -> i
                // 8)  prioridade -> i
                // 9)  proporcao -> s
                // 10) titulo_carrossel -> s
                // 11) descricao_curta -> s
                // 12) id -> i
                $stmt->bind_param(
                    "sssss i i i s s s i",
                    $nome,
                    $data_inicio,
                    $hora_inicio,
                    $local,
                    $atracoes,
                    $promotor_id,
                    $em_carrossel,
                    $prioridade,
                    $proporcao,
                    $titulo_carrossel,
                    $descricao_curta,
                    $id
                );
                
                /*
                  Retirando espaços: "sssssiiisssi"
                  Vamos enumerar:
                    s (nome)
                    s (data_inicio)
                    s (hora_inicio)
                    s (local)
                    s (atracoes)
                    i (promotor_id)
                    i (em_carrossel)
                    i (prioridade)
                    s (proporcao)
                    s (titulo_carrossel)
                    s (descricao_curta)
                    i (id)
                */
            }
            
            // Remova espaços extras e use a string de tipos sem quebras:
            // Com logo => "sssssisiisssi"
            // Sem logo => "sssssiiisssi"
            
            if ($stmt === false) {
                echo "Erro ao preparar o UPDATE: " . $conn->error;
                exit();
            }
            
            if ($stmt->execute()) {
                echo "Evento atualizado com sucesso!";
            } else {
                echo "Erro ao atualizar evento: " . $conn->error;
            }
            $stmt->close();
            $conn->close();
            exit();
        }
        
        // === (4) Excluir evento ===
        elseif ($action == 'delete') {
            $id = intval($_POST['id']);
            // Excluir ingressos desse evento (se houver)
            $stmt = $conn->prepare("DELETE FROM ingressos WHERE evento_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // Excluir o próprio evento
            $stmt = $conn->prepare("DELETE FROM eventos WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                echo "Evento excluído com sucesso!";
            } else {
                echo "Erro ao excluir evento: " . $conn->error;
            }
            $stmt->close();
            $conn->close();
            exit();
        }
    }
}

// ===============================================
// ============= CARREGANDO EVENTOS =============
// ===============================================
$current_date = date("Y-m-d");

// Aqui o problema: 'data' não existe. Use 'data_inicio'
$sql = "SELECT e.*, a.nome AS admin_nome
          FROM eventos e
          LEFT JOIN administradores a ON e.promotor_id = a.id
         WHERE e.data_inicio >= ?
      ORDER BY e.data_inicio ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();

$eventos = [];
while ($row = $result->fetch_assoc()) {
    $eventos[] = $row;
}
$stmt->close();
$conn->close();
?>

<?php include('header_admin.php'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Gerenciar Eventos</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="css/Gerenciar_eventos.css">
  
  
</head>
<body>
<div class="container">
  <div class="header">
    <h1><i class="fas fa-calendar-alt"></i> Gerenciar Eventos</h1>
  </div>

  <div id="statusMessage" class="message"></div>

  <?php if (!empty($eventos)): ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Logo</th>
            <th>Evento</th>
            <th>Data/Horário</th>
            <th>Local</th>
            <th>Atrações</th>
            <th>Status</th>
            <th>Carrossel</th>
            <th>Administrador</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($eventos as $evento): ?>
          <?php 
            $statusLower = strtolower($evento['status']);
          ?>
          <tr data-status="<?= $statusLower; ?>">
            <td><?= $evento['id'] ?></td>
            <td>
              <?php if ($evento['logo']): ?>
                <img src="<?= htmlspecialchars($evento['logo']) ?>" class="event-logo" alt="Logo">
              <?php else: ?>
                <i class="fas fa-image fa-2x text-muted"></i>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($evento['nome']) ?></td>
            <td>
              <div><?= date("d/m/Y", strtotime($evento['data_inicio'])) ?></div>
              <small><?= date("H:i", strtotime($evento['hora_inicio'])) ?></small>
            </td>
            <td><?= htmlspecialchars($evento['local']) ?></td>
            <td><?= nl2br(htmlspecialchars($evento['atracoes'])) ?></td>
            
            <!-- Toggle de STATUS (Aprovado/Desaprovado) -->
            <td>
              <div class="toggle-wrapper">
                <label class="status-toggle">
                  <input type="checkbox"
                         data-event-id="<?= $evento['id'] ?>"
                         data-toggle-type="status"
                         <?= ($statusLower === 'aprovado') ? 'checked' : '' ?>>
                  <span class="toggle-slider"></span>
                </label>
              </div>
              <?php 
                if ($statusLower === 'aprovado') {
                    echo '<span class="label-small status-approved">Aprovado</span>';
                } elseif ($statusLower === 'desaprovado') {
                    echo '<span class="label-small status-disapproved">Desaprovado</span>';
                } else {
                    echo '<span class="label-small status-pending">Pendente</span>';
                }
              ?>
            </td>

            <!-- Toggle de CARROSSEL (Sim/Não) -->
            <td>
              <div class="toggle-wrapper">
                <label class="carrossel-toggle">
                  <input type="checkbox"
                         data-event-id="<?= $evento['id'] ?>"
                         data-toggle-type="carrossel"
                         <?= ($evento['em_carrossel'] == 1 ? 'checked' : '') ?>>
                  <span class="toggle-slider"></span>
                </label>
              </div>
              <span class="label-small"><?= ($evento['em_carrossel'] == 1 ? 'Sim' : 'Não') ?></span>
            </td>

            <td><?= htmlspecialchars($evento['admin_nome']) ?></td>
            
            <!-- Ícone de olho (visualizar) -->
            <td>
              <i class="fas fa-eye view-event" style="cursor:pointer;"
                 data-event='<?php echo json_encode($evento, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'></i>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="no-events">
      <i class="fas fa-calendar-times fa-3x"></i>
      <h3>Nenhum evento encontrado</h3>
      <p>Não há eventos futuros para gerenciar.</p>
    </div>
  <?php endif; ?>
</div>

<!-- ================= MODAL ================= -->
<div id="eventModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    
    <div class="modal-header">
      <h2 id="modalEventName"></h2>
    </div>
    
    <img id="modalEventLogo" src="" alt="Logo do Evento">
    
    <!-- Input file oculto para alterar a imagem -->
    <input type="file" id="editEventLogoInput" style="display:none;" accept="image/*">
    
    <div id="modalFields"></div> <!-- onde iremos inserir o formulário ou texto de visualização -->
    
    <div id="modalActions">
      <button id="editButton">Editar</button>
      <button id="deleteButton">Excluir</button>
      <button id="cancelEditButton" style="display:none;">Cancelar</button>
    </div>
  </div>
</div>

<script>
  let currentEventData = null;
  let isEditMode = false;
  
  const modal = document.getElementById("eventModal");
  const closeModal = modal.querySelector(".close");
  const editLogoInput = document.getElementById("editEventLogoInput");
  const modalFields = document.getElementById("modalFields");
  const messageElement = document.getElementById("statusMessage");

  // === FECHAR MODAL ===
  closeModal.addEventListener("click", () => {
    modal.style.display = "none";
  });
  window.addEventListener("click", (e) => {
    if (e.target == modal) {
      modal.style.display = "none";
    }
  });

  // ================== 1) Abrir Modal ao clicar no ícone de olho ==================
  document.querySelectorAll(".view-event").forEach(icon => {
    icon.addEventListener("click", function() {
      const eventData = JSON.parse(this.getAttribute("data-event"));
      currentEventData = eventData;
      isEditMode = false;

      // Modo de VISUALIZAÇÃO
      document.getElementById("modalEventName").textContent = eventData.nome;

      // Imagem
      if (eventData.logo) {
        document.getElementById("modalEventLogo").src = eventData.logo;
        document.getElementById("modalEventLogo").style.display = "block";
      } else {
        document.getElementById("modalEventLogo").style.display = "none";
      }

      // Monta texto "bonito" de VISUALIZAÇÃO
      modalFields.innerHTML = `
        <div class="form-group">
          <label>ID:</label>
          <p>${eventData.id}</p>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Data Início:</label>
            <p>${new Date(eventData.data_inicio).toLocaleDateString("pt-BR")}</p>
          </div>
          <div class="form-group">
            <label>Hora Início:</label>
            <p>${eventData.hora_inicio}</p>
          </div>
        </div>
        <div class="form-group">
          <label>Local:</label>
          <p>${eventData.local}</p>
        </div>
        <div class="form-group">
          <label>Atrações:</label>
          <p>${eventData.atracoes}</p>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Status:</label>
            <p>${eventData.status}</p>
          </div>
          <div class="form-group">
            <label>Carrossel:</label>
            <p>${(eventData.em_carrossel == 1) ? 'Sim' : 'Não'}</p>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Prioridade:</label>
            <p>${eventData.prioridade || 0}</p>
          </div>
          <div class="form-group">
            <label>Proporção:</label>
            <p>${eventData.proporcao || '16:9'}</p>
          </div>
        </div>
        <div class="form-group">
          <label>Título Carrossel:</label>
          <p>${eventData.titulo_carrossel || ''}</p>
        </div>
        <div class="form-group">
          <label>Descrição Curta:</label>
          <p>${eventData.descricao_curta || ''}</p>
        </div>
        <div class="form-group">
          <label>Promotor ID:</label>
          <p>${eventData.promotor_id}</p>
        </div>
        <div class="form-group">
          <label>Administrador Vinculado:</label>
          <p>${eventData.admin_nome || ''}</p>
        </div>
      `;

      document.getElementById("editButton").textContent = "Editar";
      document.getElementById("cancelEditButton").style.display = "none";

      modal.style.display = "block";
    });
  });

  // ================== 2) Toggle de STATUS e CARROSSEL ==================
  document.querySelectorAll("input[data-toggle-type]").forEach(toggle => {
    toggle.addEventListener("change", function() {
      const eventId = this.dataset.eventId;
      const toggleType = this.dataset.toggleType; // "status" ou "carrossel"
      const row = this.closest("tr"); 
      const rowStatus = row.getAttribute("data-status"); // status do evento em lowercase
      const valor = this.checked ? '1' : '0';
      
      // >>>>>> Exemplo de bloqueio: se não quiser ativar carrossel quando não aprovado:
      if (toggleType === 'carrossel' && valor === '1') {
        if (rowStatus !== 'aprovado') {
          // Reverte o toggle e mostra erro
          this.checked = false;
          messageElement.textContent = 'Não é possível ativar carrossel para evento não aprovado!';
          messageElement.className = 'message error';
          messageElement.style.display = 'block';
          setTimeout(() => { messageElement.style.display = 'none'; }, 4000);
          return;
        }
      }

      let bodyString = '';
      if (toggleType === 'status') {
        bodyString = 'action=status&event_id=' + eventId + '&status=' + valor;
      } else {
        // toggleType === 'carrossel'
        bodyString = 'action=carrossel&event_id=' + eventId + '&carrossel=' + valor;
      }

      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: bodyString
      })
      .then(response => response.text())
      .then(data => {
        messageElement.textContent = data;
        messageElement.className = 'message success';
        messageElement.style.display = 'block';
        setTimeout(() => { messageElement.style.display = 'none'; }, 3000);

        // Se atualizou STATUS e foi aprovado/desaprovado, atualiza data-status
        if (toggleType === 'status') {
          let newStatus = (valor === '1') ? 'aprovado' : 'desaprovado';
          row.setAttribute('data-status', newStatus);
        }
      })
      .catch(error => {
        messageElement.textContent = 'Erro ao atualizar ' + toggleType;
        messageElement.className = 'message error';
        messageElement.style.display = 'block';
        console.error('Erro:', error);
        // Reverte toggle se falhar
        this.checked = !this.checked;
      });
    });
  });

  // ================== 3) Botão EDITAR / SALVAR no modal ==================
  document.getElementById("editButton").addEventListener("click", function() {
    if (!isEditMode) {
      // Entrar em modo EDIÇÃO
      isEditMode = true;
      this.textContent = "Salvar";
      document.getElementById("cancelEditButton").style.display = "inline-block";
      
      // Substituir conteúdo do modalFields por inputs
      modalFields.innerHTML = `
        <div class="form-group">
          <label>ID:</label>
          <p>${currentEventData.id}</p>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Data Início:</label>
            <input type="date" id="editEventDataInicio" value="${new Date(currentEventData.data_inicio).toISOString().substring(0,10)}">
          </div>
          <div class="form-group">
            <label>Hora Início:</label>
            <input type="time" id="editEventHoraInicio" value="${currentEventData.hora_inicio}">
          </div>
        </div>
        <div class="form-group">
          <label>Local:</label>
          <input type="text" id="editEventLocal" value="${currentEventData.local}">
        </div>
        <div class="form-group">
          <label>Atrações:</label>
          <textarea id="editEventAtracoes" rows="2">${currentEventData.atracoes}</textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>em_carrossel:</label>
            <select id="editEmCarrossel">
              <option value="0" ${currentEventData.em_carrossel == 0 ? 'selected' : ''}>Não</option>
              <option value="1" ${currentEventData.em_carrossel == 1 ? 'selected' : ''}>Sim</option>
            </select>
          </div>
          <div class="form-group">
            <label>Prioridade:</label>
            <input type="number" id="editPrioridade" value="${currentEventData.prioridade || 0}">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Proporção:</label>
            <select id="editProporcao">
              <option value="16:9" ${(currentEventData.proporcao=='16:9')?'selected':''}>16:9</option>
              <option value="4:3" ${(currentEventData.proporcao=='4:3')?'selected':''}>4:3</option>
              <option value="1:1" ${(currentEventData.proporcao=='1:1')?'selected':''}>1:1</option>
            </select>
          </div>
          <div class="form-group">
            <label>Título Carrossel:</label>
            <input type="text" id="editTituloCarrossel" value="${currentEventData.titulo_carrossel||''}">
          </div>
        </div>
        <div class="form-group">
          <label>Descrição Curta:</label>
          <textarea id="editDescricaoCurta" rows="2">${currentEventData.descricao_curta||''}</textarea>
        </div>
        <div class="form-group">
          <label>Promotor ID:</label>
          ${
            (currentEventData.promotor_id != 1)
              ? `<input type="number" id="editPromotorID" value="${currentEventData.promotor_id}">`
              : `<p>${currentEventData.promotor_id}</p>`
          }
        </div>
        <div class="form-group">
          <label>Nome do Evento:</label>
          <input type="text" id="editEventName" value="${currentEventData.nome}">
        </div>
      `;

      // Tornar imagem clicável para trocar
      document.getElementById("modalEventLogo").style.cursor = "pointer";
      document.getElementById("modalEventLogo").addEventListener("click", triggerImageInput);

    } else {
      // SALVAR alterações
      let updatedEvent = {
        id: currentEventData.id,
        nome: document.getElementById("editEventName").value,
        data_inicio: document.getElementById("editEventDataInicio").value,
        hora_inicio: document.getElementById("editEventHoraInicio").value,
        local: document.getElementById("editEventLocal").value,
        atracoes: document.getElementById("editEventAtracoes").value,
        promotor_id: (currentEventData.promotor_id != 1)
                      ? document.getElementById("editPromotorID").value
                      : currentEventData.promotor_id,
        em_carrossel: document.getElementById("editEmCarrossel").value,
        prioridade: document.getElementById("editPrioridade").value,
        proporcao: document.getElementById("editProporcao").value,
        titulo_carrossel: document.getElementById("editTituloCarrossel").value,
        descricao_curta: document.getElementById("editDescricaoCurta").value
      };

      let formData = new FormData();
      formData.append("action", "edit");
      formData.append("id", updatedEvent.id);
      formData.append("nome", updatedEvent.nome);
      formData.append("data_inicio", updatedEvent.data_inicio);
      formData.append("hora_inicio", updatedEvent.hora_inicio);
      formData.append("local", updatedEvent.local);
      formData.append("atracoes", updatedEvent.atracoes);
      formData.append("promotor_id", updatedEvent.promotor_id);
      formData.append("em_carrossel", updatedEvent.em_carrossel);
      formData.append("prioridade", updatedEvent.prioridade);
      formData.append("proporcao", updatedEvent.proporcao);
      formData.append("titulo_carrossel", updatedEvent.titulo_carrossel);
      formData.append("descricao_curta", updatedEvent.descricao_curta);

      // Se um novo arquivo foi selecionado
      if (editLogoInput.files.length > 0) {
        formData.append("image", editLogoInput.files[0]);
      }

      fetch(location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        alert(data);
        // Atualiza currentEventData local
        currentEventData.nome = updatedEvent.nome;
        currentEventData.data_inicio = updatedEvent.data_inicio;
        currentEventData.hora_inicio = updatedEvent.hora_inicio;
        currentEventData.local = updatedEvent.local;
        currentEventData.atracoes = updatedEvent.atracoes;
        currentEventData.promotor_id = updatedEvent.promotor_id;
        currentEventData.em_carrossel = updatedEvent.em_carrossel;
        currentEventData.prioridade = updatedEvent.prioridade;
        currentEventData.proporcao = updatedEvent.proporcao;
        currentEventData.titulo_carrossel = updatedEvent.titulo_carrossel;
        currentEventData.descricao_curta = updatedEvent.descricao_curta;

        // Se imagem foi alterada
        if (editLogoInput.files.length > 0) {
          currentEventData.logo = URL.createObjectURL(editLogoInput.files[0]);
        }

        // Volta para modo VISUALIZAÇÃO
        isEditMode = false;
        document.getElementById("editButton").textContent = "Editar";
        document.getElementById("cancelEditButton").style.display = "none";
        // Restaura cursor da imagem
        document.getElementById("modalEventLogo").removeEventListener("click", triggerImageInput);
        document.getElementById("modalEventLogo").style.cursor = "default";

        // Recarrega modal em modo de visualização
        document.getElementById("modalEventName").textContent = currentEventData.nome;
        document.getElementById("modalEventLogo").src = currentEventData.logo || "";
        
        modalFields.innerHTML = `
          <div class="form-group">
            <label>ID:</label>
            <p>${currentEventData.id}</p>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Data Início:</label>
              <p>${new Date(currentEventData.data_inicio).toLocaleDateString("pt-BR")}</p>
            </div>
            <div class="form-group">
              <label>Hora Início:</label>
              <p>${currentEventData.hora_inicio}</p>
            </div>
          </div>
          <div class="form-group">
            <label>Local:</label>
            <p>${currentEventData.local}</p>
          </div>
          <div class="form-group">
            <label>Atrações:</label>
            <p>${currentEventData.atracoes}</p>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Carrossel:</label>
              <p>${(currentEventData.em_carrossel == 1)? 'Sim':'Não'}</p>
            </div>
            <div class="form-group">
              <label>Prioridade:</label>
              <p>${currentEventData.prioridade}</p>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Proporção:</label>
              <p>${currentEventData.proporcao}</p>
            </div>
            <div class="form-group">
              <label>Título Carrossel:</label>
              <p>${currentEventData.titulo_carrossel}</p>
            </div>
          </div>
          <div class="form-group">
            <label>Descrição Curta:</label>
            <p>${currentEventData.descricao_curta}</p>
          </div>
          <div class="form-group">
            <label>Promotor ID:</label>
            <p>${currentEventData.promotor_id}</p>
          </div>
          <div class="form-group">
            <label>Nome do Evento:</label>
            <p>${currentEventData.nome}</p>
          </div>
        `;
      })
      .catch(error => {
        alert("Erro ao atualizar evento");
        console.error('Erro:', error);
      });
    }
  });

  // ================== 4) Botão Cancelar Edição ==================
  document.getElementById("cancelEditButton").addEventListener("click", function() {
    isEditMode = false;
    document.getElementById("editButton").textContent = "Editar";
    this.style.display = "none";
    document.getElementById("modalEventLogo").removeEventListener("click", triggerImageInput);
    document.getElementById("modalEventLogo").style.cursor = "default";
    // Simula reabrir o modal no modo visualização
    document.querySelector(".view-event[data-event='"+ JSON.stringify(currentEventData).replace(/"/g, '\\"') +"']").click();
  });

  // ================== 5) Botão Excluir ==================
  document.getElementById("deleteButton").addEventListener("click", function() {
    if (!currentEventData) return;
    if (confirm("Tem certeza que deseja excluir este evento?")) {
      fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&id=' + encodeURIComponent(currentEventData.id)
      })
      .then(response => response.text())
      .then(data => {
        alert(data);
        // Remove a linha da tabela (ou poderíamos recarregar a página)
        let rows = document.querySelectorAll("table tbody tr");
        rows.forEach(tr => {
          if (tr.cells[0].textContent.trim() == currentEventData.id) {
            tr.remove();
          }
        });
        modal.style.display = "none";
      })
      .catch(error => {
        alert("Erro ao excluir evento");
        console.error("Erro:", error);
      });
    }
  });

  // ================== 6) Função para abrir input file ao clicar imagem ==================
  function triggerImageInput() {
    editLogoInput.click();
  }
  editLogoInput.addEventListener("change", function() {
    if (this.files && this.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById("modalEventLogo").src = e.target.result;
      }
      reader.readAsDataURL(this.files[0]);
    }
  });
</script>
</body>
</html>
