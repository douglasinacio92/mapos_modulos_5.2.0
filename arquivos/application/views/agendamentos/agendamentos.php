<?php
/**
 * Agenda externa (Daitech): visitas técnicas do dia escolhido.
 *
 * - Calendário do mês ao lado (em cima, no celular), com um ponto nos dias
 *   com visita; setas trocam o mês e "Hoje" volta para o dia atual. Tudo por
 *   link (?data=AAAA-MM-DD), sem JavaScript.
 * - Cada visita é um cartão: horário, cliente, endereço, descrição e status
 *   (pill-status), com WhatsApp, Maps, Google Agenda, editar e excluir.
 * - Novo agendamento e edição em modal (POST comum). O módulo
 *   agendamentos/tela liga o autocomplete do cliente (que sugere o endereço
 *   do cadastro) e o envio da confirmação pela Evolution API.
 *
 * @var string        $data
 * @var string        $hoje
 * @var array         $calendario daitechCalendarioDoMes()
 * @var array<string, int> $contagem visitas por dia do calendário
 * @var list<object>  $results
 * @var string        $modelo_mensagem
 * @var string        $emitente_nome
 * @var bool          $evolution
 * @var array<string, bool> $pode
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
$horarios = daitechHorarios();
foreach ($results as $r) {
    $horarios += daitechHorarios($r->hora_agendamento);
}
ksort($horarios);
$statusOpcoes = array_combine(array_keys(DAITECH_AGENDAMENTO_STATUS), array_map(static fn ($s) => daitechAgendamentoPill($s)['label'], array_keys(DAITECH_AGENDAMENTO_STATUS)));
$csrf = new HtmlSeguro('<input type="hidden" name="' . e($this->security->get_csrf_token_name()) . '" value="' . e($this->security->get_csrf_hash()) . '">');
$dataExtenso = painelDataPorExtenso($data);
?>
<div class="flex flex-col gap-4 pt-2 pb-8" <?= js_module('agendamentos/tela') ?> data-enviar-url="<?= e(site_url('agendamentos/enviarWhatsApp')) ?>">
    <header class="flex flex-col gap-1" <?= js_module('daitech/modal-form') ?>>
        <h1 class="font-display text-heading-xl text-text">Agenda externa</h1>
        <p class="text-caption text-muted" aria-live="polite">
            <?= e(ucfirst($dataExtenso) . ' · ' . (count($results) === 0 ? 'nenhuma visita' : (count($results) === 1 ? '1 visita' : count($results) . ' visitas'))) ?>
        </p>
    </header>

    <div class="grid items-start gap-4 lg:grid-cols-[19rem_minmax(0,1fr)]">
    <nav aria-label="Calendário da agenda" class="rounded-xl border border-border bg-surface p-3 lg:sticky lg:top-20">
        <div class="flex items-center justify-between gap-2">
            <?= component('button', ['label' => 'Mês anterior', 'icon' => 'chevron-left', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('agendamentos') . '?' . http_build_query(['data' => $calendario['mes_anterior']])]) ?>
            <p class="text-label-md text-text"><?= e($calendario['titulo']) ?></p>
            <?= component('button', ['label' => 'Próximo mês', 'icon' => 'chevron-right', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('agendamentos') . '?' . http_build_query(['data' => $calendario['mes_seguinte']])]) ?>
        </div>
        <table class="mt-2 w-full table-fixed text-center">
            <thead>
                <tr class="text-[0.75rem] text-muted">
                    <?php foreach (['D' => 'Domingo', 'S' => 'Segunda', 'T' => 'Terça', 'Q' => 'Quarta', 'Qi' => 'Quinta', 'Sx' => 'Sexta', 'Sá' => 'Sábado'] as $curto => $nome) { ?>
                        <th scope="col" class="pb-1 font-medium"><abbr title="<?= e($nome) ?>" class="no-underline"><?= e(mb_substr($curto, 0, 1)) ?></abbr></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($calendario['semanas'] as $semana) { ?>
                    <tr>
                        <?php foreach ($semana as $dia) {
                            $total = $contagem[$dia['data']] ?? 0;
                            $classe = match (true) {
                                $dia['atual'] => 'bg-primary-tint font-semibold text-text ring-1 ring-primary',
                                $dia['hoje'] => 'font-semibold text-primary-strong hover:bg-surface-subtle',
                                $dia['no_mes'] => 'text-text hover:bg-surface-subtle',
                                default => 'text-muted/60 hover:bg-surface-subtle',
                            }; ?>
                            <td class="p-0.5">
                                <a href="<?= e(site_url('agendamentos') . '?' . http_build_query(['data' => $dia['data']])) ?>"
                                   <?= $dia['atual'] ? 'aria-current="date"' : '' ?>
                                   title="<?= e(dataBr($dia['data']) . ($total ? ' · ' . $total . ($total === 1 ? ' visita' : ' visitas') : '')) ?>"
                                   class="relative mx-auto flex size-9 flex-col items-center justify-center rounded-md text-body-md tabular-nums focus-visible:outline-3 focus-visible:outline-ring <?= e($classe) ?>">
                                    <?= e($dia['numero']) ?>
                                    <?php if ($total > 0) { ?>
                                        <span class="absolute bottom-1 size-1.5 rounded-full bg-info" aria-hidden="true"></span>
                                        <span class="sr-only"><?= e($total === 1 ? ', 1 visita' : ', ' . $total . ' visitas') ?></span>
                                    <?php } ?>
                                </a>
                            </td>
                        <?php } ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <div class="mt-2 flex items-center justify-between gap-2 border-t border-border pt-2">
            <span class="flex items-center gap-1.5 text-caption text-muted"><span class="size-1.5 rounded-full bg-info" aria-hidden="true"></span>Dia com visita</span>
            <?php if ($data !== $hoje) { ?>
                <?= component('button', ['label' => 'Hoje', 'icon' => 'calendar-days', 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('agendamentos')]) ?>
            <?php } ?>
        </div>
    </nav>

    <section class="min-w-0" aria-label="Visitas do dia">

    <?php if ($results === []) { ?>
        <?= component('empty-state', [
            'title' => 'Nenhuma visita neste dia',
            'message' => 'As visitas técnicas agendadas para ' . dataBr($data) . ' aparecem aqui.',
            'icon' => 'calendar-days',
            'action' => $pode['adicionar'] ? component('button', ['label' => 'Agendar visita', 'icon' => 'calendar-plus', 'variant' => 'outline', 'attrs' => ['data-modal-abrir' => 'agendamento-novo']]) : null,
        ]) ?>
    <?php } else { ?>
        <ol class="flex flex-col gap-3">
            <?php foreach ($results as $r) {
                $hora = daitechHoraLimpa($r->hora_agendamento);
                $nome = (string) ($r->nomeCliente ?? 'Cliente removido');
                $telefone = daitechTelefone($r->celular ?: $r->telefone);
                $mensagem = daitechMensagemAgendamento($modelo_mensagem, $r, $emitente_nome);
                $maps = daitechMapsUrl($r->endereco);
                $google = daitechGoogleAgendaUrl('Visita: ' . $nome, $r->data_agendamento, $hora, (string) $r->endereco, (string) $r->descricao);
                ?>
                <li class="<?= e($caixa) ?> grid gap-4 sm:grid-cols-[6rem_minmax(0,1fr)_auto] sm:items-start">
                    <div class="flex items-center gap-3 sm:flex-col sm:items-start sm:gap-1">
                        <span class="font-display text-heading-lg tabular-nums text-text"><?= e($hora ?: '--:--') ?></span>
                        <?= component('pill-status', daitechAgendamentoPill($r->status)) ?>
                    </div>
                    <div class="flex min-w-0 flex-col gap-1">
                        <h2 class="text-heading-sm [overflow-wrap:anywhere] text-text"><?= e($nome) ?></h2>
                        <?php if (trim((string) $r->endereco) !== '') { ?>
                            <p class="flex items-start gap-1.5 text-body-md text-text"><?= icon('map-pin', ['class' => 'mt-0.5 size-4 text-muted']) ?><span class="[overflow-wrap:anywhere]"><?= e($r->endereco) ?></span></p>
                        <?php } ?>
                        <?php if (trim((string) $r->descricao) !== '') { ?>
                            <p class="text-body-md whitespace-pre-line [overflow-wrap:anywhere] text-muted"><?= e($r->descricao) ?></p>
                        <?php } ?>
                        <p class="text-caption text-muted"><?= e(trim(implode(' · ', array_filter([$r->celular ?: $r->telefone, $r->nomeUsuario ? 'Agendado por ' . $r->nomeUsuario : null])))) ?></p>
                    </div>
                    <div class="flex flex-wrap gap-1 sm:justify-end">
                        <?php if ($telefone !== null) { ?>
                            <?= component('button', ['label' => 'WhatsApp de ' . $nome, 'icon' => 'message-circle', 'icon_only' => true, 'variant' => 'ghost', 'href' => 'https://wa.me/' . $telefone . '?text=' . rawurlencode($mensagem), 'attrs' => ['target' => '_blank', 'rel' => 'noopener', 'title' => 'Abrir o WhatsApp com a confirmação']]) ?>
                            <?php if ($evolution && ($pode['adicionar'] || $pode['editar'])) { ?>
                                <?= component('button', ['label' => 'Enviar confirmação automática', 'icon' => 'send', 'icon_only' => true, 'variant' => 'ghost', 'attrs' => ['data-enviar-confirmacao' => (string) $r->idAgendamento, 'title' => 'Enviar a confirmação pela API do WhatsApp']]) ?>
                            <?php } ?>
                        <?php } ?>
                        <?php if ($maps !== '') { ?>
                            <?= component('button', ['label' => 'Abrir o endereço no Maps', 'icon' => 'map-pin', 'icon_only' => true, 'variant' => 'ghost', 'href' => $maps, 'attrs' => ['target' => '_blank', 'rel' => 'noopener', 'title' => 'Abrir no Google Maps']]) ?>
                        <?php } ?>
                        <?php if ($google !== '') { ?>
                            <?= component('button', ['label' => 'Salvar no Google Agenda', 'icon' => 'calendar-plus', 'icon_only' => true, 'variant' => 'ghost', 'href' => $google, 'attrs' => ['target' => '_blank', 'rel' => 'noopener', 'title' => 'Salvar no Google Agenda do celular']]) ?>
                        <?php } ?>
                        <?php if ($pode['editar']) { ?>
                            <?= component('button', ['label' => 'Editar visita de ' . $nome, 'icon' => 'pencil', 'icon_only' => true, 'variant' => 'ghost', 'attrs' => [
                                'data-modal-abrir' => 'agendamento-editar',
                                'data-form-acao' => site_url('agendamentos/editar/' . (int) $r->idAgendamento),
                                'data-valor-nome' => $nome,
                                'data-valor-status' => (string) $r->status,
                                'data-valor-data' => (string) $r->data_agendamento,
                                'data-valor-hora' => $hora,
                                'data-valor-endereco' => (string) $r->endereco,
                                'data-valor-descricao' => (string) $r->descricao,
                            ]]) ?>
                        <?php } ?>
                        <?php if ($pode['excluir']) { ?>
                            <?= component('button', ['label' => 'Excluir visita de ' . $nome, 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'class' => 'hover:text-danger-ink', 'attrs' => [
                                'data-modal-abrir' => 'excluir-agendamento',
                                'data-valor-id' => (string) $r->idAgendamento,
                                'data-valor-nome' => $nome . ' às ' . $hora,
                            ]]) ?>
                        <?php } ?>
                    </div>
                </li>
            <?php } ?>
        </ol>
    <?php } ?>
    </section>
    </div>
</div>

<?php if ($pode['adicionar']) { ?>
    <?= component('modal', [
        'id' => 'agendamento-novo',
        'title' => 'Novo agendamento',
        'size' => 'lg',
        'body' => [
            new HtmlSeguro('<form id="form-agendamento-novo" method="post" action="' . e(site_url('agendamentos/adicionar')) . '" class="grid gap-4 sm:grid-cols-2" novalidate ' . js_module('formulario/padrao') . '>'),
            $csrf,
            new HtmlSeguro('<div class="relative sm:col-span-2" data-autocomplete="' . e(site_url('agendamentos/autoCompleteCliente')) . '" data-autocomplete-alvo="agendamento-clientes-id"' . ($pode['cadastrar_cliente'] ? ' data-autocomplete-novo-modal="cliente-rapido" data-autocomplete-novo-rotulo="Cadastrar novo cliente"' : '') . ' data-agenda-cliente>'),
            component('input', ['name' => 'cliente', 'id' => 'agendamento-cliente', 'label' => 'Cliente', 'required' => true, 'autocomplete' => 'off', 'placeholder' => 'Nome, documento ou telefone', 'attrs' => ['data-msg-vazio' => 'Escolha o cliente.', 'data-msg-invalido' => 'Escolha um cliente da lista.']]),
            new HtmlSeguro('<input type="hidden" id="agendamento-clientes-id" name="clientes_id" value="">'),
            new HtmlSeguro('</div>'),
            component('input', ['name' => 'data_agendamento', 'id' => 'agendamento-data', 'label' => 'Data', 'type' => 'date', 'required' => true, 'value' => $data, 'attrs' => ['data-msg-vazio' => 'Informe a data.']]),
            component('select', ['name' => 'hora_agendamento', 'id' => 'agendamento-hora', 'label' => 'Horário', 'required' => true, 'options' => daitechHorarios(), 'selected' => '09:00']),
            component('select', ['name' => 'status', 'id' => 'agendamento-status', 'label' => 'Status', 'required' => true, 'options' => $statusOpcoes, 'selected' => 'Agendado']),
            new HtmlSeguro('<div class="hidden sm:block" aria-hidden="true"></div>'),
            new HtmlSeguro('<div class="sm:col-span-2">'),
            component('input', ['name' => 'endereco', 'id' => 'agendamento-endereco', 'label' => 'Endereço da visita', 'placeholder' => 'Rua, número, bairro', 'help' => 'Preenchido com o endereço do cadastro ao escolher o cliente.', 'attrs' => ['maxlength' => 255, 'data-agenda-endereco' => true]]),
            new HtmlSeguro('</div><div class="sm:col-span-2">'),
            component('textarea', ['name' => 'descricao', 'id' => 'agendamento-descricao', 'label' => 'Descrição', 'rows' => 3, 'placeholder' => 'O que será feito na visita']),
            new HtmlSeguro('</div></form>'),
        ],
        'footer' => [
            component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
            component('button', ['label' => 'Agendar', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['form' => 'form-agendamento-novo', 'data-rotulo-carregando' => 'Salvando…']]),
        ],
    ]) ?>
    <?php if ($pode['cadastrar_cliente']) { ?>
        <?php $this->load->view('clientes/partes/cadastro-rapido') ?>
    <?php } ?>
<?php } ?>

<?php if ($pode['editar']) { ?>
    <?= component('modal', [
        'id' => 'agendamento-editar',
        'title' => 'Editar agendamento',
        'size' => 'lg',
        'body' => [
            new HtmlSeguro('<p class="mb-4 text-body-md font-medium text-text" data-modal-valor="nome"></p>'),
            new HtmlSeguro('<form id="form-agendamento-editar" method="post" action="' . e(site_url('agendamentos')) . '" class="grid gap-4 sm:grid-cols-2" novalidate ' . js_module('formulario/padrao') . '>'),
            $csrf,
            component('input', ['name' => 'data_agendamento', 'id' => 'editar-data', 'label' => 'Data', 'type' => 'date', 'required' => true, 'attrs' => ['data-modal-valor' => 'data', 'data-msg-vazio' => 'Informe a data.']]),
            component('select', ['name' => 'hora_agendamento', 'id' => 'editar-hora', 'label' => 'Horário', 'required' => true, 'options' => $horarios, 'attrs' => ['data-modal-valor' => 'hora']]),
            component('select', ['name' => 'status', 'id' => 'editar-status', 'label' => 'Status', 'required' => true, 'options' => $statusOpcoes, 'attrs' => ['data-modal-valor' => 'status']]),
            new HtmlSeguro('<div class="hidden sm:block" aria-hidden="true"></div><div class="sm:col-span-2">'),
            component('input', ['name' => 'endereco', 'id' => 'editar-endereco', 'label' => 'Endereço da visita', 'attrs' => ['maxlength' => 255, 'data-modal-valor' => 'endereco']]),
            new HtmlSeguro('</div><div class="sm:col-span-2">'),
            component('textarea', ['name' => 'descricao', 'id' => 'editar-descricao', 'label' => 'Descrição / anotações', 'rows' => 4, 'attrs' => ['data-modal-valor' => 'descricao']]),
            new HtmlSeguro('</div></form>'),
        ],
        'footer' => [
            component('button', ['label' => 'Cancelar', 'variant' => 'ghost', 'attrs' => ['data-modal-fechar' => true]]),
            component('button', ['label' => 'Salvar alterações', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['form' => 'form-agendamento-editar', 'data-rotulo-carregando' => 'Salvando…']]),
        ],
    ]) ?>
<?php } ?>

<?php if ($pode['excluir']) { ?>
    <form id="form-excluir-agendamento" method="post" action="<?= e(site_url('agendamentos/excluir')) ?>" hidden>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <input type="hidden" name="id" value="" data-modal-de="excluir-agendamento" data-modal-valor="id">
    </form>
    <?= component('modal-confirm', [
        'id' => 'excluir-agendamento',
        'title' => 'Excluir agendamento?',
        'message' => [
            'A visita de ',
            new HtmlSeguro('<strong class="font-semibold text-text" data-modal-valor="nome"></strong>'),
            ' será removida da agenda. Essa ação não pode ser desfeita.',
        ],
        'confirm_label' => 'Excluir',
        'confirm_attrs' => ['form' => 'form-excluir-agendamento'],
    ]) ?>
<?php } ?>
