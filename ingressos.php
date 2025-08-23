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
        
        // Validações
        if (!$evento_id || !$tipo_ingresso || $preco <= 0 || $quantidade < 0) {
            $response['message'] = 'Preencha todos os campos obrigatórios!';
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
    
    <!-- JavaScript -->
    <script src="assets/js/ingressos-new.js"></script>
</body>
</html>