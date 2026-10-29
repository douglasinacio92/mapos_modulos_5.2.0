<?php
/**
 * Dados do aparelho na tela da OS (Daitech), aba Resumo: incluído em
 * views/os/visualizar.php. Mostra marca, modelo, série, senha (o padrão
 * desenhado vira a grade de pontos) e a assinatura do cliente.
 *
 * @var object $os
 * @var array  $pode
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
$campos = array_filter([
    'Marca' => trim((string) ($os->marca ?? '')),
    'Modelo' => trim((string) ($os->modelo ?? '')),
    'Nº de série / IMEI' => trim((string) ($os->numero_serie ?? '')),
], static fn ($v) => $v !== '');
$senha = trim((string) ($os->senha_dispositivo ?? ''));
$padrao = daitechPontosDoPadrao($senha);
$assinatura = daitechAssinaturaValida($os->assinatura_digital ?? null);
if ($campos === [] && $senha === '' && $assinatura === null) {
    if (! ($pode['editar'] ?? false)) {
        return;
    }
}
?>
<section class="rounded-xl border border-border bg-surface p-4 sm:p-6" aria-labelledby="secao-aparelho">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="secao-aparelho" class="text-heading-sm text-text">Dados do aparelho</h2>
        <div class="flex flex-wrap gap-2">
            <?php if ($pode['editar'] ?? false) { ?>
                <?= component('button', ['label' => 'Editar', 'icon' => 'pencil', 'variant' => 'outline', 'size' => 'sm', 'href' => site_url('os/editar/' . (int) $os->idOs) . '#secao-aparelho']) ?>
            <?php } ?>
        </div>
    </div>
    <?php if ($campos === [] && $senha === '' && $assinatura === null) { ?>
        <p class="mt-3 text-body-md text-muted">Marca, modelo, série, senha e assinatura ainda não informados.</p>
    <?php } else { ?>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($campos as $rotulo => $valor) { ?>
                <div>
                    <p class="text-caption text-muted"><?= e($rotulo) ?></p>
                    <p class="text-body-md [overflow-wrap:anywhere] text-text"><?= e($valor) ?></p>
                </div>
            <?php } ?>
            <?php if ($senha !== '') { ?>
                <div>
                    <p class="text-caption text-muted">Senha de acesso</p>
                    <?php if ($padrao !== []) { ?>
                        <p class="text-body-md tabular-nums text-text"><?= e(implode(' → ', $padrao)) ?></p>
                        <div class="mt-1 grid w-fit grid-cols-3 gap-1" role="img" aria-label="<?= e('Padrão ' . implode(', ', $padrao)) ?>">
                            <?php for ($n = 1; $n <= 9; $n++) {
                                $ordem = array_search($n, $padrao, true); ?>
                                <span class="inline-flex size-6 items-center justify-center rounded-full text-[0.6875rem] font-semibold <?= $ordem === false ? 'border border-input text-muted' : 'bg-info-soft text-info-ink' ?>"><?= e($ordem === false ? '' : (string) ($ordem + 1)) ?></span>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <p class="font-mono text-body-md [overflow-wrap:anywhere] text-text"><?= e($senha) ?></p>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
        <?php if ($assinatura !== null) { ?>
            <div class="mt-4">
                <p class="text-caption text-muted">Assinatura do cliente</p>
                <img src="<?= e($assinatura) ?>" alt="Assinatura do cliente" class="mt-1 h-24 max-w-xs rounded-md border border-border object-contain dark:invert">
            </div>
        <?php } ?>
    <?php } ?>
</section>
