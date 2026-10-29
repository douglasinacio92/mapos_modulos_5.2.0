<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Mapa de permissões dos módulos Daitech
| -------------------------------------------------------------------------
|
| Incluído no fim de config/permissions_map.php. Mesmo formato do mapa do
| Map-OS: método público que não está aqui é negado.
|
| Permissões novas (grupo de permissões, matriz Ver/Adicionar/Editar/Excluir):
|   Agendamento  agenda externa (visitas técnicas)
|   Pelicula     compatibilidade de películas
| O PDV e os orçamentos usam as permissões de venda; o Kanban, as da OS e da
| agenda; o mural, qualquer usuário logado.
*/

$config['permissions_map'] = array_merge($config['permissions_map'] ?? [], [
    'Agendamentos' => [
        'index' => 'vAgendamento',
        'adicionar' => 'aAgendamento',
        'editar' => 'eAgendamento',
        'excluir' => 'dAgendamento',
        'enviarWhatsApp' => ['aAgendamento', 'eAgendamento'],
        'autoCompleteCliente' => ['aAgendamento', 'eAgendamento'],
    ],

    // Cada tipo de cartão confere a sua permissão dentro do método.
    'Kanban' => [
        'index' => ['vOs', 'vAgendamento'],
        'dados' => ['vOs', 'vAgendamento'],
        'mover' => ['eOs', 'eAgendamento', 'vOs', 'vAgendamento'],
        'salvarTarefa' => ['vOs', 'vAgendamento'],
        'excluirTarefa' => ['vOs', 'vAgendamento'],
    ],

    'Notas' => [
        'index' => '*',
        'adicionar' => '*',
        'excluir' => '*',
        'novidades' => '*',
        'historico' => '*',
        'enviar' => '*',
    ],

    'Peliculas' => [
        'index' => 'vPelicula',
        'adicionar' => 'aPelicula',
        'editar' => 'ePelicula',
        'excluir' => 'dPelicula',
    ],

    'Etiquetas' => [
        'index' => 'vProduto',
        'buscar' => 'vProduto',
        'imprimir' => 'vProduto',
    ],

    'Pdv' => [
        'index' => 'aVenda',
        'buscar' => ['aVenda', 'eVenda'],
        'finalizar' => 'aVenda',
        'cupom' => 'vVenda',
    ],

    'Orcamentos' => [
        'index' => 'vVenda',
        'gerenciar' => 'vVenda',
        'novo' => 'aVenda',
        'editar' => 'eVenda',
        'salvar' => ['aVenda', 'eVenda'],
        'imprimir' => 'vVenda',
        'converter' => 'eVenda',
        'excluir' => 'dVenda',
    ],

    'Os_extras' => [
        'etiquetas' => 'vOs',
        'cupom' => 'vOs',
    ],

    'Modulos' => [
        'index' => 'cSistema',
    ],
]);
