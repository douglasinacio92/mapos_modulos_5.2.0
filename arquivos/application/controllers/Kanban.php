<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Kanban (Daitech): ordens de serviço, visitas da agenda externa e tarefas
 * internas em colunas por status.
 *
 * As colunas vêm renderizadas do servidor (abas por link, ?aba=, e filtros na
 * query string). O módulo kanban/quadro arrasta os cartões (ou usa o "Mover
 * para" de cada cartão, que também serve ao teclado e ao celular) e grava em
 * kanban/mover. Cada tipo confere a sua permissão: eOs para a OS, eAgendamento
 * para a visita; tarefas, quem abre o Kanban.
 *
 * Faturar e cancelar uma OS mexem no financeiro e no estoque: ficam na tela
 * da OS, não no arrastar.
 */
class Kanban extends MY_Controller
{
    public const ABAS = ['os', 'agendamentos', 'tarefas'];

    public const FILTROS = [
        'aba' => self::ABAS,
        'pesquisa' => 'texto',
        'minhas' => ['1'],
    ];

    /** Status de OS que o Kanban não muda (ver o cabeçalho). */
    private const OS_TRAVADOS = ['Faturado', 'Cancelado'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('kanban_model');
        $this->load->helper('os');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vOs', 'vAgendamento'])) {
            return $this->negarAcesso();
        }

        $pode = [
            'os' => $this->permite('vOs'),
            'agendamentos' => $this->permite('vAgendamento'),
            'mover_os' => $this->permite('eOs'),
            'mover_agendamentos' => $this->permite('eAgendamento'),
        ];

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $aba = $filtros['aba'] ?? ($pode['os'] ? 'os' : ($pode['agendamentos'] ? 'agendamentos' : 'tarefas'));
        if (($aba === 'os' && ! $pode['os']) || ($aba === 'agendamentos' && ! $pode['agendamentos'])) {
            $aba = 'tarefas';
        }

        $hoje = date('Y-m-d');
        $consulta = [
            'pesquisa' => $filtros['pesquisa'] ?? '',
            'minhas' => isset($filtros['minhas']),
            'usuario' => (int) $this->session->userdata('id_admin'),
        ];

        $this->data['aba'] = $aba;
        $this->data['filtros'] = $filtros;
        $this->data['hoje'] = $hoje;
        $this->data['pode'] = $pode;
        $this->data['estatisticas'] = $this->kanban_model->estatisticas($hoje);

        if ($aba === 'os') {
            $status = daitechStatusDaOsParaKanban($this->data['configuration']['os_status_list'] ?? null);
            $this->data['colunas'] = $this->kanban_model->osPorStatus($status, $consulta);
        } elseif ($aba === 'agendamentos') {
            $this->data['colunas'] = $this->kanban_model->agendamentosPorStatus($consulta);
        } else {
            $this->data['colunas'] = $this->kanban_model->tarefasPorStatus($consulta);
            $this->data['usuarios'] = $this->kanban_model->usuariosAtivos();
            $this->data['topbar_acao'] = ['label' => 'Nova tarefa', 'icon' => 'plus', 'attrs' => ['data-modal-abrir' => 'tarefa-form', 'data-form-acao' => site_url('kanban/salvarTarefa'), 'data-form-titulo' => 'Nova tarefa', 'data-form-limpar' => true]];
        }

        $this->data['travados'] = self::OS_TRAVADOS;
        $this->data['view'] = 'kanban/painel';

        return $this->layout();
    }

    /** Contagens do topo, para o quadro atualizar depois de mover. */
    public function dados()
    {
        if (! $this->hasAnyPermission(['vOs', 'vAgendamento'])) {
            return $this->negarAcesso();
        }

        return $this->json(200, ['result' => true, 'estatisticas' => $this->kanban_model->estatisticas(date('Y-m-d'))]);
    }

    /**
     * Muda o status de um cartão: POST tipo (os, agendamentos, tarefas), id e
     * status. 200 {result, message, prazo?}, 403, 404 ou 422.
     */
    public function mover()
    {
        if (! $this->hasAnyPermission(['eOs', 'eAgendamento', 'vOs', 'vAgendamento'])) {
            return $this->negarAcesso();
        }

        if ($this->input->method() !== 'post') {
            return $this->json(405, ['result' => false, 'message' => 'Método não permitido.']);
        }

        $tipo = (string) $this->input->post('tipo');
        $id = (int) $this->input->post('id');
        $status = trim((string) $this->input->post('status'));
        $hoje = date('Y-m-d');

        if ($tipo === 'os') {
            if (! $this->permite('eOs')) {
                return $this->json(403, ['result' => false, 'message' => 'Você não tem permissão para editar OS.']);
            }
            $this->load->model('os_model');
            $os = $this->kanban_model->getOs($id);
            if (! $os) {
                return $this->json(404, ['result' => false, 'message' => 'OS não encontrada. Atualize o quadro.']);
            }
            if (! array_key_exists($status, OS_STATUS_VARIANTES)) {
                return $this->json(422, ['result' => false, 'message' => 'Status inválido.']);
            }
            if (in_array($status, self::OS_TRAVADOS, true) || in_array($os->status, self::OS_TRAVADOS, true) || ! $this->os_model->isEditable($id)) {
                return $this->json(422, ['result' => false, 'message' => 'Faturar, cancelar ou reabrir a OS #' . $id . ' é feito na tela da OS (mexe no financeiro e no estoque).']);
            }
            if ($os->status === $status) {
                return $this->json(200, ['result' => true, 'message' => 'Sem mudança.']);
            }

            $this->kanban_model->atualizar('os', 'idOs', $id, ['status' => $status]);
            $this->os_model->registrarStatus($id, (string) $os->status, $status, (int) $this->session->userdata('id_admin') ?: null);
            log_info('OS Nº ' . $id . ' movida para ' . $status . ' pelo Kanban');

            return $this->json(200, ['result' => true, 'message' => 'OS #' . $id . ' em ' . $status . '.']);
        }

        if ($tipo === 'agendamentos') {
            if (! $this->permite('eAgendamento')) {
                return $this->json(403, ['result' => false, 'message' => 'Você não tem permissão para editar a agenda.']);
            }
            $agendamento = $this->kanban_model->getAgendamento($id);
            if (! $agendamento) {
                return $this->json(404, ['result' => false, 'message' => 'Visita não encontrada. Atualize o quadro.']);
            }
            if (! array_key_exists($status, DAITECH_AGENDAMENTO_STATUS)) {
                return $this->json(422, ['result' => false, 'message' => 'Status inválido.']);
            }

            $this->kanban_model->atualizar('agendamentos', 'idAgendamento', $id, ['status' => $status]);
            log_info('Agendamento ID ' . $id . ' movido para ' . $status . ' pelo Kanban');

            return $this->json(200, ['result' => true, 'message' => 'Visita em ' . daitechAgendamentoPill($status)['label'] . '.', 'prazo' => daitechPrazo($agendamento->data_agendamento, $status, ['concluido', 'cancelado'], $hoje)]);
        }

        if ($tipo === 'tarefas') {
            $tarefa = $this->kanban_model->getTarefa($id);
            if (! $tarefa) {
                return $this->json(404, ['result' => false, 'message' => 'Tarefa não encontrada. Atualize o quadro.']);
            }
            if (! array_key_exists($status, DAITECH_TAREFA_STATUS)) {
                return $this->json(422, ['result' => false, 'message' => 'Status inválido.']);
            }

            $this->kanban_model->atualizar('daitech_tarefas', 'idTarefa', $id, ['status' => $status]);

            return $this->json(200, ['result' => true, 'message' => 'Tarefa em ' . $status . '.', 'prazo' => daitechPrazo($tarefa->dataLimite, $status, ['concluído'], $hoje)]);
        }

        return $this->json(422, ['result' => false, 'message' => 'Tipo de cartão inválido.']);
    }

    /** Cria (kanban/salvarTarefa) ou edita (kanban/salvarTarefa/ID) uma tarefa. */
    public function salvarTarefa()
    {
        if (! $this->hasAnyPermission(['vOs', 'vAgendamento'])) {
            return $this->negarAcesso();
        }

        $destino = 'kanban?aba=tarefas';
        if ($this->input->method() !== 'post') {
            return redirect($destino);
        }

        $tarefa = null;
        if ($this->uri->segment(3) !== null) {
            $tarefa = is_numeric($this->uri->segment(3)) ? $this->kanban_model->getTarefa((int) $this->uri->segment(3)) : null;
            if (! $tarefa) {
                $this->session->set_flashdata('error', 'Tarefa não encontrada.');

                return redirect($destino);
            }
        }

        [$dados, $erros] = daitechTarefaDadosDoFormulario((array) $this->input->post());
        $usuarios = $this->kanban_model->usuariosAtivos();
        if ($dados['usuarios_id'] === null || ! isset($usuarios[(string) $dados['usuarios_id']])) {
            $dados['usuarios_id'] = (int) $this->session->userdata('id_admin');
        }

        if ($erros !== []) {
            $this->session->set_flashdata('error', 'Tarefa não salva: ' . implode(' ', $erros));

            return redirect($destino);
        }

        if ($tarefa === null) {
            $ok = $this->kanban_model->adicionarTarefa($dados) !== null;
        } else {
            $ok = $this->kanban_model->atualizar('daitech_tarefas', 'idTarefa', (int) $tarefa->idTarefa, $dados);
        }

        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? ($tarefa === null ? 'Tarefa criada.' : 'Tarefa alterada.') : 'Não foi possível salvar a tarefa.');
        redirect($destino);
    }

    public function excluirTarefa()
    {
        if (! $this->hasAnyPermission(['vOs', 'vAgendamento'])) {
            return $this->negarAcesso();
        }

        $tarefa = $this->input->method() === 'post' ? $this->kanban_model->getTarefa((int) $this->input->post('id')) : null;
        $ok = $tarefa && $this->kanban_model->excluirTarefa((int) $tarefa->idTarefa);

        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Tarefa excluída.' : 'Não foi possível excluir a tarefa.');
        redirect('kanban?aba=tarefas');
    }

    private function json(int $status, array $corpo)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }
}
