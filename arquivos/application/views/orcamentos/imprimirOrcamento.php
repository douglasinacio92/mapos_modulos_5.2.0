<?php
/**
 * Orçamento em A4 (Daitech): produtos, serviços e itens avulsos, desconto,
 * validade e observações para o cliente. Classes doc-* do
 * assets/dist/imprimir.css, como as impressões de venda do Map-OS.
 *
 * @var object       $venda
 * @var list<object> $produtos
 * @var list<object> $extras
 * @var float        $bruto
 * @var float        $total
 * @var object|null  $emitente
 * @var string       $validade
 */
$qtd = static fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ''), '0'), ',');
$linhas = [];
foreach ($produtos as $p) {
    $linhas[] = ['Produto', $p->descricao ?? 'Produto removido', $p->quantidade, $p->preco, $p->subTotal];
}
foreach ($extras as $x) {
    $linhas[] = [$x->tipo === 'servico' ? 'Serviço' : 'Item', $x->descricao, $x->quantidade, $x->preco, $x->subTotal];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title><?= e('Orçamento #' . $venda->idVendas . ' - ' . ($venda->nomeCliente ?? '')) ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?= e(base_url('assets/dist/imprimir.css') . layoutVersaoAsset('assets/dist/imprimir.css')) ?>">
</head>
<body>
<div class="doc-folha">
    <header class="doc-cabecalho">
        <?php if ($emitente == null) { ?>
            <div class="w-full bg-danger-soft p-2 text-danger-ink" role="alert">Configure os dados do emitente em Configurações &gt; Emitente.</div>
        <?php } else { ?>
            <div class="flex items-center"><img src="<?= e($emitente->url_logo) ?>" class="h-auto w-[140px] max-w-full" alt=""></div>
            <div class="mt-0.5 px-2.5">
                <span class="text-base"><b><?= e($emitente->nome) ?></b></span><br>
                <?php if ($emitente->cnpj != '00.000.000/0000-00') { ?><span>CNPJ: <?= e($emitente->cnpj) ?></span><br><?php } ?>
                <span><?= e($emitente->rua . ', ' . $emitente->numero . ', ' . $emitente->bairro) ?><br><?= e($emitente->cidade . ' - ' . $emitente->uf . ' - ' . $emitente->cep) ?></span>
            </div>
            <div class="mt-2.5 max-w-[230px] text-right">
                <span class="font-bold">Tel: <?= e($emitente->telefone) ?></span><br>
                <span class="font-bold"><?= e($emitente->email) ?></span>
            </div>
        <?php } ?>
    </header>

    <section class="w-full py-2.5">
        <div class="doc-titulo">
            <?= e('ORÇAMENTO Nº ' . str_pad((string) $venda->idVendas, 4, '0', STR_PAD_LEFT)) ?>
            <span class="absolute right-1 text-[13px]">Emissão: <?= e(date('d/m/Y H:i')) ?></span>
        </div>

        <table class="doc-tabela">
            <thead><tr><th class="text-center">DATA</th><th class="text-center">VALIDADE</th><th class="text-center">VENDEDOR</th></tr></thead>
            <tbody><tr>
                <td class="text-center"><?= e(dataBr($venda->dataVenda)) ?></td>
                <td class="text-center"><?= e($validade !== '' ? $validade : '—') ?></td>
                <td class="text-center"><?= e($venda->vendedor ?? '—') ?></td>
            </tr></tbody>
        </table>

        <div class="doc-subtitulo">CLIENTE</div>
        <div class="border border-hairline-cool p-2">
            <b><?= e($venda->nomeCliente ?? '') ?></b>
            <?php $contato = array_filter([$venda->documento ?? '', $venda->celular ?: ($venda->telefone ?? ''), $venda->email ?? '']); ?>
            <?php if ($contato) { ?><br><?= e(implode(' · ', $contato)) ?><?php } ?>
            <?php $endereco = trim(implode(', ', array_filter([$venda->rua ?? '', $venda->numero ?? '', $venda->bairro ?? '', trim(($venda->cidade ?? '') . ' - ' . ($venda->estado ?? ''), ' -')]))); ?>
            <?php if ($endereco !== '') { ?><br><?= e($endereco) ?><?php } ?>
        </div>

        <div class="doc-subtitulo">ITENS</div>
        <table class="doc-tabela">
            <thead><tr><th class="text-left">TIPO</th><th class="text-left">DESCRIÇÃO</th><th class="text-center">QTD.</th><th class="text-right">UNITÁRIO</th><th class="text-right">SUBTOTAL</th></tr></thead>
            <tbody>
                <?php foreach ($linhas as [$tipo, $descricao, $quantidade, $preco, $subtotal]) { ?>
                    <tr>
                        <td><?= e($tipo) ?></td>
                        <td><?= e($descricao) ?></td>
                        <td class="text-center"><?= e($qtd($quantidade)) ?></td>
                        <td class="text-right"><?= e(dinheiro($preco)) ?></td>
                        <td class="text-right"><?= e(dinheiro($subtotal)) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <?php if ($total < $bruto) { ?>
                    <tr><td colspan="4" class="text-right">Subtotal</td><td class="text-right"><?= e(dinheiro($bruto)) ?></td></tr>
                    <tr><td colspan="4" class="text-right">Desconto</td><td class="text-right">− <?= e(dinheiro($bruto - $total)) ?></td></tr>
                <?php } ?>
                <tr><td colspan="4" class="text-right font-bold">TOTAL</td><td class="text-right text-base font-bold"><?= e(dinheiro($total)) ?></td></tr>
            </tfoot>
        </table>

        <?php if (trim((string) $venda->observacoes_cliente) !== '') { ?>
            <div class="doc-subtitulo">OBSERVAÇÕES</div>
            <div class="border border-hairline-cool p-2 whitespace-pre-line"><?= e($venda->observacoes_cliente) ?></div>
        <?php } ?>

        <div class="doc-rodape mt-2.5">
            <?php if ($validade !== '') { ?><p><?= e('• Os valores deste orçamento valem por ' . $validade . '.') ?></p><?php } ?>
            <p>• Produtos sujeitos à disponibilidade de estoque na aprovação.</p>
        </div>

        <div class="flex justify-around">
            <div class="doc-assinatura">Assinatura do cliente</div>
            <div class="doc-assinatura"><?= e($emitente->nome ?? 'Empresa') ?></div>
        </div>
    </section>
</div>
<script src="<?= e(base_url('assets/js/imprimir.js')) ?>"></script>
</body>
</html>
