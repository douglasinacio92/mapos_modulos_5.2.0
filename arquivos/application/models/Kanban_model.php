<?php

/**
 * Kanban (Daitech): OS, visitas da agenda externa e tarefas internas em
 * colunas por status.
 */
class Kanban_model extends CI_Model
{
    /** Cartões por coluna; o resto fica no "ver todas" da coluna. */
    public const POR_COLUNA = 40;

    /**
     * OS por status, as mais recentes primeiro.
     *
     * @param  list<string>  $status
     * @return array<string, array{total: int, itens: list<object>}>
     */
    public function osPorStatus(array $status, array $filtros): array
    {
        $colunas = [];
        foreach ($status as $s) {
            $this->filtrarOs($filtros);
            $total = (int) $this->db->where('os.status', $s)->count_all_results('os');

            $this->db->select('os.idOs, os.status, os.dataInicial, os.dataFinal, os.descricaoProduto, os.defeito, os.usuarios_id, clientes.nomeCliente, clientes.celular, clientes.telefone, usuarios.nome AS nomeTecnico')
                ->join('clientes', 'clientes.idClientes = os.clientes_id', 'left')
                ->join('usuarios', 'usuarios.idUsuarios = os.usuarios_id', 'left');
            if ($this->db->field_exists('marca', 'os')) {
                $this->db->select('os.marca, os.modelo');
            }
            $this->filtrarOs($filtros);
            $itens = $this->db->where('os.status', $s)->order_by('os.idOs', 'DESC')->limit(self::POR_COLUNA)->get('os')->result();

            $colunas[$s] = ['total' => $total, 'itens' => $itens];
        }

        return $colunas;
    }

    private function filtrarOs(array $filtros): void
    {
        if (! empty($filtros['minhas'])) {
            $this->db->where('os.usuarios_id', (int) $filtros['usuario']);
        }
        if (($filtros['pesquisa'] ?? '') !== '') {
            $termo = $filtros['pesquisa'];
            $this->db->join('clientes c_busca', 'c_busca.idClientes = os.clientes_id', 'left');
            $this->db->group_start()->like('c_busca.nomeCliente', $termo)->or_like('os.descricaoProduto', $termo)->or_like('os.defeito', $termo);
            if (ctype_digit($termo)) {
                $this->db->or_where('os.idOs', (int) $termo);
            }
            $this->db->group_end();
        }
    }

    /** @return array<string, array{total: int, itens: list<object>}> */
    public function agendamentosPorStatus(array $filtros): array
    {
        $colunas = [];
        foreach (array_keys(DAITECH_AGENDAMENTO_STATUS) as $s) {
            $this->filtrarAgendamentos($filtros);
            $total = (int) $this->db->where('a.status', $s)->count_all_results('agendamentos a');

            $this->db->select('a.*, c.nomeCliente, c.celular, c.telefone, u.nome AS nomeTecnico')
                ->join('clientes c', 'c.idClientes = a.clientes_id', 'left')
                ->join('usuarios u', 'u.idUsuarios = a.usuarios_id', 'left');
            $this->filtrarAgendamentos($filtros);
            $fechado = in_array($s, ['Concluido', 'Cancelado'], true);
            $itens = $this->db->where('a.status', $s)
                ->order_by('a.data_agendamento', $fechado ? 'DESC' : 'ASC')
                ->order_by('a.hora_agendamento', 'ASC')
                ->limit(self::POR_COLUNA)
                ->get('agendamentos a')->result();

            $colunas[$s] = ['total' => $total, 'itens' => $itens];
        }

        return $colunas;
    }

    private function filtrarAgendamentos(array $filtros): void
    {
        if (! empty($filtros['minhas'])) {
            $this->db->where('a.usuarios_id', (int) $filtros['usuario']);
        }
        if (($filtros['pesquisa'] ?? '') !== '') {
            $this->db->join('clientes c_busca', 'c_busca.idClientes = a.clientes_id', 'left');
            $this->db->group_start()->like('c_busca.nomeCliente', $filtros['pesquisa'])->or_like('a.endereco', $filtros['pesquisa'])->or_like('a.descricao', $filtros['pesquisa'])->group_end();
        }
    }

