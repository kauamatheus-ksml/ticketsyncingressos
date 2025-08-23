<?php
/**
 * DynamicQRCode.php
 * 
 * Componente para exibir um QR Code dinâmico baseado no ticket_code.
 * O QR Code é atualizado automaticamente a cada 30 segundos, com
 * uma barra de progresso e contagem regressiva.
 *
 * Uso:
 *   - Defina a variável $ticket_code com o código do ingresso antes de incluir este arquivo.
 *   - Exemplo:
 *         $ticket_code = "ABC1234567";
 *         include('DynamicQRCode.php');
 */

// Se não estiver definido, usa um valor default (para teste)
if (!isset($ticket_code)) {
    $ticket_code = 'DEFAULT123';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>QR Code Dinâmico</title>
  <!-- Importa a biblioteca QRCode.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    /* Container principal do componente */
    #dynamic-qr-wrapper {
      max-width: 300px;
      margin: 20px auto;
      text-align: center;
      font-family: 'Poppins', sans-serif;
    }
    /* Área onde o QR Code será gerado */
    #qr-code-container {
      width: 256px;
      height: 256px;
      margin: 0 auto;
      border: 1px solid #ddd;
      border-radius: 8px;
    }
    /* Barra de progresso e contagem regressiva */
    #qr-code-timer {
      width: 100%;
      height: 10px;
      background-color: #eaeaea;
      margin-top: 10px;
      border-radius: 5px;
      overflow: hidden;
    }
    #qr-code-progress {
      height: 100%;
      background-color: #007bff;
      width: 100%;
      transition: width 1s linear;
    }
    #qr-code-text {
      margin-top: 5px;
      font-size: 1rem;
      color: #333;
    }
  </style>
</head>
<body>
  <div id="dynamic-qr-wrapper">
    <div id="qr-code-container"></div>
    <div id="qr-code-timer">
      <div id="qr-code-progress"></div>
    </div>
    <div id="qr-code-text">30 s</div>
  </div>

  <script>
    (function(){
      // Intervalo de validade do QR Code (em segundos)
      var intervalSeconds = 30;
      var remainingSeconds = intervalSeconds;
      // Valor base do ticket_code passado pelo PHP
      var ticketCodeBase = "<?php echo $ticket_code; ?>";
      
      /**
       * Gera um valor dinâmico combinando o ticket_code com um fator baseado no tempo.
       * Assim, o QR Code muda a cada intervalo determinado.
       */
      function getDynamicCode(){
        // Usamos o timestamp atual mod intervalSeconds para gerar uma variação
        var timestamp = Math.floor(Date.now() / 1000);
        // Exemplo: "ABC1234567-15" (o sufixo muda de 0 a 29)
        return ticketCodeBase + '-' + (timestamp % intervalSeconds);
      }
      
      var qrCode; // Variável para armazenar o objeto do QR Code

      /**
       * Renderiza o QR Code no container.
       */
      function renderQRCode(){
        var container = document.getElementById("qr-code-container");
        // Limpa o container para evitar acúmulo de elementos
        container.innerHTML = "";
        var dynamicCode = getDynamicCode();
        qrCode = new QRCode(container, {
          text: dynamicCode,
          width: 256,
          height: 256,
          colorDark : "#000000",
          colorLight : "#ffffff",
          correctLevel : QRCode.CorrectLevel.H
        });
      }
      
      /**
       * Atualiza a barra de progresso e o texto da contagem regressiva.
       * Quando o tempo expira, renova o QR Code e reinicia a contagem.
       */
      function updateProgress(){
        var progressElem = document.getElementById("qr-code-progress");
        var textElem = document.getElementById("qr-code-text");
        remainingSeconds--;
        if (remainingSeconds < 0){
          renderQRCode();      // Atualiza o QR Code
          remainingSeconds = intervalSeconds; // Reinicia a contagem
        }
        // Atualiza a largura da barra de progresso
        var percent = (remainingSeconds / intervalSeconds) * 100;
        progressElem.style.width = percent + "%";
        // Atualiza o texto com os segundos restantes
        textElem.innerHTML = remainingSeconds + " s";
      }
      
      // Renderiza o QR Code inicialmente
      renderQRCode();
      // Atualiza a cada 1 segundo (1000 ms)
      setInterval(updateProgress, 1000);
    })();
  </script>
</body>
</html>
