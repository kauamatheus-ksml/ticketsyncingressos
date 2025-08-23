<?php
header('Content-Type: application/json');
session_start();
include('conexao.php');

if (!isset($_FILES['profile_image'])) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.']);
    exit;
}

if (!isset($_POST['table'])) {
    echo json_encode(['success' => false, 'error' => 'Tabela não informada.']);
    exit;
}

$table = $_POST['table'];

// Obtém o ID do usuário de acordo com a tabela informada
if ($table === 'clientes') {
    if (!isset($_SESSION['userid'])) {
        echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
        exit;
    }
    $userId = $_SESSION['userid'];
} elseif ($table === 'funcionarios') {
    if (!isset($_SESSION['funcionarioid'])) {
        echo json_encode(['success' => false, 'error' => 'Usuário não autenticado.']);
        exit;
    }
    $userId = $_SESSION['funcionarioid'];
} else {
    echo json_encode(['success' => false, 'error' => 'Tabela inválida.']);
    exit;
}

$target_dir = "uploads/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0775, true);
}

$file = $_FILES['profile_image'];
$target_file = $target_dir . basename($file['name']);
if (move_uploaded_file($file['tmp_name'], $target_file)) {
    // Atualiza o banco de dados na tabela adequada (supondo que a coluna seja "foto")
    $sql = "UPDATE $table SET foto = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $target_file, $userId);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'image_url' => $target_file]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo.']);
}
$conn->close();
?>