    /** @return array<string, array{total: int, itens: list<object>}> */
    public function tarefasPorStatus(array $filtros): array
    {
        $colunas = [];
        foreach (array_keys(DAITECH_TAREFA_STATUS) as $s) {
            $this->filtrarTarefas($filtros);
            $total = (int) $this->db->where('t.status', $s)->count_all_results('daitech_tarefas t');

            $this->db->select('t.*, u.nome AS nomeResponsavel')
                ->join('usuarios u', 'u.idUsuarios = t.usuarios_id', 'left');
            $this->filtrarTarefas($filtros);
            $itens = $this->db->where('t.status', $s)
                ->order_by('t.dataLimite IS NULL', 'ASC', false)
                ->order_by('t.dataLimite', 'ASC')
                ->order_by('t.idTarefa', 'DESC')
                ->limit(self::POR_COLUNA)
                ->get('daitech_tarefas t')->result();

            $colunas[$s] = ['total' => $total, 'itens' => $itens];
        }

        return $colunas;
    }

    private function filtrarTarefas(array $filtros): void
    {
        if (! empty($filtros['minhas'])) {
            $this->db->where('t.usuarios_id', (int) $filtros['usuario']);
        }
        if (($filtros['pesquisa'] ?? '') !== '') {
            $this->db->group_start()->like('t.titulo', $filtros['pesquisa'])->or_like('t.descricao', $filtros['pesquisa'])->group_end();
        }
    }

    /**
     * Resumo do topo: OS em aberto, atrasadas e vencendo (hoje ou amanhã),
     * tarefas pendentes e visitas de hoje.
     *
     * @return array<string, int>
     */
    public function estatisticas(string $hoje): array
    {
        $fechados = ['Finalizado', 'Cancelado', 'Faturado'];
        $abertas = (int) $this->db->where_not_in('status', $fechados)->count_all_results('os');
        $atrasadas = (int) $this->db->where_not_in('status', $fechados)->where('dataFinal <', $hoje)->where('dataFinal >', '0000-00-00')->count_all_results('os');
        $vencendo = (int) $this->db->where_not_in('status', $fechados)->where('dataFinal >=', $hoje)->where('dataFinal <=', date('Y-m-d', strtotime($hoje . ' +1 day')))->count_all_results('os');

        return [
            'os_abertas' => $abertas,
            'os_atrasadas' => $atrasadas,
            'os_vencendo' => $vencendo,
            'tarefas' => (int) $this->db->where('status !=', 'CONCLUÍDO')->count_all_results('daitech_tarefas'),
            'visitas_hoje' => (int) $this->db->where('data_agendamento', $hoje)->where_not_in('status', ['Concluido', 'Cancelado'])->count_all_results('agendamentos'),
        ];
    }

    public function getOs(int $id): ?object
    {
        return $this->db->select('idOs, status, faturado')->where('idOs', $id)->get('os', 1)->row() ?: null;
    }

    public function getAgendamento(int $id): ?object
    {
        return $this->db->where('idAgendamento', $id)->get('agendamentos', 1)->row() ?: null;
    }

    public function getTarefa(int $id): ?object
    {
        return $this->db->where('idTarefa', $id)->get('daitech_tarefas', 1)->row() ?: null;
    }

    public function atualizar(string $tabela, string $chave, int $id, array $dados): bool
    {
        return (bool) $this->db->where($chave, $id)->update($tabela, $dados);
    }

    public function adicionarTarefa(array $dados): ?int
    {
        return $this->db->insert('daitech_tarefas', $dados) ? (int) $this->db->insert_id() : null;
    }

    public function excluirTarefa(int $id): bool
    {
        return (bool) $this->db->where('idTarefa', $id)->delete('daitech_tarefas');
    }

    /** Usuários ativos, para o responsável da tarefa. @return array<string, string> */
    public function usuariosAtivos(): array
    {
        $usuarios = $this->db->select('idUsuarios, nome')->where('situacao', 1)->order_by('nome', 'ASC')->get('usuarios')->result();

        return array_column(array_map(static fn ($u) => ['id' => (string) $u->idUsuarios, 'nome' => (string) $u->nome], $usuarios), 'nome', 'id');
    }
}
