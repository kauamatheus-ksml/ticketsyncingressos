<?php
// File: api/auth.php
require_once "conexao.php";

function clienteAutenticado($conn) {
    // Obtém todos os cabeçalhos da requisição
    $headers = getallheaders();
    $authHeader = $headers["Authorization"] ?? "";

    // Se o header usar o esquema "Bearer", remova essa parte e pegue somente o token
    if (stripos($authHeader, "Bearer ") === 0) {
        $token = trim(substr($authHeader, 7));
    } else {
        $token = trim($authHeader);
    }
    
    // Se não houver token, retorna false
    if (!$token) return false;

    // Consulta no banco de dados, verificando se o token existe e se ainda é válido (token_validade > NOW())
    $stmt = $conn->prepare("
        SELECT id, nome, email
        FROM clientes
        WHERE token_red = ? AND token_validade > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
