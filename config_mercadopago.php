<?php
// config_mercadopago.php
require_once 'vendor/autoload.php';

use MercadoPago\SDK;

// Configurações do Mercado Pago
// IMPORTANTE: Você DEVE obter suas credenciais reais em:
// https://www.mercadopago.com.br/developers/panel/app

// CREDENCIAIS DE TESTE (substitua pelas suas)
$mp_public_key = "TEST-bd73f35a-5a58-42c3-a434-82943d8df12c"; 
$mp_access_token = "TEST-5362820108352996-031810-3558c7ea33c8e6d85d97f16180840b8a-2320640278"; 

// Configure o SDK (versão 2.x)
SDK::setAccessToken($mp_access_token);

// Configurar ambiente
SDK::setIntegratorId("dev_24c65fb163bf11ea96500242ac130004"); // Opcional
SDK::setPlatformId("dev_platform"); // Opcional

// Variável global para usar no JavaScript
$GLOBALS['mp_public_key'] = $mp_public_key;
?>