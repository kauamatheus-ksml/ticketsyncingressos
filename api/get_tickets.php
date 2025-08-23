<?php
// File: api/get_tickets.php
ini_set('display_errors', 0);
error_reporting(0);

require_once "cors.php";
header("Content-Type: application/json; charset=utf-8");
require_once "auth.php";

// Autentica o usuário
$user = clienteAutenticado($conn);
if (!$user) {
    http_response_code(401);
    exit(json_encode(["error" => "Não autorizado."]));
}

// Busca apenas pedidos approved
$sql = "
  SELECT 
    p.id,
    p.order_id,
    p.ticket_code,
    p.quantidade_total,
    p.valor_total,
    p.status,
    p.created_at,
    p.evento_id,
    p.ingresso_validado,
    p.nome            AS comprador_nome,
    p.sobrenome       AS comprador_sobrenome,
    e.nome            AS evento_nome,
    e.local           AS evento_local,
    e.data_inicio     AS evento_data,
    e.hora_inicio     AS evento_horario,
    -- Construindo a URL completa da imagem diretamente no SQL
    CONCAT('https://ticketsync.com.br/uploads/', e.logo) AS evento_logo
  FROM pedidos p
  JOIN eventos e ON p.evento_id = e.id
  WHERE p.email         = ?
    AND p.arquivado     = 0
    AND LOWER(p.status) = 'approved'
  ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user["email"]);
$stmt->execute();
$res = $stmt->get_result();

$tickets = [];
while ($row = $res->fetch_assoc()) {
    $tickets[] = [
        "id"                  => (int)$row["id"],
        "order_id"            => $row["order_id"],
        "ticket_code"         => $row["ticket_code"],
        "quantidade_total"    => (int)$row["quantidade_total"],
        "valor_total"         => (float)$row["valor_total"],
        "status"              => $row["status"],
        "created_at"          => $row["created_at"],
        "evento_id"           => (int)$row["evento_id"],
        "ingresso_validado"   => (int)$row["ingresso_validado"],
        "comprador_nome"      => $row["comprador_nome"],
        "comprador_sobrenome" => $row["comprador_sobrenome"],
        "evento_nome"         => $row["evento_nome"],
        "evento_local"        => $row["evento_local"],
        "evento_data"         => $row["evento_data"],
        "evento_horario"      => $row["evento_horario"],
        "evento_logo"         => $row["evento_logo"],
    ];
}

echo json_encode(["tickets" => $tickets], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;