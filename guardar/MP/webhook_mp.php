<?php
header('Content-Type: application/json');

// Recebe o payload enviado pelo Mercado Pago
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Opcional: registre o payload para depuração
// file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " " . $payload . "\n", FILE_APPEND);

if (isset($data['type']) && $data['type'] === 'payment') {
    $paymentData = $data['data'];
    $payment_id  = isset($paymentData['id']) ? $paymentData['id'] : null;
    $status      = isset($paymentData['status']) ? $paymentData['status'] : null;

    if ($payment_id && $status) {
        try {
            $conn = new PDO("mysql:host=localhost;dbname=SistemaIngressos", "root", "root");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Supondo que a tabela "ingressos" possui colunas "pagamento_id" e "status"
            $stmt = $conn->prepare("UPDATE ingressos SET status = :status WHERE pagamento_id = :payment_id");
            $stmt->execute([
                ':status'     => $status,
                ':payment_id' => $payment_id
            ]);
        } catch (PDOException $e) {
            error_log("Erro no webhook ao atualizar o pagamento: " . $e->getMessage());
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'OK']);
?>
