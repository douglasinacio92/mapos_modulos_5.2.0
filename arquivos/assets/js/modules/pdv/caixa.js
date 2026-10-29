// Carrinho do PDV e do orçamento rápido (Daitech), views/pdv/carrinho.php.
//
// Estado em memória (os itens), desenhado com textContent (nunca HTML).
// - Busca: o autocomplete da v5 (lib/autocomplete.js) lista produtos e
//   serviços de pdv/buscar; escolher adiciona ao carrinho. Enter sem escolher
//   da lista (o leitor de código de barras) busca o termo e adiciona o
//   resultado exato, ou o único resultado.
// - Item avulso, quantidade e preço editáveis, remover e limpar.
// - Totais, desconto e troco são uma prévia: o servidor recalcula ao gravar.
// - Gravar (botão ou Ctrl+Enter): PDV em pdv/finalizar (abre o modal com o
//   cupom); orçamento em orcamentos/salvar (vai para a edição).

import { iniciarAutocompletes, urlDeBusca } from '../../lib/autocomplete.js';
import { lerJson } from '../../lib/dados.js';
import { get as getPadrao, post as postPadrao } from '../../lib/http.js';
import { criarIcone } from '../../lib/icone.js';
import { mostrarToast } from '../../lib/toast.js';

const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export const dinheiro = (valor) => moeda.format(Number(valor) || 0);

/** "1.234,56", "1234.56", "10" → número; vazio ou inválido → null. */
export function numero(texto) {
    let valor = String(texto ?? '').replace(/[R$\s]/g, '').trim();
    if (valor === '') {
        return null;
    }
    if (valor.includes(',')) {
        valor = valor.replace(/\./g, '').replace(',', '.');
    }
    const n = Number(valor);

    return Number.isFinite(n) ? n : null;
}

const arredondar = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

export function subtotal(itens) {
    return arredondar(itens.reduce((soma, item) => soma + arredondar(item.preco * item.quantidade), 0));
}

/** Prévia do desconto, com a mesma regra do servidor (daitechDesconto()). */
export function calcularDesconto(bruto, tipo, texto) {
    const valor = numero(texto);
    if (valor === null || valor === 0) {
        return { desconto: 0, total: bruto, erro: null };
    }
    if (valor < 0) {
        return { desconto: 0, total: bruto, erro: 'O desconto não pode ser negativo.' };
    }
    const desconto = tipo === 'porcento' ? arredondar(bruto * valor / 100) : arredondar(valor);
    if ((tipo === 'porcento' && valor > 100) || desconto > bruto) {
        return { desconto: 0, total: bruto, erro: 'O desconto não pode ser maior que o total.' };
    }

    return { desconto, total: arredondar(bruto - desconto), erro: null };
}

const ROTULOS = { produto: 'Produto', servico: 'Serviço', avulso: 'Avulso' };
const PILL = {
    produto: 'bg-info-soft text-info-ink',
    servico: 'bg-progress-soft text-progress-ink',
    avulso: 'bg-neutral-soft text-neutral-ink',
};

