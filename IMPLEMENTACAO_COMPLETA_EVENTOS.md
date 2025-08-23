# 🎯 GUIA COMPLETO DE IMPLEMENTAÇÃO - SISTEMA MODERNO DE EVENTOS

## 📋 RESUMO EXECUTIVO

O sistema de eventos foi **completamente reimaginado** com funcionalidades de nível profissional. Este documento detalha **TUDO** que foi feito e **TUDO** que ainda precisa ser implementado para 100% de funcionalidade.

---

## 📁 **ARQUIVOS CRIADOS/MODIFICADOS**

### ✅ **ARQUIVOS JÁ CRIADOS:**
1. **`database_updates_eventos.sql`** - Todas as modificações do banco
2. **`eventos.php`** - Nova página principal (backup: `eventos_backup.php`)
3. **`css/eventos-modern.css`** - Estilos modernos
4. **`js/eventos-modern.js`** - JavaScript completo
5. **`FUNCIONALIDADES_EVENTOS.md`** - Documentação das funcionalidades

---

## 🗄️ **IMPLEMENTAÇÃO DO BANCO DE DADOS**

### ✅ **EXECUTAR O SQL (OBRIGATÓRIO):**
```sql
-- Execute TODO o arquivo: database_updates_eventos.sql
-- Isso criará:
```

#### **NOVAS TABELAS CRIADAS:**
1. **`categorias_eventos`** - Sistema de categorização
2. **`tags_eventos`** - Tags flexíveis
3. **`evento_tags`** - Relacionamento evento-tags
4. **`evento_galeria`** - Galeria de imagens
5. **`usuario_favoritos`** - Sistema de favoritos
6. **`evento_avaliacoes`** - Sistema de avaliações e comentários
7. **`evento_analytics`** - Analytics detalhado
8. **`evento_notificacoes`** - Sistema de notificações

#### **NOVAS COLUNAS NA TABELA `eventos`:**
```sql
ALTER TABLE eventos ADD COLUMN:
- categoria_id INT
- faixa_etaria ENUM('livre', '10', '12', '14', '16', '18')
- acessibilidade TEXT
- politica_cancelamento TEXT
- codigo_vestimenta VARCHAR(100)
- estacionamento TEXT
- observacoes_importantes TEXT
- capacidade_maxima INT
- tipo_evento ENUM('presencial', 'virtual', 'hibrido')
- link_transmissao VARCHAR(255)
- destaque TINYINT(1)
- meta_title VARCHAR(200)
- meta_description VARCHAR(300)
- slug VARCHAR(200)
- visualizacoes INT
- compartilhamentos INT
- rating_medio DECIMAL(2,1)
- total_avaliacoes INT
- created_at TIMESTAMP
- updated_at TIMESTAMP
```

---

## 🚨 **FUNCIONALIDADES QUE PRECISAM SER IMPLEMENTADAS/COMPLETADAS**

### 1. **SISTEMA DE EDIÇÃO DE EVENTOS**

#### ❌ **FALTANDO: Função editEvento() no JavaScript**
**Arquivo:** `js/eventos-modern.js`
**Linha:** 713

