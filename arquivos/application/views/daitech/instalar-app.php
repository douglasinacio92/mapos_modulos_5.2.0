<?php
/**
 * Módulos Daitech: aviso "Instale o Daitech-OS" (PWA).
 *
 * Fica escondido. O módulo daitech/instalar registra o service worker e só
 * mostra o aviso quando o navegador permite instalar (Chrome, Edge, Android)
 * ou, no iPhone, com o passo a passo do Safari. "Agora não" esconde por 30 dias.
 *
 * @var bool|null $entrada Tela de login (fundo escuro)
 */
$entrada = ! empty($entrada);
?>
<div <?= js_module('daitech/instalar') ?> data-sw-url="<?= e(base_url('sw.js')) ?>" data-escopo="<?= e(base_url()) ?>">
    <section hidden data-instalar-aviso role="dialog" aria-labelledby="instalar-titulo" aria-describedby="instalar-texto"
             class="<?= $entrada ? 'superficie-entrada ' : '' ?>fixed bottom-4 left-4 z-50 flex w-[calc(100%-2rem)] max-w-sm gap-3 rounded-xl border border-border bg-surface p-4 text-text shadow-overlay sm:bottom-6 sm:left-6">
        <img src="<?= e(base_url('assets/img/app/icone-192.png')) ?>" alt="" width="44" height="44" class="size-11 shrink-0 rounded-lg">
        <div class="flex min-w-0 flex-col gap-2">
            <h2 id="instalar-titulo" class="text-label-md text-text">Instale o Daitech-OS</h2>
            <p id="instalar-texto" class="text-caption text-muted" data-instalar-texto>Abra o sistema direto da tela inicial, em janela própria, como um aplicativo.</p>
            <p class="text-caption text-muted" hidden data-instalar-ios>No Safari, toque em <strong class="font-semibold text-text">Compartilhar</strong> e depois em <strong class="font-semibold text-text">Adicionar à Tela de Início</strong>.</p>
            <div class="flex flex-wrap gap-2">
                <?= component('button', ['label' => 'Instalar', 'icon' => 'download', 'variant' => 'outline', 'size' => 'sm', 'attrs' => ['data-instalar-confirmar' => true]]) ?>
                <?= component('button', ['label' => 'Agora não', 'variant' => 'ghost', 'size' => 'sm', 'attrs' => ['data-instalar-fechar' => true]]) ?>
            </div>
        </div>
    </section>
</div>
