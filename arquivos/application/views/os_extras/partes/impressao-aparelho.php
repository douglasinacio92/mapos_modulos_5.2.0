<?php
/**
 * Dados do aparelho na impressão A4 da OS (Daitech), incluído em
 * views/os/imprimirOs.php antes da descrição. Sem dados, não imprime nada.
 *
 * @var object $result
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
$campos = array_filter([
    'Marca' => trim((string) ($result->marca ?? '')),
    'Modelo' => trim((string) ($result->modelo ?? '')),
    'Nº de série / IMEI' => trim((string) ($result->numero_serie ?? '')),
    'Senha' => trim((string) ($result->senha_dispositivo ?? '')),
], static fn ($v) => $v !== '');
if ($campos === []) {
    return;
}
?>
<div class="doc-subtitulo">APARELHO</div>
<table class="doc-tabela mt-0">
    <thead><tr><?php foreach (array_keys($campos) as $rotulo) { ?><th class="text-left"><?= e(mb_strtoupper($rotulo)) ?></th><?php } ?></tr></thead>
    <tbody><tr><?php foreach ($campos as $valor) { ?><td><?= e($valor) ?></td><?php } ?></tr></tbody>
</table>
