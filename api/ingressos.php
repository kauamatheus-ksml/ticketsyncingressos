<?php
// File: api/login.php

require_once "cors.php"; // ← Adicione isto
header("Content-Type: application/json; charset=utf-8");
require_once "conexao.php";

// Lê os dados enviados via POST (JSON)
$data  = json_decode(file_get_contents("php://input"), true);
$email = trim($data["email"] ?? "");
$senha = trim($data["senha"] ?? "");

// Verifica se email e senha foram enviados
if (!$email || !$senha) {
    http_response_code(400);
    exit(json_encode(["error" => "Email e senha são obrigatórios."]));
}

// Busca o cliente
$stmt = $conn->prepare("SELECT id, nome, senha FROM clientes WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res && ($row = $res->fetch_assoc())) {
    if (password_verify($senha, $row["senha"])) {
        // Gera token e salva no banco
        $token  = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+24 hours"));

        $upd = $conn->prepare("UPDATE clientes SET token_red = ?, token_validade = ? WHERE id = ?");
        $upd->bind_param("ssi", $token, $expiry, $row["id"]);
        $upd->execute();

        // Retorna dados do cliente + token
        echo json_encode([
            "success" => true,
            "cliente" => [
                "id"    => $row["id"],
                "nome"  => $row["nome"],
                "email" => $email
            ],
            "token"   => $token,
            "expires" => $expiry
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Credenciais inválidas."]);
    }
} else {
    http_response_code(401);
    echo json_encode(["error" => "Credenciais inválidas."]);
}


