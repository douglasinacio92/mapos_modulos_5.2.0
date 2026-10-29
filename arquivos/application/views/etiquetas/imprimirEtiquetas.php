<?php
/**
 * Etiquetas de produtos (Daitech): 35x21mm, 3 por fileira (bobina de
 * 105x21mm, uma fileira por página), com o nome, o preço (opcional) e o
 * código de barras do produto. CSS em assets/daitech/etiquetas.css; abre a
 * impressão sozinho (assets/js/imprimir.js).
 *
 * @var list<list<array{produto: object, barras: ?string}>> $fileiras
 * @var bool $comPreco
 */
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas de produtos</title>
    <link rel="stylesheet" href="<?= e(base_url('assets/daitech/etiquetas.css') . layoutVersaoAsset('assets/daitech/etiquetas.css')) ?>">
</head>
<body>
<?php foreach ($fileiras as $fileira) { ?>
    <div class="etiquetas">
        <?php for ($i = 0; $i < 3; $i++) {
            $etiqueta = $fileira[$i] ?? null; ?>
            <?php if ($etiqueta === null) { ?>
                <div class="etiqueta"></div>
            <?php continue;
            }
            $produto = $etiqueta['produto']; ?>
            <div class="etiqueta etiqueta-produto<?= $comPreco ? '' : ' etiqueta-sem-preco' ?>">
                <div class="etiqueta-nome"><?= e($produto->descricao) ?></div>
                <?php if ($comPreco) { ?>
                    <div class="etiqueta-preco"><?= e(dinheiro($produto->precoVenda)) ?></div>
                <?php } ?>
                <?php if ($etiqueta['barras'] !== null) { ?>
                    <img class="etiqueta-barras" src="<?= e($etiqueta['barras']) ?>" alt="<?= e('Código de barras ' . $produto->codDeBarra) ?>">
                    <div class="etiqueta-codigo"><?= e($produto->codDeBarra) ?></div>
                <?php } else { ?>
                    <div class="etiqueta-codigo"><?= e('Cód. ' . $produto->idProdutos) ?></div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
<?php } ?>
<script src="<?= e(base_url('assets/js/imprimir.js')) ?>"></script>
</body>
</html>
