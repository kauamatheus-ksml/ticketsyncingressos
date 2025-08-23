<?php
session_start();
require 'conexao.php';       // <-- Ajuste o caminho conforme seu projeto
require 'check_permissions.php'; // <-- Ajuste o caminho conforme seu projeto

// -------------------------------------------------------------------
// 1. VERIFICAÇÃO DE LOGIN E PERMISSÕES
// -------------------------------------------------------------------
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION['adminid'];

// Consulta os dados do administrador logado para confirmar se ele é promotor
$sqlAdmin = "SELECT * FROM administradores WHERE id = ?";
$stmtAdmin = $conn->prepare($sqlAdmin);
$stmtAdmin->bind_param("i", $adminId);
$stmtAdmin->execute();
$resultAdmin = $stmtAdmin->get_result();

if ($resultAdmin->num_rows == 0) {
    echo "Administrador não encontrado!";
    exit();
}

$adminData = $resultAdmin->fetch_assoc();

// Se não for promotor, bloqueia o acesso
if (!$adminData['is_promotor']) {
    echo "Você não tem permissão para cadastrar funcionários.";
    exit();
}

// -------------------------------------------------------------------
// 2. TRATAMENTO DE AÇÕES (CREATE / UPDATE / DELETE)
// -------------------------------------------------------------------
$mensagem = "";

// A) EXCLUSÃO DE FUNCIONÁRIO (via GET: ?action=delete&id=XX)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $deleteId = intval($_GET['id']);
    
    // Remove primeiro o vínculo na tabela funcionarios_promotores
    $sqlDelLink = "DELETE FROM funcionarios_promotores WHERE funcionario_id = ? AND promotor_id = ?";
    $stmtDelLink = $conn->prepare($sqlDelLink);
    $stmtDelLink->bind_param("ii", $deleteId, $adminData['id']);
    
    // Se conseguir remover o vínculo, então remove o registro da tabela 'funcionarios'
    if ($stmtDelLink->execute()) {
        $sqlDelFunc = "DELETE FROM funcionarios WHERE id = ?";
        $stmtDelFunc = $conn->prepare($sqlDelFunc);
        $stmtDelFunc->bind_param("i", $deleteId);
        if ($stmtDelFunc->execute()) {
            $mensagem = "Funcionário excluído com sucesso.";
        } else {
            $mensagem = "Erro ao excluir funcionário: " . $stmtDelFunc->error;
        }
        $stmtDelFunc->close();
    } else {
        $mensagem = "Erro ao desvincular o funcionário: " . $stmtDelLink->error;
    }
    $stmtDelLink->close();
}

// B) CRIAÇÃO DE FUNCIONÁRIO (via POST: action=create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $nomeFuncionario  = trim($_POST['nome_funcionario'] ?? '');
    $emailFuncionario = trim($_POST['email_funcionario'] ?? '');
    $senhaPadrao = password_hash("1234", PASSWORD_DEFAULT); // Senha padrão "1234"

    if (!empty($nomeFuncionario) && !empty($emailFuncionario)) {
        // Insere na tabela funcionarios
        $sqlInsert = "INSERT INTO funcionarios (nome, email, senha) VALUES (?, ?, ?)";
        $stmtInsert = $conn->prepare($sqlInsert);
        if ($stmtInsert) {
            $stmtInsert->bind_param("sss", $nomeFuncionario, $emailFuncionario, $senhaPadrao);
            if ($stmtInsert->execute()) {
                $funcionarioId = $stmtInsert->insert_id;
                // Vincula ao promotor (admin atual)
                $promotorId = $adminData['id'];
                $sqlLink = "INSERT INTO funcionarios_promotores (funcionario_id, promotor_id) VALUES (?, ?)";
                $stmtLink = $conn->prepare($sqlLink);
                if ($stmtLink) {
                    $stmtLink->bind_param("ii", $funcionarioId, $promotorId);
                    if ($stmtLink->execute()) {
                        $mensagem = "Funcionário cadastrado com sucesso! (Senha padrão: 1234)";
                    } else {
                        $mensagem = "Cadastrado, porém erro ao vincular promotor: " . $stmtLink->error;
                    }
                    $stmtLink->close();
                }
            } else {
                $mensagem = "Erro ao cadastrar funcionário: " . $stmtInsert->error;
            }
            $stmtInsert->close();
        }
    } else {
        $mensagem = "Preencha todos os campos para cadastrar.";
    }
}

