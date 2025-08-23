<?php
session_start();
require 'vendor/autoload.php';  // Certifique-se de que o Composer está configurado corretamente

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

include('conexao.php');

// Configure a chave secreta do Stripe
\Stripe\Stripe::setApiKey('sk_test_51QdJqZQObnNa36zPGKqXkYTdl2Ya1B6Bcsb4KWwBnUC09RA6CCFX71mAKKku0qVOwfw9jrWQVIHeOLem2Nl5HqCb00xyoe4Lry');

// Consulta os itens no carrinho
$carrinho = [];
$subtotal = 0;
if (!empty($_SESSION['carrinho'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['carrinho'])));
    $sqlCarrinho = "SELECT ingressos.*, eventos.nome AS evento_nome 
                    FROM ingressos 
                    JOIN eventos ON ingressos.evento_id = eventos.id 
                    WHERE ingressos.id IN ($ids)";
    $resultCarrinho = $conn->query($sqlCarrinho);
    while ($row = $resultCarrinho->fetch_assoc()) {
        $row['quantidade'] = $_SESSION['carrinho'][$row['id']];
        $subtotal += $row['preco'] * $row['quantidade'];
        $carrinho[] = $row;
    }
}

$message = "";

// Processa o formulário de pagamento
$message = "";
$pixQRCode = "";

// Processa o formulário de pagamento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalizar_compra'])) {
    if ($_POST['metodo_pagamento'] == 'pix') {
        // Gerar QR Code para pagamento PIX
        $pixPayload = "00020126580014BR.GOV.BCB.PIX0136yourpixkeyhere520400005303986540410.005802BR5913Your Company6009Your City62070503***6304"; // Personalize com os dados do seu PIX
        $qrCode = new QrCode($pixPayload);
        $qrCode->setSize(300);
        $writer = new PngWriter();
        $pixQRCode = base64_encode($writer->write($qrCode)->getString());

        $message = "Utilize o QR Code abaixo para realizar o pagamento via PIX.";
    } else {
        // Processamento de pagamento via cartão (Stripe)
        $token = $_POST['stripeToken'];
        $email = $_POST['email'];

        try {
            // Cria um cliente no Stripe
            $customer = \Stripe\Customer::create([
                'email' => $email,
                'source' => $token,
            ]);

            // Cria uma cobrança no Stripe
            $charge = \Stripe\Charge::create([
                'customer' => $customer->id,
                'amount' => $subtotal * 100, // Em centavos
                'currency' => 'brl',
                'description' => 'Compra de Ingressos',
            ]);

            // Registra a compra no banco de dados
            $usuario_id = $_SESSION['userid'];
            foreach ($carrinho as $item) {
                $ingresso_id = $item['id'];
                $quantidade = $item['quantidade'];
                for ($i = 0; $i < $quantidade; $i++) {
                    $sqlCompra = "INSERT INTO vendas (ingresso_id, usuario_id) VALUES (?, ?)";
                    $stmtCompra = $conn->prepare($sqlCompra);
                    $stmtCompra->bind_param("ii", $ingresso_id, $usuario_id);
                    $stmtCompra->execute();
                    $stmtCompra->close();
                }
            }
            $_SESSION['carrinho'] = [];
            $message = "Compra finalizada com sucesso!";
        } catch (Exception $e) {
            $message = "Erro ao processar o pagamento: " . $e->getMessage();
        }
    }
}

$conn->close();

?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/checkout_custom.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="checkout-body">
    <?php include('header_cliente.php'); ?>
    <div class="checkout-container my-5">
        <h3 class="text-center mb-4">Checkout</h3>
        <?php if (!empty($message)): ?>
            <div class="checkout-alert-success text-center"><?php echo $message; ?></div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <h4 class="mb-3">Informações do Carrinho</h4>
                <ul class="list-group mb-3">
                    <?php foreach ($carrinho as $item): ?>
                        <li class="checkout-list-group-item d-flex justify-content-between lh-condensed">
                            <div>
                                <h6 class="my-0"><?php echo htmlspecialchars($item['evento_nome']); ?></h6>
                                <small class="text-muted">Tipo: <?php echo htmlspecialchars($item['tipo_ingresso']); ?> | Quantidade: <?php echo $item['quantidade']; ?></small>
                            </div>
                            <span class="text-muted">R$ <?php echo number_format($item['preco'] * $item['quantidade'], 2, ',', '.'); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <li class="checkout-list-group-item d-flex justify-content-between">
                        <span>Subtotal</span>
                        <strong>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></strong>
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h4 class="mb-3">Detalhes de Pagamento</h4>
                <form action="checkout.php" method="post" class="checkout-needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="nome" class="checkout-form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                        <div class="invalid-feedback">
                            Nome completo é obrigatório.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="checkout-form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <div class="invalid-feedback">
                            Por favor, insira um email válido.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="endereco" class="checkout-form-label">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="endereco" required>
                        <div class="invalid-feedback">
                            Por favor, insira seu endereço.
                        </div>
                    </div>
                    <?php if (!empty($pixQRCode)): ?>
    <div class="text-center">
        <h4>Pagamento via PIX</h4>
        <p><?php echo $message; ?></p>
        <img src="data:image/png;base64,<?php echo $pixQRCode; ?>" alt="QR Code PIX">
    </div>
<?php endif; ?>

                    <div class="mb-3">
                        <label for="cartao" class="checkout-form-label">Número do Cartão</label>
                        <div id="card-element" class="form-control"></div>
                        <div id="card-errors" role="alert"></div>
                        <div class="invalid-feedback">
                            Número do cartão é obrigatório.
                        </div>
                    </div>
                    <button class="checkout-btn-primary btn btn-block" type="submit" name="finalizar_compra">Finalizar Compra</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        var stripe = Stripe('pk_test_51QdJqZQObnNa36zPlh0bFqssE8SHUOw7MMqHhdgOsqvDwm3jkGUgFHqLgvLWmrrdR8sblW4jS2ORgLEdIHBv26bl00jmMK1LPc');
        var elements = stripe.elements();
        var card = elements.create('card');
        card.mount('#card-element');

        // Exemplo de validação de formulário customizada em Bootstrap
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('checkout-needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        } else {
                            event.preventDefault();
                            stripe.createToken(card).then(function(result) {
                                if (result.error) {
                                    var errorElement = document.getElementById('card-errors');
                                    errorElement.textContent = result.error.message;
                                } else {
                                    var hiddenInput = document.createElement('input');
                                    hiddenInput.setAttribute('type', 'hidden');
                                    hiddenInput.setAttribute('name', 'stripeToken');
                                    hiddenInput.setAttribute('value', result.token.id);
                                    form.appendChild(hiddenInput);
                                    form.submit();
                                }
                            });
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>



