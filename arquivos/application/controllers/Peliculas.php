<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Películas (Daitech): busca de compatibilidade de películas entre modelos
 * de celular. Listagem no padrão do Map-OS v5 (Clientes::gerenciar()), com o
 * cadastro e a edição num modal (POST comum, com CSRF).
 */
class Peliculas extends MY_Controller
{
    public const FILTROS = ['pesquisa' => 'texto'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('peliculas_model');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vPelicula'])) {
            return $this->negarAcesso();
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->input->get('per_page');
        $total = $this->peliculas_model->contar($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->peliculas_model->listar($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('peliculas'), $total, $offset, 'per_page', $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aPelicula'),
            'editar' => $this->permite('ePelicula'),
            'excluir' => $this->permite('dPelicula'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Nova película', 'icon' => 'plus', 'attrs' => ['data-modal-abrir' => 'pelicula-form']];
        }

        $this->data['view'] = 'peliculas/peliculas';

        return $this->layout();
    }

    public function adicionar()
    {
        if (! $this->hasAnyPermission(['aPelicula'])) {
            return $this->negarAcesso();
        }

        $this->salvar(null);
    }

    public function editar()
    {
        if (! $this->hasAnyPermission(['ePelicula'])) {
            return $this->negarAcesso();
        }

        $pelicula = is_numeric($this->uri->segment(3)) ? $this->peliculas_model->getById((int) $this->uri->segment(3)) : null;
        if (! $pelicula) {
            $this->session->set_flashdata('error', 'Película não encontrada.');
            redirect('peliculas');
        }

        $this->salvar($pelicula);
    }

    public function excluir()
    {
        if (! $this->hasAnyPermission(['dPelicula'])) {
            return $this->negarAcesso();
        }

        if ($this->input->method() !== 'post') {
            redirect('peliculas');
        }

        $id = (int) $this->input->post('id');
        if ($id > 0 && $this->peliculas_model->getById($id) && $this->peliculas_model->delete($id)) {
            log_info('Excluiu a película ID ' . $id);
            $this->session->set_flashdata('success', 'Película excluída.');
        } else {
            $this->session->set_flashdata('error', 'Não foi possível excluir a película.');
        }

        redirect('peliculas' . listagemQuery(listagemFiltros(self::FILTROS, $this->input->get())));
    }

    private function salvar(?object $pelicula): void
    {
        if ($this->input->method() !== 'post') {
            redirect('peliculas');
        }

        $modelo = mb_substr(trim((string) $this->input->post('modelo')), 0, 150);
        $compativeis = mb_substr(trim((string) $this->input->post('compativeis')), 0, 5000);

        if ($modelo === '' || $compativeis === '') {
            $this->session->set_flashdata('error', 'Informe o modelo e os modelos compatíveis.');
            redirect('peliculas');
        }

        $dados = ['modelo' => $modelo, 'compativeis' => $compativeis];
        $ok = $pelicula === null ? $this->peliculas_model->add($dados) : $this->peliculas_model->edit((int) $pelicula->id, $dados);

        if ($ok) {
            log_info(($pelicula === null ? 'Cadastrou' : 'Alterou') . ' a película do modelo ' . $modelo);
            $this->session->set_flashdata('success', $pelicula === null ? 'Película cadastrada.' : 'Película alterada.');
        } else {
            $this->session->set_flashdata('error', 'Não foi possível salvar. Tente de novo.');
        }

        redirect('peliculas?' . http_build_query(['pesquisa' => $modelo]));
    }
}
