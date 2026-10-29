<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Etiquetas de produtos (Daitech): o usuário busca os produtos pelo nome ou
 * pelo código de barras, escolhe quantas etiquetas de cada um e se o preço
 * sai. A impressão monta as etiquetas de 35x21mm, 3 por fileira (bobina de
 * 105x21mm), com nome, preço e código de barras.
 */
class Etiquetas extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pdv_model');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vProduto'])) {
            return $this->negarAcesso();
        }

        $this->data['view'] = 'etiquetas/etiquetas';

        return $this->layout();
    }

    /** Autocomplete só de produtos (nome, código de barras ou número). JSON. */
    public function buscar()
    {
        if (! $this->hasAnyPermission(['vProduto'])) {
            return $this->negarAcesso();
        }

        $termo = mb_substr(trim((string) $this->input->get('term')), 0, 100);

        return $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($termo === '' ? [] : $this->pdv_model->buscar($termo, false)));
    }

    /** etiquetas/imprimir?i=12:3,15:1&preco=1 */
    public function imprimir()
    {
        if (! $this->hasAnyPermission(['vProduto'])) {
            return $this->negarAcesso();
        }

        $itens = daitechItensDaEtiqueta((string) $this->input->get('i'));
        $produtos = $itens === [] ? [] : $this->db->select('idProdutos, descricao, precoVenda, codDeBarra')
            ->where_in('idProdutos', array_keys($itens))->get('produtos')->result();
        $porId = array_column($produtos, null, 'idProdutos');

        $etiquetas = [];
        foreach ($itens as $id => $quantidade) {
            if (! isset($porId[$id])) {
                continue;
            }
            $produto = $porId[$id];
            $barras = daitechCodigoDeBarrasSvg($produto->codDeBarra);
            for ($i = 0; $i < $quantidade; $i++) {
                $etiquetas[] = ['produto' => $produto, 'barras' => $barras];
            }
        }

        if ($etiquetas === []) {
            $this->session->set_flashdata('error', 'Escolha pelo menos um produto para imprimir as etiquetas.');

            return redirect('etiquetas');
        }

        $this->load->model('mapos_model');
        $this->load->view('etiquetas/imprimirEtiquetas', [
            'fileiras' => array_chunk($etiquetas, 3),
            'comPreco' => (string) $this->input->get('preco') === '1',
            'emitente' => $this->mapos_model->getEmitente(),
        ]);
    }
}
