<?php
include('conexao.php');
session_start();
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $tabela = $_GET['tabela'];

    // Deleta o registro da tabela especificada
    $sql = "DELETE FROM $tabela WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        echo "Registro excluído com sucesso!";
    } else {
        echo "Erro ao excluir registro: " . $conn->error;
    }

    // Redireciona de volta para a página de origem
    $origem = $_GET['origem'];
    header("Location: $origem");
    exit();
} else {
    echo "ID ou tabela não fornecidos.";
}
$conn->close();
?>
