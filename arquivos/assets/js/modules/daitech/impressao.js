// Módulos Daitech: impressão direta, sem a janela de impressão.
//
// Com a impressão direta ligada (Configurações › Módulos Daitech), um clique
// num link com data-imprimir="cupom|a4|etiqueta" não abre a aba de impressão:
// 1. a página de impressão é carregada num iframe escondido, sem o script que
//    abre a janela de impressão;
// 2. as imagens do sistema (logo, anexos) viram data: URI, porque o agente
//    não tem a sessão do usuário;
// 3. no cupom, as duas vias vão num documento só, uma por página, com a
//    página da altura da via maior (80mm x altura): a impressora corta entre
//    as páginas;
// 4. o HTML vai para o agente do computador da loja (agente-impressao/agente.ps1),
//    que gera o PDF com o Edge e manda para a impressora configurada.
// Se o agente não responder, abre a impressão do navegador, como antes.

import { mostrarToast } from '../../lib/toast.js';

const AJUSTE = { cupom: 'noscale', etiqueta: 'noscale', a4: 'fit' };
const ORIENTACOES = ['portrait', 'landscape'];
const ESPERA_DO_AGENTE = 120000;

/** Opções do SumatraPDF para o tipo: escala e, na etiqueta, a orientação configurada. */
export function ajusteDoTipo(tipo, orientacao = '') {
    const ajuste = AJUSTE[tipo] ?? 'fit';

    return tipo === 'etiqueta' && ORIENTACOES.includes(orientacao) ? `${ajuste},${orientacao}` : ajuste;
}

/** Troca os url(...) relativos de um CSS por endereços completos (o CSS vai dentro do HTML). */
export function absolutizarCss(css, urlDoCss) {
    return String(css).replace(/url\(\s*(['"]?)(?!data:|https?:|\/\/|#)([^'")]+)\1\s*\)/gi, (_, aspas, caminho) => `url(${aspas}${new URL(caminho, urlDoCss).href}${aspas})`);
}
const PX_POR_MM = 96 / 25.4;

/** Tira os <script> da página de impressão e põe o <base> (o agente abre como arquivo). */
export function limparHtml(html, base) {
    const semScript = String(html).replace(/<script\b[^>]*>[\s\S]*?<\/script\s*>/gi, '');
    const tagBase = `<base href="${String(base).replace(/"/g, '&quot;')}">`;

    return /<head[^>]*>/i.test(semScript)
        ? semScript.replace(/<head([^>]*)>/i, `<head$1>${tagBase}`)
        : tagBase + semScript;
}

/**
 * CSS de página por tipo de impressão. altura = altura da via maior do cupom,
 * em mm, com uma folga para a fonte da impressão nunca cortar o fim da via.
 */
export function cssDaPagina(tipo, altura = 0) {
    if (tipo === 'a4') {
        return '@page { size: A4; margin: 10mm; }';
    }
    if (tipo === 'cupom') {
        return [
            `@page { size: 80mm ${Math.ceil(altura * 1.06 + 8)}mm; margin: 0; }`,
            'html, body { margin: 0; padding: 0; }',
            '.cupom { margin: 0 !important; box-shadow: none !important; break-inside: avoid; }',
            '.cupom:not(:last-of-type) { break-after: page !important; }',
            '.cupom:last-of-type { break-after: auto !important; }',
        ].join('\n');
    }

    return '';
}

/** Coloca as fontes do sistema dentro do CSS (data: URI): o Edge não baixa nada. */
async function embutirFontes(css, origem) {
    const urls = new Set(Array.from(css.matchAll(/url\((['"]?)(https?:[^'")]+\.(?:woff2?|ttf|otf)(?:\?[^'")]*)?)\1\)/gi), (m) => m[2]));
    let resultado = css;
    await Promise.all(Array.from(urls, async (url) => {
        if (new URL(url).origin !== origem) {
            return;
        }
        try {
            const dataUri = await imagemComoDataUri(url);
            resultado = resultado.split(url).join(dataUri);
        } catch {
            // fica o endereço: o Edge baixa a fonte
        }
    }));

    return resultado;
}

async function imagemComoDataUri(src) {
    const resposta = await fetch(src, { credentials: 'same-origin' });
    if (!resposta.ok) {
        throw new Error(`imagem ${resposta.status}`);
    }
    const blob = await resposta.blob();

    return new Promise((resolver, rejeitar) => {
        const leitor = new FileReader();
        leitor.onload = () => resolver(leitor.result);
        leitor.onerror = () => rejeitar(leitor.error);
        leitor.readAsDataURL(blob);
    });
}

/**
 * Monta o documento que vai para o agente, a partir da URL da página de
 * impressão (as duas vias do cupom vão juntas, uma por página).
 */
export async function montarImpressao(url, tipo, doc = document) {
    const resposta = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    if (!resposta.ok) {
        throw new Error(`página de impressão ${resposta.status}`);
    }
    const base = new URL('.', new URL(url, doc.baseURI)).href;
    const html = limparHtml(await resposta.text(), base);

    const quadro = doc.createElement('iframe');
    quadro.setAttribute('aria-hidden', 'true');
    quadro.tabIndex = -1;
    quadro.className = 'fixed -left-[9999px] top-0 h-[1px] w-[900px] border-0';
    doc.body.append(quadro);

    try {
        await new Promise((resolver) => {
            quadro.addEventListener('load', resolver, { once: true });
            quadro.srcdoc = html;
        });
        const pagina = quadro.contentDocument;
        await pagina.fonts?.ready;

        const origem = doc.location.origin;
        // O CSS vai dentro do HTML (só na cópia que vai para o agente: na tela, a
        // CSP não deixa <style> sem nonce): o Edge não precisa baixar nada.
        const estilos = new Map();
        await Promise.all(Array.from(pagina.querySelectorAll('link[rel="stylesheet"]'), async (link) => {
            if (new URL(link.href).origin !== origem) {
                return;
            }
            try {
                const css = absolutizarCss(await (await fetch(link.href)).text(), link.href);
                estilos.set(link.getAttribute('href'), await embutirFontes(css, origem));
            } catch {
                // fica o <link>: o Edge baixa o CSS
            }
        }));
        await Promise.all(Array.from(pagina.images, async (img) => {
            if (img.src && !img.src.startsWith('data:') && new URL(img.src).origin === origem) {
                try {
                    img.src = await imagemComoDataUri(img.src);
                } catch {
                    img.remove();
                }
            }
        }));

        const documento = (css) => {
            const copia = pagina.documentElement.cloneNode(true);
            copia.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
                const css = estilos.get(link.getAttribute('href'));
                if (css !== undefined) {
                    const estilo = pagina.createElement('style');
                    estilo.textContent = css;
                    link.replaceWith(estilo);
                }
            });
            if (css !== '') {
                const estilo = pagina.createElement('style');
                estilo.textContent = css;
                copia.querySelector('head')?.append(estilo);
            }

            return '<!DOCTYPE html>\n' + copia.outerHTML;
        };

        if (tipo === 'cupom') {
            const alturas = Array.from(pagina.querySelectorAll('.cupom'), (via) => via.getBoundingClientRect().height / PX_POR_MM);

            return [documento(cssDaPagina('cupom', Math.max(0, ...alturas)))];
        }

        return [documento(cssDaPagina(tipo))];
    } finally {
        quadro.remove();
    }
}

