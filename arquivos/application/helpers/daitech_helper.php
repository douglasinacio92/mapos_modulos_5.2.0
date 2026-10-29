<?php

/*
 * Módulos Daitech (agenda externa, Kanban, mural, películas, PDV, orçamentos
 * e dados do aparelho na OS) sobre o Map-OS v5.
 *
 * Regras em funções puras, para serem testadas sem banco nem sessão, como os
 * helpers do próprio Map-OS (os_helper, vendas_helper...). Carregado pelo
 * config/autoload.php.
 */

if (! defined('DAITECH_AGENDAMENTO_STATUS')) {
    // Status da visita técnica => variante do pill-status (DESIGN.md).
    define('DAITECH_AGENDAMENTO_STATUS', [
        'Agendado' => 'info',
        'Confirmado' => 'progress',
        'Em Andamento' => 'warning',
        'Concluido' => 'success',
        'Cancelado' => 'neutral',
    ]);
}

if (! defined('DAITECH_TAREFA_STATUS')) {
    define('DAITECH_TAREFA_STATUS', [
        'A FAZER' => 'neutral',
        'FAZENDO' => 'progress',
        'CONCLUÍDO' => 'success',
    ]);
}

if (! defined('DAITECH_TAREFA_PRIORIDADES')) {
    define('DAITECH_TAREFA_PRIORIDADES', ['Baixa' => 'neutral', 'Media' => 'warning', 'Alta' => 'danger']);
}

if (! defined('DAITECH_FORMAS_PAGAMENTO')) {
    // Chave do formulário do PDV => texto gravado em lancamentos.forma_pgto
    // (os mesmos nomes do faturamento da venda no Map-OS).
    define('DAITECH_FORMAS_PAGAMENTO', [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'debito' => 'Cartão de Débito',
        'credito' => 'Cartão de Crédito',
    ]);
}

if (! defined('DAITECH_HORARIOS_INICIO')) {
    define('DAITECH_HORARIOS_INICIO', 7);
    define('DAITECH_HORARIOS_FIM', 20);
}

if (! function_exists('daitechMenu')) {
    /**
     * Acrescenta os itens dos módulos ao menu do painel (layoutMenu()). Cada
     * item só aparece se o mapa de permissões liberar a rota, como os do
     * Map-OS (config/daitech_permissions_map.php).
     *
     * @param  list<array<string, mixed>>  $menu
     * @return list<array<string, mixed>>
     */
    function daitechMenu(array $menu): array
    {
        $depoisDe = [
            // O PDV abre pelo botão da tela de vendas (views/vendas/vendas.php) e
            // fica marcado como Vendas no menu.
            'vendas' => [
                ['grupo' => 'Operação', 'label' => 'Orçamentos', 'icon' => 'calculator', 'url' => 'orcamentos', 'rota' => ['Orcamentos', 'index'], 'ativo' => ['Orcamentos']],
            ],
            'produtos' => [
                ['grupo' => 'Operação', 'label' => 'Etiquetas', 'icon' => 'tag', 'url' => 'etiquetas', 'rota' => ['Etiquetas', 'index'], 'ativo' => ['Etiquetas']],
            ],
            'os' => [
                ['grupo' => 'Operação', 'label' => 'Kanban', 'icon' => 'columns-3', 'url' => 'kanban', 'rota' => ['Kanban', 'index'], 'ativo' => ['Kanban']],
                ['grupo' => 'Operação', 'label' => 'Agenda externa', 'icon' => 'calendar-days', 'url' => 'agendamentos', 'rota' => ['Agendamentos', 'index'], 'ativo' => ['Agendamentos']],
            ],
            'garantias' => [
                ['grupo' => 'Operação', 'label' => 'Películas', 'icon' => 'smartphone', 'url' => 'peliculas', 'rota' => ['Peliculas', 'index'], 'ativo' => ['Peliculas']],
            ],
        ];

        $saida = [];
        foreach ($menu as $item) {
            if (($item['url'] ?? null) === 'vendas') {
                $item['ativo'][] = 'Pdv';
            }
            if (($item['label'] ?? '') === 'Configurações' && isset($item['itens'])) {
                $item['itens'][] = ['label' => 'Módulos Daitech', 'url' => 'modulos', 'rota' => ['Modulos', 'index'], 'ativo' => ['Modulos']];
            }

            $saida[] = $item;
            foreach ($depoisDe[$item['url'] ?? ''] ?? [] as $extra) {
                $saida[] = $extra;
            }
        }

        return $saida;
    }
}

