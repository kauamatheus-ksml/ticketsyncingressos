<?php
// pdv.php

// Exibir erros para depuração (desative em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}
include_once('check_permissions.php');
if (!checkPermission("pdv")) {
    echo "Você não possui permissão para acessar esta página!";
    exit();
}
include('conexao.php');

// Recupera os dados do administrador logado (para filtrar os eventos)
$adminid = intval($_SESSION['adminid']);
$admin_query = "SELECT * FROM administradores WHERE id = $adminid LIMIT 1";
$admin_result = $conn->query($admin_query);
if ($admin_result && $admin_result->num_rows > 0) {
    $admin = $admin_result->fetch_assoc();
} else {
    header("Location: login.php");
    exit();
}

// Inicializa as variáveis de mensagem via sessão
if (!isset($_SESSION['error'])) {
    $_SESSION['error'] = "";
}
if (!isset($_SESSION['success'])) {
    $_SESSION['success'] = "";
}

// Inclui o autoload do Composer (confira o caminho correto)
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Processamento do pedido (quando o formulário é enviado)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_submit'])) {
    // Recebe e sanitiza os dados do formulário
    $evento_id = intval($_POST['evento_id']);
    $ticket_id = intval($_POST['ticket_id']);
    $quantity = intval($_POST['quantity']);
    $nome = $conn->real_escape_string(trim($_POST['nome']));
    $sobrenome = $conn->real_escape_string(trim($_POST['sobrenome']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $forma_pagamento = $conn->real_escape_string(trim($_POST['forma_pagamento']));
    
    // Consulta o ingresso selecionado para obter seu preço e quantidade disponível
    $ticket_query = "SELECT * FROM ingressos WHERE id = $ticket_id AND evento_id = $evento_id LIMIT 1";
    $ticket_result = $conn->query($ticket_query);
    if ($ticket_result && $ticket_result->num_rows > 0) {
        $ticket = $ticket_result->fetch_assoc();
        if ($ticket['quantidade'] < $quantity) {
            $_SESSION['error'] = "Quantidade solicitada não disponível para este ingresso.";
        } else {
            // Calcula o valor total do pedido
            $valor_total = $ticket['preco'] * $quantity;
            
            // Gera um order_id conforme a mesma lógica usada em outras partes do sistema
            $sqlOrder = "SELECT MAX(CAST(order_id AS UNSIGNED)) AS last_order FROM pedidos";
            $resultOrder = $conn->query($sqlOrder);
            if ($resultOrder && $row = $resultOrder->fetch_assoc()) {
                $lastOrder = (int)$row['last_order'];
                $newOrder = $lastOrder + 1;
            } else {
                $newOrder = 1;
            }
            $order_id = str_pad($newOrder, 8, "0", STR_PAD_LEFT);
            
            $created_at = date('Y-m-d H:i:s');
            // Define o status como approved (pagamento validado)
            $status = 'approved';
            
            // Insere o pedido na tabela "pedidos" marcando que veio do PDV (pdv = 1)
            $sql = "INSERT INTO pedidos 
                        (order_id, nome, sobrenome, email, valor_total, quantidade_total, status, created_at, evento_id, forma_pagamento, pdv)
                    VALUES 
                        ('$order_id', '$nome', '$sobrenome', '$email', '$valor_total', '$quantity', '$status', '$created_at', '$evento_id', '$forma_pagamento', 1)";
            if ($conn->query($sql)) {
                $_SESSION['success'] = "Pedido realizado com sucesso. Order ID: $order_id";
                
                // --- Geração do Ingresso e Envio de E‑mail ---
                // Busca os dados do evento
                $event_query = "SELECT * FROM eventos WHERE id = $evento_id LIMIT 1";
                $event_result = $conn->query($event_query);
                if ($event_result && $event_result->num_rows > 0) {
                    $evento = $event_result->fetch_assoc();
                } else {
                    $evento = [];
                }
                $nomeEvento    = isset($evento['nome']) ? $evento['nome'] : "Evento";
                $dataEvento    = isset($evento['data_inicio']) ? date("d/m/Y", strtotime($evento['data_inicio'])) : "Data não informada";
                $horarioEvento = !empty($evento['hora_inicio']) ? date("H:i", strtotime($evento['hora_inicio'])) : "";
                $local         = isset($evento['local']) ? $evento['local'] : "Local não informado";
                $atracoes      = !empty($evento['atracoes']) ? $evento['atracoes'] : "Não especificadas";
                $logo          = !empty($evento['logo']) ? $evento['logo'] : "https://ticketsync.com.br/uploads/ticketsyhnklogo_branco.png";
                
                // Gera um Ticket Code (6 dígitos aleatórios)
                $ticketCode = sprintf("%06d", rand(1, 999999));
                
                // Gera QR Code com Endroid QR Code
                $writer = new PngWriter();
                $qrCode = QrCode::create($ticketCode)
                            ->setSize(150)
                            ->setMargin(10);
                $resultQr = $writer->write($qrCode);
                $qrDataUri = $resultQr->getDataUri();
                
                // Monta o HTML do ingresso para gerar o PDF
                $html = "
                <html>
                <head>
                  <meta charset='UTF-8'>
                  <title>Ingresso - Order {$order_id}</title>
                  <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                    .ticket-container { max-width: 600px; margin: 20px auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
                    .header { background-color: #002f6d; padding: 20px; text-align: center; }
                    .header img { max-width: 200px; }
                    .content { padding: 20px; }
                    .content h2 { color: #002f6d; margin-bottom: 10px; }
                    .content .info { margin-bottom: 10px; line-height: 1.5; }
                    .qrcode { text-align: center; margin-top: 20px; }
                    .qrcode p { font-size: 12px; color: #555; margin: 5px 0 0; }
                    .footer { background-color: #002f6d; padding: 10px; text-align: center; color: #fff; font-size: 12px; }
                  </style>
                </head>
                <body>
                  <div class='ticket-container'>
                    <div class='header'>
                      <img src='{$logo}' alt='Logo Evento'>
                    </div>
                    <div class='content'>
                      <h2>Ingresso Confirmado</h2>
                      <div class='info'>
                        <strong>Evento:</strong> " . htmlspecialchars($nomeEvento) . "<br>
                        <strong>Data:</strong> $dataEvento" . ($horarioEvento ? " - $horarioEvento" : "") . "<br>
                        <strong>Local:</strong> " . htmlspecialchars($local) . "<br>
                        <strong>Atrações:</strong> " . htmlspecialchars($atracoes) . "
                      </div>
                      <hr>
                      <div class='info'>
                        <strong>Order ID:</strong> " . htmlspecialchars($order_id) . "<br>
                        <strong>Cliente:</strong> " . htmlspecialchars($nome . ' ' . $sobrenome) . "<br>
                        <strong>Email:</strong> " . htmlspecialchars($email) . "<br>
                        <strong>Valor:</strong> R$ " . number_format($valor_total, 2, ',', '.') . "<br>
                        <strong>Ticket Code:</strong> " . $ticketCode . "
                      </div>
                      <div class='qrcode'>
                        <p>Escaneie o QR Code para validar seu ingresso:</p>
                        <img src='{$qrDataUri}' alt='QR Code' style='width:150px; height:150px;'/><br>
                      </div>
                      <p style='text-align: center; font-size: 14px;'>Apresente este ingresso na entrada do evento.</p>
                    </div>
                    <div class='footer'>
                      © " . date("Y") . " Ticket Sync. Todos os direitos reservados.
                    </div>
                  </div>
                </body>
                </html>
                ";
                
                // Gera o PDF com Dompdf
                $dompdf = new Dompdf();
                $options = $dompdf->getOptions();
                $options->setIsRemoteEnabled(true);
                $dompdf->setOptions($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfContent = $dompdf->output();
                
                // (Opcional) Salva o PDF na pasta /pdfs
                $pdfDir = __DIR__ . '/pdfs';
                if (!is_dir($pdfDir)) {
                    mkdir($pdfDir, 0777, true);
                }
                $pdfFileName = "ingresso_{$order_id}.pdf";
                $pdfFilePath = $pdfDir . '/' . $pdfFileName;
                file_put_contents($pdfFilePath, $pdfContent);
                
                // Envio do e‑mail com PHPMailer
                $assuntoEmail = "Confirmação de Pagamento e Ingresso do $nomeEvento";
                $mensagemHTML = "
                    <h2>Olá, " . htmlspecialchars($nome) . " " . htmlspecialchars($sobrenome) . "!</h2>
                    <p>Seu pagamento no valor de <strong>R$ " . number_format($valor_total, 2, ',', '.') . "</strong> foi aprovado com sucesso!</p>
                    <p>Segue em anexo seu ingresso em PDF.</p>
                    <p><strong>Ticket Code:</strong> " . $ticketCode . "</p>
                    <p><strong>Equipe Ticket Sync</strong></p>
                ";
                
                $mail = new PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                
                try {
                    // Configuração SMTP – ajuste conforme seu servidor e credenciais
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.hostinger.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'kaua@ticketsync.com.br';
                    $mail->Password   = 'Aaku_2004@';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;
                    
                    // Desativa debug SMTP para produção
                    $mail->SMTPDebug = 0;
                    
                    // Utilize o mesmo endereço autenticado para o remetente
                    $mail->setFrom('kaua@ticketsync.com.br', 'Ticket Sync');
                    $mail->addAddress($email, $nome);
                    $mail->isHTML(true);
                    $mail->Subject = $assuntoEmail;
                    $mail->Body    = $mensagemHTML;
                    $mail->addStringAttachment($pdfContent, "ingresso_{$order_id}.pdf", 'base64', 'application/pdf');
                    $mail->send();
                    
                    // Atualiza o pedido no BD com o PDF e o Ticket Code
                    $stmt_pdf = $conn->prepare("UPDATE pedidos SET pdf = ?, ticket_code = ? WHERE order_id = ?");
                    $stmt_pdf->bind_param("sss", $pdfContent, $ticketCode, $order_id);
                    $stmt_pdf->execute();
                    $stmt_pdf->close();
                    
                    $_SESSION['success'] .= " E‑mail enviado com sucesso!";
                } catch (Exception $e) {
                    $_SESSION['error'] .= " Erro ao enviar e‑mail: " . $mail->ErrorInfo;
                }
            } else {
                $_SESSION['error'] = "Erro ao realizar pedido: " . $conn->error;
            }
        }
    } else {
        $_SESSION['error'] = "Ingresso não encontrado.";
    }
    // Redireciona após o POST para evitar reenvio do formulário (PRG)
    header("Location: pdv.php?event_id=" . $evento_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>PDV - Ponto de Venda</title>
  <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon"/>
  <!-- Font Awesome -->
  <link rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" 
        integrity="sha512-Fo3rlrZj/k7ujTnH2N2bNqykVNpyFJpN7Mx5jZ0ip2qZf9ObK0MZJxY9w+cn0bXn8N+Ge2B9RVzOfX6swbZZmw==" 
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <!-- CSS Unificado Atualizado -->
  <link rel="stylesheet" href="css/pdv.css">
  <script>
    // Exibe overlay de "Processando..." quando o formulário for submetido
    function showProcessingOverlay() {
      document.getElementById('processingOverlay').classList.add('active');
    }
  </script>
</head>
<body class="pdv-body">
  <?php include('header_admin.php'); ?>
  
  <!-- Overlay de processamento -->
  <div id="processingOverlay">
    <div class="overlay-content">
      <div class="spinner"></div>
      <h2>Aguarde...</h2>
      <p>Processando seu pedido</p>
    </div>
  </div>

  <div class="pdv-container">
    <h1><i class="fas fa-cash-register"></i> PDV - Ponto de Venda</h1>
    <?php
    // Exibe mensagens de erro ou sucesso, se houver
    if (!empty($_SESSION['error'])) {
        echo "<div class='msg error'>" . $_SESSION['error'] . "</div>";
        $_SESSION['error'] = "";
    }
    if (!empty($_SESSION['success'])) {
        echo "<div class='msg success'>Pedido Concluído!<br>" . $_SESSION['success'] . "</div>";
        $_SESSION['success'] = "";
    }
    
    // Se um evento for selecionado via GET (event_id), exibe seus detalhes e os ingressos disponíveis
    if (isset($_GET['event_id'])) {
        $evento_id = intval($_GET['event_id']);
        $where_extra = ($admin['master'] == 1) ? "" : " AND promotor_id = $adminid";
        $event_query = "SELECT * FROM eventos WHERE id = $evento_id $where_extra LIMIT 1";
        $event_result = $conn->query($event_query);
        if ($event_result && $event_result->num_rows > 0) {
            $evento = $event_result->fetch_assoc();
            echo "<h2>" . htmlspecialchars($evento['nome']) . "</h2> <br>";
            echo "<p>Data: " . date("d/m/Y", strtotime($evento['data_inicio'])) . " | Horário: " . date("H:i", strtotime($evento['hora_inicio'])) . "</p> <br>";
      
            // Lista os ingressos liberados para este evento
            $ingressos_query = "SELECT * FROM ingressos WHERE evento_id = $evento_id AND liberado = 1";
            $ingressos_result = $conn->query($ingressos_query);
            if ($ingressos_result && $ingressos_result->num_rows > 0) {
                echo "<h3>Selecione o tipo de ingresso</h3>";
                echo "<form method='post' action='pdv.php?event_id={$evento_id}' onsubmit='showProcessingOverlay()'>";
                echo "<input type='hidden' name='evento_id' value='{$evento_id}'>";
                echo "<table>";
                echo "<tr>
                        <th>Selecionar</th>
                        <th>Tipo</th>
                        <th>Preço</th>
                        <th>Disponível</th>
                      </tr>";
                while ($ingresso = $ingressos_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td><input type='radio' name='ticket_id' value='" . $ingresso['id'] . "' required></td>";
                    echo "<td>" . htmlspecialchars($ingresso['tipo_ingresso']) . "</td>";
                    echo "<td>R$ " . number_format($ingresso['preco'], 2, ',', '.') . "</td>";
                    echo "<td>" . intval($ingresso['quantidade']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "<label>Quantidade: <input type='number' name='quantity' min='1' value='1' required></label>";
                echo "<h3>Dados do Comprador</h3>";
                echo "<label>Nome: <input type='text' name='nome' required></label>";
                echo "<label>Sobrenome: <input type='text' name='sobrenome' required></label>";
                echo "<label>Email: <input type='email' name='email' required></label>";
                echo "<h3>Forma de Pagamento</h3>";
                echo "<label><input type='radio' name='forma_pagamento' value='pix' required> Pix</label>";
                echo "<label><input type='radio' name='forma_pagamento' value='cartao'> Cartão de Crédito</label>";
                echo "<label><input type='radio' name='forma_pagamento' value='dinheiro'> Dinheiro</label>";
                echo "<input type='submit' name='order_submit' value='Finalizar Pedido'>";
                echo "</form>";
                echo "<br><a href='pdv.php' class='btn btn-primary'>Voltar para a lista de eventos</a>";
            } else {
                echo "<p>Nenhum ingresso disponível para este evento.</p>";
                echo "<a href='pdv.php' class='btn btn-primary'>Voltar para a lista de eventos</a>";
            }
        } else {
            echo "<p>Evento não encontrado ou não vinculado a este administrador.</p>";
            echo "<a href='pdv.php' class='btn btn-primary'>Voltar para a lista de eventos</a>";
        }
    } else {
        // Lista todos os eventos vinculados ao administrador (ou todos se for master)
        echo "<h2>Selecione um Evento</h2>";
        if ($admin['master'] == 1) {
            $event_query = "SELECT * FROM eventos ORDER BY data_inicio DESC";
        } else {
            $event_query = "SELECT * FROM eventos WHERE promotor_id = $adminid ORDER BY data_inicio DESC";
        }
        $result = $conn->query($event_query);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<div class='event'>";
                if (!empty($row['logo'])) {
                    echo "<img src='" . htmlspecialchars($row['logo']) . "' alt='Logo do evento'>";
                }
                echo "<div class='event-info'>";
                echo "<h3>" . htmlspecialchars($row['nome']) . "</h3>";
                echo "<p>Data: " . date("d/m/Y", strtotime($row['data_inicio'])) . " - Horário: " . date("H:i", strtotime($row['hora_inicio'])) . "</p>";
                echo "<p>Local: " . htmlspecialchars($row['local']) . "</p>";
                echo "</div>";
                echo "<a href='pdv.php?event_id=" . $row['id'] . "' class='btn btn-primary'>Selecionar Evento</a>";
                echo "</div>";
            }
        } else {
            echo "<p>Nenhum evento encontrado.</p>";
        }
    }
    ?>
  </div>
</body>
</html>
<?php $conn->close(); ?>
