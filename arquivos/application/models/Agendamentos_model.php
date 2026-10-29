<?php

/**
 * Agenda externa (Daitech): visitas técnicas na casa ou empresa do cliente.
 */
class Agendamentos_model extends CI_Model
{
    private string $tabela = 'agendamentos';

    private function selecionar(): void
    {
        $this->db->select('agendamentos.*, clientes.nomeCliente, clientes.celular, clientes.telefone, usuarios.nome AS nomeUsuario')
            ->from($this->tabela)
            ->join('clientes', 'clientes.idClientes = agendamentos.clientes_id', 'left')
            ->join('usuarios', 'usuarios.idUsuarios = agendamentos.usuarios_id', 'left');
    }

    /** Visitas do dia, pelo horário. */
    public function doDia(string $data): array
    {
        $this->selecionar();

        return $this->db->where('agendamentos.data_agendamento', $data)
            ->order_by('agendamentos.hora_agendamento', 'ASC')
            ->get()->result();
    }

    /** Quantas visitas há em cada dia do período (para a barra de dias). */
    public function contarPorDia(string $de, string $ate): array
    {
        $linhas = $this->db->select('data_agendamento, COUNT(*) AS total')
            ->where('data_agendamento >=', $de)
            ->where('data_agendamento <=', $ate)
            ->group_by('data_agendamento')
            ->get($this->tabela)->result();

        return array_column(array_map(static fn ($l) => (array) $l, $linhas), 'total', 'data_agendamento');
    }

    /** Visitas do período, para a agenda do painel. */
    public function doPeriodo(string $de, string $ate, ?int $usuario = null): array
    {
        $this->selecionar();
        $this->db->where('agendamentos.data_agendamento >=', $de)->where('agendamentos.data_agendamento <=', $ate);
        if ($usuario !== null) {
            $this->db->where('agendamentos.usuarios_id', $usuario);
        }

        return $this->db->order_by('agendamentos.data_agendamento', 'ASC')->order_by('agendamentos.hora_agendamento', 'ASC')->get()->result();
    }

    public function getById(int $id): ?object
    {
        $this->selecionar();

        return $this->db->where('agendamentos.idAgendamento', $id)->get()->row() ?: null;
    }

    public function add(array $dados): ?int
    {
        return $this->db->insert($this->tabela, $dados) ? (int) $this->db->insert_id() : null;
    }

    public function edit(int $id, array $dados): bool
    {
        return (bool) $this->db->where('idAgendamento', $id)->update($this->tabela, $dados);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->db->where('idAgendamento', $id)->delete($this->tabela);
    }

    public function clienteExiste(int $id): bool
    {
        return $id > 0 && $this->db->where('idClientes', $id)->count_all_results('clientes') > 0;
    }

    /** Endereço do cadastro do cliente, para sugerir na visita. */
    public function enderecoDoCliente(object $cliente): string
    {
        $rua = trim(implode(', ', array_filter([$cliente->rua ?? '', $cliente->numero ?? '', $cliente->complemento ?? ''], static fn ($v) => trim((string) $v) !== '')));
        $cidade = trim(implode(' - ', array_filter([$cliente->bairro ?? '', $cliente->cidade ?? '', $cliente->estado ?? ''], static fn ($v) => trim((string) $v) !== '')));

        return trim(implode(', ', array_filter([$rua, $cidade])));
    }

    /** Clientes para o autocomplete, no formato da lib/autocomplete.js. */
    public function autoCompleteCliente(string $termo): array
    {
        $linhas = $this->db->select('idClientes, nomeCliente, telefone, celular, documento, rua, numero, complemento, bairro, cidade, estado')
            ->group_start()
            ->like('nomeCliente', $termo)
            ->or_like('telefone', $termo)
            ->or_like('celular', $termo)
            ->or_like('documento', $termo)
            ->group_end()
            ->limit(25)
            ->get('clientes')->result();

        return array_map(fn ($c) => [
            'id' => (string) $c->idClientes,
            'valor' => (string) $c->nomeCliente,
            'detalhe' => implode(' · ', array_filter([(string) $c->documento, (string) ($c->celular ?: $c->telefone)], static fn ($v) => $v !== '')),
            'endereco' => $this->enderecoDoCliente($c),
        ], $linhas);
    }
}
