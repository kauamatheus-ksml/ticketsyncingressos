/**
 * TicketSync - Eventos Modern JavaScript
 * Sistema avançado de gestão de eventos
 */

class EventosManager {
    constructor() {
        this.mapbox = null;
        this.geocoder = null;
        this.map = null;
        this.currentView = 'grid';
        this.currentPage = 1;
        this.currentFilters = {
            search: '',
            categoria: '',
            status: '',
            ordem: 'data_asc'
        };
        this.debounceTimer = null;
        this.loadingStates = new Set();
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupMapbox();
        this.loadInitialData();
        this.hidePreloader();
    }
    
    // === SETUP INICIAL === //
    
    setupEventListeners() {
        // Busca em tempo real
        document.getElementById('searchInput').addEventListener('input', (e) => {
            this.debounceSearch(e.target.value);
        });
        
        // Filtros
        document.getElementById('categoriaFilter').addEventListener('change', (e) => {
            this.currentFilters.categoria = e.target.value;
            this.searchEventos();
        });
        
        document.getElementById('statusFilter').addEventListener('change', (e) => {
            this.currentFilters.status = e.target.value;
            this.searchEventos();
        });
        
        document.getElementById('ordenacaoSelect').addEventListener('change', (e) => {
            this.currentFilters.ordem = e.target.value;
            this.searchEventos();
        });
        
        // Alternância de visualização
        document.getElementById('gridView').addEventListener('click', () => {
            this.setView('grid');
        });
        
        document.getElementById('listView').addEventListener('click', () => {
            this.setView('list');
        });
        
        document.getElementById('mapView').addEventListener('click', () => {
            this.setView('map');
        });
        
        // Formulário de evento
        document.getElementById('eventoForm').addEventListener('submit', (e) => {
            this.handleFormSubmit(e);
        });
        
        // Preview de imagens
        document.querySelector('input[name="logo"]').addEventListener('change', (e) => {
            this.previewImage(e.target, 'logoPreview');
        });
        
        document.querySelector('input[name="galeria[]"]').addEventListener('change', (e) => {
            this.previewGallery(e.target, 'galeriaPreview');
        });
        
        // Tipo de evento
        document.querySelector('select[name="tipo_evento"]').addEventListener('change', (e) => {
            this.toggleTransmissionField(e.target.value);
        });
        
        // Modal events
        document.getElementById('eventoModal').addEventListener('hidden.bs.modal', () => {
            this.resetForm();
        });
        
        // Scroll infinito
        window.addEventListener('scroll', () => {
            this.handleScroll();
        });
        
        // Teclas de atalho
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    }
    
    setupMapbox() {
        // Configurar token do Mapbox
        mapboxgl.accessToken = 'pk.eyJ1Ijoia2F1YW1hdGhldXM5MjAiLCJhIjoiY203OGZvbmRyMWxkMzJqb2l6bXQ2NDZpMSJ9.WjRV7tH7lbvR1wVDDvQD0g';
        
        // Geocoder para o formulário
        this.geocoder = new MapboxGeocoder({
            accessToken: mapboxgl.accessToken,
            language: 'pt-BR',
            placeholder: 'Digite o endereço do evento...',
            marker: false
        });
        
        document.getElementById('geocoderContainer').appendChild(this.geocoder.onAdd());
        
        this.geocoder.on('result', (e) => {
            const coords = e.result.center;
            const placeName = e.result.place_name;
            
            document.getElementById('eventLocal').value = placeName;
            document.getElementById('eventLat').value = coords[1];
            document.getElementById('eventLng').value = coords[0];
        });
    }
    
    loadInitialData() {
        this.loadStats();
        this.loadCategorias();
        this.searchEventos();
    }
    