**IMPLEMENTAR:**
```javascript
async function editEvento(id) {
    try {
        // Carregar dados do evento
        const response = await fetch(`eventos.php?action=get_evento&id=${id}`);
        const evento = await response.json();
        
        if (evento) {
            // Preencher formulário com dados existentes
            document.getElementById('eventoId').value = evento.id;
            document.querySelector('input[name="nome"]').value = evento.nome;
            document.querySelector('select[name="categoria_id"]').value = evento.categoria_id;
            document.querySelector('input[name="data_inicio"]').value = evento.data_inicio;
            document.querySelector('input[name="hora_inicio"]').value = evento.hora_inicio;
            document.querySelector('input[name="data_termino"]').value = evento.data_termino;
            document.querySelector('input[name="hora_termino"]').value = evento.hora_termino;
            document.getElementById('eventLocal').value = evento.local;
            document.getElementById('eventLat').value = evento.lat;
            document.getElementById('eventLng').value = evento.lng;
            document.querySelector('textarea[name="atracoes"]').value = evento.atracoes;
            document.querySelector('textarea[name="descricao_evento"]').value = evento.descricao_evento;
            document.querySelector('input[name="tags"]').value = evento.tags;
            document.querySelector('select[name="faixa_etaria"]').value = evento.faixa_etaria;
            document.querySelector('input[name="capacidade_maxima"]').value = evento.capacidade_maxima;
            document.querySelector('select[name="tipo_evento"]').value = evento.tipo_evento;
            document.querySelector('input[name="link_transmissao"]').value = evento.link_transmissao;
            document.querySelector('input[name="codigo_vestimenta"]').value = evento.codigo_vestimenta;
            document.querySelector('textarea[name="acessibilidade"]').value = evento.acessibilidade;
            document.querySelector('textarea[name="estacionamento"]').value = evento.estacionamento;
            document.querySelector('textarea[name="politica_cancelamento"]').value = evento.politica_cancelamento;
            document.querySelector('textarea[name="observacoes_importantes"]').value = evento.observacoes_importantes;
            document.querySelector('input[name="destaque"]').checked = evento.destaque;
            document.querySelector('input[name="slug"]').value = evento.slug;
            document.querySelector('input[name="meta_title"]').value = evento.meta_title;
            document.querySelector('textarea[name="meta_description"]').value = evento.meta_description;
            
            // Atualizar títulos do modal
            document.getElementById('modalTitle').textContent = 'Editar Evento';
            document.getElementById('btnSalvarText').textContent = 'Atualizar Evento';
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('eventoModal'));
            modal.show();
        }
    } catch (error) {
        console.error('Erro ao carregar evento:', error);
        Swal.fire('Erro', 'Erro ao carregar dados do evento', 'error');
    }
}
```

#### ❌ **FALTANDO: Endpoint get_evento no PHP**
**Arquivo:** `eventos.php`
**Adicionar após linha 351:**

```php
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
```

### 2. **SISTEMA DE EXCLUSÃO DE EVENTOS**

#### ❌ **FALTANDO: Função deleteEvento() no JavaScript**
**Arquivo:** `js/eventos-modern.js`
**Linha:** 717

**IMPLEMENTAR:**
```javascript
async function deleteEvento(id) {
    const result = await Swal.fire({
        title: 'Tem certeza?',
        text: 'Esta ação não pode ser desfeita!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e53e3e',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar'
    });
    
    if (result.isConfirmed) {
        try {
            const response = await fetch(`eventos.php?action=delete_evento&id=${id}`, {
                method: 'DELETE'
            });
            const result = await response.json();
            
            if (result.success) {
                Swal.fire('Sucesso!', 'Evento excluído com sucesso!', 'success');
                // Recarregar lista
                window.eventosManager.searchEventos();
                window.eventosManager.loadStats();
            } else {
                Swal.fire('Erro!', result.message || 'Erro ao excluir evento', 'error');
            }
        } catch (error) {
            console.error('Erro ao excluir evento:', error);
            Swal.fire('Erro!', 'Erro interno. Tente novamente.', 'error');
        }
    }
}
```

#### ❌ **FALTANDO: Endpoint delete_evento no PHP**
**Arquivo:** `eventos.php`
**Adicionar após o endpoint get_evento:**

```php
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
```

### 3. **SISTEMA DE COMPARTILHAMENTO**

#### ❌ **FALTANDO: Funcionalidade de compartilhamento**

