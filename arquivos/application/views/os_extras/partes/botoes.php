<?php
/**
 * Impressões dos módulos Daitech no cabeçalho da tela da OS (incluído em
 * views/os/visualizar.php): cupom 80mm em duas vias e as etiquetas.
 *
 * @var int $idOs
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
?>
<?= component('button', ['label' => 'Cupom 2 vias', 'icon' => 'receipt', 'variant' => 'ghost', 'href' => site_url('os_extras/cupom/' . (int) $idOs), 'attrs' => ['target' => '_blank', 'rel' => 'noopener', 'data-imprimir' => 'cupom', 'title' => 'Cupom 80mm: via do cliente e via da loja, com termos, assinatura e QR codes']]) ?>
<?= component('button', ['label' => 'Etiquetas (3x)', 'icon' => 'tag', 'variant' => 'ghost', 'href' => site_url('os_extras/etiquetas/' . (int) $idOs), 'attrs' => ['target' => '_blank', 'rel' => 'noopener', 'data-imprimir' => 'etiqueta', 'title' => 'Três etiquetas 35x21mm']]) ?>
