<?php
/**
 * Kanban (Daitech): OS, visitas e tarefas em colunas por status.
 *
 * - Resumo no topo (kpi-card), abas por link (?aba=) e filtros na URL.
 * - Colunas renderizadas aqui; o módulo kanban/quadro arrasta os cartões
 *   entre as colunas e grava em kanban/mover. Cada cartão também tem o
 *   select "Mover para", que funciona no teclado e no celular.
 * - Cada coluna mostra até Kanban_model::POR_COLUNA cartões; o total fica no
 *   cabeçalho e, nas OS, o "ver todas" leva à listagem filtrada.
 *
 * @var string        $aba
 * @var array         $filtros
 * @var string        $hoje
 * @var array         $pode
 * @var array         $estatisticas
 * @var array<string, array{total: int, itens: list<object>}> $colunas
 * @var array<string, string>|null $usuarios
 * @var list<string>  $travados
 */
$base = site_url('kanban');
$query = static fn (array $extra) => $base . listagemQuery(array_filter($extra + ['pesquisa' => $filtros['pesquisa'] ?? null, 'minhas' => $filtros['minhas'] ?? null], static fn ($v) => $v !== null && $v !== ''));
$podeMover = match ($aba) {
    'os' => $pode['mover_os'],
    'agendamentos' => $pode['mover_agendamentos'],
    default => true,
};
$opcoesStatus = match ($aba) {
    'os' => array_combine(array_keys($colunas), array_keys($colunas)),
    'agendamentos' => array_combine(array_keys(DAITECH_AGENDAMENTO_STATUS), array_map(static fn ($s) => daitechAgendamentoPill($s)['label'], array_keys(DAITECH_AGENDAMENTO_STATUS))),
    default => array_combine(array_keys(DAITECH_TAREFA_STATUS), array_map(static fn ($s) => ucfirst(mb_strtolower($s)), array_keys(DAITECH_TAREFA_STATUS))),
};
$rotuloColuna = static fn (string $s) => $opcoesStatus[$s] ?? $s;
$varianteColuna = static fn (string $s) => match ($aba) {
    'os' => OS_STATUS_VARIANTES[$s] ?? 'neutral',
    'agendamentos' => DAITECH_AGENDAMENTO_STATUS[$s] ?? 'neutral',
    default => DAITECH_TAREFA_STATUS[$s] ?? 'neutral',
};
$csrf = new HtmlSeguro('<input type="hidden" name="' . e($this->security->get_csrf_token_name()) . '" value="' . e($this->security->get_csrf_hash()) . '">');
$e = $estatisticas;
?>
<div class="flex min-w-0 flex-col gap-4 pt-2 pb-8" <?= js_module('kanban/quadro') ?> data-mover-url="<?= e(site_url('kanban/mover')) ?>" data-tipo="<?= e($aba) ?>" data-dados-url="<?= e(site_url('kanban/dados')) ?>">
    <header class="flex flex-col gap-1" <?= js_module('daitech/modal-form') ?>>
        <h1 class="font-display text-heading-xl text-text">Kanban</h1>
        <p class="text-caption text-muted">Arraste os cartões entre as colunas ou use "Mover para" em cada cartão.</p>
    </header>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <?= component('kpi-card', ['label' => 'OS em aberto', 'value' => $e['os_abertas'], 'icon' => 'wrench', 'accent' => 'progress', 'href' => $pode['os'] ? $query(['aba' => 'os']) : null, 'attrs' => ['data-kanban-kpi' => 'os_abertas']]) ?>
        <?= component('kpi-card', ['label' => 'OS atrasadas', 'value' => $e['os_atrasadas'], 'icon' => 'triangle-alert', 'accent' => $e['os_atrasadas'] > 0 ? 'warning' : 'neutral', 'caption' => 'Data final já passou', 'attrs' => ['data-kanban-kpi' => 'os_atrasadas']]) ?>
        <?= component('kpi-card', ['label' => 'Vencendo', 'value' => $e['os_vencendo'], 'icon' => 'history', 'accent' => 'info', 'caption' => 'Hoje ou amanhã', 'attrs' => ['data-kanban-kpi' => 'os_vencendo']]) ?>
        <?= component('kpi-card', ['label' => 'Visitas hoje', 'value' => $e['visitas_hoje'], 'icon' => 'calendar-days', 'accent' => 'info', 'href' => $pode['agendamentos'] ? site_url('agendamentos') : null, 'attrs' => ['data-kanban-kpi' => 'visitas_hoje']]) ?>
        <?= component('kpi-card', ['label' => 'Tarefas pendentes', 'value' => $e['tarefas'], 'icon' => 'list-checks', 'accent' => 'neutral', 'href' => $query(['aba' => 'tarefas']), 'attrs' => ['data-kanban-kpi' => 'tarefas']]) ?>
    </div>

    <?= component('tabs', [
        'label' => 'Quadros do Kanban',
        'items' => array_values(array_filter([
            $pode['os'] ? ['label' => 'Ordens de serviço', 'short' => 'OS', 'url' => $query(['aba' => 'os']), 'active' => $aba === 'os', 'icon' => 'wrench'] : null,
            $pode['agendamentos'] ? ['label' => 'Agenda externa', 'short' => 'Agenda', 'url' => $query(['aba' => 'agendamentos']), 'active' => $aba === 'agendamentos', 'icon' => 'calendar-days'] : null,
            ['label' => 'Tarefas internas', 'short' => 'Tarefas', 'url' => $query(['aba' => 'tarefas']), 'active' => $aba === 'tarefas', 'icon' => 'list-checks'],
        ])),
    ]) ?>

    <form method="get" action="<?= e($base) ?>" role="search" aria-label="Filtrar o quadro" class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-surface p-4">
        <input type="hidden" name="aba" value="<?= e($aba) ?>">
        <?= component('input', [
            'name' => 'pesquisa',
            'label' => 'Buscar',
            'type' => 'search',
            'value' => $filtros['pesquisa'] ?? null,
            'placeholder' => $aba === 'os' ? 'Nº da OS, cliente, equipamento ou defeito' : ($aba === 'agendamentos' ? 'Cliente, endereço ou descrição' : 'Título ou descrição'),
            'class' => 'min-w-64',
            'attrs' => ['maxlength' => 100],
        ]) ?>
        <div class="pb-2">
            <?= component('checkbox', ['name' => 'minhas', 'label' => $aba === 'tarefas' ? 'Só as minhas' : ($aba === 'os' ? 'Só as minhas (técnico)' : 'Só as que eu agendei'), 'checked' => isset($filtros['minhas'])]) ?>
        </div>
        <div class="flex gap-2">
            <?= component('button', ['label' => 'Filtrar', 'icon' => 'search', 'variant' => 'outline', 'type' => 'submit']) ?>
            <?php if (isset($filtros['pesquisa']) || isset($filtros['minhas'])) { ?>
                <?= component('button', ['label' => 'Limpar', 'icon' => 'x', 'variant' => 'ghost', 'href' => $base . listagemQuery(['aba' => $aba])]) ?>
            <?php } ?>
            <?= component('button', ['label' => 'Atualizar', 'icon' => 'refresh-cw', 'variant' => 'ghost', 'href' => $query(['aba' => $aba])]) ?>
        </div>
    </form>

    <div class="relative -mx-4 overflow-x-auto px-4 pb-2 lg:-mx-6 lg:px-6">
        <div class="flex min-w-max items-start gap-3">
            <?php foreach ($colunas as $status => $coluna) { ?>
                <section class="flex w-72 shrink-0 flex-col rounded-xl border border-border bg-surface-subtle" aria-label="<?= e($rotuloColuna($status)) ?>" data-kanban-coluna="<?= e($status) ?>" <?= $aba === 'os' && in_array($status, $travados, true) ? 'data-kanban-travada' : '' ?>>
                    <header class="flex items-center justify-between gap-2 border-b border-border px-3 py-2.5">
                        <?= component('pill-status', ['label' => $rotuloColuna($status), 'variant' => $varianteColuna($status)]) ?>
                        <span class="text-caption tabular-nums text-muted"><span data-kanban-contador><?= e((string) $coluna['total']) ?></span><span class="sr-only"> cartões</span></span>
                    </header>
                    <ol class="flex max-h-[70vh] min-h-24 flex-col gap-2 overflow-y-auto p-2" data-kanban-lista>
                        <?php foreach ($coluna['itens'] as $item) {
                            if ($aba === 'os') {
                                $id = (int) $item->idOs;
                                $prazo = daitechPrazo($item->dataFinal, $item->status, ['finalizado', 'faturado', 'cancelado'], $hoje);
                                $equipamento = daitechEquipamento($item) ?: osTextoCurto($item->descricaoProduto, 60);
                                $titulo = 'OS #' . $id;
                                $movivel = $podeMover && ! in_array($status, $travados, true);
                            } elseif ($aba === 'agendamentos') {
                                $id = (int) $item->idAgendamento;
                                $prazo = daitechPrazo($item->data_agendamento, $item->status, ['concluido', 'cancelado'], $hoje);
                                $titulo = dataBr($item->data_agendamento) . ' · ' . daitechHoraLimpa($item->hora_agendamento);
                                $movivel = $podeMover;
                            } else {
                                $id = (int) $item->idTarefa;
                                $prazo = daitechPrazo($item->dataLimite, $item->status, ['concluído'], $hoje);
                                $titulo = $item->titulo;
                                $movivel = true;
                            }
                            ?>
                            <li class="flex shrink-0 flex-col gap-1.5 rounded-lg border border-border bg-surface p-3 shadow-elev-1 <?= $movivel ? 'cursor-grab active:cursor-grabbing' : '' ?>"
                                data-kanban-cartao data-id="<?= e((string) $id) ?>" data-status="<?= e($status) ?>" <?= $movivel ? 'draggable="true"' : '' ?>>
                                <div class="flex items-start justify-between gap-2">
                                    <?php if ($aba === 'os') { ?>
                                        <a href="<?= e(site_url('os/visualizar/' . $id)) ?>" class="rounded-xs font-semibold text-text underline-offset-4 hover:underline focus-visible:outline-3 focus-visible:outline-ring"><?= e($titulo) ?></a>
                                    <?php } else { ?>
                                        <span class="font-semibold [overflow-wrap:anywhere] text-text"><?= e($titulo) ?></span>
                                    <?php } ?>
                                    <?php if ($aba === 'tarefas') { ?>
                                        <?= component('pill-status', ['label' => $item->prioridade === 'Media' ? 'Média' : (string) $item->prioridade, 'variant' => DAITECH_TAREFA_PRIORIDADES[$item->prioridade] ?? 'neutral', 'dot' => false]) ?>
                                    <?php } ?>
                                </div>

                                <?php if ($aba === 'os') { ?>
                                    <p class="text-body-md [overflow-wrap:anywhere] text-text"><?= e($item->nomeCliente ?? 'Cliente removido') ?></p>
                                    <?php if ($equipamento !== '') { ?><p class="text-caption [overflow-wrap:anywhere] text-muted"><?= e($equipamento) ?></p><?php } ?>
                                    <p class="text-caption text-muted"><?= e('Técnico: ' . ($item->nomeTecnico ?? '—')) ?></p>
                                <?php } elseif ($aba === 'agendamentos') { ?>
                                    <p class="text-body-md [overflow-wrap:anywhere] text-text"><?= e($item->nomeCliente ?? 'Cliente removido') ?></p>
                                    <?php if (trim((string) $item->endereco) !== '') { ?><p class="flex items-start gap-1 text-caption [overflow-wrap:anywhere] text-muted"><?= icon('map-pin', ['class' => 'mt-0.5 size-3.5']) ?><?= e($item->endereco) ?></p><?php } ?>
                                <?php } else { ?>
                                    <?php if (trim((string) $item->descricao) !== '') { ?><p class="line-clamp-3 text-caption whitespace-pre-line [overflow-wrap:anywhere] text-muted"><?= e($item->descricao) ?></p><?php } ?>
                                    <p class="text-caption text-muted"><?= e('Responsável: ' . ($item->nomeResponsavel ?? '—')) ?></p>
                                <?php } ?>

                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <?= component('pill-status', $prazo + ['attrs' => ['data-kanban-prazo' => true]]) ?>
                                    <?php if ($aba === 'tarefas') { ?>
                                        <span class="flex gap-1">
                                            <?= component('button', ['label' => 'Editar ' . $item->titulo, 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'attrs' => [
                                                'data-modal-abrir' => 'tarefa-form',
                                                'data-form-acao' => site_url('kanban/salvarTarefa/' . $id),
                                                'data-form-titulo' => 'Editar tarefa',
                                                'data-valor-titulo' => (string) $item->titulo,
                                                'data-valor-descricao' => (string) $item->descricao,
                                                'data-valor-prioridade' => (string) $item->prioridade,
                                                'data-valor-status' => (string) $item->status,
                                                'data-valor-limite' => (string) $item->dataLimite,
                                                'data-valor-responsavel' => (string) $item->usuarios_id,
                                            ]]) ?>
                                            <?= component('button', ['label' => 'Excluir ' . $item->titulo, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => ['data-modal-abrir' => 'excluir-tarefa', 'data-valor-id' => (string) $id, 'data-valor-nome' => (string) $item->titulo]]) ?>
                                        </span>
                                    <?php } ?>
                                </div>

                                <?php if ($movivel) { ?>
                                    <?= component('select', [
                                        'name' => 'mover',
                                        'id' => 'mover-' . $aba . '-' . $id,
                                        'label' => 'Mover ' . $titulo . ' para',
                                        'hide_label' => true,
                                        'options' => $aba === 'os' ? array_diff_key($opcoesStatus, array_flip($travados)) + [$status => $rotuloColuna($status)] : $opcoesStatus,
                                        'selected' => $status,
                                        'class' => 'text-caption',
                                        'attrs' => ['data-kanban-mover' => true],
                                    ]) ?>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ol>
                    <?php if ($coluna['total'] > count($coluna['itens'])) { ?>
                        <p class="border-t border-border px-3 py-2 text-caption text-muted">
                            <?= e('Mostrando ' . count($coluna['itens']) . ' de ' . $coluna['total'] . '.') ?>
                            <?php if ($aba === 'os') { ?>
                                <a class="font-medium text-primary-strong underline" href="<?= e(site_url('os') . listagemQuery(['status' => $status])) ?>">Ver todas</a>
                            <?php } ?>
                        </p>
                    <?php } ?>
                </section>
            <?php } ?>
        </div>
    </div>
</div>

<?php if ($aba === 'tarefas') { ?>
    <?= component('modal', [
        'id' => 'tarefa-form',
        'title' => 'Nova tarefa',
        'size' => 'lg',
        'body' => [
            new HtmlSeguro('<form id="form-tarefa" method="post" action="' . e(site_url('kanban/salvarTarefa')) . '" class="grid gap-4 sm:grid-cols-2" novalidate ' . js_module('formulario/padrao') . '>'),
            $csrf,
            new HtmlSeguro('<div class="sm:col-span-2">'),
            component('input', ['name' => 'titulo', 'id' => 'tarefa-titulo', 'label' => 'Título', 'required' => true, 'attrs' => ['maxlength' => 255, 'data-modal-valor' => 'titulo', 'data-msg-vazio' => 'Informe o título da tarefa.']]),
            new HtmlSeguro('</div><div class="sm:col-span-2">'),
            component('textarea', ['name' => 'descricao', 'id' => 'tarefa-descricao', 'label' => 'Descrição', 'rows' => 3, 'attrs' => ['data-modal-valor' => 'descricao']]),
            new HtmlSeguro('</div>'),
            component('select', ['name' => 'prioridade', 'id' => 'tarefa-prioridade', 'label' => 'Prioridade', 'options' => ['Baixa' => 'Baixa', 'Media' => 'Média', 'Alta' => 'Alta'], 'selected' => 'Baixa', 'attrs' => ['data-modal-valor' => 'prioridade']]),
            component('select', ['name' => 'status', 'id' => 'tarefa-status', 'label' => 'Status', 'options' => $opcoesStatus, 'selected' => 'A FAZER', 'attrs' => ['data-modal-valor' => 'status']]),
            component('input', ['name' => 'dataLimite', 'id' => 'tarefa-limite', 'label' => 'Prazo', 'type' => 'date', 'help' => 'Opcional.', 'attrs' => ['data-modal-valor' => 'limite']]),
            component('select', ['name' => 'usuarios_id', 'id' => 'tarefa-responsavel', 'label' => 'Responsável', 'options' => $usuarios ?? [], 'selected' => (string) $this->session->userdata('id_admin'), 'attrs' => ['data-modal-valor' => 'responsavel']]),
            new HtmlSeguro('</form>'),
        ],
        'footer' => [
            component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
            component('button', ['label' => 'Salvar', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['form' => 'form-tarefa', 'data-rotulo-carregando' => 'Salvando…']]),
        ],
    ]) ?>

    <form id="form-excluir-tarefa" method="post" action="<?= e(site_url('kanban/excluirTarefa')) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-tarefa" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-tarefa',
        'title' => 'Excluir tarefa?',
        'message' => [
            'A tarefa ',
            new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="nome"></strong>'),
            ' será removida. Essa ação não pode ser desfeita.',
        ],
        'confirm_label' => 'Excluir',
        'confirm_attrs' => ['form' => 'form-excluir-tarefa'],
    ]) ?>
<?php } ?>