**CRIAR ARQUIVO:** `js/compartilhamento.js`
```javascript
class CompartilhamentoManager {
    static async compartilhar(eventoId, tipo = 'link') {
        try {
            // Registrar compartilhamento
            await fetch(`eventos.php?action=registrar_compartilhamento&evento_id=${eventoId}&tipo=${tipo}`);
            
            const evento = await this.getEventoData(eventoId);
            const url = `${window.location.origin}/evento/${evento.slug}`;
            
            switch (tipo) {
                case 'facebook':
                    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`);
                    break;
                case 'twitter':
                    const texto = `Confira este evento: ${evento.nome}`;
                    window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(texto)}&url=${encodeURIComponent(url)}`);
                    break;
                case 'whatsapp':
                    const mensagem = `*${evento.nome}*\n\n${evento.descricao_evento.substring(0, 100)}...\n\n${url}`;
                    window.open(`https://wa.me/?text=${encodeURIComponent(mensagem)}`);
                    break;
                case 'link':
                default:
                    await navigator.clipboard.writeText(url);
                    Swal.fire('Sucesso!', 'Link copiado para a área de transferência!', 'success');
                    break;
            }
        } catch (error) {
            console.error('Erro ao compartilhar:', error);
            Swal.fire('Erro!', 'Erro ao compartilhar evento', 'error');
        }
    }
    
    static async getEventoData(eventoId) {
        const response = await fetch(`eventos.php?action=get_evento&id=${eventoId}`);
        return await response.json();
    }
}
```

**ADICIONAR NO PHP:** Endpoint registrar_compartilhamento
```php
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
```

### 4. **SISTEMA DE NOTIFICAÇÕES**

#### ❌ **FALTANDO: Sistema completo de notificações**

**CRIAR ARQUIVO:** `notificacoes.php`
```php
<?php
class NotificacaoManager {
    private $conn;
    
    public function __construct($conexao) {
        $this->conn = $conexao;
    }
    
