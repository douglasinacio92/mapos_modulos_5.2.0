<?php
/**
 * Acompanhamento público da OS (Daitech), na superfície de entrada do
 * Map-OS v5 (views/entrada/inicio.php e fim.php). Sem documento, pede o
 * CPF/CNPJ; com a OS conferida, mostra só o resumo.
 *
 * @var int         $idOs
 * @var object|null $os
 * @var string|null $erro
 * @var object|null $emitente
 * @var float|null  $total
 * @var string      $documento
 * @var string|null $modo      'celular' no link do QR do cupom
 * @var string|null $acao      URL do formulário do celular
 */
$modo ??= 'documento';
$entrada = [
    'titulo' => $idOs > 0 ? 'Acompanhar a OS ' . $idOs : 'Acompanhar OS',
    'secundaria' => ['label' => 'Área do cliente', 'href' => site_url('mine'), 'icon' => 'circle-user'],
];
include APPPATH . 'views/entrada/inicio.php';
$texto = static fn ($html) => trim(html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $html)), ENT_QUOTES, 'UTF-8'));
?>
<main class="mx-auto grid w-full max-w-2xl flex-1 items-start px-4 py-6 lg:py-12">
    <section class="superficie-entrada w-full rounded-xxl border border-hairline-violet bg-night p-6 text-text sm:p-8" aria-labelledby="titulo-acompanhar">
        <?php if ($os === null) { ?>
            <h1 id="titulo-acompanhar" class="font-display text-heading-md text-text"><?= e($modo === 'celular' ? 'Acompanhe a OS #' . $idOs : 'Acompanhe sua ordem de serviço') ?></h1>
            <p class="mt-1 text-body-md text-muted"><?= e($modo === 'celular' ? 'Para sua segurança, confirme o celular informado no cadastro.' : 'Informe o número da OS e o CPF ou CNPJ do cadastro.') ?></p>
            <?php if ($erro !== null) { ?>
                <div class="mt-4"><?= component('alert', ['variant' => 'danger', 'message' => $erro]) ?></div>
            <?php } ?>
            <?php if ($modo === 'celular') { ?>
            <form method="post" action="<?= e($acao) ?>" class="mt-6 flex flex-col gap-4" novalidate <?= js_module('formulario/padrao') ?>>
                <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
                <?= component('input', ['name' => 'celular', 'label' => 'Celular com DDD', 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel-national', 'placeholder' => '(51) 99999-9999', 'attrs' => ['data-mascara' => 'telefone', 'maxlength' => 20, 'autofocus' => true]]) ?>
                <?= component('button', ['label' => 'Ver minha OS', 'icon' => 'search', 'type' => 'submit', 'size' => 'lg', 'class' => 'w-full']) ?>
            </form>
            <?php } else { ?>
            <form method="get" action="<?= e(site_url('acompanhar/os')) ?>" class="mt-6 flex flex-col gap-4" novalidate <?= js_module('formulario/padrao') ?>>
                <?= component('input', ['name' => 'numero', 'label' => 'Número da OS', 'required' => true, 'value' => $idOs > 0 ? (string) $idOs : null, 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 10]]) ?>
                <?= component('input', ['name' => 'doc', 'label' => 'CPF ou CNPJ', 'required' => true, 'value' => $documento !== '' ? $documento : null, 'autocomplete' => 'off', 'attrs' => ['data-mascara' => 'documento', 'maxlength' => 20]]) ?>
                <?= component('button', ['label' => 'Consultar', 'icon' => 'search', 'type' => 'submit', 'size' => 'lg', 'class' => 'w-full']) ?>
            </form>
            <?php } ?>
        <?php } else { ?>
            <div class="flex flex-wrap items-center gap-3">
                <h1 id="titulo-acompanhar" class="font-display text-heading-lg text-text"><?= e('OS #' . $os->idOs) ?></h1>
                <?= component('pill-status', osStatusPill($os->status)) ?>
            </div>
            <p class="mt-1 text-body-md text-muted"><?= e($os->nomeCliente) ?></p>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-caption text-muted">Entrada</dt><dd class="text-body-md text-text"><?= e(dataBr($os->dataInicial)) ?></dd></div>
                <div><dt class="text-caption text-muted">Previsão de entrega</dt><dd class="text-body-md text-text"><?= e($os->dataFinal ? dataBr($os->dataFinal) : '—') ?></dd></div>
                <?php $aparelho = trim(implode(' · ', array_filter([$texto($os->descricaoProduto), daitechEquipamento($os)]))); ?>
                <?php if ($aparelho !== '') { ?>
                    <div class="sm:col-span-2"><dt class="text-caption text-muted">Aparelho</dt><dd class="text-body-md [overflow-wrap:anywhere] text-text"><?= e($aparelho) ?></dd></div>
                <?php } ?>
                <?php if ($texto($os->defeito) !== '') { ?>
                    <div class="sm:col-span-2"><dt class="text-caption text-muted">Defeito relatado</dt><dd class="text-body-md whitespace-pre-line [overflow-wrap:anywhere] text-text"><?= e($texto($os->defeito)) ?></dd></div>
                <?php } ?>
                <?php if ($texto($os->laudoTecnico) !== '' && $os->status !== 'Aberto') { ?>
                    <div class="sm:col-span-2"><dt class="text-caption text-muted">Laudo técnico</dt><dd class="text-body-md whitespace-pre-line [overflow-wrap:anywhere] text-text"><?= e($texto($os->laudoTecnico)) ?></dd></div>
                <?php } ?>
                <?php if (($total ?? 0) > 0) { ?>
                    <div><dt class="text-caption text-muted">Valor</dt><dd class="font-display text-heading-md tabular-nums text-text"><?= e(dinheiro($total)) ?></dd></div>
                <?php } ?>
            </dl>

            <?php if ($emitente) { ?>
                <p class="mt-6 border-t border-border pt-4 text-caption text-muted">
                    <?= e('Dúvidas? ' . $emitente->nome . ($emitente->telefone ? ' · ' . $emitente->telefone : '') . ($emitente->email ? ' · ' . $emitente->email : '')) ?>
                </p>
            <?php } ?>
            <div class="mt-4 flex flex-wrap gap-2">
                <?= component('button', ['label' => 'Entrar na área do cliente', 'icon' => 'log-in', 'variant' => 'outline', 'href' => site_url('mine')]) ?>
            </div>
        <?php } ?>
    </section>
</main>
<?php include APPPATH . 'views/entrada/fim.php'; ?>
