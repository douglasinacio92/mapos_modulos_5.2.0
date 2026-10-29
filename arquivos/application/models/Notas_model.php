<?php

/**
 * Mural de notas (Daitech): recados entre a equipe, com aviso de não lidas
 * para cada usuário (mural_notificacoes).
 */
class Notas_model extends CI_Model
{
    public function salvar(string $texto, int $remetente): ?int
    {
        $this->db->trans_start();
        $this->db->insert('mural_notas', [
            'usuarios_id' => $remetente,
            'texto' => $texto,
            'data_postagem' => date('Y-m-d H:i:s'),
        ]);
        $idNota = (int) $this->db->insert_id();

        $outros = $this->db->select('idUsuarios')->where('idUsuarios !=', $remetente)->where('situacao', 1)->get('usuarios')->result();
        $avisos = array_map(static fn ($u) => ['idNota' => $idNota, 'usuarios_id' => (int) $u->idUsuarios, 'lido' => 0], $outros);
        if ($avisos !== []) {
            $this->db->insert_batch('mural_notificacoes', $avisos);
        }
        $this->db->trans_complete();

        return $this->db->trans_status() && $idNota > 0 ? $idNota : null;
    }

    /** As últimas notas, da mais nova para a mais antiga. */
    public function listar(int $limite = 50): array
    {
        return $this->db->select('mural_notas.idNota, mural_notas.usuarios_id, mural_notas.texto, mural_notas.data_postagem, usuarios.nome')
            ->from('mural_notas')
            ->join('usuarios', 'usuarios.idUsuarios = mural_notas.usuarios_id', 'left')
            ->order_by('mural_notas.data_postagem', 'DESC')
            ->order_by('mural_notas.idNota', 'DESC')
            ->limit($limite)
            ->get()->result();
    }

    public function getById(int $id): ?object
    {
        return $this->db->where('idNota', $id)->get('mural_notas', 1)->row() ?: null;
    }

    public function naoLidas(int $usuario): int
    {
        return (int) $this->db->where('usuarios_id', $usuario)->where('lido', 0)->count_all_results('mural_notificacoes');
    }

    public function ultimaNaoLida(int $usuario): ?object
    {
        return $this->db->select('mural_notas.idNota, mural_notas.texto, usuarios.nome')
            ->from('mural_notificacoes')
            ->join('mural_notas', 'mural_notas.idNota = mural_notificacoes.idNota')
            ->join('usuarios', 'usuarios.idUsuarios = mural_notas.usuarios_id', 'left')
            ->where('mural_notificacoes.usuarios_id', $usuario)
            ->where('mural_notificacoes.lido', 0)
            ->order_by('mural_notas.idNota', 'DESC')
            ->limit(1)
            ->get()->row() ?: null;
    }

    public function marcarLidas(int $usuario): void
    {
        $this->db->where('usuarios_id', $usuario)->where('lido', 0)->update('mural_notificacoes', ['lido' => 1]);
    }

    public function excluir(int $id): bool
    {
        $this->db->where('idNota', $id)->delete('mural_notificacoes');

        return (bool) $this->db->where('idNota', $id)->delete('mural_notas');
    }
}
