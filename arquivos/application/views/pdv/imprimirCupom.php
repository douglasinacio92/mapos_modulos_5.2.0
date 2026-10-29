<?php
/**
 * Cupom 80mm da venda do PDV (Daitech): produtos, serviços e itens avulsos,
 * desconto, forma de pagamento e troco. Usa as classes cupom-* do
 * assets/dist/imprimir.css e abre a impressão sozinho (assets/js/imprimir.js).
 *
 * @var object       $venda
 * @var list<object> $produtos
 * @var list<object> $extras
 * @var object|null  $emitente
 * @var object|null  $lancamento
 */
$bruto = 0.0;
foreach ([...$produtos, ...$extras] as $item) {
    $bruto += (float) $item->subTotal;
}
$bruto = round($bruto, 2);
$total = (float) $venda->valor_desconto > 0 && (float) $venda->valor_desconto < $bruto ? (float) $venda->valor_desconto : $bruto;
$qtd = static fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ''), '0'), ',');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title><?= e('Cupom da venda #' . $venda->idVendas) ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?= e(base_url('assets/dist/imprimir.css') . layoutVersaoAsset('assets/dist/imprimir.css')) ?>">
</head>
<body>
<div class="cupom">
    <?php if ($emitente) { ?>
        <div class="text-center">
            <?php if (! empty($emitente->url_logo)) { ?><img class="mx-auto max-h-[60px] grayscale" src="<?= e($emitente->url_logo) ?>" alt=""><?php } ?>
            <p class="mt-1 text-xs font-bold uppercase"><?= e($emitente->nome) ?></p>
            <?php if ($emitente->cnpj != '00.000.000/0000-00') { ?><p>CNPJ: <?= e($emitente->cnpj) ?></p><?php } ?>
            <p><?= e(trim($emitente->rua . ', ' . $emitente->numero . ' - ' . $emitente->bairro, ' ,-')) ?></p>
            <p><?= e(trim($emitente->cidade . ' - ' . $emitente->uf, ' -')) ?></p>
            <p><?= e($emitente->telefone) ?></p>
        </div>
    <?php } else { ?>
        <p class="bg-warning-soft p-2 text-warning-ink">Configure os dados do emitente em Configurações &gt; Emitente.</p>
    <?php } ?>

    <div class="mt-2 border-t border-dashed border-ink pt-1 text-center">
        <p class="text-[13px] font-bold"><?= e('VENDA Nº ' . str_pad((string) $venda->idVendas, 4, '0', STR_PAD_LEFT)) ?></p>
        <p><?= e(dataBr($venda->dataVenda) . ' · Emissão ' . date('d/m/Y H:i')) ?></p>
        <p class="text-[10px]">Documento sem valor fiscal</p>
    </div>

    <div class="mt-1 border-t border-dashed border-ink pt-1">
        <p><b>Cliente:</b> <?= e($venda->nomeCliente ?? 'Consumidor final') ?></p>
        <?php if (! empty($venda->documento)) { ?><p><b>CPF/CNPJ:</b> <?= e($venda->documento) ?></p><?php } ?>
        <?php if (! empty($venda->vendedor)) { ?><p><b>Vendedor:</b> <?= e($venda->vendedor) ?></p><?php } ?>
    </div>

    <table class="cupom-tabela mt-1 border-t border-dashed border-ink">
        <thead>
            <tr class="border-b border-dashed border-ink"><th class="text-left">Qtd</th><th class="text-left">Item</th><th class="text-right">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $p) { ?>
                <tr>
                    <td><?= e($qtd($p->quantidade)) ?>x</td>
                    <td><?= e($p->descricao ?? 'Produto removido') ?><br><span class="text-[10px]"><?= e(dinheiro($p->preco)) ?> un.</span></td>
                    <td class="text-right tabular-nums"><?= e(dinheiro($p->subTotal)) ?></td>
                </tr>
            <?php } ?>
            <?php foreach ($extras as $x) { ?>
                <tr>
                    <td><?= e($qtd($x->quantidade)) ?>x</td>
                    <td><?= e($x->descricao) ?><br><span class="text-[10px]"><?= e(($x->tipo === 'servico' ? 'Serviço · ' : '') . dinheiro($x->preco) . ' un.') ?></span></td>
                    <td class="text-right tabular-nums"><?= e(dinheiro($x->subTotal)) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <div class="border-t border-dashed border-ink pt-1 text-right">
        <?php if ($total < $bruto) { ?>
            <p>Subtotal: <?= e(dinheiro($bruto)) ?></p>
            <p>Desconto: − <?= e(dinheiro($bruto - $total)) ?></p>
        <?php } ?>
        <p class="cupom-total">TOTAL: <?= e(dinheiro($total)) ?></p>
        <?php if ($lancamento) { ?>
            <p><b>Pagamento:</b> <?= e((string) $lancamento->forma_pgto) ?></p>
            <?php if ((int) $lancamento->baixado === 0) { ?>
                <p class="font-bold"><?= e('A PRAZO · vence em ' . dataBr($lancamento->data_vencimento)) ?></p>
            <?php } ?>
        <?php } ?>
    </div>

    <?php if ($lancamento && (int) $lancamento->baixado === 0) { ?>
        <div class="cupom-assinatura">Assinatura do cliente</div>
    <?php } ?>

    <p class="mt-3 text-center">Obrigado pela preferência!</p>
</div>
<script src="<?= e(base_url('assets/js/imprimir.js')) ?>"></script>
</body>
</html>