    public function criarNotificacao($eventoId, $usuarioId, $tipoUsuario, $tipoNotificacao, $titulo, $mensagem) {
        $stmt = $this->conn->prepare("
            INSERT INTO evento_notificacoes 
            (evento_id, usuario_id, tipo_usuario, tipo_notificacao, titulo, mensagem) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iissss", $eventoId, $usuarioId, $tipoUsuario, $tipoNotificacao, $titulo, $mensagem);
        return $stmt->execute();
    }
    
    public function notificarMudancaEvento($eventoId, $mudancas) {
        // Buscar todos os usuários que favoritaram o evento
        $stmt = $this->conn->prepare("
            SELECT usuario_id, tipo_usuario FROM usuario_favoritos WHERE evento_id = ?
        ");
        $stmt->bind_param("i", $eventoId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $titulo = "Evento Atualizado";
            $mensagem = "O evento que você favoritou foi atualizado: " . implode(', ', $mudancas);
            
            $this->criarNotificacao(
                $eventoId, 
                $row['usuario_id'], 
                $row['tipo_usuario'], 
                'mudanca', 
                $titulo, 
                $mensagem
            );
        }
    }
    
    public function obterNotificacoes($usuarioId, $tipoUsuario, $naoLidas = false) {
        $whereClause = $naoLidas ? "AND lido = 0" : "";
        
        $stmt = $this->conn->prepare("
            SELECT n.*, e.nome as evento_nome 
            FROM evento_notificacoes n
            JOIN eventos e ON n.evento_id = e.id
            WHERE n.usuario_id = ? AND n.tipo_usuario = ? $whereClause
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->bind_param("is", $usuarioId, $tipoUsuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
```

### 5. **PÁGINAS PÚBLICAS DOS EVENTOS**

#### ❌ **FALTANDO: Página pública do evento**

**CRIAR ARQUIVO:** `evento.php` (página pública)
```php
<?php
// Página pública de visualização do evento
include('conexao.php');

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: index.php");
    exit();
}

// Buscar evento pelo slug
$stmt = $conn->prepare("
    SELECT e.*, c.nome as categoria_nome, c.cor as categoria_cor, c.icone as categoria_icone,
           (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM evento_tags et 
            JOIN tags_eventos t ON et.tag_id = t.id WHERE et.evento_id = e.id) as tags
    FROM eventos e
    LEFT JOIN categorias_eventos c ON e.categoria_id = c.id
    WHERE e.slug = ? AND e.status = 'aprovado'
");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("HTTP/1.0 404 Not Found");
    include('404.php');
    exit();
}

$evento = $result->fetch_assoc();

// Incrementar visualizações
$stmt = $conn->prepare("UPDATE eventos SET visualizacoes = visualizacoes + 1 WHERE id = ?");
$stmt->bind_param("i", $evento['id']);
$stmt->execute();

// Buscar galeria
$stmt = $conn->prepare("SELECT * FROM evento_galeria WHERE evento_id = ? ORDER BY ordem ASC");
$stmt->bind_param("i", $evento['id']);
$stmt->execute();
$galeria = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar avaliações
$stmt = $conn->prepare("
    SELECT ea.*, 
           CASE 
               WHEN ea.tipo_usuario = 'cliente' THEN c.nome
               WHEN ea.tipo_usuario = 'admin' THEN a.nome
           END as usuario_nome
    FROM evento_avaliacoes ea
    LEFT JOIN clientes c ON ea.usuario_id = c.id AND ea.tipo_usuario = 'cliente'
    LEFT JOIN administradores a ON ea.usuario_id = a.id AND ea.tipo_usuario = 'admin'
    WHERE ea.evento_id = ? AND ea.aprovado = 1
    ORDER BY ea.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $evento['id']);
$stmt->execute();
$avaliacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($evento['meta_title'] ?: $evento['nome']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($evento['meta_description']); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($evento['nome']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($evento['meta_description']); ?>">
    <meta property="og:image" content="<?php echo $evento['logo'] ? $evento['logo'] : 'default-event.jpg'; ?>">
    <meta property="og:url" content="<?php echo $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="event">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/evento-publico.css">
</head>
<body>
    <!-- Conteúdo da página pública do evento -->
    <div class="container">
        <div class="evento-header">
            <h1><?php echo htmlspecialchars($evento['nome']); ?></h1>
            <!-- Resto do conteúdo... -->
        </div>
    </div>
</body>
</html>
```

### 6. **SISTEMA DE .HTACCESS PARA URLs AMIGÁVEIS**

#### ❌ **FALTANDO: Configuração do .htaccess**

**CRIAR ARQUIVO:** `.htaccess`
```apache
RewriteEngine On

# URL amigável para eventos: /evento/slug-do-evento
RewriteRule ^evento/([a-z0-9\-]+)/?$ evento.php?slug=$1 [L,QSA]

# URL amigável para categoria: /categoria/nome-categoria
RewriteRule ^categoria/([a-z0-9\-]+)/?$ eventos-publicos.php?categoria=$1 [L,QSA]

# Outras regras...
```

### 7. **SISTEMA DE BUSCA PÚBLICA**

#### ❌ **FALTANDO: Página de busca pública**

**CRIAR ARQUIVO:** `eventos-publicos.php`
```php
<?php
// Página pública de listagem de eventos
// Similar ao eventos.php mas para visualização pública
?>
```

### 8. **SISTEMA DE API REST**

#### ❌ **FALTANDO: API completa**

**CRIAR ARQUIVO:** `api/eventos.php`
```php
<?php
// API REST para eventos
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Implementar endpoints REST completos
?>
```

---

## 🔧 **CONFIGURAÇÕES NECESSÁRIAS**

### 1. **TOKEN DO MAPBOX**
**Arquivo:** `js/eventos-modern.js`
**Linha:** 793
```javascript
// SUBSTITUIR pelo seu token real:
mapboxgl.accessToken = 'SUA_CHAVE_MAPBOX_AQUI';
```

### 2. **CONFIGURAÇÕES DE UPLOAD**
**Arquivo:** `php.ini` ou `.htaccess`
```ini
upload_max_filesize = 5M
post_max_size = 10M
max_file_uploads = 20
```

### 3. **PERMISSÕES DE DIRETÓRIO**
```bash
chmod 755 uploads/
chmod 755 uploads/eventos/
```

---

## 🧪 **TESTES NECESSÁRIOS**

### ✅ **TESTES A REALIZAR:**

1. **Banco de Dados:**
   - [ ] Executar SQL completo
   - [ ] Verificar todas as tabelas criadas
   - [ ] Testar triggers de rating
   - [ ] Verificar foreign keys

2. **Interface:**
   - [ ] Carregar página eventos.php
   - [ ] Testar busca em tempo real
   - [ ] Testar filtros por categoria
   - [ ] Alternar visualizações (Grid/Lista/Mapa)

3. **Formulário:**
   - [ ] Criar novo evento
   - [ ] Upload de imagens
   - [ ] Sistema de tags
   - [ ] Geocoding de endereços

4. **Funcionalidades:**
   - [ ] Sistema de favoritos
   - [ ] Edição de eventos
   - [ ] Exclusão de eventos
   - [ ] Analytics em tempo real

---

## 📝 **CHECKLIST DE IMPLEMENTAÇÃO**

### **FASE 1 - BANCO DE DADOS (OBRIGATÓRIO)**
- [ ] Executar `database_updates_eventos.sql` completo
- [ ] Verificar se todas as 8 tabelas foram criadas
- [ ] Confirmar que as colunas foram adicionadas à tabela `eventos`
- [ ] Testar triggers e functions

### **FASE 2 - FRONTEND BÁSICO**
- [ ] Acessar `eventos.php` e verificar carregamento
- [ ] Configurar token do Mapbox
- [ ] Testar criação de evento básico
- [ ] Verificar responsividade

### **FASE 3 - FUNCIONALIDADES CORE**
- [ ] Implementar `editEvento()` no JavaScript
- [ ] Implementar endpoint `get_evento` no PHP
- [ ] Implementar `deleteEvento()` no JavaScript
- [ ] Implementar endpoint `delete_evento` no PHP

### **FASE 4 - FUNCIONALIDADES AVANÇADAS**
- [ ] Sistema de compartilhamento
- [ ] Notificações
- [ ] Página pública dos eventos
- [ ] URLs amigáveis (.htaccess)

### **FASE 5 - OTIMIZAÇÕES**
- [ ] Cache de consultas
- [ ] Otimização de imagens
- [ ] Performance do JavaScript
- [ ] SEO completo

---

## 🚨 **AVISOS IMPORTANTES**

### **DEPENDÊNCIAS OBRIGATÓRIAS:**
1. **PHP 7.4+** com extensões: mysqli, gd, json
2. **MySQL 5.7+** ou **MariaDB 10.2+**
3. **Mapbox Token** (gratuito até 50k requisições/mês)
4. **Bootstrap 5.3** (carregado via CDN)
5. **Font Awesome 6.4** (carregado via CDN)

### **SEGURANÇA:**
- ✅ Prepared statements implementados
- ✅ Sanitização de inputs
- ✅ Validação de uploads
- ❌ **FALTANDO:** Rate limiting para API
- ❌ **FALTANDO:** CSRF tokens
- ❌ **FALTANDO:** Logs de segurança

### **PERFORMANCE:**
- ✅ Lazy loading de imagens
- ✅ Debounce na busca
- ✅ Paginação eficiente
- ❌ **FALTANDO:** Cache Redis/Memcached
- ❌ **FALTANDO:** CDN para imagens
- ❌ **FALTANDO:** Minificação de CSS/JS

---

## 🎯 **PRIORIDADES DE IMPLEMENTAÇÃO**

### **🔴 CRÍTICO (Fazer AGORA):**
1. Executar SQL do banco de dados
2. Configurar token do Mapbox
3. Implementar edição de eventos
4. Implementar exclusão de eventos

### **🟡 IMPORTANTE (Próximos dias):**
1. Sistema de compartilhamento
2. Página pública dos eventos
3. URLs amigáveis
4. Sistema de notificações

### **🟢 DESEJÁVEL (Futuro):**
1. API REST completa
2. Cache avançado
3. Analytics detalhado
4. App mobile

---

## 📞 **SUPORTE E DÚVIDAS**

Para qualquer problema na implementação:

1. **Verificar logs do PHP:** `error_log()`
2. **Console do navegador:** F12 > Console
3. **Network tab:** Verificar requisições AJAX
4. **Banco de dados:** Verificar se tabelas existem

---

**🎉 RESULTADO FINAL:**
Após implementar **TUDO** desta lista, o TicketSync terá um sistema de gestão de eventos **PROFISSIONAL** comparável aos melhores do mercado!

**💪 VAMOS IMPLEMENTAR JUNTOS!**