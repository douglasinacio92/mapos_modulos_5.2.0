<?php
/**
 * Carrinho do PDV e do orçamento rápido (Daitech).
 *
 * - $modo 'pdv': venda de balcão faturada na hora (pdv/finalizar), com
 *   pagamento, troco e venda a prazo; com $venda, é um orçamento levado ao
 *   caixa.
 * - $modo 'orcamento': grava o orçamento (orcamentos/salvar[/ID]).
 *
 * A tela é montada pelo módulo pdv/caixa a partir de page_data('pdv-dados'):
 * busca por nome ou código de barras (o leitor digita o código e o Enter),
 * itens com quantidade e preço editáveis, item avulso, cliente (com
 * cadastro rápido), desconto e totais. Os totais da tela são só uma prévia:
 * o servidor recalcula tudo ao gravar.
 *
 * @var string       $modo
 * @var object|null  $venda
 * @var list<array>  $carrinho
 * @var array|null   $cliente
 * @var array|null   $desconto
 * @var string|null  $observacoes
 * @var array<string, bool> $pode
 */
$pdv = $modo === 'pdv';
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-5';
$idVenda = $venda ? (int) $venda->idVendas : null;

$titulo = match (true) {
    $pdv && $venda !== null => 'Caixa · orçamento #' . $idVenda,
    $pdv => 'PDV · frente de caixa',
    $venda !== null => 'Orçamento #' . $idVenda,
    default => 'Novo orçamento',
};

