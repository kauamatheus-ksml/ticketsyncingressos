<?php
/**
 * TicketSync - Gestão Moderna de Ingressos
 * Sistema Profissional de Ingressos - Versão 2.0
 * 
 * Funcionalidades implementadas:
 * - Dashboard com métricas em tempo real
 * - Sistema de filtros e busca avançada
 * - Múltiplas visualizações (Grid/Lista)
 * - Sistema de cupons de desconto
 * - Gestão de lotes e categorias
 * - Analytics de vendas
 * - Interface moderna responsiva
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

function generateTicketCode($length = 8) {
    return strtoupper(substr(md5(uniqid(rand(), true)), 0, $length));
}

// ===== PROCESSAMENTO DE REQUISIÇÕES AJAX =====

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Buscar ingressos com filtros avançados
    if ($action === 'search') {
        $search = sanitize($_GET['q'] ?? '');
        $eventoId = intval($_GET['evento_id'] ?? 0);
        $categoriaId = intval($_GET['categoria_id'] ?? 0);
        $status = sanitize($_GET['status'] ?? '');
        $precoMin = floatval($_GET['preco_min'] ?? 0);
        $precoMax = floatval($_GET['preco_max'] ?? 0);
        $ordenacao = sanitize($_GET['ordem'] ?? 'created_desc');
        $visualizacao = sanitize($_GET['view'] ?? 'grid');
        $page = intval($_GET['page'] ?? 1);
        $limit = 12;
        $offset = ($page - 1) * $limit;
        
        $where = ["i.promotor_id = ?"];
        $params = [$usuarioId];
        $types = "i";
        
        if (!empty($search)) {
            $where[] = "(i.tipo_ingresso LIKE ? OR i.nome LIKE ? OR e.nome LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            $types .= "sss";
        }
        
        if ($eventoId > 0) {
            $where[] = "i.evento_id = ?";
            $params[] = $eventoId;
            $types .= "i";
        }
        
        if ($categoriaId > 0) {
            $where[] = "i.categoria_id = ?";
            $params[] = $categoriaId;
            $types .= "i";
        }
        
        if (!empty($status)) {
            if ($status === 'liberado') {
                $where[] = "i.liberado = 1";
            } elseif ($status === 'bloqueado') {
                $where[] = "i.liberado = 0";
            } elseif ($status === 'esgotado') {
                $where[] = "i.quantidade <= i.vendas_count";
            }
        }
        
        if ($precoMin > 0) {
            $where[] = "i.preco >= ?";
            $params[] = $precoMin;
            $types .= "d";
        }
        
        if ($precoMax > 0) {
            $where[] = "i.preco <= ?";
            $params[] = $precoMax;
            $types .= "d";
        }
        
        // Ordenação
        $orderBy = "i.created_at DESC";
        switch ($ordenacao) {
            case 'created_asc': $orderBy = "i.created_at ASC"; break;
            case 'nome_asc': $orderBy = "i.tipo_ingresso ASC"; break;
            case 'nome_desc': $orderBy = "i.tipo_ingresso DESC"; break;
            case 'preco_asc': $orderBy = "i.preco ASC"; break;
            case 'preco_desc': $orderBy = "i.preco DESC"; break;
            case 'vendas': $orderBy = "i.vendas_count DESC"; break;
            case 'disponivel': $orderBy = "(i.quantidade - i.vendas_count) DESC"; break;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Query principal
        $sql = "
            SELECT i.*, 
                   e.nome AS evento_nome, 
                   e.data_inicio, 
                   e.hora_inicio,
                   e.local, 
                   e.atracoes, 
                   e.logo,
                   c.nome AS categoria_nome,
                   c.cor AS categoria_cor,
                   c.icone AS categoria_icone,
                   (i.quantidade - COALESCE(i.vendas_count, 0)) AS disponivel,
                   COALESCE(i.vendas_count, 0) * i.preco AS receita_total
            FROM ingressos i
            INNER JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN categorias_ingressos c ON i.categoria_id = c.id
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ingressos = [];
        while ($row = $result->fetch_assoc()) {
            $ingressos[] = $row;
        }
        
        // Contar total para paginação
        $sqlCount = "
            SELECT COUNT(*) as total 
            FROM ingressos i
            INNER JOIN eventos e ON i.evento_id = e.id
            LEFT JOIN categorias_ingressos c ON i.categoria_id = c.id
            WHERE $whereClause
        ";
        $stmtCount = $conn->prepare($sqlCount);
        $typesCount = str_replace("ii", "", $types); // Remove limit e offset
        $paramsCount = array_slice($params, 0, -2); // Remove limit e offset
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
            'totalPages' => ceil($total / $limit),
            'visualizacao' => $visualizacao
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
    
    // Carregar categorias de ingressos
    if ($action === 'categorias') {
        $stmt = $conn->prepare("SELECT * FROM categorias_ingressos WHERE ativo = 1 ORDER BY ordem ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categorias = [];
        while ($row = $result->fetch_assoc()) {
            $categorias[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($categorias);
        exit();
    }
    
    // Carregar estatísticas do dashboard
    if ($action === 'stats') {
        $stats = [];
        
        // Total de ingressos criados
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
        $stmt = $conn->prepare("SELECT COALESCE(SUM(vendas_count), 0) as total FROM ingressos WHERE promotor_id = ?");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_vendas'] = $result->fetch_assoc()['total'];
        
        // Receita total
        $stmt = $conn->prepare("SELECT COALESCE(SUM(vendas_count * preco), 0) as total FROM ingressos WHERE promotor_id = ?");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['receita_total'] = $result->fetch_assoc()['total'];
        
        // Ingressos por categoria
        $stmt = $conn->prepare("
            SELECT c.nome, c.cor, COUNT(i.id) as total
            FROM ingressos i
            LEFT JOIN categorias_ingressos c ON i.categoria_id = c.id
            WHERE i.promotor_id = ?
            GROUP BY c.id, c.nome, c.cor
            ORDER BY total DESC
        ");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats['por_categoria'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['por_categoria'][] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($stats);
        exit();
    }
    
    // Dados de um ingresso específico para edição
    if ($action === 'get_ingresso') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("
                SELECT i.*, 
                       e.nome AS evento_nome,
                       c.nome AS categoria_nome
                FROM ingressos i
                INNER JOIN eventos e ON i.evento_id = e.id
                LEFT JOIN categorias_ingressos c ON i.categoria_id = c.id
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
    
    // Toggle status do ingresso
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
                        'message' => $newStatus ? 'Ingresso liberado' : 'Ingresso bloqueado'
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
            $stmt = $conn->prepare("SELECT vendas_count FROM ingressos WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['vendas_count'] > 0) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Não é possível excluir ingresso com vendas']);
                    exit();
                }
                
                $stmt = $conn->prepare("DELETE FROM ingressos WHERE id = ? AND promotor_id = ?");
                $stmt->bind_param("ii", $id, $usuarioId);
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Ingresso excluído com sucesso']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Erro ao excluir ingresso']);
                }
            }
            exit();
        }
    }
}

// ===== PROCESSAMENTO DE FORMULÁRIOS =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Salvar/editar ingresso
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_ingresso') {
        $id = intval($_POST['id'] ?? 0);
        $evento_id = intval($_POST['evento_id'] ?? 0);
        $categoria_id = intval($_POST['categoria_id'] ?? 0) ?: null;
        $tipo_ingresso = sanitize($_POST['tipo_ingresso'] ?? '');
        $nome = sanitize($_POST['nome'] ?? '');
        $descricao = sanitize($_POST['descricao'] ?? '');
        $preco = floatval($_POST['preco'] ?? 0);
        $preco_meia = floatval($_POST['preco_meia'] ?? 0) ?: null;
        $quantidade = intval($_POST['quantidade'] ?? 0);
        $quantidade_minima = intval($_POST['quantidade_minima'] ?? 1);
        $quantidade_maxima = intval($_POST['quantidade_maxima'] ?? 10);
        $meia_entrada = isset($_POST['meia_entrada']) ? 1 : 0;
        $cor = sanitize($_POST['cor'] ?? '#4299e1');
        $data_inicio_vendas = sanitize($_POST['data_inicio_vendas'] ?? '');
        $data_fim_vendas = sanitize($_POST['data_fim_vendas'] ?? '');
        
        // Validações
        if (!$evento_id || !$tipo_ingresso || $preco <= 0 || $quantidade < 0) {
            $response['message'] = 'Dados obrigatórios não preenchidos!';
        } else {
            // Verificar se evento pertence ao usuário
            $stmt = $conn->prepare("SELECT id FROM eventos WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $evento_id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $response['message'] = 'Evento não encontrado!';
            } else {
                if ($id > 0) {
                    // Edição
                    $sql = "
                        UPDATE ingressos SET
                            evento_id = ?, categoria_id = ?, tipo_ingresso = ?, nome = ?,
                            descricao = ?, preco = ?, preco_meia = ?, quantidade = ?,
                            quantidade_minima = ?, quantidade_maxima = ?, meia_entrada = ?,
                            cor = ?, data_inicio_vendas = ?, data_fim_vendas = ?, 
                            updated_at = NOW()
                        WHERE id = ? AND promotor_id = ?
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(
                        "iisssdiiiiissii", 
                        $evento_id, $categoria_id, $tipo_ingresso, $nome,
                        $descricao, $preco, $preco_meia, $quantidade,
                        $quantidade_minima, $quantidade_maxima, $meia_entrada,
                        $cor, $data_inicio_vendas, $data_fim_vendas, $id, $usuarioId
                    );
                } else {
                    // Novo ingresso
                    $sql = "
                        INSERT INTO ingressos (
                            evento_id, categoria_id, tipo_ingresso, nome, descricao,
                            preco, preco_meia, quantidade, quantidade_minima, quantidade_maxima,
                            meia_entrada, cor, data_inicio_vendas, data_fim_vendas, 
                            promotor_id, liberado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param(
                        "iisssdiiiiisssi", 
                        $evento_id, $categoria_id, $tipo_ingresso, $nome, $descricao,
                        $preco, $preco_meia, $quantidade, $quantidade_minima, $quantidade_maxima,
                        $meia_entrada, $cor, $data_inicio_vendas, $data_fim_vendas, $usuarioId
                    );
                }
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = $id > 0 ? 'Ingresso atualizado com sucesso!' : 'Ingresso criado com sucesso!';
                    $response['ingresso_id'] = $id > 0 ? $id : $conn->insert_id;
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
    
    <!-- CSS Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="assets/css/ingressos.css">
</head>
<body>
    <?php include('header_admin.php'); ?>
    
    <!-- Container Principal -->
    <div class="container">
        <!-- Header da Página -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1>
                            <i class="fas fa-ticket-alt"></i>
                            Gestão de Ingressos
                        </h1>
                        <p class="text-muted mb-0">
                            Gerencie seus ingressos de forma moderna e eficiente
                        </p>
                    </div>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#ingressoModal">
                        <i class="fas fa-plus me-2"></i>
                        Novo Ingresso
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Cards -->
        <div class="stats-container" id="statsContainer">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="stat-content">
                    <h3 id="totalIngressos">0</h3>
                    <p>Total de Ingressos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #38a169 0%, #48bb78 100%);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="ingressosLiberados">0</h3>
                    <p>Ingressos Liberados</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936 0%, #f6ad55 100%);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-content">
                    <h3 id="totalVendas">0</h3>
                    <p>Vendas Realizadas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #38a169 0%, #4fd1c7 100%);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3 id="receitaTotal">R$ 0</h3>
                    <p>Receita Total</p>
                </div>
            </div>
        </div>
        
        <!-- Filtros e Barra de Busca -->
        <div class="filters-container">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="form-control search-input" 
                               placeholder="Buscar ingressos...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="eventoFilter" class="form-select">
                        <option value="">Todos os Eventos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="categoriaFilter" class="form-select">
                        <option value="">Todas as Categorias</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="statusFilter" class="form-select">
                        <option value="">Todos os Status</option>
                        <option value="liberado">Liberado</option>
                        <option value="bloqueado">Bloqueado</option>
                        <option value="esgotado">Esgotado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="ordenacaoSelect" class="form-select">
                        <option value="created_desc">Mais Recente</option>
                        <option value="created_asc">Mais Antigo</option>
                        <option value="nome_asc">Nome A-Z</option>
                        <option value="nome_desc">Nome Z-A</option>
                        <option value="preco_asc">Menor Preço</option>
                        <option value="preco_desc">Maior Preço</option>
                        <option value="vendas">Mais Vendidos</option>
                        <option value="disponivel">Mais Disponível</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Área de Conteúdo -->
        <div class="row">
            <div class="col-12">
                <!-- Grid de Ingressos -->
                <div id="ingressosGrid" class="grid"></div>
                
                <!-- Loading -->
                <div id="loadingContainer" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando ingressos...</span>
                    </div>
                </div>
                
                <!-- Sem Resultados -->
                <div id="noResultsContainer" class="text-center py-5 d-none">
                    <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Nenhum ingresso encontrado</h4>
                    <p class="text-muted">Tente ajustar os filtros ou criar um novo ingresso</p>
                </div>
            </div>
        </div>
        
        <!-- Paginação -->
        <div class="row mt-4">
            <div class="col-12">
                <nav id="paginationContainer" class="d-flex justify-content-center">
                    <!-- Paginação será inserida aqui via JavaScript -->
                </nav>
            </div>
        </div>
    </div>
    
    <!-- Modal de Ingresso -->
    <div class="modal fade" id="ingressoModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-ticket-alt me-2"></i>
                        <span id="modalTitle">Novo Ingresso</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="ingressoForm">
                        <input type="hidden" id="ingressoId" name="id">
                        <input type="hidden" name="acao" value="salvar_ingresso">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Evento *</label>
                                <select name="evento_id" id="eventoSelect" class="form-select" required>
                                    <option value="">Selecionar evento...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Categoria</label>
                                <select name="categoria_id" id="categoriaSelect" class="form-select">
                                    <option value="">Selecionar categoria...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo de Ingresso *</label>
                                <input type="text" name="tipo_ingresso" class="form-control" 
                                       placeholder="Ex: Pista, Camarote, VIP" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nome do Ingresso</label>
                                <input type="text" name="nome" class="form-control" 
                                       placeholder="Nome descritivo (opcional)">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2" 
                                          placeholder="Descrição detalhada do ingresso"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preço (R$) *</label>
                                <input type="number" name="preco" step="0.01" min="0" class="form-control" 
                                       placeholder="0.00" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Preço Meia Entrada (R$)</label>
                                <input type="number" name="preco_meia" step="0.01" min="0" class="form-control" 
                                       placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Quantidade *</label>
                                <input type="number" name="quantidade" min="0" class="form-control" 
                                       placeholder="100" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Qtd. Mínima por Compra</label>
                                <input type="number" name="quantidade_minima" min="1" value="1" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Qtd. Máxima por Compra</label>
                                <input type="number" name="quantidade_maxima" min="1" value="10" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cor de Identificação</label>
                                <input type="color" name="cor" value="#4299e1" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Início das Vendas</label>
                                <input type="datetime-local" name="data_inicio_vendas" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fim das Vendas</label>
                                <input type="datetime-local" name="data_fim_vendas" class="form-control">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="meia_entrada" id="meiaEntrada">
                                    <label class="form-check-label" for="meiaEntrada">
                                        Aceita meia entrada
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancelar
                    </button>
                    <button type="submit" form="ingressoForm" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        <span id="btnSalvarText">Salvar Ingresso</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/ingressos-modern.js"></script>
</body>
</html>