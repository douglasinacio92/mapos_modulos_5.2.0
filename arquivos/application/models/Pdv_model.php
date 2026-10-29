<?php

/**
 * Carrinho do PDV e dos orçamentos (Daitech).
 *
 * Venda e orçamento são linhas de `vendas` (o orçamento com status
 * "Orçamento", como a venda nova do Map-OS). Produtos vão para
 * itens_de_vendas; serviços e itens avulsos, que itens_de_vendas não aceita,
 * para vendas_itens_extras, e também entram como texto nas observações para
 * aparecer nas telas do Map-OS.
 *
 * O estoque segue a regra das vendas do Map-OS v5: com o controle de estoque
 * ligado, o produto sai do estoque quando entra na venda (ou no orçamento) e
 * volta quando sai dela.
 */
class Pdv_model extends CI_Model
{
    /** Produtos e serviços para a busca do caixa, no formato do autocomplete. */
    public function buscar(string $termo, bool $comServicos = true): array
    {
        $exato = $this->db->select('idProdutos, descricao, precoVenda, estoque, codDeBarra')
            ->group_start()->where('codDeBarra', $termo);
        if (ctype_digit($termo)) {
            $exato->or_where('idProdutos', (int) $termo);
        }
        $exatos = $exato->group_end()->limit(5)->get('produtos')->result();

        $produtos = $this->db->select('idProdutos, descricao, precoVenda, estoque, codDeBarra')
            ->group_start()->like('descricao', $termo)->or_like('codDeBarra', $termo)->group_end()
            ->order_by('descricao', 'ASC')->limit(20)->get('produtos')->result();

        $vistos = [];
        $itens = [];
        foreach ([...$exatos, ...$produtos] as $p) {
            if (isset($vistos[$p->idProdutos])) {
                continue;
            }
            $vistos[$p->idProdutos] = true;
            $itens[] = [
                'id' => 'produto-' . $p->idProdutos,
                'valor' => (string) $p->descricao,
                'detalhe' => 'Produto · ' . dinheiro($p->precoVenda) . ' · Estoque: ' . (int) $p->estoque . ($p->codDeBarra ? ' · ' . $p->codDeBarra : ''),
                'tipo' => 'produto',
                'cadastro' => (int) $p->idProdutos,
                'preco' => (float) $p->precoVenda,
                'estoque' => (int) $p->estoque,
                'exato' => $p->codDeBarra === $termo,
            ];
        }

        if ($comServicos) {
            $servicos = $this->db->select('idServicos, nome, preco')->like('nome', $termo)->order_by('nome', 'ASC')->limit(10)->get('servicos')->result();
            foreach ($servicos as $s) {
                $itens[] = [
                    'id' => 'servico-' . $s->idServicos,
                    'valor' => (string) $s->nome,
                    'detalhe' => 'Serviço · ' . dinheiro($s->preco),
                    'tipo' => 'servico',
                    'cadastro' => (int) $s->idServicos,
                    'preco' => (float) $s->preco,
                    'estoque' => null,
                    'exato' => false,
                ];
            }
        }

        return $itens;
    }