if (! function_exists('daitechDiasDaBarra')) {
    /**
     * Os 7 dias da barra da agenda: 3 antes, o escolhido e 3 depois.
     *
     * @return list<array{data: string, dia: string, numero: string, hoje: bool, atual: bool}>
     */
    function daitechDiasDaBarra(string $escolhido, string $hoje): array
    {
        $nomes = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $base = strtotime($escolhido . ' 12:00:00');
        $dias = [];

        for ($i = -3; $i <= 3; $i++) {
            $instante = strtotime(($i >= 0 ? '+' : '') . $i . ' days', $base);
            $data = date('Y-m-d', $instante);
            $dias[] = [
                'data' => $data,
                'dia' => $data === $hoje ? 'Hoje' : $nomes[(int) date('w', $instante)],
                'numero' => date('d', $instante),
                'hoje' => $data === $hoje,
                'atual' => $data === $escolhido,
            ];
        }

        return $dias;
    }
}

if (! function_exists('daitechCalendarioDoMes')) {
    /**
     * Semanas (domingo a sábado) do mês da data escolhida, para o calendário
     * da agenda. Dias de fora do mês completam a primeira e a última semana.
     *
     * @return array{titulo: string, inicio: string, fim: string, mes_anterior: string, mes_seguinte: string, semanas: list<list<array{data: string, numero: string, no_mes: bool, hoje: bool, atual: bool}>>}
     */
    function daitechCalendarioDoMes(string $escolhido, string $hoje): array
    {
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $primeiro = strtotime(substr($escolhido, 0, 7) . '-01 12:00:00');
        $mes = date('m', $primeiro);
        $dia = strtotime('-' . date('w', $primeiro) . ' days', $primeiro);
        $ultimo = strtotime(date('Y-m-t', $primeiro) . ' 12:00:00');
        $fim = strtotime('+' . (6 - (int) date('w', $ultimo)) . ' days', $ultimo);

        $semanas = [];
        $inicio = date('Y-m-d', $dia);
        while ($dia <= $fim) {
            $semana = [];
            for ($i = 0; $i < 7; $i++) {
                $data = date('Y-m-d', $dia);
                $semana[] = ['data' => $data, 'numero' => date('j', $dia), 'no_mes' => date('m', $dia) === $mes, 'hoje' => $data === $hoje, 'atual' => $data === $escolhido];
                $dia = strtotime('+1 day', $dia);
            }
            $semanas[] = $semana;
        }

        return [
            'titulo' => ucfirst($meses[(int) $mes - 1]) . ' de ' . date('Y', $primeiro),
            'inicio' => $inicio,
            'fim' => date('Y-m-d', $fim),
            'mes_anterior' => date('Y-m-d', strtotime('-1 month', $primeiro)),
            'mes_seguinte' => date('Y-m-d', strtotime('+1 month', $primeiro)),
            'semanas' => $semanas,
        ];
    }
}

if (! function_exists('daitechHorarios')) {
    /**
     * Horários do select da agenda, de 30 em 30 minutos. Um horário gravado
     * fora da lista (agendamento antigo, 10:15) entra para não se perder ao
     * editar.
     *
     * @return array<string, string>
     */
    function daitechHorarios(?string $atual = null): array
    {
        $horas = [];
        for ($h = DAITECH_HORARIOS_INICIO; $h <= DAITECH_HORARIOS_FIM; $h++) {
            foreach (['00', '30'] as $m) {
                $hora = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':' . $m;
                $horas[$hora] = $hora;
            }
        }

        $atual = daitechHoraLimpa($atual);
        if ($atual !== '' && ! isset($horas[$atual])) {
            $horas[$atual] = $atual;
            ksort($horas);
        }

        return $horas;
    }
}

