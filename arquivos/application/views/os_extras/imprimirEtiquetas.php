<?php
/**
 * 3 etiquetas da OS (Daitech) para colar no aparelho, no carregador e na
 * ficha: número da OS em destaque, cliente, data de entrada e o aparelho.
 * Bobina 105x21mm (assets/daitech/etiquetas.css); abre a impressão
 * sozinho (assets/js/imprimir.js).
 *
 * @var object      $os
 * @var object|null $emitente
 */
$aparelho = daitechEquipamento($os) ?: osTextoCurto($os->descricaoProduto ?? '', 40);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= e('Etiquetas da OS ' . $os->idOs) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/daitech/etiquetas.css') . layoutVersaoAsset('assets/daitech/etiquetas.css')) ?>">
</head>
<body>
<div class="etiquetas">
    <?php for ($i = 0; $i < 3; $i++) { ?>
        <div class="etiqueta">
            <div class="etiqueta-empresa"><?= e($emitente->nome ?? 'Ordem de serviço') ?></div>
            <div class="etiqueta-numero"><?= e('OS ' . $os->idOs) ?></div>
            <div class="etiqueta-dados">
                <div class="etiqueta-linha"><b>Cliente:</b> <?= e($os->nomeCliente) ?></div>
                <div class="etiqueta-linha"><b>Entrada:</b> <?= e(dataBr($os->dataInicial)) ?></div>
                <div class="etiqueta-linha"><b>Equip:</b> <?= e($aparelho) ?></div>
            </div>
        </div>
    <?php } ?>
</div>
<script src="<?= e(base_url('assets/js/imprimir.js')) ?>"></script>
</body>
</html>
