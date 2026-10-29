// Módulos Daitech: aplicativo instalável (PWA).
//
// Registra o sw.js e mostra o aviso "Instale o Daitech-OS":
// - Chrome, Edge e Android: quando o navegador dispara beforeinstallprompt, o
//   botão Instalar abre a janela de instalação do próprio navegador;
// - iPhone e iPad: o Safari não tem esse evento, então o aviso explica o
//   "Compartilhar › Adicionar à Tela de Início".
// "Agora não" esconde o aviso por 30 dias neste navegador.

const CHAVE = 'daitech-instalar-adiado';
const TRINTA_DIAS = 30 * 24 * 60 * 60 * 1000;

function lerAdiado(janela) {
    try {
        return Number(janela.localStorage.getItem(CHAVE)) || 0;
    } catch {
        return 0;
    }
}

function adiar(janela, agora) {
    try {
        janela.localStorage.setItem(CHAVE, String(agora));
    } catch {
        // Sem armazenamento (aba anônima): o aviso volta na próxima visita.
    }
}

export function jaInstalado(janela) {
    return Boolean(janela.matchMedia?.('(display-mode: standalone)').matches || janela.navigator.standalone);
}

export function ehIos(navegador) {
    const ua = navegador.userAgent || '';

    return /iphone|ipad|ipod/i.test(ua) || (navegador.platform === 'MacIntel' && navegador.maxTouchPoints > 1);
}

export function podeMostrar(janela, agora = Date.now()) {
    return !jaInstalado(janela) && agora - lerAdiado(janela) > TRINTA_DIAS;
}

export default function iniciar(raiz, { janela = window, agora = () => Date.now() } = {}) {
    const aviso = raiz.querySelector('[data-instalar-aviso]');
    const confirmar = raiz.querySelector('[data-instalar-confirmar]');
    const fechar = raiz.querySelector('[data-instalar-fechar]');
    let pedido = null;

    if ('serviceWorker' in janela.navigator && raiz.dataset.swUrl) {
        janela.navigator.serviceWorker.register(raiz.dataset.swUrl, { scope: raiz.dataset.escopo }).catch((erro) => {
            console.warn('[instalar] service worker não registrado', erro);
        });
    }

    const mostrar = () => {
        if (aviso && podeMostrar(janela, agora())) {
            aviso.hidden = false;
        }
    };
    const esconder = () => {
        if (aviso) {
            aviso.hidden = true;
        }
    };

    janela.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        pedido = evento;
        mostrar();
    });

    janela.addEventListener('appinstalled', () => {
        pedido = null;
        esconder();
    });

    if (ehIos(janela.navigator) && !jaInstalado(janela)) {
        raiz.querySelector('[data-instalar-ios]')?.removeAttribute('hidden');
        confirmar?.setAttribute('hidden', '');
        if (fechar) {
            fechar.textContent = 'Entendi';
        }
        mostrar();
    }

    confirmar?.addEventListener('click', async () => {
        if (!pedido) {
            return;
        }
        pedido.prompt();
        const escolha = await pedido.userChoice.catch(() => null);
        pedido = null;
        esconder();
        if (escolha?.outcome !== 'accepted') {
            adiar(janela, agora());
        }
    });

    fechar?.addEventListener('click', () => {
        adiar(janela, agora());
        esconder();
    });
}
