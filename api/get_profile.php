<?php
// File: api/get_profile.php
require_once "cors.php";
header("Content-Type: application/json; charset=utf-8");
require_once "auth.php";

// Autentica o usuário
$user = clienteAutenticado($conn);
if (!$user) {
    http_response_code(401);
    exit(json_encode(["error" => "Não autorizado."]));
}

// Busca os dados completos do perfil
$sql = "
    SELECT 
        id, 
        nome, 
        email, 
        telefone, 
        foto, 
        tipo_usuario, 
        promotor_id,
        whatsapp_optin
    FROM clientes 
    WHERE id = ? 
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user["id"]);
$stmt->execute();
$res = $stmt->get_result();

if ($profile = $res->fetch_assoc()) {
    // Garante que o whatsapp_optin seja um inteiro para o Flutter
    $profile["whatsapp_optin"] = (int)$profile["whatsapp_optin"];
    
    echo json_encode($profile);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Usuário não encontrado."]);
}