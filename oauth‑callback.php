<?php
$code = $_GET['code'];
$state = $_GET['state']; // promotor_id que você enviou

$payload = [
  'grant_type'    => 'authorization_code',
  'client_id'     => $clientId,
  'client_secret' => $clientSecret,
  'code'          => $code,
  'redirect_uri'  => 'https://ticketsync.com/oauth-callback.php',
];

$ch = curl_init('https://api.mercadopago.com/oauth/token');
curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
                        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
                        CURLOPT_POSTFIELDS=>http_build_query($payload)]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

/*
$response ===
[
  access_token   => "APP_USR-AAA...",
  refresh_token  => "TG-AAA...",
  user_id        => 123456789,
  public_key     => "APP_USR-aaa",
  ...
]
*/

// Salve em tabela `promotores`

?>>