    hidePreloader() {
        setTimeout(() => {
            const preloader = document.getElementById('preloader');
            preloader.classList.add('hide');
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 300);
        }, 500);
    }
    
    // === FUNCIONALIDADES PRINCIPAIS === //
    
    debounceSearch(query) {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.currentFilters.search = query;
            this.currentPage = 1;
            this.searchEventos();
        }, 300);
    }
    
    async searchEventos(append = false) {
        if (!append) {
            this.showLoading();
        }
        
        const params = new URLSearchParams({
            action: 'search',
            q: this.currentFilters.search,
            categoria: this.currentFilters.categoria,
            status: this.currentFilters.status,
            ordem: this.currentFilters.ordem,
            view: this.currentView,
            page: this.currentPage
        });
        
        try {
            const response = await fetch(`eventos.php?${params}`);
            const data = await response.json();
            
            if (data.eventos) {
                if (append) {
                    this.appendEventos(data.eventos);
                } else {
                    this.renderEventos(data.eventos, data.visualizacao);
                }
                
                this.renderPagination(data.page, data.totalPages, data.total);
            }
        } catch (error) {
            console.error('Erro ao buscar eventos:', error);
            this.showError('Erro ao carregar eventos');
        } finally {
            this.hideLoading();
        }
    }
    
    renderEventos(eventos, visualizacao) {
        const container = document.getElementById(
            visualizacao === 'list' ? 'eventosList' : 'eventosGrid'
        );
        
        if (eventos.length === 0) {
            this.showNoResults();
            return;
        }
        
        this.hideNoResults();
        
        if (visualizacao === 'grid') {
            container.innerHTML = eventos.map(evento => this.createEventCard(evento)).join('');
        } else if (visualizacao === 'list') {
            container.innerHTML = eventos.map(evento => this.createEventListItem(evento)).join('');
        } else if (visualizacao === 'map') {
            this.renderMapView(eventos);
        }
        
        // Adicionar animações
        this.animateElements(container.children);
        
        // Configurar event listeners dos cards
        this.setupCardListeners();
    }
    
    createEventCard(evento) {
        const dataInicio = new Date(evento.data_inicio + ' ' + evento.hora_inicio);
        const dataFormatada = dataInicio.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        const horaFormatada = dataInicio.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const categoriaStyle = evento.categoria_cor ? 
            `background-color: ${evento.categoria_cor}20; color: ${evento.categoria_cor};` : 
            'background-color: #f7fafc; color: #4a5568;';
        
        const statusClass = `status-${evento.status}`;
        const destaque = evento.destaque ? 'evento-destaque' : '';
        
        return `
            <div class="evento-card ${destaque}" data-id="${evento.id}">
                <div class="evento-card-image">
                    ${evento.logo ? 
                        `<img src="${evento.logo}" alt="${evento.nome}" loading="lazy">` :
                        `<div class="placeholder-image d-flex align-items-center justify-content-center bg-light h-100">
                            <i class="fas fa-calendar-alt fa-3x text-muted"></i>
                        </div>`
                    }
                    <div class="evento-card-badge ${statusClass}">
                        ${this.getStatusLabel(evento.status)}
                    </div>
                    <button class="favorite-btn" data-evento-id="${evento.id}" title="Adicionar aos favoritos">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
                
                <div class="evento-card-content">
                    ${evento.categoria_nome ? 
                        `<div class="evento-categoria" style="${categoriaStyle}">
                            <i class="${evento.categoria_icone || 'fas fa-tag'}"></i>
                            ${evento.categoria_nome}
                        </div>` : ''
                    }
                    
                    <h3 class="evento-title">${evento.nome}</h3>
                    
                    <div class="evento-meta">
                        <div class="evento-meta-item">
                            <i class="fas fa-calendar"></i>
                            ${dataFormatada} às ${horaFormatada}
                        </div>
                        <div class="evento-meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            ${evento.local}
                        </div>
                        ${evento.faixa_etaria && evento.faixa_etaria !== 'livre' ? 
                            `<div class="evento-meta-item">
                                <i class="fas fa-users"></i>
                                ${evento.faixa_etaria} anos
                            </div>` : ''
                        }
                        ${evento.capacidade_maxima ? 
                            `<div class="evento-meta-item">
                                <i class="fas fa-user-friends"></i>
                                Capacidade: ${evento.capacidade_maxima}
                            </div>` : ''
                        }
                    </div>
                    
                    <p class="evento-description">${evento.descricao_evento}</p>
                    
                    ${evento.tags ? 
                        `<div class="evento-tags">
                            ${evento.tags.split(', ').map(tag => 
                                `<span class="tag">${tag}</span>`
                            ).join('')}
                        </div>` : ''
                    }
                    
                    <div class="evento-footer">
                        <div class="evento-stats">
                            <span><i class="fas fa-eye"></i> ${evento.visualizacoes || 0}</span>
                            <span><i class="fas fa-heart"></i> ${evento.total_favoritos || 0}</span>
                            ${evento.rating_medio > 0 ? 
                                `<span><i class="fas fa-star"></i> ${evento.rating_medio}/5</span>` : ''
                            }
                        </div>
                        
                        <div class="evento-actions">
                            <button class="btn-action btn-edit" onclick="editEvento(${evento.id})">
                                <i class="fas fa-edit"></i>
                                Editar
                            </button>
                            <button class="btn-action btn-delete" onclick="deleteEvento(${evento.id})">
                                <i class="fas fa-trash"></i>
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    createEventListItem(evento) {
        // Similar ao card, mas em formato de lista
        return `
            <div class="evento-item" data-id="${evento.id}">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        ${evento.logo ? 
                            `<img src="${evento.logo}" alt="${evento.nome}" class="img-fluid rounded">` :
                            `<div class="placeholder-image bg-light rounded d-flex align-items-center justify-content-center" style="height: 100px;">
                                <i class="fas fa-calendar-alt fa-2x text-muted"></i>
                            </div>`
                        }
                    </div>
                    <div class="col-md-8">
                        <h4>${evento.nome}</h4>
                        <p class="text-muted mb-1">
                            <i class="fas fa-calendar me-2"></i>
                            ${new Date(evento.data_inicio).toLocaleDateString('pt-BR')} às 
                            ${new Date(evento.data_inicio + ' ' + evento.hora_inicio).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}
                        </p>
                        <p class="text-muted mb-1">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            ${evento.local}
                        </p>
                        <p class="mb-0">${evento.descricao_evento.substring(0, 150)}...</p>
                    </div>
                    <div class="col-md-2 text-end">
                        <div class="btn-group-vertical">
                            <button class="btn btn-sm btn-outline-primary" onclick="editEvento(${evento.id})">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteEvento(${evento.id})">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    renderMapView(eventos) {
        // Inicializar mapa se não existir
        if (!this.map) {
            this.map = new mapboxgl.Map({
                container: 'mapContainer',
                style: 'mapbox://styles/mapbox/streets-v11',
                center: [-46.633309, -23.55052], // São Paulo
                zoom: 10
            });
            
            this.map.addControl(new mapboxgl.NavigationControl());
        }
        
        // Limpar marcadores existentes
        const existingMarkers = document.querySelectorAll('.mapboxgl-marker');
        existingMarkers.forEach(marker => marker.remove());
        
        // Adicionar marcadores dos eventos
        eventos.forEach(evento => {
            if (evento.lat && evento.lng) {
                const marker = new mapboxgl.Marker()
                    .setLngLat([evento.lng, evento.lat])
                    .setPopup(new mapboxgl.Popup().setHTML(`
                        <div class="p-2">
                            <h6>${evento.nome}</h6>
                            <p class="mb-1 small">${evento.local}</p>
                            <p class="mb-1 small">
                                ${new Date(evento.data_inicio).toLocaleDateString('pt-BR')}
                            </p>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-primary btn-sm" onclick="editEvento(${evento.id})">
                                    Editar
                                </button>
                            </div>
                        </div>
                    `))
                    .addTo(this.map);
            }
        });
        
        // Ajustar zoom para mostrar todos os marcadores
        if (eventos.length > 0) {
            const coords = eventos
                .filter(e => e.lat && e.lng)
                .map(e => [e.lng, e.lat]);
            
            if (coords.length > 1) {
                const bounds = coords.reduce(
                    (bounds, coord) => bounds.extend(coord),
                    new mapboxgl.LngLatBounds(coords[0], coords[0])
                );
                this.map.fitBounds(bounds, { padding: 50 });
            } else if (coords.length === 1) {
                this.map.setCenter(coords[0]);
                this.map.setZoom(14);
            }
        }
    }
    
    setView(view) {
        this.currentView = view;
        
        // Atualizar botões
        document.querySelectorAll('.view-toggle .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById(view + 'View').classList.add('active');
        
        // Mostrar/esconder containers
        document.getElementById('eventosGrid').classList.toggle('d-none', view !== 'grid');
        document.getElementById('eventosList').classList.toggle('d-none', view !== 'list');
        document.getElementById('eventosMap').classList.toggle('d-none', view !== 'map');
        
        // Recarregar dados
        this.searchEventos();
    }
    
    // === FORMULÁRIO === //
    
    async handleFormSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = document.querySelector('#eventoModal .btn-primary');
        
        // Mostrar loading
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Salvando...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch('eventos.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess(result.message);
                bootstrap.Modal.getInstance(document.getElementById('eventoModal')).hide();
                this.searchEventos(); // Recarregar lista
                this.loadStats(); // Atualizar estatísticas
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Erro ao salvar evento:', error);
            this.showError('Erro interno. Tente novamente.');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }
    
    resetForm() {
        const form = document.getElementById('eventoForm');
        form.reset();
        
        document.getElementById('eventoId').value = '';
        document.getElementById('modalTitle').textContent = 'Novo Evento';
        document.getElementById('btnSalvarText').textContent = 'Salvar Evento';
        
        // Limpar previews
        document.getElementById('logoPreview').innerHTML = '';
        document.getElementById('galeriaPreview').innerHTML = '';
        
        // Voltar para primeira aba
        const firstTab = document.querySelector('.nav-tabs .nav-link');
        if (firstTab) {
            new bootstrap.Tab(firstTab).show();
        }
    }
    
    previewImage(input, containerId) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                container.innerHTML = `
                    <div class="mt-2">
                        <img src="${e.target.result}" alt="Preview" 
                             class="img-fluid rounded" style="max-height: 200px;">
                    </div>
                `;
            };
            
            reader.readAsDataURL(file);
        }
    }
    
    previewGallery(input, containerId) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        
        if (input.files) {
            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const col = document.createElement('div');
                    col.className = 'col-md-3';
                    col.innerHTML = `
                        <img src="${e.target.result}" alt="Galeria ${index + 1}" 
                             class="img-fluid rounded">
                    `;
                    container.appendChild(col);
                };
                
                reader.readAsDataURL(file);
            });
        }
    }
    
    toggleTransmissionField(tipo) {
        const linkGroup = document.getElementById('linkTransmissaoGroup');
        if (tipo === 'virtual' || tipo === 'hibrido') {
            linkGroup.classList.remove('d-none');
            linkGroup.querySelector('input').required = true;
        } else {
            linkGroup.classList.add('d-none');
            linkGroup.querySelector('input').required = false;
        }
    }
    
    // === FAVORITOS === //
    
    async toggleFavorite(eventoId) {
        try {
            const response = await fetch(`eventos.php?action=toggle_favorite&evento_id=${eventoId}`);
            const result = await response.json();
            
            const btn = document.querySelector(`[data-evento-id="${eventoId}"]`);
            const icon = btn.querySelector('i');
            
            if (result.favorito) {
                btn.classList.add('active');
                icon.className = 'fas fa-heart';
                this.showSuccess('Evento adicionado aos favoritos!');
            } else {
                btn.classList.remove('active');
                icon.className = 'far fa-heart';
                this.showSuccess('Evento removido dos favoritos!');
            }
        } catch (error) {
            console.error('Erro ao alterar favorito:', error);
            this.showError('Erro ao alterar favorito');
        }
    }
    
    setupCardListeners() {
        // Favoritos
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const eventoId = btn.dataset.eventoId;
                this.toggleFavorite(eventoId);
            });
        });
        
        // Clique no card para analytics
        document.querySelectorAll('.evento-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (!e.target.closest('.evento-actions') && !e.target.closest('.favorite-btn')) {
                    // Registrar clique para analytics
                    const eventoId = card.dataset.id;
                    this.registerAnalytics(eventoId, 'click');
                }
            });
        });
    }
    
    async registerAnalytics(eventoId, action) {
        try {
            await fetch(`eventos.php?action=analytics&evento_id=${eventoId}&type=${action}`);
        } catch (error) {
            console.error('Erro ao registrar analytics:', error);
        }
    }
    
    // === DADOS === //
    
    async loadStats() {
        try {
            const response = await fetch('eventos.php?action=stats');
            const stats = await response.json();
            
            document.getElementById('totalEventos').textContent = stats.total_eventos || 0;
            document.getElementById('eventosAtivos').textContent = stats.eventos_ativos || 0;
            document.getElementById('totalVisualizacoes').textContent = stats.total_visualizacoes || 0;
            
            // Calcular avaliação média
            let avaliacaoMedia = 0;
            if (stats.por_categoria) {
                const total = stats.por_categoria.reduce((sum, cat) => sum + cat.total, 0);
                if (total > 0) {
                    avaliacaoMedia = (stats.total_eventos / total * 5).toFixed(1);
                }
            }
            document.getElementById('avaliacaoMedia').textContent = avaliacaoMedia;
            
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    }
    
    async loadCategorias() {
        try {
            const response = await fetch('eventos.php?action=categorias');
            const categorias = await response.json();
            
            const select = document.getElementById('categoriaFilter');
            const formSelect = document.querySelector('select[name="categoria_id"]');
            
            categorias.forEach(categoria => {
                const option = new Option(categoria.nome, categoria.id);
                select.add(option.cloneNode(true));
                formSelect.add(option);
            });
            
        } catch (error) {
            console.error('Erro ao carregar categorias:', error);
        }
    }
    
    // === UTILITÁRIOS === //
    
    showLoading() {
        document.getElementById('loadingContainer').classList.remove('d-none');
        this.hideNoResults();
    }
    
    hideLoading() {
        document.getElementById('loadingContainer').classList.add('d-none');
    }
    
    showNoResults() {
        document.getElementById('noResultsContainer').classList.remove('d-none');
        document.getElementById('eventosGrid').innerHTML = '';
        document.getElementById('eventosList').innerHTML = '';
    }
    
    hideNoResults() {
        document.getElementById('noResultsContainer').classList.add('d-none');
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
            confirmButtonText: 'OK'
        });
    }
    
    getStatusLabel(status) {
        const labels = {
            'pendente': 'Pendente',
            'aprovado': 'Aprovado',
            'desaprovado': 'Rejeitado'
        };
        return labels[status] || status;
    }
    
    animateElements(elements) {
        Array.from(elements).forEach((element, index) => {
            element.style.opacity = '0';
            element.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                element.style.transition = 'all 0.6s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
    
    renderPagination(currentPage, totalPages, totalItems) {
        const container = document.getElementById('paginationContainer');
        
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let pagination = '<ul class="pagination">';
        
        // Botão anterior
        if (currentPage > 1) {
            pagination += `
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${currentPage - 1}">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;
        }
        
        // Páginas
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            pagination += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if (startPage > 2) {
                pagination += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            pagination += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pagination += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            pagination += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }
        
        // Botão próximo
        if (currentPage < totalPages) {
            pagination += `
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${currentPage + 1}">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;
        }
        
        pagination += '</ul>';
        
        container.innerHTML = pagination;
        
        // Event listeners para paginação
        container.querySelectorAll('.page-link[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = parseInt(link.dataset.page);
                this.searchEventos();
            });
        });
    }
    
    handleScroll() {
        // Implementar scroll infinito se necessário
        const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
        
        if (scrollTop + clientHeight >= scrollHeight - 5) {
            // Próxima página
            // this.loadMoreEvents();
        }
    }
    
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + N = Novo evento
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('eventoModal'));
            modal.show();
        }
        
        // Escape = Fechar modal
        if (e.key === 'Escape') {
            const modal = bootstrap.Modal.getInstance(document.getElementById('eventoModal'));
            if (modal) {
                modal.hide();
            }
        }
        
        // Ctrl/Cmd + F = Foco na busca
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchInput').focus();
        }
    }
}

