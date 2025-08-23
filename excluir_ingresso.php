<?php
include('conexao.php');
session_start();
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Deleta o ingresso do banco de dados
    $sql = "DELETE FROM ingressos WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        echo "Ingresso excluído com sucesso!";
    } else {
        echo "Erro ao excluir ingresso: " . $conn->error;
    }

    // Redireciona para a página de ingressos
    header("Location: ingressos.php");
    exit();
} else {
    echo "ID do ingresso não fornecido.";
}
$conn->close();
?>
