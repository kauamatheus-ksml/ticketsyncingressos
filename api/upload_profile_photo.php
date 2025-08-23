<?php
// File: api/upload_profile_photo.php
require_once "cors.php";
header("Content-Type: application/json; charset=utf-8");
require_once "auth.php";

// Autentica o usuário
$user = clienteAutenticado($conn);
if (!$user) {
    http_response_code(401);
    exit(json_encode(["error" => "Não autorizado."]));
}

// Verifica se a requisição é do tipo POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit(json_encode(["error" => "Método não permitido."]));
}

// Verifica se o arquivo foi enviado
if (!isset($_FILES["photo"]) || $_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMsg = isset($_FILES["photo"]) ? "Erro: " . $_FILES["photo"]["error"] : "Nenhum arquivo enviado";
    exit(json_encode(["error" => $errorMsg]));
}

// Configuração para uploads
$uploadDir = "../uploads/profile_photos/";
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Verifica o tipo do arquivo
$fileType = $_FILES["photo"]["type"];
if (!in_array($fileType, $allowedTypes)) {
    http_response_code(400);
    exit(json_encode(["error" => "Tipo de arquivo inválido. Apenas JPG, PNG e GIF são permitidos."]));
}

// Verifica o tamanho do arquivo
if ($_FILES["photo"]["size"] > $maxFileSize) {
    http_response_code(400);
    exit(json_encode(["error" => "Arquivo muito grande. O tamanho máximo é 5MB."]));
}

// Cria o diretório de uploads se não existir
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Gera um nome único para o arquivo
$extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
$filename = "user_" . $user["id"] . "_" . time() . "." . $extension;
$uploadPath = $uploadDir . $filename;

// Move o arquivo enviado
if (move_uploaded_file($_FILES["photo"]["tmp_name"], $uploadPath)) {
    // Atualiza o perfil do usuário com a nova URL da foto
    $photoUrl = "uploads/profile_photos/" . $filename;
    $stmt = $conn->prepare("UPDATE clientes SET foto = ? WHERE id = ?");
    $stmt->bind_param("si", $photoUrl, $user["id"]);
    $result = $stmt->execute();
    
    if ($result) {
        // Obtém a URL completa
        $protocol = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http";
        $host = $_SERVER["HTTP_HOST"];
        $baseUrl = $protocol . "://" . $host . "/";
        $fullUrl = $baseUrl . $photoUrl;
        
        echo json_encode([
            "success" => true,
            "message" => "Foto enviada com sucesso!",
            "photo_url" => $fullUrl
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Falha ao atualizar o perfil com a nova foto."]);
    }
} else {
    http_response_code(500);
    echo json_encode(["error" => "Falha ao mover o arquivo enviado."]);
}