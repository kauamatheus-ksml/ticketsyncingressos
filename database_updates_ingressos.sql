-- =====================================================
-- TICKETSYNC - ATUALIZAÇÕES DO SISTEMA DE INGRESSOS
-- Sistema Moderno de Gestão de Ingressos Profissional
-- Versão: 2.0 - Implementação Completa
-- =====================================================

-- Configurações iniciais
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET foreign_key_checks = 0;

-- =====================================================
-- 1. SISTEMA DE CUPONS E DESCONTOS
-- =====================================================

CREATE TABLE IF NOT EXISTS `cupons_desconto` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL UNIQUE,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `tipo` ENUM('percentual', 'valor_fixo', 'frete_gratis') NOT NULL DEFAULT 'percentual',
  `valor` DECIMAL(10,2) NOT NULL,
  `valor_minimo_pedido` DECIMAL(10,2) DEFAULT 0.00,
  `uso_maximo` INT(11) DEFAULT NULL,
  `uso_por_cliente` INT(11) DEFAULT 1,
  `uso_atual` INT(11) DEFAULT 0,
  `data_inicio` DATETIME NOT NULL,
  `data_fim` DATETIME NOT NULL,
  `eventos_ids` TEXT DEFAULT NULL,
  `tipos_ingresso` TEXT DEFAULT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `promotor_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_datas` (`data_inicio`, `data_fim`),
  KEY `fk_cupom_promotor` (`promotor_id`),
  CONSTRAINT `fk_cupom_promotor` FOREIGN KEY (`promotor_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de uso dos cupons
CREATE TABLE IF NOT EXISTS `cupons_uso` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cupom_id` INT(11) NOT NULL,
  `pedido_id` INT(11) NOT NULL,
  `cliente_id` INT(11) NOT NULL,
  `desconto_aplicado` DECIMAL(10,2) NOT NULL,
  `usado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cupom_pedido` (`cupom_id`, `pedido_id`),
  KEY `fk_cupom_uso_pedido` (`pedido_id`),
  KEY `fk_cupom_uso_cliente` (`cliente_id`),
  CONSTRAINT `fk_cupom_uso_cupom` FOREIGN KEY (`cupom_id`) REFERENCES `cupons_desconto` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cupom_uso_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cupom_uso_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. SISTEMA DE TAXAS E COMISSÕES
-- =====================================================

CREATE TABLE IF NOT EXISTS `configuracoes_taxas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `tipo_taxa` ENUM('plataforma', 'pagamento', 'conveniencia', 'afiliado', 'saque') NOT NULL,
  `percentual` DECIMAL(5,2) DEFAULT 0.00,
  `valor_fixo` DECIMAL(10,2) DEFAULT 0.00,
  `valor_minimo` DECIMAL(10,2) DEFAULT 0.00,
  `valor_maximo` DECIMAL(10,2) DEFAULT NULL,
  `aplicar_sobre` ENUM('bruto', 'liquido') DEFAULT 'bruto',
  `ativo` TINYINT(1) DEFAULT 1,
  `promotor_id` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo_taxa` (`tipo_taxa`),
  KEY `fk_taxa_promotor` (`promotor_id`),
  CONSTRAINT `fk_taxa_promotor` FOREIGN KEY (`promotor_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir taxas padrão
INSERT INTO `configuracoes_taxas` (`nome`, `tipo_taxa`, `percentual`, `valor_fixo`, `ativo`) VALUES
('Taxa da Plataforma', 'plataforma', 5.00, 0.00, 1),
('Taxa de Conveniência', 'conveniencia', 0.00, 3.50, 1),
('Taxa PIX', 'pagamento', 1.50, 0.00, 1),
('Taxa Cartão Crédito', 'pagamento', 3.99, 0.39, 1),
('Taxa Cartão Débito', 'pagamento', 2.99, 0.39, 1);

-- =====================================================
-- 3. MELHORIAS NA TABELA DE INGRESSOS
-- =====================================================

-- Adicionar novas colunas à tabela ingressos
ALTER TABLE `ingressos` 
ADD COLUMN IF NOT EXISTS `nome` VARCHAR(100) DEFAULT NULL AFTER `tipo_ingresso`,
ADD COLUMN IF NOT EXISTS `descricao` TEXT DEFAULT NULL AFTER `nome`,
ADD COLUMN IF NOT EXISTS `data_inicio_vendas` DATETIME DEFAULT NULL AFTER `quantidade`,
ADD COLUMN IF NOT EXISTS `data_fim_vendas` DATETIME DEFAULT NULL AFTER `data_inicio_vendas`,
ADD COLUMN IF NOT EXISTS `quantidade_minima` INT(11) DEFAULT 1 AFTER `data_fim_vendas`,
ADD COLUMN IF NOT EXISTS `quantidade_maxima` INT(11) DEFAULT 10 AFTER `quantidade_minima`,
ADD COLUMN IF NOT EXISTS `meia_entrada` TINYINT(1) DEFAULT 0 AFTER `quantidade_maxima`,
ADD COLUMN IF NOT EXISTS `preco_meia` DECIMAL(10,2) DEFAULT NULL AFTER `meia_entrada`,
ADD COLUMN IF NOT EXISTS `cor` VARCHAR(7) DEFAULT '#4299e1' AFTER `preco_meia`,
ADD COLUMN IF NOT EXISTS `ordem` INT(11) DEFAULT 0 AFTER `cor`,
ADD COLUMN IF NOT EXISTS `vendas_count` INT(11) DEFAULT 0 AFTER `ordem`,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `vendas_count`,
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Adicionar índices para performance
ALTER TABLE `ingressos` 
ADD INDEX IF NOT EXISTS `idx_evento_id` (`evento_id`),
ADD INDEX IF NOT EXISTS `idx_promotor_id` (`promotor_id`),
ADD INDEX IF NOT EXISTS `idx_liberado` (`liberado`),
ADD INDEX IF NOT EXISTS `idx_datas_vendas` (`data_inicio_vendas`, `data_fim_vendas`),
ADD INDEX IF NOT EXISTS `idx_ordem` (`ordem`);

-- =====================================================
-- 4. SISTEMA DE LOTES DE INGRESSOS
-- =====================================================

CREATE TABLE IF NOT EXISTS `lotes_ingressos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `evento_id` INT(11) NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `ordem` INT(11) DEFAULT 0,
  `data_inicio` DATETIME NOT NULL,
  `data_fim` DATETIME NOT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_lote_evento` (`evento_id`),
  KEY `idx_datas_lote` (`data_inicio`, `data_fim`),
  KEY `idx_ordem_lote` (`ordem`),
  CONSTRAINT `fk_lote_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar referência do lote na tabela ingressos
ALTER TABLE `ingressos` 
ADD COLUMN IF NOT EXISTS `lote_id` INT(11) DEFAULT NULL AFTER `evento_id`,
ADD KEY IF NOT EXISTS `fk_ingresso_lote` (`lote_id`);

-- =====================================================
-- 5. SISTEMA DE AFILIADOS
-- =====================================================

CREATE TABLE IF NOT EXISTS `afiliados` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) DEFAULT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `codigo_afiliado` VARCHAR(20) NOT NULL UNIQUE,
  `percentual_comissao` DECIMAL(5,2) DEFAULT 5.00,
  `comissao_fixa` DECIMAL(10,2) DEFAULT 0.00,
  `vendas_total` DECIMAL(10,2) DEFAULT 0.00,
  `comissao_total` DECIMAL(10,2) DEFAULT 0.00,
  `comissao_paga` DECIMAL(10,2) DEFAULT 0.00,
  `banco` VARCHAR(100) DEFAULT NULL,
  `agencia` VARCHAR(10) DEFAULT NULL,
  `conta` VARCHAR(20) DEFAULT NULL,
  `pix_key` VARCHAR(100) DEFAULT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `aprovado` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo_afiliado` (`codigo_afiliado`),
  KEY `fk_afiliado_usuario` (`usuario_id`),
  KEY `idx_ativo_aprovado` (`ativo`, `aprovado`),
  CONSTRAINT `fk_afiliado_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de vendas por afiliado
CREATE TABLE IF NOT EXISTS `vendas_afiliados` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `afiliado_id` INT(11) NOT NULL,
  `pedido_id` INT(11) NOT NULL,
  `valor_venda` DECIMAL(10,2) NOT NULL,
  `percentual_comissao` DECIMAL(5,2) NOT NULL,
  `valor_comissao` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pendente', 'aprovada', 'paga', 'cancelada') DEFAULT 'pendente',
  `paga_em` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_afiliado_pedido` (`afiliado_id`, `pedido_id`),
  KEY `fk_venda_afiliado` (`afiliado_id`),
  KEY `fk_venda_pedido` (`pedido_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_venda_afiliado` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_venda_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar coluna de afiliado na tabela de pedidos
ALTER TABLE `pedidos` 
ADD COLUMN IF NOT EXISTS `afiliado_id` INT(11) DEFAULT NULL,
ADD KEY IF NOT EXISTS `fk_pedido_afiliado` (`afiliado_id`);

-- =====================================================
-- 6. MELHORIAS NO CHECK-IN E QR CODE
-- =====================================================

-- Adicionar colunas para QR Code e check-in
ALTER TABLE `ingressos_pedidos` 
ADD COLUMN IF NOT EXISTS `qr_code` TEXT DEFAULT NULL AFTER `ticket_code`,
ADD COLUMN IF NOT EXISTS `qr_code_data` TEXT DEFAULT NULL AFTER `qr_code`,
ADD COLUMN IF NOT EXISTS `check_in_realizado` TINYINT(1) DEFAULT 0 AFTER `validado`,
ADD COLUMN IF NOT EXISTS `data_check_in` DATETIME DEFAULT NULL AFTER `check_in_realizado`,
ADD COLUMN IF NOT EXISTS `porteiro_check_in` INT(11) DEFAULT NULL AFTER `data_check_in`,
ADD COLUMN IF NOT EXISTS `localizacao_check_in` VARCHAR(255) DEFAULT NULL AFTER `porteiro_check_in`,
ADD COLUMN IF NOT EXISTS `ip_check_in` VARCHAR(45) DEFAULT NULL AFTER `localizacao_check_in`,
ADD COLUMN IF NOT EXISTS `transferido_para` INT(11) DEFAULT NULL AFTER `ip_check_in`,
ADD COLUMN IF NOT EXISTS `data_transferencia` DATETIME DEFAULT NULL AFTER `transferido_para`,
ADD COLUMN IF NOT EXISTS `motivo_transferencia` TEXT DEFAULT NULL AFTER `data_transferencia`;

-- Índices para performance
ALTER TABLE `ingressos_pedidos`
ADD INDEX IF NOT EXISTS `idx_check_in` (`check_in_realizado`),
ADD INDEX IF NOT EXISTS `idx_transferido` (`transferido_para`),
ADD KEY IF NOT EXISTS `fk_porteiro_check_in` (`porteiro_check_in`);

-- =====================================================
-- 7. SISTEMA DE WAITLIST (LISTA DE ESPERA)
-- =====================================================

CREATE TABLE IF NOT EXISTS `waitlist` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `evento_id` INT(11) NOT NULL,
  `ingresso_id` INT(11) DEFAULT NULL,
  `cliente_id` INT(11) DEFAULT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `telefone` VARCHAR(20) DEFAULT NULL,
  `quantidade_desejada` INT(11) DEFAULT 1,
  `posicao` INT(11) NOT NULL,
  `notificado` TINYINT(1) DEFAULT 0,
  `data_notificacao` DATETIME DEFAULT NULL,
  `expirou` TINYINT(1) DEFAULT 0,
  `data_expiracao` DATETIME DEFAULT NULL,
  `status` ENUM('ativo', 'notificado', 'convertido', 'expirado', 'cancelado') DEFAULT 'ativo',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_waitlist_evento` (`evento_id`),
  KEY `fk_waitlist_ingresso` (`ingresso_id`),
  KEY `fk_waitlist_cliente` (`cliente_id`),
  KEY `idx_posicao` (`posicao`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`),
  CONSTRAINT `fk_waitlist_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_waitlist_ingresso` FOREIGN KEY (`ingresso_id`) REFERENCES `ingressos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_waitlist_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. RELATÓRIOS FINANCEIROS AVANÇADOS
-- =====================================================

CREATE TABLE IF NOT EXISTS `relatorios_financeiros` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `evento_id` INT(11) NOT NULL,
  `promotor_id` INT(11) NOT NULL,
  `data_relatorio` DATE NOT NULL,
  `periodo_inicio` DATE NOT NULL,
  `periodo_fim` DATE NOT NULL,
  `vendas_brutas` DECIMAL(10,2) DEFAULT 0.00,
  `taxas_plataforma` DECIMAL(10,2) DEFAULT 0.00,
  `taxas_pagamento` DECIMAL(10,2) DEFAULT 0.00,
  `taxas_conveniencia` DECIMAL(10,2) DEFAULT 0.00,
  `descontos_aplicados` DECIMAL(10,2) DEFAULT 0.00,
  `comissoes_afiliados` DECIMAL(10,2) DEFAULT 0.00,
  `estornos_cancelamentos` DECIMAL(10,2) DEFAULT 0.00,
  `vendas_liquidas` DECIMAL(10,2) DEFAULT 0.00,
  `total_ingressos_vendidos` INT(11) DEFAULT 0,
  `total_ingressos_validados` INT(11) DEFAULT 0,
  `taxa_conversao` DECIMAL(5,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evento_data` (`evento_id`, `data_relatorio`),
  KEY `fk_relatorio_evento` (`evento_id`),
  KEY `fk_relatorio_promotor` (`promotor_id`),
  KEY `idx_data_relatorio` (`data_relatorio`),
  KEY `idx_periodo` (`periodo_inicio`, `periodo_fim`),
  CONSTRAINT `fk_relatorio_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_relatorio_promotor` FOREIGN KEY (`promotor_id`) REFERENCES `administradores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. SISTEMA DE NOTIFICAÇÕES AVANÇADO
-- =====================================================

CREATE TABLE IF NOT EXISTS `notificacoes_push` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(11) NOT NULL,
  `tipo_usuario` ENUM('cliente', 'promotor', 'admin') NOT NULL DEFAULT 'cliente',
  `tipo_notificacao` ENUM('compra', 'lembrete', 'cancelamento', 'transferencia', 'waitlist', 'promocao', 'evento_alterado') NOT NULL,
  `titulo` VARCHAR(100) NOT NULL,
  `mensagem` TEXT NOT NULL,
  `dados_extras` JSON DEFAULT NULL,
  `canal` ENUM('push', 'email', 'sms', 'whatsapp') DEFAULT 'push',
  `programada_para` DATETIME DEFAULT NULL,
  `enviado` TINYINT(1) DEFAULT 0,
  `data_envio` DATETIME DEFAULT NULL,
  `lido` TINYINT(1) DEFAULT 0,
  `data_leitura` DATETIME DEFAULT NULL,
  `tentativas_envio` INT(11) DEFAULT 0,
  `erro_envio` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notificacao_usuario` (`usuario_id`),
  KEY `idx_tipo_notificacao` (`tipo_notificacao`),
  KEY `idx_enviado` (`enviado`),
  KEY `idx_lido` (`lido`),
  KEY `idx_programada` (`programada_para`),
  KEY `idx_canal` (`canal`),
  CONSTRAINT `fk_notificacao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. ANALYTICS E MÉTRICAS AVANÇADAS
-- =====================================================

CREATE TABLE IF NOT EXISTS `metricas_ingressos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `evento_id` INT(11) NOT NULL,
  `ingresso_id` INT(11) NOT NULL,
  `data_metrica` DATE NOT NULL,
  `visualizacoes` INT(11) DEFAULT 0,
  `tentativas_compra` INT(11) DEFAULT 0,
  `compras_finalizadas` INT(11) DEFAULT 0,
  `carrinho_abandonado` INT(11) DEFAULT 0,
  `taxa_conversao` DECIMAL(5,2) DEFAULT 0.00,
  `receita_gerada` DECIMAL(10,2) DEFAULT 0.00,
  `origem_trafego` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_metrica_data` (`evento_id`, `ingresso_id`, `data_metrica`),
  KEY `fk_metrica_evento` (`evento_id`),
  KEY `fk_metrica_ingresso` (`ingresso_id`),
  KEY `idx_data_metrica` (`data_metrica`),
  CONSTRAINT `fk_metrica_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_metrica_ingresso` FOREIGN KEY (`ingresso_id`) REFERENCES `ingressos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. SISTEMA DE CATEGORIAS PARA INGRESSOS
-- =====================================================

CREATE TABLE IF NOT EXISTS `categorias_ingressos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `icone` VARCHAR(50) DEFAULT 'fas fa-ticket-alt',
  `cor` VARCHAR(7) DEFAULT '#4299e1',
  `ordem` INT(11) DEFAULT 0,
  `ativo` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir categorias padrão
INSERT INTO `categorias_ingressos` (`nome`, `descricao`, `icone`, `cor`, `ordem`) VALUES
('Pista', 'Ingressos para área de pista', 'fas fa-users', '#4299e1', 1),
('Camarote', 'Ingressos para área VIP', 'fas fa-crown', '#f6ad55', 2),
('Cadeira', 'Ingressos numerados', 'fas fa-chair', '#68d391', 3),
('Mesa', 'Ingressos para mesas', 'fas fa-table', '#fc8181', 4),
('Backstage', 'Acesso aos bastidores', 'fas fa-eye', '#9f7aea', 5),
('Open Bar', 'Inclui bebidas', 'fas fa-cocktail', '#4fd1c7', 6);

-- Adicionar categoria aos ingressos
ALTER TABLE `ingressos` 
ADD COLUMN IF NOT EXISTS `categoria_id` INT(11) DEFAULT NULL AFTER `lote_id`,
ADD KEY IF NOT EXISTS `fk_ingresso_categoria` (`categoria_id`);

-- =====================================================
-- 12. SISTEMA DE RESERVAS TEMPORÁRIAS
-- =====================================================

CREATE TABLE IF NOT EXISTS `reservas_temporarias` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(255) NOT NULL,
  `cliente_id` INT(11) DEFAULT NULL,
  `ingresso_id` INT(11) NOT NULL,
  `quantidade` INT(11) NOT NULL DEFAULT 1,
  `preco_unitario` DECIMAL(10,2) NOT NULL,
  `expira_em` DATETIME NOT NULL,
  `status` ENUM('ativa', 'convertida', 'expirada', 'cancelada') DEFAULT 'ativa',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `fk_reserva_cliente` (`cliente_id`),
  KEY `fk_reserva_ingresso` (`ingresso_id`),
  KEY `idx_expira_em` (`expira_em`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_reserva_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reserva_ingresso` FOREIGN KEY (`ingresso_id`) REFERENCES `ingressos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. SISTEMA DE AVALIAÇÕES DE INGRESSOS/SETORES
-- =====================================================

CREATE TABLE IF NOT EXISTS `avaliacoes_setores` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `evento_id` INT(11) NOT NULL,
  `ingresso_id` INT(11) NOT NULL,
  `cliente_id` INT(11) NOT NULL,
  `rating` INT(11) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `comentario` TEXT DEFAULT NULL,
  `aspecto_visual` INT(11) DEFAULT NULL CHECK (`aspecto_visual` >= 1 AND `aspecto_visual` <= 5),
  `aspecto_auditivo` INT(11) DEFAULT NULL CHECK (`aspecto_auditivo` >= 1 AND `aspecto_auditivo` <= 5),
  `conforto` INT(11) DEFAULT NULL CHECK (`conforto` >= 1 AND `conforto` <= 5),
  `preco_beneficio` INT(11) DEFAULT NULL CHECK (`preco_beneficio` >= 1 AND `preco_beneficio` <= 5),
  `recomenda` TINYINT(1) DEFAULT 1,
  `aprovado` TINYINT(1) DEFAULT 0,
  `moderado_por` INT(11) DEFAULT NULL,
  `moderado_em` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cliente_ingresso_evento` (`cliente_id`, `ingresso_id`, `evento_id`),
  KEY `fk_avaliacao_evento` (`evento_id`),
  KEY `fk_avaliacao_ingresso` (`ingresso_id`),
  KEY `fk_avaliacao_cliente` (`cliente_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_aprovado` (`aprovado`),
  CONSTRAINT `fk_avaliacao_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avaliacao_ingresso` FOREIGN KEY (`ingresso_id`) REFERENCES `ingressos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avaliacao_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. TRIGGERS PARA AUTOMAÇÃO
-- =====================================================

DELIMITER $$

-- Trigger para atualizar contador de vendas nos ingressos
CREATE TRIGGER IF NOT EXISTS `tr_update_vendas_count` 
AFTER INSERT ON `ingressos_pedidos` 
FOR EACH ROW 
BEGIN 
  UPDATE ingressos 
  SET vendas_count = vendas_count + 1 
  WHERE id = NEW.ingresso_id;
END$$

-- Trigger para decrementar contador quando cancelado
CREATE TRIGGER IF NOT EXISTS `tr_update_vendas_count_delete` 
AFTER DELETE ON `ingressos_pedidos` 
FOR EACH ROW 
BEGIN 
  UPDATE ingressos 
  SET vendas_count = GREATEST(vendas_count - 1, 0) 
  WHERE id = OLD.ingresso_id;
END$$

-- Trigger para gerar QR Code automaticamente
CREATE TRIGGER IF NOT EXISTS `tr_generate_qr_code` 
BEFORE INSERT ON `ingressos_pedidos` 
FOR EACH ROW 
BEGIN 
  IF NEW.qr_code IS NULL THEN
    SET NEW.qr_code = CONCAT('QR_', NEW.ticket_code, '_', UNIX_TIMESTAMP());
    SET NEW.qr_code_data = JSON_OBJECT(
      'ticket_code', NEW.ticket_code,
      'evento_id', NEW.evento_id,
      'ingresso_id', NEW.ingresso_id,
      'cliente_id', NEW.cliente_id,
      'timestamp', UNIX_TIMESTAMP()
    );
  END IF;
END$$

-- Trigger para atualizar posição na waitlist
CREATE TRIGGER IF NOT EXISTS `tr_update_waitlist_position` 
BEFORE INSERT ON `waitlist` 
FOR EACH ROW 
BEGIN 
  DECLARE max_pos INT DEFAULT 0;
  SELECT COALESCE(MAX(posicao), 0) + 1 INTO max_pos 
  FROM waitlist 
  WHERE evento_id = NEW.evento_id 
    AND (ingresso_id = NEW.ingresso_id OR ingresso_id IS NULL)
    AND status = 'ativo';
  SET NEW.posicao = max_pos;
END$$

DELIMITER ;

-- =====================================================
-- 15. VIEWS PARA RELATÓRIOS
-- =====================================================

-- View para dashboard de ingressos
CREATE OR REPLACE VIEW `v_dashboard_ingressos` AS
SELECT 
  i.id,
  i.evento_id,
  e.nome AS evento_nome,
  e.data_inicio,
  i.tipo_ingresso,
  i.preco,
  i.quantidade,
  i.quantidade - COALESCE(SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END), 0) AS disponivel,
  i.vendas_count,
  COALESCE(SUM(CASE WHEN p.status = 'approved' THEN ip.preco ELSE 0 END), 0) AS receita_total,
  AVG(CASE WHEN as.rating IS NOT NULL THEN as.rating ELSE NULL END) AS rating_medio,
  COUNT(as.id) AS total_avaliacoes,
  i.liberado,
  i.created_at
FROM ingressos i
INNER JOIN eventos e ON i.evento_id = e.id
LEFT JOIN ingressos_pedidos ip ON i.id = ip.ingresso_id
LEFT JOIN pedidos p ON ip.pedido_id = p.id
LEFT JOIN avaliacoes_setores as ON i.id = as.ingresso_id
GROUP BY i.id, i.evento_id, e.nome, e.data_inicio, i.tipo_ingresso, i.preco, i.quantidade, i.vendas_count, i.liberado, i.created_at;

-- View para analytics de vendas
CREATE OR REPLACE VIEW `v_analytics_vendas` AS
SELECT 
  DATE(p.created_at) AS data_venda,
  e.id AS evento_id,
  e.nome AS evento_nome,
  i.id AS ingresso_id,
  i.tipo_ingresso,
  COUNT(ip.id) AS ingressos_vendidos,
  SUM(ip.preco) AS receita_bruta,
  AVG(ip.preco) AS ticket_medio,
  p.status
FROM pedidos p
INNER JOIN ingressos_pedidos ip ON p.id = ip.pedido_id
INNER JOIN ingressos i ON ip.ingresso_id = i.id
INNER JOIN eventos e ON i.evento_id = e.id
WHERE p.status IN ('approved', 'pending')
GROUP BY DATE(p.created_at), e.id, e.nome, i.id, i.tipo_ingresso, p.status
ORDER BY data_venda DESC;

-- =====================================================
-- 16. PROCEDIMENTOS ARMAZENADOS ÚTEIS
-- =====================================================

DELIMITER $$

-- Procedure para aplicar cupom de desconto
CREATE PROCEDURE IF NOT EXISTS `sp_aplicar_cupom`(
  IN p_codigo VARCHAR(20),
  IN p_cliente_id INT,
  IN p_valor_pedido DECIMAL(10,2),
  IN p_evento_id INT,
  OUT p_desconto DECIMAL(10,2),
  OUT p_mensagem VARCHAR(255)
)
BEGIN
  DECLARE v_cupom_id INT DEFAULT 0;
  DECLARE v_tipo VARCHAR(20);
  DECLARE v_valor DECIMAL(10,2);
  DECLARE v_uso_atual INT;
  DECLARE v_uso_maximo INT;
  DECLARE v_uso_cliente INT;
  DECLARE v_uso_por_cliente INT;
  DECLARE v_data_inicio DATETIME;
  DECLARE v_data_fim DATETIME;
  DECLARE v_valor_minimo DECIMAL(10,2);
  
  SET p_desconto = 0;
  SET p_mensagem = '';
  
  -- Buscar cupom
  SELECT id, tipo, valor, uso_atual, uso_maximo, uso_por_cliente, 
         data_inicio, data_fim, valor_minimo_pedido
  INTO v_cupom_id, v_tipo, v_valor, v_uso_atual, v_uso_maximo, 
       v_uso_por_cliente, v_data_inicio, v_data_fim, v_valor_minimo
  FROM cupons_desconto 
  WHERE codigo = p_codigo AND ativo = 1;
  
  IF v_cupom_id = 0 THEN
    SET p_mensagem = 'Cupom não encontrado ou inválido';
  ELSEIF NOW() < v_data_inicio THEN
    SET p_mensagem = 'Cupom ainda não está válido';
  ELSEIF NOW() > v_data_fim THEN
    SET p_mensagem = 'Cupom expirado';
  ELSEIF p_valor_pedido < v_valor_minimo THEN
    SET p_mensagem = CONCAT('Valor mínimo do pedido deve ser R$ ', v_valor_minimo);
  ELSEIF v_uso_maximo IS NOT NULL AND v_uso_atual >= v_uso_maximo THEN
    SET p_mensagem = 'Cupom esgotado';
  ELSE
    -- Verificar uso por cliente
    SELECT COUNT(*) INTO v_uso_cliente
    FROM cupons_uso 
    WHERE cupom_id = v_cupom_id AND cliente_id = p_cliente_id;
    
    IF v_uso_cliente >= v_uso_por_cliente THEN
      SET p_mensagem = 'Você já utilizou este cupom o máximo de vezes permitido';
    ELSE
      -- Calcular desconto
      IF v_tipo = 'percentual' THEN
        SET p_desconto = (p_valor_pedido * v_valor) / 100;
      ELSE
        SET p_desconto = v_valor;
      END IF;
      
      -- Garantir que desconto não seja maior que o pedido
      IF p_desconto > p_valor_pedido THEN
        SET p_desconto = p_valor_pedido;
      END IF;
      
      SET p_mensagem = 'Cupom aplicado com sucesso';
    END IF;
  END IF;
END$$

-- Procedure para processar waitlist quando ingresso fica disponível
CREATE PROCEDURE IF NOT EXISTS `sp_processar_waitlist`(
  IN p_evento_id INT,
  IN p_ingresso_id INT,
  IN p_quantidade_disponivel INT
)
BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE v_id INT;
  DECLARE v_email VARCHAR(100);
  DECLARE v_nome VARCHAR(100);
  DECLARE v_quantidade_desejada INT;
  
  DECLARE cur CURSOR FOR 
    SELECT id, email, nome, quantidade_desejada
    FROM waitlist 
    WHERE evento_id = p_evento_id 
      AND (ingresso_id = p_ingresso_id OR ingresso_id IS NULL)
      AND status = 'ativo'
    ORDER BY posicao ASC
    LIMIT p_quantidade_disponivel;
    
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  
  OPEN cur;
  
  read_loop: LOOP
    FETCH cur INTO v_id, v_email, v_nome, v_quantidade_desejada;
    IF done THEN
      LEAVE read_loop;
    END IF;
    
    -- Atualizar status para notificado
    UPDATE waitlist 
    SET status = 'notificado', 
        data_notificacao = NOW(),
        data_expiracao = DATE_ADD(NOW(), INTERVAL 24 HOUR)
    WHERE id = v_id;
    
    -- Inserir notificação
    INSERT INTO notificacoes_push 
    (usuario_id, tipo_notificacao, titulo, mensagem, programada_para)
    SELECT c.id, 'waitlist', 'Ingresso Disponível!', 
           CONCAT('Olá ', v_nome, ', o ingresso que você estava esperando ficou disponível!'),
           NOW()
    FROM clientes c 
    WHERE c.email = v_email;
    
  END LOOP;
  
  CLOSE cur;
END$$

DELIMITER ;

-- =====================================================
-- 17. CONFIGURAÇÕES FINAIS
-- =====================================================

-- Reativar verificação de chaves estrangeiras
SET foreign_key_checks = 1;

-- Inserir dados de exemplo para cupons
INSERT INTO `cupons_desconto` (`codigo`, `nome`, `tipo`, `valor`, `data_inicio`, `data_fim`, `uso_maximo`) VALUES
('BEMVINDO10', 'Desconto Boas-vindas', 'percentual', 10.00, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 100),
('PRIMEIRA20', 'Primeira Compra', 'percentual', 20.00, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 500),
('FLASH5', 'Desconto Relâmpago', 'valor_fixo', 5.00, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 50);

-- Inserir afiliado de exemplo
INSERT INTO `afiliados` (`nome`, `email`, `codigo_afiliado`, `percentual_comissao`, `ativo`, `aprovado`) VALUES
('Programa de Afiliados TicketSync', 'afiliados@ticketsync.com', 'TICKETSYNC', 10.00, 1, 1);

-- Otimizações finais
ANALYZE TABLE ingressos, ingressos_pedidos, pedidos, eventos;

-- =====================================================
-- SCRIPT CONCLUÍDO COM SUCESSO!
-- 
-- Este script implementa um sistema completo de gestão
-- de ingressos profissional com todas as funcionalidades
-- necessárias para competir com Eventbrite e Sympla.
-- =====================================================