<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Checkout Transparente - Mercado Pago</title>
  <!-- Carrega o SDK do Mercado Pago -->
  <script src="https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js"></script>
</head>
<body>
  <h2>Pagamento com Cartão de Crédito</h2>
  <form id="payment-form">
    <div>
      <label for="cardNumber">Número do Cartão</label>
      <!-- Adicionado data-checkout -->
      <input type="text" id="cardNumber" name="cardNumber" data-checkout="cardNumber" placeholder="Digite o número do cartão" required>
    </div>
    <div>
      <label for="cardholderName">Nome do Titular</label>
      <!-- Para o nome do titular, geralmente se usa data-checkout="cardholderName" se o SDK precisar -->
      <input type="text" id="cardholderName" name="cardholderName" data-checkout="cardholderName" placeholder="Nome como está no cartão" required>
    </div>
    <div>
      <label for="cardExpirationMonth">Mês de Expiração</label>
      <input type="text" id="cardExpirationMonth" name="cardExpirationMonth" data-checkout="cardExpirationMonth" placeholder="MM" required>
    </div>
    <div>
      <label for="cardExpirationYear">Ano de Expiração</label>
      <input type="text" id="cardExpirationYear" name="cardExpirationYear" data-checkout="cardExpirationYear" placeholder="AAAA" required>
    </div>
    <div>
      <label for="securityCode">Código de Segurança</label>
      <input type="text" id="securityCode" name="securityCode" data-checkout="securityCode" placeholder="Código" required>
    </div>
    <!-- Adicione campos adicionais conforme necessário, como número de parcelas, documento, etc. -->
    <button type="submit">Pagar</button>
  </form>

  <script>
  // Inicializa o SDK com a sua chave pública do sandbox
  MercadoPago.setPublishableKey('TEST-208cfed3-c870-4125-8a88-fc9c4dff3436');

  function createToken(event) {
    event.preventDefault();
    console.log("Formulário submetido"); // Debug: confirmar se a função é chamada

    MercadoPago.createToken(event.target, function(status, response) {
      console.log("Status:", status, "Response:", response); // Debug: log do retorno
      if (status === 200 || status === 201) {
        var token = response.id;
        console.log("Token gerado:", token);
        enviarPagamento(token);
      } else {
        console.error("Erro ao criar o token:", response);
        alert("Erro ao validar os dados do cartão. Verifique e tente novamente.");
      }
    });
  }

  function enviarPagamento(token) {
    // Dados adicionais do pagamento
    var pagamentoData = {
      token: token,
      transactionAmount: 100.00,
      installments: 1,
      paymentMethodId: 'visa',
      payer: {
        email: 'comprador@exemplo.com'
      }
    };

    fetch('process_payment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(pagamentoData)
    })
    .then(response => response.json())
    .then(data => {
      console.log('Resposta do pagamento:', data);
      if (data.status === 'approved') {
        alert('Pagamento realizado com sucesso!');
      } else {
        alert('Pagamento não autorizado. Verifique os detalhes e tente novamente.');
      }
    })
    .catch(error => {
      console.error('Erro na requisição do pagamento:', error);
      alert('Erro na comunicação com o servidor.');
    });
  }

  // Verifica se o listener está sendo corretamente atribuído
  document.getElementById('payment-form').addEventListener('submit', createToken);
</script>

</body>
</html>
