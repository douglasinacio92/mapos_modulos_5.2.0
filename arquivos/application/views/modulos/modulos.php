<?php
/**
 * Configurações dos módulos Daitech (Modulos::index()), no padrão dos
 * formulários do Map-OS v5: POST comum com CSRF, erros no campo e toast.
 *
 * @var array<string, string> $valores
 * @var array<string, string> $erros
 * @var array<string, bool>   $integracoes
 * @var bool                  $banco
 */
$caixa = 'rounded-xl border border-border bg-surface p-4 sm:p-6';
?>
<div class="flex max-w-4xl flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">Módulos Daitech</h1>
        <p class="text-caption text-muted">Agenda externa, Kanban, mural, películas, PDV, orçamentos e os dados do aparelho na OS.</p>
    </header>

    <?php if (! $banco) { ?>
        <?= component('alert', ['variant' => 'warning', 'message' => 'O banco ainda não tem as tabelas dos módulos. Rode Configurações > Sistema > Atualizar banco de dados.']) ?>
    <?php } elseif ($erros !== []) { ?>
        <?= component('alert', ['variant' => 'danger', 'message' => 'Confira os campos destacados.']) ?>
    <?php } ?>

    <form method="post" action="<?= e(current_url()) ?>" novalidate class="flex flex-col gap-4" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-os">
            <h2 id="secao-os" class="text-heading-sm text-text">Ordem de serviço</h2>
            <div class="mt-4">
                <?= component('textarea', ['name' => 'dt_termos_os', 'label' => 'Termos e condições', 'rows' => 5, 'value' => $valores['dt_termos_os'] ?: null, 'error' => $erros['dt_termos_os'] ?? null, 'help' => 'Saem na OS impressa (A4) e no cupom de 2 vias. Uma regra por linha.', 'attrs' => ['maxlength' => 3000]]) ?>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-agenda">
            <h2 id="secao-agenda" class="text-heading-sm text-text">Agenda externa</h2>
            <div class="mt-4 flex flex-col gap-4">
                <?= component('textarea', ['name' => 'dt_msg_agendamento', 'label' => 'Mensagem de confirmação da visita', 'rows' => 7, 'value' => $valores['dt_msg_agendamento'] ?: null, 'error' => $erros['dt_msg_agendamento'] ?? null, 'help' => 'Vai pelo WhatsApp. Use {CLIENTE}, {DATA}, {HORA}, {ENDERECO}, {DESCRICAO} e {EMITENTE}; *texto* sai em negrito.', 'attrs' => ['maxlength' => 2000]]) ?>
                <p class="flex flex-wrap items-center gap-2 text-body-md text-text">Envio automático (Evolution API): <?= component('pill-status', $integracoes['evolution'] ? ['label' => 'Configurado', 'variant' => 'success'] : ['label' => 'Não configurado', 'variant' => 'neutral']) ?></p>
                <p class="text-caption text-muted">Para enviar sem abrir o WhatsApp, defina EVOLUTION_API_URL, EVOLUTION_API_INSTANCE e EVOLUTION_API_KEY no application/.env. Sem elas, o botão do WhatsApp abre a conversa com a mensagem pronta.</p>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-vendas">
            <h2 id="secao-vendas" class="text-heading-sm text-text">PDV e orçamentos</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="relative" data-module="vendas/formulario">
                    <div class="relative" data-autocomplete="<?= e(site_url('os/autoCompleteCliente')) ?>" data-autocomplete-alvo="modulos-balcao-id">
                        <?= component('input', ['name' => 'cliente_balcao', 'id' => 'modulos-balcao', 'label' => 'Cliente das vendas de balcão', 'value' => $valores['balcao_nome'] ?: null, 'autocomplete' => 'off', 'error' => $erros['cliente_balcao'] ?? null, 'help' => 'Usado quando o PDV fica sem cliente. Em branco: o cadastro BALCAO ou "Consumidor final".', 'attrs' => ['data-msg-invalido' => 'Escolha um cliente da lista ou deixe em branco.']]) ?>
                        <input type="hidden" id="modulos-balcao-id" name="dt_cliente_balcao" value="<?= e($valores['balcao_id']) ?>">
                    </div>
                </div>
                <?= component('input', ['name' => 'dt_validade_orc', 'label' => 'Validade do orçamento', 'value' => $valores['dt_validade_orc'] ?: null, 'error' => $erros['dt_validade_orc'] ?? null, 'placeholder' => '5 dias úteis', 'help' => 'Sai na impressão do orçamento.', 'attrs' => ['maxlength' => 60]]) ?>
            </div>
        </section>

        <section class="<?= e($caixa) ?>" aria-labelledby="secao-agente">
            <h2 id="secao-agente" class="text-heading-sm text-text">API do agente (n8n)</h2>
            <p class="mt-3 flex flex-wrap items-center gap-2 text-body-md text-text">Token: <?= component('pill-status', $integracoes['agente'] ? ['label' => 'Configurado', 'variant' => 'success'] : ['label' => 'Desligada', 'variant' => 'neutral']) ?></p>
            <p class="mt-1 text-caption text-muted">Defina AGENTE_API_TOKEN (24 caracteres ou mais) no application/.env. Rotas: agente/buscar_os/ID, buscar_cliente?telefone=, buscar_ordens_cliente/ID, buscar_produto?q= e buscar_servico?q=, com o cabeçalho Authorization: Bearer TOKEN.</p>
        </section>

        <div class="flex justify-end">
            <?= component('button', ['label' => 'Salvar configurações', 'icon' => 'save', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Salvando…']]) ?>
        </div>
    </form>
</div>
