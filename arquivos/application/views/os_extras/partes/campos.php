<?php
/**
 * Equipamento e serviço no formulário da OS (Daitech), incluído em
 * views/os/formulario.php no lugar da seção padrão: equipamento, marca,
 * modelo, série, senha (texto ou desenho do padrão), acessórios, defeito,
 * laudo e a assinatura do cliente, coletada na tela (dedo ou mouse). Os
 * textos do Map-OS (descricaoProduto, observacoes, defeito, laudoTecnico)
 * continuam com o nome, o valor e o erro do formulário da OS.
 *
 * Gravado por osExtrasSalvar() (helpers/daitech_helper.php), chamado em
 * Os::formulario() depois que a OS é salva. Na assinatura, "manter" deixa a
 * gravada como está; o módulo os_extras/campos troca o valor pela imagem
 * desenhada (PNG) ou por vazio ao limpar.
 *
 * @var object|null $os
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
$post = $this->input->method() === 'post' ? (array) $this->input->post() : null;
$valor = static fn (string $campo) => $post !== null ? (string) ($post[$campo] ?? '') : (string) ($os->{$campo} ?? '');
$assinaturaAtual = $os ? daitechAssinaturaValida($os->assinatura_digital ?? null) : null;
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$valoresOs = $valores ?? [];
$errosOs = $erros ?? [];
$formatadosOs = $formatados ?? [];

// Textos da OS (gravados pelo Map-OS) com a mesma altura dos campos de uma
// linha: o campo cresce sozinho enquanto se digita (field-sizing: content).
$texto = static fn (string $nome, string $label, string $exemplo) => [
    'name' => $nome,
    'label' => $label,
    'rows' => 1,
    'placeholder' => $exemplo,
    'value' => ($valoresOs[$nome] ?? '') !== '' ? $valoresOs[$nome] : null,
    'error' => $errosOs[$nome] ?? null,
    'help' => in_array($nome, $formatadosOs, true) ? 'A formatação do editor antigo sai ao salvar.' : null,
    'class' => 'field-sizing-content min-h-10 resize-none max-sm:min-h-11',
];
$linha = static fn (string $nome, string $label, string $exemplo) => ['name' => $nome, 'id' => 'os-' . str_replace('_', '-', $nome), 'label' => $label, 'value' => $valor($nome) ?: null, 'placeholder' => $exemplo, 'attrs' => ['maxlength' => 100, 'autocomplete' => 'off']];
?>
<section class="<?= e($caixa) ?>" aria-labelledby="secao-aparelho" <?= js_module('os_extras/campos') ?>>
    <h2 id="secao-aparelho" class="text-heading-sm text-text">Equipamento e serviço</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <?= component('textarea', $texto('descricaoProduto', 'Equipamento', 'Ex.: Notebook, celular, impressora')) ?>
        <?= component('input', $linha('marca', 'Marca', 'Ex.: Dell, Samsung')) ?>
        <?= component('input', $linha('modelo', 'Modelo', 'Ex.: Inspiron 15, Galaxy A15')) ?>
        <?= component('input', $linha('numero_serie', 'Nº de série / IMEI', 'Número de série ou IMEI')) ?>
        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2">
            <?= component('input', ['name' => 'senha_dispositivo', 'id' => 'os-senha', 'label' => 'Senha de acesso', 'value' => $valor('senha_dispositivo') ?: null, 'placeholder' => 'Texto, PIN ou padrão', 'attrs' => ['maxlength' => 100, 'autocomplete' => 'off', 'data-os-senha' => true]]) ?>
            <?= component('button', ['label' => 'Desenhar padrão', 'icon' => 'lock-keyhole', 'icon_only' => true, 'variant' => 'outline', 'class' => 'h-10 max-sm:h-11', 'attrs' => ['data-modal-abrir' => 'os-padrao', 'title' => 'Desenhar a senha de padrão']]) ?>
        </div>
        <?= component('textarea', $texto('observacoes', 'Acessórios / observações', 'Ex.: fonte, capa, chip')) ?>
        <?= component('textarea', $texto('defeito', 'Defeito relatado', 'O que o cliente relatou')) ?>
        <?= component('textarea', $texto('laudoTecnico', 'Laudo técnico', 'Diagnóstico e o que foi feito')) ?>
    </div>

    <fieldset class="mt-6 flex flex-col gap-2">
        <legend class="text-label-md text-text">Assinatura do cliente</legend>
        <p class="text-caption text-muted">Na entrada do aparelho ou na aprovação do orçamento. Assine com o dedo ou o mouse.</p>
        <div class="relative w-full max-w-lg">
            <canvas data-assinatura-tela width="600" height="200" class="block h-40 w-full touch-none rounded-lg border border-dashed border-input bg-field dark:bg-transparent dark:invert" aria-label="Área para a assinatura"></canvas>
            <?php if ($assinaturaAtual !== null) { ?>
                <img src="<?= e($assinaturaAtual) ?>" alt="Assinatura gravada" class="pointer-events-none absolute inset-0 h-40 w-full rounded-lg object-contain dark:invert" data-assinatura-atual>
            <?php } ?>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?= component('button', ['label' => 'Limpar assinatura', 'icon' => 'eraser', 'variant' => 'ghost', 'size' => 'sm', 'attrs' => ['data-assinatura-limpar' => true]]) ?>
            <span class="text-caption text-muted" data-assinatura-estado><?= e($assinaturaAtual !== null ? 'Assinatura gravada. Desenhe por cima para trocar.' : 'Sem assinatura.') ?></span>
        </div>
        <input type="hidden" name="assinatura_digital" value="manter" data-assinatura-valor>
    </fieldset>

    <?= component('modal', [
        'id' => 'os-padrao',
        'title' => 'Senha de padrão',
        'size' => 'sm',
        'body' => [
            new HtmlSeguro('<p class="text-caption text-muted">Toque nos pontos na ordem do desenho.</p>'),
            new HtmlSeguro('<p class="mt-2 min-h-7 text-center font-display text-heading-sm tabular-nums text-text" data-padrao-sequencia aria-live="polite"></p>'),
            new HtmlSeguro('<div class="mx-auto mt-3 grid w-fit grid-cols-3 gap-4 rounded-xl bg-surface-subtle p-5" role="group" aria-label="Pontos do padrão">'
                . implode('', array_map(static fn ($n) => '<button type="button" data-padrao-ponto="' . $n . '" aria-pressed="false" class="inline-flex size-14 items-center justify-center rounded-full border-2 border-input bg-surface text-heading-sm font-semibold text-text aria-pressed:border-primary aria-pressed:bg-primary-tint focus-visible:outline-3 focus-visible:outline-ring">' . $n . '</button>', range(1, 9)))
                . '</div>'),
        ],
        'footer' => [
            component('button', ['label' => 'Limpar', 'icon' => 'eraser', 'variant' => 'ghost', 'attrs' => ['data-padrao-limpar' => true]]),
            component('button', ['label' => 'Usar este padrão', 'icon' => 'check', 'variant' => 'outline', 'attrs' => ['data-padrao-confirmar' => true]]),
        ],
    ]) ?>
</section>
