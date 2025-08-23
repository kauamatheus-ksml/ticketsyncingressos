<?php
/**
 * TicketSync - Sistema Moderno de Gestão de Ingressos
 * Página completamente reformulada com design moderno
 * Tema claro e funcionalidades avançadas
 */

// Configurações iniciais
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui verificação de permissões
include_once('check_permissions.php');
if (!checkPermission("ingressos")) {
    echo json_encode(['error' => 'Sem permissão']);
    exit();
}

include('conexao.php');

// Verificar login
if (!isset($_SESSION['adminid'])) {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('America/Sao_Paulo');

// Verificar se é usuário master
$usuarioId = $_SESSION['adminid'];
$isMaster = false;

$stmtMaster = $conn->prepare("SELECT master FROM administradores WHERE id = ?");
$stmtMaster->bind_param("i", $usuarioId);
$stmtMaster->execute();
$resMaster = $stmtMaster->get_result();
if ($resMaster && $resMaster->num_rows > 0) {
    $rowMaster = $resMaster->fetch_assoc();
    $isMaster = ($rowMaster['master'] == 1);
}
$stmtMaster->close();

// ===== FUNÇÕES AUXILIARES =====

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES);
}

function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// ===== PROCESSAMENTO DE REQUISIÇÕES AJAX =====

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Buscar ingressos com filtros
    if ($action === 'search') {
        $search = sanitize($_GET['q'] ?? '');
        $eventoId = intval($_GET['evento_id'] ?? 0);
        $status = sanitize($_GET['status'] ?? '');
        $ordenacao = sanitize($_GET['ordem'] ?? 'created_desc');
        $page = intval($_GET['page'] ?? 1);
        $limit = 12;
        $offset = ($page - 1) * $limit;
        
        $where = ["i.promotor_id = ?"];
        $params = [$usuarioId];
        $types = "i";
        
        if (!empty($search)) {
            $where[] = "(i.tipo_ingresso LIKE ? OR e.nome LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm]);
            $types .= "ss";
        }
        
        if ($eventoId > 0) {
            $where[] = "i.evento_id = ?";
            $params[] = $eventoId;
            $types .= "i";
        }
        
        if (!empty($status)) {
            if ($status === 'liberado') {
                $where[] = "i.liberado = 1";
            } elseif ($status === 'bloqueado') {
                $where[] = "i.liberado = 0";
            } elseif ($status === 'esgotado') {
                $where[] = "i.quantidade <= COALESCE((SELECT COUNT(*) FROM ingressos_pedidos ip INNER JOIN pedidos p ON ip.pedido_id = p.id WHERE ip.ingresso_id = i.id AND p.status = 'approved'), 0)";
            }
        }
        
        // Ordenação
        $orderBy = "i.id DESC";
        switch ($ordenacao) {
            case 'created_asc': $orderBy = "i.id ASC"; break;
            case 'nome_asc': $orderBy = "i.tipo_ingresso ASC"; break;
            case 'nome_desc': $orderBy = "i.tipo_ingresso DESC"; break;
            case 'preco_asc': $orderBy = "i.preco ASC"; break;
            case 'preco_desc': $orderBy = "i.preco DESC"; break;
            case 'vendas': $orderBy = "vendas_count DESC"; break;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Query principal
        $sql = "
            SELECT i.*, 
                   e.nome AS evento_nome, 
                   e.data_inicio, 
                   e.hora_inicio,
                   e.logo,
                   COALESCE((SELECT COUNT(*) FROM ingressos_pedidos ip 
                            INNER JOIN pedidos p ON ip.pedido_id = p.id 
                            WHERE ip.ingresso_id = i.id AND p.status = 'approved'), 0) as vendas_count,
                   (i.quantidade - COALESCE((SELECT COUNT(*) FROM ingressos_pedidos ip 
                                           INNER JOIN pedidos p ON ip.pedido_id = p.id 
                                           WHERE ip.ingresso_id = i.id AND p.status = 'approved'), 0)) AS disponivel,
                   COALESCE((SELECT COUNT(*) FROM ingressos_pedidos ip 
                            INNER JOIN pedidos p ON ip.pedido_id = p.id 
                            WHERE ip.ingresso_id = i.id AND p.status = 'approved'), 0) * i.preco AS receita_total
            FROM ingressos i
            INNER JOIN eventos e ON i.evento_id = e.id
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ingressos = [];
        while ($row = $result->fetch_assoc()) {
            $ingressos[] = $row;
        }
        
        // Contar total
        $sqlCount = "
            SELECT COUNT(*) as total 
            FROM ingressos i
            INNER JOIN eventos e ON i.evento_id = e.id
            WHERE $whereClause
        ";
        $stmtCount = $conn->prepare($sqlCount);
        $typesCount = str_replace("ii", "", $types);
        $paramsCount = array_slice($params, 0, -2);
        if (!empty($paramsCount)) {
            $stmtCount->bind_param($typesCount, ...$paramsCount);
        }
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result();
        $total = $totalResult->fetch_assoc()['total'];
        
        header('Content-Type: application/json');
        echo json_encode([
            'ingressos' => $ingressos,
            'total' => $total,
            'page' => $page,
            'totalPages' => ceil($total / $limit)
        ]);
        exit();
    }
    
    // Carregar eventos
    if ($action === 'eventos') {
        $stmt = $conn->prepare("
            SELECT id, nome, data_inicio, hora_inicio, local, logo 
            FROM eventos 
            WHERE promotor_id = ? 
            ORDER BY data_inicio DESC
        ");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $eventos = [];
        while ($row = $result->fetch_assoc()) {
            $eventos[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($eventos);
        exit();
    }
    
    // Estatísticas
    if ($action === 'stats') {
        $stats = [];
        
        // Total de ingressos
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ingressos WHERE promotor_id = ?");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_ingressos'] = $result->fetch_assoc()['total'];
        
        // Ingressos liberados
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ingressos WHERE promotor_id = ? AND liberado = 1");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['ingressos_liberados'] = $result->fetch_assoc()['total'];
        
        // Total de vendas
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM ingressos_pedidos ip 
            INNER JOIN ingressos i ON ip.ingresso_id = i.id 
            INNER JOIN pedidos p ON ip.pedido_id = p.id
            WHERE i.promotor_id = ? AND p.status = 'approved'
        ");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_vendas'] = $result->fetch_assoc()['total'];
        
        // Receita total
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(ip.preco), 0) as total 
            FROM ingressos_pedidos ip 
            INNER JOIN ingressos i ON ip.ingresso_id = i.id 
            INNER JOIN pedidos p ON ip.pedido_id = p.id
            WHERE i.promotor_id = ? AND p.status = 'approved'
        ");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['receita_total'] = $result->fetch_assoc()['total'];
        
        header('Content-Type: application/json');
        echo json_encode($stats);
        exit();
    }
    
    // Buscar ingresso específico
    if ($action === 'get_ingresso') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("
                SELECT i.*, e.nome AS evento_nome
                FROM ingressos i
                INNER JOIN eventos e ON i.evento_id = e.id
                WHERE i.id = ? AND i.promotor_id = ?
            ");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $ingresso = $result->fetch_assoc();
                header('Content-Type: application/json');
                echo json_encode($ingresso);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Ingresso não encontrado']);
            }
            exit();
        }
    }
    
    // Alternar status
    if ($action === 'toggle_status') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT liberado FROM ingressos WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $newStatus = $row['liberado'] == 1 ? 0 : 1;
                
                $stmt = $conn->prepare("UPDATE ingressos SET liberado = ? WHERE id = ? AND promotor_id = ?");
                $stmt->bind_param("iii", $newStatus, $id, $usuarioId);
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'status' => $newStatus,
                        'message' => $newStatus ? 'Ingresso liberado com sucesso!' : 'Ingresso bloqueado com sucesso!'
                    ]);
                }
            }
            exit();
        }
    }
    
    // Excluir ingresso
    if ($action === 'delete_ingresso') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            // Verificar se tem vendas
            $stmt = $conn->prepare("
                SELECT COUNT(*) as vendas
                FROM ingressos_pedidos ip 
                INNER JOIN ingressos i ON ip.ingresso_id = i.id 
                INNER JOIN pedidos p ON ip.pedido_id = p.id
                WHERE i.id = ? AND i.promotor_id = ? AND p.status = 'approved'
            ");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['vendas'] > 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Não é possível excluir ingresso que já possui vendas']);
                exit();
            }
            
            $stmt = $conn->prepare("DELETE FROM ingressos WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $id, $usuarioId);
            
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Ingresso excluído com sucesso!']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir ingresso']);
            }
            exit();
        }
    }
    
    // ===== ENDPOINTS PARA CUPONS =====
    
    // Listar cupons
    if ($action === 'load_cupons') {
        $stmt = $conn->prepare("
            SELECT c.*, 
                   COALESCE(COUNT(pc.cupom_id), 0) as usos_count,
                   CASE 
                       WHEN c.data_inicio > NOW() THEN 'agendado'
                       WHEN c.data_fim < NOW() THEN 'expirado'
                       WHEN c.limite_uso > 0 AND COALESCE(COUNT(pc.cupom_id), 0) >= c.limite_uso THEN 'esgotado'
                       WHEN c.ativo = 1 THEN 'ativo'
                       ELSE 'inativo'
                   END as status_cupom
            FROM cupons_desconto c
            LEFT JOIN pedidos_cupons pc ON c.id = pc.cupom_id
            WHERE c.promotor_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cupons = [];
        while ($row = $result->fetch_assoc()) {
            $cupons[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($cupons);
        exit();
    }
    
    // Buscar cupom específico
    if ($action === 'get_cupom') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("
                SELECT c.*,
                       GROUP_CONCAT(ce.evento_id) as eventos_ids
                FROM cupons_desconto c
                LEFT JOIN cupons_eventos ce ON c.id = ce.cupom_id
                WHERE c.id = ? AND c.promotor_id = ?
                GROUP BY c.id
            ");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $cupom = $result->fetch_assoc();
                header('Content-Type: application/json');
                echo json_encode($cupom);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Cupom não encontrado']);
            }
            exit();
        }
    }
    
    // Alternar status do cupom
    if ($action === 'toggle_cupom_status') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT ativo FROM cupons_desconto WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $newStatus = $row['ativo'] == 1 ? 0 : 1;
                
                $stmt = $conn->prepare("UPDATE cupons_desconto SET ativo = ? WHERE id = ? AND promotor_id = ?");
                $stmt->bind_param("iii", $newStatus, $id, $usuarioId);
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'status' => $newStatus,
                        'message' => $newStatus ? 'Cupom ativado com sucesso!' : 'Cupom desativado com sucesso!'
                    ]);
                }
            }
            exit();
        }
    }
    
    // Excluir cupom
    if ($action === 'delete_cupom') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            // Verificar se tem usos
            $stmt = $conn->prepare("
                SELECT COUNT(*) as usos
                FROM pedidos_cupons pc
                WHERE pc.cupom_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['usos'] > 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Não é possível excluir cupom que já foi usado']);
                exit();
            }
            
            // Excluir relacionamentos primeiro
            $conn->prepare("DELETE FROM cupons_eventos WHERE cupom_id = ?")->execute([$id]);
            
            $stmt = $conn->prepare("DELETE FROM cupons_desconto WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $id, $usuarioId);
            
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Cupom excluído com sucesso!']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir cupom']);
            }
            exit();
        }
    }
    
    // ===== ENDPOINTS PARA LOTES =====
    
    // Buscar lotes do ingresso
    if ($action === 'get_lotes') {
        $ingressoId = intval($_GET['ingresso_id'] ?? 0);
        if ($ingressoId > 0) {
            $stmt = $conn->prepare("
                SELECT l.* 
                FROM lotes_ingressos l
                INNER JOIN ingressos i ON l.ingresso_id = i.id
                WHERE l.ingresso_id = ? AND i.promotor_id = ?
                ORDER BY l.ordem ASC
            ");
            $stmt->bind_param("ii", $ingressoId, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $lotes = [];
            while ($row = $result->fetch_assoc()) {
                $lotes[] = $row;
            }
            
            header('Content-Type: application/json');
            echo json_encode($lotes);
            exit();
        }
    }
}

// ===== PROCESSAMENTO DE FORMULÁRIOS =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_ingresso') {
        $id = intval($_POST['id'] ?? 0);
        $evento_id = intval($_POST['evento_id'] ?? 0);
        $tipo_ingresso = sanitize($_POST['tipo_ingresso'] ?? '');
        $preco = floatval($_POST['preco'] ?? 0);
        $quantidade = intval($_POST['quantidade'] ?? 0);
        $usarLotes = isset($_POST['usar_lotes']) && $_POST['usar_lotes'] == '1';
        $lotes = $_POST['lotes'] ?? [];
        
        // Validações
        if (!$evento_id || !$tipo_ingresso || $preco <= 0 || $quantidade < 0) {
            $response['message'] = 'Preencha todos os campos obrigatórios!';
        } elseif ($usarLotes && empty($lotes)) {
            $response['message'] = 'Para usar lotes, adicione pelo menos um lote!';
        } else {
            // Verificar se evento pertence ao usuário
            $stmt = $conn->prepare("SELECT id FROM eventos WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $evento_id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Evento não encontrado ou não pertence a você!';
            } else {
                if ($id > 0) {
                    // Edição
                    $sql = "
                        UPDATE ingressos SET
                            evento_id = ?, tipo_ingresso = ?, preco = ?, quantidade = ?
                        WHERE id = ? AND promotor_id = ?
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isdiii", $evento_id, $tipo_ingresso, $preco, $quantidade, $id, $usuarioId);
                } else {
                    // Novo ingresso
                    $sql = "
                        INSERT INTO ingressos (
                            evento_id, tipo_ingresso, preco, quantidade, promotor_id, liberado
                        ) VALUES (?, ?, ?, ?, ?, 1)
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isdii", $evento_id, $tipo_ingresso, $preco, $quantidade, $usuarioId);
                }
                
                if ($stmt->execute()) {
                    $ingressoId = $id > 0 ? $id : $conn->insert_id;
                    
                    // Processar lotes se habilitado
                    if ($usarLotes && !empty($lotes)) {
                        // Remover lotes antigos se for edição
                        $conn->prepare("DELETE FROM lotes_ingressos WHERE ingresso_id = ?")->execute([$ingressoId]);
                        
                        // Adicionar novos lotes
                        $stmtLote = $conn->prepare("
                            INSERT INTO lotes_ingressos 
                            (ingresso_id, nome, descricao, preco, quantidade, data_inicio, data_fim, ordem, ativo) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        
                        foreach ($lotes as $index => $lote) {
                            $nomeLote = sanitize($lote['nome'] ?? '');
                            $descricaoLote = sanitize($lote['descricao'] ?? '');
                            $precoLote = floatval($lote['preco'] ?? 0);
                            $quantidadeLote = intval($lote['quantidade'] ?? 0);
                            $dataInicioLote = $lote['data_inicio'] ?? null;
                            $dataFimLote = $lote['data_fim'] ?? null;
                            
                            if ($nomeLote && $precoLote > 0 && $quantidadeLote > 0) {
                                if (empty($dataInicioLote)) $dataInicioLote = null;
                                if (empty($dataFimLote)) $dataFimLote = null;
                                
                                $stmtLote->bind_param("issdissi", 
                                    $ingressoId, $nomeLote, $descricaoLote, $precoLote, 
                                    $quantidadeLote, $dataInicioLote, $dataFimLote, $index + 1
                                );
                                $stmtLote->execute();
                            }
                        }
                    }
                    
                    $response['success'] = true;
                    $response['message'] = $id > 0 ? 'Ingresso atualizado com sucesso!' : 'Ingresso criado com sucesso!';
                } else {
                    $response['message'] = 'Erro ao salvar ingresso: ' . $conn->error;
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Salvar cupom
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_cupom') {
        $id = intval($_POST['cupom_id'] ?? 0);
        $codigo = strtoupper(sanitize($_POST['codigo'] ?? ''));
        $tipo_desconto = sanitize($_POST['tipo_desconto'] ?? '');
        $valor_desconto = floatval($_POST['valor_desconto'] ?? 0);
        $limite_uso = intval($_POST['limite_uso'] ?? 0);
        $data_inicio = $_POST['data_inicio'] ?? null;
        $data_fim = $_POST['data_fim'] ?? null;
        $descricao = sanitize($_POST['descricao'] ?? '');
        $eventos = $_POST['eventos'] ?? [];
        
        // Validações
        if (!$codigo || !$tipo_desconto || $valor_desconto <= 0) {
            $response['message'] = 'Preencha todos os campos obrigatórios!';
        } elseif ($tipo_desconto === 'percentual' && $valor_desconto > 100) {
            $response['message'] = 'Desconto percentual não pode ser maior que 100%!';
        } else {
            // Verificar se código já existe (exceto se for edição)
            $sql = "SELECT id FROM cupons_desconto WHERE codigo = ? AND promotor_id = ?";
            if ($id > 0) {
                $sql .= " AND id != ?";
            }
            
            $stmt = $conn->prepare($sql);
            if ($id > 0) {
                $stmt->bind_param("sii", $codigo, $usuarioId, $id);
            } else {
                $stmt->bind_param("si", $codigo, $usuarioId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['message'] = 'Já existe um cupom com este código!';
            } else {
                // Ajustar datas
                if (empty($data_inicio)) $data_inicio = null;
                if (empty($data_fim)) $data_fim = null;
                
                if ($id > 0) {
                    // Edição
                    $sql = "
                        UPDATE cupons_desconto SET
                            codigo = ?, tipo_desconto = ?, valor_desconto = ?, limite_uso = ?,
                            data_inicio = ?, data_fim = ?, descricao = ?
                        WHERE id = ? AND promotor_id = ?
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdissiii", 
                        $codigo, $tipo_desconto, $valor_desconto, $limite_uso,
                        $data_inicio, $data_fim, $descricao, $id, $usuarioId
                    );
                } else {
                    // Novo cupom
                    $sql = "
                        INSERT INTO cupons_desconto (
                            codigo, tipo_desconto, valor_desconto, limite_uso,
                            data_inicio, data_fim, descricao, promotor_id, ativo
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdisssi", 
                        $codigo, $tipo_desconto, $valor_desconto, $limite_uso,
                        $data_inicio, $data_fim, $descricao, $usuarioId
                    );
                }
                
                if ($stmt->execute()) {
                    $cupomId = $id > 0 ? $id : $conn->insert_id;
                    
                    // Atualizar relacionamentos com eventos
                    $conn->prepare("DELETE FROM cupons_eventos WHERE cupom_id = ?")->execute([$cupomId]);
                    
                    if (!empty($eventos)) {
                        $stmtEvento = $conn->prepare("INSERT INTO cupons_eventos (cupom_id, evento_id) VALUES (?, ?)");
                        foreach ($eventos as $eventoId) {
                            $eventoId = intval($eventoId);
                            if ($eventoId > 0) {
                                $stmtEvento->bind_param("ii", $cupomId, $eventoId);
                                $stmtEvento->execute();
                            }
                        }
                    }
                    
                    $response['success'] = true;
                    $response['message'] = $id > 0 ? 'Cupom atualizado com sucesso!' : 'Cupom criado com sucesso!';
                } else {
                    $response['message'] = 'Erro ao salvar cupom: ' . $conn->error;
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TicketSync - Gestão de Ingressos</title>
    <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/ingressos-new.css">
</head>
<body>
    <?php include('header_admin.php'); ?>
    
    <div class="container">
        <!-- Header da Página -->
        <div class="page-header">
            <div class="header-content">
                <div>
                    <div class="header-title">
                        <i class="fas fa-ticket-alt"></i>
                        <h1>Gestão de Ingressos</h1>
                    </div>
                    <p class="header-subtitle">
                        Sistema moderno e completo para gerenciar seus ingressos
                    </p>
                </div>
                <button id="btnNovoIngresso" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Novo Ingresso
                </button>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon primary">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-details">
                        <h3 id="totalIngressos">0</h3>
                        <p>Total de Ingressos</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <h3 id="ingressosLiberados">0</h3>
                        <p>Ingressos Liberados</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon warning">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-details">
                        <h3 id="totalVendas">0</h3>
                        <p>Vendas Realizadas</p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-content">
                    <div class="stat-icon success">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3 id="receitaTotal">R$ 0,00</h3>
                        <p>Receita Total</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="form-group">
                    <label class="form-label">Buscar</label>
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="form-control search-input" 
                               placeholder="Digite o nome do ingresso ou evento...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Evento</label>
                    <select id="eventoFilter" class="form-select">
                        <option value="">Todos os Eventos</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">Todos</option>
                        <option value="liberado">Liberado</option>
                        <option value="bloqueado">Bloqueado</option>
                        <option value="esgotado">Esgotado</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ordenar por</label>
                    <select id="ordenacaoSelect" class="form-select">
                        <option value="created_desc">Mais Recente</option>
                        <option value="created_asc">Mais Antigo</option>
                        <option value="nome_asc">Nome A-Z</option>
                        <option value="nome_desc">Nome Z-A</option>
                        <option value="preco_asc">Menor Preço</option>
                        <option value="preco_desc">Maior Preço</option>
                        <option value="vendas">Mais Vendidos</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Sistema de Cupons -->
        <div class="content-section" style="margin-bottom: 2rem;">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--gray-200);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; color: var(--gray-800); font-size: 1.25rem; font-weight: 700;">
                            <i class="fas fa-ticket-alt"></i>
                            Sistema de Cupons de Desconto
                        </h3>
                        <p style="margin: 0.5rem 0 0 0; color: var(--gray-600);">
                            Gerencie cupons de desconto para seus ingressos
                        </p>
                    </div>
                    <button id="btnNovoCupom" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Novo Cupom
                    </button>
                </div>
            </div>
            
            <div id="cuponsGrid" class="cupons-grid" style="padding: 1.5rem;">
                <!-- Cupons serão carregados aqui -->
            </div>
        </div>

        <!-- Conteúdo Principal -->
        <div class="content-section">
            <div id="ingressosGrid" class="ingressos-grid">
                <!-- Ingressos serão carregados aqui -->
            </div>
            
            <!-- Paginação -->
            <div id="paginationContainer" class="pagination-container">
                <!-- Paginação será inserida aqui -->
            </div>
        </div>
    </div>
    
    <!-- Modal de Ingresso -->
    <div id="ingressoModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" class="modal-title">
                    <i class="fas fa-ticket-alt"></i>
                    Novo Ingresso
                </h2>
                <button class="modal-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="ingressoForm" class="form-grid">
                    <input type="hidden" id="ingressoId" name="id">
                    <input type="hidden" name="acao" value="salvar_ingresso">
                    
                    <div class="form-group">
                        <label class="form-label">Evento *</label>
                        <select id="eventoSelect" name="evento_id" class="form-select" required>
                            <option value="">Selecione um evento...</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tipo de Ingresso *</label>
                            <input type="text" id="tipoIngresso" name="tipo_ingresso" class="form-control" 
                                   placeholder="Ex: Pista, Camarote, VIP..." required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Preço (R$) *</label>
                            <input type="number" id="preco" name="preco" class="form-control" 
                                   step="0.01" min="0" placeholder="0,00" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Quantidade *</label>
                        <input type="number" id="quantidade" name="quantidade" class="form-control" 
                               min="0" placeholder="100" required>
                    </div>
                    
                    <!-- Sistema de Lotes -->
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="usarLotes" style="margin: 0;">
                            <span class="form-label" style="margin: 0;">Usar sistema de lotes promocionais</span>
                        </label>
                        <small style="color: var(--gray-600); font-size: 0.8rem;">
                            Ative para criar diferentes períodos de venda com preços especiais
                        </small>
                    </div>
                    
                    <div id="lotesSection" class="hidden" style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 1rem; margin-top: 1rem;">
                        <h4 style="margin: 0 0 1rem 0; color: var(--gray-800); font-size: 1rem;">
                            <i class="fas fa-layer-group"></i>
                            Configuração de Lotes
                        </h4>
                        
                        <div id="lotesContainer">
                            <!-- Lotes serão adicionados aqui -->
                        </div>
                        
                        <button type="button" id="btnAdicionarLote" class="btn btn-secondary btn-sm" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i>
                            Adicionar Lote
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="ingressosSystem.closeModal()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" id="btnSalvar" form="ingressoForm" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Salvar Ingresso
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Cupom -->
    <div id="cupomModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="cupomModalTitle" class="modal-title">
                    <i class="fas fa-ticket-alt"></i>
                    Novo Cupom de Desconto
                </h2>
                <button class="modal-close" onclick="ingressosSystem.closeCupomModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="cupomForm" class="form-grid">
                    <input type="hidden" id="cupomId" name="cupom_id">
                    <input type="hidden" name="acao" value="salvar_cupom">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Código do Cupom *</label>
                            <input type="text" id="codigoCupom" name="codigo" class="form-control" 
                                   placeholder="Ex: DESC10, PROMO20..." style="text-transform: uppercase;" required>
                            <small style="color: var(--gray-600); font-size: 0.8rem;">Será convertido para maiúsculas</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tipo de Desconto *</label>
                            <select id="tipoDesconto" name="tipo_desconto" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="percentual">Percentual (%)</option>
                                <option value="valor_fixo">Valor Fixo (R$)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" id="labelValorDesconto">Valor do Desconto *</label>
                            <input type="number" id="valorDesconto" name="valor_desconto" class="form-control" 
                                   step="0.01" min="0" placeholder="0" required>
                            <small id="descontoHelper" style="color: var(--gray-600); font-size: 0.8rem;">
                                Informe o valor do desconto
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Limite de Uso</label>
                            <input type="number" id="limiteUso" name="limite_uso" class="form-control" 
                                   min="0" placeholder="0 = Ilimitado">
                            <small style="color: var(--gray-600); font-size: 0.8rem;">0 = sem limite</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Data de Início</label>
                            <input type="datetime-local" id="dataInicio" name="data_inicio" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Data de Fim</label>
                            <input type="datetime-local" id="dataFim" name="data_fim" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Eventos (Opcional)</label>
                        <select id="eventosSelect" name="eventos[]" class="form-select" multiple>
                            <!-- Eventos serão carregados aqui -->
                        </select>
                        <small style="color: var(--gray-600); font-size: 0.8rem;">
                            Deixe vazio para aplicar a todos os eventos
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea id="descricaoCupom" name="descricao" class="form-control" rows="3" 
                                  placeholder="Descrição opcional do cupom..."></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="ingressosSystem.closeCupomModal()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="submit" id="btnSalvarCupom" form="cupomForm" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Salvar Cupom
                </button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="assets/js/ingressos-new.js"></script>
</body>
</html>