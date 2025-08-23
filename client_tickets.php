<?php
// client_tickets.php
session_start();

// Se o cliente não estiver logado, redireciona para a página de login
if (!isset($_SESSION['cliente_email'])) {
    header("Location: client_login.php");
    exit();
}

include('conexao.php');

$cliente_email = $_SESSION['cliente_email'];

// Consulta os ingressos (pedidos) do cliente na tabela "pedidos"
$stmt = $conn->prepare("SELECT order_id, nome, sobrenome, email, valor_total, created_at, ticket_code, status FROM pedidos WHERE email = ?");
$stmt->bind_param("s", $cliente_email);
$stmt->execute();
$result = $stmt->get_result();

$tickets = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Meus Ingressos</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; }
        h2 { color: #002f6d; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #002f6d; color: #fff; }
        a { color: #002f6d; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .logout { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <h2>Bem-vindo, <?php echo htmlspecialchars($_SESSION['cliente_nome']); ?>!</h2>
    <h3>Meus Ingressos Comprados</h3>
    <?php if (empty($tickets)): ?>
        <p>Você não possui ingressos comprados.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Valor</th>
                    <th>Data de Compra</th>
                    <th>Ticket Code</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tickets as $ticket): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['nome'] . " " . $ticket['sobrenome']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['email']); ?></td>
                        <td>R$ <?php echo number_format($ticket['valor_total'], 2, ',', '.'); ?></td>
                        <td><?php echo date("d/m/Y H:i", strtotime($ticket['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($ticket['ticket_code']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="logout">
        <a href="logout.php">Sair</a>
    </div>
</body>
</html>
