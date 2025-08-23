<?php
//conexao.php

$servername = "p:srv406.hstgr.io"; // Adicione 'p:' antes do hostname para conexões persistentes
$username   = "u383946504_ticketsync";
$password   = "Aaku_2004@";
$dbname     = "u383946504_ticketsync";

// Cria a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
?>
