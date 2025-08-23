<?php
/**
 * gerencia_acesso.php
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

include('conexao.php');

// Verifica se o usuário logado é master
$sqlMasterCheck = "SELECT master, nome FROM administradores WHERE id = " . intval($_SESSION['adminid']);
$resultMasterCheck = $conn->query($sqlMasterCheck);
if ($resultMasterCheck->num_rows === 0) {
    header("Location: login.php");
    exit();
}
$admin = $resultMasterCheck->fetch_assoc();
if (intval($admin['master']) !== 1) {
    echo "Você não possui permissão para acessar essa página!";
    exit();
}



// Caminho para o arquivo de permissões
$permissionsFile = "permissoes_geral.json";

// Valores padrão para cada usuário
$defaultUserPermissions = [
    "eventos"                => true,
    "ingressos"              => true,
    "ingressos_vendidos"     => false,
    "promotores"             => false,
    "gerenciar_eventos"      => false,
    "gerencia_acesso"        => false,
    "cadastrar_funcionarios" => false,
    // NOVA PERMISSÃO "validar_ingressos"
    "validar_ingressos"      => false,
    // NOVA PERMISSÃO "pdv"
    "pdv"                    => false
];

// Carrega os dados de permissões do arquivo (se existir) ou inicializa vazio
if (file_exists($permissionsFile)) {
    $permissionsData = json_decode(file_get_contents($permissionsFile), true);
    if (!$permissionsData) {
        $permissionsData = [];
    }
} else {
    $permissionsData = [];
}

// Processa a requisição AJAX de auto-salvar (quando toggles são alterados)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['auto_save'])) {
    // Consulta todos os usuários da tabela administradores
    $sqlUsers = "SELECT id FROM administradores";
    $allUsersResult = $conn->query($sqlUsers);
    while ($row = $allUsersResult->fetch_assoc()) {
        if (!isset($row['id'])) {
            continue;
        }
        $uid = $row['id'];

        // Verifica se há toggles marcados para esse usuário
        if (isset($_POST['permissions'][$uid])) {
            foreach ($defaultUserPermissions as $button => $defaultVal) {
                // Se for "gerencia_acesso" e for o próprio usuário Master atual, força como true
                if ($button === "gerencia_acesso" && $uid == $_SESSION['adminid']) {
                    $permissionsData[$uid][$button] = true;
                } else {
                    // Caso contrário, pega o valor marcado (true/false)
                    $permissionsData[$uid][$button] = !empty($_POST['permissions'][$uid][$button]);
                }
            }
        } else {
            // Se nenhum toggle for alterado para esse usuário, define todas como false
            $permissionsData[$uid] = array_fill_keys(array_keys($defaultUserPermissions), false);
        }
    }
    // Salva as permissões atualizadas no arquivo JSON
    file_put_contents($permissionsFile, json_encode($permissionsData));
    echo "Permissões atualizadas com sucesso!";
    exit();
}

// Consulta todos os administradores
$sql = "SELECT * FROM administradores";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <title>Gerência de Acesso</title>
  <link rel="stylesheet" href="css/admin.css">
  <!-- Font Awesome para ícones -->
  <link rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" 
        integrity="sha512-Fo3rlrZj/k7ujTnH2N2bNqykVNpyFJpN7Mx5jZ0ip2qZf9ObK0MZJxY9w+cn0bXn8N+Ge2B9RVzOfX6swbZZmw==" 
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    /* Estilos adicionais para melhorar a performance dos cards */
    .admin-menu-card {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      will-change: transform, box-shadow;
    }
  </style>
  <style>
    /* CSS para a página de Gerência de Acesso */
    .access-container {
      max-width: 1200px;
      margin: 20px auto;
      padding: 20px;
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .access-container h2 {
      text-align: center;
      color: #2c3e50;
      margin-bottom: 20px;
    }
    .table-responsive {
      overflow-x: auto;
    }
    table.access-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      min-width: 1200px; /* para comportar mais colunas se necessário */
    }
    table.access-table th,
    table.access-table td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: center;
      font-size: 1rem;
    }
    table.access-table th {
      background: #f9f9f9;
    }
    .message {
      text-align: center;
      margin-bottom: 15px;
      color: #2c3e50;
      font-size: 1.1rem;
    }
    /* Toggle Switches */
    .switch {
      position: relative;
      display: inline-block;
      width: 50px;
      height: 24px;
    }
    .switch input {
      opacity: 0;
      width: 0; height: 0;
    }
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 24px;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px; bottom: 3px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }
    input:checked + .slider {
      background-color: #66bb6a; /* verde */
    }
    input:checked + .slider:before {
      transform: translateX(26px);
    }
    @media (max-width: 768px) {
      table.access-table th,
      table.access-table td {
        padding: 8px;
        font-size: 0.9rem;
      }
      .switch {
        width: 40px; height: 20px;
      }
      .slider:before {
        height: 16px; width: 16px;
        left: 2px; bottom: 2px;
      }
      input:checked + .slider:before {
        transform: translateX(20px);
      }
    }
    @media (max-width: 480px) {
      .access-container {
        padding: 15px;
      }
      table.access-table th,
      table.access-table td {
        padding: 6px;
        font-size: 0.8rem;
      }
    }
  </style>

