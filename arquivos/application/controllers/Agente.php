<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * API do agente de atendimento (Daitech), usada pelo n8n/WhatsApp para
 * consultar OS, clientes, produtos e serviços. Só leitura.
 *
 * Autenticação: cabeçalho "Authorization: Bearer <AGENTE_API_TOKEN>", com o
 * token no application/.env (pelo menos 24 caracteres). Sem o token
 * configurado, todas as rotas respondem 503; com o token errado, 401. Na
 * 4.x o token ficava no código e a resposta 401 não parava a execução: o
 * dado saía mesmo com o token errado.
 *
 *     GET agente/buscar_os/1542
 *     GET agente/buscar_cliente?telefone=51999999999
 *     GET agente/buscar_ordens_cliente/15
 *     GET agente/buscar_produto?q=teclado
 *     GET agente/buscar_servico?q=formatacao
 */
class Agente extends CI_Controller
{
    private const TOKEN_MINIMO = 24;

    public function __construct()
    {
        parent::__construct();

        $token = daitechConfig('AGENTE_API_TOKEN');
        if (strlen($token) < self::TOKEN_MINIMO) {
            $this->parar(503, 'API do agente desligada: defina AGENTE_API_TOKEN (24+ caracteres) no application/.env.');
        }

        $cabecalho = (string) ($this->input->get_request_header('Authorization', true) ?? '');
        $enviado = preg_match('/^Bearer\s+(.+)$/i', trim($cabecalho), $m) ? trim($m[1]) : '';
        if ($enviado === '' || ! hash_equals($token, $enviado)) {
            $this->parar(401, 'Acesso não autorizado. Token inválido.');
        }

        $this->load->model('os_model');
    }

    public function buscar_os($idOs = null)
    {
        if (! ctype_digit((string) $idOs)) {
            return $this->responder(400, ['erro' => 'ID da OS inválido.']);
        }

        $os = $this->os_model->getById((int) $idOs);
        if (! $os) {
            return $this->responder(404, ['erro' => 'OS não localizada.']);
        }

        $produtos = $this->os_model->getProdutos((int) $idOs);
        $servicos = $this->os_model->getServicos((int) $idOs);
        $bruto = array_sum(array_map(static fn ($p) => (float) $p->subTotal, $produtos))
            + array_sum(array_map(static fn ($s) => (float) ($s->subTotal ?? (($s->preco ?: 0) * ($s->quantidade ?: 1))), $servicos));
        $total = (float) $os->valor_desconto > 0 && (float) $os->valor_desconto < $bruto ? (float) $os->valor_desconto : $bruto;

        return $this->responder(200, [
            'idOs' => (int) $os->idOs,
            'cliente' => $os->nomeCliente,
            'status' => $os->status,
            'equipamento' => daitechEquipamento($os) ?: trim(strip_tags((string) $os->descricaoProduto)),
            'problema_relatado' => trim(strip_tags((string) $os->defeito)),
            'laudo_tecnico' => trim(strip_tags((string) $os->laudoTecnico)),
            'valor_total' => number_format($total, 2, ',', '.'),
            'faturado' => (int) $os->faturado === 1 ? 'Sim' : 'Nao',
            'data_inicial' => $os->dataInicial,
            'previsao' => $os->dataFinal,
        ]);
    }

    public function buscar_cliente()
    {
        $telefone = preg_replace('/\D/', '', (string) $this->input->get('telefone'));
        if (strlen($telefone) < 8) {
            return $this->responder(400, ['erro' => 'Telefone não informado.']);
        }

        // O WhatsApp manda com o 55 na frente; o cadastro costuma ter só DDD + número.
        $sem55 = strlen($telefone) > 11 && str_starts_with($telefone, '55') ? substr($telefone, 2) : $telefone;
        $limpar = static fn (string $coluna) => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$coluna}, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '')";
        $cliente = $this->db->query(
            'SELECT idClientes, nomeCliente, documento FROM clientes WHERE ' . $limpar('celular') . ' LIKE ? OR ' . $limpar('telefone') . ' LIKE ? ORDER BY idClientes DESC LIMIT 1',
            ['%' . $sem55, '%' . $sem55]
        )->row();

        if (! $cliente) {
            return $this->responder(404, ['erro' => 'Cliente não localizado.']);
        }

        return $this->responder(200, [
            'idCliente' => (int) $cliente->idClientes,
            'nome' => $cliente->nomeCliente,
            'documento' => $cliente->documento,
        ]);
    }

    public function buscar_ordens_cliente($idCliente = null)
    {
        if (! ctype_digit((string) $idCliente)) {
            return $this->responder(400, ['erro' => 'ID do cliente inválido.']);
        }

        $this->db->select('idOs, status, dataInicial, dataFinal, descricaoProduto')->where('clientes_id', (int) $idCliente)->order_by('idOs', 'DESC')->limit(5);
        if ($this->db->field_exists('marca', 'os')) {
            $this->db->select('marca, modelo');
        }
        $ordens = $this->db->get('os')->result();

        if ($ordens === []) {
            return $this->responder(404, ['erro' => 'Nenhuma OS encontrada para este cliente.']);
        }

        return $this->responder(200, array_map(static fn ($os) => [
            'idOs' => (int) $os->idOs,
            'status' => $os->status,
            'equipamento' => daitechEquipamento($os) ?: trim(strip_tags((string) $os->descricaoProduto)),
            'data_inicial' => $os->dataInicial,
            'previsao' => $os->dataFinal,
        ], $ordens));
    }

    public function buscar_produto()
    {
        $termo = mb_substr(trim((string) $this->input->get('q')), 0, 100);
        if ($termo === '') {
            return $this->responder(400, ['erro' => 'Informe o termo de pesquisa.']);
        }

        $produtos = $this->db->select('descricao, precoVenda, estoque')->like('descricao', $termo)->limit(5)->get('produtos')->result();
        if ($produtos === []) {
            return $this->responder(404, ['erro' => 'Nenhum produto encontrado.']);
        }

        return $this->responder(200, array_map(static fn ($p) => [
            'descricao' => $p->descricao,
            'preco' => number_format((float) $p->precoVenda, 2, ',', '.'),
            'estoque' => (int) $p->estoque > 0 ? 'Disponível' : 'Em falta',
        ], $produtos));
    }

    public function buscar_servico()
    {
        $termo = mb_substr(trim((string) $this->input->get('q')), 0, 100);
        if ($termo === '') {
            return $this->responder(400, ['erro' => 'Informe o termo de pesquisa.']);
        }

        $servicos = $this->db->select('nome, preco')->like('nome', $termo)->limit(5)->get('servicos')->result();
        if ($servicos === []) {
            return $this->responder(404, ['erro' => 'Nenhum serviço encontrado.']);
        }

        return $this->responder(200, array_map(static fn ($s) => [
            'servico' => $s->nome,
            'preco' => number_format((float) $s->preco, 2, ',', '.'),
        ], $servicos));
    }

    private function responder(int $status, array $corpo)
    {
        return $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($corpo, JSON_UNESCAPED_UNICODE));
    }

    /** Responde e encerra: nenhum método roda depois de uma recusa. */
    private function parar(int $status, string $mensagem): void
    {
        $this->responder($status, ['erro' => $mensagem]);
        $this->output->_display();
        exit;
    }
}
