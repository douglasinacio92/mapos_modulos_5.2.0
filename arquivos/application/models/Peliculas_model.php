<?php

/**
 * Compatibilidade de películas (Daitech): modelo do aparelho e os modelos que
 * usam a mesma película.
 */
class Peliculas_model extends CI_Model
{
    private string $tabela = 'peliculas';

    private function filtrar(array $filtros): void
    {
        if (($filtros['pesquisa'] ?? '') !== '') {
            $this->db->group_start()
                ->like('modelo', $filtros['pesquisa'])
                ->or_like('compativeis', $filtros['pesquisa'])
                ->group_end();
        }
    }

    public function listar(array $filtros, int $limite, int $offset): array
    {
        $this->filtrar($filtros);

        return $this->db->order_by('modelo', 'ASC')->limit($limite, $offset)->get($this->tabela)->result();
    }

    public function contar(array $filtros): int
    {
        $this->filtrar($filtros);

        return (int) $this->db->count_all_results($this->tabela);
    }

    public function getById(int $id): ?object
    {
        return $this->db->where('id', $id)->get($this->tabela, 1)->row() ?: null;
    }

    public function add(array $dados): bool
    {
        return (bool) $this->db->insert($this->tabela, $dados);
    }

    public function edit(int $id, array $dados): bool
    {
        return (bool) $this->db->where('id', $id)->update($this->tabela, $dados);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->db->where('id', $id)->delete($this->tabela);
    }
}