if (! function_exists('daitechHoraLimpa')) {
    /** "09:30:00" ou " 9:30" → "09:30"; inválido → "". */
    function daitechHoraLimpa(?string $hora): string
    {
        if (! preg_match('/^\s*(\d{1,2}):(\d{2})/', (string) $hora, $m)) {
            return '';
        }
        if ((int) $m[1] > 23 || (int) $m[2] > 59) {
            return '';
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
    }
}

if (! function_exists('daitechAgendamentoDadosDoFormulario')) {
    /**
     * Dados do agendamento a partir do POST, com os erros por campo. O id do
     * cliente é conferido no banco pelo controller.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    function daitechAgendamentoDadosDoFormulario(array $post, bool $novo): array
    {
        $texto = static fn (string $campo): string => is_scalar($post[$campo] ?? null) ? trim((string) $post[$campo]) : '';
        $erros = [];

        $data = dataIsoParaYmd($texto('data_agendamento'));
        if ($data === null) {
            $erros['data_agendamento'] = 'Informe uma data válida.';
        }

        $hora = daitechHoraLimpa($texto('hora_agendamento'));
        if ($hora === '') {
            $erros['hora_agendamento'] = 'Escolha o horário.';
        }

        $status = $texto('status');
        if (! array_key_exists($status, DAITECH_AGENDAMENTO_STATUS)) {
            $erros['status'] = 'Escolha um status da lista.';
        }

        $dados = [
            'data_agendamento' => $data,
            'hora_agendamento' => $hora,
            'status' => $status,
            'endereco' => mb_substr($texto('endereco'), 0, 255),
            'descricao' => mb_substr($texto('descricao'), 0, 5000),
        ];

        if ($novo) {
            $cliente = $texto('clientes_id');
            if (! ctype_digit($cliente) || (int) $cliente <= 0) {
                $erros['cliente'] = 'Escolha um cliente da lista.';
            }
            $dados['clientes_id'] = ctype_digit($cliente) ? (int) $cliente : 0;
        }

        return [$dados, $erros];
    }
}

if (! function_exists('daitechAgendamentoPill')) {
    /** @return array{label: string, variant: string} */
    function daitechAgendamentoPill(?string $status): array
    {
        $status = (string) $status;

        return ['label' => $status !== '' ? ($status === 'Concluido' ? 'Concluído' : $status) : 'Sem status', 'variant' => DAITECH_AGENDAMENTO_STATUS[$status] ?? 'neutral'];
    }
}

if (! function_exists('daitechGoogleAgendaUrl')) {
    /** Link "salvar no Google Agenda" (1 hora a partir do horário). */
    function daitechGoogleAgendaUrl(string $titulo, string $data, string $hora, string $local = '', string $detalhes = ''): string
    {
        $hora = daitechHoraLimpa($hora) ?: '08:00';
        $inicio = strtotime($data . ' ' . $hora);
        if ($inicio === false) {
            return '';
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $titulo,
            'dates' => date('Ymd\THis', $inicio) . '/' . date('Ymd\THis', $inicio + 3600),
            'location' => $local,
            'details' => $detalhes,
        ]);
    }
}

if (! function_exists('daitechMapsUrl')) {
    function daitechMapsUrl(?string $endereco): string
    {
        $endereco = trim((string) $endereco);

        return $endereco === '' ? '' : 'https://www.google.com/maps/search/?' . http_build_query(['api' => 1, 'query' => $endereco]);
    }
}

if (! function_exists('daitechMensagemAgendamento')) {
    /**
     * Mensagem de confirmação da visita, a partir do modelo de Módulos Daitech
     * (configuração dt_msg_agendamento).
     */
    function daitechMensagemAgendamento(string $modelo, object $agendamento, string $emitente): string
    {
        return strtr($modelo, [
            '{CLIENTE}' => (string) ($agendamento->nomeCliente ?? ''),
            '{DATA}' => dataBr($agendamento->data_agendamento ?? null),
            '{HORA}' => daitechHoraLimpa($agendamento->hora_agendamento ?? ''),
            '{ENDERECO}' => trim((string) ($agendamento->endereco ?? '')) ?: 'a combinar',
            '{DESCRICAO}' => trim((string) ($agendamento->descricao ?? '')),
            '{EMITENTE}' => $emitente,
        ]);
    }
}

