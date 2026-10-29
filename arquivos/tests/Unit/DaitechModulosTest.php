<?php

/**
 * Regras dos módulos Daitech (application/helpers/daitech_helper.php): menu,
 * agenda, Kanban, carrinho do PDV e do orçamento, assinatura e senha de
 * padrão da OS.
 */
final class DaitechModulosTest extends MaposTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once APPPATH . 'helpers/status_helper.php';
        require_once APPPATH . 'helpers/layout_helper.php';
        require_once APPPATH . 'helpers/daitech_helper.php';
    }

    protected function setUp(): void
    {
    }

    public function testMenuGanhaOsModulosDepoisDosItensDoMapos(): void
    {
        $urls = array_column(daitechMenu(layoutMenu()), 'url');

        $this->assertSame(array_search('vendas', $urls, true) + 1, array_search('orcamentos', $urls, true));
        $this->assertNotContains('pdv', $urls, 'o PDV abre pelo botão da tela de Vendas');
        $this->assertNotContains('notas', $urls, 'o mural abre pelo chat flutuante');
        $this->assertSame(array_search('os', $urls, true) + 1, array_search('kanban', $urls, true));
        $this->assertContains('agendamentos', $urls);
        $this->assertContains('peliculas', $urls);

        $configuracoes = array_values(array_filter(daitechMenu(layoutMenu()), static fn ($i) => ($i['label'] ?? '') === 'Configurações'))[0];
        $this->assertContains('modulos', array_column($configuracoes['itens'], 'url'));
    }

    public function testTodaRotaDoMenuNovoEstaNoMapaDePermissoes(): void
    {
        $config = [];
        include APPPATH . 'config/permissions_map.php';
        $mapa = $config['permissions_map'];

        foreach (['Agendamentos', 'Kanban', 'Notas', 'Peliculas', 'Pdv', 'Orcamentos', 'Modulos', 'Os_extras'] as $controller) {
            $this->assertArrayHasKey($controller, $mapa, $controller);
        }
        $this->assertSame('aVenda', $mapa['Pdv']['finalizar']);
        $this->assertSame('dAgendamento', $mapa['Agendamentos']['excluir']);
    }

    public function testBarraDaAgendaTemSeteDiasComOEscolhidoNoMeio(): void
    {
        $dias = daitechDiasDaBarra('2026-10-28', '2026-10-28');

        $this->assertCount(7, $dias);
        $this->assertSame('2026-10-25', $dias[0]['data']);
        $this->assertTrue($dias[3]['atual']);
        $this->assertSame('Hoje', $dias[3]['dia']);
        $this->assertSame('2026-10-31', $dias[6]['data']);
    }

    public function testCalendarioDoMesComecaNoDomingoETerminaNoSabado(): void
    {
        $cal = daitechCalendarioDoMes('2026-10-28', '2026-10-29');

        $this->assertSame('Outubro de 2026', $cal['titulo']);
        $this->assertSame('2026-09-27', $cal['inicio']);
        $this->assertSame('2026-10-31', $cal['fim']);
        $this->assertCount(5, $cal['semanas']);
        $this->assertSame('2026-09-01', $cal['mes_anterior']);
        $this->assertSame('2026-11-01', $cal['mes_seguinte']);
        $this->assertFalse($cal['semanas'][0][0]['no_mes']);
        $this->assertTrue($cal['semanas'][4][3]['atual']);
        $this->assertTrue($cal['semanas'][4][4]['hoje']);
    }

    public function testHorariosMantemOHorarioAntigoForaDaLista(): void
    {
        $horas = daitechHorarios('10:15:00');

        $this->assertArrayHasKey('10:15', $horas);
        $this->assertArrayHasKey('07:00', $horas);
        $this->assertSame('', daitechHoraLimpa('25:00'));
        $this->assertSame('09:05', daitechHoraLimpa(' 9:05'));
    }

    public function testAgendamentoRecusaDataStatusEClienteInvalidos(): void
    {
        [, $erros] = daitechAgendamentoDadosDoFormulario(['data_agendamento' => '31/02/2026', 'hora_agendamento' => 'x', 'status' => 'Sumiu'], true);

        $this->assertArrayHasKey('data_agendamento', $erros);
        $this->assertArrayHasKey('hora_agendamento', $erros);
        $this->assertArrayHasKey('status', $erros);
        $this->assertArrayHasKey('cliente', $erros);

        [$dados, $erros] = daitechAgendamentoDadosDoFormulario(['data_agendamento' => '2026-11-03', 'hora_agendamento' => '10:30', 'status' => 'Agendado', 'clientes_id' => '15'], true);
        $this->assertSame([], $erros);
        $this->assertSame(15, $dados['clientes_id']);
    }

    public function testMensagemDaVisitaTrocaOsMarcadores(): void
    {
        $texto = daitechMensagemAgendamento('{CLIENTE} {DATA} {HORA} {ENDERECO} {EMITENTE}', (object) [
            'nomeCliente' => 'Ana', 'data_agendamento' => '2026-11-03', 'hora_agendamento' => '10:30:00', 'endereco' => '',
        ], 'Daitech');

        $this->assertSame('Ana 03/11/2026 10:30 a combinar Daitech', $texto);
    }

    public function testPrazoDoKanban(): void
    {
        $hoje = '2026-10-28';
        $this->assertSame('danger', daitechPrazo('2026-10-25', 'Aberto', ['finalizado'], $hoje)['variant']);
        $this->assertSame('Vence hoje', daitechPrazo('2026-10-28', 'Aberto', ['finalizado'], $hoje)['label']);
        $this->assertSame('Vence amanhã', daitechPrazo('2026-10-29', 'Aberto', ['finalizado'], $hoje)['label']);
        $this->assertSame('success', daitechPrazo('2026-11-05', 'Aberto', ['finalizado'], $hoje)['variant']);
        $this->assertSame('Concluído', daitechPrazo('2026-10-01', 'Finalizado', ['finalizado'], $hoje)['label']);
        $this->assertSame('Sem prazo', daitechPrazo('0000-00-00', 'Aberto', [], $hoje)['label']);
    }

    public function testColunasDoKanbanSeguemAListaDasConfiguracoesComEncodeDuplo(): void
    {
        $duplo = json_encode(json_encode(['Aberto', 'Orçamento', 'Finalizado']));

        $this->assertSame(['Orçamento', 'Aberto', 'Finalizado'], daitechStatusDaOsParaKanban($duplo));
        $this->assertSame(array_keys(OS_STATUS_VARIANTES), daitechStatusDaOsParaKanban(null));
    }

    public function testCarrinhoRecusaItemInvalido(): void
    {
        $this->assertNotSame([], daitechItensDoCarrinho('[]')[1]);
        $this->assertNotSame([], daitechItensDoCarrinho('[{"tipo":"hack","id":1,"quantidade":1}]')[1]);
        $this->assertNotSame([], daitechItensDoCarrinho('[{"tipo":"produto","id":1,"quantidade":1.5}]')[1], 'produto com quantidade fracionada');
        $this->assertNotSame([], daitechItensDoCarrinho('[{"tipo":"produto","id":1,"quantidade":-1}]')[1]);
        $this->assertNotSame([], daitechItensDoCarrinho('[{"tipo":"avulso","quantidade":1,"descricao":""}]')[1]);
        $this->assertNotSame([], daitechItensDoCarrinho('[{"tipo":"avulso","quantidade":1,"descricao":"Cabo"}]')[1], 'avulso sem preço');
        $this->assertNotSame([], daitechItensDoCarrinho('[{"tipo":"servico","id":"abc","quantidade":1}]')[1]);
    }

    public function testCarrinhoAceitaPrecoDigitadoEOAvulso(): void
    {
        [$itens, $erros] = daitechItensDoCarrinho(json_encode([
            ['tipo' => 'produto', 'id' => 3, 'quantidade' => 2],
            ['tipo' => 'servico', 'id' => 7, 'quantidade' => '1,5', 'preco' => '80,00'],
            ['tipo' => 'avulso', 'quantidade' => 1, 'descricao' => 'Cabo HDMI', 'preco' => '15.5'],
        ]));

        $this->assertSame([], $erros);
        $this->assertNull($itens[0]['preco'], 'sem preço, vale o do cadastro');
        $this->assertSame(1.5, $itens[1]['quantidade']);
        $this->assertSame(80.0, $itens[1]['preco']);
        $this->assertSame('Cabo HDMI', $itens[2]['descricao']);
        $this->assertNull($itens[2]['id']);
    }

    public function testDescontoDoCarrinho(): void
    {
        $this->assertSame(90.0, daitechDesconto(100, 'porcento', '10')[0]['total']);
        $this->assertSame(87.5, daitechDesconto(100, 'real', '12,50')[0]['total']);
        $this->assertSame(100.0, daitechDesconto(100, 'real', '')[0]['total']);
        $this->assertNotSame([], daitechDesconto(100, 'real', '150')[1]);
        $this->assertNotSame([], daitechDesconto(100, 'porcento', '101')[1]);
        $this->assertNotSame([], daitechDesconto(100, 'real', '-5')[1]);
    }

    public function testAssinaturaSoAceitaPngEmDataUrl(): void
    {
        $png = 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . str_repeat("\0", 20));

        $this->assertSame($png, daitechAssinaturaValida($png));
        $this->assertNull(daitechAssinaturaValida('https://exemplo.com/a.png'));
        $this->assertNull(daitechAssinaturaValida('data:image/svg+xml;base64,' . base64_encode('<svg onload="alert(1)"/>')));
        $this->assertNull(daitechAssinaturaValida('data:image/png;base64,' . base64_encode('<script>')));
        $this->assertNull(daitechAssinaturaValida('data:image/png;base64,' . str_repeat('A', DAITECH_ASSINATURA_MAX)));
    }

    public function testCamposDoAparelhoMantemAAssinaturaGravada(): void
    {
        $dados = daitechOsExtrasDoFormulario(['marca' => ' Dell ', 'modelo' => str_repeat('x', 150), 'assinatura_digital' => 'manter']);

        $this->assertSame('Dell', $dados['marca']);
        $this->assertSame(100, mb_strlen($dados['modelo']));
        $this->assertArrayNotHasKey('assinatura_digital', $dados);

        $this->assertNull(daitechOsExtrasDoFormulario(['assinatura_digital' => ''])['assinatura_digital'], 'limpar apaga');
    }

    public function testSenhaDePadrao(): void
    {
        $this->assertSame('Padrão: 1-5-9-6', daitechSenhaPadrao([1, 5, 9, 6, 5, 12]));
        $this->assertSame([1, 5, 9], daitechPontosDoPadrao('Padrão: 1-5-9'));
        $this->assertSame([], daitechPontosDoPadrao('1234'));
    }

    public function testTelefoneParaAEvolutionApi(): void
    {
        $this->assertSame('5551999998888', daitechTelefone('(51) 99999-8888'));
        $this->assertSame('5551999998888', daitechTelefone('+55 51 99999-8888'));
        $this->assertNull(daitechTelefone('123'));
    }

    public function testEventosDaAgendaNoCalendario(): void
    {
        $eventos = daitechEventosDaAgenda([(object) ['idAgendamento' => 9, 'status' => 'Concluido', 'hora_agendamento' => '14:00', 'nomeCliente' => 'Ana', 'data_agendamento' => '2026-11-03']], 'http://x/agendamentos');

        $this->assertSame('agendamento-9', $eventos[0]['id']);
        $this->assertSame('agendamento', $eventos[0]['extendedProps']['tipo']);
        $this->assertSame('success', $eventos[0]['extendedProps']['variante']);
        $this->assertSame('http://x/agendamentos?data=2026-11-03', $eventos[0]['extendedProps']['url']);
    }
}
