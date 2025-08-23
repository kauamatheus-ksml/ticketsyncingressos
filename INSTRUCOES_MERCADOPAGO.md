# 📋 Instruções para Configurar Mercado Pago

## 🔑 1. Obter Credenciais

1. Acesse: https://www.mercadopago.com.br/developers/panel/app
2. Faça login em sua conta Mercado Pago
3. Crie uma nova aplicação ou use uma existente
4. Vá para "Credenciais" → "Credenciais de teste"
5. Copie:
   - **Public Key** (TEST-...)
   - **Access Token** (TEST-...)

## ⚙️ 2. Configurar no Sistema

### Arquivo: `config_mercadopago.php`
```php
// Substitua estas linhas:
$mp_public_key = "TEST-bd73f35a-5a58-42c3-a434-82943d8df12c"; 
$mp_access_token = "TEST-5362820108352996-031810-3558c7ea33c8e6d85d97f16180840b8a-2320640278"; 

// Por suas credenciais reais:
$mp_public_key = "TEST-sua-public-key-aqui"; 
$mp_access_token = "TEST-seu-access-token-aqui"; 
```

**⚠️ Importante:** O sistema usa SDK v2.6.2 do Mercado Pago com sintaxe específica.

## 🧪 3. Testar Integração

### Cartões de Teste para Sandbox:

**✅ Aprovado:**
- **Visa:** 4509 9535 6623 3704
- **Mastercard:** 5031 7557 3453 0604

**❌ Rejeitado:**
- **Visa:** 4234 1234 1234 1234

**⏳ Pendente:**
- **Visa:** 4009 1759 5559 0995

### Dados de Teste:
- **CVV:** 123
- **Data:** Qualquer data futura
- **Nome:** Qualquer nome
- **CPF:** 11111111111

## 🚀 4. Produção

1. Substitua credenciais TEST- por PROD-
2. Altere ambiente em `config_mercadopago.php`:
```php
MercadoPagoConfig::setEnvironment('production');
```

## ⚠️ 5. Segurança

- ❌ NUNCA commit credenciais no Git
- ✅ Use variáveis de ambiente em produção
- ✅ Mantenha access token privado
- ✅ Public key pode ser exposta no frontend

## 🔍 6. Debug de Erros

### Erro: "Si quieres conocer los recursos..."
- ❌ Credenciais inválidas ou não configuradas
- ✅ Verifique se substitui as credenciais example

### Erro: "Invalid token"
- ❌ Public key incorreta no JavaScript
- ✅ Confirme que public key no JS = config PHP

### Erro: "Payment method not found"
- ❌ Cartão inválido ou não suportado
- ✅ Use cartões de teste válidos

## 📞 7. Suporte

- Documentação: https://www.mercadopago.com.br/developers/pt
- Status API: https://status.mercadopago.com/
- Fórum: https://www.mercadopago.com.br/developers/pt/support/contact