if (! function_exists('daitechEventosDaAgenda')) {
    /**
     * Visitas da agenda externa no calendário do painel (Mapos::calendario()),
     * no formato do FullCalendar. O clique abre a agenda no dia da visita
     * (painel.js).
     *
     * @param  iterable<object>  $agendamentos  Agendamentos_model::doPeriodo()
     * @return list<array<string, mixed>>
     */
    function daitechEventosDaAgenda(iterable $agendamentos, string $urlAgenda): array
    {
        $eventos = [];
        foreach ($agendamentos as $ag) {
            $pill = daitechAgendamentoPill($ag->status ?? null);
            $hora = daitechHoraLimpa($ag->hora_agendamento ?? '');
            $eventos[] = [
                'id' => 'agendamento-' . (int) $ag->idAgendamento,
                'title' => 'Visita ' . $hora . ' · ' . ($ag->nomeCliente ?? 'Cliente removido'),
                'start' => substr((string) $ag->data_agendamento, 0, 10),
                'allDay' => true,
                'extendedProps' => [
                    'tipo' => 'agendamento',
                    'status' => $pill['label'],
                    'variante' => $pill['variant'],
                    'url' => $urlAgenda . '?' . http_build_query(['data' => substr((string) $ag->data_agendamento, 0, 10)]),
                ],
            ];
        }

        return $eventos;
    }
}

if (! function_exists('daitechDiasAte')) {
    /** Dias de hoje até a data (negativo = já passou), ou null sem data. */
    function daitechDiasAte(?string $data, string $hoje): ?int
    {
        $data = substr((string) $data, 0, 10);
        if ($data === '' || $data === '0000-00-00' || strtotime($data) === false) {
            return null;
        }

        return (int) round((strtotime($data . ' 12:00:00') - strtotime($hoje . ' 12:00:00')) / 86400);
    }
}

if (! function_exists('daitechPrazo')) {
    /**
     * Situação do prazo de um cartão do Kanban, como pill-status:
     * vermelho (danger) atrasado, amarelo (warning) hoje ou amanhã, verde
     * (success) no prazo, neutro sem prazo ou já concluído.
     *
     * @param  list<string>  $concluidos  Status (em minúsculas) que encerram o prazo
     * @return array{label: string, variant: string}
     */
    function daitechPrazo(?string $data, ?string $status, array $concluidos, string $hoje): array
    {
        if (in_array(mb_strtolower(trim((string) $status)), $concluidos, true)) {
            return ['label' => 'Concluído', 'variant' => 'neutral'];
        }

        $dias = daitechDiasAte($data, $hoje);

        return match (true) {
            $dias === null => ['label' => 'Sem prazo', 'variant' => 'neutral'],
            $dias < 0 => ['label' => 'Atrasado há ' . abs($dias) . ' dia' . (abs($dias) === 1 ? '' : 's'), 'variant' => 'danger'],
            $dias === 0 => ['label' => 'Vence hoje', 'variant' => 'warning'],
            $dias === 1 => ['label' => 'Vence amanhã', 'variant' => 'warning'],
            default => ['label' => 'Faltam ' . $dias . ' dias', 'variant' => 'success'],
        };
    }
}

if (! function_exists('daitechTarefaDadosDoFormulario')) {
    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    function daitechTarefaDadosDoFormulario(array $post): array
    {
        $texto = static fn (string $campo): string => is_scalar($post[$campo] ?? null) ? trim((string) $post[$campo]) : '';
        $erros = [];

        $titulo = mb_substr($texto('titulo'), 0, 255);
        if ($titulo === '') {
            $erros['titulo'] = 'Informe o título da tarefa.';
        }

        $prioridade = $texto('prioridade') ?: 'Baixa';
        if (! array_key_exists($prioridade, DAITECH_TAREFA_PRIORIDADES)) {
            $erros['prioridade'] = 'Escolha uma prioridade da lista.';
        }

        $status = $texto('status') ?: 'A FAZER';
        if (! array_key_exists($status, DAITECH_TAREFA_STATUS)) {
            $erros['status'] = 'Escolha um status da lista.';
        }

        $limite = null;
        if ($texto('dataLimite') !== '') {
            $limite = dataIsoParaYmd($texto('dataLimite'));
            if ($limite === null) {
                $erros['dataLimite'] = 'Informe uma data válida.';
            }
        }

        $responsavel = $texto('usuarios_id');

        return [[
            'titulo' => $titulo,
            'descricao' => mb_substr($texto('descricao'), 0, 5000),
            'prioridade' => $prioridade,
            'status' => $status,
            'dataLimite' => $limite,
            'usuarios_id' => ctype_digit($responsavel) && (int) $responsavel > 0 ? (int) $responsavel : null,
        ], $erros];
    }
}

