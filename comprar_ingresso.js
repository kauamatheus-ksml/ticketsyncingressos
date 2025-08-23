function filtrarCategoria(categoria) {
    const itens = document.querySelectorAll('.comprar-ingresso-item-categoria');

    itens.forEach(item => {
        item.style.display = (categoria === 'todos' || item.getAttribute('data-categoria') === categoria) ? 'block' : 'none';
    });
}
