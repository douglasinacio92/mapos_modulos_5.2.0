<?php
/**
 * Termos e assinatura digital na impressão A4 da OS (Daitech), incluído em
 * views/os/imprimirOs.php antes do rodapé. Os termos vêm de Módulos
 * Daitech (dt_termos_os); a assinatura é a coletada no formulário da OS.
 *
 * @var object $result
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
$termos = ! isset($this->db) ? '' : trim((string) ($this->db->where('config', 'dt_termos_os')->get('configuracoes', 1)->row()->valor ?? ''));
$assinatura = daitechAssinaturaValida($result->assinatura_digital ?? null);
if ($termos === '' && $assinatura === null) {
    return;
}
?>
<?php if ($termos !== '') { ?>
    <div class="doc-subtitulo">TERMOS E CONDIÇÕES</div>
    <div class="border border-hairline-cool p-1.5 text-justify whitespace-pre-line"><?= e($termos) ?></div>
<?php } ?>
<?php if ($assinatura !== null) { ?>
    <div class="mt-2.5 flex flex-col items-center break-inside-avoid">
        <img src="<?= e($assinatura) ?>" alt="Assinatura do cliente" class="max-h-24 max-w-[60mm]">
        <span class="w-2/5 border-t border-ink pt-1 text-center">Assinatura digital do cliente</span>
    </div>
<?php } ?>