if (! function_exists('daitechStatusDaOsParaKanban')) {
    /**
     * Colunas do Kanban de OS: os status visíveis das Configurações
     * (os_status_list, que pode vir com encode duplo da 4.x), na ordem do
     * fluxo do Map-OS. Sem configuração, todos.
     *
     * @return list<string>
     */
    function daitechStatusDaOsParaKanban($osStatusList): array
    {
        $todos = array_keys(OS_STATUS_VARIANTES);
        $lista = is_string($osStatusList) ? json_decode($osStatusList, true) : $osStatusList;
        if (is_string($lista)) {
            $lista = json_decode($lista, true);
        }
        if (! is_array($lista) || $lista === []) {
            return $todos;
        }

        $visiveis = array_values(array_filter($todos, static fn ($s) => in_array($s, $lista, true)));

        return $visiveis === [] ? $todos : $visiveis;
    }
}

if (! function_exists('daitechSenhaPadrao')) {
    /**
     * Texto gravado para a senha de desenho: "Padrão: 1-5-9-6". Só pontos de
     * 1 a 9, sem repetir.
     *
     * @param  list<int|string>  $pontos
     */
    function daitechSenhaPadrao(array $pontos): string
    {
        $limpos = [];
        foreach ($pontos as $ponto) {
            $n = (int) $ponto;
            if ($n >= 1 && $n <= 9 && ! in_array($n, $limpos, true)) {
                $limpos[] = $n;
            }
        }

        return $limpos === [] ? '' : 'Padrão: ' . implode('-', $limpos);
    }
}

if (! function_exists('daitechPontosDoPadrao')) {
    /**
     * Pontos de uma senha de desenho gravada ("Padrão: 1-5-9"), ou [] quando a
     * senha é texto comum.
     *
     * @return list<int>
     */
    function daitechPontosDoPadrao(?string $senha): array
    {
        if (! preg_match('/^Padr(?:ão|ao):\s*([1-9](?:-[1-9])*)$/u', trim((string) $senha), $m)) {
            return [];
        }

        return array_map('intval', explode('-', $m[1]));
    }
}

if (! defined('DAITECH_ASSINATURA_MAX')) {
    // ~300 KB de PNG em base64: uma assinatura de 600x200 fica bem abaixo.
    define('DAITECH_ASSINATURA_MAX', 400000);
}

if (! function_exists('daitechAssinaturaValida')) {
    /**
     * A assinatura só é gravada e impressa se for um PNG em data URL (o que o
     * canvas gera). Qualquer outra coisa vira null: nada de URL externa nem
     * SVG com script no <img>.
     */
    function daitechAssinaturaValida(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strlen($valor) > DAITECH_ASSINATURA_MAX) {
            return null;
        }
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#', $valor, $m)) {
            return null;
        }

        $binario = base64_decode($m[1], true);

        return $binario !== false && str_starts_with($binario, "\x89PNG\r\n\x1a\n") ? $valor : null;
    }
}

if (! function_exists('daitechOsExtrasDoFormulario')) {
    /**
     * Campos do aparelho no formulário da OS (os_extras/partes/campos).
     * "manter" na assinatura deixa a gravada como está; vazio apaga.
     *
     * @return array<string, string|null>
     */
    function daitechOsExtrasDoFormulario(array $post): array
    {
        $texto = static fn (string $campo): string => is_scalar($post[$campo] ?? null) ? mb_substr(trim((string) $post[$campo]), 0, 100) : '';

        $dados = [
            'marca' => $texto('marca'),
            'modelo' => $texto('modelo'),
            'numero_serie' => $texto('numero_serie'),
            'senha_dispositivo' => $texto('senha_dispositivo'),
        ];

        $assinatura = is_scalar($post['assinatura_digital'] ?? null) ? (string) $post['assinatura_digital'] : 'manter';
        if ($assinatura !== 'manter') {
            $dados['assinatura_digital'] = daitechAssinaturaValida($assinatura);
        }

        return $dados;
    }
}

if (! function_exists('osExtrasSalvar')) {
    /**
     * Grava os dados do aparelho depois que o formulário da OS salvou a OS
     * (gancho em Os::formulario()). Sem as colunas (migration pendente), não
     * faz nada.
     */
    function osExtrasSalvar($db, int $idOs, $post): void
    {
        if ($idOs <= 0 || ! is_array($post) || ! array_key_exists('marca', $post) || ! $db->field_exists('marca', 'os')) {
            return;
        }

        $db->where('idOs', $idOs)->update('os', daitechOsExtrasDoFormulario($post));
    }
}

