<?php
// transfer_ingresso.php
session_start();
include('conexao.php');

// Carrega autoload do Composer para o PHPMailer
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se requisição veio via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se o usuário está logado (por segurança)
    if (!isset($_SESSION['userid']) || empty($_SESSION['email'])) {
        echo json_encode(["status" => "error", "message" => "Usuário não autenticado."]);
        exit;
    }

    // Dados recebidos via AJAX
    $order_id = $_POST['order_id'] ?? '';
    $transferEmail = $_POST['transfer_email'] ?? '';
    $userEmailLogado = $_SESSION['email'];

    // 1. Verifica se o usuário destinatário existe na tabela clientes
    $sqlCheck = "SELECT id, nome FROM clientes WHERE email = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param('s', $transferEmail);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows === 0) {
        // Usuário não encontrado
        echo json_encode(["status" => "error", "message" => "O e-mail informado não está cadastrado."]);
        exit;
    }
    $rowDestinatario = $resultCheck->fetch_assoc();
    $nomeDestinatario = $rowDestinatario['nome'] ?? '';

    // 2. Verifica se o pedido pertence mesmo ao usuário logado
    $sqlPedido = "SELECT * FROM pedidos WHERE order_id = ? AND email = ?";
    $stmtPedido = $conn->prepare($sqlPedido);
    $stmtPedido->bind_param('ss', $order_id, $userEmailLogado);
    $stmtPedido->execute();
    $resultPedido = $stmtPedido->get_result();
    $pedidoRow = $resultPedido->fetch_assoc();

    if (!$pedidoRow) {
        echo json_encode(["status" => "error", "message" => "Pedido não encontrado para este usuário."]);
        exit;
    }

    // 3. Verifica se status permite transferência (ex: só "approved")
    $statusLower = strtolower($pedidoRow['status']);
    if ($statusLower !== 'approved') {
        echo json_encode(["status" => "error", "message" => "Só é possível transferir ingressos aprovados."]);
        exit;
    }

    // 4. Verifica se ingresso não foi validado ainda
    if ($pedidoRow['ingresso_validado'] == 1) {
        echo json_encode(["status" => "error", "message" => "Não é possível transferir um ingresso já validado."]);
        exit;
    }

    // 5. Atualiza o e-mail do pedido (transferência)
    $sqlUpdate = "UPDATE pedidos SET email = ? WHERE order_id = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param('ss', $transferEmail, $order_id);
    $stmtUpdate->execute();

    if ($stmtUpdate->affected_rows > 0) {
        // Se chegou aqui, deu certo a transferência.
        // ENVIAR E-MAIL PARA QUEM RECEBEU O INGRESSO:
        $nomeRemetente = $pedidoRow['nome'] . ' ' . $pedidoRow['sobrenome'] ?? $userEmailLogado; 
        // ou busque o nome do remetente (caso esteja armazenado em 'nome' e 'sobrenome' na tabela 'pedidos')

        // Assunto e corpo do e-mail
        $assunto = "Você recebeu um ingresso de $nomeRemetente (Ticket Sync)";
        $mensagemHTML = "
            <h3>Olá, {$nomeDestinatario}!</h3>
            <p><strong>{$nomeRemetente}</strong> transferiu um ingresso para você através do <em>Ticket Sync</em>.</p>
            <p>Agora esse ingresso está associado ao seu e-mail <strong>{$transferEmail}</strong>.</p>
            <p>Para visualizar ou imprimir, basta acessar nosso site e fazer login.</p>
            <hr>
            <p>Atenciosamente,<br>Equipe Ticket Sync</p>
        ";

        // (A) Usando PHPMailer
        try {
            $mail = new PHPMailer(true);
            // Configurações de servidor de e-mail
            // (Se você usa servidor local sem SMTP configurado, pode precisar de mais configs)
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            
            // Ajuste conforme seu provedor de hospedagem ou SMTP
            // Exemplo: usando mail() local
            $mail->isMail(); // Usa função mail() do PHP internamente

            // Se tiver SMTP (recomendado):
            // $mail->isSMTP();
            // $mail->Host = 'smtp.seudominio.com';
            // $mail->SMTPAuth = true;
            // $mail->Username = 'usuario';
            // $mail->Password = 'senha';
            // $mail->SMTPSecure = 'tls'; // ou 'ssl'
            // $mail->Port = 587; // ou 465 se SSL

            // Remetente
            $mail->setFrom('kaua@ticketsync.com.br', 'Ticket Sync');
            // Destinatário
            $mail->addAddress($transferEmail, $nomeDestinatario);

            // Conteúdo do e-mail
            $mail->Subject = $assunto;
            $mail->Body    = $mensagemHTML;

            // Envia
            $mail->send();

        } catch (Exception $e) {
            // Se falhar o envio, apenas registra ou exibe um log
            // Mas a transferência já foi feita.
            error_log("Erro ao enviar e-mail de transferência: " . $e->getMessage());
        }

        // Por fim, responde ao AJAX
        echo json_encode(["status" => "success", "message" => "Ingresso transferido com sucesso!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Não foi possível transferir o ingresso."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Método inválido."]);
}
    ?>