// C) EDIÇÃO DE FUNCIONÁRIO (via POST: action=update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $editId    = intval($_POST['edit_id'] ?? 0);
    $editNome  = trim($_POST['edit_nome_funcionario'] ?? '');
    $editEmail = trim($_POST['edit_email_funcionario'] ?? '');

    if ($editId > 0 && !empty($editNome) && !empty($editEmail)) {
        // Atualiza a tabela funcionarios
        $sqlUpdate = "UPDATE funcionarios SET nome = ?, email = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("ssi", $editNome, $editEmail, $editId);
            if ($stmtUpdate->execute()) {
                $mensagem = "Dados do funcionário atualizados com sucesso.";
            } else {
                $mensagem = "Erro ao atualizar: " . $stmtUpdate->error;
            }
            $stmtUpdate->close();
        }
    } else {
        $mensagem = "Preencha os campos corretamente para editar.";
    }
}

// -------------------------------------------------------------------
// 3. CONSULTA FINAL DOS FUNCIONÁRIOS VINCULADOS
// -------------------------------------------------------------------
$sqlList = "SELECT f.id, f.nome, f.email, f.created_at 
            FROM funcionarios f
            INNER JOIN funcionarios_promotores fp ON f.id = fp.funcionario_id
            WHERE fp.promotor_id = ?
            ORDER BY f.created_at DESC";
$stmtList = $conn->prepare($sqlList);
$stmtList->bind_param("i", $adminData['id']);
$stmtList->execute();
$resultList = $stmtList->get_result();

include('header_admin.php'); // <-- Ajuste se necessário
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastrar Funcionários</title>
  <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
  <!-- CSS principal -->
  <link rel="stylesheet" href="css/cadastro_funcionarios.css">
