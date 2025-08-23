<?php
// perfil.php - Página de perfil completa e moderna (LGPD)
session_start();

// Configurações do banco de dados consolidadas
$servername = "p:srv406.hstgr.io";
$username   = "u383946504_ticketsync";
$password   = "Aaku_2004@";
$dbname     = "u383946504_ticketsync";

// Cria a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Verifica se o usuário está logado
if (!isset($_SESSION['userid']) && !isset($_SESSION['adminid'])) {
    header("Location: login.php?redirect=perfil.php");
    exit();
}

$message = "";
$message_type = "";

// Processar formulário de atualização
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Atualização de dados pessoais
    if (isset($_POST['update_profile'])) {
        $nome = trim($_POST['nome']);
        $sobrenome = trim($_POST['sobrenome']);
        $email = trim($_POST['email']);
        $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
        $celular = trim($_POST['celular']);
        $data_nascimento = $_POST['data_nascimento'] ?? null;
        $sexo = $_POST['sexo'] ?? null;
        $nacionalidade = $_POST['nacionalidade'] ?? 'brasileira';
        
        $userid = $_SESSION['userid'] ?? $_SESSION['adminid'];
        $table = isset($_SESSION['userid']) ? 'clientes' : 'administradores';
        
        // Validações
        $errors = [];
        
        if (empty($nome)) $errors[] = "Nome é obrigatório";
        if (empty($email)) $errors[] = "Email é obrigatório";
        if (empty($celular)) $errors[] = "Celular é obrigatório";
        
        // Verificar email único
        if (!empty($email)) {
            $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $userid);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Este email já está sendo usado por outro usuário";
            }
        }
        
        // Verificar CPF único (se fornecido)
        if (!empty($cpf)) {
            if (strlen($cpf) != 11) {
                $errors[] = "CPF deve ter 11 dígitos";
            } else {
                $stmt = $conn->prepare("SELECT id FROM $table WHERE cpf = ? AND id != ?");
                $stmt->bind_param("si", $cpf, $userid);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $errors[] = "Este CPF já está cadastrado";
                }
            }
        }
        
        if (empty($errors)) {
            $updates = [
                "nome = ?",
                "email = ?", 
                "celular = ?",
                "updated_at = NOW()"
            ];
            $params = [$nome, $email, $celular];
            $types = "sss";
            
            // Campos condicionais
            if (!empty($sobrenome)) {
                $updates[] = "sobrenome = ?";
                $params[] = $sobrenome;
                $types .= "s";
            }
            
            if (!empty($cpf)) {
                $updates[] = "cpf = ?";
                $params[] = $cpf;
                $types .= "s";
            }
            
            if (!empty($data_nascimento)) {
                $updates[] = "data_nascimento = ?";
                $params[] = $data_nascimento;
                $types .= "s";
            }
            
            if (!empty($sexo)) {
                $updates[] = "sexo = ?";
                $params[] = $sexo;
                $types .= "s";
            }
            
            if (!empty($nacionalidade)) {
                $updates[] = "nacionalidade = ?";
                $params[] = $nacionalidade;
                $types .= "s";
            }
            
            $params[] = $userid;
            $types .= "i";
            
            $sql = "UPDATE $table SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $_SESSION['nome'] = $nome;
                $_SESSION['email'] = $email;
                $message = "Dados atualizados com sucesso!";
                $message_type = "success";
            } else {
                $message = "Erro ao atualizar dados: " . $conn->error;
                $message_type = "error";
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = "error";
        }
    }
    
    // Atualização de senha
    elseif (isset($_POST['update_password'])) {
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        $userid = $_SESSION['userid'] ?? $_SESSION['adminid'];
        $table = isset($_SESSION['userid']) ? 'clientes' : 'administradores';
        
        $errors = [];
        
        if (empty($senha_atual)) $errors[] = "Senha atual é obrigatória";
        if (empty($nova_senha)) $errors[] = "Nova senha é obrigatória";
        if ($nova_senha !== $confirmar_senha) $errors[] = "Confirmação de senha não confere";
        if (strlen($nova_senha) < 6) $errors[] = "Nova senha deve ter pelo menos 6 caracteres";
        
        if (empty($errors)) {
            // Verificar senha atual
            $stmt = $conn->prepare("SELECT senha FROM $table WHERE id = ?");
            $stmt->bind_param("i", $userid);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (password_verify($senha_atual, $user['senha'])) {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE $table SET senha = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $senha_hash, $userid);
                
                if ($stmt->execute()) {
                    $message = "Senha alterada com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Erro ao alterar senha";
                    $message_type = "error";
                }
            } else {
                $message = "Senha atual incorreta";
                $message_type = "error";
            }
        } else {
            $message = implode("<br>", $errors);
            $message_type = "error";
        }
    }
    
    // Atualização de preferências de comunicação
    elseif (isset($_POST['update_preferences'])) {
        $whatsapp_optin = isset($_POST['whatsapp_optin']) ? 1 : 0;
        $sms_optin = isset($_POST['sms_optin']) ? 1 : 0;
        $newsletter_optin = isset($_POST['newsletter_optin']) ? 1 : 0;
        
        $userid = $_SESSION['userid'] ?? $_SESSION['adminid'];
        $table = isset($_SESSION['userid']) ? 'clientes' : 'administradores';
        
        $stmt = $conn->prepare("UPDATE $table SET whatsapp_optin = ?, sms_optin = ?, newsletter_optin = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("iiii", $whatsapp_optin, $sms_optin, $newsletter_optin, $userid);
        
        if ($stmt->execute()) {
            $message = "Preferências atualizadas com sucesso!";
            $message_type = "success";
        } else {
            $message = "Erro ao atualizar preferências";
            $message_type = "error";
        }
    }
    
    // Upload de foto
    elseif (isset($_POST['update_photo'])) {
        $userid = $_SESSION['userid'] ?? $_SESSION['adminid'];
        $table = isset($_SESSION['userid']) ? 'clientes' : 'administradores';
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['foto']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                if ($_FILES['foto']['size'] <= 2 * 1024 * 1024) { // 2MB
                    $target_dir = "uploads/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0775, true);
                    }
                    
                    $new_filename = uniqid() . "." . $ext;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
                        $stmt = $conn->prepare("UPDATE $table SET foto = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->bind_param("si", $target_file, $userid);
                        
                        if ($stmt->execute()) {
                            $message = "Foto atualizada com sucesso!";
                            $message_type = "success";
                        } else {
                            $message = "Erro ao salvar foto no banco de dados";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Erro ao fazer upload da foto";
                        $message_type = "error";
                    }
                } else {
                    $message = "Arquivo muito grande. Máximo 2MB";
                    $message_type = "error";
                }
            } else {
                $message = "Formato não permitido. Use JPG, PNG ou GIF";
                $message_type = "error";
            }
        } else {
            $message = "Nenhuma foto selecionada";
            $message_type = "error";
        }
    }
    
    // Solicitação de dados (LGPD)
    elseif (isset($_POST['request_data'])) {
        $userid = $_SESSION['userid'] ?? $_SESSION['adminid'];
        $table = isset($_SESSION['userid']) ? 'clientes' : 'administradores';
        
        // Gerar relatório de dados
        $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        
        // Remover dados sensíveis
        unset($user_data['senha']);
        unset($user_data['token_red']);
        
        $json_data = json_encode($user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="meus_dados_ticketsync.json"');
        echo $json_data;
        exit();
    }
    
    // Solicitação de exclusão de conta (LGPD)
    elseif (isset($_POST['request_deletion'])) {
        $userid = $_SESSION['userid'] ?? $_SESSION['adminid'];
        $table = isset($_SESSION['userid']) ? 'clientes' : 'administradores';
        
        // Marcar conta para exclusão (30 dias)
        $stmt = $conn->prepare("UPDATE $table SET status = 'suspenso', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $userid);
        
        if ($stmt->execute()) {
            $message = "Solicitação de exclusão registrada. Sua conta será excluída em 30 dias. Entre em contato conosco para cancelar.";
            $message_type = "warning";
        } else {
            $message = "Erro ao processar solicitação";
            $message_type = "error";
        }
    }
}

// Buscar dados do usuário
$userid = $_SESSION['userid'] ?? $_SESSION['adminid'];
$table = isset($_SESSION['userid']) ? 'clientes' : 'administradores';

$stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meu Perfil - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/index.css">
  <link rel="stylesheet" href="assets/css/perfil.css">
</head>
<body>
  <!-- HEADER -->
  <header class="modern-header">
    <div class="modern-header-container">
      <!-- Logo -->
      <div class="header-logo">
        <a href="index.php">
          <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
        </a>
      </div>

      <!-- Saudação -->
      <div class="header-greeting">
        <span>Olá, <?php echo htmlspecialchars($user['nome']); ?>!</span>
      </div>

      <!-- Barra de Pesquisa -->
      <div class="header-search">
        <div class="search-container">
          <input type="text" id="headerSearchInput" placeholder="Pesquisar eventos...">
          <i class="fas fa-search search-icon"></i>
        </div>
      </div>

      <!-- Menu Perfil -->
      <div class="header-profile">
        <div class="profile-dropdown">
          <button class="profile-btn" id="profileBtn">
            <i class="fas fa-user-circle"></i>
          </button>
          <div class="dropdown-menu" id="dropdownMenu">
            <a href="perfil.php" class="dropdown-item active">
              <i class="fas fa-user"></i> Meu Perfil
            </a>
            <a href="meus_ingressos.php" class="dropdown-item">
              <i class="fas fa-ticket-alt"></i> Meus Ingressos
            </a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="dropdown-item logout">
              <i class="fas fa-sign-out-alt"></i> Sair
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Container Principal -->
  <div class="ts-profile-wrapper">
    <div class="ts-profile-container">
      
      <!-- Header do Perfil -->
      <div class="ts-profile-header">
        <div class="ts-profile-avatar">
          <?php if (!empty($user['foto'])): ?>
            <img src="<?php echo htmlspecialchars($user['foto']); ?>" alt="Foto de Perfil" class="ts-avatar-img">
          <?php else: ?>
            <div class="ts-avatar-placeholder">
              <i class="fas fa-user"></i>
            </div>
          <?php endif; ?>
          <button class="ts-avatar-edit" onclick="openModal('photoModal')">
            <i class="fas fa-camera"></i>
          </button>
        </div>
        <div class="ts-profile-info">
          <h1 class="ts-profile-name"><?php echo htmlspecialchars($user['nome'] . ' ' . ($user['sobrenome'] ?? '')); ?></h1>
          <p class="ts-profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
          <div class="ts-profile-badges">
            <span class="ts-badge ts-badge-<?php echo $user['status'] ?? 'ativo'; ?>">
              <?php echo ucfirst($user['status'] ?? 'ativo'); ?>
            </span>
            <?php if ($user['email_verificado'] ?? false): ?>
              <span class="ts-badge ts-badge-verified">
                <i class="fas fa-check-circle"></i> Email Verificado
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Mensagens -->
      <?php if (!empty($message)): ?>
        <div class="ts-alert ts-alert-<?php echo $message_type; ?>">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <!-- Tabs -->
      <div class="ts-tabs">
        <button class="ts-tab-btn active" onclick="openTab(event, 'personalData')">
          <i class="fas fa-user"></i> Dados Pessoais
        </button>
        <button class="ts-tab-btn" onclick="openTab(event, 'security')">
          <i class="fas fa-shield-alt"></i> Segurança
        </button>
        <button class="ts-tab-btn" onclick="openTab(event, 'preferences')">
          <i class="fas fa-cog"></i> Preferências
        </button>
        <button class="ts-tab-btn" onclick="openTab(event, 'privacy')">
          <i class="fas fa-lock"></i> Privacidade (LGPD)
        </button>
      </div>

      <!-- Tab Content: Dados Pessoais -->
      <div id="personalData" class="ts-tab-content active">
        <div class="ts-card">
          <div class="ts-card-header">
            <h3><i class="fas fa-user"></i> Informações Pessoais</h3>
            <button class="ts-btn-edit" onclick="openModal('profileModal')">
              <i class="fas fa-edit"></i> Editar
            </button>
          </div>
          <div class="ts-info-grid">
            <div class="ts-info-item">
              <label>Nome Completo</label>
              <span><?php echo htmlspecialchars($user['nome'] . ' ' . ($user['sobrenome'] ?? '')); ?></span>
            </div>
            <div class="ts-info-item">
              <label>Email</label>
              <span><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <div class="ts-info-item">
              <label>Celular</label>
              <span><?php echo htmlspecialchars($user['celular'] ?? $user['telefone'] ?? 'Não informado'); ?></span>
            </div>
            <div class="ts-info-item">
              <label>CPF</label>
              <span><?php echo !empty($user['cpf']) ? '***.***.***-' . substr($user['cpf'], -2) : 'Não informado'; ?></span>
            </div>
            <div class="ts-info-item">
              <label>Data de Nascimento</label>
              <span><?php echo !empty($user['data_nascimento']) ? date('d/m/Y', strtotime($user['data_nascimento'])) : 'Não informado'; ?></span>
            </div>
            <div class="ts-info-item">
              <label>Sexo</label>
              <span><?php echo ucfirst($user['sexo'] ?? 'Não informado'); ?></span>
            </div>
            <div class="ts-info-item">
              <label>Nacionalidade</label>
              <span><?php echo ucfirst($user['nacionalidade'] ?? 'brasileira'); ?></span>
            </div>
            <div class="ts-info-item">
              <label>Membro desde</label>
              <span><?php echo !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Data não disponível'; ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Content: Segurança -->
      <div id="security" class="ts-tab-content">
        <div class="ts-card">
          <div class="ts-card-header">
            <h3><i class="fas fa-shield-alt"></i> Segurança da Conta</h3>
          </div>
          <div class="ts-security-options">
            <div class="ts-security-item">
              <div class="ts-security-info">
                <h4>Senha</h4>
                <p>Última alteração: <?php echo !empty($user['updated_at']) ? date('d/m/Y', strtotime($user['updated_at'])) : 'Nunca alterada'; ?></p>
              </div>
              <button class="ts-btn-secondary" onclick="openModal('passwordModal')">
                <i class="fas fa-key"></i> Alterar Senha
              </button>
            </div>
            <div class="ts-security-item">
              <div class="ts-security-info">
                <h4>Verificação de Email</h4>
                <p>Status: <?php echo ($user['email_verificado'] ?? false) ? 'Verificado' : 'Não verificado'; ?></p>
              </div>
              <?php if (!($user['email_verificado'] ?? false)): ?>
                <button class="ts-btn-secondary">
                  <i class="fas fa-envelope"></i> Verificar
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Content: Preferências -->
      <div id="preferences" class="ts-tab-content">
        <div class="ts-card">
          <div class="ts-card-header">
            <h3><i class="fas fa-cog"></i> Preferências de Comunicação</h3>
          </div>
          <form method="post" class="ts-preferences-form">
            <div class="ts-checkbox-group">
              <label class="ts-checkbox-item">
                <input type="checkbox" name="whatsapp_optin" <?php echo ($user['whatsapp_optin'] ?? false) ? 'checked' : ''; ?>>
                <span class="ts-checkbox-mark"></span>
                <div class="ts-checkbox-content">
                  <h4>WhatsApp</h4>
                  <p>Receber notificações importantes via WhatsApp</p>
                </div>
              </label>
              <label class="ts-checkbox-item">
                <input type="checkbox" name="sms_optin" <?php echo ($user['sms_optin'] ?? false) ? 'checked' : ''; ?>>
                <span class="ts-checkbox-mark"></span>
                <div class="ts-checkbox-content">
                  <h4>SMS</h4>
                  <p>Receber confirmações e lembretes via SMS</p>
                </div>
              </label>
              <label class="ts-checkbox-item">
                <input type="checkbox" name="newsletter_optin" <?php echo ($user['newsletter_optin'] ?? false) ? 'checked' : ''; ?>>
                <span class="ts-checkbox-mark"></span>
                <div class="ts-checkbox-content">
                  <h4>Newsletter</h4>
                  <p>Receber novidades e promoções por email</p>
                </div>
              </label>
            </div>
            <button type="submit" name="update_preferences" class="ts-btn-primary">
              <i class="fas fa-save"></i> Salvar Preferências
            </button>
          </form>
        </div>
      </div>

      <!-- Tab Content: Privacidade (LGPD) -->
      <div id="privacy" class="ts-tab-content">
        <div class="ts-card">
          <div class="ts-card-header">
            <h3><i class="fas fa-lock"></i> Seus Direitos de Privacidade</h3>
            <p class="ts-card-subtitle">De acordo com a Lei Geral de Proteção de Dados (LGPD)</p>
          </div>
          <div class="ts-lgpd-options">
            <div class="ts-lgpd-item">
              <div class="ts-lgpd-info">
                <h4><i class="fas fa-download"></i> Baixar Meus Dados</h4>
                <p>Receba uma cópia de todos os seus dados pessoais armazenados em nosso sistema</p>
              </div>
              <form method="post" style="display: inline;">
                <button type="submit" name="request_data" class="ts-btn-secondary">
                  <i class="fas fa-download"></i> Baixar Dados
                </button>
              </form>
            </div>
            <div class="ts-lgpd-item">
              <div class="ts-lgpd-info">
                <h4><i class="fas fa-trash"></i> Excluir Minha Conta</h4>
                <p>Solicitar a exclusão permanente de sua conta e dados pessoais (processamento em 30 dias)</p>
              </div>
              <button class="ts-btn-danger" onclick="confirmDeletion()">
                <i class="fas fa-trash"></i> Solicitar Exclusão
              </button>
            </div>
            <div class="ts-lgpd-item">
              <div class="ts-lgpd-info">
                <h4><i class="fas fa-info-circle"></i> Política de Privacidade</h4>
                <p>Saiba como seus dados são coletados, utilizados e protegidos</p>
              </div>
              <a href="politica-privacidade.php" class="ts-btn-secondary" target="_blank">
                <i class="fas fa-external-link-alt"></i> Ler Política
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: Editar Perfil -->
  <div id="profileModal" class="ts-modal">
    <div class="ts-modal-content">
      <div class="ts-modal-header">
        <h3>Editar Dados Pessoais</h3>
        <button class="ts-modal-close" onclick="closeModal('profileModal')">&times;</button>
      </div>
      <form method="post" class="ts-modal-form">
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="nome">Nome *</label>
            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($user['nome']); ?>" required>
          </div>
          <div class="ts-form-group">
            <label for="sobrenome">Sobrenome</label>
            <input type="text" id="sobrenome" name="sobrenome" value="<?php echo htmlspecialchars($user['sobrenome'] ?? ''); ?>">
          </div>
        </div>
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
          </div>
          <div class="ts-form-group">
            <label for="celular">Celular *</label>
            <input type="tel" id="celular" name="celular" value="<?php echo htmlspecialchars($user['celular'] ?? $user['telefone'] ?? ''); ?>" required>
          </div>
        </div>
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="cpf">CPF</label>
            <input type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($user['cpf'] ?? ''); ?>" placeholder="000.000.000-00">
          </div>
          <div class="ts-form-group">
            <label for="data_nascimento">Data de Nascimento</label>
            <input type="date" id="data_nascimento" name="data_nascimento" value="<?php echo $user['data_nascimento'] ?? ''; ?>">
          </div>
        </div>
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="sexo">Sexo</label>
            <select id="sexo" name="sexo">
              <option value="">Não informar</option>
              <option value="masculino" <?php echo ($user['sexo'] ?? '') == 'masculino' ? 'selected' : ''; ?>>Masculino</option>
              <option value="feminino" <?php echo ($user['sexo'] ?? '') == 'feminino' ? 'selected' : ''; ?>>Feminino</option>
              <option value="outro" <?php echo ($user['sexo'] ?? '') == 'outro' ? 'selected' : ''; ?>>Outro</option>
            </select>
          </div>
          <div class="ts-form-group">
            <label for="nacionalidade">Nacionalidade</label>
            <select id="nacionalidade" name="nacionalidade">
              <option value="brasileira" <?php echo ($user['nacionalidade'] ?? 'brasileira') == 'brasileira' ? 'selected' : ''; ?>>Brasileira</option>
              <option value="estrangeira" <?php echo ($user['nacionalidade'] ?? '') == 'estrangeira' ? 'selected' : ''; ?>>Estrangeira</option>
            </select>
          </div>
        </div>
        <div class="ts-modal-actions">
          <button type="button" class="ts-btn-secondary" onclick="closeModal('profileModal')">Cancelar</button>
          <button type="submit" name="update_profile" class="ts-btn-primary">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Alterar Senha -->
  <div id="passwordModal" class="ts-modal">
    <div class="ts-modal-content">
      <div class="ts-modal-header">
        <h3>Alterar Senha</h3>
        <button class="ts-modal-close" onclick="closeModal('passwordModal')">&times;</button>
      </div>
      <form method="post" class="ts-modal-form">
        <div class="ts-form-group">
          <label for="senha_atual">Senha Atual *</label>
          <input type="password" id="senha_atual" name="senha_atual" required>
        </div>
        <div class="ts-form-group">
          <label for="nova_senha">Nova Senha *</label>
          <input type="password" id="nova_senha" name="nova_senha" required minlength="6">
        </div>
        <div class="ts-form-group">
          <label for="confirmar_senha">Confirmar Nova Senha *</label>
          <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
        </div>
        <div class="ts-modal-actions">
          <button type="button" class="ts-btn-secondary" onclick="closeModal('passwordModal')">Cancelar</button>
          <button type="submit" name="update_password" class="ts-btn-primary">Alterar Senha</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Alterar Foto -->
  <div id="photoModal" class="ts-modal">
    <div class="ts-modal-content">
      <div class="ts-modal-header">
        <h3>Alterar Foto de Perfil</h3>
        <button class="ts-modal-close" onclick="closeModal('photoModal')">&times;</button>
      </div>
      <form method="post" enctype="multipart/form-data" class="ts-modal-form">
        <div class="ts-upload-area">
          <div class="ts-upload-preview">
            <?php if (!empty($user['foto'])): ?>
              <img src="<?php echo htmlspecialchars($user['foto']); ?>" alt="Foto atual" id="photoPreview">
            <?php else: ?>
              <div class="ts-upload-placeholder" id="photoPreview">
                <i class="fas fa-user"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="ts-upload-info">
            <h4>Escolher nova foto</h4>
            <p>JPG, PNG ou GIF - Máximo 2MB</p>
            <input type="file" id="foto" name="foto" accept="image/*" onchange="previewPhoto(this)">
            <label for="foto" class="ts-btn-secondary">
              <i class="fas fa-upload"></i> Selecionar Arquivo
            </label>
          </div>
        </div>
        <div class="ts-modal-actions">
          <button type="button" class="ts-btn-secondary" onclick="closeModal('photoModal')">Cancelar</button>
          <button type="submit" name="update_photo" class="ts-btn-primary">Salvar Foto</button>
        </div>
      </form>
    </div>
  </div>

  <!-- JavaScript -->
  <script>
    // Dropdown do perfil
    document.addEventListener('DOMContentLoaded', function() {
      const profileBtn = document.getElementById('profileBtn');
      const dropdownMenu = document.getElementById('dropdownMenu');
      
      if (profileBtn && dropdownMenu) {
        profileBtn.addEventListener('click', function(e) {
          e.preventDefault();
          dropdownMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
          if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
          }
        });
      }
    });

    // Sistema de Tabs
    function openTab(evt, tabName) {
      var i, tabcontent, tablinks;
      
      tabcontent = document.getElementsByClassName("ts-tab-content");
      for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
      }
      
      tablinks = document.getElementsByClassName("ts-tab-btn");
      for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
      }
      
      document.getElementById(tabName).classList.add("active");
      evt.currentTarget.classList.add("active");
    }

    // Sistema de Modais
    function openModal(modalId) {
      document.getElementById(modalId).style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
      document.body.style.overflow = 'auto';
    }

    // Fechar modal ao clicar fora
    window.onclick = function(event) {
      const modals = document.getElementsByClassName('ts-modal');
      for (let i = 0; i < modals.length; i++) {
        if (event.target == modals[i]) {
          modals[i].style.display = 'none';
          document.body.style.overflow = 'auto';
        }
      }
    }

    // Preview da foto
    function previewPhoto(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const preview = document.getElementById('photoPreview');
          preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
        }
        reader.readAsDataURL(input.files[0]);
      }
    }

    // Máscara para CPF
    document.getElementById('cpf').addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      value = value.replace(/(\d{3})(\d)/, '$1.$2');
      value = value.replace(/(\d{3})(\d)/, '$1.$2');
      value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      e.target.value = value;
    });

    // Confirmação de exclusão
    function confirmDeletion() {
      if (confirm('ATENÇÃO: Esta ação irá solicitar a exclusão permanente de sua conta e todos os dados associados.\n\nVocê tem certeza que deseja continuar?\n\nEsta ação não pode ser desfeita e o processamento levará 30 dias.')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = '<input type="hidden" name="request_deletion" value="1">';
        document.body.appendChild(form);
        form.submit();
      }
    }

    // Validação de senha
    document.getElementById('confirmar_senha').addEventListener('input', function() {
      const nova = document.getElementById('nova_senha').value;
      const confirmar = this.value;
      
      if (nova !== confirmar) {
        this.setCustomValidity('Senhas não conferem');
      } else {
        this.setCustomValidity('');
      }
    });
  </script>
</body>
</html>
<?php
$conn->close();
?>