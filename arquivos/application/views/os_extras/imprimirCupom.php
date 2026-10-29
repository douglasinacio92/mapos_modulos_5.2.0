<?php
/**
 * Cupom 80mm da OS (Daitech), compacto e sempre em duas vias: a do cliente
 * (com o QR para acompanhar a OS ao lado dos termos) e a da loja (com a senha
 * do aparelho, a assinatura do cliente e o QR do WhatsApp dele). Classes
 * cupom-* do assets/dist/imprimir.css.
 *
 * @var object       $os
 * @var list<object> $produtos
 * @var list<object> $servicos
 * @var float        $bruto
 * @var float        $total
 * @var list<string> $vias
 * @var object|null  $emitente
 * @var string       $termos
 * @var string|null  $qrStatus   QR do acompanhamento (acompanhar/qr/...)
 * @var string|null  $qrWhatsapp QR do WhatsApp do cliente
 */
$assinatura = daitechAssinaturaValida($os->assinatura_digital ?? null);
$texto = static fn ($html) => trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $html)), ENT_QUOTES, 'UTF-8'));
$qtd = static fn ($q) => rtrim(rtrim(number_format((float) $q, 2, ',', ''), '0'), ',');
$aparelho = array_values(array_filter([
    $texto($os->descricaoProduto ?? ''),
    daitechEquipamento($os),
    trim((string) ($os->numero_serie ?? '')) !== '' ? 'S/N ' . trim((string) $os->numero_serie) : '',
]));
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
<?php foreach ($vias as $indice => $via) {
    $loja = $indice > 0;
    $qr = $loja ? $qrWhatsapp : $qrStatus; ?>
    <div class="cupom text-[10px] leading-snug">
        <div class="text-center leading-tight">
            <?php if ($emitente) { ?>
                <?php if (! empty($emitente->url_logo)) { ?><img class="mx-auto max-h-[32px] grayscale" src="<?= e($emitente->url_logo) ?>" alt=""><?php } ?>
                <p class="text-[12px] font-bold uppercase"><?= e($emitente->nome) ?></p>
                <p class="text-[9px]"><?= e(implode(' · ', array_filter([$emitente->cnpj != '00.000.000/0000-00' ? 'CNPJ ' . $emitente->cnpj : '', $emitente->telefone ? 'Tel ' . $emitente->telefone : '']))) ?></p>
                <p class="text-[9px]"><?= e(trim($emitente->rua . ', ' . $emitente->numero . ' - ' . $emitente->bairro, ' ,-')) ?></p>
            <?php } else { ?>
                <p>Configure os dados do emitente.</p>
            <?php } ?>
        </div>

        <div class="mt-0.5 border-t border-dashed border-ink pt-0.5 text-center">
            <p><span class="text-[14px] font-bold"><?= e('OS #' . str_pad((string) $os->idOs, 4, '0', STR_PAD_LEFT)) ?></span> <span class="font-bold"><?= e('· ' . $via) ?></span></p>
            <p class="text-[9px]"><?= e(implode(' · ', array_filter(['Entrada ' . dataBr($os->dataInicial), $os->dataFinal ? 'Previsão ' . dataBr($os->dataFinal) : '', 'Status: ' . $os->status]))) ?></p>
        </div>

        <div class="mt-0.5 border-t border-dashed border-ink pt-0.5">
            <p><b>Cliente:</b> <?= e($os->nomeCliente . (($os->celular_cliente ?: $os->telefone_cliente) != '' ? ' · ' . ($os->celular_cliente ?: $os->telefone_cliente) : '')) ?></p>
            <?php if ($aparelho !== []) { ?><p><b>Aparelho:</b> <?= e(implode(' · ', $aparelho)) ?></p><?php } ?>
            <?php if ($loja && trim((string) ($os->senha_dispositivo ?? '')) !== '') { ?><p><b>Senha:</b> <?= e($os->senha_dispositivo) ?></p><?php } ?>
            <?php if ($texto($os->defeito) !== '') { ?><p class="whitespace-pre-line"><b>Defeito:</b> <?= e($texto($os->defeito)) ?></p><?php } ?>
            <?php if ($texto($os->observacoes) !== '') { ?><p class="whitespace-pre-line"><b>Acessórios:</b> <?= e($texto($os->observacoes)) ?></p><?php } ?>
            <?php if ($texto($os->laudoTecnico) !== '' && $os->status !== 'Aberto') { ?><p class="whitespace-pre-line"><b>Laudo:</b> <?= e($texto($os->laudoTecnico)) ?></p><?php } ?>
            <?php if ((int) $os->garantia > 0) { ?><p><b>Garantia:</b> <?= e((int) $os->garantia . ' dia(s)') ?></p><?php } ?>
        </div>

        <?php if ($produtos || $servicos) { ?>
            <table class="cupom-tabela my-0 border-t border-dashed border-ink text-[10px]">
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

        <div class="border-t border-dashed border-ink pt-0.5 text-right">
            <?php if ($bruto > 0) { ?>
                <?php if ($total < $bruto) { ?><p><?= e('Subtotal ' . dinheiro($bruto) . ' · Desconto − ' . dinheiro($bruto - $total)) ?></p><?php } ?>
                <p class="text-[12px] font-bold">TOTAL: <?= e(dinheiro($total)) ?></p>
            <?php } else { ?>
                <p class="text-center font-bold">ORÇAMENTO SUJEITO A ANÁLISE TÉCNICA</p>
            <?php } ?>
        </div>

        <?php if ($loja) { ?>
            <div class="mt-1 text-center">
                <?php if ($assinatura !== null) { ?>
                    <img src="<?= e($assinatura) ?>" alt="Assinatura do cliente" class="mx-auto -mb-1 max-h-[42px] max-w-[55mm]">
                <?php } else { ?>
                    <div class="h-7"></div>
                <?php } ?>
                <div class="mx-auto w-4/5 border-t border-ink"></div>
                <p class="text-[9px]">Assinatura do cliente</p>
            </div>
        <?php } ?>

        <?php if ($termos !== '' || $qr !== null) { ?>
            <div class="mt-1 flex items-start gap-1.5 border-t border-dashed border-ink pt-1">
                <?php if ($termos !== '') { ?>
                    <p class="min-w-0 flex-1 text-justify text-[8px] leading-tight whitespace-pre-line"><b>TERMOS:</b> <?= e($termos) ?></p>
                <?php } ?>
                <?php if ($qr !== null) { ?>
                    <figure class="<?= $termos !== '' ? 'w-[21mm] shrink-0' : 'mx-auto w-[24mm]' ?> text-center">
                        <img src="<?= e($qr) ?>" alt="<?= e($loja ? 'QR code do WhatsApp do cliente' : 'QR code para acompanhar a OS') ?>" class="mx-auto size-[21mm]">
                        <figcaption class="text-[7px] leading-tight font-bold"><?= e($loja ? 'WHATSAPP DO CLIENTE' : 'ACOMPANHE SUA OS') ?></figcaption>
                    </figure>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
<?php } ?>
<script src="<?= e(base_url('assets/js/imprimir.js')) ?>"></script>
</body>
</html>
