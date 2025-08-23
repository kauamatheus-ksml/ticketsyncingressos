<?php
// registro.php - Página de registro consolidada
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

$message = "";
$message_class = "";

// Se houver mensagens armazenadas na sessão, recupera-as e limpa-as
if(isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_class = $_SESSION['message_class'];
    unset($_SESSION['message'], $_SESSION['message_class']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitização e validação de entrada
    $primeiro_nome = trim($_POST['primeiro_nome']);
    $sobrenome = trim($_POST['sobrenome']);
    $email = trim(strtolower($_POST['email']));
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']); // Remove formatação
    $celular = preg_replace('/[^0-9]/', '', $_POST['celular']); // Remove formatação
    $data_nascimento = $_POST['data_nascimento'];
    $sexo = $_POST['sexo'];
    $nacionalidade = $_POST['nacionalidade'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $foto = $_FILES['foto']['name'];
    
    // Preferências de comunicação
    $receber_email = isset($_POST['receber_email']) ? 1 : 0;
    $receber_sms = isset($_POST['receber_sms']) ? 1 : 0;
    $receber_push = isset($_POST['receber_push']) ? 1 : 0;
    
    // Para cadastro como CLIENTE usamos tipo_usuario = 0
    $novo_tipo = 0;
    
    // Validações de entrada
    $errors = [];
    
    // Validar primeiro nome
    if (empty($primeiro_nome) || strlen($primeiro_nome) < 2) {
        $errors[] = "Primeiro nome deve ter pelo menos 2 caracteres.";
    }
    
    // Validar sobrenome
    if (empty($sobrenome) || strlen($sobrenome) < 2) {
        $errors[] = "Sobrenome deve ter pelo menos 2 caracteres.";
    }
    
    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "E-mail inválido.";
    }
    
    // Validar CPF (algoritmo básico)
    if (strlen($cpf) != 11 || !validarCPF($cpf)) {
        $errors[] = "CPF inválido.";
    }
    
    // Validar celular (formato brasileiro)
    if (strlen($celular) < 10 || strlen($celular) > 11) {
        $errors[] = "Celular deve ter 10 ou 11 dígitos.";
    }
    
    // Validar data de nascimento
    $hoje = new DateTime();
    $nascimento = new DateTime($data_nascimento);
    $idade = $hoje->diff($nascimento)->y;
    if ($idade < 16 || $idade > 120) {
        $errors[] = "Idade deve estar entre 16 e 120 anos.";
    }
    
    // Validar senha
    if (strlen($_POST['senha']) < 8) {
        $errors[] = "Senha deve ter pelo menos 8 caracteres.";
    }
    
    // Se há erros, exibe e para o processamento
    if (!empty($errors)) {
        $message = "<strong>Corrija os seguintes erros:</strong><br>" . implode("<br>", $errors);
        $message_class = "alert-error";
    } else {

    // Verifica se o diretório 'uploads' existe, se não, cria-o
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0775, true);
    }

    // Processa o upload da foto, se houver
    if ($foto) {
        $target_file = $target_dir . basename($_FILES["foto"]["name"]);
        if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
            $message .= "<p>Foto carregada com sucesso.</p>";
        } else {
            $message .= "<p>Erro ao carregar a foto.</p>";
            $target_file = NULL;
        }
    } else {
        $target_file = NULL;
    }

        // Verifica se já existe um usuário com o mesmo email ou CPF
        $stmt_check = $conn->prepare("SELECT id, tipo_usuario, foto FROM clientes WHERE email = ? OR cpf = ?");
        $stmt_check->bind_param("ss", $email, $cpf);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $row = $result_check->fetch_assoc();
            $id = $row['id'];
            // Se o usuário estiver cadastrado como visitante (tipo_usuario = 1), atualiza para cliente (0)
            if ($row['tipo_usuario'] == 1) {
                $stmt_update = $conn->prepare("UPDATE clientes SET 
                    primeiro_nome = ?, sobrenome = ?, cpf = ?, celular = ?, 
                    data_nascimento = ?, sexo = ?, nacionalidade = ?, senha = ?, 
                    foto = ?, tipo_usuario = ?, receber_email = ?, receber_sms = ?, receber_push = ?
                    WHERE id = ?");
                $stmt_update->bind_param("sssssssssiiii", 
                    $primeiro_nome, $sobrenome, $cpf, $celular, $data_nascimento, 
                    $sexo, $nacionalidade, $senha, $target_file, $novo_tipo, 
                    $receber_email, $receber_sms, $receber_push, $id
                );
                
                if ($stmt_update->execute()) {
                    $message = "<p>Você estava cadastrado como visitante e agora foi atualizado para cliente com sucesso!</p>";
                    $message_class = "alert-success";
                } else {
                    $message = "<p>Ocorreu um erro ao atualizar seus dados. Por favor, tente novamente mais tarde.</p>";
                    $message_class = "alert-error";
                }
                $stmt_update->close();
            } else {
                // Já está cadastrado como cliente
                $message = "<p>Este e-mail ou CPF já está registrado como cliente. Caso tenha esquecido sua senha, utilize a recuperação.</p>";
                $message_class = "alert-error";
            }
        } else {
            // Insere um novo registro
            $stmt_insert = $conn->prepare("INSERT INTO clientes (
                primeiro_nome, sobrenome, email, cpf, celular, data_nascimento, 
                sexo, nacionalidade, senha, foto, tipo_usuario, 
                receber_email, receber_sms, receber_push, data_criacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt_insert->bind_param("sssssssssiiii", 
                $primeiro_nome, $sobrenome, $email, $cpf, $celular, $data_nascimento, 
                $sexo, $nacionalidade, $senha, $target_file, $novo_tipo, 
                $receber_email, $receber_sms, $receber_push
            );
            
            if ($stmt_insert->execute()) {
                $message = "<p>Registro realizado com sucesso! Bem-vindo(a) ao TicketSync!</p>";
                $message_class = "alert-success";
            } else {
                $message = "<p>Ocorreu um erro ao registrar. Por favor, tente novamente mais tarde.</p>";
                $message_class = "alert-error";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
    
    $conn->close();
    
    // Armazena as mensagens na sessão e redireciona (PRG Pattern)
    $_SESSION['message'] = $message;
    $_SESSION['message_class'] = $message_class;
    header("Location: registro.php");
    exit();
}

// Função para validar CPF
function validarCPF($cpf) {
    // Elimina CPFs conhecidos como inválidos
    if (strlen($cpf) != 11 || preg_match('/([0-9])\1{10}/', $cpf)) {
        return false;
    }
    
    // Calcula os dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    return true;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de Cliente - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/index.css">
  <link rel="stylesheet" href="assets/css/registro.css">
  <script>
    // Máscaras para formatação de campos
    function aplicarMascaraCPF(campo) {
      let valor = campo.value.replace(/\D/g, '');
      valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
      valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
      valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      campo.value = valor;
    }
    
    function aplicarMascaraCelular(campo) {
      let valor = campo.value.replace(/\D/g, '');
      valor = valor.replace(/(\d{2})(\d)/, '($1) $2');
      valor = valor.replace(/(\d{5})(\d)/, '$1-$2');
      campo.value = valor;
    }
    
    // Validações em tempo real
    function validarCPF(cpf) {
      cpf = cpf.replace(/[^\d]+/g, '');
      if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
      
      let soma = 0;
      for (let i = 0; i < 9; i++) soma += parseInt(cpf.charAt(i)) * (10 - i);
      let resto = 11 - (soma % 11);
      if (resto === 10 || resto === 11) resto = 0;
      if (resto !== parseInt(cpf.charAt(9))) return false;
      
      soma = 0;
      for (let i = 0; i < 10; i++) soma += parseInt(cpf.charAt(i)) * (11 - i);
      resto = 11 - (soma % 11);
      if (resto === 10 || resto === 11) resto = 0;
      return resto === parseInt(cpf.charAt(10));
    }
    
    function validarEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    }
    
    function validarSenha(senha) {
      return senha.length >= 8;
    }
    
    function mostrarFeedback(campo, valido, mensagem) {
      const grupo = campo.closest('.ts-form-group');
      const feedback = grupo.querySelector('.ts-field-feedback') || document.createElement('small');
      
      feedback.className = `ts-field-feedback ${valido ? 'ts-valid' : 'ts-invalid'}`;
      feedback.textContent = mensagem;
      
      if (!grupo.querySelector('.ts-field-feedback')) {
        grupo.appendChild(feedback);
      }
      
      campo.classList.toggle('ts-input-valid', valido);
      campo.classList.toggle('ts-input-invalid', !valido);
    }
    
    // Função para melhorar upload de arquivo
    function setupFileUpload() {
      const fileInput = document.getElementById('foto');
      const uploadArea = document.querySelector('.ts-upload-area');
      const uploadPreview = document.getElementById('uploadPreview');
      
      if (fileInput && uploadArea) {
        fileInput.addEventListener('change', (e) => {
          if (e.target.files.length > 0) {
            const file = e.target.files[0];
            if (file.type.startsWith('image/')) {
              const reader = new FileReader();
              reader.onload = (e) => {
                uploadPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
              };
              reader.readAsDataURL(file);
            }
          }
        });
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
          e.preventDefault();
          uploadArea.classList.add('ts-dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
          uploadArea.classList.remove('ts-dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
          e.preventDefault();
          uploadArea.classList.remove('ts-dragover');
          const files = e.dataTransfer.files;
          if (files.length > 0 && files[0].type.startsWith('image/')) {
            fileInput.files = files;
            const file = files[0];
            const reader = new FileReader();
            reader.onload = (e) => {
              uploadPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
            };
            reader.readAsDataURL(file);
          }
        });
      }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      setupFileUpload();
      
      // Aplicar máscaras
      document.getElementById('cpf').addEventListener('input', (e) => {
        aplicarMascaraCPF(e.target);
        const cpfValido = validarCPF(e.target.value);
        mostrarFeedback(e.target, cpfValido, cpfValido ? 'CPF válido' : 'CPF inválido');
      });
      
      document.getElementById('celular').addEventListener('input', (e) => {
        aplicarMascaraCelular(e.target);
      });
      
      // Validação de email
      document.getElementById('email').addEventListener('blur', (e) => {
        const emailValido = validarEmail(e.target.value);
        mostrarFeedback(e.target, emailValido, emailValido ? 'E-mail válido' : 'E-mail inválido');
      });
      
      // Validação de senha
      document.getElementById('senha').addEventListener('input', (e) => {
        const senhaValida = validarSenha(e.target.value);
        mostrarFeedback(e.target, senhaValida, senhaValida ? 'Senha forte' : 'Senha deve ter pelo menos 8 caracteres');
      });
      
      // Validação de idade
      document.getElementById('data_nascimento').addEventListener('change', (e) => {
        const hoje = new Date();
        const nascimento = new Date(e.target.value);
        const idade = hoje.getFullYear() - nascimento.getFullYear();
        const idadeValida = idade >= 16 && idade <= 120;
        mostrarFeedback(e.target, idadeValida, idadeValida ? 'Idade válida' : 'Idade deve estar entre 16 e 120 anos');
      });
      
      // Configurar checkboxes personalizados
      document.querySelectorAll('.ts-checkbox-item').forEach(item => {
        item.addEventListener('click', (e) => {
          if (e.target.type !== 'checkbox') {
            const checkbox = item.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
          }
        });
      });
    });
    
    // JavaScript para dropdown do perfil
    document.addEventListener('DOMContentLoaded', function() {
      const profileBtn = document.getElementById('profileBtn');
      const dropdownMenu = document.getElementById('dropdownMenu');
      
      if (profileBtn && dropdownMenu) {
        profileBtn.addEventListener('click', function(e) {
          e.preventDefault();
          dropdownMenu.classList.toggle('show');
        });
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
          if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
          }
        });
      }
    });
  </script>
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
        <?php if (isset($_SESSION['userid']) && !empty($_SESSION['nome'])): ?>
          <span>Olá, <?php echo htmlspecialchars($_SESSION['nome']); ?>!</span>
        <?php else: ?>
          <span>Olá, Visitante!</span>
        <?php endif; ?>
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
            <?php if (isset($_SESSION['userid']) && !empty($_SESSION['nome'])): ?>
              <a href="perfil.php" class="dropdown-item">
                <i class="fas fa-user"></i> Meu Perfil
              </a>
              <a href="meus_ingressos.php" class="dropdown-item">
                <i class="fas fa-ticket-alt"></i> Meus Ingressos
              </a>
              <div class="dropdown-divider"></div>
              <a href="logout.php" class="dropdown-item logout">
                <i class="fas fa-sign-out-alt"></i> Sair
              </a>
            <?php else: ?>
              <a href="login.php?redirect=perfil.php" class="dropdown-item">
                <i class="fas fa-user"></i> Meu Perfil
              </a>
              <a href="login.php?redirect=meus_ingressos.php" class="dropdown-item">
                <i class="fas fa-ticket-alt"></i> Meus Ingressos
              </a>
              <div class="dropdown-divider"></div>
              <a href="login.php" class="dropdown-item">
                <i class="fas fa-sign-in-alt"></i> Entrar
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Container de Registro -->
  <div class="ts-register-wrapper">
    <div class="ts-register-container">
      <div class="ts-register-header">
        <h1 class="ts-register-heading">Criar Conta</h1>
        <p class="ts-register-subtitle">Preencha os dados para se registrar no TicketSync</p>
        
        <!-- Progress indicator -->
        <div class="ts-progress-container">
          <div class="ts-progress-bar">
            <div class="ts-progress-fill" style="width: 25%"></div>
          </div>
          <div class="ts-progress-steps">
            <span class="ts-step active">Acesso</span>
            <span class="ts-step">Pessoal</span>
            <span class="ts-step">Comunicação</span>
            <span class="ts-step">Foto</span>
          </div>
        </div>
      </div>
    
    <!-- Exibe a mensagem de retorno, se houver -->
    <?php if (!empty($message)) : ?>
      <div class="ts-alert ts-<?php echo str_replace('alert-', '', $message_class); ?>">
          <?php echo $message; ?>
      </div>
    <?php endif; ?>
    
    <form action="registro.php" method="post" enctype="multipart/form-data" class="ts-register-form" id="registroForm">
      <input type="hidden" name="tipo_usuario" value="0">
      
      <!-- Seção 1: Dados de Acesso -->
      <div class="ts-form-section" id="section-access">
        <div class="ts-section-header">
          <h3 class="ts-section-title">
            <span class="ts-section-number">1</span>
            Dados de Acesso
          </h3>
          <p class="ts-section-subtitle">Crie suas credenciais de login</p>
        </div>
        
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="email" class="ts-form-label" data-required="true">E-mail</label>
            <input type="email" id="email" name="email" class="ts-form-input" placeholder="seu@email.com" required>
            <small class="ts-field-help">Será usado para fazer login</small>
          </div>
          
          <div class="ts-form-group">
            <label for="senha" class="ts-form-label" data-required="true">Senha</label>
            <input type="password" id="senha" name="senha" class="ts-form-input" placeholder="Mínimo 8 caracteres" required>
            <small class="ts-field-help">Use letras, números e símbolos</small>
          </div>
        </div>
      </div>
      
      <!-- Seção 2: Dados Pessoais -->
      <div class="ts-form-section" id="section-personal">
        <div class="ts-section-header">
          <h3 class="ts-section-title">
            <span class="ts-section-number">2</span>
            Dados Pessoais
          </h3>
          <p class="ts-section-subtitle">Informações para personalizar sua experiência</p>
        </div>
        
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="primeiro_nome" class="ts-form-label" data-required="true">Primeiro Nome</label>
            <input type="text" id="primeiro_nome" name="primeiro_nome" class="ts-form-input" placeholder="Digite seu primeiro nome" required>
          </div>
          
          <div class="ts-form-group">
            <label for="sobrenome" class="ts-form-label" data-required="true">Sobrenome</label>
            <input type="text" id="sobrenome" name="sobrenome" class="ts-form-input" placeholder="Digite seu sobrenome" required>
          </div>
        </div>
        
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="cpf" class="ts-form-label" data-required="true">CPF</label>
            <input type="text" id="cpf" name="cpf" class="ts-form-input" placeholder="000.000.000-00" required maxlength="14">
          </div>
          
          <div class="ts-form-group">
            <label for="celular" class="ts-form-label" data-required="true">Celular</label>
            <input type="tel" id="celular" name="celular" class="ts-form-input" placeholder="(11) 99999-9999" required>
          </div>
        </div>
        
        <div class="ts-form-row">
          <div class="ts-form-group">
            <label for="data_nascimento" class="ts-form-label" data-required="true">Data de Nascimento</label>
            <input type="date" id="data_nascimento" name="data_nascimento" class="ts-form-input" required>
          </div>
          
          <div class="ts-form-group">
            <label for="sexo" class="ts-form-label" data-required="true">Sexo</label>
            <select id="sexo" name="sexo" class="ts-form-input" required>
              <option value="">Selecione</option>
              <option value="M">Masculino</option>
              <option value="F">Feminino</option>
              <option value="NB">Não-binário</option>
              <option value="PNI">Prefiro não informar</option>
            </select>
          </div>
        </div>
        
        <div class="ts-form-group">
          <label for="nacionalidade" class="ts-form-label" data-required="true">Nacionalidade</label>
          <select id="nacionalidade" name="nacionalidade" class="ts-form-input" required>
            <option value="">Selecione sua nacionalidade</option>
            <option value="Brasileira" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Brasileira') ? 'selected' : ''; ?>>Brasileira</option>
            <option value="Argentina" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Argentina') ? 'selected' : ''; ?>>Argentina</option>
            <option value="Americana" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Americana') ? 'selected' : ''; ?>>Americana</option>
            <option value="Portuguesa" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Portuguesa') ? 'selected' : ''; ?>>Portuguesa</option>
            <option value="Espanhola" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Espanhola') ? 'selected' : ''; ?>>Espanhola</option>
            <option value="Italiana" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Italiana') ? 'selected' : ''; ?>>Italiana</option>
            <option value="Francesa" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Francesa') ? 'selected' : ''; ?>>Francesa</option>
            <option value="Alemã" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Alemã') ? 'selected' : ''; ?>>Alemã</option>
            <option value="Outra" <?php echo (isset($form_data['nacionalidade']) && $form_data['nacionalidade'] == 'Outra') ? 'selected' : ''; ?>>Outra</option>
          </select>
        </div>
      </div>
      
      <!-- Seção 3: Preferências de Comunicação -->
      <div class="ts-form-section" id="section-communication">
        <div class="ts-section-header">
          <h3 class="ts-section-title">
            <span class="ts-section-number">3</span>
            Preferências de Comunicação
          </h3>
          <p class="ts-section-subtitle">Como deseja receber nossas novidades?</p>
        </div>
        
        <div class="ts-checkbox-group">
          <div class="ts-checkbox-item">
            <input type="checkbox" id="receber_email" name="receber_email" value="1" checked>
            <div class="ts-checkbox-mark"></div>
            <div class="ts-checkbox-content">
              <h4>E-mail</h4>
              <p>Receber novidades, promoções e confirmações por e-mail</p>
            </div>
          </div>
          
          <div class="ts-checkbox-item">
            <input type="checkbox" id="receber_sms" name="receber_sms" value="1">
            <div class="ts-checkbox-mark"></div>
            <div class="ts-checkbox-content">
              <h4>SMS</h4>
              <p>Receber lembretes importantes por mensagem de texto</p>
            </div>
          </div>
          
          <div class="ts-checkbox-item">
            <input type="checkbox" id="receber_push" name="receber_push" value="1" checked>
            <div class="ts-checkbox-mark"></div>
            <div class="ts-checkbox-content">
              <h4>Notificações Push</h4>
              <p>Receber alertas instantâneos sobre eventos e ingressos</p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Seção 4: Foto de Perfil -->
      <div class="ts-form-section" id="section-photo">
        <div class="ts-section-header">
          <h3 class="ts-section-title">
            <span class="ts-section-number">4</span>
            Foto de Perfil
          </h3>
          <p class="ts-section-subtitle">Adicione uma foto para personalizar seu perfil (opcional)</p>
        </div>
        
        <div class="ts-upload-area">
          <div class="ts-upload-preview">
            <div class="ts-upload-placeholder" id="uploadPreview">
              <i class="fas fa-user"></i>
            </div>
          </div>
          <div class="ts-upload-info">
            <h4>Clique para enviar uma foto</h4>
            <p>JPG, PNG ou GIF - Máximo 2MB</p>
            <button type="button" class="ts-btn-secondary" onclick="document.getElementById('foto').click();">Escolher Arquivo</button>
            <input type="file" id="foto" name="foto" class="ts-file-input-hidden" accept="image/*">
          </div>
        </div>
      </div>
      
      <div class="ts-form-actions">
        <button type="submit" class="ts-btn-primary">
          <i class="fas fa-user-plus"></i>
          Criar Minha Conta
        </button>
      </div>
    </form>
    
    <div class="ts-register-links">
      <p class="ts-register-text">Já tem uma conta? <a href="login.php" class="ts-register-link">Faça login</a></p>
    </div>
  </div>
</body>
</html>
