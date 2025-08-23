<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Solicitação de Exclusão de Dados - Ticket Sync</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0 auto;
      max-width: 800px;
      padding: 20px;
      line-height: 1.6;
      color: #333;
    }
    h1 {
      color: #002f6d;
      margin-bottom: 0.5em;
    }
    p {
      margin-bottom: 1em;
    }
    label {
      display: block;
      margin-top: 1em;
      font-weight: bold;
    }
    input[type="email"],
    textarea {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }
    button {
      background-color: #002f6d;
      color: #fff;
      border: none;
      padding: 10px 20px;
      margin-top: 20px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
    }
    button:hover {
      background-color: #001f4d;
    }
  </style>
</head>
<body>

  <h1>Solicitação de Exclusão de Dados</h1>
  
  <p>
    Para atender às diretrizes da Lei Geral de Proteção de Dados (LGPD) e em conformidade com as políticas de privacidade do Ticket Sync, 
    você pode solicitar a exclusão dos seus dados pessoais armazenados em nosso sistema.
  </p>
  
  <p>
    Ao enviar este formulário, você confirma que deseja que seus dados cadastrais sejam removidos de nossos registros. 
    Após o envio, nossa equipe analisará a solicitação e poderá entrar em contato para confirmar ou solicitar informações adicionais, se necessário.
  </p>

  <form action="processa_exclusao.php" method="post">
    <label for="email">E-mail Cadastrado:</label>
    <input type="email" id="email" name="email" placeholder="Seu e-mail" required>

    <label for="motivo">Motivo da Solicitação (opcional):</label>
    <textarea id="motivo" name="motivo" rows="4" placeholder="Explique, se desejar, o motivo da exclusão dos seus dados"></textarea>
    
    <button type="submit">Solicitar Exclusão de Dados</button>
  </form>

  <p>
    Observação: A exclusão de dados poderá levar até 30 dias úteis para ser processada. Caso haja alguma pendência ou necessidade de confirmação, 
    entraremos em contato pelo e-mail informado.
  </p>

</body>
</html>
