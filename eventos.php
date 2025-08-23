<?php
/**
 * TicketSync - Sistema Moderno de Gerenciamento de Eventos
 * Versão 2.0 - Completamente Reimaginado
 */

// Configurações iniciais
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includes e permissões
include_once('check_permissions.php');
if (!checkPermission("eventos")) {
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

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[àáâãäå]/', 'a', $text);
    $text = preg_replace('/[èéêë]/', 'e', $text);
    $text = preg_replace('/[ìíîï]/', 'i', $text);
    $text = preg_replace('/[òóôõö]/', 'o', $text);
    $text = preg_replace('/[ùúûü]/', 'u', $text);
    $text = preg_replace('/[ç]/', 'c', $text);
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function uploadImagem($file, $targetDir = "uploads/eventos/") {
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }
    
    if (empty($file['name'])) return null;
    
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    $filename = basename($file['name']);
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) return null;
    if ($file['size'] > $maxSize) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    
    $uniqueName = uniqid('evt_', true) . '.' . $fileExt;
    $targetFile = $targetDir . $uniqueName;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $targetFile;
    }
    return null;
}

function registrarAnalytics($eventoId, $tipoAcao, $usuarioId = null, $tipoUsuario = null) {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    
    $stmt = $conn->prepare("
        INSERT INTO evento_analytics 
        (evento_id, tipo_acao, usuario_id, tipo_usuario, ip_address, user_agent, referrer) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isisiss", $eventoId, $tipoAcao, $usuarioId, $tipoUsuario, $ip, $userAgent, $referrer);
    $stmt->execute();
    $stmt->close();
}

// ===== PROCESSAMENTO DE REQUISIÇÕES AJAX =====

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Buscar eventos com filtros
    if ($action === 'search') {
        $search = sanitize($_GET['q'] ?? '');
        $categoria = intval($_GET['categoria'] ?? 0);
        $status = sanitize($_GET['status'] ?? '');
        $dataInicio = sanitize($_GET['data_inicio'] ?? '');
        $dataFim = sanitize($_GET['data_fim'] ?? '');
        $ordenacao = sanitize($_GET['ordem'] ?? 'data_asc');
        $visualizacao = sanitize($_GET['view'] ?? 'grid');
        $page = intval($_GET['page'] ?? 1);
        $limit = 12;
        $offset = ($page - 1) * $limit;
        
        $where = ["promotor_id = ?"];
        $params = [$usuarioId];
        $types = "i";
        
        if (!empty($search)) {
            $where[] = "(nome LIKE ? OR local LIKE ? OR atracoes LIKE ? OR descricao_evento LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= "ssss";
        }
        
        if ($categoria > 0) {
            $where[] = "categoria_id = ?";
            $params[] = $categoria;
            $types .= "i";
        }
        
        if (!empty($status)) {
            $where[] = "status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if (!empty($dataInicio)) {
            $where[] = "data_inicio >= ?";
            $params[] = $dataInicio;
            $types .= "s";
        }
        
        if (!empty($dataFim)) {
            $where[] = "data_inicio <= ?";
            $params[] = $dataFim;
            $types .= "s";
        }
        
        // Ordenação
        $orderBy = "data_inicio ASC";
        switch ($ordenacao) {
            case 'data_desc': $orderBy = "data_inicio DESC"; break;
            case 'nome_asc': $orderBy = "nome ASC"; break;
            case 'nome_desc': $orderBy = "nome DESC"; break;
            case 'rating': $orderBy = "rating_medio DESC, total_avaliacoes DESC"; break;
            case 'visualizacoes': $orderBy = "visualizacoes DESC"; break;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Query principal
        $sql = "
            SELECT e.*, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone,
                   (SELECT COUNT(*) FROM usuario_favoritos uf WHERE uf.evento_id = e.id) as total_favoritos,
                   (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM evento_tags et 
                    JOIN tags_eventos t ON et.tag_id = t.id WHERE et.evento_id = e.id) as tags
            FROM eventos e
            LEFT JOIN categorias_eventos c ON e.categoria_id = c.id
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
        
        $eventos = [];
        while ($row = $result->fetch_assoc()) {
            // Incrementar visualizações
            registrarAnalytics($row['id'], 'view', $usuarioId, 'admin');
            
            $eventos[] = $row;
        }
        
        // Contar total para paginação
        $sqlCount = "SELECT COUNT(*) as total FROM eventos e WHERE $whereClause";
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
            'eventos' => $eventos,
            'total' => $total,
            'page' => $page,
            'totalPages' => ceil($total / $limit),
            'visualizacao' => $visualizacao
        ]);
        exit();
    }
    
    // Carregar categorias
    if ($action === 'categorias') {
        $stmt = $conn->prepare("SELECT * FROM categorias_eventos WHERE ativo = 1 ORDER BY ordem ASC, nome ASC");
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
    
    // Carregar tags populares
    if ($action === 'tags') {
        $stmt = $conn->prepare("SELECT * FROM tags_eventos ORDER BY uso_count DESC, nome ASC LIMIT 20");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tags = [];
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($tags);
        exit();
    }
    
    // Adicionar/remover favorito
    if ($action === 'toggle_favorite') {
        $eventoId = intval($_GET['evento_id'] ?? 0);
        if ($eventoId > 0) {
            // Verificar se já é favorito
            $stmt = $conn->prepare("
                SELECT id FROM usuario_favoritos 
                WHERE usuario_id = ? AND evento_id = ? AND tipo_usuario = 'admin'
            ");
            $stmt->bind_param("ii", $usuarioId, $eventoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Remover favorito
                $stmt = $conn->prepare("
                    DELETE FROM usuario_favoritos 
                    WHERE usuario_id = ? AND evento_id = ? AND tipo_usuario = 'admin'
                ");
                $stmt->bind_param("ii", $usuarioId, $eventoId);
                $stmt->execute();
                
                registrarAnalytics($eventoId, 'unfavorite', $usuarioId, 'admin');
                
                echo json_encode(['favorito' => false]);
            } else {
                // Adicionar favorito
                $stmt = $conn->prepare("
                    INSERT INTO usuario_favoritos (usuario_id, evento_id, tipo_usuario) 
                    VALUES (?, ?, 'admin')
                ");
                $stmt->bind_param("ii", $usuarioId, $eventoId);
                $stmt->execute();
                
                registrarAnalytics($eventoId, 'favorite', $usuarioId, 'admin');
                
                echo json_encode(['favorito' => true]);
            }
        }
        exit();
    }
    
    // Carregar estatísticas do dashboard
    if ($action === 'stats') {
        $stats = [];
        
        // Total de eventos
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM eventos WHERE promotor_id = ?");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_eventos'] = $result->fetch_assoc()['total'];
        
        // Eventos ativos (futuros)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total FROM eventos 
            WHERE promotor_id = ? AND data_inicio >= CURDATE()
        ");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['eventos_ativos'] = $result->fetch_assoc()['total'];
        
        // Total de visualizações
        $stmt = $conn->prepare("SELECT SUM(visualizacoes) as total FROM eventos WHERE promotor_id = ?");
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_visualizacoes'] = $result->fetch_assoc()['total'] ?? 0;
        
        // Eventos por categoria
        $stmt = $conn->prepare("
            SELECT c.nome, COUNT(e.id) as total, c.cor
            FROM eventos e
            LEFT JOIN categorias_eventos c ON e.categoria_id = c.id
            WHERE e.promotor_id = ?
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
    
    // Carregar dados de um evento específico para edição
    if ($action === 'get_evento') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("
                SELECT e.*, 
                       c.nome as categoria_nome,
                       (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM evento_tags et 
                        JOIN tags_eventos t ON et.tag_id = t.id WHERE et.evento_id = e.id) as tags
                FROM eventos e
                LEFT JOIN categorias_eventos c ON e.categoria_id = c.id
                WHERE e.id = ? AND e.promotor_id = ?
            ");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $evento = $result->fetch_assoc();
                header('Content-Type: application/json');
                echo json_encode($evento);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Evento não encontrado']);
            }
            exit();
        }
    }
    
    // Excluir evento
    if ($action === 'delete_evento') {
        $id = intval($_GET['id'] ?? 0);
        if ($id > 0 && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            // Verificar se o evento pertence ao usuário
            $stmt = $conn->prepare("SELECT id FROM eventos WHERE id = ? AND promotor_id = ?");
            $stmt->bind_param("ii", $id, $usuarioId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Excluir evento (cascade irá excluir relacionamentos)
                $stmt = $conn->prepare("DELETE FROM eventos WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Evento excluído com sucesso!']);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Erro ao excluir evento']);
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Evento não encontrado ou sem permissão']);
            }
            exit();
        }
    }
    
    // Registrar compartilhamento
    if ($action === 'registrar_compartilhamento') {
        $eventoId = intval($_GET['evento_id'] ?? 0);
        $tipo = sanitize($_GET['tipo'] ?? 'link');
        
        if ($eventoId > 0) {
            // Incrementar contador de compartilhamentos
            $stmt = $conn->prepare("UPDATE eventos SET compartilhamentos = compartilhamentos + 1 WHERE id = ?");
            $stmt->bind_param("i", $eventoId);
            $stmt->execute();
            
            // Registrar analytics
            registrarAnalytics($eventoId, 'share', $usuarioId, 'admin');
            
            echo json_encode(['success' => true]);
        }
        exit();
    }
}

// ===== PROCESSAMENTO DE FORMULÁRIOS =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Cadastro/edição de evento
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_evento') {
        $id = intval($_POST['id'] ?? 0);
        $nome = sanitize($_POST['nome'] ?? '');
        $categoria_id = intval($_POST['categoria_id'] ?? 0);
        $data_inicio = sanitize($_POST['data_inicio'] ?? '');
        $hora_inicio = sanitize($_POST['hora_inicio'] ?? '');
        $data_termino = sanitize($_POST['data_termino'] ?? '');
        $hora_termino = sanitize($_POST['hora_termino'] ?? '');
        $local = sanitize($_POST['local'] ?? '');
        $lat = !empty($_POST['lat']) ? floatval($_POST['lat']) : null;
        $lng = !empty($_POST['lng']) ? floatval($_POST['lng']) : null;
        $atracoes = sanitize($_POST['atracoes'] ?? '');
        $descricao_evento = sanitize($_POST['descricao_evento'] ?? '');
        $faixa_etaria = sanitize($_POST['faixa_etaria'] ?? 'livre');
        $capacidade_maxima = !empty($_POST['capacidade_maxima']) ? intval($_POST['capacidade_maxima']) : null;
        $tipo_evento = sanitize($_POST['tipo_evento'] ?? 'presencial');
        $link_transmissao = sanitize($_POST['link_transmissao'] ?? '');
        $acessibilidade = sanitize($_POST['acessibilidade'] ?? '');
        $politica_cancelamento = sanitize($_POST['politica_cancelamento'] ?? '');
        $codigo_vestimenta = sanitize($_POST['codigo_vestimenta'] ?? '');
        $estacionamento = sanitize($_POST['estacionamento'] ?? '');
        $observacoes_importantes = sanitize($_POST['observacoes_importantes'] ?? '');
        $destaque = isset($_POST['destaque']) ? 1 : 0;
        
        // Campos SEO
        $meta_title = sanitize($_POST['meta_title'] ?? $nome);
        $meta_description = sanitize($_POST['meta_description'] ?? '');
        $slug = generateSlug($_POST['slug'] ?? $nome);
        
        // Validações básicas
        if (empty($nome) || empty($data_inicio) || empty($hora_inicio) || 
            empty($data_termino) || empty($hora_termino) || empty($local) || 
            empty($atracoes) || empty($descricao_evento)) {
            $response['message'] = 'Todos os campos obrigatórios devem ser preenchidos.';
        } else {
            // Upload da imagem principal
            $logo = null;
            if (!empty($_FILES['logo']['name'])) {
                $logo = uploadImagem($_FILES['logo']);
                if (!$logo) {
                    $response['message'] = 'Erro no upload da imagem principal.';
                    echo json_encode($response);
                    exit();
                }
            }
            
            if ($id > 0) {
                // Edição
                $sql = "
                    UPDATE eventos SET
                        nome = ?, categoria_id = ?, data_inicio = ?, hora_inicio = ?,
                        data_termino = ?, hora_termino = ?, local = ?, lat = ?, lng = ?,
                        atracoes = ?, descricao_evento = ?, faixa_etaria = ?,
                        capacidade_maxima = ?, tipo_evento = ?, link_transmissao = ?,
                        acessibilidade = ?, politica_cancelamento = ?, codigo_vestimenta = ?,
                        estacionamento = ?, observacoes_importantes = ?, destaque = ?,
                        meta_title = ?, meta_description = ?, slug = ?, updated_at = NOW()
                        " . ($logo ? ", logo = ?" : "") . "
                    WHERE id = ? AND promotor_id = ?
                ";
                
                $params = [
                    $nome, $categoria_id, $data_inicio, $hora_inicio, $data_termino, $hora_termino,
                    $local, $lat, $lng, $atracoes, $descricao_evento, $faixa_etaria,
                    $capacidade_maxima, $tipo_evento, $link_transmissao, $acessibilidade,
                    $politica_cancelamento, $codigo_vestimenta, $estacionamento,
                    $observacoes_importantes, $destaque, $meta_title, $meta_description, $slug
                ];
                $types = "siissssddsssisssssssisss";
                
                if ($logo) {
                    $params[] = $logo;
                    $types .= "s";
                }
                
                $params[] = $id;
                $params[] = $usuarioId;
                $types .= "ii";
                
            } else {
                // Novo evento
                $codigo_evento = rand(1000, 9999);
                
                $sql = "
                    INSERT INTO eventos (
                        nome, categoria_id, data_inicio, hora_inicio, data_termino, hora_termino,
                        local, lat, lng, atracoes, descricao_evento, promotor_id, status,
                        codigo_evento, faixa_etaria, capacidade_maxima, tipo_evento,
                        link_transmissao, acessibilidade, politica_cancelamento,
                        codigo_vestimenta, estacionamento, observacoes_importantes,
                        destaque, meta_title, meta_description, slug, logo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $params = [
                    $nome, $categoria_id, $data_inicio, $hora_inicio, $data_termino, $hora_termino,
                    $local, $lat, $lng, $atracoes, $descricao_evento, $usuarioId, $codigo_evento,
                    $faixa_etaria, $capacidade_maxima, $tipo_evento, $link_transmissao,
                    $acessibilidade, $politica_cancelamento, $codigo_vestimenta,
                    $estacionamento, $observacoes_importantes, $destaque, $meta_title,
                    $meta_description, $slug, $logo
                ];
                $types = "siissssddsssisssisssssisss";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $eventoId = $id > 0 ? $id : $conn->insert_id;
                
                // Processar tags
                if (!empty($_POST['tags'])) {
                    // Limpar tags existentes
                    $stmt = $conn->prepare("DELETE FROM evento_tags WHERE evento_id = ?");
                    $stmt->bind_param("i", $eventoId);
                    $stmt->execute();
                    
                    $tags = explode(',', $_POST['tags']);
                    foreach ($tags as $tagNome) {
                        $tagNome = trim($tagNome);
                        if (!empty($tagNome)) {
                            // Verificar se tag existe
                            $stmt = $conn->prepare("SELECT id FROM tags_eventos WHERE nome = ?");
                            $stmt->bind_param("s", $tagNome);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $tagId = $result->fetch_assoc()['id'];
                            } else {
                                // Criar nova tag
                                $stmt = $conn->prepare("INSERT INTO tags_eventos (nome) VALUES (?)");
                                $stmt->bind_param("s", $tagNome);
                                $stmt->execute();
                                $tagId = $conn->insert_id;
                            }
                            
                            // Associar tag ao evento
                            $stmt = $conn->prepare("INSERT IGNORE INTO evento_tags (evento_id, tag_id) VALUES (?, ?)");
                            $stmt->bind_param("ii", $eventoId, $tagId);
                            $stmt->execute();
                        }
                    }
                }
                
                // Upload de galeria adicional
                if (!empty($_FILES['galeria']['name'][0])) {
                    foreach ($_FILES['galeria']['name'] as $key => $filename) {
                        if (!empty($filename)) {
                            $galeria_file = [
                                'name' => $_FILES['galeria']['name'][$key],
                                'tmp_name' => $_FILES['galeria']['tmp_name'][$key],
                                'size' => $_FILES['galeria']['size'][$key],
                                'error' => $_FILES['galeria']['error'][$key]
                            ];
                            
                            $imagemPath = uploadImagem($galeria_file);
                            if ($imagemPath) {
                                $stmt = $conn->prepare("
                                    INSERT INTO evento_galeria (evento_id, imagem_path, ordem) 
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->bind_param("isi", $eventoId, $imagemPath, $key);
                                $stmt->execute();
                            }
                        }
                    }
                }
                
                $response['success'] = true;
                $response['message'] = $id > 0 ? 'Evento atualizado com sucesso!' : 'Evento criado com sucesso!';
                $response['evento_id'] = $eventoId;
            } else {
                $response['message'] = 'Erro ao salvar evento: ' . $conn->error;
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
    <title>TicketSync - Gestão de Eventos</title>
    <link rel="icon" href="uploads/ticketsync.ico" type="image/x-icon">
    
    <!-- CSS Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Mapbox -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css">
    
    <!-- CSS Customizado -->
    <link rel="stylesheet" href="assets/css/eventos.css">
</head>
<body class="eventos-modern-body">
    <?php include('header_admin.php'); ?>
    
    <!-- Preloader -->
    <div id="preloader" class="preloader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
        </div>
    </div>
    
    <!-- Container Principal -->
    <div class="container-fluid px-4 py-3">
        <!-- Header da Página -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title mb-1">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Gestão de Eventos
                        </h1>
                        <p class="page-subtitle text-muted mb-0">
                            Gerencie seus eventos de forma moderna e eficiente
                        </p>
                    </div>
                    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#eventoModal">
                        <i class="fas fa-plus me-2"></i>
                        Novo Evento
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Cards -->
        <div class="row mb-4" id="statsContainer">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-content">
                        <h3 id="totalEventos">0</h3>
                        <p>Total de Eventos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-success">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stats-content">
                        <h3 id="eventosAtivos">0</h3>
                        <p>Eventos Ativos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-info">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stats-content">
                        <h3 id="totalVisualizacoes">0</h3>
                        <p>Visualizações</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon bg-warning">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stats-content">
                        <h3 id="avaliacaoMedia">0.0</h3>
                        <p>Avaliação Média</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros e Barra de Busca -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="filters-container">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="searchInput" class="form-control search-input" 
                                       placeholder="Buscar eventos...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select id="categoriaFilter" class="form-select">
                                <option value="">Todas as Categorias</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="statusFilter" class="form-select">
                                <option value="">Todos os Status</option>
                                <option value="pendente">Pendente</option>
                                <option value="aprovado">Aprovado</option>
                                <option value="desaprovado">Desaprovado</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select id="ordenacaoSelect" class="form-select">
                                <option value="data_asc">Data ↑</option>
                                <option value="data_desc">Data ↓</option>
                                <option value="nome_asc">Nome A-Z</option>
                                <option value="nome_desc">Nome Z-A</option>
                                <option value="rating">Melhor Avaliado</option>
                                <option value="visualizacoes">Mais Visto</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="view-toggle">
                                <button id="gridView" class="btn btn-outline-secondary active">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button id="listView" class="btn btn-outline-secondary">
                                    <i class="fas fa-list"></i>
                                </button>
                                <button id="mapView" class="btn btn-outline-secondary">
                                    <i class="fas fa-map"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Área de Conteúdo -->
        <div class="row">
            <div class="col-12">
                <!-- Grid de Eventos -->
                <div id="eventosGrid" class="eventos-grid"></div>
                
                <!-- Visualização em Lista -->
                <div id="eventosList" class="eventos-list d-none"></div>
                
                <!-- Visualização em Mapa -->
                <div id="eventosMap" class="eventos-map d-none">
                    <div id="mapContainer" style="height: 600px; border-radius: 10px;"></div>
                </div>
                
                <!-- Loading -->
                <div id="loadingContainer" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando eventos...</span>
                    </div>
                </div>
                
                <!-- Sem Resultados -->
                <div id="noResultsContainer" class="text-center py-5 d-none">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Nenhum evento encontrado</h4>
                    <p class="text-muted">Tente ajustar os filtros ou criar um novo evento</p>
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
    
    <!-- Modal de Evento -->
    <div class="modal fade" id="eventoModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-plus me-2"></i>
                        <span id="modalTitle">Novo Evento</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="eventoForm" enctype="multipart/form-data">
                        <input type="hidden" id="eventoId" name="id">
                        <input type="hidden" name="acao" value="salvar_evento">
                        
                        <!-- Navegação por Abas -->
                        <ul class="nav nav-tabs mb-4" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dadosBasicos">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Dados Básicos
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#detalhesEvento">
                                    <i class="fas fa-cogs me-1"></i>
                                    Detalhes
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#imagensEvento">
                                    <i class="fas fa-images me-1"></i>
                                    Imagens
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#seoEvento">
                                    <i class="fas fa-search me-1"></i>
                                    SEO
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Dados Básicos -->
                            <div class="tab-pane fade show active" id="dadosBasicos">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Nome do Evento *</label>
                                        <input type="text" name="nome" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Categoria *</label>
                                        <select name="categoria_id" class="form-select" required>
                                            <option value="">Selecionar...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Data de Início *</label>
                                        <input type="date" name="data_inicio" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Hora de Início *</label>
                                        <input type="time" name="hora_inicio" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Data de Término *</label>
                                        <input type="date" name="data_termino" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Hora de Término *</label>
                                        <input type="time" name="hora_termino" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Local do Evento *</label>
                                        <div id="geocoderContainer"></div>
                                        <input type="hidden" name="lat" id="eventLat">
                                        <input type="hidden" name="lng" id="eventLng">
                                        <input type="text" name="local" id="eventLocal" class="form-control mt-2" readonly required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Atrações *</label>
                                        <textarea name="atracoes" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Descrição do Evento *</label>
                                        <textarea name="descricao_evento" class="form-control" rows="4" required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Tags (separadas por vírgula)</label>
                                        <input type="text" name="tags" class="form-control" 
                                               placeholder="rock, show, festival, música">
                                        <div class="form-text">Exemplo: rock, show, festival, música</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detalhes -->
                            <div class="tab-pane fade" id="detalhesEvento">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Faixa Etária</label>
                                        <select name="faixa_etaria" class="form-select">
                                            <option value="livre">Livre</option>
                                            <option value="10">10 anos</option>
                                            <option value="12">12 anos</option>
                                            <option value="14">14 anos</option>
                                            <option value="16">16 anos</option>
                                            <option value="18">18 anos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Capacidade Máxima</label>
                                        <input type="number" name="capacidade_maxima" class="form-control" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tipo de Evento</label>
                                        <select name="tipo_evento" class="form-select">
                                            <option value="presencial">Presencial</option>
                                            <option value="virtual">Virtual</option>
                                            <option value="hibrido">Híbrido</option>
                                        </select>
                                    </div>
                                    <div class="col-12 d-none" id="linkTransmissaoGroup">
                                        <label class="form-label">Link da Transmissão</label>
                                        <input type="url" name="link_transmissao" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Código de Vestimenta</label>
                                        <input type="text" name="codigo_vestimenta" class="form-control" 
                                               placeholder="Ex: Casual, Esporte Fino, etc.">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mt-4">
                                            <input class="form-check-input" type="checkbox" name="destaque" id="destaqueSwitch">
                                            <label class="form-check-label" for="destaqueSwitch">
                                                Evento em Destaque
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Acessibilidade</label>
                                        <textarea name="acessibilidade" class="form-control" rows="2" 
                                                  placeholder="Descreva os recursos de acessibilidade disponíveis..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Informações sobre Estacionamento</label>
                                        <textarea name="estacionamento" class="form-control" rows="2" 
                                                  placeholder="Informações sobre estacionamento disponível..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Política de Cancelamento</label>
                                        <textarea name="politica_cancelamento" class="form-control" rows="3" 
                                                  placeholder="Política de cancelamento e reembolso..."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Observações Importantes</label>
                                        <textarea name="observacoes_importantes" class="form-control" rows="2" 
                                                  placeholder="Informações importantes para os participantes..."></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Imagens -->
                            <div class="tab-pane fade" id="imagensEvento">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Imagem Principal</label>
                                        <input type="file" name="logo" class="form-control" accept="image/*">
                                        <div class="form-text">Formato recomendado: 1200x630px, máximo 5MB</div>
                                        <div id="logoPreview" class="mt-3"></div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Galeria de Imagens</label>
                                        <input type="file" name="galeria[]" class="form-control" accept="image/*" multiple>
                                        <div class="form-text">Selecione múltiplas imagens para a galeria do evento</div>
                                        <div id="galeriaPreview" class="mt-3 row g-2"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SEO -->
                            <div class="tab-pane fade" id="seoEvento">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">URL Amigável (Slug)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">/evento/</span>
                                            <input type="text" name="slug" class="form-control" 
                                                   placeholder="nome-do-evento">
                                        </div>
                                        <div class="form-text">Deixe em branco para gerar automaticamente</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Título SEO</label>
                                        <input type="text" name="meta_title" class="form-control" 
                                               maxlength="200" placeholder="Título para mecanismos de busca">
                                        <div class="form-text">Máximo 200 caracteres. Deixe em branco para usar o nome do evento.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Descrição SEO</label>
                                        <textarea name="meta_description" class="form-control" rows="3" 
                                                  maxlength="300" placeholder="Descrição para mecanismos de busca"></textarea>
                                        <div class="form-text">Máximo 300 caracteres</div>
                                    </div>
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
                    <button type="submit" form="eventoForm" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        <span id="btnSalvarText">Salvar Evento</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js"></script>
    <script src="js/eventos-modern.js"></script>
</body>
</html>