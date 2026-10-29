<?php
/**
 * Chat flutuante do mural (Daitech), em todas as telas do painel: incluído
 * pelo views/tema/rodape.php. O módulo notas/chat consulta notas/novidades a
 * cada 30 segundos (contador e aviso de nota nova) e, com a janela aberta,
 * mostra e envia as mensagens (notas/historico e notas/enviar).
 */
if (! function_exists('daitechAssinaturaValida')) {
    // Helper dos módulos Daitech fora do autoload: não mostra nada.
    return;
}
?>
<div class="pointer-events-none fixed right-4 bottom-4 z-40 flex flex-col items-end gap-3 print:hidden" <?= js_module('notas/chat') ?>
     data-novidades-url="<?= e(site_url('notas/novidades')) ?>"
     data-historico-url="<?= e(site_url('notas/historico')) ?>"
     data-enviar-url="<?= e(site_url('notas/enviar')) ?>"
     data-som-url="<?= e(base_url('assets/daitech/notificacao.mp3')) ?>">
    <section id="chat-mural" hidden aria-labelledby="chat-mural-titulo"
             class="pointer-events-auto flex h-[min(30rem,calc(100vh-7rem))] w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border border-border bg-surface shadow-overlay">
        <header class="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
            <h2 id="chat-mural-titulo" class="flex items-center gap-2 text-heading-sm text-text"><?= icon('message-square', ['class' => 'size-5 text-muted']) ?>Mural da equipe</h2>
            <div class="flex items-center gap-1">
                <?= component('button', ['label' => 'Abrir o mural', 'icon' => 'external-link', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'href' => site_url('notas')]) ?>
                <?= component('button', ['label' => 'Fechar o chat', 'icon' => 'x', 'icon_only' => true, 'variant' => 'ghost', 'size' => 'sm', 'attrs' => ['data-chat-fechar' => true]]) ?>
            </div>
        </header>
        <ol class="flex flex-1 flex-col-reverse gap-2 overflow-y-auto bg-surface-subtle px-3 py-3" data-chat-lista aria-live="polite" aria-label="Mensagens">
            <li class="text-center text-caption text-muted">Carregando…</li>
        </ol>
        <form class="flex items-end gap-2 border-t border-border p-3" data-chat-form novalidate>
            <label for="chat-mural-texto" class="sr-only">Mensagem</label>
            <textarea id="chat-mural-texto" name="texto" rows="1" maxlength="2000" required placeholder="Escreva uma mensagem…"
                      class="block max-h-28 min-h-10 w-full resize-none rounded-sm border border-input bg-field px-3 py-2 text-body-md text-text placeholder:text-muted focus:outline-3 focus:outline-offset-0 focus:outline-ring"></textarea>
            <?= component('button', ['label' => 'Enviar', 'icon' => 'send', 'icon_only' => true, 'variant' => 'outline', 'type' => 'submit']) ?>
        </form>
    </section>
    <button type="button" data-chat-abrir aria-controls="chat-mural" aria-expanded="false"
            class="pointer-events-auto relative inline-flex size-14 items-center justify-center rounded-full border border-border bg-surface text-text shadow-overlay hover:bg-surface-subtle focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-ring">
        <?= icon('message-square', ['class' => 'size-6']) ?>
        <span class="sr-only">Mural da equipe</span>
        <?= component('badge', ['label' => '0', 'variant' => 'accent', 'size' => 'sm', 'class' => 'absolute -top-1 -right-1 border border-primary', 'attrs' => ['hidden' => true, 'data-chat-contador' => true]]) ?>
    </button>
</div>
