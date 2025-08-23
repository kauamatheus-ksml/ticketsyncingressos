<?php
// File: api/update_profile.php
require_once "cors.php";
header("Content-Type: application/json; charset=utf-8");
require_once "auth.php";

// Autentica o usuário
$user = clienteAutenticado($conn);
if (!$user) {
    http_response_code(401);
    exit(json_encode(["error" => "Não autorizado."]));
}

// Lê os dados enviados via POST (JSON)
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    exit(json_encode(["error" => "Dados inválidos."]));
}

// Campos que podem ser atualizados
$updateFields = [];
$params = [];
$types = "";

// Verifica e adiciona os campos a serem atualizados
if (isset($data["nome"])) {
    $updateFields[] = "nome = ?";
    $params[] = trim($data["nome"]);
    $types .= "s";
}

if (isset($data["email"])) {
    // Verifica se o email já existe para outro usuário
    $checkStmt = $conn->prepare("SELECT id FROM clientes WHERE email = ? AND id != ? LIMIT 1");
    $checkStmt->bind_param("si", $data["email"], $user["id"]);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        http_response_code(409);
        exit(json_encode(["error" => "Este email já está em uso por outro usuário."]));
    }
    
    $updateFields[] = "email = ?";
    $params[] = trim($data["email"]);
    $types .= "s";
}

if (isset($data["telefone"])) {
    $updateFields[] = "telefone = ?";
    $params[] = trim($data["telefone"]);
    $types .= "s";
}

if (isset($data["whatsapp_optin"])) {
    $updateFields[] = "whatsapp_optin = ?";
    $params[] = (int)$data["whatsapp_optin"];
    $types .= "i";
}

if (isset($data["senha"]) && !empty($data["senha"])) {
    // Hash da senha
    $hashedPassword = password_hash($data["senha"], PASSWORD_DEFAULT);
    $updateFields[] = "senha = ?";
    $params[] = $hashedPassword;
    $types .= "s";
}

// Se não há campos para atualizar
if (empty($updateFields)) {
    echo json_encode(["message" => "Nenhuma alteração para salvar."]);
    exit;
}

// Constrói a query de atualização
$sql = "UPDATE clientes SET " . implode(", ", $updateFields) . " WHERE id = ?";
$params[] = $user["id"];
$types .= "i";

// Executa a atualização
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$result = $stmt->execute();

if ($result) {
    echo json_encode(["success" => true, "message" => "Perfil atualizado com sucesso!"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Erro ao atualizar o perfil."]);
}