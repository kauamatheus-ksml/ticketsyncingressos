<?php
// gerar_pdf.php

session_start();
include('conexao.php');

// Verifica se o evento_id e as colunas foram enviados
if (!isset($_POST['evento_id']) || !isset($_POST['columns']) || empty($_POST['columns'])) {
    die("Dados insuficientes para gerar o PDF.");
}

$evento_id = intval($_POST['evento_id']);
$selectedColumns = $_POST['columns']; // Array de colunas selecionadas

// Define todas as colunas possíveis e seus respectivos rótulos para o PDF,
// combinando nome e sobrenome em um único campo "Nome Completo"
$allColumns = array(
    'order_id'      => 'Order ID',
    'nome_completo' => 'Nome Completo',
    'email'         => 'Email',
    'valor_total'   => 'Valor',
    'status'        => 'Status',
    'created_at'    => 'Data',
    'ingresso'      => 'Ingresso'
);

// Filtra as colunas selecionadas para garantir que sejam válidas
$columns = array();
foreach ($selectedColumns as $col) {
    if (array_key_exists($col, $allColumns)) {
        $columns[$col] = $allColumns[$col];
    }
}

if (empty($columns)) {
    die("Nenhuma coluna válida selecionada.");
}

// Consulta o nome do evento (sem o ID) na tabela "eventos"
$stmtEvento = $conn->prepare("SELECT nome FROM eventos WHERE id = ?");
$stmtEvento->bind_param("i", $evento_id);
$stmtEvento->execute();
$resultEvento = $stmtEvento->get_result();
if ($rowEvento = $resultEvento->fetch_assoc()){
    $eventoNome = $rowEvento['nome'];
} else {
    $eventoNome = "Evento";
}
$stmtEvento->close();

// Se a coluna "status" foi selecionada e se foram enviados status específicos (via subcaixas),
// constrói um filtro para a query.
$statusFilter = "";
if (in_array("status", $selectedColumns) && isset($_POST['status_options']) && !empty($_POST['status_options'])) {
    // Definindo uma whitelist dos status permitidos (ajuste conforme seu sistema)
    $allowed_statuses = array("approved", "pending", "paid", "cancelled");
    $status_options = $_POST['status_options'];
    $filtered_statuses = array();
    foreach ($status_options as $st) {
        $stLower = strtolower($st);
        if (in_array($stLower, $allowed_statuses)) {
            $filtered_statuses[] = $stLower;
        }
    }
    if (!empty($filtered_statuses)) {
        // Cria uma lista de status para a cláusula IN (com aspas simples)
        $status_list = "'" . implode("','", $filtered_statuses) . "'";
        $statusFilter = " AND status IN ($status_list)";
    }
}

// Consulta os pedidos para o evento informado, combinando "nome" e "sobrenome" em "nome_completo"
// e ordenando em ordem alfabética pelo nome completo. Aplica o filtro de status, se definido.
$query = "SELECT order_id, CONCAT(nome, ' ', sobrenome) AS nome_completo, email, valor_total, status, created_at 
          FROM pedidos 
          WHERE evento_id = ? $statusFilter 
          ORDER BY CONCAT(nome, ' ', sobrenome) ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $evento_id);
$stmt->execute();
$result = $stmt->get_result();
$pedidos = array();
while ($row = $result->fetch_assoc()) {
    $pedidos[] = $row;
}
$stmt->close();
$conn->close();

// Mapeamento dos status para tradução (opcional, se quiser usar no PDF)
$statusTranslations = array(
    'approved'  => 'Aprovado',
    'pending'   => 'Pendente',
    'paid'      => 'Pago',
    'cancelled' => 'Cancelado'
);

// Monta o HTML para o PDF (preto e branco, sem linhas de tabela fortes e com linha sutil abaixo de cada linha)
$html = '<html><head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #000; }
    h2 { text-align: center; margin-bottom: 5px; }
    hr { border: 0; border-top: 1px solid rgba(0,0,0,0.2); margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 8px; text-align: left; border: none; }
    th { font-weight: bold; }
    tbody tr { border-bottom: 1px solid rgba(0,0,0,0.1); }
  </style>
</head><body>';

$html .= '<h2>Lista de Ingressos Comprados para o Evento ' . htmlspecialchars($eventoNome) . '</h2>';
$html .= '<hr>';
$html .= '<table>';
$html .= '<thead><tr>';
// Adiciona a coluna de numeração
$html .= '<th>N°</th>';
foreach ($columns as $col => $header) {
    $html .= '<th>' . $header . '</th>';
}
$html .= '</tr></thead><tbody>';
$counter = 1;
foreach ($pedidos as $pedido) {
    $html .= '<tr>';
    // Coluna de numeração
    $html .= '<td>' . $counter . '</td>';
    foreach ($columns as $col => $header) {
        $cellText = '';
        if ($col == 'ingresso') {
            $cellText = (strtolower($pedido['status']) == 'approved') ? 'Gerado' : '';
        } elseif ($col == 'valor_total') {
            $cellText = "R$ " . number_format($pedido['valor_total'], 2, ',', '.');
        } elseif ($col == 'created_at') {
            $cellText = date("d/m/Y H:i", strtotime($pedido['created_at']));
        } elseif ($col == 'status') {
            $st = strtolower($pedido['status']);
            $cellText = isset($statusTranslations[$st]) ? $statusTranslations[$st] : $pedido['status'];
        } else {
            $cellText = $pedido[$col];
        }
        $html .= '<td>' . htmlspecialchars($cellText) . '</td>';
    }
    $html .= '</tr>';
    $counter++;
}
$html .= '</tbody></table>';
$html .= '</body></html>';

// Inclui o autoloader do Composer para DOMPDF
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;

// Cria uma nova instância do DOMPDF e carrega o HTML
$dompdf = new Dompdf();
$dompdf->loadHtml($html);

// Configura o tamanho do papel para A4 com orientação vertical (portrait)
$dompdf->setPaper('A4', 'portrait');

// Renderiza o HTML para PDF
$dompdf->render();

// Envia o PDF para o navegador para download
$dompdf->stream('lista_ingressos_evento_' . $evento_id . '.pdf', array("Attachment" => 1));

exit();
?>