if (! function_exists('daitechEquipamento')) {
    /** "Notebook Dell Inspiron 15" a partir dos campos da OS. */
    function daitechEquipamento(object $os): string
    {
        $partes = [
            trim(strip_tags((string) ($os->marca ?? ''))),
            trim(strip_tags((string) ($os->modelo ?? ''))),
        ];

        return trim(implode(' ', array_filter($partes, static fn ($p) => $p !== '')));
    }
}

if (! function_exists('daitechNumero')) {
    /** "1.234,56", "1234.56" ou "10" → float; inválido → null. */
    function daitechNumero($valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        $texto = str_replace(['R$', ' '], '', trim((string) $valor));
        if ($texto === '') {
            return null;
        }
        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }
}

if (! function_exists('daitechItensDoCarrinho')) {
    /**
     * Itens do PDV/orçamento a partir do JSON do navegador: tipo, id,
     * quantidade, preço (opcional para produto e serviço: sem ele vale o do
     * cadastro) e, no avulso, a descrição. O produto e o serviço são
     * conferidos no banco pelo Pdv_model.
     *
     * @return array{0: list<array{tipo: string, id: int|null, quantidade: float, descricao: string, preco: float|null}>, 1: array<string, string>}
     */
    function daitechItensDoCarrinho($json): array
    {
        $lista = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($lista) || $lista === []) {
            return [[], ['itens' => 'Adicione pelo menos um item.']];
        }
        if (count($lista) > 200) {
            return [[], ['itens' => 'São no máximo 200 itens.']];
        }

        $itens = [];
        foreach (array_values($lista) as $i => $bruto) {
            if (! is_array($bruto)) {
                return [[], ['itens' => 'Item inválido na linha ' . ($i + 1) . '.']];
            }
            $tipo = (string) ($bruto['tipo'] ?? '');
            $quantidade = daitechNumero($bruto['quantidade'] ?? null);
            if (! in_array($tipo, ['produto', 'servico', 'avulso'], true)) {
                return [[], ['itens' => 'Item inválido na linha ' . ($i + 1) . '.']];
            }
            if ($quantidade === null || $quantidade <= 0 || $quantidade > 99999) {
                return [[], ['itens' => 'Quantidade inválida na linha ' . ($i + 1) . '.']];
            }
            if ($tipo === 'produto' && floor($quantidade) != $quantidade) {
                return [[], ['itens' => 'A quantidade de produto é inteira (linha ' . ($i + 1) . ').']];
            }

            $item = ['tipo' => $tipo, 'id' => null, 'quantidade' => round($quantidade, 2), 'descricao' => '', 'preco' => null];

            // Preço digitado (o Map-OS também aceita outro preço na OS e na
            // venda); vazio usa o do cadastro.
            $precoInformado = $bruto['preco'] ?? null;
            if ($precoInformado !== null && $precoInformado !== '') {
                $preco = daitechNumero($precoInformado);
                if ($preco === null || $preco < 0 || $preco > 9999999) {
                    return [[], ['itens' => 'Preço inválido na linha ' . ($i + 1) . '.']];
                }
                $item['preco'] = round($preco, 2);
            }

            if ($tipo === 'avulso') {
                $descricao = mb_substr(trim((string) ($bruto['descricao'] ?? '')), 0, 255);
                if ($descricao === '') {
                    return [[], ['itens' => 'Informe a descrição do item avulso (linha ' . ($i + 1) . ').']];
                }
                if ($item['preco'] === null) {
                    return [[], ['itens' => 'Informe o preço do item avulso (linha ' . ($i + 1) . ').']];
                }
                $item['descricao'] = $descricao;
            } else {
                $id = $bruto['id'] ?? null;
                if (! is_numeric($id) || (int) $id <= 0) {
                    return [[], ['itens' => 'Item inválido na linha ' . ($i + 1) . '.']];
                }
                $item['id'] = (int) $id;
            }

            $itens[] = $item;
        }

        return [$itens, []];
    }
}

