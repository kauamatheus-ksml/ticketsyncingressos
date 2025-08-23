<?php
function exibirCarrinho($carrinho) {
    if (empty($carrinho)) {
        echo '<div class="alert alert-info text-center">Seu carrinho está vazio.</div>';
    } else {
        $subtotal = 0;
        echo '<div class="row">';
        foreach ($carrinho as $item) {
            $subtotal += $item['preco'] * $item['quantidade'];
            echo '<div class="col-md-4 mb-4">';
            echo '<div class="card h-100">';
            echo '<div class="card-body text-center">';
            echo '<h5 class="card-title">' . htmlspecialchars($item['evento_nome']) . '</h5>';
            echo '<p class="card-text">Tipo: ' . htmlspecialchars($item['tipo_ingresso']) . '</p>';
            echo '<p class="card-text">Preço: R$ ' . number_format($item['preco'], 2, ',', '.') . '</p>';
            echo '<form action="comprar_ingresso.php" method="post" class="comprar-ingresso-form">';
            echo '<input type="hidden" name="ingresso_id" value="' . $item['id'] . '">';
            echo '<input type="number" name="quantidade" value="' . $item['quantidade'] . '" min="1" class="form-control mb-2">';
            echo '<button type="submit" name="atualizar_quantidade" class="btn btn-primary btn-block">Atualizar</button>';
            echo '<button type="submit" name="remover_carrinho" class="btn btn-danger btn-block">Remover</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="comprar-ingresso-subtotal">Subtotal: R$ ' . number_format($subtotal, 2, ',', '.') . '</div>';
        echo '<form action="comprar_ingresso.php" method="post" class="text-center">';
        echo '<button type="submit" name="finalizar_compra" class="btn btn-warning">Finalizar Compra</button>';
        echo '</form>';
    }
}
?>
