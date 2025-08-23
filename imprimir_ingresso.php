<?php
session_start();
include('conexao.php');

// Verifica se o usuário está logado (opcional, mas recomendado)
if (!isset($_SESSION['userid']) || empty($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Verifica se o order_id foi enviado via GET
if (!isset($_GET['order_id'])) {
    echo "Order ID não especificado.";
    exit();
}

$order_id = $_GET['order_id'];

// Consulta o PDF do ingresso na tabela 'pedidos'
$stmt = $conn->prepare("SELECT pdf FROM pedidos WHERE order_id = ?");
if (!$stmt) {
    echo "Erro na preparação da query: " . $conn->error;
    exit();
}
$stmt->bind_param("s", $order_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo "Ingresso não encontrado.";
    exit();
}

$stmt->bind_result($pdf);
$stmt->fetch();
$stmt->close();
$conn->close();

if (empty($pdf)) {
    echo "PDF do ingresso não encontrado.";
    exit();
}

// Define os cabeçalhos para download do PDF
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=\"ingresso_{$order_id}.pdf\"");
header("Content-Length: " . strlen($pdf));

// Envia o conteúdo do PDF para o navegador
echo $pdf;
exit();
?>
