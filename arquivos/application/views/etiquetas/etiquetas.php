<?php
/**
 * Etiquetas de produtos (Daitech): busca o produto pelo nome ou pelo código
 * de barras (o leitor manda o código e um Enter), escolhe quantas etiquetas
 * de cada um e se o preço sai. A lista fica no navegador
 * (assets/js/modules/etiquetas/tela.js), que monta o link da impressão.
 */
?>
<div class="flex flex-col gap-4 pt-2 pb-8" <?= js_module('etiquetas/tela') ?> data-buscar="<?= e(site_url('etiquetas/buscar')) ?>" data-imprimir-url="<?= e(site_url('etiquetas/imprimir')) ?>">
    <header>
        <h1 class="font-display text-heading-xl text-text">Etiquetas de produtos</h1>
        <p class="text-caption text-muted">Etiquetas de 35x21mm, 3 por fileira, com nome, preço e código de barras.</p>
    </header>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="flex min-w-0 flex-col rounded-xl border border-border bg-surface" aria-labelledby="etiquetas-lista-titulo">
            <div class="border-b border-border p-4">
                <div class="relative" data-autocomplete="<?= e(site_url('etiquetas/buscar')) ?>" data-autocomplete-alvo="etiquetas-busca-id" data-autocomplete-min="2" data-etiquetas-busca>
                    <?= component('input', [
                        'name' => 'busca',
                        'id' => 'etiquetas-busca',
                        'label' => 'Produto ou código de barras',
                        'type' => 'search',
                        'autocomplete' => 'off',
                        'placeholder' => 'Bipe o código de barras ou digite o nome do produto',
                        'attrs' => ['autofocus' => true, 'data-msg-invalido' => 'Escolha um produto da lista.'],
                    ]) ?>
                    <input type="hidden" id="etiquetas-busca-id" value="">
                </div>
            </div>
            <div class="flex items-center justify-between gap-2 px-4 pt-3">
                <h2 id="etiquetas-lista-titulo" class="text-label-md text-text">Produtos <span class="text-caption font-normal text-muted" data-etiquetas-resumo></span></h2>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'eraser', 'variant' => 'ghost', 'size' => 'sm', 'attrs' => ['data-etiquetas-limpar' => true, 'hidden' => true]]) ?>
            </div>
            <div data-etiquetas-vazio>
                <?= component('empty-state', ['title' => 'Nenhum produto na lista', 'message' => 'Bipe ou busque os produtos que vão receber etiqueta.', 'icon' => 'tag', 'class' => 'border-0 py-8']) ?>
            </div>
            <ol class="divide-y divide-border" data-etiquetas-lista aria-label="Produtos das etiquetas"></ol>
        </section>

        <aside class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4" aria-label="Impressão">
            <?= component('switch', ['name' => 'preco', 'id' => 'etiquetas-preco', 'label' => 'Mostrar o preço', 'checked' => true, 'help' => 'Desligado, o código de barras fica maior.']) ?>
            <p class="text-body-md text-text" data-etiquetas-total aria-live="polite">Nenhuma etiqueta</p>
            <?= component('button', [
                'label' => 'Imprimir etiquetas',
                'icon' => 'printer',
                'size' => 'lg',
                'class' => 'w-full',
                'href' => site_url('etiquetas/imprimir'),
                'attrs' => ['target' => '_blank', 'rel' => 'noopener', 'data-imprimir' => 'etiqueta', 'data-etiquetas-imprimir' => true, 'aria-disabled' => 'true'],
            ]) ?>
            <p class="text-caption text-muted">Até 300 etiquetas por impressão. A última fileira é completada com etiquetas em branco.</p>
        </aside>
    </div>
</div>
