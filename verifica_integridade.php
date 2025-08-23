<?php
require 'conexao.php';

$sql = "CHECK TABLE pagamentos, usuarios, eventos"; // Adicione outras tabelas usadas
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo "Tabela: " . $row['Table'] . " - Status: " . $row['Msg_text'] . "<br>";
}

// Fecha a conexão
$conn->close();
?>
