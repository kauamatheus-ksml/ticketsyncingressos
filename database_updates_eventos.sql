-- ===================================================
-- ATUALIZAÇÕES DO BANCO DE DADOS PARA EVENTOS.PHP
-- Sistema TicketSync - Funcionalidades Modernas
-- ===================================================

-- 1. Criar tabela de categorias de eventos
CREATE TABLE IF NOT EXISTS categorias_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    icone VARCHAR(50), -- classe de ícone (ex: fas fa-music)
    cor VARCHAR(7), -- cor hex (ex: #FF5722)
    ativo TINYINT(1) DEFAULT 1,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Criar tabela de tags de eventos
CREATE TABLE IF NOT EXISTS tags_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    cor VARCHAR(7), -- cor hex
    uso_count INT DEFAULT 0, -- contador de uso
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Criar tabela de relacionamento evento-tags (muitos para muitos)
CREATE TABLE IF NOT EXISTS evento_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags_eventos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evento_tag (evento_id, tag_id)
);

-- 4. Criar tabela de galeria de imagens dos eventos
CREATE TABLE IF NOT EXISTS evento_galeria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    imagem_path VARCHAR(255) NOT NULL,
    titulo VARCHAR(100),
    descricao TEXT,
    eh_principal TINYINT(1) DEFAULT 0, -- imagem principal/banner
    ordem INT DEFAULT 0,
    tamanho_arquivo INT, -- em bytes
    dimensoes VARCHAR(20), -- ex: 1920x1080
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
);

-- 5. Criar tabela de favoritos dos usuários
CREATE TABLE IF NOT EXISTS usuario_favoritos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    evento_id INT NOT NULL,
    tipo_usuario ENUM('cliente', 'admin', 'funcionario') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id, tipo_usuario),
    INDEX idx_evento (evento_id),
    UNIQUE KEY unique_favorito (usuario_id, evento_id, tipo_usuario)
);

-- 6. Criar tabela de avaliações dos eventos
CREATE TABLE IF NOT EXISTS evento_avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_usuario ENUM('cliente', 'admin', 'funcionario') NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comentario TEXT,
    aprovado TINYINT(1) DEFAULT 0, -- moderação
    data_evento DATE, -- data do evento que participou
    anonimo TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_avaliacao (evento_id, usuario_id, tipo_usuario)
);

-- 7. Criar tabela de visualizações/analytics dos eventos
CREATE TABLE IF NOT EXISTS evento_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    tipo_acao ENUM('view', 'click', 'share', 'favorite', 'unfavorite', 'comment') NOT NULL,
    usuario_id INT,
    tipo_usuario ENUM('cliente', 'admin', 'funcionario', 'visitante'),
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer TEXT,
    dados_extras JSON, -- dados adicionais em JSON
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    INDEX idx_evento_tipo (evento_id, tipo_acao),
    INDEX idx_data (created_at),
    INDEX idx_usuario (usuario_id, tipo_usuario)
);

-- 8. Criar tabela de notificações de eventos
CREATE TABLE IF NOT EXISTS evento_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_usuario ENUM('cliente', 'admin', 'funcionario') NOT NULL,
    tipo_notificacao ENUM('lembrete', 'mudanca', 'cancelamento', 'nova_data') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    enviado TINYINT(1) DEFAULT 0,
    lido TINYINT(1) DEFAULT 0,
    data_envio TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    INDEX idx_usuario_tipo (usuario_id, tipo_usuario, lido),
    INDEX idx_envio (enviado, data_envio)
);

-- 9. Adicionar novas colunas à tabela eventos existente
ALTER TABLE eventos 
ADD COLUMN IF NOT EXISTS categoria_id INT,
ADD COLUMN IF NOT EXISTS faixa_etaria ENUM('livre', '10', '12', '14', '16', '18') DEFAULT 'livre',
ADD COLUMN IF NOT EXISTS acessibilidade TEXT, -- detalhes de acessibilidade
ADD COLUMN IF NOT EXISTS politica_cancelamento TEXT,
ADD COLUMN IF NOT EXISTS codigo_vestimenta VARCHAR(100),
ADD COLUMN IF NOT EXISTS estacionamento TEXT,
ADD COLUMN IF NOT EXISTS observacoes_importantes TEXT,
ADD COLUMN IF NOT EXISTS capacidade_maxima INT,
ADD COLUMN IF NOT EXISTS tipo_evento ENUM('presencial', 'virtual', 'hibrido') DEFAULT 'presencial',
ADD COLUMN IF NOT EXISTS link_transmissao VARCHAR(255), -- para eventos virtuais
ADD COLUMN IF NOT EXISTS permite_gravacao TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS destaque TINYINT(1) DEFAULT 0, -- evento em destaque
ADD COLUMN IF NOT EXISTS meta_title VARCHAR(200), -- SEO
ADD COLUMN IF NOT EXISTS meta_description VARCHAR(300), -- SEO
ADD COLUMN IF NOT EXISTS slug VARCHAR(200), -- URL amigável
ADD COLUMN IF NOT EXISTS visualizacoes INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS compartilhamentos INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS rating_medio DECIMAL(2,1) DEFAULT 0.0,
ADD COLUMN IF NOT EXISTS total_avaliacoes INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 10. Adicionar foreign key para categoria
ALTER TABLE eventos 
ADD CONSTRAINT fk_evento_categoria 
FOREIGN KEY (categoria_id) REFERENCES categorias_eventos(id) ON DELETE SET NULL;

