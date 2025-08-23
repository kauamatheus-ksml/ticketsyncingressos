<?php
// meus_ingressos.php
session_start();
include('conexao.php');

// Verifica se o usuário está logado
if (!isset($_SESSION['userid']) || empty($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['email'];

// Consulta os ingressos (pedidos) do usuário logado e junta com a tabela de eventos
$sql = "
    SELECT 
        p.order_id, 
        p.ingresso_validado,
        p.status,
        e.nome AS evento_nome,
        e.logo AS evento_logo,
        e.data_inicio AS evento_data,
        e.hora_inicio AS evento_horario
    FROM pedidos p
    JOIN eventos e ON p.evento_id = e.id
    WHERE p.email = '$userEmail'
    ORDER BY p.created_at DESC
";
$result = $conn->query($sql);

$pedidos = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pedidos[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <!-- Font Awesome -->
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />
  <title>Meus Ingressos - Ticket Sync</title>

  <!-- CSS externo (exemplo) -->
  <link rel="stylesheet" href="css/meus_ingressos.css">

  <style>
    /* Estilo para os cards clicáveis */
    .ingresso-card {
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    .ingresso-card:hover {
      transform: scale(1.02);
    }
    /* Para cards não clicáveis, altera o cursor */
    .ingresso-card.nao-clicavel {
      cursor: default;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <?php
    // Se for admin, exibe header_admin, caso contrário, header_cliente
    if (isset($_SESSION['adminid'])) {
      include('header_admin.php');
    } else {
      include('header_cliente.php');
    }
  ?>

  <!-- Conteúdo Principal -->
  <div class="main-content">
    <div class="ingressos-container">
      <h2>Meus Ingressos</h2>

      <?php if (count($pedidos) > 0): ?>
        <div class="ingressos-cards">
          <?php foreach ($pedidos as $pedido): ?>
            <?php 
              // Monta link para página de detalhes
              $detalhesLink = 'detalhes_pedido.php?order_id=' . urlencode($pedido['order_id']);
              
              // Verifica status do pedido
              $status = strtolower($pedido['status']);
              $naoClicavel = in_array($status, ['cancelado', 'cancelled', 'pendente', 'pending']);
              // Determina se o pedido está cancelado (para aplicar o filtro)
              $isCancelado = in_array($status, ['cancelado', 'cancelled']);
              
              // Define o atributo onclick apenas se o pedido for clicável
              $cardAttributes = $naoClicavel ? '' : 'onclick="window.location.href=\'' . $detalhesLink . '\'"';
              
              // Define a classe extra se estiver pendente ou cancelado
              $cardClass = $naoClicavel ? 'ingresso-card nao-clicavel' : 'ingresso-card';
              
              // Exibe se está validado ou não
              $validadoTexto = ($pedido['ingresso_validado'] == 1) ? 'Validado' : 'Não validado';
              $validadoIcone = ($pedido['ingresso_validado'] == 1) 
                  ? '<i class="fa-solid fa-circle-check" style="color: green;"></i>'
                  : '<i class="fa-solid fa-circle-xmark" style="color: red;"></i>';
            ?>
            
            <!-- Card clicável ou não, conforme o status -->
            <div class="<?php echo $cardClass; ?>" <?php echo $cardAttributes; ?>>
              <!-- Imagem do evento: aplica filtro somente se o pedido estiver cancelado -->
              <div class="card-image">
                <img 
                  src="<?php echo (!empty($pedido['evento_logo'])) 
                           ? htmlspecialchars($pedido['evento_logo']) 
                           : 'default-event.jpg'; ?>" 
                  alt="Logo do evento <?php echo htmlspecialchars($pedido['evento_nome']); ?>" 
                  <?php echo $isCancelado ? 'style="filter: grayscale(100%);"' : ''; ?>
                />
              </div>

              <!-- Corpo do card -->
              <div class="card-body" style="padding: 15px;">
                <h3 style="margin-top: 0;">
                  <?php echo htmlspecialchars($pedido['evento_nome']); ?>
                </h3>
                
                <p>
                  <strong>Data/Horário:</strong>
                  <?php echo date("d/m/Y", strtotime($pedido['evento_data'])); ?> 
                  às 
                  <?php echo date("H:i", strtotime($pedido['evento_horario'])); ?>
                </p>
                
                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($pedido['order_id']); ?></p>

                <p>
                  <strong>Validação:</strong> 
                  <?php echo $validadoIcone . ' ' . $validadoTexto; ?>
                </p>
              </div>
            </div> <!-- .ingresso-card -->
          <?php endforeach; ?>
        </div> <!-- .ingressos-cards -->
      <?php else: ?>
        <p class="no-ingressos">Você ainda não possui ingressos.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- (Opcional) Footer -->
  <?php 
    // include('footer.php'); 
  ?>

</body>
</html>