// === FUNÇÕES GLOBAIS === //

async function editEvento(id) {
    try {
        // Carregar dados do evento
        const response = await fetch(`eventos.php?action=get_evento&id=${id}`);
        const evento = await response.json();
        
        if (evento.error) {
            Swal.fire('Erro', evento.error, 'error');
            return;
        }
        
        if (evento) {
            // Preencher formulário com dados existentes
            document.getElementById('eventoId').value = evento.id;
            document.querySelector('input[name="nome"]').value = evento.nome || '';
            document.querySelector('select[name="categoria_id"]').value = evento.categoria_id || '';
            document.querySelector('input[name="data_inicio"]').value = evento.data_inicio || '';
            document.querySelector('input[name="hora_inicio"]').value = evento.hora_inicio || '';
            document.querySelector('input[name="data_termino"]').value = evento.data_termino || '';
            document.querySelector('input[name="hora_termino"]').value = evento.hora_termino || '';
            document.getElementById('eventLocal').value = evento.local || '';
            document.getElementById('eventLat').value = evento.lat || '';
            document.getElementById('eventLng').value = evento.lng || '';
            document.querySelector('textarea[name="atracoes"]').value = evento.atracoes || '';
            document.querySelector('textarea[name="descricao_evento"]').value = evento.descricao_evento || '';
            document.querySelector('input[name="tags"]').value = evento.tags || '';
            document.querySelector('select[name="faixa_etaria"]').value = evento.faixa_etaria || 'livre';
            document.querySelector('input[name="capacidade_maxima"]').value = evento.capacidade_maxima || '';
            document.querySelector('select[name="tipo_evento"]').value = evento.tipo_evento || 'presencial';
            document.querySelector('input[name="link_transmissao"]').value = evento.link_transmissao || '';
            document.querySelector('input[name="codigo_vestimenta"]').value = evento.codigo_vestimenta || '';
            document.querySelector('textarea[name="acessibilidade"]').value = evento.acessibilidade || '';
            document.querySelector('textarea[name="estacionamento"]').value = evento.estacionamento || '';
            document.querySelector('textarea[name="politica_cancelamento"]').value = evento.politica_cancelamento || '';
            document.querySelector('textarea[name="observacoes_importantes"]').value = evento.observacoes_importantes || '';
            document.querySelector('input[name="destaque"]').checked = evento.destaque == 1;
            document.querySelector('input[name="slug"]').value = evento.slug || '';
            document.querySelector('input[name="meta_title"]').value = evento.meta_title || '';
            document.querySelector('textarea[name="meta_description"]').value = evento.meta_description || '';
            
            // Verificar tipo de evento para mostrar/esconder campo de transmissão
            window.eventosManager.toggleTransmissionField(evento.tipo_evento || 'presencial');
            
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

// === INICIALIZAÇÃO === //

document.addEventListener('DOMContentLoaded', () => {
    window.eventosManager = new EventosManager();
});