if (! function_exists('daitechDesconto')) {
    /**
     * Total com desconto do PDV/orçamento: em R$ ou %, nunca abaixo de zero.
     *
     * @return array{0: array{bruto: float, desconto: float, total: float, tipo: string|null, informado: float}|null, 1: array<string, string>}
     */
    function daitechDesconto(float $bruto, $tipo, $valor): array
    {
        $bruto = round($bruto, 2);
        $numero = daitechNumero($valor);
        if ($numero === null || $numero == 0) {
            return [['bruto' => $bruto, 'desconto' => 0.0, 'total' => $bruto, 'tipo' => null, 'informado' => 0.0], []];
        }
        if (! in_array($tipo, ['real', 'porcento'], true)) {
            return [null, ['desconto' => 'Escolha o tipo de desconto.']];
        }
        if ($numero < 0) {
            return [null, ['desconto' => 'O desconto não pode ser negativo.']];
        }

        $desconto = $tipo === 'porcento' ? round($bruto * $numero / 100, 2) : round($numero, 2);
        if (($tipo === 'porcento' && $numero > 100) || $desconto > $bruto) {
            return [null, ['desconto' => 'O desconto não pode ser maior que o total.']];
        }

        return [['bruto' => $bruto, 'desconto' => $desconto, 'total' => round($bruto - $desconto, 2), 'tipo' => $tipo, 'informado' => round($numero, 2)], []];
    }
}

if (! function_exists('daitechObservacaoDosExtras')) {
    /**
     * Linhas dos serviços e itens avulsos nas observações da venda, para eles
     * aparecerem também nas telas e impressões do Map-OS, que só listam
     * produtos.
     *
     * @param  iterable<object|array>  $extras
     */
    function daitechObservacaoDosExtras(iterable $extras): string
    {
        $linhas = [];
        foreach ($extras as $extra) {
            $extra = (object) $extra;
            $qtd = rtrim(rtrim(number_format((float) $extra->quantidade, 2, ',', ''), '0'), ',');
            $linhas[] = '[+] ' . $qtd . 'x ' . $extra->descricao . ' (' . dinheiro($extra->preco) . ')';
        }

        return implode("\n", $linhas);
    }
}

if (! function_exists('daitechTelefone')) {
    /** Só os dígitos, com o 55 na frente quando falta (para a Evolution API). */
    function daitechTelefone(?string $telefone): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefone);
        if (strlen($digitos) < 10) {
            return null;
        }

        return strlen($digitos) <= 11 ? '55' . $digitos : $digitos;
    }
}

if (! function_exists('daitechConfig')) {
    /** Valor de uma configuração do .env (EVOLUTION_API_URL...). */
    function daitechConfig(string $chave, string $padrao = ''): string
    {
        $valor = $_ENV[$chave] ?? getenv($chave);

        return is_string($valor) && trim($valor) !== '' ? trim($valor) : $padrao;
    }
}

if (! function_exists('daitechTokenAcompanhar')) {
    /**
     * Código do QR "acompanhe sua OS" (acompanhar/qr/ID/CODIGO): HMAC do número
     * da OS com a chave do sistema. Sem a chave não dá para montar o link de
     * outra OS trocando só o número.
     */
    function daitechTokenAcompanhar(int $idOs, string $chave): string
    {
        return substr(hash_hmac('sha256', 'acompanhar-os|' . $idOs, $chave), 0, 20);
    }
}