    /**
     * Confere os itens do navegador no cadastro e calcula as linhas.
     *
     * @param  list<array>       $itens       daitechItensDoCarrinho()
     * @param  array<int, int>   $reservados  Quantidade de cada produto que já está na venda editada (volta ao estoque antes)
     * @return array{0: list<array>, 1: list<array>, 2: float, 3: array<string, string>} produtos, extras, bruto, erros
     */
    public function montar(array $itens, bool $controleEstoque, array $reservados = []): array
    {
        $produtos = [];
        $extras = [];
        $bruto = 0.0;
        $pedidos = [];

        foreach ($itens as $i => $item) {
            $linha = $i + 1;
            if ($item['tipo'] === 'produto') {
                $cadastro = $this->db->select('idProdutos, descricao, precoVenda, estoque')->where('idProdutos', $item['id'])->get('produtos', 1)->row();
                if (! $cadastro) {
                    return [[], [], 0.0, ['itens' => 'O produto da linha ' . $linha . ' não existe mais. Remova-o do carrinho.']];
                }
                $quantidade = (int) $item['quantidade'];
                $pedidos[$cadastro->idProdutos] = ($pedidos[$cadastro->idProdutos] ?? 0) + $quantidade;
                if ($controleEstoque && $pedidos[$cadastro->idProdutos] > (int) $cadastro->estoque + (int) ($reservados[$cadastro->idProdutos] ?? 0)) {
                    return [[], [], 0.0, ['itens' => 'Estoque insuficiente de ' . $cadastro->descricao . ': há ' . (int) $cadastro->estoque . '.']];
                }
                $preco = $item['preco'] ?? round((float) $cadastro->precoVenda, 2);
                $subTotal = round($preco * $quantidade, 2);
                $produtos[] = [
                    'produtos_id' => (int) $cadastro->idProdutos,
                    'descricao' => (string) $cadastro->descricao,
                    'quantidade' => $quantidade,
                    'preco' => $preco,
                    'subTotal' => $subTotal,
                ];
            } elseif ($item['tipo'] === 'servico') {
                $cadastro = $this->db->select('idServicos, nome, preco')->where('idServicos', $item['id'])->get('servicos', 1)->row();
                if (! $cadastro) {
                    return [[], [], 0.0, ['itens' => 'O serviço da linha ' . $linha . ' não existe mais. Remova-o do carrinho.']];
                }
                $preco = $item['preco'] ?? round((float) $cadastro->preco, 2);
                $subTotal = round($preco * $item['quantidade'], 2);
                $extras[] = [
                    'tipo' => 'servico',
                    'servicos_id' => (int) $cadastro->idServicos,
                    'descricao' => (string) $cadastro->nome,
                    'quantidade' => $item['quantidade'],
                    'preco' => $preco,
                    'subTotal' => $subTotal,
                ];
            } else {
                $subTotal = round($item['preco'] * $item['quantidade'], 2);
                $extras[] = [
                    'tipo' => 'avulso',
                    'servicos_id' => null,
                    'descricao' => $item['descricao'],
                    'quantidade' => $item['quantidade'],
                    'preco' => $item['preco'],
                    'subTotal' => $subTotal,
                ];
            }
            $bruto += $subTotal;
        }

        return [$produtos, $extras, round($bruto, 2), []];
    }

    /**
     * Grava a venda (nova ou a editada), os itens, o estoque e, quando
     * houver, o lançamento, numa transação.
     *
     * @param  array|null  $lancamento  Linha de lancamentos (sem vendas_id), ou null para orçamento
     */
    public function gravar(?object $venda, array $dados, array $produtos, array $extras, bool $controleEstoque, ?array $lancamento): ?int
    {
        $this->load->model('produtos_model');
        $this->db->trans_start();

        if ($venda === null) {
            $this->db->insert('vendas', $dados);
            $idVenda = (int) $this->db->insert_id();
        } else {
            $idVenda = (int) $venda->idVendas;
            if ($controleEstoque && strtolower((string) $venda->status) !== 'cancelado') {
                foreach ($this->produtosDaVenda($idVenda) as $antigo) {
                    $this->produtos_model->updateEstoque($antigo->produtos_id, $antigo->quantidade, '+');
                }
            }
            $this->db->where('vendas_id', $idVenda)->delete('itens_de_vendas');
            $this->db->where('vendas_id', $idVenda)->delete('vendas_itens_extras');
            $this->db->where('idVendas', $idVenda)->update('vendas', $dados);
        }

        foreach ($produtos as $p) {
            $this->db->insert('itens_de_vendas', [
                'vendas_id' => $idVenda,
                'produtos_id' => $p['produtos_id'],
                'quantidade' => $p['quantidade'],
                'preco' => $p['preco'],
                'subTotal' => $p['subTotal'],
            ]);
            if ($controleEstoque) {
                $this->produtos_model->updateEstoque($p['produtos_id'], $p['quantidade'], '-');
            }
        }

        foreach ($extras as $x) {
            $this->db->insert('vendas_itens_extras', $x + ['vendas_id' => $idVenda]);
        }

        if ($lancamento !== null && $idVenda > 0) {
            $this->db->insert('lancamentos', $lancamento + ['vendas_id' => $idVenda]);
            $this->db->where('idVendas', $idVenda)->update('vendas', ['lancamentos_id' => (int) $this->db->insert_id()]);
        }

        $this->db->trans_complete();

        return $this->db->trans_status() && $idVenda > 0 ? $idVenda : null;
    }

