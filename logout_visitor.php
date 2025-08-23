<?php
session_start();
// Remove apenas a variável do visitante (ou destrua a sessão, conforme sua lógica)
unset($_SESSION['visitor']);
unset($_SESSION['visitor_name']);
header("Location: index.php");
exit();
?>