if (! function_exists('daitechCelularConfere')) {
    /**
     * O celular digitado pelo cliente é um dos telefones do cadastro? Compara
     * só os dígitos, sem o 55 do país, e exige o número com DDD.
     *
     * @param list<string|null> $cadastrados
     */
    function daitechCelularConfere(string $digitado, array $cadastrados): bool
    {
        $limpar = static function (?string $numero): string {
            $digitos = preg_replace('/\D/', '', (string) $numero);
            if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
                $digitos = substr($digitos, 2);
            }

            return ltrim($digitos, '0');
        };

        $informado = $limpar($digitado);
        if (strlen($informado) < 10) {
            return false;
        }

        foreach ($cadastrados as $numero) {
            $cadastro = $limpar($numero);
            if ($cadastro !== '' && hash_equals($cadastro, $informado)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('daitechQrCode')) {
    /** QR code em SVG (data URI) para as impressões, ou null sem a biblioteca. */
    function daitechQrCode(string $texto): ?string
    {
        if ($texto === '' || ! class_exists(chillerlan\QRCode\QRCode::class)) {
            return null;
        }

        $opcoes = new chillerlan\QRCode\QROptions([
            'outputType' => chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => chillerlan\QRCode\QRCode::ECC_M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);

        return (new chillerlan\QRCode\QRCode($opcoes))->render($texto);
    }
}

if (! function_exists('daitechChaveAcompanhar')) {
    /** Chave do HMAC do QR: a APP_ENCRYPTION_KEY, ou um hash do acesso ao banco sem ela. */
    function daitechChaveAcompanhar(): string
    {
        $chave = (string) config_item('encryption_key');
        if (strlen($chave) >= 16 && $chave !== 'enter_encryption_key') {
            return $chave;
        }
        $ci = &get_instance();

        return hash('sha256', 'daitech|' . (string) ($ci->db->password ?? '') . '|' . (string) ($ci->db->database ?? ''));
    }
}

if (! defined('DAITECH_AGENTE_IMPRESSAO')) {
    /** Endereço do agente de impressão no computador da loja (agente-impressao/agente.ps1). */
    define('DAITECH_AGENTE_IMPRESSAO', 'http://127.0.0.1:9123');
}

if (! function_exists('daitechImpressaoDireta')) {
    /**
     * Impressoras da impressão direta, ou null com ela desligada.
     *
     * @param  array<string, mixed>       $config configuracoes do sistema
     * @return array<string, string>|null tipo (cupom, a4, etiqueta) => impressora
     */
    function daitechImpressaoDireta(array $config): ?array
    {
        if ((string) ($config['dt_imp_direta'] ?? '0') !== '1') {
            return null;
        }
        $impressoras = array_filter([
            'cupom' => trim((string) ($config['dt_imp_cupom'] ?? '')),
            'a4' => trim((string) ($config['dt_imp_a4'] ?? '')),
            'etiqueta' => trim((string) ($config['dt_imp_etiqueta'] ?? '')),
        ], static fn ($nome) => $nome !== '');

        return $impressoras !== [] ? $impressoras : null;
    }
}

if (! function_exists('daitechCodigoDeBarrasSvg')) {
    /**
     * Código de barras em SVG (data URI) para a etiqueta de produto: EAN-13
     * quando o código é um EAN válido, Code 128 nos outros. Null sem código
     * ou sem a biblioteca (mPDF).
     */
    function daitechCodigoDeBarrasSvg(?string $codigo): ?string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '' || strlen($codigo) > 40 || ! class_exists(Mpdf\Barcode::class) || preg_match('/[^\x20-\x7E]/', $codigo)) {
            return null;
        }

        $tipos = ctype_digit($codigo)
            ? (strlen($codigo) === 13 ? ['EAN13', 'C128B'] : (strlen($codigo) % 2 === 0 ? ['C128C', 'C128B'] : ['C128B']))
            : ['C128B'];
        $barras = null;
        foreach ($tipos as $tipo) {
            try {
                $barras = (new Mpdf\Barcode())->getBarcodeArray($codigo, $tipo);
                break;
            } catch (Throwable) {
                $barras = null;
            }
        }
        if (! is_array($barras) || empty($barras['bcode'])) {
            return null;
        }

        $x = 0;
        $retangulos = '';
        foreach ($barras['bcode'] as $barra) {
            if (! empty($barra['t'])) {
                $retangulos .= '<rect x="' . $x . '" y="0" width="' . (int) $barra['w'] . '" height="40"/>';
            }
            $x += (int) $barra['w'];
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $x . ' 40" preserveAspectRatio="none" shape-rendering="crispEdges">' . $retangulos . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

if (! function_exists('daitechItensDaEtiqueta')) {
    /**
     * Lê a lista da etiqueta de produtos (?i=12:3,15:1): id do produto e
     * quantas etiquetas. No máximo 300 etiquetas por impressão.
     *
     * @return array<int, int> id do produto => quantidade
     */
    function daitechItensDaEtiqueta(?string $lista): array
    {
        $itens = [];
        $total = 0;
        foreach (explode(',', (string) $lista) as $par) {
            if (! preg_match('/^\s*(\d{1,10}):(\d{1,3})\s*$/', $par, $m) || (int) $m[1] <= 0 || (int) $m[2] <= 0) {
                continue;
            }
            $quantidade = min((int) $m[2], 300 - $total);
            if ($quantidade <= 0) {
                break;
            }
            $itens[(int) $m[1]] = ($itens[(int) $m[1]] ?? 0) + $quantidade;
            $total += $quantidade;
        }

        return $itens;
    }
}
