<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Orçamentos rápidos (Daitech).
 *
 * Um orçamento é uma venda com status "Orçamento" (o mesmo status com que o
 * Map-OS v5 cria a venda), montada no carrinho do PDV: produtos, serviços e
 * itens avulsos. Depois de aprovado, ele vira venda de duas formas:
 * - "Finalizar no PDV" (pdv/index/ID): fatura na hora, com pagamento e cupom;
 * - "Converter em venda": muda o status para Aberto e segue na tela de venda
 *   do Map-OS (faturamento, cobrança, PIX).
 */
class Orcamentos extends MY_Controller
{
    public const FILTROS = [
        'pesquisa' => 'texto',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('pdv_model');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['vVenda'])) {
            return $this->negarAcesso();
        }

        $this->gerenciar();
    }

    public function gerenciar()
    {
        if (! $this->hasAnyPermission(['vVenda'])) {
            return $this->negarAcesso();
        }

        $filtros = listagemFiltros(self::FILTROS, $this->input->get());
        $offset = (int) $this->input->get('per_page');
        $total = $this->pdv_model->contarOrcamentos($filtros);

        $this->data['filtros'] = $filtros;
        $this->data['total'] = $total;
        $this->data['results'] = $this->pdv_model->listarOrcamentos($filtros, (int) $this->data['configuration']['per_page'], $offset);
        $this->data['paginacao'] = $this->paginacao(site_url('orcamentos'), $total, $offset, 'per_page', $filtros);
        $this->data['pode'] = [
            'adicionar' => $this->permite('aVenda'),
            'editar' => $this->permite('eVenda'),
            'excluir' => $this->permite('dVenda'),
            'pdv' => $this->permite('aVenda') && $this->permite('eVenda'),
        ];

        if ($this->data['pode']['adicionar']) {
            $this->data['topbar_acao'] = ['label' => 'Novo orçamento', 'icon' => 'plus', 'href' => site_url('orcamentos/novo')];
        }

        $this->data['view'] = 'orcamentos/orcamentos';

        return $this->layout();
    }

    public function novo()
    {
        if (! $this->hasAnyPermission(['aVenda'])) {
            return $this->negarAcesso();
        }

        return $this->carrinho(null);
    }

    public function editar()
    {
        if (! $this->hasAnyPermission(['eVenda'])) {
            return $this->negarAcesso();
        }

        return $this->carrinho($this->orcamentoDaUrl());
    }

    private function carrinho(?object $orcamento)
    {
        $this->data['modo'] = 'orcamento';
        $this->data['venda'] = $orcamento;
        $this->data['carrinho'] = $orcamento ? $this->pdv_model->carrinhoDaVenda((int) $orcamento->idVendas) : [];
        $this->data['cliente'] = $orcamento ? ['id' => (string) $orcamento->clientes_id, 'nome' => (string) $orcamento->nomeCliente] : null;
        $this->data['desconto'] = $orcamento && (float) $orcamento->valor_desconto > 0 ? ['tipo' => $orcamento->tipo_desconto ?: 'real', 'valor' => (float) $orcamento->desconto] : null;
        $this->data['observacoes'] = $orcamento ? (string) $orcamento->observacoes_cliente : '';
        $this->data['pode'] = ['cadastrar_cliente' => $this->permite('aCliente')];
        $this->data['view'] = 'pdv/carrinho';

        return $this->layout();
    }

    /** Grava o orçamento novo (orcamentos/salvar) ou o editado (orcamentos/salvar/ID). JSON. */
    public function salvar()
    {
        if (! $this->hasAnyPermission(['aVenda', 'eVenda'])) {
            return $this->negarAcesso();
        }

        if ($this->input->method() !== 'post') {
            return $this->json(405, ['result' => false, 'message' => 'Método não permitido.']);
        }

        $orcamento = null;
        if ($this->uri->segment(3) !== null) {
            $orcamento = is_numeric($this->uri->segment(3)) ? $this->pdv_model->getVenda((int) $this->uri->segment(3)) : null;
            if (! $orcamento || $orcamento->status !== 'Orçamento' || (int) $orcamento->faturado === 1 || ! $this->permite('eVenda')) {
                return $this->json(403, ['result' => false, 'message' => 'Este orçamento não pode mais ser alterado.']);
            }
        } elseif (! $this->permite('aVenda')) {
            return $this->json(403, ['result' => false, 'message' => 'Você não tem permissão para criar orçamentos.']);
        }

        $post = (array) $this->input->post();
        $controleEstoque = (bool) ($this->data['configuration']['control_estoque'] ?? false);
        [$produtos, $extras, $totais, $cliente, $erros] = $this->pdv_model->prepararDoPost($post, $controleEstoque, $orcamento, (int) ($this->data['configuration']['dt_cliente_balcao'] ?? 0));
        if ($erros !== []) {
            return $this->json(422, ['result' => false, 'message' => reset($erros), 'erros' => $erros]);
        }

        $dados = [
            'clientes_id' => (int) $cliente->idClientes,
            'valorTotal' => $totais['bruto'],
            'desconto' => $totais['informado'],
            'valor_desconto' => $totais['desconto'] > 0 ? $totais['total'] : 0,
            'tipo_desconto' => $totais['tipo'],
            'observacoes' => trim("Orçamento rápido\n" . daitechObservacaoDosExtras($extras)),
            'observacoes_cliente' => mb_substr(trim(strip_tags((string) ($post['observacoes'] ?? ''))), 0, 5000),
        ];
        if ($orcamento === null) {
            $dados += [
                'dataVenda' => date('Y-m-d'),
                'usuarios_id' => (int) $this->session->userdata('id_admin'),
                'status' => 'Orçamento',
                'faturado' => 0,
            ];
        }

        $id = $this->pdv_model->gravar($orcamento, $dados, $produtos, $extras, $controleEstoque, null);
        if ($id === null) {
            return $this->json(500, ['result' => false, 'message' => 'Não foi possível salvar o orçamento. Tente de novo.']);
        }

        log_info(($orcamento ? 'Alterou' : 'Criou') . ' o orçamento ID: ' . $id);
        $this->session->set_flashdata('success', 'Orçamento #' . $id . ' salvo: ' . dinheiro($totais['total']) . '.');

        return $this->json($orcamento ? 200 : 201, [
            'result' => true,
            'message' => 'Orçamento #' . $id . ' salvo.',
            'id' => $id,
            'redirecionar' => site_url('orcamentos/editar/' . $id),
            'imprimir' => site_url('orcamentos/imprimir/' . $id),
        ]);
    }

    public function imprimir()
    {
        if (! $this->hasAnyPermission(['vVenda'])) {
            return $this->negarAcesso();
        }

        $venda = is_numeric($this->uri->segment(3)) ? $this->pdv_model->getVenda((int) $this->uri->segment(3)) : null;
        if (! $venda) {
            $this->session->set_flashdata('error', 'Orçamento não encontrado.');

            return redirect('orcamentos');
        }

        $this->load->model('mapos_model');
        $produtos = $this->pdv_model->produtosDaVenda((int) $venda->idVendas);
        $extras = $this->pdv_model->extrasDaVenda((int) $venda->idVendas);
        $bruto = array_sum(array_map(static fn ($i) => (float) $i->subTotal, [...$produtos, ...$extras]));

        $this->load->view('orcamentos/imprimirOrcamento', [
            'venda' => $venda,
            'produtos' => $produtos,
            'extras' => $extras,
            'bruto' => round($bruto, 2),
            'total' => (float) $venda->valor_desconto > 0 && (float) $venda->valor_desconto < $bruto ? (float) $venda->valor_desconto : round($bruto, 2),
            'emitente' => $this->mapos_model->getEmitente(),
            'validade' => (string) ($this->data['configuration']['dt_validade_orc'] ?? ''),
        ]);
    }

    /** Aprova: o orçamento vira venda em aberto, na tela de venda do Map-OS. */
    public function converter()
    {
        if (! $this->hasAnyPermission(['eVenda'])) {
            return $this->negarAcesso();
        }

        $orcamento = $this->orcamentoDaUrl();
        if ($this->input->method() !== 'post') {
            return redirect('orcamentos');
        }

        $this->db->where('idVendas', (int) $orcamento->idVendas)->update('vendas', ['status' => 'Aberto', 'dataVenda' => date('Y-m-d')]);
        log_info('Converteu o orçamento em venda. ID: ' . $orcamento->idVendas);
        $this->session->set_flashdata('success', 'Orçamento #' . $orcamento->idVendas . ' convertido em venda. Fature quando receber.');

        redirect('vendas/visualizar/' . (int) $orcamento->idVendas);
    }

    public function excluir()
    {
        if (! $this->hasAnyPermission(['dVenda'])) {
            return $this->negarAcesso();
        }

        $venda = $this->input->method() === 'post' ? $this->pdv_model->getVenda((int) $this->input->post('id')) : null;
        if (! $venda || $venda->status !== 'Orçamento' || (int) $venda->faturado === 1) {
            $this->session->set_flashdata('error', 'Orçamento não encontrado ou já convertido em venda.');

            return redirect('orcamentos');
        }

        $ok = $this->pdv_model->excluirOrcamento($venda, (bool) ($this->data['configuration']['control_estoque'] ?? false));
        if ($ok) {
            log_info('Excluiu o orçamento ID: ' . $venda->idVendas);
        }
        $this->session->set_flashdata($ok ? 'success' : 'error', $ok ? 'Orçamento excluído.' : 'Não foi possível excluir o orçamento.');
        redirect('orcamentos');
    }

    private function orcamentoDaUrl(): object
    {
        $venda = is_numeric($this->uri->segment(3)) ? $this->pdv_model->getVenda((int) $this->uri->segment(3)) : null;
        if (! $venda || $venda->status !== 'Orçamento' || (int) $venda->faturado === 1) {
            $this->session->set_flashdata('error', 'Orçamento não encontrado ou já convertido em venda.');
            redirect('orcamentos');
        }

        return $venda;
    }

    private function json(int $status, array $corpo)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }
}
