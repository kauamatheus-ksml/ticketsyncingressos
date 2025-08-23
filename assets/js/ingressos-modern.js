/**
 * TicketSync - Ingressos Modern JavaScript
 * Sistema moderno de gestão de ingressos
 */

class IngressosManager {
    constructor() {
        this.currentPage = 1;
        this.currentView = 'grid';
        this.isLoading = false;
        this.searchTimeout = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadStats();
        this.loadEventos();
        this.loadIngressos();
    }
    
    bindEvents() {
        // Busca em tempo real
        document.getElementById('searchInput').addEventListener('input', () => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.currentPage = 1;
                this.loadIngressos();
            }, 500);
        });
        
        // Filtros
        ['eventoFilter', 'statusFilter', 'ordenacaoSelect'].forEach(filterId => {
            const element = document.getElementById(filterId);
            if (element) {
                element.addEventListener('change', () => {
                    this.currentPage = 1;
                    this.loadIngressos();
                });
            }
        });
        
        // Formulário de ingresso
        document.getElementById('ingressoForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveIngresso();
        });
        
        // Modal events
        const modal = document.getElementById('ingressoModal');
        modal.addEventListener('show.bs.modal', () => {
            this.resetForm();
        });
        
    }
    
    async loadStats() {
        try {
            const response = await fetch('ingressos.php?action=stats');
            const data = await response.json();
            
            document.getElementById('totalIngressos').textContent = data.total_ingressos || 0;
            document.getElementById('ingressosLiberados').textContent = data.ingressos_liberados || 0;
            document.getElementById('totalVendas').textContent = data.total_vendas || 0;
            document.getElementById('receitaTotal').textContent = this.formatMoney(data.receita_total || 0);
            
            // Animar números
            this.animateNumbers();
            
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    }
    
    async loadEventos() {
        try {
            const response = await fetch('ingressos.php?action=eventos');
            const eventos = await response.json();
            
            const eventoSelect = document.getElementById('eventoSelect');
            const eventoFilter = document.getElementById('eventoFilter');
            
            // Limpar options existentes
            eventoSelect.innerHTML = '<option value="">Selecionar evento...</option>';
            eventoFilter.innerHTML = '<option value="">Todos os Eventos</option>';
            
            eventos.forEach(evento => {
                const option = `<option value="${evento.id}">${evento.nome} - ${this.formatDate(evento.data_inicio)}</option>`;
                eventoSelect.insertAdjacentHTML('beforeend', option);
                eventoFilter.insertAdjacentHTML('beforeend', option);
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
                q: document.getElementById('searchInput').value,
                evento_id: document.getElementById('eventoFilter').value,
                status: document.getElementById('statusFilter').value,
                ordem: document.getElementById('ordenacaoSelect').value,
                view: this.currentView,
                page: this.currentPage
            });
            
            const response = await fetch(`ingressos.php?${params}`);
            const data = await response.json();
            
            this.renderIngressos(data.ingressos);
            this.renderPagination(data.page, data.totalPages, data.total);
            
            if (data.ingressos.length === 0) {
                this.showNoResults();
            }
            
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
        container.innerHTML = '';
        
        ingressos.forEach(ingresso => {
            const card = this.createIngressoCard(ingresso);
            container.appendChild(card);
        });
    }
    
    createIngressoCard(ingresso) {
        const div = document.createElement('div');
        div.className = 'card ticket-card fade-in-up';
        div.setAttribute('data-liberado', ingresso.liberado);
        
        const statusBadge = ingresso.liberado == 1 ? 
            '<span class="badge bg-success">Liberado</span>' : 
            '<span class="badge bg-warning">Bloqueado</span>';
            
        const esgotado = ingresso.disponivel <= 0;
        const esgotadoBadge = esgotado ? '<span class="badge bg-danger ms-1">Esgotado</span>' : '';
        
        div.innerHTML = `
            <div class="preview-evento">
                <img src="${ingresso.logo || 'uploads/default-event.jpg'}" 
                     alt="${ingresso.evento_nome}" 
                     onerror="this.src='uploads/default-event.jpg'">
                <div>
                    <h3>${ingresso.evento_nome}</h3>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        ${this.formatDate(ingresso.data_inicio)} às ${ingresso.hora_inicio}
                    </small>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="mb-0 text-primary">${ingresso.tipo_ingresso}</h5>
                    <div>${statusBadge}${esgotadoBadge}</div>
                </div>
                
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <p class="mb-1"><strong>Preço:</strong> ${this.formatMoney(ingresso.preco)}</p>
                    </div>
                    <div class="col-6">
                        <p class="mb-1"><strong>Disponível:</strong> ${ingresso.disponivel}/${ingresso.quantidade}</p>
                        <p class="mb-1"><strong>Vendidos:</strong> ${ingresso.vendas_count || 0}</p>
                    </div>
                </div>
                
                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <small class="text-muted">
                            <strong>Receita:</strong> ${this.formatMoney(ingresso.receita_total || 0)}
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-2 mt-auto">
                <button class="btn btn-primary btn-sm flex-fill" onclick="ingressosManager.editIngresso(${ingresso.id})">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn ${ingresso.liberado == 1 ? 'btn-warning' : 'btn-success'} btn-sm" 
                        onclick="ingressosManager.toggleStatus(${ingresso.id})">
                    <i class="fas ${ingresso.liberado == 1 ? 'fa-ban' : 'fa-check'}"></i>
                    ${ingresso.liberado == 1 ? 'Bloquear' : 'Liberar'}
                </button>
                <button class="btn btn-danger btn-sm" onclick="ingressosManager.deleteIngresso(${ingresso.id})">
                    <i class="fas fa-trash"></i>
                </button>
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
            
            // Preencher formulário
            document.getElementById('modalTitle').textContent = 'Editar Ingresso';
            document.getElementById('btnSalvarText').textContent = 'Atualizar Ingresso';
            document.getElementById('ingressoId').value = ingresso.id;
            
            // Aguardar eventos carregarem
            await this.loadEventos();
            
            // Preencher campos
            document.querySelector('[name="evento_id"]').value = ingresso.evento_id;
            document.querySelector('[name="tipo_ingresso"]').value = ingresso.tipo_ingresso;
            document.querySelector('[name="preco"]').value = ingresso.preco;
            document.querySelector('[name="quantidade"]').value = ingresso.quantidade;
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('ingressoModal'));
            modal.show();
            
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
        const result = await Swal.fire({
            title: 'Confirmar Exclusão',
            text: 'Tem certeza que deseja excluir este ingresso?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e53e3e',
            cancelButtonColor: '#718096',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        });
        
        if (result.isConfirmed) {
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
    }
    
    async saveIngresso() {
        const form = document.getElementById('ingressoForm');
        const formData = new FormData(form);
        
        // Desabilitar botão
        const btnSalvar = document.querySelector('#ingressoModal .btn-primary');
        const originalText = btnSalvar.innerHTML;
        btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';
        btnSalvar.disabled = true;
        
        try {
            const response = await fetch('ingressos.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(data.message);
                
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('ingressoModal'));
                modal.hide();
                
                // Recarregar dados
                this.loadIngressos();
                this.loadStats();
            } else {
                this.showError(data.message);
            }
            
        } catch (error) {
            console.error('Erro ao salvar ingresso:', error);
            this.showError('Erro ao salvar ingresso');
        } finally {
            // Reabilitar botão
            btnSalvar.innerHTML = originalText;
            btnSalvar.disabled = false;
        }
    }
    
    resetForm() {
        document.getElementById('ingressoForm').reset();
        document.getElementById('modalTitle').textContent = 'Novo Ingresso';
        document.getElementById('btnSalvarText').textContent = 'Salvar Ingresso';
        document.getElementById('ingressoId').value = '';
    }
    
    renderPagination(currentPage, totalPages, total) {
        const container = document.getElementById('paginationContainer');
        
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<ul class="pagination">';
        
        // Botão anterior
        if (currentPage > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="ingressosManager.goToPage(${currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>`;
        }
        
        // Páginas
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="ingressosManager.goToPage(1)">1</a>
            </li>`;
            if (startPage > 2) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="ingressosManager.goToPage(${i})">${i}</a>
            </li>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="ingressosManager.goToPage(${totalPages})">${totalPages}</a>
            </li>`;
        }
        
        // Botão próximo
        if (currentPage < totalPages) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="ingressosManager.goToPage(${currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>`;
        }
        
        html += '</ul>';
        
        html += `<div class="text-center mt-2">
            <small class="text-muted">
                Mostrando ${total} ingresso${total !== 1 ? 's' : ''} • Página ${currentPage} de ${totalPages}
            </small>
        </div>`;
        
        container.innerHTML = html;
    }
    
    goToPage(page) {
        this.currentPage = page;
        this.loadIngressos();
        
        // Scroll suave para o topo
        document.querySelector('.container').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
    
    showLoading() {
        document.getElementById('loadingContainer').classList.remove('d-none');
        document.getElementById('noResultsContainer').classList.add('d-none');
    }
    
    hideLoading() {
        document.getElementById('loadingContainer').classList.add('d-none');
    }
    
    showNoResults() {
        document.getElementById('noResultsContainer').classList.remove('d-none');
    }
    
    animateNumbers() {
        const animateNumber = (element, target) => {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                if (element.id === 'receitaTotal') {
                    element.textContent = this.formatMoney(current);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 20);
        };
        
        // Animar apenas se os valores mudaram
        const elements = ['totalIngressos', 'ingressosLiberados', 'totalVendas'];
        elements.forEach(id => {
            const element = document.getElementById(id);
            const target = parseInt(element.textContent) || 0;
            if (target > 0) {
                element.textContent = '0';
                animateNumber(element, target);
            }
        });
        
        // Animar receita
        const receitaElement = document.getElementById('receitaTotal');
        const receitaText = receitaElement.textContent.replace(/[R$\s.,]/g, '');
        const receitaTarget = parseFloat(receitaText) || 0;
        if (receitaTarget > 0) {
            animateNumber(receitaElement, receitaTarget);
        }
    }
    
    formatMoney(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }
    
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }
    
    showSuccess(message) {
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: message,
            confirmButtonColor: '#4299e1'
        });
    }
}

// Instanciar o manager quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    window.ingressosManager = new IngressosManager();
});

// Funções globais para compatibilidade
function goToPage(page) {
    window.ingressosManager.goToPage(page);
}

// Auto-refresh stats a cada 30 segundos
setInterval(() => {
    if (window.ingressosManager) {
        window.ingressosManager.loadStats();
    }
}, 30000);

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K para busca
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
    }
    
    // Esc para fechar modal
    if (e.key === 'Escape') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('ingressoModal'));
        if (modal) {
            modal.hide();
        }
    }
});

// Service Worker para cache (opcional)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {
        // Falha silenciosa
    });
}