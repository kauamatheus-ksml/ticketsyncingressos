<?php
// perfil.php
include('conexao.php');
session_start();

if (!isset($_SESSION['userid']) && !isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $foto = $_FILES['foto']['name'];
    $data_nascimento = isset($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;

    if (isset($_SESSION['userid'])) {
        $userid = $_SESSION['userid'];
        $table = "clientes";
    } elseif (isset($_SESSION['adminid'])) {
        $userid = $_SESSION['adminid'];
        $table = "administradores";
    }

    $updates = [];
    if ($nome) {
        $updates[] = "nome='$nome'";
    }
    if ($email) {
        $updates[] = "email='$email'";
    }
    if ($data_nascimento) {
        $updates[] = "data_nascimento='$data_nascimento'";
    }
    if ($_POST['senha']) {
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $updates[] = "senha='$senha'";
    }
    if ($foto) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0775, true);
        }
        $target_file = $target_dir . basename($_FILES["foto"]["name"]);
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
            $updates[] = "foto='$target_file'";
            $_SESSION['foto'] = $target_file;  // Atualize a variável de sessão
        }
    }

    if (!empty($updates)) {
        $sql = "UPDATE $table SET " . implode(", ", $updates) . " WHERE id='$userid'";
        if ($conn->query($sql) === TRUE) {
            // Atualiza as informações da sessão
            $_SESSION['nome'] = $nome;
            $_SESSION['email'] = $email;
            // Recarrega a página para mostrar as novas informações
            header("Location: perfil.php");
            exit();
        } else {
            echo "Erro ao atualizar informações: " . $conn->error;
        }
    }
}

// Consulta os dados do usuário
if (isset($_SESSION['userid'])) {
    $userid = $_SESSION['userid'];
    $table = "clientes";
} elseif (isset($_SESSION['adminid'])) {
    $userid = $_SESSION['adminid'];
    $table = "administradores";
}

$sql = "SELECT * FROM $table WHERE id='$userid'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meu Perfil</title>
  <!-- CSS embutido para esta página -->
  <link rel="stylesheet" href="css/perfil.css">
  <script>
    function openModal() {
      document.getElementById("modal").style.display = "block";
    }
    function closeModal() {
      document.getElementById("modal").style.display = "none";
    }
    // Fecha o modal ao clicar fora da área de conteúdo
    window.onclick = function(event) {
      var modal = document.getElementById("modal");
      if (event.target == modal) {
        closeModal();
      }
    }
  </script>
</head>
<body class="perfil-page-body">
  <?php 
    if (isset($_SESSION['adminid'])) {
      include('header_admin.php'); 
    } else {
      include('header_cliente.php');
    }
  ?>
  <div class="perfil-container">
    <div class="perfil-card">
      <h2 class="perfil-titulo">Meu Perfil</h2>
      <?php if (!empty($row['foto'])): ?>
        <img src="<?php echo $row['foto']; ?>" alt="Foto de Perfil" class="perfil-pic">
      <?php endif; ?>
      <p class="perfil-info"><strong>Nome:</strong> <?php echo $row['nome']; ?></p>
      <p class="perfil-info"><strong>Email:</strong> <?php echo $row['email']; ?></p>
      <?php if (isset($row['telefone'])): ?>
        <p class="perfil-info"><strong>Telefone:</strong> <?php echo $row['telefone']; ?></p>
      <?php endif; ?>
      <?php if (isset($row['data_nascimento'])): ?>
        <p class="perfil-info"><strong>Data de Nascimento:</strong> <?php echo date("d/m/Y", strtotime($row['data_nascimento'])); ?></p>
      <?php endif; ?>
      <button onclick="openModal()" class="perfil-botao-editar">Editar Meu Perfil</button>
    </div>
  </div>
  
  <!-- Modal para edição do perfil -->
  <div id="modal" class="modal">
    <div class="modal-content">
      <span class="close-button" onclick="closeModal()">&times;</span>
      <form action="perfil.php" method="post" enctype="multipart/form-data">
        <h2 class="perfil-formulario-titulo">Editar Perfil</h2>
        <label for="nome" class="perfil-formulario-label">Nome:</label>
        <input type="text" name="nome" value="<?php echo $row['nome']; ?>" class="perfil-formulario-input" required><br>
        
        <label for="email" class="perfil-formulario-label">Email:</label>
        <input type="email" name="email" value="<?php echo $row['email']; ?>" class="perfil-formulario-input" required><br>
        
        <?php if (isset($row['telefone'])): ?>
          <label for="telefone" class="perfil-formulario-label">Telefone:</label>
          <input type="text" name="telefone" value="<?php echo $row['telefone']; ?>" class="perfil-formulario-input"><br>
        <?php endif; ?>
        
        <?php if (isset($row['data_nascimento'])): ?>
          <label for="data_nascimento" class="perfil-formulario-label">Data de Nascimento:</label>
          <input type="date" name="data_nascimento" value="<?php echo $row['data_nascimento']; ?>" class="perfil-formulario-input"><br>
        <?php endif; ?>
        
        <label for="senha" class="perfil-formulario-label">Nova Senha:</label>
        <input type="password" name="senha" class="perfil-formulario-input"><br>
        
        <label for="foto" class="perfil-formulario-label">Foto (opcional):</label>
        <input type="file" name="foto" class="perfil-formulario-input"><br><br>
        
        <input type="hidden" name="update" value="1">
        <input type="submit" value="Atualizar Informações" class="perfil-formulario-submit">
      </form>
    </div>
  </div>
</body>
</html>
<?php
$conn->close();
?>
