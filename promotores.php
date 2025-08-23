<?php
session_start();
include_once('check_permissions.php');
if (!checkPermission("promotores")) {
    echo "Você não possui permissão para acessar esta página!";
    exit();
}

include('conexao.php');
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";

// Se houver mensagem armazenada na sessão (após redirecionamento), recupera-a e a limpa.
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// PROCESSA POST (cadastro, atualização, exclusão, reenvio)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- REENVIO DE CREDENCIAIS ---
    if (isset($_POST['resend_credentials'])) {
        $idPromotor = intval($_POST['id_promotor']);
        $stmt = $conn->prepare("SELECT nome, email FROM administradores WHERE id = ? AND is_promotor = 1");
        if ($stmt) {
            $stmt->bind_param("i", $idPromotor);
            $stmt->execute();
            $resultTemp = $stmt->get_result();
            if ($promotor = $resultTemp->fetch_assoc()) {
                // Gere um token (exemplo) e crie o link para redefinir a senha
                $token = md5($promotor['email'] . time());
                // Aqui, idealmente, você armazenaria o token no banco para verificação, se necessário.
                $linkRedefinicao = "https://ticketsync.com.br/redefinir.php?token=" . $token;
    
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.hostinger.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'kaua@ticketsync.com.br';
                    $mail->Password   = 'Aaku_2004@';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;
        
                    $mail->setFrom('kaua@ticketsync.com.br', 'Ticket Sync');
                    $mail->addAddress($promotor['email'], $promotor['nome']);
        
                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = "Reenvio de Credenciais - Redefina sua Senha";
        
                    $mail->Body = "
                    <html>
                    <head>
                      <meta charset='UTF-8'>
                      <title>Redefina sua Senha</title>
                    </head>
                    <body style='margin:0; padding:0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
                      <div style='max-width:600px; margin: 20px auto; background-color: #fff; border: 1px solid #ddd;'>
                        <div style='background-color: #002f6d; padding: 20px; text-align: center;'>
                          <img src='https://ticketsync.com.br/uploads/ticketsyhnklogo_branco.png' alt='Logo' style='max-width:200px; height:auto;' />
                        </div>
                        <div style='padding: 20px; color: #333;'>
                          <h2 style='color: #002f6d;'>Reenvio de Credenciais</h2>
                          <p>Este e-mail é um reenvio do link para redefinir sua senha.</p>
                          <p>Clique no botão abaixo para redefinir sua senha:</p>
                          <p style='text-align: center;'>
                            <a href='{$linkRedefinicao}' target='_blank' 
                               style='display: inline-block; padding: 10px 20px; background-color: #002f6d; color: #fff; text-decoration: none; border-radius: 4px;'>
                              Redefinir Senha
                            </a>
                          </p>
                          <p>Se você não solicitou a redefinição, ignore este e-mail.</p>
                          <p>Atenciosamente,<br>Equipe do Sistema</p>
                        </div>
                        <div style='background-color: #002f6d; padding: 10px; text-align: center; color: #fff; font-size: 12px;'>
                          © " . date('Y') . " Ticket Sync. Todos os direitos reservados.
                        </div>
                      </div>
                    </body>
                    </html>
                    ";
        
                    $mail->send();
                    $message = "Reenvio de credenciais enviado para {$promotor['email']}.";
                } catch (Exception $e) {
                    $message = "Reenvio de credenciais não pôde ser enviado. Erro: " . $mail->ErrorInfo;
                }
            } else {
                $message = "Promotor não encontrado para reenvio.";
            }
            $stmt->close();
        } else {
            $message = "Erro na preparação da query para reenvio.";
        }
    }
    // --- EXCLUSÃO DE PROMOTORES ---
    elseif (isset($_POST['delete_promotor'])) {
        $idPromotor = intval($_POST['id_promotor']);
        $stmtUpdate = $conn->prepare("UPDATE administradores SET promotor_id = NULL WHERE promotor_id = ?");
        if ($stmtUpdate) {
            $stmtUpdate->bind_param("i", $idPromotor);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
        $stmtDel = $conn->prepare("DELETE FROM administradores WHERE id = ? AND is_promotor = 1");
        if ($stmtDel) {
            $stmtDel->bind_param("i", $idPromotor);
            if ($stmtDel->execute()) {
                $message = "Promotor excluído com sucesso!";
            } else {
                $message = "Erro ao excluir promotor: " . $conn->error;
            }
            $stmtDel->close();
        } else {
            $message = "Erro na preparação da query de exclusão: " . $conn->error;
        }
    }
    // --- ATUALIZAÇÃO DE PROMOTORES ---
    elseif (isset($_POST['update_promotor'])) {
        $id = intval($_POST['id']);
        $nome = mysqli_real_escape_string($conn, $_POST['nome']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $telefone = mysqli_real_escape_string($conn, $_POST['telefone']);
        $foto = $_POST['foto_atual'];
        if (!empty($_FILES['foto']['name'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $target_file = $target_dir . uniqid('promotor_', true) . "." . $ext;
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                $foto = $target_file;
            }
        }
        $stmt = $conn->prepare("UPDATE administradores SET nome=?, email=?, telefone=?, foto=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssssi", $nome, $email, $telefone, $foto, $id);
            if ($stmt->execute()) {
                $message = "Promotor atualizado com sucesso!";
            } else {
                $message = "Erro ao atualizar: " . $conn->error;
            }
            $stmt->close();
        }
    }
    // --- CADASTRO DE PROMOTORES ---
    elseif (isset($_POST['register_promotor'])) {
        $nome     = mysqli_real_escape_string($conn, $_POST['nome']);
        $email    = mysqli_real_escape_string($conn, $_POST['email']);
        $telefone = mysqli_real_escape_string($conn, $_POST['telefone']);
        // A senha original será utilizada para o primeiro envio (não armazenada em texto plano)
        $senhaOriginal = mysqli_real_escape_string($conn, $_POST['senha']);
        $hashed   = password_hash($senhaOriginal, PASSWORD_DEFAULT);
        
        $foto = "";
        if (isset($_FILES['foto']) && $_FILES['foto']['name'] != "") {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0775, true);
            }
            $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $target_file = $target_dir . uniqid('promotor_', true) . "." . $ext;
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                $foto = $target_file;
            }
        }
        
        // Inserir o promotor (não armazenamos a senha original)
        $sql = "INSERT INTO administradores (nome, email, telefone, senha, foto, is_promotor, promotor_id) 
                VALUES (?, ?, ?, ?, ?, 1, NULL)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Erro na preparação da query: " . $conn->error);
        }
        $stmt->bind_param("sssss", $nome, $email, $telefone, $hashed, $foto);
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $sqlUpdate = "UPDATE administradores SET promotor_id = ? WHERE id = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            if (!$stmtUpdate) {
                die("Erro na preparação da query de atualização: " . $conn->error);
            }
            $stmtUpdate->bind_param("ii", $newId, $newId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            
            $message = "Promotor cadastrado com sucesso!";
        
            // ENVIA E-MAIL DE BOAS-VINDAS
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.hostinger.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'kaua@ticketsync.com.br';
                $mail->Password   = 'Aaku_2004@';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
        
                $mail->setFrom('contato@ticketsync.com.br', 'Ticket Sync');
                $mail->addAddress($email, $nome);
                
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = "Bem-vindo ao Sistema!";
                
                $linkAcesso = "https://ticketsync.com.br/login.php";
                $mail->Body = "
                <html>
                <head>
                  <meta charset='UTF-8'>
                  <title>Bem-vindo ao Sistema</title>
                </head>
                <body style='margin:0; padding:0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
                  <div style='max-width:600px; margin: 20px auto; background-color: #fff; border: 1px solid #ddd;'>
                    <div style='background-color: #002f6d; padding: 20px; text-align: center;'>
                      <img src='https://ticketsync.com.br/uploads/ticketsyhnklogo_branco.png' alt='Logo' style='max-width:200px; height:auto;' />
                    </div>
                    <div style='padding: 20px; color: #333;'>
                      <h2 style='color: #002f6d;'>Bem-vindo, {$nome}!</h2>
                      <p>Seu cadastro como promotor foi realizado com sucesso.</p>
                      <p>Você pode acessar o sistema utilizando as seguintes credenciais:</p>
                      <ul style='list-style: none; padding: 0;'>
                        <li><strong>Email:</strong> {$email}</li>
                        <li><strong>Senha:</strong> {$senhaOriginal}</li>
                      </ul>
                      <p style='text-align: center;'>
                        <a href='{$linkAcesso}' target='_blank' 
                          style='display: inline-block; padding: 10px 20px; background-color: #002f6d; color: #fff; text-decoration: none; border-radius: 4px;'>
                          Acessar o Sistema
                        </a>
                      </p>
                      <p>Atenciosamente,<br>Equipe do Sistema</p>
                    </div>
                    <div style='background-color: #002f6d; padding: 10px; text-align: center; color: #fff; font-size: 12px;'>
                      © " . date('Y') . " Ticket Sync. Todos os direitos reservados.
                    </div>
                  </div>
                </body>
                </html>
                ";
                
                $mail->send();
                $message .= " E um e-mail de boas-vindas foi enviado para {$email}.";
            } catch (Exception $e) {
                $message .= " Contudo, o e-mail não pôde ser enviado. Erro: " . $mail->ErrorInfo;
            }
        } else {
            $message = "Erro ao cadastrar promotor: " . $conn->error;
        }
        $stmt->close();
    }
    
    // PRG: Armazena a mensagem em sessão e redireciona
    $_SESSION['message'] = $message;
    header("Location: promotores.php");
    exit();
}

