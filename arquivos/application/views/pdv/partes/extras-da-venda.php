<?php
/**
 * Serviços e itens avulsos da venda (Daitech), na tela da venda do Map-OS
 * (incluído em views/vendas/visualizar.php). Eles vêm do PDV e dos
 * orçamentos rápidos (vendas_itens_extras) e já entram no total da venda
 * (Vendas_model::totais()). Sem extras, não aparece nada.
 *
 * @var int $idVenda
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
$extras = isset($this->db) && $this->db->table_exists('vendas_itens_extras')
    ? $this->db->where('vendas_id', (int) $idVenda)->order_by('idExtra', 'ASC')->get('vendas_itens_extras')->result()
    : [];
if ($extras === []) {
    return;
}
$qtd = static fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ''), '0'), ',');
?>
<section aria-labelledby="titulo-extras">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 id="titulo-extras" class="text-heading-sm text-text">Serviços e itens avulsos</h2>
        <?= component('button', ['label' => 'Cupom com todos os itens', 'icon' => 'receipt', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('pdv/cupom/' . (int) $idVenda), 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]) ?>
    </div>
    <?= component('data-table', [
        'caption' => 'Serviços e itens avulsos da venda',
        'columns' => [
            ['label' => 'Tipo', 'nowrap' => true, 'render' => fn ($x) => component('pill-status', ['label' => $x->tipo === 'servico' ? 'Serviço' : 'Avulso', 'variant' => $x->tipo === 'servico' ? 'progress' : 'neutral', 'dot' => false])],
            ['label' => 'Descrição', 'render' => fn ($x) => $x->descricao],
            ['label' => 'Qtd.', 'align' => 'right', 'nowrap' => true, 'render' => fn ($x) => $qtd($x->quantidade)],
            ['label' => 'Preço', 'align' => 'right', 'nowrap' => true, 'render' => fn ($x) => dinheiro($x->preco)],
            ['label' => 'Subtotal', 'align' => 'right', 'nowrap' => true, 'render' => fn ($x) => dinheiro($x->subTotal)],
        ],
        'rows' => $extras,
    ]) ?>
    <p class="mt-2 text-right text-body-md text-text">Total de serviços e avulsos: <span class="font-medium tabular-nums"><?= e(dinheiro(array_sum(array_map(static fn ($x) => (float) $x->subTotal, $extras)))) ?></span></p>
</section>
