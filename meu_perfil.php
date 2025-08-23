<?php
//meu_perfil.php
session_start();
include('conexao.php');

// Verifica se há um usuário logado (cliente ou funcionário)
if (isset($_SESSION['funcionarioid'])) {
    $userId = $_SESSION['funcionarioid'];
    $table = 'funcionarios';
} elseif (isset($_SESSION['userid'])) {
    $userId = $_SESSION['userid'];
    $table = 'clientes';
} else {
    header("Location: login.php");
    exit();
}

// Busca os dados do usuário na tabela adequada
$sql = "SELECT * FROM $table WHERE id = $userId";
$result = $conn->query($sql);
if ($result && $user = $result->fetch_assoc()) {
    // Dados do usuário obtidos
} else {
    header("Location: logout.php");
    exit();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Meu Perfil - Ticket Sync</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="css/meu_perfil.css">
</head>
<body>
  <!-- Header -->
  <header>
    <div class="logo">
      <a href="<?php echo ($table === 'funcionarios' ? 'validar_ingresso.php' : 'index.php'); ?>">
        <img src="uploads/ticketsyhnklogo.png" alt="Ticket Sync Logo">
      </a>
    </div>
    <nav>
      <?php if(isset($_SESSION['userid']) || isset($_SESSION['funcionarioid'])): ?>
        <div class="user-container">
          <?php if($table === 'clientes'): ?>
            <a href="meus_ingressos.php" class="meus-ingressos-btn">
              <i class="fa-solid fa-ticket"></i> <span class="btn-text">Meus Ingressos</span>
            </a>
          <?php endif; ?>
          <a href="meu_perfil.php" class="user-btn">
            <i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($user['nome']); ?>
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

  <!-- Conteúdo Principal -->
  <div class="main-content">
    <!-- Conteúdo do Perfil -->
    <div class="profile-container">
      <div class="profile-header">
        <!-- Exibe a área de imagem apenas para clientes -->
        <?php if($table === 'clientes'): ?>
        <div class="profile-image-container" id="profileImageContainer">
          <?php if(!empty($user['foto'])): ?>
            <img id="profileImage" src="<?php echo htmlspecialchars($user['foto']); ?>" alt="Foto de <?php echo htmlspecialchars($user['nome']); ?>">
          <?php else: ?>
            <img id="profileImage" src="default-user.png" alt="Foto Padrão">
          <?php endif; ?>
          <input type="file" id="profileImageInput" accept="image/*">
        </div>
        <?php endif; ?>
        <div class="profile-info">
          <div class="info-item" data-field="nome">
            <label>Nome:</label>
            <span class="info-value"><?php echo htmlspecialchars($user['nome']); ?></span>
            <input type="text" class="edit-input" value="<?php echo htmlspecialchars($user['nome']); ?>">
            <i class="fa-solid fa-pen-to-square edit-icon" onclick="editField(this)"></i>
            <i class="fa-solid fa-check save-icon" onclick="saveField(this)" style="display:none;"></i>
            <i class="fa-solid fa-xmark cancel-icon" onclick="cancelEdit(this)" style="display:none;"></i>
          </div>
          <div class="info-item" data-field="email">
            <label>Email:</label>
            <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
            <input type="text" class="edit-input" value="<?php echo htmlspecialchars($user['email']); ?>">
            <i class="fa-solid fa-pen-to-square edit-icon" onclick="editField(this)"></i>
            <i class="fa-solid fa-check save-icon" onclick="saveField(this)" style="display:none;"></i>
            <i class="fa-solid fa-xmark cancel-icon" onclick="cancelEdit(this)" style="display:none;"></i>
          </div>
          <?php if($table === 'clientes'): ?>
          <div class="info-item" data-field="telefone">
            <label>Telefone:</label>
            <span class="info-value"><?php echo htmlspecialchars($user['telefone']); ?></span>
            <input type="text" class="edit-input" value="<?php echo htmlspecialchars($user['telefone']); ?>">
            <i class="fa-solid fa-pen-to-square edit-icon" onclick="editField(this)"></i>
            <i class="fa-solid fa-check save-icon" onclick="saveField(this)" style="display:none;"></i>
            <i class="fa-solid fa-xmark cancel-icon" onclick="cancelEdit(this)" style="display:none;"></i>
          </div>
          <?php endif; ?>
          <div class="info-item" data-field="senha">
            <label>Senha:</label>
            <span class="info-value">********</span>
            <input type="password" class="edit-input" value="" placeholder="Nova senha">
            <i class="fa-solid fa-pen-to-square edit-icon" onclick="editField(this)"></i>
            <i class="fa-solid fa-check save-icon" onclick="saveField(this)" style="display:none;"></i>
            <i class="fa-solid fa-xmark cancel-icon" onclick="cancelEdit(this)" style="display:none;"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Função para edição inline dos campos
    function editField(icon) {
      var container = icon.parentElement;
      var field = container.getAttribute('data-field');
      var span = container.querySelector('.info-value');
      var input = container.querySelector('.edit-input');
      var saveIcon = container.querySelector('.save-icon');
      var cancelIcon = container.querySelector('.cancel-icon');
      span.style.display = 'none';
      input.style.display = 'inline-block';
      if(field === 'senha'){
         input.value = "";
      }
      saveIcon.style.display = 'inline-block';
      cancelIcon.style.display = 'inline-block';
      icon.style.display = 'none';
    }

    function cancelEdit(icon) {
      var container = icon.parentElement;
      var field = container.getAttribute('data-field');
      var span = container.querySelector('.info-value');
      var input = container.querySelector('.edit-input');
      var saveIcon = container.querySelector('.save-icon');
      var editIcon = container.querySelector('.edit-icon');
      var cancelIcon = container.querySelector('.cancel-icon');
      if(field === 'senha'){
         input.value = "";
      } else {
         input.value = span.textContent;
      }
      input.style.display = 'none';
      saveIcon.style.display = 'none';
      cancelIcon.style.display = 'none';
      span.style.display = 'inline';
      editIcon.style.display = 'inline';
    }

    function saveField(icon) {
      var container = icon.parentElement;
      var field = container.getAttribute('data-field');
      var input = container.querySelector('.edit-input');
      var newValue = input.value;
      var span = container.querySelector('.info-value');
      var saveIcon = container.querySelector('.save-icon');
      var cancelIcon = container.querySelector('.cancel-icon');
      var editIcon = container.querySelector('.edit-icon');
      
      fetch('update_profile.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ field: field, value: newValue, user_id: <?php echo $userId; ?>, table: '<?php echo $table; ?>' })
      })
      .then(response => response.json())
      .then(data => {
          if(data.success) {
              if(field === 'senha'){
                 span.textContent = "********";
              } else {
                 span.textContent = newValue;
              }
              input.style.display = 'none';
              saveIcon.style.display = 'none';
              cancelIcon.style.display = 'none';
              span.style.display = 'inline';
              editIcon.style.display = 'inline';
          } else {
              alert('Erro ao atualizar: ' + data.error);
          }
      })
      .catch(error => {
          console.error('Erro:', error);
          alert('Erro na requisição.');
      });
    }

    // Atualização da imagem de perfil (apenas para clientes)
    <?php if($table === 'clientes'): ?>
    document.getElementById('profileImage').addEventListener('click', function() {
      document.getElementById('profileImageInput').click();
    });

    document.getElementById('profileImageInput').addEventListener('change', function() {
      var file = this.files[0];
      if (!file) return;
      var formData = new FormData();
      formData.append('profile_image', file);
      formData.append('user_id', <?php echo $userId; ?>);
      formData.append('table', '<?php echo $table; ?>');
      
      fetch('update_profile_image.php', {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if(data.success) {
              document.getElementById('profileImage').src = data.image_url;
          } else {
              alert('Erro ao atualizar a imagem: ' + data.error);
          }
      })
      .catch(error => {
          console.error('Erro:', error);
          alert('Erro na requisição.');
      });
    });
    <?php endif; ?>
  </script>
</body>
</html>
