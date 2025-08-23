<?php
// timeout.php
session_start();

// Destrói a sessão para liberar todos os dados salvos (ingressos, login etc.)
session_destroy();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Tempo Esgotado</title>
  <link rel="icon" href="uploads\ticketsync.ico" type="image/x-icon"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <style>
    /* Ocupamos a tela toda com um overlay (fundo semitransparente) */
    #overlay {
      position: fixed;
      top: 0; 
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      display: none; /* começa oculto */
      z-index: 999;  /* fica à frente de todo o conteúdo */
    }

    /* Container do "modal" em si */
    #timeoutModal {
      position: fixed;
      top: 50%; 
      left: 50%;
      transform: translate(-50%, -50%);
      background: #fff;
      padding: 30px;
      border-radius: 8px;
      text-align: center;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      display: none; /* começa oculto */
      z-index: 1000; /* acima do overlay */
      font-family: Arial, sans-serif;
      color: #333;
    }

    /* Título em vermelho */
    #timeoutModal h2 {
      color: #e74c3c;
      margin-bottom: 20px;
    }

    /* Parágrafos */
    #timeoutModal p {
      margin-bottom: 20px;
      line-height: 1.5;
    }

    /* Botão de voltar */
    .btn-voltar {
      background: #3498db;
      color: #fff;
      padding: 10px 20px;
      border: none;
      border-radius: 4px;
      text-decoration: none;
      font-weight: bold;
      transition: background 0.3s;
      cursor: pointer;
      font-size: 1em;
    }
    .btn-voltar:hover {
      background: #2980b9;
    }
  </style>

  <script>
    // Função para exibir o modal e o overlay
    function showModal() {
      document.getElementById("overlay").style.display = "block";
      document.getElementById("timeoutModal").style.display = "block";
    }

    // Função para redirecionar para a index (ou qualquer outra página)
    function voltarPagina() {
      // Vá para a index.php (ajuste conforme desejar)
      window.location.href = "index.php";
    }

    // Quando a página carrega, exibimos o modal automaticamente.
    window.onload = function() {
      showModal();
    }
  </script>
</head>
<body>
  <!-- Overlay semitransparente -->
  <div id="overlay"></div>

  <!-- Modal -->
  <div id="timeoutModal">
    <h2>O tempo para a compra expirou</h2>
    <p>
      Para evitar que uma reserva fique presa e para que os ingressos possam ficar disponíveis novamente,
      o tempo para finalizar a compra chegou ao fim.
    </p>
    <p>
      Você pode recomeçar a compra clicando no botão abaixo.
    </p>

    <!-- Ao clicar, redireciona rapidamente para index.php -->
    <button class="btn-voltar" onclick="voltarPagina()">Voltar</button>
  </div>
</body>
</html>