</head>
<body class="staff-page-body">

  <!-- CONTAINER GERAL -->
  <div class="staff-container">
    <h2>Gerenciar Funcionários</h2>

    <!-- MENSAGEM DE RETORNO (CADASTRO, EDIÇÃO, EXCLUSÃO) -->
    <?php if (!empty($mensagem)): ?>
      <p class="staff-message"><?php echo htmlspecialchars($mensagem); ?></p>
    <?php endif; ?>

    <!-- BOTÃO PARA ABRIR MODAL DE CADASTRO -->
    <button class="open-create-modal-button" onclick="openCreateModal()">Cadastrar Funcionário</button>

    <hr>

    <h3>Funcionários Cadastrados</h3>
    <?php if($resultList->num_rows > 0): ?>
      <!-- TABELA RESPONSIVA SEM SCROLL HORIZONTAL -->
      <table class="staff-table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Data de Cadastro </th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $resultList->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['nome']); ?></td>
              <td><?php echo htmlspecialchars($row['email']); ?></td>
              <!-- Subtraindo 3h do horário armazenado (10800 seg) -->
              <td><?php echo date("d/m/Y H:i", strtotime($row['created_at']) - 10800); ?></td>
              <td>
                <!-- BOTÃO EDITAR abre modal de edição com dados (via JS) -->
                <button class="staff-edit-button" 
                  onclick="openEditModal(
                    <?php echo $row['id']; ?>,
                    '<?php echo htmlspecialchars($row['nome'], ENT_QUOTES); ?>',
                    '<?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?>'
                  )">
                  Editar
                </button>
                <!-- BOTÃO EXCLUIR (confirma e redireciona com action=delete) -->
                <button class="staff-delete-button"
                  onclick="if(confirm('Tem certeza que deseja excluir este funcionário?')) {
                    window.location.href='cadastrar_funcionarios.php?action=delete&id=<?php echo $row['id']; ?>';
                  }">
                  Excluir
                </button>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="staff-no-record">Nenhum funcionário cadastrado.</p>
    <?php endif; ?>

  </div> <!-- /staff-container -->

  <?php 
    $stmtList->close();
    $conn->close();
  ?>

  <!-- MODAL PARA CADASTRAR FUNCIONÁRIO -->
  <div id="createModalOverlay" class="modal-overlay">
    <div class="modal-content">
      <button class="close-modal-button" onclick="closeCreateModal()">×</button>
      <h2 class="modal-title">Cadastrar Funcionário</h2>
      <form method="POST" action="cadastrar_funcionarios.php">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
          <label class="staff-label">Nome do Funcionário:</label>
          <input type="text" name="nome_funcionario" class="staff-input" required>
        </div>
        <div class="form-group">
          <label class="staff-label">E-mail do Funcionário:</label>
          <input type="email" name="email_funcionario" class="staff-input" required>
        </div>
        <button type="submit" class="staff-button">Cadastrar</button>
      </form>
    </div>
  </div>

  <!-- MODAL PARA EDITAR FUNCIONÁRIO -->
  <div id="editModalOverlay" class="modal-overlay">
    <div class="modal-content">
      <button class="close-modal-button" onclick="closeEditModal()">×</button>
      <h2 class="modal-title">Editar Funcionário</h2>
      <form method="POST" action="cadastrar_funcionarios.php">
        <input type="hidden" name="action" value="update">
        <!-- Armazena o ID do funcionário para edição -->
        <input type="hidden" id="edit_id" name="edit_id">
        <div class="form-group">
          <label class="staff-label">Nome do Funcionário:</label>
          <input type="text" id="edit_nome_funcionario" name="edit_nome_funcionario" class="staff-input" required>
        </div>
        <div class="form-group">
          <label class="staff-label">E-mail do Funcionário:</label>
          <input type="email" id="edit_email_funcionario" name="edit_email_funcionario" class="staff-input" required>
        </div>
        <button type="submit" class="staff-button">Salvar Alterações</button>
      </form>
    </div>
  </div>

  <!-- SCRIPTS JAVASCRIPT PARA CONTROLE DOS MODAIS -->
  <script>
    // FUNÇÃO PARA ABRIR MODAL DE CRIAÇÃO
    function openCreateModal() {
      document.getElementById('createModalOverlay').style.display = 'flex';
    }
    // FUNÇÃO PARA FECHAR MODAL DE CRIAÇÃO
    function closeCreateModal() {
      document.getElementById('createModalOverlay').style.display = 'none';
    }

    // FUNÇÃO PARA ABRIR MODAL DE EDIÇÃO, RECEBENDO OS DADOS DO FUNCIONÁRIO
    function openEditModal(id, nome, email) {
      // Preenche os campos do formulário de edição
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_nome_funcionario').value = nome;
      document.getElementById('edit_email_funcionario').value = email;
      // Exibe o modal
      document.getElementById('editModalOverlay').style.display = 'flex';
    }
    // FUNÇÃO PARA FECHAR MODAL DE EDIÇÃO
    function closeEditModal() {
      document.getElementById('editModalOverlay').style.display = 'none';
    }

    // Fecha os modais se o usuário clicar fora do conteúdo
    window.addEventListener('click', function(e) {
      const createModal = document.getElementById('createModalOverlay');
      const editModal   = document.getElementById('editModalOverlay');
      if (e.target === createModal) {
        closeCreateModal();
      }
      if (e.target === editModal) {
        closeEditModal();
      }
    });

    // Fecha modais no ESC
    window.addEventListener('keydown', function(e) {
      if (e.key === "Escape") {
        closeCreateModal();
        closeEditModal();
      }
    });
  </script>
</body>
</html>
