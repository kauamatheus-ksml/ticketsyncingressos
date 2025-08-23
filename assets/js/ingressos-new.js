/**
 * TicketSync - Sistema Moderno de Ingressos
 * JavaScript moderno e funcional
 */

class IngressosSystem {
    constructor() {
        this.currentPage = 1;
        this.isLoading = false;
        this.searchTimeout = null;
        this.loteCount = 0;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadStats();
        this.loadEventos();
        this.loadIngressos();
        this.loadCupons();
    }
    
    bindEvents() {
        // Busca em tempo real
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.currentPage = 1;
                    this.loadIngressos();
                }, 300);
            });
        }
        
        // Filtros
        const filters = ['eventoFilter', 'statusFilter', 'ordenacaoSelect'];
        filters.forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => {
                    this.currentPage = 1;
                    this.loadIngressos();
                });
            }
        });
        
        // Modal
        const modal = document.getElementById('ingressoModal');
        if (modal) {
            // Botão de fechar
            const closeBtn = modal.querySelector('.modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closeModal());
            }
            
            // Clique fora do modal
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.closeModal();
                }
            });
        }
        
        // Form submit
        const form = document.getElementById('ingressoForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveIngresso();
            });
        }
        
        // Botão novo ingresso
        const btnNovo = document.getElementById('btnNovoIngresso');
        if (btnNovo) {
            btnNovo.addEventListener('click', () => {
                this.resetForm();
                this.openModal();
            });
        }
        
        // ===== EVENTOS DOS CUPONS =====
        
        // Botão novo cupom
        const btnNovoCupom = document.getElementById('btnNovoCupom');
        if (btnNovoCupom) {
            btnNovoCupom.addEventListener('click', () => {
                this.resetCupomForm();
                this.openCupomModal();
            });
        }
        
        // Modal de cupom
        const cupomModal = document.getElementById('cupomModal');
        if (cupomModal) {
            cupomModal.addEventListener('click', (e) => {
                if (e.target === cupomModal) {
                    this.closeCupomModal();
                }
            });
        }
        
        // Form de cupom
        const cupomForm = document.getElementById('cupomForm');
        if (cupomForm) {
            cupomForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveCupom();
            });
        }
        
        // Mudança no tipo de desconto
        const tipoDesconto = document.getElementById('tipoDesconto');
        if (tipoDesconto) {
            tipoDesconto.addEventListener('change', this.updateDescontoHelper.bind(this));
        }
        
        // ===== EVENTOS DOS LOTES =====
        
        // Checkbox usar lotes
        const usarLotes = document.getElementById('usarLotes');
        if (usarLotes) {
            usarLotes.addEventListener('change', this.toggleLotesSection.bind(this));
        }
        
        // Botão adicionar lote
        const btnAdicionarLote = document.getElementById('btnAdicionarLote');
        if (btnAdicionarLote) {
            btnAdicionarLote.addEventListener('click', this.adicionarLote.bind(this));
        }
    }
    
    async loadStats() {
        try {
            const response = await fetch('ingressos.php?action=stats');
            const data = await response.json();
            
            // Atualizar estatísticas
            this.updateElement('totalIngressos', data.total_ingressos || 0);
            this.updateElement('ingressosLiberados', data.ingressos_liberados || 0);
            this.updateElement('totalVendas', data.total_vendas || 0);
            this.updateElement('receitaTotal', this.formatCurrency(data.receita_total || 0));
            
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    }
    
    async loadEventos() {
        try {
            const response = await fetch('ingressos.php?action=eventos');
            const eventos = await response.json();
            
            const selects = ['eventoSelect', 'eventoFilter'];
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    // Manter primeira opção
                    const firstOption = select.querySelector('option');
                    select.innerHTML = '';
                    if (firstOption) select.appendChild(firstOption);
                    
                    // Adicionar eventos
                    eventos.forEach(evento => {
                        const option = document.createElement('option');
                        option.value = evento.id;
                        option.textContent = `${evento.nome} - ${this.formatDate(evento.data_inicio)}`;
                        select.appendChild(option);
                    });
                }
            });
            
        } catch (error) {
            console.error('Erro ao carregar eventos:', error);
        }
    }
    
    async loadIngressos() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            const params = new URLSearchParams({
                action: 'search',
                q: this.getInputValue('searchInput'),
                evento_id: this.getInputValue('eventoFilter'),
                status: this.getInputValue('statusFilter'),
                ordem: this.getInputValue('ordenacaoSelect') || 'created_desc',
                page: this.currentPage
            });
            
            const response = await fetch(`ingressos.php?${params}`);
            const data = await response.json();
            
            this.renderIngressos(data.ingressos || []);
            this.renderPagination(data.page || 1, data.totalPages || 1, data.total || 0);
            
        } catch (error) {
            console.error('Erro ao carregar ingressos:', error);
            this.showError('Erro ao carregar ingressos');
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }
    
    renderIngressos(ingressos) {
        const container = document.getElementById('ingressosGrid');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (ingressos.length === 0) {
            this.showEmptyState();
            return;
        }
        
        ingressos.forEach(ingresso => {
            const card = this.createIngressoCard(ingresso);
            container.appendChild(card);
        });
        
        this.hideEmptyState();
    }
    
    createIngressoCard(ingresso) {
        const div = document.createElement('div');
        div.className = 'ingresso-card fade-in-up';
        
        const disponivel = parseInt(ingresso.disponivel) || 0;
        const quantidade = parseInt(ingresso.quantidade) || 0;
        const vendidos = parseInt(ingresso.vendas_count) || 0;
        const percentVendido = quantidade > 0 ? (vendidos / quantidade) * 100 : 0;
        
        const statusBadge = ingresso.liberado == 1 ? 
            '<span class="badge badge-success">Liberado</span>' : 
            '<span class="badge badge-warning">Bloqueado</span>';
            
        const esgotadoBadge = disponivel <= 0 ? 
            '<span class="badge badge-danger">Esgotado</span>' : '';
        
        div.innerHTML = `
            <div class="card-header">
                <div class="evento-info">
                    <img class="evento-logo" 
                         src="${ingresso.logo || 'uploads/default-event.jpg'}" 
                         alt="${ingresso.evento_nome}"
                         onerror="this.src='uploads/default-event.jpg'">
                    <div class="evento-details">
                        <h4>${ingresso.evento_nome || 'Evento'}</h4>
                        <div class="date">
                            <i class="fas fa-calendar-alt"></i>
                            ${this.formatDate(ingresso.data_inicio)} às ${ingresso.hora_inicio || '00:00'}
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="ingresso-title">
                    ${ingresso.tipo_ingresso}
                    <div class="status-badges">
                        ${statusBadge}
                        ${esgotadoBadge}
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Preço</span>
                        <span class="info-value">${this.formatCurrency(ingresso.preco)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Disponível</span>
                        <span class="info-value">${disponivel}/${quantidade}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Vendidos</span>
                        <span class="info-value">${vendidos}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Receita</span>
                        <span class="info-value">${this.formatCurrency(ingresso.receita_total || 0)}</span>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${percentVendido}%"></div>
                </div>
                <div class="mt-1" style="font-size: 0.8rem; color: var(--gray-500);">
                    ${percentVendido.toFixed(1)}% vendido
                </div>
                
                <div class="card-actions">
                    <button class="btn btn-primary btn-sm" onclick="ingressosSystem.editIngresso(${ingresso.id})">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn ${ingresso.liberado == 1 ? 'btn-warning' : 'btn-success'} btn-sm" 
                            onclick="ingressosSystem.toggleStatus(${ingresso.id})">
                        <i class="fas ${ingresso.liberado == 1 ? 'fa-ban' : 'fa-check'}"></i>
                        ${ingresso.liberado == 1 ? 'Bloquear' : 'Liberar'}
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="ingressosSystem.deleteIngresso(${ingresso.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        return div;
    }
    
    async editIngresso(id) {
        try {
            const response = await fetch(`ingressos.php?action=get_ingresso&id=${id}`);
            const ingresso = await response.json();
            
            if (ingresso.error) {
                this.showError(ingresso.error);
                return;
            }
            
            // Aguardar eventos carregarem
            await this.loadEventos();
            
            // Preencher formulário
            document.getElementById('modalTitle').textContent = 'Editar Ingresso';
            document.getElementById('ingressoId').value = ingresso.id;
            document.getElementById('eventoSelect').value = ingresso.evento_id;
            document.getElementById('tipoIngresso').value = ingresso.tipo_ingresso;
            document.getElementById('preco').value = ingresso.preco;
            document.getElementById('quantidade').value = ingresso.quantidade;
            
            this.openModal();
            
        } catch (error) {
            console.error('Erro ao carregar ingresso:', error);
            this.showError('Erro ao carregar dados do ingresso');
        }
    }
    
    async toggleStatus(id) {
        try {
            const response = await fetch(`ingressos.php?action=toggle_status&id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadIngressos();
                this.loadStats();
            } else {
                this.showError(data.message || 'Erro ao alterar status');
            }
            
        } catch (error) {
            console.error('Erro ao alterar status:', error);
            this.showError('Erro ao alterar status do ingresso');
        }
    }
    
    async deleteIngresso(id) {
        if (!confirm('Tem certeza que deseja excluir este ingresso?')) {
            return;
        }
        
        try {
            const response = await fetch(`ingressos.php?action=delete_ingresso&id=${id}`, {
                method: 'DELETE'
            });
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadIngressos();
                this.loadStats();
            } else {
                this.showError(data.message);
            }
            
        } catch (error) {
            console.error('Erro ao excluir ingresso:', error);
            this.showError('Erro ao excluir ingresso');
        }
    }
    
    async saveIngresso() {
        const form = document.getElementById('ingressoForm');
        const formData = new FormData(form);
        
        const btnSalvar = document.getElementById('btnSalvar');
        const originalText = btnSalvar.innerHTML;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        btnSalvar.disabled = true;
        
        try {
            const response = await fetch('ingressos.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.closeModal();
                this.loadIngressos();
                this.loadStats();
            } else {
                this.showError(data.message);
            }
            
        } catch (error) {
            console.error('Erro ao salvar ingresso:', error);
            this.showError('Erro ao salvar ingresso');
        } finally {
            btnSalvar.innerHTML = originalText;
            btnSalvar.disabled = false;
        }
    }
    
    resetForm() {
        const form = document.getElementById('ingressoForm');
        if (form) form.reset();
        
        document.getElementById('modalTitle').textContent = 'Novo Ingresso';
        document.getElementById('ingressoId').value = '';
        
        // Resetar lotes
        document.getElementById('usarLotes').checked = false;
        this.toggleLotesSection();
        document.getElementById('lotesContainer').innerHTML = '';
        this.loteCount = 0;
    }
    
    openModal() {
        const modal = document.getElementById('ingressoModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    closeModal() {
        const modal = document.getElementById('ingressoModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    // ===== MÉTODOS DOS CUPONS =====
    
    async loadCupons() {
        try {
            const response = await fetch('ingressos.php?action=load_cupons');
            const cupons = await response.json();
            this.renderCupons(cupons);
        } catch (error) {
            console.error('Erro ao carregar cupons:', error);
        }
    }
    
    renderCupons(cupons) {
        const container = document.getElementById('cuponsGrid');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (cupons.length === 0) {
            container.innerHTML = `
                <div class="cupom-empty">
                    <i class="fas fa-ticket-alt"></i>
                    <h4>Nenhum cupom criado</h4>
                    <p>Crie cupons de desconto para aumentar suas vendas!</p>
                </div>
            `;
            return;
        }
        
        cupons.forEach(cupom => {
            const card = this.createCupomCard(cupom);
            container.appendChild(card);
        });
    }
    
    createCupomCard(cupom) {
        const div = document.createElement('div');
        div.className = `cupom-card ${cupom.ativo == 0 ? 'inactive' : ''}`;
        
        const statusClass = this.getCupomStatusClass(cupom.status_cupom);
        const statusText = this.getCupomStatusText(cupom.status_cupom);
        
        const descontoDisplay = cupom.tipo_desconto === 'percentual' ? 
            `${cupom.valor_desconto}%` : 
            this.formatCurrency(cupom.valor_desconto);
            
        const limiteTxt = cupom.limite_uso > 0 ? 
            `${cupom.usos_count}/${cupom.limite_uso}` : 
            cupom.usos_count;
            
        const progressPercent = cupom.limite_uso > 0 ? 
            (cupom.usos_count / cupom.limite_uso) * 100 : 0;
        
        div.innerHTML = `
            <div class="cupom-status ${statusClass}">${statusText}</div>
            <div class="cupom-header">
                <h4 class="cupom-code">${cupom.codigo}</h4>
                <p class="cupom-type">
                    ${cupom.tipo_desconto === 'percentual' ? 'Desconto Percentual' : 'Desconto Fixo'}
                </p>
            </div>
            <div class="cupom-body">
                <div class="cupom-desconto">${descontoDisplay}</div>
                
                <div class="cupom-info">
                    <div class="cupom-info-item">
                        <div class="cupom-info-label">Usos</div>
                        <div class="cupom-info-value">${limiteTxt}</div>
                    </div>
                    <div class="cupom-info-item">
                        <div class="cupom-info-label">Status</div>
                        <div class="cupom-info-value">${cupom.ativo == 1 ? 'Ativo' : 'Inativo'}</div>
                    </div>
                </div>
                
                ${cupom.limite_uso > 0 ? `
                    <div class="cupom-progress">
                        <div class="cupom-progress-label">
                            <span>Progresso de uso</span>
                            <span>${progressPercent.toFixed(1)}%</span>
                        </div>
                        <div class="cupom-progress-bar">
                            <div class="cupom-progress-fill" style="width: ${progressPercent}%"></div>
                        </div>
                    </div>
                ` : ''}
                
                ${(cupom.data_inicio || cupom.data_fim) ? `
                    <div class="cupom-dates">
                        ${cupom.data_inicio ? `<div><strong>Início:</strong> ${this.formatDateTime(cupom.data_inicio)}</div>` : ''}
                        ${cupom.data_fim ? `<div><strong>Fim:</strong> ${this.formatDateTime(cupom.data_fim)}</div>` : ''}
                    </div>
                ` : ''}
                
                ${cupom.descricao ? `<p style="font-size: 0.9rem; color: var(--gray-600); margin: 1rem 0;">${cupom.descricao}</p>` : ''}
                
                <div class="cupom-actions">
                    <button class="btn btn-primary btn-sm" onclick="ingressosSystem.editCupom(${cupom.id})">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn ${cupom.ativo == 1 ? 'btn-warning' : 'btn-success'} btn-sm" 
                            onclick="ingressosSystem.toggleCupomStatus(${cupom.id})">
                        <i class="fas ${cupom.ativo == 1 ? 'fa-ban' : 'fa-check'}"></i>
                        ${cupom.ativo == 1 ? 'Desativar' : 'Ativar'}
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="ingressosSystem.deleteCupom(${cupom.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        return div;
    }
    
    getCupomStatusClass(status) {
        const statusMap = {
            'ativo': 'ativo',
            'inativo': 'inativo',
            'expirado': 'expirado',
            'esgotado': 'esgotado',
            'agendado': 'agendado'
        };
        return statusMap[status] || 'inativo';
    }
    
    getCupomStatusText(status) {
        const textMap = {
            'ativo': 'Ativo',
            'inativo': 'Inativo',
            'expirado': 'Expirado',
            'esgotado': 'Esgotado',
            'agendado': 'Agendado'
        };
        return textMap[status] || 'Inativo';
    }
    
    async editCupom(id) {
        try {
            const response = await fetch(`ingressos.php?action=get_cupom&id=${id}`);
            const cupom = await response.json();
            
            if (cupom.error) {
                this.showError(cupom.error);
                return;
            }
            
            // Carregar eventos para o select
            await this.loadEventosForCupom();
            
            // Preencher formulário
            document.getElementById('cupomModalTitle').textContent = 'Editar Cupom';
            document.getElementById('cupomId').value = cupom.id;
            document.getElementById('codigoCupom').value = cupom.codigo;
            document.getElementById('tipoDesconto').value = cupom.tipo_desconto;
            document.getElementById('valorDesconto').value = cupom.valor_desconto;
            document.getElementById('limiteUso').value = cupom.limite_uso || '';
            
            if (cupom.data_inicio) {
                document.getElementById('dataInicio').value = this.formatDateTimeLocal(cupom.data_inicio);
            }
            if (cupom.data_fim) {
                document.getElementById('dataFim').value = this.formatDateTimeLocal(cupom.data_fim);
            }
            
            document.getElementById('descricaoCupom').value = cupom.descricao || '';
            
            // Selecionar eventos
            const eventosSelect = document.getElementById('eventosSelect');
            if (cupom.eventos_ids) {
                const eventosIds = cupom.eventos_ids.split(',');
                Array.from(eventosSelect.options).forEach(option => {
                    option.selected = eventosIds.includes(option.value);
                });
            }
            
            this.updateDescontoHelper();
            this.openCupomModal();
            
        } catch (error) {
            console.error('Erro ao carregar cupom:', error);
            this.showError('Erro ao carregar dados do cupom');
        }
    }
    
    async toggleCupomStatus(id) {
        try {
            const response = await fetch(`ingressos.php?action=toggle_cupom_status&id=${id}`);
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadCupons();
            } else {
                this.showError(data.message || 'Erro ao alterar status');
            }
            
        } catch (error) {
            console.error('Erro ao alterar status do cupom:', error);
            this.showError('Erro ao alterar status do cupom');
        }
    }
    
    async deleteCupom(id) {
        if (!confirm('Tem certeza que deseja excluir este cupom?')) {
            return;
        }
        
        try {
            const response = await fetch(`ingressos.php?action=delete_cupom&id=${id}`, {
                method: 'DELETE'
            });
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.loadCupons();
            } else {
                this.showError(data.message);
            }
            
        } catch (error) {
            console.error('Erro ao excluir cupom:', error);
            this.showError('Erro ao excluir cupom');
        }
    }
    
    async saveCupom() {
        const form = document.getElementById('cupomForm');
        const formData = new FormData(form);
        
        const btnSalvar = document.getElementById('btnSalvarCupom');
        const originalText = btnSalvar.innerHTML;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        btnSalvar.disabled = true;
        
        try {
            const response = await fetch('ingressos.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                this.closeCupomModal();
                this.loadCupons();
            } else {
                this.showError(data.message);
            }
            
        } catch (error) {
            console.error('Erro ao salvar cupom:', error);
            this.showError('Erro ao salvar cupom');
        } finally {
            btnSalvar.innerHTML = originalText;
            btnSalvar.disabled = false;
        }
    }
    
    async loadEventosForCupom() {
        try {
            const response = await fetch('ingressos.php?action=eventos');
            const eventos = await response.json();
            
            const select = document.getElementById('eventosSelect');
            if (select) {
                select.innerHTML = '';
                eventos.forEach(evento => {
                    const option = document.createElement('option');
                    option.value = evento.id;
                    option.textContent = `${evento.nome} - ${this.formatDate(evento.data_inicio)}`;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Erro ao carregar eventos para cupom:', error);
        }
    }
    
    updateDescontoHelper() {
        const tipo = document.getElementById('tipoDesconto').value;
        const label = document.getElementById('labelValorDesconto');
        const helper = document.getElementById('descontoHelper');
        const input = document.getElementById('valorDesconto');
        
        if (tipo === 'percentual') {
            label.textContent = 'Percentual de Desconto (%) *';
            helper.textContent = 'Valor entre 0 e 100';
            input.max = '100';
            input.step = '0.01';
        } else if (tipo === 'valor_fixo') {
            label.textContent = 'Valor Fixo (R$) *';
            helper.textContent = 'Valor em reais';
            input.max = '';
            input.step = '0.01';
        } else {
            label.textContent = 'Valor do Desconto *';
            helper.textContent = 'Informe o valor do desconto';
        }
    }
    
    resetCupomForm() {
        const form = document.getElementById('cupomForm');
        if (form) form.reset();
        
        document.getElementById('cupomModalTitle').textContent = 'Novo Cupom de Desconto';
        document.getElementById('cupomId').value = '';
        this.updateDescontoHelper();
    }
    
    openCupomModal() {
        const modal = document.getElementById('cupomModal');
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            this.loadEventosForCupom();
        }
    }
    
    closeCupomModal() {
        const modal = document.getElementById('cupomModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    renderPagination(currentPage, totalPages, total) {
        const container = document.getElementById('paginationContainer');
        if (!container) return;
        
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<div class="pagination">';
        
        // Anterior
        if (currentPage > 1) {
            html += `<a href="#" class="page-btn" onclick="ingressosSystem.goToPage(${currentPage - 1})">
                <i class="fas fa-chevron-left"></i>
            </a>`;
        }
        
        // Páginas
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<a href="#" class="page-btn ${i === currentPage ? 'active' : ''}" 
                       onclick="ingressosSystem.goToPage(${i})">${i}</a>`;
        }
        
        // Próxima
        if (currentPage < totalPages) {
            html += `<a href="#" class="page-btn" onclick="ingressosSystem.goToPage(${currentPage + 1})">
                <i class="fas fa-chevron-right"></i>
            </a>`;
        }
        
        html += '</div>';
        html += `<div class="page-info">
            Mostrando ${total} ingresso${total !== 1 ? 's' : ''} • Página ${currentPage} de ${totalPages}
        </div>`;
        
        container.innerHTML = html;
    }
    
    goToPage(page) {
        this.currentPage = page;
        this.loadIngressos();
    }
    
    showLoading() {
        const container = document.getElementById('ingressosGrid');
        if (container) {
            container.innerHTML = `
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Carregando ingressos...</p>
                </div>
            `;
        }
    }
    
    hideLoading() {
        // O loading é substituído pelo conteúdo
    }
    
    showEmptyState() {
        const container = document.getElementById('ingressosGrid');
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <h3 class="empty-title">Nenhum ingresso encontrado</h3>
                    <p class="empty-text">Crie seu primeiro ingresso para começar!</p>
                </div>
            `;
        }
    }
    
    hideEmptyState() {
        // O empty state é substituído pelo conteúdo
    }
    
    // Utility methods
    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }
    
    getInputValue(id) {
        const element = document.getElementById(id);
        return element ? element.value : '';
    }
    
    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value || 0);
    }
    
    formatDate(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        } catch {
            return dateString;
        }
    }
    
    formatDateTime(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            return date.toLocaleString('pt-BR');
        } catch {
            return dateString;
        }
    }
    
    formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}`;
        } catch {
            return '';
        }
    }
    
    showSuccess(message) {
        this.showToast(message, 'success');
    }
    
    showError(message) {
        this.showToast(message, 'error');
    }
    
    showToast(message, type = 'info') {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};
            color: white;
            border-radius: var(--border-radius);
            z-index: 9999;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            transform: translateX(100%);
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
}

// Initialize system when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.ingressosSystem = new IngressosSystem();
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (window.ingressosSystem) {
            window.ingressosSystem.closeModal();
            window.ingressosSystem.closeCupomModal();
        }
    }
});

// Auto refresh stats every 30 seconds
setInterval(() => {
    if (window.ingressosSystem && !window.ingressosSystem.isLoading) {
        window.ingressosSystem.loadStats();
    }
}, 30000);