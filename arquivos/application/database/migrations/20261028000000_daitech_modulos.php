<?php

/**
 * Módulos Daitech para o Map-OS v5: agenda externa, Kanban (tarefas), mural
 * de notas, películas, PDV, orçamentos e os dados do aparelho na OS.
 *
 * Idempotente: quem vem da 4.x com o dump da Daitech já tem as tabelas e as
 * colunas (agendamentos, peliculas, mural_*, daitech_tarefas, os.marca...);
 * nada é recriado nem apagado. Numa instalação nova, tudo é criado.
 *
 * - vendas_itens_extras: serviços e itens avulsos do PDV e dos orçamentos
 *   (itens_de_vendas só aceita produto do cadastro).
 * - configuracoes: termos impressos na OS, mensagem de confirmação do
 *   agendamento, validade do orçamento e o cliente "balcão" do PDV (o
 *   cadastro BALCAO/Consumidor final, se existir).
 * - permissoes: os módulos Agenda (vAgendamento...) e Películas
 *   (vPelicula...) entram nos grupos que já têm as permissões equivalentes da
 *   OS e dos produtos, para ninguém perder o acesso que tinha na 4.x.
 *
 * O down() só tira as configurações e a tabela nova de extras: as tabelas e
 * colunas com dados da 4.x ficam, para um rollback não apagar a agenda.
 */
class Migration_daitech_modulos extends CI_Migration
{
    private const TERMOS_PADRAO = "Orçamentos não aprovados terão uma taxa de R$ 50,00.\nEquipamentos com mais de 30 dias na loja pagam taxa de armazenamento de R$ 5,00 por dia; após 90 dias serão descartados.";

    private const MENSAGEM_AGENDAMENTO = "*Confirmação de agendamento - {EMITENTE}*\n\nOlá, *{CLIENTE}*! Confirmamos sua visita técnica:\n\n*Data:* {DATA}\n*Horário:* {HORA}\n*Endereço:* {ENDERECO}\n\nCaso precise desmarcar ou alterar o horário, avise com antecedência. Até logo!";

    public function up()
    {
        $this->criarTabelas();
        $this->criarColunasDaOs();
        $this->criarConfiguracoes();
        $this->normalizarStatusDosAgendamentos();
        $this->concederPermissoes();
    }

    public function down()
    {
        $this->db->where_in('config', ['dt_termos_os', 'dt_msg_agendamento', 'dt_validade_orc', 'dt_cliente_balcao'])->delete('configuracoes');
        $this->dbforge->drop_table('vendas_itens_extras', true);
    }

