<?php
/**
 * Películas (Daitech): busca de compatibilidade, no padrão das listagens do
 * Map-OS v5 (views/clientes/clientes.php). Cadastrar e editar usam o mesmo
 * modal (daitech/modal-form troca o action pelo data-form-acao do gatilho).
 *
 * @var list<object>          $results
 * @var array<string, string> $filtros
 * @var int                   $total
 * @var array                 $paginacao
 * @var array{adicionar: bool, editar: bool, excluir: bool} $pode
 */
$temFiltro = $filtros !== [];

$colunas = [
    ['label' => 'Modelo', 'class' => 'min-w-40', 'nowrap' => true, 'render' => fn ($p) => new HtmlSeguro('<span class="font-medium">' . e($p->modelo) . '</span>')],
    ['label' => 'Usa a mesma película que', 'render' => fn ($p) => $p->compativeis],
    ['label' => 'Ações', 'align' => 'right', 'nowrap' => true, 'render' => function ($p) use ($pode) {
        $acoes = [];
        if ($pode['editar']) {
            $acoes[] = component('button', ['label' => 'Editar ' . $p->modelo, 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'attrs' => [
                'data-modal-abrir' => 'pelicula-form',
                'data-form-acao' => site_url('peliculas/editar/' . (int) $p->id),
                'data-form-titulo' => 'Editar película',
                'data-valor-modelo' => $p->modelo,
                'data-valor-compativeis' => $p->compativeis,
            ]]);
        }
        if ($pode['excluir']) {
            $acoes[] = component('button', ['label' => 'Excluir ' . $p->modelo, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => [
                'data-modal-abrir' => 'excluir-pelicula',
                'data-valor-id' => (string) $p->id,
                'data-valor-nome' => $p->modelo,
            ]]);
        }

        return $acoes;
    }],
];

$vazio = $temFiltro
    ? component('empty-state', [
        'title' => 'Nenhuma película encontrada',
        'message' => 'Nada corresponde a "' . ($filtros['pesquisa'] ?? '') . '". Confira o modelo ou cadastre a compatibilidade.',
        'icon' => 'search-x',
        'action' => component('button', ['label' => 'Limpar busca', 'variant' => 'outline', 'href' => site_url('peliculas')]),
        'class' => 'border-0',
    ])
    : component('empty-state', [
        'title' => 'Nenhuma película cadastrada',
        'message' => 'Cadastre o modelo e os aparelhos que usam a mesma película.',
        'icon' => 'smartphone',
        'action' => $pode['adicionar'] ? component('button', ['label' => 'Cadastrar película', 'icon' => 'plus', 'variant' => 'outline', 'attrs' => ['data-modal-abrir' => 'pelicula-form', 'data-form-acao' => site_url('peliculas/adicionar'), 'data-form-titulo' => 'Nova película', 'data-form-limpar' => true]]) : null,
        'class' => 'border-0',
    ]);
?>
<div class="flex flex-col gap-4 pt-2 pb-8" <?= js_module('daitech/modal-form') ?>>
    <header>
        <h1 class="font-display text-heading-xl text-text">Películas</h1>
        <p class="text-caption text-muted" aria-live="polite">
            <?= e(($total === 1 ? '1 modelo' : number_format($total, 0, ',', '.') . ' modelos') . ($temFiltro ? ($total === 1 ? ' encontrado' : ' encontrados') . ' para "' . $filtros['pesquisa'] . '"' : ' cadastrados')) ?>
        </p>
    </header>

    <form method="get" action="<?= e(site_url('peliculas')) ?>" role="search" aria-label="Buscar película" class="grid items-end gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_auto]">
        <?= component('input', [
            'name' => 'pesquisa',
            'label' => 'Modelo do aparelho',
            'type' => 'search',
            'value' => $filtros['pesquisa'] ?? null,
            'placeholder' => 'Ex.: Samsung A15, Moto G9, Redmi Note 10',
            'help' => 'Busca no modelo e na lista de compatíveis.',
            'attrs' => ['maxlength' => 100, 'autofocus' => true],
        ]) ?>
        <div class="flex gap-2 sm:mb-6">
            <?= component('button', ['label' => 'Buscar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if ($temFiltro) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => site_url('peliculas')]) ?>
            <?php } ?>
        </div>
    </form>

    <?= component('data-table', [
        'columns' => $colunas,
        'rows' => $results,
        'empty' => $vazio,
        'caption' => 'Compatibilidade de películas',
    ]) ?>

    <?= component('pagination', $paginacao) ?>
</div>

<?php if ($pode['adicionar'] || $pode['editar']) { ?>
    <?= component('modal', [
        'id' => 'pelicula-form',
        'title' => 'Nova película',
        'body' => [
            new HtmlSeguro('<form id="form-pelicula" method="post" action="' . e(site_url('peliculas/adicionar')) . '" class="flex flex-col gap-4" novalidate ' . js_module('formulario/padrao') . '>'),
            new HtmlSeguro('<input type="hidden" name="' . e($this->security->get_csrf_token_name()) . '" value="' . e($this->security->get_csrf_hash()) . '">'),
            component('input', ['name' => 'modelo', 'id' => 'pelicula-modelo', 'label' => 'Modelo', 'required' => true, 'placeholder' => 'Ex.: Samsung A15', 'attrs' => ['maxlength' => 150, 'data-modal-valor' => 'modelo', 'data-msg-vazio' => 'Informe o modelo.']]),
            component('textarea', ['name' => 'compativeis', 'id' => 'pelicula-compativeis', 'label' => 'Modelos compatíveis', 'required' => true, 'rows' => 4, 'placeholder' => 'Samsung A24 / M34 / A34 / A25', 'help' => 'Separe os modelos com barra (/).', 'attrs' => ['data-modal-valor' => 'compativeis', 'data-msg-vazio' => 'Informe os modelos compatíveis.']]),
            new HtmlSeguro('</form>'),
        ],
        'footer' => [
            component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
            component('button', ['label' => 'Salvar', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['form' => 'form-pelicula', 'data-rotulo-carregando' => 'Salvando…']]),
        ],
    ]) ?>
<?php } ?>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-pelicula" method="post" action="<?= e(site_url('peliculas/excluir') . listagemQuery($filtros)) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-pelicula" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-pelicula',
        'title' => 'Excluir película?',
        'message' => [
            'A compatibilidade de ',
            new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="nome"></strong>'),
            ' será removida. Essa ação não pode ser desfeita.',
        ],
        'confirm_label' => 'Excluir',
        'confirm_attrs' => ['form' => 'form-excluir-pelicula'],
    ]) ?>
<?php } ?>