$dados = [
    'modo' => $modo,
    'orcamento' => $idVenda,
    'itens' => $carrinho,
    'cliente' => $cliente,
    'desconto' => $desconto,
    'urls' => [
        'buscar' => site_url('pdv/buscar'),
        'gravar' => $pdv ? site_url('pdv/finalizar') : site_url('orcamentos/salvar' . ($idVenda ? '/' . $idVenda : '')),
        'novo' => $pdv ? site_url('pdv') : site_url('orcamentos/novo'),
    ],
];
?>
<div class="flex flex-col gap-3 pt-1 pb-4" <?= js_module('pdv/caixa') ?>>
    <?= page_data('pdv-dados', $dados) ?>

    <header class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-heading-lg text-text"><?= e($titulo) ?></h1>
            <?php if ($venda !== null) { ?>
                <?= component('pill-status', osStatusPill($venda->status)) ?>
            <?php } ?>
        </div>
        <?php if (! $pdv && $venda !== null) { ?>
            <div class="flex flex-wrap gap-1">
                <?= component('button', ['label' => 'Imprimir', 'icon' => 'printer', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('orcamentos/imprimir/' . $idVenda), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]) ?>
                <?= component('button', ['label' => 'Finalizar no PDV', 'icon' => 'scan-barcode', 'variant' => 'outline', 'size' => 'sm', 'href' => site_url('pdv/index/' . $idVenda)]) ?>
                <form method="post" action="<?= e(site_url('orcamentos/converter/' . $idVenda)) ?>" class="inline">
                    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                    <?= component('button', ['label' => 'Converter em venda', 'icon' => 'shopping-cart', 'variant' => 'ghost', 'size' => 'sm', 'type' => 'submit']) ?>
                </form>
            </div>
        <?php } ?>
    </header>

    <?php /* No computador, a tela cabe na janela: só a lista de itens rola. */ ?>
    <div class="grid gap-3 lg:h-[calc(100dvh-15.25rem)] lg:min-h-[28rem] lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="flex min-h-0 min-w-0 flex-col rounded-xl border border-border bg-surface" aria-labelledby="pdv-itens-titulo">
            <div class="flex flex-col gap-2 border-b border-border p-3">
                <div class="relative" data-autocomplete="<?= e(site_url('pdv/buscar')) ?>" data-autocomplete-alvo="pdv-busca-id" data-autocomplete-min="2" data-pdv-busca>
                    <?= component('input', [
                        'name' => 'busca',
                        'id' => 'pdv-busca',
                        'label' => 'Produto, serviço ou código de barras',
                        'hide_label' => true,
                        'type' => 'search',
                        'autocomplete' => 'off',
                        'placeholder' => 'Bipe o código de barras ou digite o produto ou serviço (Enter adiciona)',
                        'attrs' => ['autofocus' => true, 'data-msg-invalido' => 'Escolha um item da lista.'],
                    ]) ?>
                    <input type="hidden" id="pdv-busca-id" value="">
                </div>
                <details data-pdv-avulso>
                    <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-sm text-caption font-medium text-muted hover:text-text focus-visible:outline-3 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                        <?= icon('package-plus', ['class' => 'size-4']) ?>Item avulso (fora do cadastro)
                    </summary>
                    <div class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_7rem_auto] sm:items-end">
                        <?= component('input', ['name' => 'avulso_descricao', 'id' => 'pdv-avulso-descricao', 'label' => 'Descrição', 'attrs' => ['maxlength' => 255]]) ?>
                        <?= component('input', ['name' => 'avulso_quantidade', 'id' => 'pdv-avulso-quantidade', 'label' => 'Qtd.', 'value' => '1', 'attrs' => ['inputmode' => 'decimal']]) ?>
                        <?= component('input', ['name' => 'avulso_preco', 'id' => 'pdv-avulso-preco', 'label' => 'Preço (R$)', 'attrs' => ['inputmode' => 'decimal', 'placeholder' => '0,00']]) ?>
                        <?= component('button', ['label' => 'Adicionar', 'icon' => 'plus', 'variant' => 'outline', 'attrs' => ['data-pdv-avulso-adicionar' => true]]) ?>
                    </div>
                </details>
            </div>
            <div class="flex items-center justify-between gap-2 px-4 pt-2">
                <h2 id="pdv-itens-titulo" class="text-label-md text-text">Itens <span class="text-caption font-normal text-muted" data-pdv-quantidade></span></h2>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'eraser', 'variant' => 'ghost', 'size' => 'sm', 'attrs' => ['data-pdv-limpar' => true, 'hidden' => true]]) ?>
            </div>
            <div class="relative min-h-0 flex-1 overflow-y-auto">
                <div class="sticky top-0 z-10 hidden grid-cols-[minmax(0,1fr)_4.5rem_6.5rem_6.5rem_2rem] gap-3 border-b border-border bg-surface px-4 py-1.5 text-caption text-muted sm:grid" aria-hidden="true" data-pdv-cabecalho hidden>
                    <span>Item</span><span class="text-right">Qtd.</span><span class="text-right">Preço (R$)</span><span class="text-right">Subtotal</span><span></span>
                </div>
                <div data-pdv-vazio>
                    <?= component('empty-state', ['title' => 'Nenhum item ainda', 'message' => 'Os produtos e serviços adicionados aparecem aqui.', 'icon' => 'shopping-cart', 'class' => 'border-0 py-8']) ?>
                </div>
                <ol class="divide-y divide-border" data-pdv-lista aria-label="Itens do carrinho"></ol>
            </div>
        </section>

        <aside class="relative flex min-h-0 flex-col gap-2.5 rounded-xl border border-border bg-surface p-3 lg:overflow-y-auto" aria-label="<?= e($pdv ? 'Cliente, total e pagamento' : 'Cliente e total') ?>">
            <div class="relative" data-autocomplete="<?= e(site_url('os/autoCompleteCliente')) ?>" data-autocomplete-alvo="pdv-clientes-id"
                 <?= $pode['cadastrar_cliente'] ? 'data-autocomplete-novo-modal="cliente-rapido" data-autocomplete-novo-rotulo="Cadastrar novo cliente"' : '' ?> data-pdv-cliente>
                <?= component('input', [
                    'name' => 'cliente',
                    'id' => 'pdv-cliente',
                    'label' => 'Cliente',
                    'autocomplete' => 'off',
                    'value' => $cliente['nome'] ?? null,
                    'placeholder' => 'Em branco: consumidor final',
                    'attrs' => ['data-msg-invalido' => 'Escolha um cliente da lista ou deixe em branco.'],
                ]) ?>
                <input type="hidden" id="pdv-clientes-id" value="<?= e($cliente['id'] ?? '') ?>">
            </div>

            <div class="grid grid-cols-[5rem_minmax(0,1fr)] items-end gap-2">
                <?= component('select', ['name' => 'tipo_desconto', 'id' => 'pdv-tipo-desconto', 'label' => 'Desconto', 'options' => ['real' => 'R$', 'porcento' => '%'], 'selected' => $desconto['tipo'] ?? 'real']) ?>
                <?= component('input', ['name' => 'desconto', 'id' => 'pdv-desconto', 'label' => 'Valor do desconto', 'hide_label' => true, 'value' => isset($desconto['valor']) ? number_format((float) $desconto['valor'], 2, ',', '') : null, 'placeholder' => '0,00', 'attrs' => ['inputmode' => 'decimal']]) ?>
            </div>
            <p class="hidden text-caption text-danger-ink" data-pdv-erro-desconto role="alert"></p>

            <dl class="flex flex-col gap-0.5 rounded-lg bg-surface-subtle px-3 py-1.5 text-body-md">
                <div class="flex justify-between gap-2"><dt class="text-muted">Subtotal</dt><dd class="tabular-nums text-text" data-pdv-subtotal>R$ 0,00</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-muted">Desconto</dt><dd class="tabular-nums text-text" data-pdv-valor-desconto>R$ 0,00</dd></div>
                <div class="flex items-baseline justify-between gap-2 border-t border-border pt-1"><dt class="text-label-md text-text">Total</dt><dd class="font-display text-heading-md tabular-nums text-text" data-pdv-total>R$ 0,00</dd></div>
            </dl>

            <?php if ($pdv) { ?>
                <fieldset>
                    <legend class="mb-1 text-label-md text-text">Pagamento</legend>
                    <div class="grid grid-cols-4 gap-1">
                        <?php foreach (DAITECH_FORMAS_PAGAMENTO as $chave => $rotulo) { ?>
                            <label class="relative flex cursor-pointer items-center justify-center rounded-md border border-border px-1 py-1.5 text-center text-caption text-text has-checked:border-primary has-checked:bg-primary-tint has-checked:font-semibold has-focus-visible:outline-3 has-focus-visible:outline-ring">
                                <input type="radio" name="forma" value="<?= e($chave) ?>" class="sr-only" <?= $chave === 'dinheiro' ? 'checked' : '' ?>>
                                <?= e(str_replace('Cartão de ', '', $rotulo)) ?>
                            </label>
                        <?php } ?>
                    </div>
                </fieldset>
                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3" data-pdv-dinheiro>
                    <?= component('input', ['name' => 'recebido', 'id' => 'pdv-recebido', 'label' => 'Recebido (R$)', 'placeholder' => '0,00', 'attrs' => ['inputmode' => 'decimal']]) ?>
                    <p class="pb-2 text-right text-body-md"><span class="block text-caption text-muted">Troco</span><span class="font-semibold tabular-nums text-text" data-pdv-troco>R$ 0,00</span></p>
                </div>
                <div class="flex flex-col gap-2">
                    <?= component('checkbox', ['name' => 'a_prazo', 'id' => 'pdv-a-prazo', 'label' => 'Venda a prazo (caderninho)']) ?>
                    <div hidden data-pdv-vencimento>
                        <?= component('input', ['name' => 'vencimento', 'id' => 'pdv-vencimento', 'label' => 'Vencimento', 'type' => 'date', 'value' => date('Y-m-d', strtotime('+30 days'))]) ?>
                    </div>
                </div>
            <?php } else { ?>
                <?= component('textarea', ['name' => 'observacoes', 'id' => 'pdv-observacoes', 'label' => 'Observações para o cliente', 'rows' => 2, 'value' => $observacoes !== '' ? $observacoes : null, 'placeholder' => 'Saem na impressão do orçamento', 'attrs' => ['maxlength' => 5000]]) ?>
            <?php } ?>

            <div class="mt-auto pt-1">
                <?= component('button', [
                    'label' => $pdv ? 'Finalizar venda' : 'Salvar orçamento',
                    'icon' => $pdv ? 'check' : 'save',
                    'size' => 'lg',
                    'class' => 'w-full',
                    'attrs' => ['title' => 'Atalho: Ctrl + Enter', 'data-pdv-gravar' => true, 'data-rotulo-carregando' => $pdv ? 'Finalizando…' : 'Salvando…', 'aria-keyshortcuts' => 'Control+Enter'],
                ]) ?>
            </div>
        </aside>
    </div>
</div>

<?php if ($pdv) { ?>
    <?= component('modal', [
        'id' => 'pdv-concluida',
        'title' => 'Venda finalizada',
        'body' => [
            new HtmlSeguro('<p class="text-body-lg text-text" data-pdv-concluida-texto></p>'),
        ],
        'footer' => [
            component('button', ['label' => 'Ver venda', 'icon' => 'eye', 'variant' => 'ghost', 'href' => site_url('vendas'), 'attrs' => ['data-pdv-ver-venda' => true]]),
            component('button', ['label' => 'Imprimir cupom', 'icon' => 'printer', 'variant' => 'outline', 'href' => site_url('vendas'), 'attrs' => ['data-pdv-cupom' => true, 'data-imprimir' => 'cupom', 'target' => '_blank', 'rel' => 'noopener']]),
            component('button', ['label' => 'Nova venda', 'icon' => 'plus', 'variant' => 'outline', 'href' => site_url('pdv'), 'attrs' => ['data-pdv-nova' => true]]),
        ],
    ]) ?>
<?php } ?>

<?php if ($pode['cadastrar_cliente']) { ?>
    <?php $this->load->view('clientes/partes/cadastro-rapido') ?>
<?php } ?>