    public function getVenda(int $id): ?object
    {
        return $this->db->select('vendas.*, clientes.nomeCliente, clientes.documento, clientes.celular, clientes.telefone, clientes.email, clientes.rua, clientes.numero, clientes.bairro, clientes.cidade, clientes.estado, clientes.cep, usuarios.nome AS vendedor')
            ->from('vendas')
            ->join('clientes', 'clientes.idClientes = vendas.clientes_id', 'left')
            ->join('usuarios', 'usuarios.idUsuarios = vendas.usuarios_id', 'left')
            ->where('vendas.idVendas', $id)
            ->get()->row() ?: null;
    }

    public function produtosDaVenda(int $idVenda): array
    {
        return $this->db->select('itens_de_vendas.*, produtos.descricao, produtos.unidade, produtos.codDeBarra, produtos.estoque')
            ->from('itens_de_vendas')
            ->join('produtos', 'produtos.idProdutos = itens_de_vendas.produtos_id', 'left')
            ->where('itens_de_vendas.vendas_id', $idVenda)
            ->order_by('itens_de_vendas.idItens', 'ASC')
            ->get()->result();
    }

    public function extrasDaVenda(int $idVenda): array
    {
        if (! $this->db->table_exists('vendas_itens_extras')) {
            return [];
        }

        return $this->db->where('vendas_id', $idVenda)->order_by('idExtra', 'ASC')->get('vendas_itens_extras')->result();
    }

    /** Itens da venda no formato do carrinho (para editar o orçamento ou levá-lo ao PDV). */
    public function carrinhoDaVenda(int $idVenda): array
    {
        $itens = [];
        foreach ($this->produtosDaVenda($idVenda) as $p) {
            $itens[] = ['tipo' => 'produto', 'id' => (int) $p->produtos_id, 'descricao' => (string) ($p->descricao ?? 'Produto removido'), 'quantidade' => (float) $p->quantidade, 'preco' => (float) $p->preco, 'estoque' => $p->estoque === null ? null : (int) $p->estoque];
        }
        foreach ($this->extrasDaVenda($idVenda) as $x) {
            $itens[] = ['tipo' => $x->tipo === 'servico' ? 'servico' : 'avulso', 'id' => $x->servicos_id ? (int) $x->servicos_id : null, 'descricao' => (string) $x->descricao, 'quantidade' => (float) $x->quantidade, 'preco' => (float) $x->preco, 'estoque' => null];
        }

        return $itens;
    }

    /** Quantidade de cada produto já na venda: o que volta ao estoque ao regravar. @return array<int, int> */
    public function reservados(int $idVenda): array
    {
        $reservados = [];
        foreach ($this->produtosDaVenda($idVenda) as $p) {
            $reservados[(int) $p->produtos_id] = ($reservados[(int) $p->produtos_id] ?? 0) + (int) $p->quantidade;
        }

        return $reservados;
    }

    public function getCliente(int $id): ?object
    {
        return $id > 0 ? ($this->db->select('idClientes, nomeCliente, documento, celular, telefone')->where('idClientes', $id)->get('clientes', 1)->row() ?: null) : null;
    }

    /**
     * Cliente das vendas sem cliente escolhido ("consumidor final"): o
     * configurado em Módulos Daitech, o cadastro "BALCAO"/"Consumidor final"
     * ou, sem nenhum, um cadastro novo.
     */
    public function clienteBalcao(int $configurado): object
    {
        $cliente = $this->getCliente($configurado);
        if ($cliente) {
            return $cliente;
        }

        $cliente = $this->db->select('idClientes, nomeCliente, documento, celular, telefone')
            ->where("UPPER(nomeCliente) IN ('BALCAO', 'BALCÃO', 'CONSUMIDOR FINAL')", null, false)
            ->order_by('idClientes', 'ASC')->get('clientes', 1)->row();
        if ($cliente) {
            return $cliente;
        }

        $this->db->insert('clientes', ['nomeCliente' => 'Consumidor final', 'dataCadastro' => date('Y-m-d'), 'fornecedor' => 0]);

        return $this->getCliente((int) $this->db->insert_id());
    }

