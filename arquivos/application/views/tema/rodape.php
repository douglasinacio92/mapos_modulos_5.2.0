<?php
/**
 * Layout do painel (#2835): rodapé, notificações e scripts do fim da página.
 *
 * As mensagens de flash (success/error da sessão) viram toasts do componente
 * da #2834, no lugar do sweetalert da v4. Algumas mensagens antigas trazem
 * <br> ou <b>; elas passam pelo HTMLPurifier (html_purificado()) em vez de
 * serem impressas cruas.
 *
 * @var array $configuration
 * @var array $layout
 */
?>
  <div class="v5-shell">
    <footer class="px-4 py-6 text-center text-xs text-muted lg:px-6">
      <p><?= e(date('Y')) ?> &copy; Douglas Inacio - Daitech-OS - Versão: <?= e($this->config->item('app_version')) ?></p>
    </footer>
  </div>
</div>

<?php $this->load->view('tema/busca'); ?>

<?php if (is_file(APPPATH . 'views/notas/partes/chat.php')) {
    // Módulos Daitech: chat flutuante do mural de notas.
    $this->load->view('notas/partes/chat');
} ?>

<div class="v5-shell">
  <div data-toast-region class="pointer-events-none fixed right-4 bottom-4 z-50 flex flex-col items-end gap-2">
    <?php foreach ($layout['flash'] as $mensagem) { ?>
      <?= component('toast', ['variant' => $mensagem['variant'], 'title' => $mensagem['title'], 'duration' => $mensagem['duration'], 'message' => html_purificado($mensagem['message'])]) ?>
    <?php } ?>
  </div>
</div>

<?php if (isset($this->load) && is_file(APPPATH . 'views/daitech/instalar-app.php')) {
    $this->load->view('daitech/instalar-app', ['entrada' => false]); // Módulos Daitech
} ?>

</body>

</html>