    private function criarTabelas(): void
    {
        $tabelas = [
            'agendamentos' => 'CREATE TABLE IF NOT EXISTS `agendamentos` (
                `idAgendamento` INT(11) NOT NULL AUTO_INCREMENT,
                `data_agendamento` DATE NOT NULL,
                `hora_agendamento` VARCHAR(10) NOT NULL,
                `endereco` VARCHAR(255) NULL DEFAULT NULL,
                `descricao` TEXT NULL,
                `status` VARCHAR(45) NOT NULL,
                `clientes_id` INT(11) NOT NULL,
                `usuarios_id` INT(11) NOT NULL,
                PRIMARY KEY (`idAgendamento`),
                KEY `idx_agendamentos_data` (`data_agendamento`)
            ) ENGINE=InnoDB',
            'daitech_tarefas' => "CREATE TABLE IF NOT EXISTS `daitech_tarefas` (
                `idTarefa` INT(11) NOT NULL AUTO_INCREMENT,
                `titulo` VARCHAR(255) NOT NULL,
                `descricao` TEXT NULL,
                `prioridade` ENUM('Baixa','Media','Alta') NULL DEFAULT 'Baixa',
                `dataLimite` DATE NULL DEFAULT NULL,
                `status` VARCHAR(50) NULL DEFAULT 'A FAZER',
                `usuarios_id` INT(11) NOT NULL,
                `clientes_id` INT(11) NULL DEFAULT NULL,
                `os_id` INT(11) NULL DEFAULT NULL,
                `criado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                `atualizado_em` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`idTarefa`),
                KEY `fk_tarefa_usuario` (`usuarios_id`),
                KEY `fk_tarefa_cliente` (`clientes_id`),
                KEY `fk_tarefa_os` (`os_id`)
            ) ENGINE=InnoDB",
            'peliculas' => 'CREATE TABLE IF NOT EXISTS `peliculas` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `modelo` VARCHAR(150) NOT NULL,
                `compativeis` TEXT NOT NULL,
                `data_cadastro` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB',
            'mural_notas' => 'CREATE TABLE IF NOT EXISTS `mural_notas` (
                `idNota` INT(11) NOT NULL AUTO_INCREMENT,
                `usuarios_id` INT(11) NOT NULL,
                `texto` TEXT NOT NULL,
                `data_postagem` DATETIME NOT NULL,
                PRIMARY KEY (`idNota`)
            ) ENGINE=InnoDB',
            'mural_notificacoes' => 'CREATE TABLE IF NOT EXISTS `mural_notificacoes` (
                `idNotif` INT(11) NOT NULL AUTO_INCREMENT,
                `idNota` INT(11) NOT NULL,
                `usuarios_id` INT(11) NOT NULL,
                `lido` TINYINT(1) NULL DEFAULT 0,
                PRIMARY KEY (`idNotif`),
                KEY `idNota` (`idNota`),
                KEY `idx_mural_notificacoes_usuario` (`usuarios_id`, `lido`),
                CONSTRAINT `mural_notificacoes_ibfk_1` FOREIGN KEY (`idNota`) REFERENCES `mural_notas` (`idNota`) ON DELETE CASCADE
            ) ENGINE=InnoDB',
            'vendas_itens_extras' => "CREATE TABLE IF NOT EXISTS `vendas_itens_extras` (
                `idExtra` INT(11) NOT NULL AUTO_INCREMENT,
                `vendas_id` INT(11) NOT NULL,
                `tipo` VARCHAR(10) NOT NULL DEFAULT 'avulso',
                `servicos_id` INT(11) NULL DEFAULT NULL,
                `descricao` VARCHAR(255) NOT NULL,
                `quantidade` DECIMAL(10,2) NOT NULL DEFAULT 1,
                `preco` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `subTotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
                PRIMARY KEY (`idExtra`),
                KEY `idx_vendas_itens_extras_venda` (`vendas_id`),
                CONSTRAINT `fk_vendas_itens_extras_vendas` FOREIGN KEY (`vendas_id`) REFERENCES `vendas` (`idVendas`) ON DELETE CASCADE
            ) ENGINE=InnoDB",
        ];

        foreach ($tabelas as $tabela => $sql) {
            if (! $this->tabelaExiste($tabela)) {
                $this->db->query($sql);
            }

            // Com db_debug desligado (produção) um CREATE com erro só vai para
            // o log e a versão avançaria mesmo assim.
            if (! $this->tabelaExiste($tabela)) {
                throw new RuntimeException("Não foi possível criar a tabela {$tabela}; veja o log da aplicação.");
            }
        }

        // A 4.x criava mural_notificacoes sem o índice por usuário (o sino do
        // chat consulta por ele a cada minuto).
        $indices = array_column($this->db->query('SHOW INDEX FROM `mural_notificacoes`')->result_array(), 'Key_name');
        if (! in_array('idx_mural_notificacoes_usuario', $indices, true)) {
            $this->db->query('ALTER TABLE `mural_notificacoes` ADD INDEX `idx_mural_notificacoes_usuario` (`usuarios_id`, `lido`)');
        }

        $indices = array_column($this->db->query('SHOW INDEX FROM `agendamentos`')->result_array(), 'Key_name');
        if (! in_array('idx_agendamentos_data', $indices, true)) {
            $this->db->query('ALTER TABLE `agendamentos` ADD INDEX `idx_agendamentos_data` (`data_agendamento`)');
        }
    }

    /**
     * table_exists() guarda a lista de tabelas em cache na primeira chamada e
     * não enxerga a tabela recém-criada; aqui a consulta é sempre nova.
     */
    private function tabelaExiste(string $tabela): bool
    {
        return $this->db->query('SHOW TABLES LIKE ' . $this->db->escape($tabela))->num_rows() > 0;
    }

    private function criarColunasDaOs(): void
    {
        $colunas = [
            'marca' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'modelo' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'numero_serie' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'senha_dispositivo' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'assinatura_digital' => ['type' => 'LONGTEXT', 'null' => true],
        ];

        foreach ($colunas as $coluna => $definicao) {
            if (! $this->db->field_exists($coluna, 'os')) {
                $this->dbforge->add_column('os', [$coluna => $definicao]);
            }
            if (! $this->db->field_exists($coluna, 'os')) {
                throw new RuntimeException("Não foi possível criar a coluna os.{$coluna}; veja o log da aplicação.");
            }
        }
    }

    private function criarConfiguracoes(): void
    {
        $balcao = $this->db->query("SELECT idClientes FROM clientes WHERE UPPER(nomeCliente) IN ('BALCAO', 'BALCÃO', 'CONSUMIDOR FINAL') ORDER BY idClientes LIMIT 1")->row();
        $padroes = [
            'dt_termos_os' => self::TERMOS_PADRAO,
            'dt_msg_agendamento' => self::MENSAGEM_AGENDAMENTO,
            'dt_validade_orc' => '5 dias úteis',
            'dt_cliente_balcao' => $balcao ? (string) $balcao->idClientes : '',
        ];
        foreach ($padroes as $config => $valor) {
            if ($this->db->where('config', $config)->count_all_results('configuracoes') === 0) {
                $this->db->insert('configuracoes', ['config' => $config, 'valor' => $valor]);
            }
        }
    }

    /**
     * A 4.x tinha dois nomes para o mesmo passo: a tela da agenda gravava
     * "Em Andamento" e o Kanban "Em Atendimento". Fica um só.
     */
    private function normalizarStatusDosAgendamentos(): void
    {
        $this->db->where('status', 'Em Atendimento')->update('agendamentos', ['status' => 'Em Andamento']);
    }

    /**
     * Agenda espelha as permissões da OS (vOs → vAgendamento...) e Películas
     * as dos produtos. Grupo que já tem o código novo não é alterado.
     */
    private function concederPermissoes(): void
    {
        $espelhos = ['Agendamento' => 'Os', 'Pelicula' => 'Produto'];

        foreach ($this->db->get('permissoes')->result() as $grupo) {
            $mapa = json_decode_legacy((string) $grupo->permissoes);
            if (! is_array($mapa)) {
                continue;
            }

            $mudou = false;
            foreach ($espelhos as $novo => $base) {
                foreach (['v', 'a', 'e', 'd'] as $acao) {
                    if (! array_key_exists($acao . $novo, $mapa)) {
                        $mapa[$acao . $novo] = (string) ($mapa[$acao . $base] ?? '') === '1' ? 1 : 0;
                        $mudou = true;
                    }
                }
            }

            if ($mudou) {
                $this->db->where('idPermissao', $grupo->idPermissao)->update('permissoes', ['permissoes' => json_encode($mapa)]);
            }
        }
    }
}