-- 11. Adicionar índices para performance
ALTER TABLE eventos
ADD INDEX IF NOT EXISTS idx_categoria (categoria_id),
ADD INDEX IF NOT EXISTS idx_destaque (destaque),
ADD INDEX IF NOT EXISTS idx_tipo_evento (tipo_evento),
ADD INDEX IF NOT EXISTS idx_data_inicio (data_inicio),
ADD INDEX IF NOT EXISTS idx_status_data (status, data_inicio),
ADD INDEX IF NOT EXISTS idx_slug (slug),
ADD INDEX IF NOT EXISTS idx_visualizacoes (visualizacoes DESC),
ADD INDEX IF NOT EXISTS idx_rating (rating_medio DESC);

-- 12. Inserir categorias padrão
INSERT IGNORE INTO categorias_eventos (nome, descricao, icone, cor, ordem) VALUES
('Música', 'Shows, festivais e eventos musicais', 'fas fa-music', '#FF5722', 1),
('Teatro', 'Peças teatrais e espetáculos', 'fas fa-theater-masks', '#9C27B0', 2),
('Esportes', 'Jogos e competições esportivas', 'fas fa-futbol', '#4CAF50', 3),
('Gastronomia', 'Festivais gastronômicos e degustações', 'fas fa-utensils', '#FF9800', 4),
('Tecnologia', 'Conferências e meetups de tech', 'fas fa-laptop-code', '#2196F3', 5),
('Arte', 'Exposições e eventos artísticos', 'fas fa-palette', '#E91E63', 6),
('Educação', 'Workshops e palestras educativas', 'fas fa-graduation-cap', '#607D8B', 7),
('Negócios', 'Networking e eventos corporativos', 'fas fa-briefcase', '#795548', 8),
('Família', 'Eventos para toda família', 'fas fa-heart', '#F44336', 9),
('Outros', 'Diversos tipos de eventos', 'fas fa-calendar-alt', '#9E9E9E', 10);

-- 13. Inserir tags padrão
INSERT IGNORE INTO tags_eventos (nome, cor) VALUES
('Rock', '#F44336'),
('Pop', '#E91E63'),
('Jazz', '#673AB7'),
('Eletrônica', '#3F51B5'),
('MPB', '#009688'),
('Sertanejo', '#4CAF50'),
('Festival', '#FF9800'),
('Show', '#FF5722'),
('Comedy', '#FFEB3B'),
('Stand-up', '#FFC107'),
('Infantil', '#8BC34A'),
('Gratuito', '#2196F3'),
('Premium', '#9C27B0'),
('VIP', '#FF9800'),
('Ao ar livre', '#4CAF50'),
('Indoor', '#607D8B');

-- 14. Criar view para estatísticas dos eventos
CREATE OR REPLACE VIEW evento_stats AS
SELECT 
    e.id,
    e.nome,
    e.visualizacoes,
    e.compartilhamentos,
    e.rating_medio,
    e.total_avaliacoes,
    COUNT(DISTINCT uf.id) as total_favoritos,
    COUNT(DISTINCT ea.id) as total_acoes,
    COALESCE(SUM(CASE WHEN ea.tipo_acao = 'view' THEN 1 ELSE 0 END), 0) as views_analytics,
    COALESCE(SUM(CASE WHEN ea.tipo_acao = 'click' THEN 1 ELSE 0 END), 0) as clicks,
    COALESCE(SUM(CASE WHEN ea.tipo_acao = 'share' THEN 1 ELSE 0 END), 0) as shares_analytics
FROM eventos e
LEFT JOIN usuario_favoritos uf ON e.id = uf.evento_id
LEFT JOIN evento_analytics ea ON e.id = ea.evento_id
GROUP BY e.id, e.nome, e.visualizacoes, e.compartilhamentos, e.rating_medio, e.total_avaliacoes;

