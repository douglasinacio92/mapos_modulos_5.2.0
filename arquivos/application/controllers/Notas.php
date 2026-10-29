<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Mural de notas (Daitech): recados da equipe numa tela própria e no chat
 * flutuante de todas as telas (views/notas/partes/chat.php, incluído no
 * rodapé do layout).
 *
 * Qualquer usuário logado lê e escreve. Excluir: o autor da nota ou quem tem
 * a permissão de configurar o sistema (cSistema).
 */
class Notas extends MY_Controller
{
    private const TAMANHO_MAXIMO = 2000;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('notas_model');
    }

    public function index()
    {
        $usuario = $this->usuario();
        $this->notas_model->marcarLidas($usuario);

        $this->data['results'] = $this->notas_model->listar(100);
        $this->data['usuario'] = $usuario;
        $this->data['admin'] = $this->permite('cSistema');
        $this->data['view'] = 'notas/mural';

        return $this->layout();
    }

    public function adicionar()
    {
        if ($this->input->method() === 'post') {
            $texto = $this->texto();
            if ($texto === '') {
                $this->session->set_flashdata('error', 'Escreva a nota antes de publicar.');
            } elseif ($this->notas_model->salvar($texto, $this->usuario()) !== null) {
                $this->session->set_flashdata('success', 'Nota publicada no mural.');
            } else {
                $this->session->set_flashdata('error', 'Não foi possível publicar. Tente de novo.');
            }
        }

        redirect('notas');
    }

    public function excluir()
    {
        $nota = $this->input->method() === 'post' ? $this->notas_model->getById((int) $this->input->post('id')) : null;

        if (! $nota) {
            $this->session->set_flashdata('error', 'Nota não encontrada.');
        } elseif ((int) $nota->usuarios_id !== $this->usuario() && ! $this->permite('cSistema')) {
            $this->session->set_flashdata('error', 'Só quem escreveu a nota pode excluí-la.');
        } elseif ($this->notas_model->excluir((int) $nota->idNota)) {
            $this->session->set_flashdata('success', 'Nota removida do mural.');
        }

        redirect('notas');
    }

    /** Aviso do chat: quantas notas não lidas e a última delas. */
    public function novidades()
    {
        $usuario = $this->usuario();
        $novas = $this->notas_model->naoLidas($usuario);
        $corpo = ['result' => true, 'novas' => $novas];

        if ($novas > 0 && ($ultima = $this->notas_model->ultimaNaoLida($usuario))) {
            $corpo['id'] = (int) $ultima->idNota;
            $corpo['autor'] = (string) ($ultima->nome ?? 'Alguém');
            $corpo['texto'] = mb_strimwidth(trim(strip_tags((string) $ultima->texto)), 0, 80, '…');
        }

        return $this->json(200, $corpo);
    }

    /** Conversa do chat (marca tudo como lido). */
    public function historico()
    {
        $usuario = $this->usuario();
        $this->notas_model->marcarLidas($usuario);

        return $this->json(200, ['result' => true, 'notas' => $this->paraJson($this->notas_model->listar(40), $usuario)]);
    }

    public function enviar()
    {
        if ($this->input->method() !== 'post') {
            return $this->json(405, ['result' => false, 'message' => 'Método não permitido.']);
        }

        $texto = $this->texto();
        if ($texto === '') {
            return $this->json(422, ['result' => false, 'message' => 'Escreva a mensagem.']);
        }

        if ($this->notas_model->salvar($texto, $this->usuario()) === null) {
            return $this->json(500, ['result' => false, 'message' => 'Não foi possível enviar. Tente de novo.']);
        }

        return $this->json(201, ['result' => true, 'notas' => $this->paraJson($this->notas_model->listar(40), $this->usuario())]);
    }

    private function paraJson(array $notas, int $usuario): array
    {
        return array_map(static fn ($n) => [
            'id' => (int) $n->idNota,
            'autor' => (string) ($n->nome ?? 'Usuário removido'),
            'texto' => (string) $n->texto,
            'data' => date('d/m H:i', strtotime((string) $n->data_postagem)),
            'minha' => (int) $n->usuarios_id === $usuario,
        ], $notas);
    }

    private function texto(): string
    {
        return mb_substr(trim(strip_tags((string) $this->input->post('texto'))), 0, self::TAMANHO_MAXIMO);
    }

    private function usuario(): int
    {
        return (int) $this->session->userdata('id_admin');
    }

    private function json(int $status, array $corpo)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }
}