export default function iniciar(raiz, { get = getPadrao, post = postPadrao, doc = document } = {}) {
    const dados = lerJson('pdv-dados', {}, doc);
    const pdv = dados.modo === 'pdv';
    const $ = (seletor) => raiz.querySelector(seletor) ?? doc.querySelector(seletor);

    const itens = (dados.itens ?? []).map((item) => ({ ...item, quantidade: Number(item.quantidade) || 1, preco: Number(item.preco) || 0 }));
    let gravando = false;

    const lista = $('[data-pdv-lista]');
    const vazio = $('[data-pdv-vazio]');
    const cabecalho = $('[data-pdv-cabecalho]');
    const busca = doc.getElementById('pdv-busca');
    const tipoDesconto = doc.getElementById('pdv-tipo-desconto');
    const campoDesconto = doc.getElementById('pdv-desconto');
    const botaoGravar = $('[data-pdv-gravar]');

    iniciarAutocompletes(raiz);

    function adicionar(novo) {
        const igual = novo.tipo !== 'avulso'
            ? itens.find((item) => item.tipo === novo.tipo && item.id === novo.id && item.preco === novo.preco)
            : null;
        if (igual) {
            igual.quantidade = arredondar(igual.quantidade + (novo.quantidade || 1));
        } else {
            itens.push({ quantidade: 1, ...novo });
        }
        if (novo.tipo === 'produto' && novo.estoque !== null && novo.estoque !== undefined && (igual ?? novo).quantidade > novo.estoque) {
            mostrarToast({ mensagem: `${novo.descricao}: só há ${novo.estoque} em estoque.`, variante: 'warning' }, doc);
        }
        desenhar();
    }

    function campoNumero(rotulo, valor, aoMudar, classe) {
        const wrapper = doc.createElement('label');
        wrapper.className = `flex flex-col gap-1 text-caption text-muted ${classe}`;
        // No computador o rótulo vira o cabeçalho da lista (uma linha por item).
        const texto = doc.createElement('span');
        texto.className = 'sm:sr-only';
        texto.textContent = rotulo;
        wrapper.append(texto);
        const campo = doc.createElement('input');
        campo.type = 'text';
        campo.inputMode = 'decimal';
        campo.value = valor;
        campo.className = 'block w-full rounded-sm border border-input bg-field px-2 py-1 text-right text-body-md tabular-nums text-text focus:outline-3 focus:outline-offset-0 focus:outline-ring';
        campo.addEventListener('change', () => aoMudar(campo));
        wrapper.append(campo);

        return wrapper;
    }

    function desenhar() {
        lista.replaceChildren(...itens.map((item, indice) => {
            const linha = doc.createElement('li');
            linha.className = 'grid grid-cols-[4.5rem_minmax(0,1fr)_auto_2rem] items-end gap-2 px-4 py-2 sm:grid-cols-[minmax(0,1fr)_4.5rem_6.5rem_6.5rem_2rem] sm:items-center sm:gap-3';

            const nome = doc.createElement('div');
            nome.className = 'col-span-full sm:col-span-1 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1';
            const pill = doc.createElement('span');
            pill.className = `inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[0.75rem] font-semibold ${PILL[item.tipo]}`;
            pill.textContent = ROTULOS[item.tipo];
            const descricao = doc.createElement('span');
            descricao.className = 'text-body-md font-medium [overflow-wrap:anywhere] text-text';
            descricao.textContent = item.descricao;
            nome.append(descricao, pill);

            const quantidade = campoNumero('Qtd.', String(item.quantidade).replace('.', ','), (campo) => {
                const n = numero(campo.value);
                if (n === null || n <= 0 || (item.tipo === 'produto' && !Number.isInteger(n))) {
                    mostrarToast({ mensagem: item.tipo === 'produto' ? 'Quantidade de produto é um número inteiro maior que zero.' : 'Informe uma quantidade maior que zero.', variante: 'warning' }, doc);
                } else {
                    item.quantidade = n;
                }
                desenhar();
            }, '');

            const preco = campoNumero('Preço', String(item.preco.toFixed(2)).replace('.', ','), (campo) => {
                const n = numero(campo.value);
                if (n === null || n < 0) {
                    mostrarToast({ mensagem: 'Informe um preço válido, como 49,90.', variante: 'warning' }, doc);
                } else {
                    item.preco = arredondar(n);
                }
                desenhar();
            }, '');

            const sub = doc.createElement('p');
            sub.className = 'flex flex-col gap-1 text-right text-caption text-muted';
            const subRotulo = doc.createElement('span');
            subRotulo.className = 'sm:sr-only';
            subRotulo.textContent = 'Subtotal';
            const valor = doc.createElement('span');
            valor.className = 'text-body-md font-semibold tabular-nums text-text';
            valor.textContent = dinheiro(item.preco * item.quantidade);
            sub.append(subRotulo, valor);

            const remover = doc.createElement('button');
            remover.type = 'button';
            remover.className = 'inline-flex size-8 items-center justify-center justify-self-end rounded-md text-muted hover:bg-surface-subtle hover:text-danger-ink focus-visible:outline-3 focus-visible:outline-ring';
            const rotulo = doc.createElement('span');
            rotulo.className = 'sr-only';
            rotulo.textContent = `Remover ${item.descricao}`;
            remover.append(criarIcone('trash-2', 'size-4', doc), rotulo);
            remover.addEventListener('click', () => {
                itens.splice(indice, 1);
                desenhar();
                busca?.focus();
            });

            linha.append(nome, quantidade, preco, sub, remover);

            return linha;
        }));

        vazio.hidden = itens.length > 0;
        if (cabecalho) {
            cabecalho.hidden = itens.length === 0;
        }
        $('[data-pdv-limpar]').hidden = itens.length === 0;
        const unidades = itens.reduce((soma, item) => soma + (item.tipo === 'produto' ? item.quantidade : 1), 0);
        $('[data-pdv-quantidade]').textContent = itens.length ? `(${itens.length} ${itens.length === 1 ? 'linha' : 'linhas'}, ${unidades} ${unidades === 1 ? 'unidade' : 'unidades'})` : '';
        totais();
    }

    function totais() {
        const bruto = subtotal(itens);
        const { desconto, total, erro } = calcularDesconto(bruto, tipoDesconto?.value, campoDesconto?.value);
        $('[data-pdv-subtotal]').textContent = dinheiro(bruto);
        $('[data-pdv-valor-desconto]').textContent = desconto > 0 ? `− ${dinheiro(desconto)}` : dinheiro(0);
        $('[data-pdv-total]').textContent = dinheiro(total);
        const aviso = $('[data-pdv-erro-desconto]');
        aviso.textContent = erro ?? '';
        aviso.classList.toggle('hidden', !erro);

        if (pdv) {
            const recebido = numero(doc.getElementById('pdv-recebido')?.value);
            $('[data-pdv-troco]').textContent = dinheiro(recebido !== null && recebido > total ? recebido - total : 0);
        }

        return { bruto, total, erro };
    }

    // Busca: escolher da lista adiciona.
    const buscaWrapper = $('[data-pdv-busca]');
    buscaWrapper?.addEventListener('autocomplete-escolha', (evento) => {
        const item = evento.detail?.dados;
        if (item) {
            adicionar({ tipo: item.tipo, id: item.cadastro, descricao: item.valor, preco: Number(item.preco) || 0, estoque: item.estoque });
        }
        busca.value = '';
        doc.getElementById('pdv-busca-id').value = '';
        busca.setCustomValidity('');
        busca.focus();
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
            const resultado = await get(urlDeBusca(dados.urls.buscar, termo));
            const escolhido = resultado.find((r) => r.exato) ?? (resultado.length === 1 ? resultado[0] : null);
            if (escolhido) {
                adicionar({ tipo: escolhido.tipo, id: escolhido.cadastro, descricao: escolhido.valor, preco: Number(escolhido.preco) || 0, estoque: escolhido.estoque });
                busca.value = '';
                busca.setCustomValidity('');
                busca.dispatchEvent(new Event('blur'));
            } else if (resultado.length === 0) {
                mostrarToast({ mensagem: `Nada encontrado para "${termo}".`, variante: 'warning' }, doc);
            }
        } catch (erro) {
            mostrarToast({ mensagem: erro?.message || 'Não foi possível buscar.', variante: 'danger' }, doc);
        }
    }, true);

    // Item avulso.
    $('[data-pdv-avulso-adicionar]')?.addEventListener('click', () => {
        const descricao = doc.getElementById('pdv-avulso-descricao');
        const quantidade = numero(doc.getElementById('pdv-avulso-quantidade').value) ?? 1;
        const preco = numero(doc.getElementById('pdv-avulso-preco').value);
        if (descricao.value.trim() === '' || preco === null || preco < 0 || quantidade <= 0) {
            mostrarToast({ mensagem: 'Informe a descrição, a quantidade e o preço do item avulso.', variante: 'warning' }, doc);
            return;
        }
        adicionar({ tipo: 'avulso', id: null, descricao: descricao.value.trim(), preco: arredondar(preco), quantidade });
        descricao.value = '';
        doc.getElementById('pdv-avulso-preco').value = '';
        doc.getElementById('pdv-avulso-quantidade').value = '1';
        descricao.focus();
    });

    $('[data-pdv-limpar]')?.addEventListener('click', () => {
        itens.splice(0);
        desenhar();
        busca?.focus();
    });

    [tipoDesconto, campoDesconto, doc.getElementById('pdv-recebido')].forEach((campo) => campo?.addEventListener('input', totais));

    // Pagamento.
    const aPrazo = doc.getElementById('pdv-a-prazo');
    aPrazo?.addEventListener('change', () => {
        $('[data-pdv-vencimento]').hidden = !aPrazo.checked;
    });
    raiz.addEventListener('change', (evento) => {
        if (evento.target.name === 'forma') {
            $('[data-pdv-dinheiro]').hidden = evento.target.value !== 'dinheiro';
        }
    });

    async function gravar() {
        if (gravando) {
            return;
        }
        if (itens.length === 0) {
            mostrarToast({ mensagem: 'Adicione pelo menos um item.', variante: 'warning' }, doc);
            busca?.focus();
            return;
        }
        const { erro } = totais();
        if (erro) {
            campoDesconto?.focus();
            return;
        }
        const cliente = doc.getElementById('pdv-cliente');
        if (cliente?.validity && !cliente.validity.valid) {
            mostrarToast({ mensagem: 'Escolha um cliente da lista ou deixe o campo em branco.', variante: 'warning' }, doc);
            cliente.focus();
            return;
        }

        const corpo = {
            itens: JSON.stringify(itens.map(({ tipo, id, quantidade, preco, descricao }) => ({ tipo, id, quantidade, preco, descricao }))),
            clientes_id: doc.getElementById('pdv-clientes-id')?.value ?? '',
            tipo_desconto: tipoDesconto?.value ?? 'real',
            desconto: campoDesconto?.value ?? '',
        };
        if (pdv) {
            corpo.forma = raiz.querySelector('input[name="forma"]:checked')?.value ?? '';
            corpo.a_prazo = aPrazo?.checked ? '1' : '';
            corpo.vencimento = doc.getElementById('pdv-vencimento')?.value ?? '';
            if (dados.orcamento) {
                corpo.orcamento = String(dados.orcamento);
            }
        } else {
            corpo.observacoes = doc.getElementById('pdv-observacoes')?.value ?? '';
        }

        gravando = true;
        const rotulo = botaoGravar.querySelector('span:not(.sr-only)');
        const original = rotulo?.textContent;
        botaoGravar.disabled = true;
        if (rotulo) {
            rotulo.textContent = botaoGravar.dataset.rotuloCarregando;
        }

        try {
            const resposta = await post(dados.urls.gravar, corpo);
            if (pdv) {
                const modal = doc.getElementById('pdv-concluida');
                modal.querySelector('[data-pdv-concluida-texto]').textContent = resposta.message;
                modal.querySelector('[data-pdv-cupom]').href = resposta.cupom;
                modal.querySelector('[data-pdv-ver-venda]').href = resposta.venda;
                modal.addEventListener('close', () => { globalThis.location.href = dados.urls.novo; }, { once: true });
                modal.showModal();
                modal.querySelector('[data-pdv-cupom]').focus();
            } else {
                globalThis.location.href = resposta.redirecionar;
            }
        } catch (erro) {
            mostrarToast({ mensagem: erro?.message || 'Não foi possível gravar. Tente de novo.', variante: 'danger', duracao: 9000 }, doc);
        } finally {
            gravando = false;
            botaoGravar.disabled = false;
            if (rotulo) {
                rotulo.textContent = original;
            }
        }
    }

    botaoGravar?.addEventListener('click', gravar);
    doc.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter' && (evento.ctrlKey || evento.metaKey)) {
            evento.preventDefault();
            gravar();
        }
    });

    desenhar();
}
