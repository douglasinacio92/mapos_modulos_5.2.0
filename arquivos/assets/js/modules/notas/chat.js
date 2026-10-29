// Chat flutuante do mural (Daitech), views/notas/partes/chat.php.
//
// - A cada 30 s (e quando a aba volta ao foco) consulta notas/novidades: o
//   contador mostra as não lidas e uma nota nova vira toast (com som, se o
//   navegador deixar).
// - Aberta, a janela carrega notas/historico (que marca como lido), atualiza
//   a cada 10 s e envia por notas/enviar. Tudo por lib/http.js (CSRF e
//   X-Requested-With); o texto entra por textContent, nunca como HTML.

import { get as getPadrao, post as postPadrao } from '../../lib/http.js';
import { mostrarToast } from '../../lib/toast.js';

const INTERVALO_FECHADO = 30000;
const INTERVALO_ABERTO = 10000;

export function criarMensagem(nota, doc = document) {
    const item = doc.createElement('li');
    item.className = `flex max-w-[85%] flex-col gap-0.5 rounded-lg px-3 py-2 text-body-md shadow-elev-1 ${nota.minha ? 'self-end bg-primary-tint text-text' : 'self-start bg-surface text-text'}`;

    if (!nota.minha) {
        const autor = doc.createElement('span');
        autor.className = 'text-caption font-semibold text-text';
        autor.textContent = nota.autor;
        item.append(autor);
    }

    const texto = doc.createElement('span');
    texto.className = 'whitespace-pre-line [overflow-wrap:anywhere]';
    texto.textContent = nota.texto;

    const hora = doc.createElement('span');
    hora.className = 'self-end text-[0.6875rem] text-muted';
    hora.textContent = nota.data;

    item.append(texto, hora);

    return item;
}

export default function iniciar(raiz, { get = getPadrao, post = postPadrao, doc = document } = {}) {
    const janela = raiz.querySelector('#chat-mural');
    const abrir = raiz.querySelector('[data-chat-abrir]');
    const contador = raiz.querySelector('[data-chat-contador]');
    const lista = raiz.querySelector('[data-chat-lista]');
    const form = raiz.querySelector('[data-chat-form]');
    const campo = form?.querySelector('textarea');
    if (!janela || !abrir || !lista || !form || !campo) {
        return;
    }

    let ultimoAvisado = 0;
    let temporizador = null;
    const aberta = () => !janela.hidden;

    function mostrarContador(novas) {
        contador.textContent = novas > 99 ? '99+' : String(novas);
        contador.hidden = novas <= 0;
        abrir.querySelector('.sr-only').textContent = novas > 0 ? `Mural da equipe, ${novas} não lida${novas === 1 ? '' : 's'}` : 'Mural da equipe';
    }

    function renderizar(notas) {
        lista.replaceChildren(...(notas.length ? notas.map((n) => criarMensagem(n, doc)) : [Object.assign(doc.createElement('li'), { className: 'text-center text-caption text-muted', textContent: 'Nenhuma mensagem ainda.' })]));
    }

    function tocarSom() {
        try {
            const som = new Audio(raiz.dataset.somUrl);
            som.volume = 0.6;
            som.play().catch(() => {});
        } catch {
            // Sem áudio: o toast já avisa.
        }
    }

    async function consultar() {
        if (doc.hidden) {
            return;
        }
        try {
            if (aberta()) {
                const { notas } = await get(raiz.dataset.historicoUrl);
                renderizar(notas ?? []);
                mostrarContador(0);
                return;
            }
            const resposta = await get(raiz.dataset.novidadesUrl);
            mostrarContador(resposta.novas ?? 0);
            if (resposta.id && resposta.id > ultimoAvisado) {
                if (ultimoAvisado !== 0) {
                    mostrarToast({ titulo: `Nova nota de ${resposta.autor}`, mensagem: resposta.texto ?? '', variante: 'info', duracao: 8000 }, doc);
                    tocarSom();
                }
                ultimoAvisado = resposta.id;
            } else if (ultimoAvisado === 0) {
                ultimoAvisado = -1;
            }
        } catch {
            // Sessão expirada ou rede fora: tenta de novo na próxima volta.
        }
    }

    function agendar() {
        clearTimeout(temporizador);
        temporizador = setTimeout(async () => {
            await consultar();
            agendar();
        }, aberta() ? INTERVALO_ABERTO : INTERVALO_FECHADO);
    }

    function alternar(abrirJanela) {
        janela.hidden = !abrirJanela;
        abrir.setAttribute('aria-expanded', String(abrirJanela));
        if (abrirJanela) {
            consultar();
            campo.focus();
        } else {
            abrir.focus();
        }
        agendar();
    }

    abrir.addEventListener('click', () => alternar(!aberta()));
    raiz.querySelector('[data-chat-fechar]')?.addEventListener('click', () => alternar(false));
    janela.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            alternar(false);
        }
    });

    campo.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter' && !evento.shiftKey) {
            evento.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const texto = campo.value.trim();
        if (texto === '') {
            return;
        }
        campo.disabled = true;
        try {
            const { notas } = await post(raiz.dataset.enviarUrl, { texto });
            campo.value = '';
            renderizar(notas ?? []);
        } catch (erro) {
            mostrarToast({ mensagem: erro?.message || 'Não foi possível enviar.', variante: 'danger' }, doc);
        } finally {
            campo.disabled = false;
            campo.focus();
        }
    });

    doc.addEventListener('visibilitychange', () => {
        if (!doc.hidden) {
            consultar();
        }
    });

    consultar();
    agendar();
}
