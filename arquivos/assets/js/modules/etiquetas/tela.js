// Etiquetas de produtos (Daitech): a lista de produtos e quantidades fica no
// navegador e vira o link da impressão (etiquetas/imprimir?i=ID:QTD,...&preco=1).
// O leitor de código de barras manda o código e um Enter: o produto entra na
// lista (ou ganha mais uma etiqueta, se já estiver nela).

import { iniciarAutocompletes, urlDeBusca } from '../../lib/autocomplete.js';
import { get as getPadrao } from '../../lib/http.js';
import { criarIcone } from '../../lib/icone.js';
import { mostrarToast } from '../../lib/toast.js';

export const LIMITE = 300;

/** Link da impressão a partir da lista. */
export function urlDaImpressao(base, itens, comPreco) {
    const lista = itens.filter((item) => item.quantidade > 0).map((item) => `${item.id}:${item.quantidade}`).join(',');

    return `${base}?i=${encodeURIComponent(lista)}&preco=${comPreco ? 1 : 0}`;
}

/** Quantidade digitada: inteiro de 1 a 300. */
export function quantidadeValida(valor) {
    const n = Number(String(valor).trim());

    return Number.isInteger(n) && n >= 1 ? Math.min(n, LIMITE) : null;
}

export function resumo(itens) {
    const total = itens.reduce((soma, item) => soma + item.quantidade, 0);
    if (total === 0) {
        return 'Nenhuma etiqueta';
    }
    const fileiras = Math.ceil(total / 3);

    return `${total} ${total === 1 ? 'etiqueta' : 'etiquetas'} em ${fileiras} ${fileiras === 1 ? 'fileira' : 'fileiras'}`;
}

