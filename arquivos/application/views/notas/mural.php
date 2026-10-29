<?php
/**
 * Mural de notas (Daitech): recados da equipe. A mesma conversa aparece no
 * chat flutuante (notas/partes/chat).
 *
 * @var list<object> $results
 * @var int          $usuario
 * @var bool         $admin
 */
$csrf = new HtmlSeguro('<input type="hidden" name="' . e($this->security->get_csrf_token_name()) . '" value="' . e($this->security->get_csrf_hash()) . '">');
?>
<div class="flex max-w-3xl flex-col gap-4 pt-2 pb-8">
    <header>
        <h1 class="font-display text-heading-xl text-text">Mural de notas</h1>
        <p class="text-caption text-muted">Recados para toda a equipe. Quem estiver no sistema recebe o aviso no chat.</p>
    </header>

    <form method="post" action="<?= e(site_url('notas/adicionar')) ?>" novalidate class="flex flex-col gap-3 rounded-xl border border-border bg-surface p-4 sm:p-6" <?= js_module('formulario/padrao') ?>>
        <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
        <?= component('textarea', ['name' => 'texto', 'label' => 'Nova nota', 'required' => true, 'rows' => 3, 'placeholder' => 'Ex.: o notebook do Sr. João chega amanhã às 10h', 'attrs' => ['maxlength' => 2000, 'data-msg-vazio' => 'Escreva a nota antes de publicar.']]) ?>
        <div class="flex justify-end">
            <?= component('button', ['label' => 'Publicar', 'icon' => 'send', 'type' => 'submit', 'attrs' => ['data-rotulo-carregando' => 'Publicando…']]) ?>
        </div>
    </form>

    <?php if ($results === []) { ?>
        <?= component('empty-state', ['title' => 'Nenhuma nota ainda', 'message' => 'As notas publicadas pela equipe aparecem aqui.', 'icon' => 'message-square']) ?>
    <?php } else { ?>
        <ol class="flex flex-col gap-3" aria-label="Notas do mural">
            <?php foreach ($results as $n) {
                $minha = (int) $n->usuarios_id === $usuario; ?>
                <li class="flex gap-3 rounded-xl border border-border p-4 <?= $minha ? 'bg-primary-tint' : 'bg-surface' ?>">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-violet-mid text-sm font-semibold text-on-dark" aria-hidden="true"><?= e(layoutIniciais((string) ($n->nome ?? '?'))) ?></span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <p class="flex flex-wrap items-baseline gap-x-2 text-caption text-muted">
                            <span class="text-label-md text-text"><?= e($minha ? 'Você' : ($n->nome ?? 'Usuário removido')) ?></span>
                            <time datetime="<?= e(date('c', strtotime($n->data_postagem))) ?>"><?= e(date('d/m/Y H:i', strtotime($n->data_postagem))) ?></time>
                        </p>
                        <p class="text-body-md whitespace-pre-line [overflow-wrap:anywhere] text-text"><?= e($n->texto) ?></p>
                    </div>
                    <?php if ($minha || $admin) { ?>
                        <?= component('button', ['label' => 'Excluir nota', 'icon' => 'trash-2', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'class' => 'hover:text-danger-ink', 'attrs' => ['data-modal-abrir' => 'excluir-nota', 'data-valor-id' => (string) $n->idNota]]) ?>
                    <?php } ?>
                </li>
            <?php } ?>
        </ol>
    <?php } ?>
</div>

<form id="form-excluir-nota" method="post" action="<?= e(site_url('notas/excluir')) ?>" hidden>
    <input type="hidden" name="<?= e($this->security->get_csrf_token_name()) ?>" value="<?= e($this->security->get_csrf_hash()) ?>">
    <input type="hidden" name="id" value="" data-modal-de="excluir-nota" data-modal-valor="id">
</form>
<?= component('modal-confirm', [
    'id' => 'excluir-nota',
    'title' => 'Excluir nota?',
    'message' => 'A nota sai do mural e do chat de todos. Essa ação não pode ser desfeita.',
    'confirm_label' => 'Excluir',
    'confirm_attrs' => ['form' => 'form-excluir-nota'],
]) ?>
