<?php
// sucesso.php
session_start();
require 'conexao.php';
require_once __DIR__ . '/vendor/autoload.php'; // Carrega mPDF e PHPMailer

// Verifica se o order_id (external_reference) foi passado
if (!isset($_GET['pedido'])) {
    die("Pedido não especificado.");
}
$orderId = $_GET['pedido'];

// Busca os dados do pedido na tabela "pedidos"
$stmt = $conn->prepare("SELECT * FROM pedidos WHERE order_id = ?");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Pedido não encontrado.");
}
$pedido = $result->fetch_assoc();
$stmt->close();

// Gerar o PDF do ingresso usando mPDF
$mpdf = new \Mpdf\Mpdf();
$html = '<h1>Ingresso - Ticket Sync</h1>';
$html .= '<p><strong>Pedido:</strong> ' . htmlspecialchars($pedido['order_id']) . '</p>';
$html .= '<p><strong>Nome:</strong> ' . htmlspecialchars($pedido['nome']) . ' ' . htmlspecialchars($pedido['sobrenome']) . '</p>';
$html .= '<p><strong>Email:</strong> ' . htmlspecialchars($pedido['email']) . '</p>';
$html .= '<p><strong>Valor Total:</strong> R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</p>';
$html .= '<p><strong>Status:</strong> ' . htmlspecialchars($pedido['status']) . '</p>';
$html .= '<p><strong>Data do Pedido:</strong> ' . htmlspecialchars($pedido['created_at']) . '</p>';
$html .= '<p>Apresente este ingresso na entrada do evento.</p>';

$mpdf->WriteHTML($html);

// Captura o PDF como string
$pdfContent = $mpdf->Output('', 'S');

// Envia o PDF por e-mail utilizando PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    // Configurações do servidor SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';   // Substitua pelo seu host SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'kaua@ticketsync.com.br';    // Substitua pelo seu usuário SMTP
    $mail->Password   = 'Aaku_2004@';                // Substitua pela sua senha SMTP
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 465;
    
    // Remetente e destinatário
    $mail->setFrom('contato@ticketsync.com', 'Ticket Sync');
    $mail->addAddress($pedido['email'], $pedido['nome']);
    
    // Anexa o PDF
    $mail->addStringAttachment($pdfContent, 'ingresso.pdf');
    
    // Conteúdo do e-mail
    $mail->isHTML(true);
    $mail->Subject = 'Seu Ingresso - Ticket Sync';
    $mail->Body    = 'Olá ' . htmlspecialchars($pedido['nome']) . ',<br><br>Seu pagamento foi aprovado com sucesso. Em anexo, segue o seu ingresso. <br><br>Obrigado por comprar conosco!<br><br><strong>Equipe Ticket Sync</strong>';
    
    $mail->send();
    
    echo 'Ingresso enviado para o seu e-mail.';
} catch (Exception $e) {
    echo "O ingresso não pôde ser enviado. Erro: " . $mail->ErrorInfo;
}

$conn->close();
exit();
?>
