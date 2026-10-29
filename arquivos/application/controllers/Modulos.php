<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Configurações dos módulos Daitech (Configurações > Módulos Daitech), com a
 * permissão de configurar o sistema (cSistema): termos impressos na OS,
 * mensagem de confirmação da visita, validade do orçamento e o cliente das
 * vendas de balcão do PDV. As chaves da Evolution API e do agente ficam no
 * application/.env; a tela só mostra se estão configuradas.
 */
class Modulos extends MY_Controller
{
    private const CAMPOS = [
        'dt_termos_os' => 3000,
        'dt_msg_agendamento' => 2000,
        'dt_validade_orc' => 60,
    ];

    public function index()
    {
        if (! $this->hasAnyPermission(['cSistema'])) {
            return $this->negarAcesso();
        }

        $erros = [];

        if ($this->input->method() === 'post') {
            $valores = [];
            foreach (self::CAMPOS as $campo => $maximo) {
                $valor = trim(str_replace("\r\n", "\n", (string) $this->input->post($campo)));
                if (mb_strlen($valor) > $maximo) {
                    $erros[$campo] = 'Use no máximo ' . $maximo . ' caracteres.';
                }
                $valores[$campo] = $valor;
            }

            $balcao = trim((string) $this->input->post('dt_cliente_balcao'));
            if ($balcao !== '' && (! ctype_digit($balcao) || $this->db->where('idClientes', (int) $balcao)->count_all_results('clientes') === 0)) {
                $erros['cliente_balcao'] = 'Escolha um cliente da lista ou deixe em branco.';
            }
            $valores['dt_cliente_balcao'] = $balcao;

            if ($erros === []) {
                foreach ($valores as $config => $valor) {
                    if ($this->db->where('config', $config)->count_all_results('configuracoes') > 0) {
                        $this->db->where('config', $config)->update('configuracoes', ['valor' => $valor]);
                    } else {
                        $this->db->insert('configuracoes', ['config' => $config, 'valor' => $valor]);
                    }
                }
                log_info('Alterou as configurações dos módulos Daitech.');
                $this->session->set_flashdata('success', 'Configurações dos módulos salvas.');

                return redirect('modulos');
            }
        }

        $config = $this->data['configuration'];
        $idBalcao = (int) ($this->input->method() === 'post' ? $this->input->post('dt_cliente_balcao') : ($config['dt_cliente_balcao'] ?? 0));
        $balcao = $idBalcao > 0 ? $this->db->select('idClientes, nomeCliente')->where('idClientes', $idBalcao)->get('clientes', 1)->row() : null;

        $this->data['valores'] = [
            'dt_termos_os' => $this->input->method() === 'post' ? (string) $this->input->post('dt_termos_os') : (string) ($config['dt_termos_os'] ?? ''),
            'dt_msg_agendamento' => $this->input->method() === 'post' ? (string) $this->input->post('dt_msg_agendamento') : (string) ($config['dt_msg_agendamento'] ?? ''),
            'dt_validade_orc' => $this->input->method() === 'post' ? (string) $this->input->post('dt_validade_orc') : (string) ($config['dt_validade_orc'] ?? ''),
            'balcao_id' => $balcao ? (string) $balcao->idClientes : '',
            'balcao_nome' => $balcao ? (string) $balcao->nomeCliente : '',
        ];
        $this->data['erros'] = $erros;
        $this->data['integracoes'] = [
            'evolution' => daitechConfig('EVOLUTION_API_URL') !== '' && daitechConfig('EVOLUTION_API_KEY') !== '',
            'agente' => strlen(daitechConfig('AGENTE_API_TOKEN')) >= 24,
        ];
        $this->data['banco'] = $this->db->table_exists('vendas_itens_extras') && $this->db->field_exists('marca', 'os');
        $this->data['view'] = 'modulos/modulos';

        return $this->layout();
    }
}
