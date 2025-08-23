<?php
include('conexao.php');
session_start();

if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'];
$sql = "DELETE FROM eventos WHERE id='$id'";
if ($conn->query($sql) === TRUE) {
    echo "Evento excluído com sucesso!";
} else {
    echo "Erro: " . $sql . "<br>" . $conn->error;
}

header("Location: eventos.php");
exit();

$conn->close();
?>