// Consulta os promotores cadastrados (no GET)
$sql = "SELECT * FROM administradores WHERE is_promotor = 1";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro de Promotores</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="css/promotores.css">
</head>
<body class="admin-page-body">
  <?php include('header_admin.php'); ?>
  <div class="admin-container">
    <div class="admin-welcome">
      <h2 class="admin-welcome-heading">Cadastro de Promotores</h2>
      <p>Utilize o formulário abaixo para cadastrar novos promotores.</p>
    </div>
    
    <!-- Mensagem de feedback -->
    <?php if (!empty($message)): ?>
      <div class="admin-message"><?php echo htmlspecialchars($message); ?></div>
    <?php elseif (isset($_GET['message']) && !empty($_GET['message'])): ?>
      <div class="admin-message"><?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>

    <!-- Formulário de cadastro de promotores -->
    <div class="promotor-form-container">
      <h3 class="promotor-form-title">Cadastrar Promotor</h3>
      <form action="promotores.php" method="post" enctype="multipart/form-data">
         <label for="nome" class="promotor-form-label">Nome:</label>
         <input type="text" id="nome" name="nome" class="promotor-form-input" required>
         
         <label for="email" class="promotor-form-label">Email:</label>
         <input type="email" id="email" name="email" class="promotor-form-input" required>
         
         <label for="telefone" class="promotor-form-label">Telefone:</label>
         <input type="text" id="telefone" name="telefone" class="promotor-form-input">
         
         <label for="senha" class="promotor-form-label">Senha:</label>
         <input type="password" id="senha" name="senha" class="promotor-form-input" required>
         
         <label for="foto" class="promotor-form-label">Foto (opcional):</label>
         <input type="file" id="foto" name="foto" class="promotor-form-input">
         
         <input type="hidden" name="register_promotor" value="1">
         <input type="submit" value="Cadastrar Promotor" class="promotor-form-submit">
      </form>
    </div>

    <!-- Lista de promotores cadastrados -->
    <div class="promotor-list-container">
      <h3 class="promotor-list-title">Promotores Cadastrados</h3>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="promotor-item" data-id="<?= $row['id'] ?>">
            <?php if (!empty($row['foto'])): ?>
              <img src="<?= htmlspecialchars($row['foto']) ?>" alt="Foto de <?= htmlspecialchars($row['nome']) ?>" class="promotor-item-pic">
            <?php endif; ?>
            <div class="promotor-item-info">
              <p><strong>Nome:</strong> <span class="editable nome"><?= htmlspecialchars($row['nome']) ?></span></p>
              <p><strong>Email:</strong> <span class="editable email"><?= htmlspecialchars($row['email']) ?></span></p>
              <p><strong>Telefone:</strong> <span class="editable telefone"><?= htmlspecialchars($row['telefone']) ?></span></p>
            </div>
            <div class="promotor-item-actions">
              <button class="edit-button" onclick="openEditModal(<?= $row['id'] ?>)">
                <i class="fas fa-edit"></i> Editar
              </button>
              <form action="promotores.php" method="post" onsubmit="return confirm('Tem certeza que deseja excluir este promotor?');" style="display:inline-block; margin:0 5px;">
                <input type="hidden" name="id_promotor" value="<?= $row['id'] ?>">
                <input type="hidden" name="delete_promotor" value="1">
                <button type="submit" class="promotor-delete-button">
                  <i class="fas fa-trash"></i> Excluir
                </button>
              </form>
              <form action="promotores.php" method="post" onsubmit="return confirm('Deseja reenviar as credenciais para este promotor?');" style="display:inline-block;">
                <input type="hidden" name="id_promotor" value="<?= $row['id'] ?>">
                <input type="hidden" name="resend_credentials" value="1">
                <button type="submit" class="promotor-resent-button">
                  <i class="fas fa-redo"></i> Reenviar
                </button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
          <p>Nenhum promotor cadastrado.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal de Edição -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" onclick="closeEditModal()">&times;</span>
      <h3>Editar Promotor</h3>
      <form id="editForm" method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" id="editId">
        <input type="hidden" name="foto_atual" id="fotoAtual">
        
        <div class="form-group">
          <label for="editNome">Nome:</label>
          <input type="text" id="editNome" name="nome" class="promotor-form-input" required>
        </div>
        
        <div class="form-group">
          <label for="editEmail">Email:</label>
          <input type="email" id="editEmail" name="email" class="promotor-form-input" required>
        </div>
        
        <div class="form-group">
          <label for="editTelefone">Telefone:</label>
          <input type="text" id="editTelefone" name="telefone" class="promotor-form-input">
        </div>
        
        <div class="form-group">
          <label for="editFoto">Nova Foto (opcional):</label>
          <input type="file" id="editFoto" name="foto" class="promotor-form-input">
          <img id="previewFoto" src="" style="max-width: 100px; margin-top: 10px;">
        </div>
    
        <input type="hidden" name="update_promotor" value="1">
        <button type="submit" class="promotor-form-submit">Salvar Alterações</button>
      </form>
    </div>
  </div>

  <script>
    function openEditModal(id) {
      fetch(`get_promotor.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
          document.getElementById('editId').value = data.id;
          document.getElementById('editNome').value = data.nome;
          document.getElementById('editEmail').value = data.email;
          document.getElementById('editTelefone').value = data.telefone;
          document.getElementById('fotoAtual').value = data.foto;
          document.getElementById('previewFoto').src = data.foto;
          document.getElementById('editModal').style.display = 'block';
        });
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    window.onclick = function(event) {
      const modal = document.getElementById('editModal');
      if (event.target === modal) {
        closeEditModal();
      }
    };

    document.getElementById('editFoto').addEventListener('change', function(e) {
      const reader = new FileReader();
      reader.onload = function() {
        document.getElementById('previewFoto').src = reader.result;
      }
      reader.readAsDataURL(e.target.files[0]);
    });
  </script>
  
  <!--
  Arquivo get_promotor.php (deve ser criado separadamente):
  <?php
    include('conexao.php');
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM administradores WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode($result->fetch_assoc());
  ?>
  -->
  
</body>
</html>