export default function iniciar(raiz, { get = getPadrao, doc = document } = {}) {
    const $ = (seletor) => raiz.querySelector(seletor);
    const lista = $('[data-etiquetas-lista]');
    const vazio = $('[data-etiquetas-vazio]');
    const total = $('[data-etiquetas-total]');
    const resumoProdutos = $('[data-etiquetas-resumo]');
    const limpar = $('[data-etiquetas-limpar]');
    const imprimir = $('[data-etiquetas-imprimir]');
    const preco = doc.getElementById('etiquetas-preco');
    const busca = doc.getElementById('etiquetas-busca');
    const buscaWrapper = $('[data-etiquetas-busca]');
    const itens = [];

    iniciarAutocompletes(raiz, { buscar: get });

    function atualizarLink() {
        const soma = itens.reduce((s, item) => s + item.quantidade, 0);
        imprimir.href = urlDaImpressao(raiz.dataset.imprimirUrl, itens, preco?.checked);
        imprimir.setAttribute('aria-disabled', soma === 0 ? 'true' : 'false');
        total.textContent = resumo(itens);
        if (soma > LIMITE) {
            total.textContent += ` (só as primeiras ${LIMITE} saem nesta impressão)`;
        }
    }

    function desenhar() {
        lista.replaceChildren(...itens.map((item, indice) => {
            const linha = doc.createElement('li');
            linha.className = 'grid grid-cols-[minmax(0,1fr)_5rem_2rem] items-center gap-3 px-4 py-2';

            const nome = doc.createElement('div');
            nome.className = 'min-w-0';
            const titulo = doc.createElement('p');
            titulo.className = 'text-body-md font-medium [overflow-wrap:anywhere] text-text';
            titulo.textContent = item.descricao;
            const detalhe = doc.createElement('p');
            detalhe.className = 'text-caption text-muted';
            detalhe.textContent = item.detalhe;
            nome.append(titulo, detalhe);

            const rotulo = doc.createElement('label');
            rotulo.className = 'flex flex-col gap-1 text-caption text-muted';
            const textoRotulo = doc.createElement('span');
            textoRotulo.className = 'sr-only';
            textoRotulo.textContent = `Etiquetas de ${item.descricao}`;
            const campo = doc.createElement('input');
            campo.type = 'number';
            campo.min = '1';
            campo.max = String(LIMITE);
            campo.inputMode = 'numeric';
            campo.value = String(item.quantidade);
            campo.className = 'block w-full rounded-sm border border-input bg-field px-2 py-1 text-right text-body-md tabular-nums text-text focus:outline-3 focus:outline-offset-0 focus:outline-ring';
            campo.addEventListener('change', () => {
                const n = quantidadeValida(campo.value);
                if (n === null) {
                    mostrarToast({ mensagem: 'Use um número inteiro de 1 a 300.', variante: 'warning' }, doc);
                } else {
                    item.quantidade = n;
                }
                desenhar();
            });
            rotulo.append(textoRotulo, campo);

            const remover = doc.createElement('button');
            remover.type = 'button';
            remover.className = 'relative inline-flex size-8 items-center justify-center rounded-md text-muted hover:bg-surface-subtle hover:text-danger-ink focus-visible:outline-3 focus-visible:outline-ring';
            const textoRemover = doc.createElement('span');
            textoRemover.className = 'sr-only';
            textoRemover.textContent = `Tirar ${item.descricao}`;
            remover.append(criarIcone('trash-2', 'size-4', doc), textoRemover);
            remover.addEventListener('click', () => {
                itens.splice(indice, 1);
                desenhar();
                busca?.focus();
            });

            linha.append(nome, rotulo, remover);

            return linha;
        }));

        vazio.hidden = itens.length > 0;
        limpar.hidden = itens.length === 0;
        resumoProdutos.textContent = itens.length > 0 ? `(${itens.length})` : '';
        atualizarLink();
    }

    function adicionar(produto) {
        const existente = itens.find((item) => item.id === produto.cadastro);
        if (existente) {
            existente.quantidade = Math.min(existente.quantidade + 1, LIMITE);
        } else {
            itens.push({ id: produto.cadastro, descricao: produto.valor, detalhe: produto.detalhe ?? '', quantidade: 1 });
        }
        desenhar();
    }

    function limparBusca() {
        busca.value = '';
        doc.getElementById('etiquetas-busca-id').value = '';
        busca.setCustomValidity('');
        busca.focus();
    }

    buscaWrapper?.addEventListener('autocomplete-escolha', (evento) => {
        if (evento.detail?.dados) {
            adicionar(evento.detail.dados);
        }
        limparBusca();
    });

    // Enter sem escolher da lista: leitor de código de barras.
    busca?.addEventListener('keydown', async (evento) => {
        if (evento.key !== 'Enter' || busca.getAttribute('aria-activedescendant')) {
            return;
        }
        evento.preventDefault();
        const termo = busca.value.trim();
        if (termo === '') {
            return;
        }
        try {
            const resultado = await get(urlDeBusca(raiz.dataset.buscar, termo));
            const escolhido = resultado.find((r) => r.exato) ?? (resultado.length === 1 ? resultado[0] : null);
            if (escolhido) {
                adicionar(escolhido);
                limparBusca();
            } else {
                mostrarToast({ mensagem: resultado.length ? 'Mais de um produto encontrado: escolha na lista.' : 'Nenhum produto com esse código ou nome.', variante: 'warning' }, doc);
            }
        } catch {
            mostrarToast({ mensagem: 'Não foi possível buscar o produto. Tente de novo.', variante: 'danger' }, doc);
        }
    });

    limpar?.addEventListener('click', () => {
        itens.splice(0, itens.length);
        desenhar();
        busca?.focus();
    });

    preco?.addEventListener('change', atualizarLink);

    imprimir?.addEventListener('click', (evento) => {
        if (imprimir.getAttribute('aria-disabled') === 'true') {
            evento.preventDefault();
            evento.stopImmediatePropagation();
        }
    }, true);

    desenhar();
}