    /**
     * O que o PDV e o orçamento têm em comum ao gravar: itens conferidos no
     * cadastro, totais com o desconto e o cliente.
     *
     * @return array{0: list<array>, 1: list<array>, 2: array|null, 3: object|null, 4: array<string, string>}
     */
    public function prepararDoPost(array $post, bool $controleEstoque, ?object $venda, int $clienteBalcao): array
    {
        [$itens, $erros] = daitechItensDoCarrinho($post['itens'] ?? null);
        if ($erros !== []) {
            return [[], [], null, null, $erros];
        }

        $reservados = $venda !== null && strtolower((string) $venda->status) !== 'cancelado' ? $this->reservados((int) $venda->idVendas) : [];
        [$produtos, $extras, $bruto, $erros] = $this->montar($itens, $controleEstoque, $reservados);
        if ($erros !== []) {
            return [[], [], null, null, $erros];
        }

        [$totais, $erros] = daitechDesconto($bruto, $post['tipo_desconto'] ?? null, $post['desconto'] ?? null);
        if ($erros !== []) {
            return [[], [], null, null, $erros];
        }

        $idCliente = is_scalar($post['clientes_id'] ?? null) ? (int) $post['clientes_id'] : 0;
        $cliente = $idCliente > 0 ? $this->getCliente($idCliente) : $this->clienteBalcao($clienteBalcao);
        if (! $cliente) {
            return [[], [], null, null, ['cliente' => 'Escolha um cliente da lista.']];
        }

        return [$produtos, $extras, $totais, $cliente, []];
    }

    /** Orçamentos (vendas com status Orçamento), com o total. */
    public function listarOrcamentos(array $filtros, int $limite, int $offset): array
    {
        $this->filtrarOrcamentos($filtros);

        return $this->db->select('vendas.idVendas, vendas.dataVenda, vendas.valorTotal, vendas.valor_desconto, vendas.clientes_id, clientes.nomeCliente, usuarios.nome AS vendedor')
            ->order_by('vendas.idVendas', 'DESC')
            ->limit($limite, $offset)
            ->get()->result();
    }

    public function contarOrcamentos(array $filtros): int
    {
        $this->filtrarOrcamentos($filtros);

        return (int) $this->db->count_all_results();
    }

    private function filtrarOrcamentos(array $filtros): void
    {
        $this->db->from('vendas')
            ->join('clientes', 'clientes.idClientes = vendas.clientes_id', 'left')
            ->join('usuarios', 'usuarios.idUsuarios = vendas.usuarios_id', 'left')
            ->where('vendas.status', 'Orçamento');

        if (($filtros['pesquisa'] ?? '') !== '') {
            $termo = $filtros['pesquisa'];
            $this->db->group_start()->like('clientes.nomeCliente', $termo)->or_like('clientes.documento', $termo);
            if (ctype_digit($termo)) {
                $this->db->or_where('vendas.idVendas', (int) $termo);
            }
            $this->db->group_end();
        }
        if (isset($filtros['de'])) {
            $this->db->where('vendas.dataVenda >=', $filtros['de']);
        }
        if (isset($filtros['ate'])) {
            $this->db->where('vendas.dataVenda <=', $filtros['ate']);
        }
    }

    /** Apaga o orçamento, devolvendo o estoque reservado. */
    public function excluirOrcamento(object $venda, bool $controleEstoque): bool
    {
        $this->load->model('produtos_model');
        $id = (int) $venda->idVendas;
        $this->db->trans_start();
        if ($controleEstoque) {
            foreach ($this->produtosDaVenda($id) as $p) {
                $this->produtos_model->updateEstoque($p->produtos_id, $p->quantidade, '+');
            }
        }
        $this->db->where('vendas_id', $id)->delete('itens_de_vendas');
        $this->db->where('vendas_id', $id)->delete('vendas_itens_extras');
        $this->db->where('idVendas', $id)->delete('vendas');
        $this->db->trans_complete();

        return (bool) $this->db->trans_status();
    }
}
