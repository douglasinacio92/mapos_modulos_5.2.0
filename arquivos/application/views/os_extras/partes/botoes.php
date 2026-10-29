<?php
/**
 * Impressões dos módulos Daitech no cabeçalho da tela da OS (incluído em
 * views/os/visualizar.php): cupom 80mm com duas vias, termos e assinatura.
 *
 * @var int $idOs
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
?>
<?= component('button', ['label' => 'Cupom 2 vias', 'icon' => 'receipt', 'variant' => 'ghost', 'href' => site_url('os_extras/cupom/' . (int) $idOs), 'attrs' => ['target' => '_blank', 'rel' => 'noopener', 'title' => 'Cupom 80mm com a via do cliente e a da loja, termos e assinatura']]) ?>
