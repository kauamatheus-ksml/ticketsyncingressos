<?php
//conexao.php

$servername = "p:srv1783.hstgr.io"; // Adicione 'p:' antes do hostname para conexões persistentes
$username   = "u153409541_ingressos";
$password   = "Aaku_2004@";
$dbname     = "u153409541_ingressos";

// Cria a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
?>
