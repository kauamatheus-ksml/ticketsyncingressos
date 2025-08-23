const express = require('express');
const bodyParser = require('body-parser');
const venom = require('venom-bot');

const app = express();
app.use(bodyParser.json());

let clientInstance;

// Inicializa o Venom Bot
venom.create('session_1')
  .then((client) => {
    clientInstance = client;
    console.log('Venom Bot iniciado.');
  })
  .catch((error) => {
    console.error('Erro ao iniciar o Venom Bot:', error);
  });


// Endpoint para receber os dados do PHP e enviar o WhatsApp
app.post('/sendWhatsApp', async (req, res) => {
  const { numero_whatsapp, mensagem, pdf_path } = req.body;
  
  if (!numero_whatsapp || !mensagem) {
    return res.status(400).json({ error: 'Dados insuficientes.' });
  }
  
  try {
    // Envia a mensagem de texto
    await clientInstance.sendText(numero_whatsapp, mensagem);
    
    // Se existir um caminho para o PDF, envia como arquivo
    if (pdf_path) {
      await clientInstance.sendFile(numero_whatsapp, pdf_path, 'pedido.pdf', 'Segue o PDF do pedido.');
    }
    
    res.json({ status: 'success' });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Inicia o servidor na porta 3000
const PORT = 3001; // ou outra porta disponível

app.listen(PORT, () => {
  console.log(`API do Venom Bot rodando na porta ${PORT}`);
});
