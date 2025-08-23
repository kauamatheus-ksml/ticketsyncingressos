// Este é o código para o arquivo jdkfront.js referenciado na página HTML

document.addEventListener('DOMContentLoaded', function() {
    // Simulação de barra de progresso ao carregar a página
    const progressBar = document.createElement('div');
    progressBar.className = 'progress-bar';
    document.body.appendChild(progressBar);
    
    // Animar a barra de progresso
    setTimeout(() => {
      progressBar.style.width = '30%';
      setTimeout(() => {
        progressBar.style.width = '60%';
        setTimeout(() => {
          progressBar.style.width = '100%';
          setTimeout(() => {
            progressBar.style.opacity = '0';
            setTimeout(() => {
              progressBar.remove();
            }, 300);
          }, 500);
        }, 400);
      }, 300);
    }, 200);
    
    // Detectar scroll para animação do header
    const header = document.querySelector('header');
    window.addEventListener('scroll', function() {
      if (window.scrollY > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });
    
    // Adicionar classe fade-in a elementos que devem aparecer com animação
    const fadeElements = document.querySelectorAll('.evento-header, .ingresso-item, .info-section');
    fadeElements.forEach(element => {
      element.classList.add('fade-in');
    });
    
    // Observador de interseção para disparar animações quando elementos são visíveis
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
        }
      });
    }, { threshold: 0.1 });
    
    // Observar elementos com fade-in
    document.querySelectorAll('.fade-in').forEach(element => {
      observer.observe(element);
    });
    
    // Função para mostrar notificação
    window.showNotification = function(message, type = 'success') {
      const notification = document.createElement('div');
      notification.className = 'notification';
      if (type === 'error') {
        notification.classList.add('error');
      }
      notification.textContent = message;
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.classList.add('show');
        setTimeout(() => {
          notification.classList.remove('show');
          setTimeout(() => {
            notification.remove();
          }, 500);
        }, 3000);
      }, 100);
    };
    
    // Gerenciar visibilidade do resumo em dispositivos móveis
    if (window.innerWidth <= 768) {
      const resumoSection = document.querySelector('.resumo-section');
      let isVisible = false;
      
      // Adicionar botão para mostrar/ocultar resumo
      const toggleBtn = document.createElement('button');
      toggleBtn.className = 'toggle-resumo-btn';
      toggleBtn.innerHTML = 'Ver resumo <span>▲</span>';
      toggleBtn.style.position = 'fixed';
      toggleBtn.style.bottom = '10px';
      toggleBtn.style.left = '50%';
      toggleBtn.style.transform = 'translateX(-50%)';
      toggleBtn.style.zIndex = '1000';
      toggleBtn.style.background = '#FF5722';
      toggleBtn.style.color = 'white';
      toggleBtn.style.border = 'none';
      toggleBtn.style.borderRadius = '20px';
      toggleBtn.style.padding = '10px 20px';
      toggleBtn.style.display = 'none';
      document.body.appendChild(toggleBtn);
      
      // Inicialmente ocultar resumo se não houver itens
      if (document.querySelectorAll('.ingresso-item .quant-input').forEach(input => parseInt(input.value) || 0) === 0) {
        resumoSection.classList.add('hidden');
      }
      
      toggleBtn.addEventListener('click', () => {
        if (isVisible) {
          resumoSection.classList.add('hidden');
          toggleBtn.innerHTML = 'Ver resumo <span>▲</span>';
        } else {
          resumoSection.classList.remove('hidden');
          toggleBtn.innerHTML = 'Ocultar resumo <span>▼</span>';
        }
        isVisible = !isVisible;
      });
      
      // Atualizar botão de toggle quando houver alteração na quantidade
      const quantInputs = document.querySelectorAll('.quant-input');
      quantInputs.forEach(input => {
        input.addEventListener('change', function() {
          let temItens = false;
          quantInputs.forEach(inp => {
            if ((parseInt(inp.value) || 0) > 0) {
              temItens = true;
            }
          });
          
          if (temItens) {
            toggleBtn.style.display = 'block';
            resumoSection.classList.remove('hidden');
            toggleBtn.innerHTML = 'Ocultar resumo <span>▼</span>';
            isVisible = true;
          } else {
            toggleBtn.style.display = 'none';
            resumoSection.classList.add('hidden');
            isVisible = false;
          }
        });
      });
    }
    
    // Sincronizar inputs visíveis e hidden para formulário
    document.querySelectorAll('.quant-input').forEach((input, index) => {
      input.addEventListener('change', function() {
        document.getElementById('hidden_qtd_' + index).value = this.value;
      });
    });
    
    // Animação para botões do carrinho
    document.querySelectorAll('.quant-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        this.classList.add('clicked');
        setTimeout(() => {
          this.classList.remove('clicked');
        }, 200);
      });
    });
  });
  
  // Função para compartilhar evento - já definida no HTML, adicionando efeito visual extra
  const originalCompartilharEvento = window.compartilharEvento;
  window.compartilharEvento = function() {
    originalCompartilharEvento();
    
    // Adicionar efeito visual de confirmação
    const btn = document.querySelector('.btn-share');
    btn.textContent = 'Link Copiado!';
    btn.style.backgroundColor = '#4CAF50';
    
    setTimeout(() => {
      btn.textContent = 'Compartilhar Evento';
      btn.style.backgroundColor = '#3f51b5';
    }, 2000);
  };
  
  // Função para finalizar compra - já definida no HTML, adicionando efeito visual extra
  const originalFinalizarCompra = window.finalizarCompra;
  window.finalizarCompra = function() {
    // Verificar se há ingressos selecionados
    let temIngressos = false;
    document.querySelectorAll('.quant-input').forEach(input => {
      if ((parseInt(input.value) || 0) > 0) {
        temIngressos = true;
      }
    });
    
    if (!temIngressos) {
      showNotification('Selecione pelo menos um ingresso', 'error');
      return;
    }
    
    // Adicionar efeito de carregamento antes de enviar o formulário
    const btnComprar = document.querySelector('.btn-comprar');
    const textOriginal = btnComprar.textContent;
    btnComprar.innerHTML = '<div class="loading"><div></div><div></div><div></div><div></div></div>';
    btnComprar.disabled = true;
    
    setTimeout(() => {
      originalFinalizarCompra();
    }, 1000);
  };