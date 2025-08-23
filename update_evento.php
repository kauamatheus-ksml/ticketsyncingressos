<?php
// update_evento.php
include('conexao.php');
session_start();
if (!isset($_SESSION['adminid'])) {
   echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
   exit();
}
$promotorId = $_SESSION['adminid'];

$id = intval($_POST['id']);
$nome = isset($_POST['nome']) ? $_POST['nome'] : '';
$data = isset($_POST['data']) ? $_POST['data'] : '';
$horario = isset($_POST['horario']) ? $_POST['horario'] : '';
$local = isset($_POST['local']) ? $_POST['local'] : '';
$atracoes = isset($_POST['atracoes']) ? $_POST['atracoes'] : '';

$stmt = $conn->prepare("UPDATE eventos SET nome = ?, data = ?, horario = ?, local = ?, atracoes = ? WHERE id = ? AND promotor_id = ?");
$stmt->bind_param("ssssssi", $nome, $data, $horario, $local, $atracoes, $id, $promotorId);
if($stmt->execute()){
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
$stmt->close();
$conn->close();
?>
