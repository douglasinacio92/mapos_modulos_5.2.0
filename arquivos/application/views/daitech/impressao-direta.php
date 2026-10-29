<?php
/**
 * Módulos Daitech: impressão direta. Com ela ligada em Configurações ›
 * Módulos Daitech, o módulo daitech/impressao pega o clique nos botões com
 * data-imprimir="cupom|a4|etiqueta" e manda a página para o agente de
 * impressão do computador da loja, sem abrir a janela de impressão.
 */
if (! function_exists('daitechImpressaoDireta')) {
    return;
}
$configuracao = isset($this->data['configuration']) && is_array($this->data['configuration']) ? $this->data['configuration'] : [];
$impressoras = daitechImpressaoDireta($configuracao);
if ($impressoras === null) {
    return;
}
?>
<div hidden <?= js_module('daitech/impressao') ?> data-agente="<?= e(DAITECH_AGENTE_IMPRESSAO) ?>" data-impressoras="<?= e(json_encode($impressoras)) ?>" data-orientacao-etiqueta="<?= e(($configuracao['dt_imp_etq_orient'] ?? '') ?: 'portrait') ?>"></div>
