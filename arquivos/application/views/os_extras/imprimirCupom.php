<?php
/**
 * Cupom 80mm da OS (Daitech): via do cliente e via da loja (a segunda some
 * com "2 vias" desligado nas configurações), com aparelho, senha, defeito,
 * acessórios, itens, total, assinatura digital e os termos de Módulos
 * Daitech. Classes cupom-* do assets/dist/imprimir.css.
 *
 * @var object       $os
 * @var list<object> $produtos
 * @var list<object> $servicos
 * @var float        $bruto
 * @var float        $total
 * @var list<string> $vias
 * @var object|null  $emitente
 * @var string       $termos
 */
$assinatura = daitechAssinaturaValida($os->assinatura_digital ?? null);
$texto = static fn ($html) => trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $html)), ENT_QUOTES, 'UTF-8'));
$qtd = static fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ''), '0'), ',');
$aparelho = array_filter([
    'Aparelho' => $texto($os->descricaoProduto ?? ''),
    'Marca/modelo' => daitechEquipamento($os),
    'Série/IMEI' => trim((string) ($os->numero_serie ?? '')),
]);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title><?= e('Cupom da OS ' . $os->idOs) ?></title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="<?= e(base_url('assets/dist/imprimir.css') . layoutVersaoAsset('assets/dist/imprimir.css')) ?>">
</head>
<body>
<?php foreach ($vias as $indice => $via) { ?>
    <div class="cupom">
        <div class="text-center">
            <?php if ($emitente) { ?>
                <?php if (! empty($emitente->url_logo)) { ?><img class="mx-auto max-h-[50px] grayscale" src="<?= e($emitente->url_logo) ?>" alt=""><?php } ?>
                <p class="mt-1 text-[13px] font-bold uppercase"><?= e($emitente->nome) ?></p>
                <?php if ($emitente->cnpj != '00.000.000/0000-00') { ?><p>CNPJ: <?= e($emitente->cnpj) ?></p><?php } ?>
                <p><?= e(trim($emitente->rua . ', ' . $emitente->numero . ' - ' . $emitente->bairro, ' ,-')) ?></p>
                <p>Tel: <?= e($emitente->telefone) ?></p>
            <?php } else { ?>
                <p>Configure os dados do emitente.</p>
            <?php } ?>
        </div>

        <div class="mt-1 border-t border-dashed border-ink pt-1 text-center">
            <p class="text-[15px] font-bold"><?= e('OS #' . str_pad((string) $os->idOs, 4, '0', STR_PAD_LEFT)) ?></p>
            <p class="text-[10px]"><?= e($via . ' · Entrada ' . dataBr($os->dataInicial)) ?></p>
            <p class="font-bold"><?= e('Status: ' . $os->status) ?></p>
        </div>

        <div class="mt-1 border-t border-dashed border-ink pt-1">
            <p><b>Cliente:</b> <?= e($os->nomeCliente) ?></p>
            <?php if (($os->celular_cliente ?: $os->telefone_cliente) != '') { ?><p><b>Tel:</b> <?= e($os->celular_cliente ?: $os->telefone_cliente) ?></p><?php } ?>
            <?php if ($os->dataFinal) { ?><p><b>Previsão:</b> <?= e(dataBr($os->dataFinal)) ?></p><?php } ?>
            <?php if ((int) $os->garantia > 0) { ?><p><b>Garantia:</b> <?= e((int) $os->garantia . ' dia(s)') ?></p><?php } ?>
        </div>

        <div class="mt-1 border-t border-dashed border-ink pt-1">
            <?php foreach ($aparelho as $rotulo => $valor) { ?>
                <p class="whitespace-pre-line"><b><?= e($rotulo) ?>:</b> <?= e($valor) ?></p>
            <?php } ?>
            <?php if ($indice > 0 && trim((string) ($os->senha_dispositivo ?? '')) !== '') { ?>
                <p><b>Senha:</b> <?= e($os->senha_dispositivo) ?></p>
            <?php } ?>
            <?php if ($texto($os->defeito) !== '') { ?><p class="mt-1 font-bold">DEFEITO RELATADO</p><p class="whitespace-pre-line"><?= e($texto($os->defeito)) ?></p><?php } ?>
            <?php if ($texto($os->observacoes) !== '') { ?><p class="mt-1 font-bold">ACESSÓRIOS / OBSERVAÇÕES</p><p class="whitespace-pre-line"><?= e($texto($os->observacoes)) ?></p><?php } ?>
            <?php if ($texto($os->laudoTecnico) !== '' && $os->status !== 'Aberto') { ?><p class="mt-1 font-bold">LAUDO TÉCNICO</p><p class="whitespace-pre-line"><?= e($texto($os->laudoTecnico)) ?></p><?php } ?>
        </div>

        <?php if ($produtos || $servicos) { ?>
            <table class="cupom-tabela mt-1 border-t border-dashed border-ink">
                <thead><tr class="border-b border-dashed border-ink"><th class="text-left">Qtd</th><th class="text-left">Item</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($produtos as $p) { ?>
                        <tr><td><?= e($qtd($p->quantidade)) ?>x</td><td><?= e($p->descricao) ?></td><td class="text-right tabular-nums"><?= e(dinheiro($p->subTotal)) ?></td></tr>
                    <?php } ?>
                    <?php foreach ($servicos as $s) {
                        $sub = $s->subTotal ?? (($s->preco ?: ($s->precoVenda ?? 0)) * ($s->quantidade ?: 1)); ?>
                        <tr><td><?= e($qtd($s->quantidade ?: 1)) ?>x</td><td><?= e($s->nome) ?></td><td class="text-right tabular-nums"><?= e(dinheiro($sub)) ?></td></tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>

        <div class="border-t border-dashed border-ink pt-1 text-right">
            <?php if ($bruto > 0) { ?>
                <?php if ($total < $bruto) { ?>
                    <p>Subtotal: <?= e(dinheiro($bruto)) ?></p>
                    <p>Desconto: − <?= e(dinheiro($bruto - $total)) ?></p>
                <?php } ?>
                <p class="cupom-total">TOTAL: <?= e(dinheiro($total)) ?></p>
            <?php } else { ?>
                <p class="my-2 text-center font-bold">ORÇAMENTO SUJEITO A ANÁLISE TÉCNICA</p>
            <?php } ?>
        </div>

        <div class="mt-4 text-center">
            <?php if ($assinatura !== null) { ?>
                <img src="<?= e($assinatura) ?>" alt="Assinatura do cliente" class="mx-auto -mb-2 max-h-[70px] max-w-[60mm]">
            <?php } else { ?>
                <div class="h-10"></div>
            <?php } ?>
            <div class="mx-auto w-4/5 border-t border-ink"></div>
            <p class="text-[10px]">Assinatura do cliente</p>
        </div>

        <?php if ($termos !== '') { ?>
            <div class="mt-2 text-justify text-[9px] leading-tight whitespace-pre-line"><b>TERMOS E CONDIÇÕES:</b><br><?= e($termos) ?></div>
        <?php } ?>
    </div>
<?php } ?>
<script src="<?= e(base_url('assets/js/imprimir.js')) ?>"></script>
</body>
</html>
