<?php
/**
 * Orçamentos rápidos (Daitech), no padrão das listagens do Map-OS v5: busca e
 * período na URL, data-table, ações por linha e exclusão em modal-confirm.
 *
 * @var list<object>          $results
 * @var array<string, string> $filtros
 * @var int                   $total
 * @var array                 $paginacao
 * @var array<string, bool>   $pode
 */
$temFiltro = $filtros !== [];
$totalDe = static fn ($o) => (float) $o->valor_desconto > 0 && (float) $o->valor_desconto < (float) $o->valorTotal ? (float) $o->valor_desconto : (float) $o->valorTotal;

$colunas = [
    ['label' => 'Nº', 'align' => 'right', 'nowrap' => true, 'render' => fn ($o) => $pode['editar']
        ? component('link', ['label' => '#' . $o->idVendas, 'href' => site_url('orcamentos/editar/' . $o->idVendas), 'class' => 'font-medium'])
        : '#' . $o->idVendas],
    ['label' => 'Data', 'nowrap' => true, 'render' => fn ($o) => dataBr($o->dataVenda)],
    ['label' => 'Cliente', 'class' => 'min-w-40', 'render' => fn ($o) => $o->nomeCliente ?? 'Cliente removido'],
    ['label' => 'Vendedor', 'hide_until' => 'lg', 'render' => fn ($o) => $o->vendedor ?? '—'],
    ['label' => 'Total', 'align' => 'right', 'nowrap' => true, 'render' => fn ($o) => dinheiro($totalDe($o))],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($o) use ($pode) {
        $acoes = [
            component('button', ['label' => 'Imprimir o orçamento #' . $o->idVendas, 'icon' => 'printer', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('orcamentos/imprimir/' . $o->idVendas), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]),
        ];
        if ($pode['editar']) {
            $acoes[] = component('button', ['label' => 'Editar o orçamento #' . $o->idVendas, 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('orcamentos/editar/' . $o->idVendas)]);
        }
        if ($pode['pdv']) {
            $acoes[] = component('button', ['label' => 'Finalizar o orçamento #' . $o->idVendas . ' no PDV', 'icon' => 'scan-barcode', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('pdv/index/' . $o->idVendas)]);
        }
        if ($pode['excluir']) {
            $acoes[] = component('button', ['label' => 'Excluir o orçamento #' . $o->idVendas, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => [
                'data-modal-abrir' => 'excluir-orcamento',
                'data-valor-id' => (string) $o->idVendas,
                'data-valor-nome' => '#' . $o->idVendas . ' de ' . ($o->nomeCliente ?? ''),
            ]]);
        }

        return $acoes;
    }],
];

$vazio = $temFiltro
    ? component('empty-state', ['title' => 'Nenhum orçamento encontrado', 'message' => 'Nada corresponde aos filtros.', 'icon' => 'search-x', 'action' => component('button', ['label' => 'Limpar filtros', 'variant' => 'outline', 'href' => site_url('orcamentos')]), 'class' => 'border-0'])
    : component('empty-state', ['title' => 'Nenhum orçamento em aberto', 'message' => 'Os orçamentos aparecem aqui até virarem venda.', 'icon' => 'calculator', 'action' => $pode['adicionar'] ? component('button', ['label' => 'Novo orçamento', 'icon' => 'plus', 'variant' => 'outline', 'href' => site_url('orcamentos/novo')]) : null, 'class' => 'border-0']);
?>
<div class="flex flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">Orçamentos</h1>
        <p class="text-caption text-muted" aria-live="polite">
            <?= e(($total === 1 ? '1 orçamento em aberto' : number_format($total, 0, ',', '.') . ' orçamentos em aberto') . ($temFiltro ? ' com os filtros' : '')) ?>
        </p>
    </header>

    <form method="get" action="<?= e(site_url('orcamentos')) ?>" role="search" aria-label="Filtrar orçamentos" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_auto]">
        <?= component('input', ['name' => 'pesquisa', 'label' => 'Buscar', 'type' => 'search', 'value' => $filtros['pesquisa'] ?? null, 'placeholder' => 'Nº, cliente ou documento', 'attrs' => ['maxlength' => 100]]) ?>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => site_url('orcamentos')]) ?>
            <?php } ?>
        </div>
    </form>

    <?= component('data-table', ['columns' => $colunas, 'rows' => $results, 'empty' => $vazio, 'caption' => 'Orçamentos em aberto']) ?>

    <?= component('pagination', $paginacao) ?>

    <p class="text-caption text-muted">Orçamentos convertidos viram vendas e ficam em <?= component('link', ['label' => 'Vendas', 'href' => site_url('vendas')]) ?>.</p>
</div>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-orcamento" method="post" action="<?= e(site_url('orcamentos/excluir')) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-orcamento" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-orcamento',
        'title' => 'Excluir orçamento?',
        'message' => ['O orçamento ', new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="nome"></strong>'), ' e os itens dele serão removidos. Essa ação não pode ser desfeita.'],
        'confirm_label' => 'Excluir',
        'confirm_attrs' => ['form' => 'form-excluir-orcamento'],
    ]) ?>
<?php } ?>
