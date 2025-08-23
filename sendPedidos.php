<?php
// sendPedidos.php

// Inclui a conexão com o banco de dados
include 'conexao.php';

// Consulta pedidos com status "aprovado" que ainda não foram enviados
$query = "SELECT * FROM pedidos WHERE status = 'aprovado' AND enviado = 0";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while($pedido = $result->fetch_assoc()) {
        // Verifica se o campo numero_whatsapp existe e não está vazio
        if (!isset($pedido['numero_whatsapp']) || empty($pedido['numero_whatsapp'])) {
            echo "Pedido ID " . $pedido['id'] . " não possui número de WhatsApp.\n";
            continue;
        }
        
        // Monta a mensagem com os dados do pedido
        $mensagem = "Pedido Aprovado!\n" .
                    "Order ID: " . $pedido['order_id'] . "\n" .
                    "Nome: " . $pedido['nome'] . " " . $pedido['sobrenome'] . "\n" .
                    "Email: " . $pedido['email'] . "\n" .
                    "Valor Total: R$ " . number_format($pedido['valor_total'], 2, ',', '.') . "\n" .
                    "Ticket Code: " . $pedido['ticket_code'] . "\n";
                    
        // Processa o PDF: Se existir, salva o blob em um arquivo temporário
        if (!empty($pedido['pdf'])) {
            $pdf_data = $pedido['pdf'];
            // Define o caminho do arquivo temporário, ex.: C:\tmp\pedido_{id}.pdf (crie o diretório C:\tmp se necessário)
            $pdf_path = "C:\\tmp\\pedido_" . $pedido['id'] . ".pdf";
            file_put_contents($pdf_path, $pdf_data);
        } else {
            $pdf_path = "";
        }
        
        // Dados a serem enviados para o serviço Node.js
        $data = [
            "numero_whatsapp" => $pedido['numero_whatsapp'],
            "mensagem"        => $mensagem,
            "pdf_path"        => $pdf_path
        ];
        
        // Configura a chamada cURL para o endpoint do Node.js
        $url = "http://localhost:3000/sendWhatsApp"; // Usando localhost para testes locais
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "Erro ao enviar pedido ID " . $pedido['id'] . ": " . curl_error($ch) . "\n";
        } else {
            echo "Pedido ID " . $pedido['id'] . " enviado com sucesso!\n";
            // Atualiza o pedido para marcar que já foi enviado
            $update = $conn->prepare("UPDATE pedidos SET enviado = 1 WHERE id = ?");
            $update->bind_param("i", $pedido['id']);
            $update->execute();
        }
        curl_close($ch);
    }
} else {
    echo "Nenhum pedido pendente.\n";
}
?>
