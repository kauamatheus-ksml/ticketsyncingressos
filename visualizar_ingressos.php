<?php
session_start();
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

include('conexao.php');

// Consulta os ingressos adquiridos
$sqlIngressosAdquiridos = "
    SELECT vendas.*, 
           ingressos.tipo_ingresso, 
           ingressos.preco, 
           eventos.nome AS evento_nome, 
           clientes.nome AS cliente_nome, 
           'cliente' AS tipo_usuario
    FROM vendas
    LEFT JOIN ingressos ON vendas.ingresso_id = ingressos.id
    LEFT JOIN eventos ON ingressos.evento_id = eventos.id
    LEFT JOIN clientes ON vendas.usuario_id = clientes.id
    ORDER BY vendas.data_venda DESC";

$resultIngressosAdquiridos = $conn->query($sqlIngressosAdquiridos);

if (!$resultIngressosAdquiridos) {
    die("Erro na consulta SQL: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingressos Adquiridos</title>
    <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
    <link rel="stylesheet" href="css/visualizar_ingressos_custom.css">
</head>
<body>
    <header class="visualizar-ingressos-header">
        <h1 class="visualizar-ingressos-header-title">Ingressos Adquiridos</h1>
    </header>
    <div class="visualizar-ingressos-container">
        <h2 class="visualizar-ingressos-title">Ingressos Adquiridos</h2>
        <table class="visualizar-ingressos-table">
            <tr class="visualizar-ingressos-table-header">
                <th class="visualizar-ingressos-table-cell">Nome do Cliente</th>
                <th class="visualizar-ingressos-table-cell">Tipo de Ingresso</th>
                <th class="visualizar-ingressos-table-cell">Tipo de Usuário</th>
                <th class="visualizar-ingressos-table-cell">Data e Hora da Compra</th>
                <th class="visualizar-ingressos-table-cell">Valor</th>
            </tr>
            <?php while ($row = $resultIngressosAdquiridos->fetch_assoc()): ?>
                <tr class="visualizar-ingressos-table-row">
                    <td class="visualizar-ingressos-table-cell"><?php echo htmlspecialchars($row['cliente_nome']); ?></td>
                    <td class="visualizar-ingressos-table-cell"><?php echo htmlspecialchars($row['tipo_ingresso']); ?></td>
                    <td class="visualizar-ingressos-table-cell"><?php echo ucfirst(htmlspecialchars($row['tipo_usuario'])); ?></td>
                    <td class="visualizar-ingressos-table-cell"><?php echo date("d/m/Y H:i", strtotime($row['data_venda'])); ?></td>
                    <td class="visualizar-ingressos-table-cell">R$ <?php echo number_format($row['preco'], 2, ',', '.'); ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
        <form class="visualizar-ingressos-form" action="export_ingressos.php" method="post">
            <button type="submit" name="export_txt" class="visualizar-ingressos-btn">Exportar para TXT</button>
            <button type="submit" name="export_docx" class="visualizar-ingressos-btn">Exportar para DOCX</button>
        </form>
    </div>
</body>
</html>
