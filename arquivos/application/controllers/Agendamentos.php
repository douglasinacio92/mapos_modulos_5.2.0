<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Agenda externa (Daitech): visitas técnicas do dia, com o calendário do mês,
 * WhatsApp (link e envio pela Evolution API), Google Agenda e Maps.
 *
 * Cadastro e edição em modal, por POST comum com CSRF, como as telas do
 * Map-OS v5. Os dados do formulário são validados no servidor
 * (daitechAgendamentoDadosDoFormulario()); o id editado vem da URL.
 */
class Agendamentos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('agendamentos_model');
        $this->load->helper('painel');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vAgendamento'])) {
            return $this->negarAcesso();
        }

        $hoje = date('Y-m-d');
        $data = dataIsoParaYmd((string) $this->input->get('data')) ?? $hoje;
        $calendario = daitechCalendarioDoMes($data, $hoje);
        $contagem = $this->agendamentos_model->contarPorDia($calendario['inicio'], $calendario['fim']);

        $this->load->model('mapos_model');
        $emitente = $this->mapos_model->getEmitente();

        $this->data['data'] = $data;
        $this->data['hoje'] = $hoje;
        $this->data['calendario'] = $calendario;
        $this->data['contagem'] = array_map('intval', $contagem);
        $this->data['results'] = $this->agendamentos_model->doDia($data);
        $this->data['modelo_mensagem'] = (string) ($this->data['configuration']['dt_msg_agendamento'] ?? '');
        $this->data['emitente_nome'] = (string) ($emitente->nome ?? $this->data['configuration']['app_name'] ?? '');
        $this->data['evolution'] = daitechConfig('EVOLUTION_API_URL') !== '' && daitechConfig('EVOLUTION_API_KEY') !== '';
        $this->data['pode'] = [
            'adicionar' => $this->permite('aAgendamento'),
            'editar' => $this->permite('eAgendamento'),
            'excluir' => $this->permite('dAgendamento'),
            'cadastrar_cliente' => $this->permite('aCliente'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo agendamento', 'icon' => 'calendar-plus', 'attrs' => ['data-modal-abrir' => 'agendamento-novo']];
        }

        $this->data['view'] = 'agendamentos/agendamentos';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->hasAnyPermission(['aAgendamento'])) {
            return $this->negarAcesso();
        }

        if ($this->input->method() !== 'post') {
            redirect('agendamentos');
        }

        [$dados, $erros] = daitechAgendamentoDadosDoFormulario((array) $this->input->post(), true);
        if ($erros === [] && ! $this->agendamentos_model->clienteExiste((int) $dados['clientes_id'])) {
            $erros['cliente'] = 'Escolha um cliente da lista.';
        }

        if ($erros !== []) {
            $this->session->set_flashdata('error', 'Agendamento não salvo: ' . implode(' ', $erros));

            return redirect('agendamentos?' . http_build_query(['data' => $dados['data_agendamento'] ?? date('Y-m-d')]));
        }

        $dados['usuarios_id'] = (int) $this->session->userdata('id_admin');
        $id = $this->agendamentos_model->add($dados);

        if ($id === null) {
            $this->session->set_flashdata('error', 'Não foi possível salvar o agendamento. Tente de novo.');
        } else {
            log_info('Adicionou um agendamento. ID: ' . $id);
            $this->session->set_flashdata('success', 'Visita agendada para ' . dataBr($dados['data_agendamento']) . ' às ' . $dados['hora_agendamento'] . '.');
        }

        redirect('agendamentos?' . http_build_query(['data' => $dados['data_agendamento']]));
    }

    public function editar()
    {
        if (! $this->hasAnyPermission(['eAgendamento'])) {
            return $this->negarAcesso();
        }

        $agendamento = is_numeric($this->uri->segment(3)) ? $this->agendamentos_model->getById((int) $this->uri->segment(3)) : null;
        if (! $agendamento || $this->input->method() !== 'post') {
            $this->session->set_flashdata('error', 'Agendamento não encontrado.');

            return redirect('agendamentos');
        }

        [$dados, $erros] = daitechAgendamentoDadosDoFormulario((array) $this->input->post(), false);
        if ($erros !== []) {
            $this->session->set_flashdata('error', 'Alterações não salvas: ' . implode(' ', $erros));

            return redirect('agendamentos?' . http_build_query(['data' => $agendamento->data_agendamento]));
        }

        if ($this->agendamentos_model->edit((int) $agendamento->idAgendamento, $dados)) {
            log_info('Alterou o agendamento ID: ' . $agendamento->idAgendamento);
            $this->session->set_flashdata('success', 'Agendamento atualizado.');
        } else {
            $this->session->set_flashdata('error', 'Não foi possível salvar. Tente de novo.');
        }

        redirect('agendamentos?' . http_build_query(['data' => $dados['data_agendamento']]));
    }

    public function excluir()
    {
        if (! $this->hasAnyPermission(['dAgendamento'])) {
            return $this->negarAcesso();
        }

        $agendamento = $this->input->method() === 'post' ? $this->agendamentos_model->getById((int) $this->input->post('id')) : null;

        if ($agendamento && $this->agendamentos_model->delete((int) $agendamento->idAgendamento)) {
            log_info('Excluiu o agendamento ID: ' . $agendamento->idAgendamento);
            $this->session->set_flashdata('success', 'Agendamento removido.');

            return redirect('agendamentos?' . http_build_query(['data' => $agendamento->data_agendamento]));
        }

        $this->session->set_flashdata('error', 'Não foi possível remover o agendamento.');
        redirect('agendamentos');
    }

    /**
     * Envia a confirmação da visita pelo WhatsApp, pela Evolution API
     * (EVOLUTION_API_URL, EVOLUTION_API_INSTANCE e EVOLUTION_API_KEY no
     * application/.env). JSON {result, message}.
     */
    public function enviarWhatsApp()
    {
        if (! $this->hasAnyPermission(['aAgendamento', 'eAgendamento'])) {
            return $this->negarAcesso();
        }

        if ($this->input->method() !== 'post') {
            return $this->json(405, ['result' => false, 'message' => 'Método não permitido.']);
        }

        $agendamento = $this->agendamentos_model->getById((int) $this->input->post('id'));
        if (! $agendamento) {
            return $this->json(404, ['result' => false, 'message' => 'Agendamento não encontrado. Recarregue a página.']);
        }

        $telefone = daitechTelefone($agendamento->celular ?: $agendamento->telefone);
        if ($telefone === null) {
            return $this->json(422, ['result' => false, 'message' => 'O cliente não tem celular válido no cadastro.']);
        }

        $url = daitechConfig('EVOLUTION_API_URL');
        $chave = daitechConfig('EVOLUTION_API_KEY');
        $instancia = daitechConfig('EVOLUTION_API_INSTANCE', 'daitech');
        if ($url === '' || $chave === '' || ! preg_match('#^https?://#', $url)) {
            return $this->json(503, ['result' => false, 'message' => 'Envio automático não configurado (EVOLUTION_API_URL e EVOLUTION_API_KEY no .env). Use o botão do WhatsApp.']);
        }

        $this->load->model('mapos_model');
        $emitente = $this->mapos_model->getEmitente();
        $texto = daitechMensagemAgendamento((string) ($this->data['configuration']['dt_msg_agendamento'] ?? ''), $agendamento, (string) ($emitente->nome ?? ''));

        $curl = curl_init(rtrim($url, '/') . '/message/sendText/' . rawurlencode($instancia));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['number' => $telefone, 'text' => $texto]),
            CURLOPT_HTTPHEADER => ['apikey: ' . $chave, 'Content-Type: application/json'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erro = curl_error($curl);
        curl_close($curl);

        if ($status >= 200 && $status < 300) {
            log_info('Enviou a confirmação do agendamento ID ' . $agendamento->idAgendamento . ' pelo WhatsApp.');

            return $this->json(200, ['result' => true, 'message' => 'Confirmação enviada para ' . $agendamento->nomeCliente . '.']);
        }

        log_message('error', 'Evolution API: HTTP ' . $status . ' ' . $erro);

        return $this->json(502, ['result' => false, 'message' => 'A API do WhatsApp não aceitou o envio (HTTP ' . $status . '). Use o botão do WhatsApp.']);
    }

    public function autoCompleteCliente()
    {
        if (! $this->hasAnyPermission(['aAgendamento', 'eAgendamento'])) {
            return $this->negarAcesso();
        }

        $termo = trim((string) $this->input->get('term'));

        return $this->json(200, mb_strlen($termo) < 2 ? [] : $this->agendamentos_model->autoCompleteCliente(mb_substr($termo, 0, 100)));
    }

    private function json(int $status, array $corpo)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }
}
