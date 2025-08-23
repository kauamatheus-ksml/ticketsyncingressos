<?php
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
    LEFT JOIN clientes ON vendas.usuario_id_cliente = clientes.id
    ORDER BY vendas.data_venda DESC";

$result = $conn->query($sqlIngressosAdquiridos);

if (!$result) {
    die("Erro na consulta SQL: " . $conn->error);
}

$ingressos = [];
while ($row = $result->fetch_assoc()) {
    $ingressos[] = $row;
}

// Exportar para TXT
if (isset($_POST['export_txt'])) {
    $filename = "ingressos_" . date("YmdHis") . ".txt";
    header("Content-Type: text/plain");
    header("Content-Disposition: attachment; filename=$filename");

    $txtOutput = "Nome do Cliente\tTipo de Ingresso\tTipo de Usuário\tData e Hora da Compra\tValor\n";
    foreach ($ingressos as $ingresso) {
        $txtOutput .= htmlspecialchars($ingresso['cliente_nome']) . "\t" .
                      htmlspecialchars($ingresso['tipo_ingresso']) . "\t" .
                      ucfirst(htmlspecialchars($ingresso['tipo_usuario'])) . "\t" .
                      date("d/m/Y H:i", strtotime($ingresso['data_venda'])) . "\t" .
                      "R$ " . number_format($ingresso['preco'], 2, ',', '.') . "\n";
    }
    echo $txtOutput;
    exit();
}

// Exportar para DOCX
if (isset($_POST['export_docx'])) {
    require_once 'vendor/autoload.php'; // Caminho para o autoload do Composer

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addTitle("Ingressos Adquiridos", 1);
    
    // Definir estilo da tabela
    $tableStyle = [
        'borderSize' => 6, 
        'borderColor' => '999999', 
        'cellMargin' => 80
    ];
    $firstRowStyle = [
        'bgColor' => 'CCCCCC'
    ];
    $phpWord->addTableStyle('IngressoTable', $tableStyle, $firstRowStyle);

    $table = $section->addTable('IngressoTable');

    // Cabeçalhos da Tabela
    $table->addRow();
    $table->addCell(2000)->addText("Nome do Cliente", ['bold' => true]);
    $table->addCell(2000)->addText("Tipo de Ingresso", ['bold' => true]);
    $table->addCell(2000)->addText("Tipo de Usuário", ['bold' => true]);
    $table->addCell(2000)->addText("Data e Hora da Compra", ['bold' => true]);
    $table->addCell(2000)->addText("Valor", ['bold' => true]);

    // Dados da Tabela
    foreach ($ingressos as $ingresso) {
        $table->addRow();
        $table->addCell(2000)->addText(htmlspecialchars($ingresso['cliente_nome']));
        $table->addCell(2000)->addText(htmlspecialchars($ingresso['tipo_ingresso']));
        $table->addCell(2000)->addText(ucfirst(htmlspecialchars($ingresso['tipo_usuario'])));
        $table->addCell(2000)->addText(date("d/m/Y H:i", strtotime($ingresso['data_venda'])));
        $table->addCell(2000)->addText("R$ " . number_format($ingresso['preco'], 2, ',', '.'));
    }

    $filename = "ingressos_" . date("YmdHis") . ".docx";
    header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    header("Content-Disposition: attachment; filename=$filename");

    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save("php://output");
    exit();
}

$conn->close();
?>
