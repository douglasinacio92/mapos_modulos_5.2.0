// Kanban (Daitech), views/kanban/painel.php.
//
// Os cartões ([data-kanban-cartao]) vão de uma coluna ([data-kanban-coluna])
// para outra arrastando (drag and drop nativo do HTML) ou pelo select "Mover
// para" ([data-kanban-mover]), que é o caminho do teclado e do celular. Os
// dois gravam em kanban/mover (lib/http.js, com o CSRF) e só mexem na tela
// depois que o servidor aceita; com erro, o cartão volta e o toast explica.

import { post as postPadrao, get as getPadrao } from '../../lib/http.js';
import { mostrarToast } from '../../lib/toast.js';

// As mesmas classes do components/pill-status.php.
export const PILL = {
    success: 'bg-success-soft text-success-ink',
    warning: 'bg-warning-soft text-warning-ink',
    danger: 'bg-danger-soft text-danger-ink',
    info: 'bg-info-soft text-info-ink',
    progress: 'bg-progress-soft text-progress-ink',
    neutral: 'bg-neutral-soft text-neutral-ink',
};

export function trocarPill(pill, { label, variant }) {
    if (!pill || !PILL[variant]) {
        return;
    }
    Object.values(PILL).forEach((classes) => pill.classList.remove(...classes.split(' ')));
    pill.classList.add(...PILL[variant].split(' '));
    const texto = [...pill.childNodes].find((no) => no.nodeType === 3);
    if (texto) {
        texto.textContent = label;
    } else {
        pill.append(label);
    }
}

function atualizarContadores(raiz) {
    raiz.querySelectorAll('[data-kanban-coluna]').forEach((coluna) => {
        const contador = coluna.querySelector('[data-kanban-contador]');
        if (contador && coluna.dataset.kanbanTotalAjustado !== undefined) {
            contador.textContent = coluna.dataset.kanbanTotalAjustado;
        }
    });
}

function ajustarTotal(coluna, delta) {
    const contador = coluna?.querySelector('[data-kanban-contador]');
    if (!contador) {
        return;
    }
    const atual = Number(contador.textContent) || 0;
    coluna.dataset.kanbanTotalAjustado = String(Math.max(0, atual + delta));
}

export default function iniciar(raiz, { post = postPadrao, get = getPadrao, doc = document } = {}) {
    const tipo = raiz.dataset.tipo;
    let arrastado = null;

    async function mover(cartao, status) {
        const origem = cartao.closest('[data-kanban-coluna]');
        const destino = raiz.querySelector(`[data-kanban-coluna="${CSS.escape(status)}"]`);
        if (!destino || origem === destino) {
            return;
        }
        if (destino.hasAttribute('data-kanban-travada')) {
            mostrarToast({ mensagem: 'Faturar e cancelar a OS é feito na tela da OS.', variante: 'warning' }, doc);
            restaurar(cartao);
            return;
        }

        cartao.setAttribute('aria-busy', 'true');
        try {
            const resposta = await post(raiz.dataset.moverUrl, { tipo, id: cartao.dataset.id, status });
            destino.querySelector('[data-kanban-lista]').prepend(cartao);
            cartao.dataset.status = status;
            const select = cartao.querySelector('[data-kanban-mover]');
            if (select) {
                select.value = status;
            }
            if (resposta.prazo) {
                trocarPill(cartao.querySelector('[data-kanban-prazo]'), resposta.prazo);
            }
            ajustarTotal(origem, -1);
            ajustarTotal(destino, +1);
            atualizarContadores(raiz);
            mostrarToast({ mensagem: resposta.message || 'Cartão movido.', variante: 'success', duracao: 2500 }, doc);
            atualizarResumo();
        } catch (erro) {
            restaurar(cartao);
            mostrarToast({ mensagem: erro?.message || 'Não foi possível mover agora.', variante: 'danger', duracao: 8000 }, doc);
        } finally {
            cartao.removeAttribute('aria-busy');
        }
    }

    function restaurar(cartao) {
        const select = cartao.querySelector('[data-kanban-mover]');
        if (select) {
            select.value = cartao.dataset.status;
        }
    }

    async function atualizarResumo() {
        try {
            const { estatisticas } = await get(raiz.dataset.dadosUrl);
            Object.entries(estatisticas ?? {}).forEach(([chave, valor]) => {
                const cartao = raiz.querySelector(`[data-kanban-kpi="${chave}"]`);
                const alvo = cartao?.querySelector('[data-kpi-valor]') ?? cartao?.querySelector('.font-display');
                if (alvo) {
                    alvo.textContent = String(valor);
                }
            });
        } catch {
            // O resumo é só informativo; a próxima carga da página acerta.
        }
    }

    raiz.addEventListener('change', (evento) => {
        const select = evento.target.closest('[data-kanban-mover]');
        if (select) {
            mover(select.closest('[data-kanban-cartao]'), select.value);
        }
    });

    raiz.addEventListener('dragstart', (evento) => {
        const cartao = evento.target.closest?.('[data-kanban-cartao][draggable="true"]');
        if (!cartao) {
            return;
        }
        arrastado = cartao;
        evento.dataTransfer.effectAllowed = 'move';
        evento.dataTransfer.setData('text/plain', cartao.dataset.id);
        cartao.classList.add('opacity-50');
    });

    raiz.addEventListener('dragend', () => {
        arrastado?.classList.remove('opacity-50');
        arrastado = null;
        raiz.querySelectorAll('[data-kanban-coluna]').forEach((c) => c.classList.remove('ring-2', 'ring-ring'));
    });

    raiz.querySelectorAll('[data-kanban-coluna]').forEach((coluna) => {
        coluna.addEventListener('dragover', (evento) => {
            if (!arrastado) {
                return;
            }
            evento.preventDefault();
            evento.dataTransfer.dropEffect = coluna.hasAttribute('data-kanban-travada') ? 'none' : 'move';
            coluna.classList.add('ring-2', 'ring-ring');
        });
        coluna.addEventListener('dragleave', (evento) => {
            if (!coluna.contains(evento.relatedTarget)) {
                coluna.classList.remove('ring-2', 'ring-ring');
            }
        });
        coluna.addEventListener('drop', (evento) => {
            evento.preventDefault();
            coluna.classList.remove('ring-2', 'ring-ring');
            if (arrastado) {
                mover(arrastado, coluna.dataset.kanbanColuna);
            }
        });
    });
}
