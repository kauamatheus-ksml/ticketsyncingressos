<?php
// ingressos.php

// Inclui o arquivo que inicia a sessão e carrega as permissões
include_once('check_permissions.php');

// Verifica se o usuário tem acesso à página "ingressos"
if (!checkPermission("ingressos")) {
    echo "Você não possui permissão para acessar esta página!";
    exit();
}

include('conexao.php');

// A sessão já foi iniciada em check_permissions.php
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

// Configurações de mensagens (usando sessão para feedback)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
} else {
    $message = isset($_GET['message']) ? $_GET['message'] : "";
}

// Recupera o ID do promotor (administrador) logado
$promotorId = $_SESSION['adminid'];

/* ============================
   Processamento do formulário
   ============================ */

// Cadastro de novo ingresso
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    // Recebe e sanitiza os dados
    $evento_id = filter_input(INPUT_POST, 'evento_id', FILTER_VALIDATE_INT);
    $tipo_ingresso = filter_input(INPUT_POST, 'tipo_ingresso', FILTER_SANITIZE_STRING);
    $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
    
    // Se o usuário optou por criar um novo tipo, pega o valor do campo adicional
    if ($tipo_ingresso === "novo") {
        $novoTipo = filter_input(INPUT_POST, 'novo_tipo_ingresso', FILTER_SANITIZE_STRING);
        if (!$novoTipo) {
            $_SESSION['message'] = "Informe o novo tipo de ingresso.";
            header("Location: ingressos.php");
            exit();
        }
        $tipo_ingresso = $novoTipo;
    }
    
    // Validação básica
    if (!$evento_id || !$tipo_ingresso || $preco === false || $preco <= 0 || $quantidade === false || $quantidade < 0) {
        $_SESSION['message'] = "Dados inválidos!";
        header("Location: ingressos.php");
        exit();
    }
    
    try {
        // Verifica se o evento pertence ao promotor logado e se o evento está no futuro
        $stmt = $conn->prepare("SELECT data_inicio FROM eventos WHERE id = ? AND promotor_id = ?");
        if (!$stmt) {
            throw new Exception("Erro na query de verificação: " . $conn->error);
        }
        $stmt->bind_param("ii", $evento_id, $promotorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Evento não encontrado ou não pertence a você!");
        }
        
        $row = $result->fetch_assoc();
        if (strtotime($row['data_inicio']) < time()) {
            throw new Exception("Não é possível cadastrar ingressos para eventos passados!");
        }
        
        // Verifica ingresso duplicado
        $stmt = $conn->prepare("SELECT id FROM ingressos WHERE evento_id = ? AND tipo_ingresso = ? AND preco = ?");
        if (!$stmt) {
            throw new Exception("Erro na query de duplicidade: " . $conn->error);
        }
        $stmt->bind_param("isd", $evento_id, $tipo_ingresso, $preco);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Ingresso já cadastrado para este evento!");
        }
        
        // Insere novo ingresso
        $stmt = $conn->prepare("INSERT INTO ingressos (evento_id, tipo_ingresso, preco, quantidade, promotor_id) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Erro na preparação da query: " . $conn->error);
        }
        $stmt->bind_param("isdii", $evento_id, $tipo_ingresso, $preco, $quantidade, $promotorId);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao cadastrar ingresso: " . $stmt->error);
        }
        
        $_SESSION['message'] = "Ingresso cadastrado com sucesso!";
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
    }
    
    header("Location: ingressos.php");
    exit();
}

