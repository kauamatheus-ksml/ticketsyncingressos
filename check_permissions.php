<?php
/**
 * check_permissions.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

include_once('conexao.php');

$userId = $_SESSION['adminid'];
$permissionsFile = "permissoes_geral.json";

/**
 * Permissões padrão de todo usuário, caso não haja configuração
 * específica no arquivo de permissões.
 */
$defaultUserPermissions = [
    "eventos"                => true,
    "ingressos"              => true,
    "promotores"             => false,
    "gerencia_acesso"        => false,
    "pagamentos"             => false,
    "ingressos_vendidos"     => false,
    "gerenciar_eventos"      => false,
    "cadastrar_funcionarios" => false,
    // Nova permissão para "Validar Ingressos"
    "validar_ingressos"      => false,
    // Nova permissão para "PDV"
    "pdv"                    => true
];

// Carrega os dados de permissões do arquivo JSON ou usa os padrões
if (file_exists($permissionsFile)) {
    $permissionsData = json_decode(file_get_contents($permissionsFile), true);
} else {
    $permissionsData = [];
}

// Define as permissões do usuário logado
$userPermissions = isset($permissionsData[$userId]) ? $permissionsData[$userId] : $defaultUserPermissions;

/**
 * Função que verifica se o usuário possui a permissão solicitada.
 *
 * @param string $perm Nome da permissão
 * @return bool True se o usuário tem a permissão; False caso contrário.
 */
function checkPermission($perm) {
    global $userPermissions;
    return isset($userPermissions[$perm]) && $userPermissions[$perm];
}
?>
