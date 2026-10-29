<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * PDV (frente de caixa) da Daitech: venda de balcão numa tela só, com leitor
 * de código de barras, produtos, serviços e itens avulsos, desconto, troco e
 * venda a prazo. Finalizar grava a venda já faturada (status Faturado), o
 * lançamento no financeiro e abre o cupom.
 *
 * pdv/index/ID abre um orçamento no caixa: finalizar converte o próprio
 * orçamento em venda faturada.
 *
 * Totais, preços do cadastro e estoque são sempre calculados no servidor
 * (Pdv_model); do navegador só vêm os itens, as quantidades e os preços
 * digitados.
 */
class Pdv extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('pdv_model');
    }

    public function index()
    {
        if (! $this->hasAnyPermission(['aVenda'])) {
            return $this->negarAcesso();
        }

        $orcamento = null;
        if (is_numeric($this->uri->segment(3))) {
            $orcamento = $this->pdv_model->getVenda((int) $this->uri->segment(3));
            if (! $orcamento || $orcamento->status !== 'Orçamento' || (int) $orcamento->faturado === 1 || ! $this->permite('eVenda')) {
                $this->session->set_flashdata('error', 'Só um orçamento em aberto pode ser levado ao caixa.');

                return redirect('orcamentos');
            }
        }

        $this->data['modo'] = 'pdv';
        $this->data['venda'] = $orcamento;
        $this->data['carrinho'] = $orcamento ? $this->pdv_model->carrinhoDaVenda((int) $orcamento->idVendas) : [];
        $this->data['cliente'] = $orcamento ? ['id' => (string) $orcamento->clientes_id, 'nome' => (string) $orcamento->nomeCliente] : null;
        $this->data['desconto'] = $orcamento && (float) $orcamento->valor_desconto > 0 ? ['tipo' => $orcamento->tipo_desconto ?: 'real', 'valor' => (float) $orcamento->desconto] : null;
        $this->data['pode'] = ['cadastrar_cliente' => $this->permite('aCliente')];
        $this->data['view'] = 'pdv/carrinho';

        return $this->layout();
    }

    public function buscar()
    {
        if (! $this->hasAnyPermission(['aVenda', 'eVenda'])) {
            return $this->negarAcesso();
        }

        $termo = mb_substr(trim((string) $this->input->get('term')), 0, 100);

        return $this->json(200, $termo === '' ? [] : $this->pdv_model->buscar($termo));
    }

    public function finalizar()
    {
        if (! $this->hasAnyPermission(['aVenda'])) {
            return $this->negarAcesso();
        }

        if ($this->input->method() !== 'post') {
            return $this->json(405, ['result' => false, 'message' => 'Método não permitido.']);
        }

        $post = (array) $this->input->post();
        $controleEstoque = (bool) ($this->data['configuration']['control_estoque'] ?? false);

        $orcamento = null;
        if (! empty($post['orcamento'])) {
            $orcamento = $this->pdv_model->getVenda((int) $post['orcamento']);
            if (! $orcamento || $orcamento->status !== 'Orçamento' || (int) $orcamento->faturado === 1 || ! $this->permite('eVenda')) {
                return $this->json(403, ['result' => false, 'message' => 'Este orçamento já foi convertido ou não pode ser alterado.']);
            }
        }

        [$produtos, $extras, $totais, $cliente, $erros] = $this->pdv_model->prepararDoPost($post, $controleEstoque, $orcamento, (int) ($this->data['configuration']['dt_cliente_balcao'] ?? 0));
        if ($erros !== []) {
            return $this->json(422, ['result' => false, 'message' => reset($erros), 'erros' => $erros]);
        }
        if ($totais['total'] <= 0 && $produtos === []) {
            return $this->json(422, ['result' => false, 'message' => 'O total da venda está zerado.']);
        }

        $forma = (string) ($post['forma'] ?? '');
        if (! isset(DAITECH_FORMAS_PAGAMENTO[$forma])) {
            return $this->json(422, ['result' => false, 'message' => 'Escolha a forma de pagamento.', 'erros' => ['forma' => 'Escolha a forma de pagamento.']]);
        }

        $aPrazo = in_array((string) ($post['a_prazo'] ?? ''), ['1', 'on', 'true'], true);
        $vencimento = date('Y-m-d');
        if ($aPrazo) {
            $vencimento = dataIsoParaYmd((string) ($post['vencimento'] ?? '')) ?? date('Y-m-d', strtotime('+30 days'));
            if ((int) $cliente->idClientes === (int) $this->pdv_model->clienteBalcao((int) ($this->data['configuration']['dt_cliente_balcao'] ?? 0))->idClientes) {
                return $this->json(422, ['result' => false, 'message' => 'Venda a prazo precisa de um cliente identificado.', 'erros' => ['cliente' => 'Escolha o cliente da venda a prazo.']]);
            }
        }

        $formaTexto = DAITECH_FORMAS_PAGAMENTO[$forma];
        $usuario = (int) $this->session->userdata('id_admin');
        $observacoes = trim('Venda PDV - Pagamento: ' . ($aPrazo ? 'a prazo (' . $formaTexto . ')' : $formaTexto) . "\n" . daitechObservacaoDosExtras($extras));

        $dados = [
            'dataVenda' => date('Y-m-d'),
            'clientes_id' => (int) $cliente->idClientes,
            'usuarios_id' => $usuario,
            'valorTotal' => $totais['bruto'],
            'desconto' => $totais['informado'],
            'valor_desconto' => $totais['desconto'] > 0 ? $totais['total'] : 0,
            'tipo_desconto' => $totais['tipo'],
            'faturado' => 1,
            'status' => 'Faturado',
            'observacoes' => $orcamento && trim((string) $orcamento->observacoes) !== '' ? $observacoes . "\n\n" . preg_replace('/^\[\+\].*$\n?/m', '', (string) $orcamento->observacoes) : $observacoes,
        ];

        $lancamento = [
            'descricao' => mb_substr('Venda PDV - ' . $cliente->nomeCliente, 0, 255),
            'valor' => $totais['bruto'],
            'tipo_desconto' => 'real',
            'desconto' => $totais['desconto'],
            'valor_desconto' => $totais['total'],
            'clientes_id' => (int) $cliente->idClientes,
            'cliente_fornecedor' => (string) $cliente->nomeCliente,
            'data_vencimento' => $vencimento,
            'data_pagamento' => $aPrazo ? null : date('Y-m-d'),
            'baixado' => $aPrazo ? 0 : 1,
            'forma_pgto' => $formaTexto,
            'tipo' => 'receita',
            'usuarios_id' => $usuario,
        ];

        $idVenda = $this->pdv_model->gravar($orcamento, $dados, $produtos, $extras, $controleEstoque, $lancamento);
        if ($idVenda === null) {
            return $this->json(500, ['result' => false, 'message' => 'Não foi possível gravar a venda. Nada foi salvo; tente de novo.']);
        }

        $this->db->where('vendas_id', $idVenda)->where('tipo', 'receita')->update('lancamentos', ['descricao' => mb_substr('Venda #' . $idVenda . ' (PDV) - ' . $cliente->nomeCliente, 0, 255)]);
        log_info(($orcamento ? 'Converteu o orçamento em venda no PDV' : 'Vendeu no PDV') . '. Venda ID: ' . $idVenda);

        return $this->json(201, [
            'result' => true,
            'message' => 'Venda #' . $idVenda . ' finalizada: ' . dinheiro($totais['total']) . ($aPrazo ? ' a receber em ' . dataBr($vencimento) : '') . '.',
            'idVenda' => $idVenda,
            'cupom' => site_url('pdv/cupom/' . $idVenda),
            'venda' => site_url('vendas/visualizar/' . $idVenda),
        ]);
    }

    /** Cupom 80mm da venda (produtos, serviços e avulsos). */
    public function cupom()
    {
        if (! $this->hasAnyPermission(['vVenda'])) {
            return $this->negarAcesso();
        }

        $venda = is_numeric($this->uri->segment(3)) ? $this->pdv_model->getVenda((int) $this->uri->segment(3)) : null;
        if (! $venda) {
            $this->session->set_flashdata('error', 'Venda não encontrada.');

            return redirect('vendas');
        }

        $this->load->model('mapos_model');
        $lancamento = $venda->lancamentos_id ? $this->db->where('idLancamentos', (int) $venda->lancamentos_id)->get('lancamentos', 1)->row() : null;

        $this->load->view('pdv/imprimirCupom', [
            'venda' => $venda,
            'produtos' => $this->pdv_model->produtosDaVenda((int) $venda->idVendas),
            'extras' => $this->pdv_model->extrasDaVenda((int) $venda->idVendas),
            'emitente' => $this->mapos_model->getEmitente(),
            'lancamento' => $lancamento,
        ]);
    }

    private function json(int $status, array $corpo)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo));
    }
}