// Atualização de ingresso (edição)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $evento_id = filter_input(INPUT_POST, 'evento_id', FILTER_VALIDATE_INT);
    $tipo_ingresso = filter_input(INPUT_POST, 'tipo_ingresso', FILTER_SANITIZE_STRING);
    $preco = filter_input(INPUT_POST, 'preco', FILTER_VALIDATE_FLOAT);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
    
    if ($tipo_ingresso === "novo") {
        $novoTipo = filter_input(INPUT_POST, 'novo_tipo_ingresso', FILTER_SANITIZE_STRING);
        if (!$novoTipo) {
            $_SESSION['message'] = "Informe o novo tipo de ingresso para atualização.";
            header("Location: ingressos.php");
            exit();
        }
        $tipo_ingresso = $novoTipo;
    }
    
    if (!$ticket_id || !$evento_id || !$tipo_ingresso || $preco === false || $preco <= 0 || $quantidade === false || $quantidade < 0) {
        $_SESSION['message'] = "Dados inválidos para atualização!";
        header("Location: ingressos.php");
        exit();
    }
    
    try {
        // Verifica se o evento pertence ao promotor e se está no futuro
        $stmt = $conn->prepare("SELECT data_inicio FROM eventos WHERE id = ? AND promotor_id = ?");
        if (!$stmt) {
            throw new Exception("Erro na query de verificação: " . $conn->error);
        }
        $stmt->bind_param("ii", $evento_id, $promotorId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Evento não encontrado ou não pertence a você!");
        }
        $row = $result->fetch_assoc();
        if (strtotime($row['data_inicio']) < time()) {
            throw new Exception("Não é possível atualizar ingressos para eventos passados!");
        }
        
        // Verifica ingresso duplicado (excluindo o ingresso que está sendo editado)
        $stmt = $conn->prepare("SELECT id FROM ingressos WHERE evento_id = ? AND tipo_ingresso = ? AND preco = ? AND id <> ?");
        if (!$stmt) {
            throw new Exception("Erro na query de duplicidade: " . $conn->error);
        }
        $stmt->bind_param("isdi", $evento_id, $tipo_ingresso, $preco, $ticket_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Já existe um ingresso com estes dados para este evento!");
        }
        
        // Atualiza o ingresso
        $stmt = $conn->prepare("UPDATE ingressos SET evento_id = ?, tipo_ingresso = ?, preco = ?, quantidade = ? WHERE id = ? AND promotor_id = ?");
        if (!$stmt) {
            throw new Exception("Erro na preparação da query de atualização: " . $conn->error);
        }
        $stmt->bind_param("isdiii", $evento_id, $tipo_ingresso, $preco, $quantidade, $ticket_id, $promotorId);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao atualizar ingresso: " . $stmt->error);
        }
        
        $_SESSION['message'] = "Ingresso atualizado com sucesso!";
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
    }
    
    header("Location: ingressos.php");
    exit();
}

// Bloco para Autorizar / Desautorizar ingresso
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_autorizacao'])) {
    $ingresso_id = filter_input(INPUT_POST, 'ingresso_id', FILTER_VALIDATE_INT);
    if (!$ingresso_id) {
        $_SESSION['message'] = "ID de ingresso inválido!";
        header("Location: ingressos.php");
        exit();
    }
    // Consulta o estado atual de 'liberado'
    $stmt = $conn->prepare("SELECT liberado FROM ingressos WHERE id = ? AND promotor_id = ?");
    if (!$stmt) {
        die("Erro na query: " . $conn->error);
    }
    $stmt->bind_param("ii", $ingresso_id, $promotorId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Ingresso não encontrado!";
        header("Location: ingressos.php");
        exit();
    }
    $row = $result->fetch_assoc();
    $current_state = $row['liberado'];
    $new_state = ($current_state == 1) ? 0 : 1;
    $stmt->close();
    
    // Atualiza o campo 'liberado'
    $stmt = $conn->prepare("UPDATE ingressos SET liberado = ? WHERE id = ? AND promotor_id = ?");
    if (!$stmt) {
        die("Erro na query de atualização: " . $conn->error);
    }
    $stmt->bind_param("iii", $new_state, $ingresso_id, $promotorId);
    if ($stmt->execute()) {
        $_SESSION['message'] = ($new_state == 1)
            ? "Ingresso autorizado com sucesso!"
            : "Ingresso desautorizado com sucesso!";
    } else {
        $_SESSION['message'] = "Erro ao atualizar autorização: " . $stmt->error;
    }
    $stmt->close();
    header("Location: ingressos.php");
    exit();
}

