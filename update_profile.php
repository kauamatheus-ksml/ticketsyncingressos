<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

// Verifica se os dados necessários foram enviados
if (!isset($data['field']) || !isset($data['value']) || !isset($data['table'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
    exit;
}

session_start();
include('conexao.php');

$table = $data['table'];

// Permitir apenas os campos: para ambos (nome, email, senha) e para clientes adicionalmente (telefone)
$allowed_fields = ['nome', 'email', 'senha'];
if ($table === 'clientes') {
    $allowed_fields[] = 'telefone';
}
if (!in_array($data['field'], $allowed_fields)) {
    echo json_encode(['success' => false, 'error' => 'Campo não permitido.']);
    exit;
}

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

$field = $data['field'];
$value = $data['value'];

// Se for atualizar a senha, gere o hash
if ($field === 'senha') {
    if (empty($value)) {
        echo json_encode(['success' => false, 'error' => 'Senha não pode ser vazia.']);
        exit;
    }
    $value = password_hash($value, PASSWORD_DEFAULT);
}

$sql = "UPDATE $table SET $field = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$stmt->bind_param("si", $value, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
