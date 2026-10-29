// Dados do aparelho no formulário da OS (Daitech), views/os_extras/partes/campos.php.
//
// - Assinatura: desenho no <canvas> com Pointer Events (dedo, caneta ou
//   mouse), sem biblioteca externa. Ao soltar, o PNG vai para o campo oculto
//   assinatura_digital; "Limpar" grava vazio; sem mexer, fica "manter".
// - Senha de padrão: os 9 pontos do modal viram "Padrão: 1-5-9" no campo da
//   senha (o mesmo texto da 4.x).

export function textoDoPadrao(pontos) {
    return pontos.length ? `Padrão: ${pontos.join('-')}` : '';
}

export function pontosDoTexto(texto) {
    const m = /^Padr(?:ão|ao):\s*([1-9](?:-[1-9])*)$/u.exec(String(texto ?? '').trim());
    return m ? m[1].split('-').map(Number) : [];
}

function iniciarAssinatura(raiz) {
    const tela = raiz.querySelector('[data-assinatura-tela]');
    const valor = raiz.querySelector('[data-assinatura-valor]');
    const estado = raiz.querySelector('[data-assinatura-estado]');
    if (!tela || !valor) {
        return;
    }

    const ctx = tela.getContext('2d');
    let desenhando = false;
    let ultimo = null;

    function preparar() {
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
    }
    preparar();

    const ponto = (evento) => {
        const r = tela.getBoundingClientRect();
        return { x: (evento.clientX - r.left) * (tela.width / r.width), y: (evento.clientY - r.top) * (tela.height / r.height) };
    };

    function limparTela() {
        ctx.clearRect(0, 0, tela.width, tela.height);
        preparar();
    }

    tela.addEventListener('pointerdown', (evento) => {
        evento.preventDefault();
        const atual = raiz.querySelector('[data-assinatura-atual]');
        if (atual) {
            atual.remove();
            limparTela();
        }
        desenhando = true;
        ultimo = ponto(evento);
        tela.setPointerCapture(evento.pointerId);
        ctx.beginPath();
        ctx.arc(ultimo.x, ultimo.y, 1, 0, Math.PI * 2);
        ctx.fillStyle = '#111827';
        ctx.fill();
    });

    tela.addEventListener('pointermove', (evento) => {
        if (!desenhando) {
            return;
        }
        const novo = ponto(evento);
        ctx.beginPath();
        ctx.moveTo(ultimo.x, ultimo.y);
        ctx.lineTo(novo.x, novo.y);
        ctx.stroke();
        ultimo = novo;
    });

    const terminar = () => {
        if (!desenhando) {
            return;
        }
        desenhando = false;
        valor.value = tela.toDataURL('image/png');
        if (estado) {
            estado.textContent = 'Assinatura pronta. Ela é gravada ao salvar a OS.';
        }
    };
    tela.addEventListener('pointerup', terminar);
    tela.addEventListener('pointercancel', terminar);

    raiz.querySelector('[data-assinatura-limpar]')?.addEventListener('click', () => {
        raiz.querySelector('[data-assinatura-atual]')?.remove();
        limparTela();
        valor.value = '';
        if (estado) {
            estado.textContent = 'Sem assinatura (ao salvar, a anterior é apagada).';
        }
    });
}

function iniciarPadrao(raiz, doc) {
    const modal = doc.getElementById('os-padrao');
    const senha = raiz.querySelector('[data-os-senha]');
    if (!modal || !senha) {
        return;
    }
    const sequencia = modal.querySelector('[data-padrao-sequencia]');
    let pontos = [];

    function desenhar() {
        sequencia.textContent = pontos.join(' → ');
        modal.querySelectorAll('[data-padrao-ponto]').forEach((botao) => {
            botao.setAttribute('aria-pressed', String(pontos.includes(Number(botao.dataset.padraoPonto))));
        });
    }

    modal.addEventListener('click', (evento) => {
        const botao = evento.target.closest('[data-padrao-ponto]');
        if (botao) {
            const n = Number(botao.dataset.padraoPonto);
            if (!pontos.includes(n)) {
                pontos.push(n);
                desenhar();
            }
        } else if (evento.target.closest('[data-padrao-limpar]')) {
            pontos = [];
            desenhar();
        } else if (evento.target.closest('[data-padrao-confirmar]')) {
            if (pontos.length) {
                senha.value = textoDoPadrao(pontos);
                senha.dispatchEvent(new Event('input', { bubbles: true }));
            }
            modal.close();
        }
    });

    // Abrir: mostra o padrão já gravado.
    doc.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-modal-abrir="os-padrao"]')) {
            pontos = pontosDoTexto(senha.value);
            desenhar();
        }
    }, true);
}

export default function iniciar(raiz, { doc = document } = {}) {
    iniciarAssinatura(raiz);
    iniciarPadrao(raiz, doc);
}
