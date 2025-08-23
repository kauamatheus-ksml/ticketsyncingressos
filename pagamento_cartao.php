<?php
// pagamento_cartao.php
session_start();
require 'conexao.php';
require 'config_mercadopago.php';

// Verifica se os dados necessários estão na sessão
if (!isset($_SESSION['order_id']) || !isset($_SESSION['total_pedido']) || !isset($_SESSION['form_nome']) || !isset($_SESSION['form_email'])) {
    header("Location: comprar_ingresso.php");
    exit();
}

$order_id = $_SESSION['order_id'];
$total_pedido = $_SESSION['total_pedido'];
$nome_completo = $_SESSION['form_nome'];
$email = $_SESSION['form_email'];
$evento_id = $_SESSION['evento_id'];

// Busca informações do evento
$stmt = $conn->prepare("SELECT nome FROM eventos WHERE id = ?");
$stmt->bind_param("i", $evento_id);
$stmt->execute();
$resultado = $stmt->get_result();
$evento = $resultado->fetch_assoc();
$stmt->close();

// Pega a public key do config
$public_key = $GLOBALS['mp_public_key'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento com Cartão - Ticket Sync</title>
    <link rel="icon" href="uploads/Group 11.svg" type="image/x-icon"/>
    <link rel="stylesheet" href="css/comprar_ingresso.css">
    
    <!-- Mercado Pago SDK -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    
    <style>
        .card-form-container {
            max-width: 500px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .card-form-group {
            margin-bottom: 20px;
        }
        
        .card-form-group label {
            display: block;
            margin-bottom: 8px;
            color: #002f6d;
            font-weight: 500;
        }
        
        .card-form-group input, .card-form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .card-form-group input:focus, .card-form-group select:focus {
            outline: none;
            border-color: #002f6d;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .card-form-group {
            flex: 1;
        }
        
        .card-icons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .card-icon {
            width: 40px;
            height: 25px;
            background: #f5f5f5;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .order-summary h3 {
            margin-bottom: 15px;
            color: #002f6d;
        }
        
        .btn-pay {
            width: 100%;
            padding: 15px;
            background: #002f6d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-pay:hover {
            background: #001a3a;
        }
        
        .btn-pay:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <a href="index">
                <img src="uploads/ticketsyhnklogo1.png" alt="Ticket Sync Logo">
            </a>
        </div>
    </header>

    <div class="container">
        <div class="order-summary">
            <h3>Resumo do Pedido</h3>
            <p><strong>Evento:</strong> <?php echo htmlspecialchars($evento['nome'] ?? 'Evento'); ?></p>
            <p><strong>Pedido:</strong> #<?php echo htmlspecialchars($order_id); ?></p>
            <p><strong>Total:</strong> R$ <?php echo number_format($total_pedido, 2, ',', '.'); ?></p>
        </div>

        <div class="card-form-container">
            <h2 style="text-align: center; margin-bottom: 30px; color: #002f6d;">Pagamento com Cartão</h2>
            
            <div id="error-container"></div>
            
            <form id="card-form">
                <div class="card-form-group">
                    <label for="cardNumber">Número do Cartão</label>
                    <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19">
                    <div class="card-icons">
                        <div class="card-icon">VISA</div>
                        <div class="card-icon">MASTER</div>
                        <div class="card-icon">ELO</div>
                    </div>
                </div>

                <div class="card-form-group">
                    <label for="cardholderName">Nome no Cartão</label>
                    <input type="text" id="cardholderName" placeholder="Nome como aparece no cartão" value="<?php echo htmlspecialchars($nome_completo); ?>">
                </div>

                <div class="form-row">
                    <div class="card-form-group">
                        <label for="expirationMonth">Mês</label>
                        <select id="expirationMonth">
                            <option value="">Mês</option>
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="card-form-group">
                        <label for="expirationYear">Ano</label>
                        <select id="expirationYear">
                            <option value="">Ano</option>
                            <?php for($i = date('Y'); $i <= date('Y') + 15; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="card-form-group">
                        <label for="securityCode">CVV</label>
                        <input type="text" id="securityCode" placeholder="123" maxlength="4">
                    </div>
                </div>

                <div class="card-form-group">
                    <label for="cpf">CPF</label>
                    <input type="text" id="cpf" placeholder="000.000.000-00" maxlength="14">
                </div>

                <div class="card-form-group">
                    <label for="installments">Parcelas</label>
                    <select id="installments">
                        <option value="1">1x de R$ <?php echo number_format($total_pedido, 2, ',', '.'); ?> sem juros</option>
                    </select>
                </div>

                <button type="submit" id="pay-button" class="btn-pay">
                    Finalizar Pagamento
                </button>
            </form>

            <div id="loading" class="loading">
                <p>Processando pagamento...</p>
                <div class="spinner"></div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="comprar_ingresso.php?step=2&evento_id=<?php echo $evento_id; ?>" class="btn-voltar">Voltar</a>
        </div>
    </div>

    <script>
        // Public key vinda do PHP
        const mp = new MercadoPago('<?php echo $public_key; ?>');

        // Máscaras para os campos
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedInputValue = value.match(/.{1,4}/g)?.join(' ') || '';
            e.target.value = formattedInputValue;
        });

        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });

        // Buscar métodos de pagamento e parcelas
        async function getPaymentMethods(cardNumber) {
            try {
                const bin = cardNumber.replace(/\s/g, '').substring(0, 6);
                if (bin.length >= 6) {
                    const paymentMethods = await mp.getPaymentMethods({bin: bin});
                    return paymentMethods;
                }
            } catch (error) {
                console.error('Erro ao buscar métodos de pagamento:', error);
            }
            return null;
        }

        // Buscar parcelas
        async function getInstallments(paymentMethodId, transactionAmount) {
            try {
                const installments = await mp.getInstallments({
                    payment_method_id: paymentMethodId,
                    amount: transactionAmount
                });
                return installments;
            } catch (error) {
                console.error('Erro ao buscar parcelas:', error);
            }
            return null;
        }

        // Atualizar parcelas quando o número do cartão mudar
        document.getElementById('cardNumber').addEventListener('blur', async function(e) {
            const cardNumber = e.target.value;
            const paymentMethods = await getPaymentMethods(cardNumber);
            
            if (paymentMethods && paymentMethods.results.length > 0) {
                const paymentMethodId = paymentMethods.results[0].id;
                const amount = <?php echo $total_pedido; ?>;
                
                const installmentsData = await getInstallments(paymentMethodId, amount);
                
                if (installmentsData && installmentsData.length > 0) {
                    const installmentsSelect = document.getElementById('installments');
                    installmentsSelect.innerHTML = '';
                    
                    installmentsData[0].payer_costs.forEach(function(installment) {
                        const option = document.createElement('option');
                        option.value = installment.installments;
                        option.textContent = `${installment.installments}x de R$ ${installment.installment_amount.toFixed(2).replace('.', ',')}`;
                        if (installment.installment_rate === 0) {
                            option.textContent += ' sem juros';
                        }
                        installmentsSelect.appendChild(option);
                    });
                }
            }
        });

        // Processar pagamento
        document.getElementById('card-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const payButton = document.getElementById('pay-button');
            const loading = document.getElementById('loading');
            const errorContainer = document.getElementById('error-container');
            
            payButton.disabled = true;
            loading.style.display = 'block';
            errorContainer.innerHTML = '';

            try {
                // Criar token do cartão
                const cardToken = await mp.createCardToken({
                    cardNumber: document.getElementById('cardNumber').value.replace(/\s/g, ''),
                    cardholderName: document.getElementById('cardholderName').value,
                    cardExpirationMonth: document.getElementById('expirationMonth').value,
                    cardExpirationYear: document.getElementById('expirationYear').value,
                    securityCode: document.getElementById('securityCode').value,
                    identificationType: 'CPF',
                    identificationNumber: document.getElementById('cpf').value.replace(/\D/g, '')
                });

                // Buscar dados do método de pagamento
                const cardNumber = document.getElementById('cardNumber').value;
                const paymentMethods = await getPaymentMethods(cardNumber);
                
                if (!paymentMethods || paymentMethods.results.length === 0) {
                    throw new Error('Método de pagamento não encontrado');
                }

                // Enviar dados para o backend
                const formData = new FormData();
                formData.append('token', cardToken.id);
                formData.append('installments', document.getElementById('installments').value);
                formData.append('payment_method_id', paymentMethods.results[0].id);
                formData.append('issuer_id', paymentMethods.results[0].issuer?.id || '');
                formData.append('cpf', document.getElementById('cpf').value.replace(/\D/g, ''));

                const response = await fetch('processa_pagamento_cartao.php', {
                    method: 'POST',
                    body: formData
                });

                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    throw new Error('Erro no processamento do pagamento');
                }

            } catch (error) {
                console.error('Erro:', error);
                errorContainer.innerHTML = `
                    <div class="error-message">
                        Erro: ${error.message || 'Erro desconhecido'}
                    </div>
                `;
                payButton.disabled = false;
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>