export async function enviarAoAgente(agente, dados, fetchImpl = fetch) {
    const controle = typeof AbortController === 'function' ? new AbortController() : null;
    const prazo = controle ? setTimeout(() => controle.abort(), ESPERA_DO_AGENTE) : null;
    let resposta;
    try {
        resposta = await fetchImpl(`${agente}/imprimir`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados),
            signal: controle?.signal,
        });
    } finally {
        clearTimeout(prazo);
    }
    const corpo = await resposta.json().catch(() => ({}));
    if (!resposta.ok || corpo.ok !== true) {
        throw new Error(corpo.erro || `agente ${resposta.status}`);
    }

    return corpo;
}

export default function iniciar(raiz, { doc = document, janela = window } = {}) {
    const agente = raiz.dataset.agente;
    const orientacao = raiz.dataset.orientacaoEtiqueta || '';
    let impressoras = {};
    try {
        impressoras = JSON.parse(raiz.dataset.impressoras || '{}');
    } catch {
        return;
    }

    doc.addEventListener('click', async (evento) => {
        const link = evento.target.closest?.('a[data-imprimir]');
        const tipo = link?.dataset.imprimir;
        const impressora = tipo ? impressoras[tipo] : null;
        if (!link || !impressora || evento.ctrlKey || evento.metaKey || evento.shiftKey) {
            return; // sem impressora para esse tipo, ou Ctrl+clique: impressão do navegador
        }
        evento.preventDefault();
        if (link.getAttribute('aria-busy') === 'true') {
            return;
        }
        link.setAttribute('aria-busy', 'true');

        try {
            const documentos = await montarImpressao(link.href, tipo, doc);
            await enviarAoAgente(agente, { impressora, tipo, ajuste: ajusteDoTipo(tipo, orientacao), documentos });
            mostrarToast({ mensagem: `Enviado para a impressora ${impressora}.`, variante: 'success' }, doc);
        } catch (erro) {
            console.warn('[impressao] impressão direta falhou', erro);
            mostrarToast({ mensagem: 'Não deu para imprimir direto (o agente de impressão está aberto neste computador?). Abrindo a impressão do navegador.', variante: 'warning' }, doc);
            janela.open(link.href, '_blank', 'noopener');
        } finally {
            link.removeAttribute('aria-busy');
        }
    });
}