// Consulta eventos do promotor – para o select de cadastro
// Agora removemos a condição de data para puxar TODOS os eventos do promotor
$eventos = [];
$stmt = $conn->prepare("
    SELECT id, nome, data_inicio, hora_inicio, local, atracoes, logo
    FROM eventos 
    WHERE promotor_id = ?
    ORDER BY data_inicio ASC
");
if (!$stmt) {
    die("Erro ao preparar consulta de eventos: " . $conn->error);
}
$stmt->bind_param("i", $promotorId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $eventos[] = $row;
}
$stmt->close();

// Consulta ingressos dos eventos do promotor
$ingressos = [];
$stmt = $conn->prepare("
    SELECT i.*, 
           e.nome AS evento_nome, 
           e.data_inicio, 
           e.hora_inicio,
           e.local, 
           e.atracoes, 
           e.logo
    FROM ingressos i
    JOIN eventos e ON i.evento_id = e.id
    WHERE i.promotor_id = ?
    ORDER BY e.data_inicio ASC
");
if (!$stmt) {
    die("Erro ao preparar consulta de ingressos: " . $conn->error);
}
$stmt->bind_param("i", $promotorId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $ingressos[] = $row;
}
$stmt->close();

$current_date = date("Y-m-d");
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
    <title>Gestão de Ingressos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/ingressos.css">
</head>
<body>
    <?php include('header_admin.php'); ?>
    <div class="container">
        <h1><i class="fas fa-ticket-alt"></i> Gerenciamento de Ingressos</h1>
        
        <?php if ($message): ?>
            <div class="alert <?= (strpos($message, 'sucesso') !== false) ? 'alert-success' : 'alert-error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <button class="btn btn-primary" onclick="toggleModal()">
            <i class="fas fa-plus"></i> Novo Ingresso
        </button>
        
        <!-- Modal de Cadastro -->
        <div id="cadastroModal" class="modal">
            <div class="modal-content card">
                <h2><i class="fas fa-ticket-alt"></i> Cadastrar Novo Ingresso</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Evento:</label>
                        <select id="evento_id" name="evento_id" class="form-control" onchange="updateEventDetails(this)" required>
                            <?php foreach ($eventos as $evento): ?>
                                <option value="<?= $evento['id'] ?>"
                                    data-logo="<?= htmlspecialchars($evento['logo']) ?>"
                                    data-detalhes="<?= htmlspecialchars(json_encode($evento)) ?>">
                                    <?= htmlspecialchars($evento['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="preview-evento" id="eventoPreview">
                        <img id="eventoLogo" src="" alt="Logo do Evento">
                        <div>
                            <h3 id="eventoNome"></h3>
                            <p id="eventoData"></p>
                            <p id="eventoLocal"></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Ingresso:</label>
                        <select name="tipo_ingresso" id="tipo_ingresso" class="form-control" required>
                            <option value="ingresso antecipado">Antecipado</option>
                            <option value="ingresso de aniversariante">Aniversariante</option>
                            <option value="ingresso de convidado de aniversariante">Convidado</option>
                            <option value="novo">Criar novo tipo</option>
                        </select>
                    </div>
                    <!-- Campo adicional para novo tipo (oculto por padrão) -->
                    <div class="form-group" id="novoTipoContainer" style="display: none;">
                        <label for="novo_tipo_ingresso">Novo Tipo de Ingresso:</label>
                        <input type="text" id="novo_tipo_ingresso" name="novo_tipo_ingresso" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Preço (R$):</label>
                        <input type="number" name="preco" step="0.01" min="0" class="form-control" placeholder="Ex: 50.00" required>
                    </div>
                    <div class="form-group">
                        <label>Quantidade:</label>
                        <input type="number" name="quantidade" step="1" min="0" class="form-control" placeholder="Ex: 10" required>
                    </div>
                    
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <button type="button" class="btn btn-danger" onclick="toggleModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Modal de Edição -->
        <div id="edicaoModal" class="modal">
            <div class="modal-content card">
                <h2><i class="fas fa-edit"></i> Editar Ingresso</h2>
                <form method="POST">
                    <input type="hidden" name="ticket_id" id="edit_ticket_id" value="">
                    
                    <div class="form-group">
                        <label>Evento:</label>
                        <select id="edit_evento_id" name="evento_id" class="form-control" onchange="updateEditEventDetails(this)" required>
                            <?php foreach ($eventos as $evento): ?>
                                <option value="<?= $evento['id'] ?>"
                                    data-logo="<?= htmlspecialchars($evento['logo']) ?>"
                                    data-detalhes="<?= htmlspecialchars(json_encode($evento)) ?>">
                                    <?= htmlspecialchars($evento['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="preview-evento" id="edit_eventoPreview">
                        <img id="edit_eventoLogo" src="" alt="Logo do Evento">
                        <div>
                            <h3 id="edit_eventoNome"></h3>
                            <p id="edit_eventoData"></p>
                            <p id="edit_eventoLocal"></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de Ingresso:</label>
                        <select name="tipo_ingresso" id="edit_tipo_ingresso" class="form-control" required>
                            <option value="ingresso antecipado">Antecipado</option>
                            <option value="ingresso de aniversariante">Aniversariante</option>
                            <option value="ingresso de convidado de aniversariante">Convidado</option>
                            <option value="novo">Criar novo tipo</option>
                        </select>
                    </div>
                    <div class="form-group" id="editNovoTipoContainer" style="display: none;">
                        <label for="edit_novo_tipo_ingresso">Novo Tipo de Ingresso:</label>
                        <input type="text" id="edit_novo_tipo_ingresso" name="novo_tipo_ingresso" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Preço (R$):</label>
                        <input type="number" name="preco" id="edit_preco" step="0.01" min="0" class="form-control" placeholder="Ex: 50.00" required>
                    </div>
                    <div class="form-group">
                        <label>Quantidade:</label>
                        <input type="number" name="quantidade" id="edit_quantidade" step="1" min="0" class="form-control" placeholder="Ex: 10" required>
                    </div>
                    
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="fas fa-save"></i> Atualizar
                    </button>
                    <button type="button" class="btn btn-danger" onclick="toggleEditModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Lista de Ingressos -->
        <div class="grid" style="margin-top: 30px;">
            <?php foreach ($ingressos as $ingresso): ?>
                <div class="card ticket-card" <?= $ingresso['liberado'] == 1 ? 'data-liberado="1"' : '' ?>>
                    <div class="preview-evento">
                        <img src="<?= htmlspecialchars($ingresso['logo']) ?>" alt="<?= htmlspecialchars($ingresso['evento_nome']) ?>">
                        <div>
                            <h3><?= htmlspecialchars($ingresso['evento_nome']) ?></h3>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <p><strong>Tipo:</strong> <?= htmlspecialchars($ingresso['tipo_ingresso']) ?></p>
                        <p><strong>Preço:</strong> R$ <?= number_format($ingresso['preco'], 2, ',', '.') ?></p>
                        <p><strong>Quantidade:</strong> <?= htmlspecialchars($ingresso['quantidade']) ?></p>
                    </div>
                    <button onclick="openEditModal(this)" class="btn btn-primary" data-ticket='<?= htmlspecialchars(json_encode($ingresso)) ?>'>
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button onclick="confirmarExclusao(<?= $ingresso['id'] ?>)" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Excluir
                    </button>
                    <!-- Botão para autorizar/desautorizar ingresso -->
                    <?php
                        if ($ingresso['liberado'] == 1) {
                            $btnClass = "btn btn-danger1";
                            $btnText = '<i class="fas fa-times"></i> Desautorizar';
                        } else {
                            $btnClass = "btn btn-success";
                            $btnText = '<i class="fas fa-check"></i> Autorizar';
                        }
                    ?>
                    <form action="ingressos.php" method="post" style="display:inline; margin:0;">
                        <input type="hidden" name="ingresso_id" value="<?= $ingresso['id'] ?>">
                        <button type="submit" name="toggle_autorizacao" class="<?= $btnClass ?>">
                            <?= $btnText ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Função para exibir/ocultar o modal de cadastro
        function toggleModal() {
            const modal = document.getElementById('cadastroModal');
            modal.style.display = (modal.style.display === 'flex') ? 'none' : 'flex';
        }
        // Atualiza a pré-visualização do evento selecionado no modal de cadastro
        function updateEventDetails(select) {
            const option = select.options[select.selectedIndex];
            const detalhes = JSON.parse(option.dataset.detalhes);
            
            document.getElementById('eventoNome').textContent = detalhes.nome;
            document.getElementById('eventoData').textContent = detalhes.data_inicio + " às " + detalhes.hora_inicio;
            document.getElementById('eventoLocal').textContent = detalhes.local;
            document.getElementById('eventoLogo').src = option.dataset.logo;
        }
        // Monitora a mudança no select de tipo de ingresso do modal de cadastro
        document.getElementById('tipo_ingresso').addEventListener('change', function() {
            const novoTipoContainer = document.getElementById('novoTipoContainer');
            if (this.value === "novo") {
                novoTipoContainer.style.display = "block";
            } else {
                novoTipoContainer.style.display = "none";
            }
        });
        // Confirmação de exclusão
        function confirmarExclusao(id) {
            if (confirm('Tem certeza que deseja excluir este ingresso?')) {
                window.location.href = `excluir_ingresso.php?id=${id}`;
            }
        }
        
        // Funções para o Modal de Edição
        function toggleEditModal() {
            const modal = document.getElementById('edicaoModal');
            modal.style.display = (modal.style.display === 'flex') ? 'none' : 'flex';
        }
        function openEditModal(button) {
            const ticketData = button.getAttribute('data-ticket');
            const ticket = JSON.parse(ticketData);
            
            document.getElementById('edit_ticket_id').value = ticket.id;
            
            const eventSelect = document.getElementById('edit_evento_id');
            eventSelect.value = ticket.evento_id;
            updateEditEventDetails(eventSelect);
            
            const tipoSelect = document.getElementById('edit_tipo_ingresso');
            const options = Array.from(tipoSelect.options).map(opt => opt.value);
            if (options.includes(ticket.tipo_ingresso)) {
                tipoSelect.value = ticket.tipo_ingresso;
                document.getElementById('editNovoTipoContainer').style.display = "none";
            } else {
                tipoSelect.value = "novo";
                document.getElementById('editNovoTipoContainer').style.display = "block";
                document.getElementById('edit_novo_tipo_ingresso').value = ticket.tipo_ingresso;
            }
            document.getElementById('edit_preco').value = ticket.preco;
            document.getElementById('edit_quantidade').value = ticket.quantidade;
            
            toggleEditModal();
        }
        function updateEditEventDetails(select) {
            const option = select.options[select.selectedIndex];
            const detalhes = JSON.parse(option.dataset.detalhes);
            
            document.getElementById('edit_eventoNome').textContent = detalhes.nome;
            document.getElementById('edit_eventoData').textContent = detalhes.data_inicio + " às " + detalhes.hora_inicio;
            document.getElementById('edit_eventoLocal').textContent = detalhes.local;
            document.getElementById('edit_eventoLogo').src = option.dataset.logo;
        }
        // Monitora a mudança no select de tipo de ingresso do modal de edição
        document.getElementById('edit_tipo_ingresso').addEventListener('change', function() {
            const novoTipoContainer = document.getElementById('editNovoTipoContainer');
            if (this.value === "novo") {
                novoTipoContainer.style.display = "block";
            } else {
                novoTipoContainer.style.display = "none";
            }
        });
        // Fecha os modais ao clicar fora deles
        window.onclick = function(event) {
            const modalCadastro = document.getElementById('cadastroModal');
            const modalEdicao = document.getElementById('edicaoModal');
            if (event.target === modalCadastro) {
                modalCadastro.style.display = 'none';
            }
            if (event.target === modalEdicao) {
                modalEdicao.style.display = 'none';
            }
        }
        // Inicializa a pré-visualização do evento no modal de cadastro com o primeiro evento disponível
        document.addEventListener('DOMContentLoaded', () => {
            const select = document.getElementById('evento_id');
            if (select && select.options.length > 0) {
                updateEventDetails(select);
            }
        });
    </script>
</body>
</html>