</head>
<body class="admin-page-body">
  <?php include('header_admin.php'); ?>
  <div class="admin-container">
    <main class="access-container">
      <h2>Gerência de Acesso</h2>
      <p style="text-align:center;">Defina quais botões cada usuário poderá visualizar.</p>
      <div id="statusMessage" class="message" style="display:none;"></div>
      
      <div class="table-responsive">
        <!-- Formulário de auto-save via AJAX -->
        <form id="accessForm" action="gerencia_acesso.php" method="post">
          <input type="hidden" name="auto_save" value="1">
          <table class="access-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Eventos</th>
                <th>Ingressos</th>
                <th>Ingressos Vendidos</th>
                <th>Promotores</th>
                <th>Gerenciar Eventos</th>
                <th>Gerência de Acesso</th>
                <th>Cadastrar Funcionários</th>
                <th>Validar Ingressos</th>
                <th>PDV</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              while ($user = $result->fetch_assoc()):
                if (!isset($user['id'])) continue;
                $uid = $user['id'];
                
                // Se não houver permissões definidas para o usuário, utiliza os padrões
                if (!isset($permissionsData[$uid])) {
                  $permissionsData[$uid] = $defaultUserPermissions;
                }

                // Define a ordem dos botões, incluindo as novas permissões
                $buttons = [
                  "eventos",
                  "ingressos",
                  "ingressos_vendidos",
                  "promotores",
                  "gerenciar_eventos",
                  "gerencia_acesso",
                  "cadastrar_funcionarios",
                  "validar_ingressos",
                  "pdv"
                ];
              ?>
              <tr>
                <td><?php echo htmlspecialchars($uid); ?></td>
                <td><?php echo htmlspecialchars($user['nome']); ?></td>
                <?php 
                  foreach ($buttons as $button):
                    // Se o botão for "gerencia_acesso" e o usuário for master, força como true e desativa o toggle
                    if ($button === "gerencia_acesso" && $user['master'] == 1) {
                        $checked = "checked";
                        $disabled = "disabled";
                    } else {
                        $checked = !empty($permissionsData[$uid][$button]) ? "checked" : "";
                        $disabled = "";
                    }
                ?>
                <td>
                  <label class="switch">
                    <input type="checkbox" 
                           name="permissions[<?php echo $uid; ?>][<?php echo $button; ?>]" 
                           <?php echo $checked . " " . $disabled; ?>>
                    <span class="slider"></span>
                  </label>
                </td>
                <?php endforeach; ?>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </form>
      </div>
    </main>
  </div>
  
  <script>
    // Função debounce para evitar múltiplas chamadas seguidas
    function debounce(func, wait) {
      let timeout;
      return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
      };
    }
    
    // Função de auto-save via AJAX
    function autoSavePermissions() {
      const form = document.getElementById('accessForm');
      const formData = new FormData(form);
      
      fetch('gerencia_acesso.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
          const statusDiv = document.getElementById('statusMessage');
          statusDiv.style.display = 'block';
          statusDiv.innerText = data;
          // Oculta a mensagem após 2 segundos
          setTimeout(() => {
              statusDiv.style.display = 'none';
          }, 2000);
      })
      .catch(error => {
          console.error('Erro ao salvar permissões:', error);
      });
    }
    
    // Adiciona event listener com debounce para todos os toggle switches
    document.addEventListener('DOMContentLoaded', () => {
      const debouncedSave = debounce(autoSavePermissions, 500);
      document.querySelectorAll('#accessForm input[type="checkbox"]').forEach(checkbox => {
          checkbox.addEventListener('change', debouncedSave);
      });
    });
  </script>
</body>
</html>
<?php $conn->close(); ?>