-- 15. Criar triggers para atualizar contadores
DELIMITER //

CREATE TRIGGER IF NOT EXISTS update_rating_after_avaliacao
AFTER INSERT ON evento_avaliacoes
FOR EACH ROW
BEGIN
    UPDATE eventos 
    SET 
        rating_medio = (
            SELECT AVG(rating) 
            FROM evento_avaliacoes 
            WHERE evento_id = NEW.evento_id AND aprovado = 1
        ),
        total_avaliacoes = (
            SELECT COUNT(*) 
            FROM evento_avaliacoes 
            WHERE evento_id = NEW.evento_id AND aprovado = 1
        )
    WHERE id = NEW.evento_id;
END//

CREATE TRIGGER IF NOT EXISTS update_rating_after_update_avaliacao
AFTER UPDATE ON evento_avaliacoes
FOR EACH ROW
BEGIN
    UPDATE eventos 
    SET 
        rating_medio = (
            SELECT AVG(rating) 
            FROM evento_avaliacoes 
            WHERE evento_id = NEW.evento_id AND aprovado = 1
        ),
        total_avaliacoes = (
            SELECT COUNT(*) 
            FROM evento_avaliacoes 
            WHERE evento_id = NEW.evento_id AND aprovado = 1
        )
    WHERE id = NEW.evento_id;
END//

CREATE TRIGGER IF NOT EXISTS update_rating_after_delete_avaliacao
AFTER DELETE ON evento_avaliacoes
FOR EACH ROW
BEGIN
    UPDATE eventos 
    SET 
        rating_medio = COALESCE((
            SELECT AVG(rating) 
            FROM evento_avaliacoes 
            WHERE evento_id = OLD.evento_id AND aprovado = 1
        ), 0),
        total_avaliacoes = (
            SELECT COUNT(*) 
            FROM evento_avaliacoes 
            WHERE evento_id = OLD.evento_id AND aprovado = 1
        )
    WHERE id = OLD.evento_id;
END//

DELIMITER ;

-- 16. Criar função para gerar slug
DELIMITER //

CREATE FUNCTION IF NOT EXISTS generate_slug(input_text VARCHAR(200)) 
RETURNS VARCHAR(200)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE slug VARCHAR(200);
    DECLARE clean_text VARCHAR(200);
    
    -- Remove acentos e caracteres especiais
    SET clean_text = LOWER(input_text);
    SET clean_text = REPLACE(clean_text, 'ã', 'a');
    SET clean_text = REPLACE(clean_text, 'á', 'a');
    SET clean_text = REPLACE(clean_text, 'à', 'a');
    SET clean_text = REPLACE(clean_text, 'â', 'a');
    SET clean_text = REPLACE(clean_text, 'é', 'e');
    SET clean_text = REPLACE(clean_text, 'ê', 'e');
    SET clean_text = REPLACE(clean_text, 'í', 'i');
    SET clean_text = REPLACE(clean_text, 'ó', 'o');
    SET clean_text = REPLACE(clean_text, 'ô', 'o');
    SET clean_text = REPLACE(clean_text, 'õ', 'o');
    SET clean_text = REPLACE(clean_text, 'ú', 'u');
    SET clean_text = REPLACE(clean_text, 'ç', 'c');
    
    -- Remove caracteres especiais e substitui espaços por hífen
    SET clean_text = REGEXP_REPLACE(clean_text, '[^a-z0-9\\s]', '');
    SET clean_text = REGEXP_REPLACE(clean_text, '\\s+', '-');
    SET clean_text = TRIM(BOTH '-' FROM clean_text);
    
    RETURN clean_text;
END//

DELIMITER ;

-- 17. Atualizar eventos existentes com slugs
UPDATE eventos 
SET slug = generate_slug(nome) 
WHERE slug IS NULL OR slug = '';

-- 18. Criar índice único para slug (depois de preencher os existentes)
ALTER TABLE eventos 
ADD UNIQUE INDEX IF NOT EXISTS idx_slug_unique (slug);

-- ===================================================
-- FIM DAS ATUALIZAÇÕES DO BANCO DE DADOS
-- ===================================================

-- OBSERVAÇÕES:
-- 1. Execute este script em ambiente de desenvolvimento primeiro
-- 2. Faça backup do banco antes de executar em produção
-- 3. Alguns comandos podem falhar se as tabelas/colunas já existirem
-- 4. Ajuste as constraints conforme necessário para seu ambiente
-- 5. Configure as chaves de API do Mapbox no código PHP