<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Acompanhamento público da OS (Daitech), o antigo link "os_direta" da 4.x:
 *
 *     index.php/acompanhar/os/1542?doc=12345678900
 *     index.php/mine/os_direta/1542?doc=12345678900   (rota antiga, config/routes.php)
 *
 * Mostra só o resumo da OS (status, aparelho, previsão, total e laudo) para
 * quem informa o número da OS e o CPF/CNPJ do cliente. Diferente da 4.x, o
 * link não faz login na área do cliente: é uma tela só de leitura. Erros
 * contam no limite de tentativas (Limite_login), como o login.
 */
class Acompanhar extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['os']);
    }

    public function index()
    {
        redirect('mine');
    }

    public function os($id = null)
    {
        $this->output->set_header('Cache-Control: no-store, private');
        $this->output->set_header('X-Robots-Tag: noindex');

        $id ??= $this->input->get('numero');
        $idOs = ctype_digit(trim((string) $id)) ? (int) trim((string) $id) : 0;
        $documento = preg_replace('/[^0-9A-Za-z]/', '', (string) $this->input->get('doc'));
        $ip = (string) $this->input->ip_address();
        $chave = 'acompanhar-os-' . $idOs; // conta no escopo da área do cliente

        $this->load->library('limite_login');
        $dados = ['idOs' => $idOs, 'os' => null, 'erro' => null, 'emitente' => null, 'documento' => (string) $this->input->get('doc')];

        if ($idOs > 0 && $documento !== '') {
            if ($this->limite_login->bloqueado('cliente', $chave, $ip)) {
                $dados['erro'] = 'Muitas tentativas. Aguarde alguns minutos e tente de novo.';
            } else {
                $os = $this->db->select('os.*, clientes.nomeCliente, clientes.documento')
                    ->from('os')
                    ->join('clientes', 'clientes.idClientes = os.clientes_id')
                    ->where('os.idOs', $idOs)
                    ->get()->row();

                $confere = $os && strlen($documento) >= 5
                    && hash_equals(strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $os->documento)), strtoupper($documento));

                if ($confere) {
                    $this->limite_login->registrarSucesso('cliente', $chave);
                    $this->load->model('os_model');
                    $this->load->model('mapos_model');
                    $produtos = $this->os_model->getProdutos($idOs);
                    $servicos = $this->os_model->getServicos($idOs);
                    $bruto = array_sum(array_map(static fn ($p) => (float) $p->subTotal, $produtos))
                        + array_sum(array_map(static fn ($s) => (float) ($s->subTotal ?? (($s->preco ?: 0) * ($s->quantidade ?: 1))), $servicos));
                    $dados['os'] = $os;
                    $dados['total'] = (float) $os->valor_desconto > 0 && (float) $os->valor_desconto < $bruto ? (float) $os->valor_desconto : round($bruto, 2);
                    $dados['emitente'] = $this->mapos_model->getEmitente();
                } else {
                    $this->limite_login->registrarFalha('cliente', $chave, $ip);
                    // A mesma mensagem para OS inexistente e documento errado.
                    $dados['erro'] = 'Não encontramos uma OS com este número e este CPF/CNPJ.';
                }
            }
        }

        $this->load->view('acompanhar/os', $dados);
    }
}
