// Módulos Daitech: impressão direta, sem a janela de impressão.
//
// Com a impressão direta ligada (Configurações › Módulos Daitech), um clique
// num link com data-imprimir="cupom|a4|etiqueta" não abre a aba de impressão:
// 1. a página de impressão é montada num iframe escondido, com o CSS de
//    impressão, as fontes e as imagens embutidos (sem o script que abre a
//    janela de impressão);
// 2. cada página vira uma imagem PNG no próprio navegador: cada via do cupom,
//    cada fileira de etiquetas, cada folha A4;
// 3. as imagens vão para o agente do computador da loja
//    (agente-impressao/agente.ps1), que as manda direto para a impressora
//    configurada, sem abrir janela nenhuma.
// A impressão do navegador (com a janela de escolher a impressora) só abre
// quando o agente não está neste computador. Com o agente no ar, um erro vira
// um aviso com o motivo.

import { mostrarToast } from '../../lib/toast.js';

const PX_POR_MM = 96 / 25.4;
const ESPERA_DO_AGENTE = 60000;

/**
 * Por tipo: largura da página em mm (a do iframe onde ela é montada), a
 * largura da imagem em pixels (a resolução da impressora: 203 dpi nas
 * térmicas) e o seletor de cada página.
 */
export const TIPOS = {
    cupom: { larguraMm: 80, pixels: 640, pagina: '.cupom' },
    etiqueta: { larguraMm: 105, pixels: 840, pagina: '.etiquetas' },
    a4: { larguraMm: 210, pixels: 1654, pagina: '.doc-folha', folhaMm: [190, 277] },
};

/** Tira os <script> da página de impressão (o window.print) e põe o <base>. */
export function limparHtml(html, base) {
    const semScript = String(html).replace(/<script\b[^>]*>[\s\S]*?<\/script\s*>/gi, '');
    const tagBase = `<base href="${String(base).replace(/"/g, '&quot;')}">`;

    return /<head[^>]*>/i.test(semScript)
        ? semScript.replace(/<head([^>]*)>/i, `<head$1>${tagBase}`)
        : tagBase + semScript;
}

/** Troca os url(...) relativos de um CSS por endereços completos (o CSS vai dentro do HTML). */
export function absolutizarCss(css, urlDoCss) {
    return String(css).replace(/url\(\s*(['"]?)(?!data:|https?:|\/\/|#)([^'")]+)\1\s*\)/gi, (_, aspas, caminho) => `url(${aspas}${new URL(caminho, urlDoCss).href}${aspas})`);
}

/** As regras de impressão (@media print) passam a valer na tela, onde a imagem é tirada. */
export function cssParaTela(css) {
    return String(css).replace(/@media\s+print\b/gi, '@media all');
}

/**
 * Corta uma página A4 alta em folhas: a altura de cada folha, em pixels da
 * imagem, na proporção da área útil (190 x 277 mm).
 */
export function fatiasDaFolha(alturaPx, larguraPx, [larguraMm, alturaMm] = TIPOS.a4.folhaMm) {
    const porFolha = Math.floor(larguraPx * alturaMm / larguraMm);
    const fatias = [];
    for (let y = 0; y < alturaPx; y += porFolha) {
        fatias.push([y, Math.min(porFolha, alturaPx - y)]);
    }

    return fatias;
}

function lerComoDataUri(blob) {
    return new Promise((resolver, rejeitar) => {
        const leitor = new FileReader();
        leitor.onload = () => resolver(leitor.result);
        leitor.onerror = () => rejeitar(leitor.error);
        leitor.readAsDataURL(blob);
    });
}

async function baixarComoDataUri(url) {
    const resposta = await fetch(url, { credentials: 'same-origin' });
    if (!resposta.ok) {
        throw new Error(`arquivo ${resposta.status}`);
    }

    return lerComoDataUri(await resposta.blob());
}

/** Coloca as fontes do sistema dentro do CSS (data: URI). */
async function embutirFontes(css, origem) {
    const urls = new Set(Array.from(css.matchAll(/url\((['"]?)(https?:[^'")]+\.(?:woff2?|ttf|otf)(?:\?[^'")]*)?)\1\)/gi), (m) => m[2]));
    let resultado = css;
    await Promise.all(Array.from(urls, async (url) => {
        if (new URL(url).origin !== origem) {
            return;
        }
        try {
            resultado = resultado.split(url).join(await baixarComoDataUri(url));
        } catch {
            // sem a fonte: sai com a fonte padrão
        }
    }));

    return resultado;
}

function escaparXml(texto) {
    return String(texto).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

/** Desenha um elemento da página num canvas (SVG com foreignObject). */
async function elementoNoCanvas(elemento, css, classeDoBody, larguraPixels, doc, folga = true) {
    const caixa = elemento.getBoundingClientRect();
    const largura = Math.ceil(caixa.width);
    // Folga embaixo: a imagem nunca corta a última linha da página.
    const altura = Math.ceil(caixa.height) + (folga ? 12 : 0);
    const xhtml = new XMLSerializer().serializeToString(elemento.cloneNode(true));
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${largura}" height="${altura}">`
        + `<foreignObject x="0" y="0" width="${largura}" height="${altura}">`
        + '<html xmlns="http://www.w3.org/1999/xhtml"><head>'
        + `<style>${escaparXml(css)}</style></head>`
        + `<body class="${escaparXml(classeDoBody)}" style="margin:0;padding:0;background:#fff;width:${largura}px">${xhtml}</body></html>`
        + '</foreignObject></svg>';

    const imagem = new Image();
    imagem.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
    await imagem.decode();

    const escala = larguraPixels / largura;
    const canvas = doc.createElement('canvas');
    canvas.width = larguraPixels;
    canvas.height = Math.ceil(altura * escala);
    const contexto = canvas.getContext('2d');
    contexto.fillStyle = '#fff';
    contexto.fillRect(0, 0, canvas.width, canvas.height);
    contexto.drawImage(imagem, 0, 0, canvas.width, canvas.height);

    return canvas;
}

function canvasEmPng(canvas) {
    return canvas.toDataURL('image/png').replace(/^data:image\/png;base64,/, '');
}

/**
 * Monta as páginas que vão para o agente: uma imagem PNG por via do cupom,
 * por fileira de etiquetas ou por folha A4, com o tamanho em mm.
 */
export async function montarPaginas(url, tipo, doc = document) {
    const config = TIPOS[tipo];
    const resposta = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    if (!resposta.ok) {
        throw new Error(`a página de impressão respondeu ${resposta.status}`);
    }
    const base = new URL('.', new URL(url, doc.baseURI)).href;
    const html = limparHtml(await resposta.text(), base);
    const origem = doc.location.origin;
    const nonce = doc.querySelector('meta[name="csp-nonce"]')?.content ?? '';

    // O CSS da página, com as fontes e as regras de impressão, num <style> só.
    const modelo = new DOMParser().parseFromString(html, 'text/html');
    const partes = await Promise.all(Array.from(modelo.querySelectorAll('link[rel="stylesheet"]'), async (link) => {
        const href = new URL(link.getAttribute('href'), base).href;
        if (new URL(href).origin !== origem) {
            return '';
        }
        const css = absolutizarCss(await (await fetch(href)).text(), href);

        return cssParaTela(await embutirFontes(css, origem));
    }));
    const css = `${partes.join('\n')}\nhtml, body { background: #fff !important; }`;
    modelo.querySelectorAll('link[rel="stylesheet"]').forEach((link) => link.remove());
    const estilo = modelo.createElement('style');
    if (nonce) {
        estilo.setAttribute('nonce', nonce); // o iframe herda a CSP desta página
    }
    estilo.textContent = css;
    modelo.head.append(estilo);

    const quadro = doc.createElement('iframe');
    quadro.setAttribute('aria-hidden', 'true');
    quadro.tabIndex = -1;
    quadro.width = String(Math.round(config.larguraMm * PX_POR_MM));
    quadro.height = '1200';
    quadro.className = 'fixed -left-[9999px] top-0 border-0';
    doc.body.append(quadro);

    try {
        await new Promise((resolver) => {
            quadro.addEventListener('load', resolver, { once: true });
            quadro.srcdoc = '<!DOCTYPE html>\n' + modelo.documentElement.outerHTML;
        });
        const pagina = quadro.contentDocument;
        await pagina.fonts?.ready;

        // Imagens do sistema (logo, anexos) viram data: URI: dentro da imagem
        // final não dá para buscar nada.
        await Promise.all(Array.from(pagina.images, async (img) => {
            if (!img.src || img.src.startsWith('data:')) {
                return;
            }
            try {
                if (new URL(img.src).origin !== origem) {
                    throw new Error('outra origem');
                }
                img.src = await baixarComoDataUri(img.src);
                await img.decode().catch(() => {});
            } catch {
                img.remove();
            }
        }));

        const elementos = Array.from(pagina.querySelectorAll(config.pagina));
        if (elementos.length === 0) {
            elementos.push(pagina.body);
        }

        const paginas = [];
        for (const elemento of elementos) {
            // A etiqueta tem altura fixa (21mm): sem folga, para não desalinhar a bobina.
            const canvas = await elementoNoCanvas(elemento, css, pagina.body.className, config.pixels, doc, tipo !== 'etiqueta');
            const mmPorPixel = config.larguraMm / config.pixels;
            if (tipo === 'a4') {
                // Folhas A4: a área útil (190 x 277 mm); uma página alta vira várias folhas.
                for (const [y, altura] of fatiasDaFolha(canvas.height, canvas.width)) {
                    const folha = doc.createElement('canvas');
                    folha.width = canvas.width;
                    folha.height = altura;
                    folha.getContext('2d').drawImage(canvas, 0, y, canvas.width, altura, 0, 0, canvas.width, altura);
                    paginas.push({ png: canvasEmPng(folha), larguraMm: config.folhaMm[0], alturaMm: Math.round(altura * config.folhaMm[0] / canvas.width) });
                }
            } else {
                paginas.push({ png: canvasEmPng(canvas), larguraMm: config.larguraMm, alturaMm: Math.round(canvas.height * mmPorPixel) });
            }
        }

        return paginas;
    } finally {
        quadro.remove();
    }
}

/** fetch com prazo: um agente travado não deixa o botão esperando para sempre. */
async function buscarComPrazo(url, opcoes, prazoMs, fetchImpl) {
    const controle = typeof AbortController === 'function' ? new AbortController() : null;
    const prazo = controle ? setTimeout(() => controle.abort(), prazoMs) : null;
    try {
        return await fetchImpl(url, { ...opcoes, signal: controle?.signal });
    } finally {
        clearTimeout(prazo);
    }
}

/** O agente está aberto neste computador? Responde em até 4 segundos. */
export async function agenteNoAr(agente, fetchImpl = fetch) {
    try {
        const resposta = await buscarComPrazo(`${agente}/status`, {}, 4000, fetchImpl);

        return resposta.ok;
    } catch {
        return false;
    }
}

/** Impressão do navegador num iframe escondido (não esbarra no bloqueio de pop-up). */
function impressaoDoNavegador(url, doc) {
    const quadro = doc.createElement('iframe');
    quadro.setAttribute('aria-hidden', 'true');
    quadro.tabIndex = -1;
    quadro.className = 'fixed -left-[9999px] top-0 h-[1px] w-[900px] border-0';
    quadro.src = url; // a página de impressão abre a janela de impressão sozinha
    doc.body.append(quadro);
    setTimeout(() => quadro.remove(), 120000);
}

export async function enviarAoAgente(agente, dados, fetchImpl = fetch) {
    let resposta;
    try {
        resposta = await buscarComPrazo(`${agente}/imprimir`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados),
        }, ESPERA_DO_AGENTE, fetchImpl);
    } catch (erro) {
        throw new Error(erro?.name === 'AbortError' ? 'o agente não respondeu em 1 minuto' : 'a conexão com o agente caiu no meio da impressão');
    }
    const corpo = await resposta.json().catch(() => ({}));
    if (!resposta.ok || corpo.ok !== true) {
        throw new Error(corpo.erro || `o agente respondeu ${resposta.status}`);
    }

    return corpo;
}

export default function iniciar(raiz, { doc = document } = {}) {
    const agente = raiz.dataset.agente;
    const orientacao = raiz.dataset.orientacaoEtiqueta || 'portrait';
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
        if (!link || !impressora || !TIPOS[tipo] || evento.ctrlKey || evento.metaKey || evento.shiftKey) {
            return; // sem impressora para esse tipo, ou Ctrl+clique: impressão do navegador
        }
        evento.preventDefault();
        if (link.getAttribute('aria-busy') === 'true') {
            return;
        }
        link.setAttribute('aria-busy', 'true');

        try {
            if (!(await agenteNoAr(agente))) {
                // Sem agente neste computador (ou o navegador sem permissão de
                // acessar a rede local): impressão do navegador, como antes.
                mostrarToast({ mensagem: 'O agente de impressão não respondeu neste computador. Abrindo a impressão do navegador.', variante: 'warning' }, doc);
                impressaoDoNavegador(link.href, doc);

                return;
            }
            const paginas = await montarPaginas(link.href, tipo, doc);
            await enviarAoAgente(agente, { impressora, tipo, orientacao: tipo === 'etiqueta' ? orientacao : 'auto', paginas });
            mostrarToast({ mensagem: `Enviado para a impressora ${impressora}.`, variante: 'success' }, doc);
        } catch (erro) {
            console.warn('[impressao] impressão direta falhou', erro);
            const motivo = String(erro?.message || 'erro desconhecido').replace(/\.\s*$/, '');
            mostrarToast({ mensagem: `Não foi possível imprimir em ${impressora}: ${motivo}. Detalhes no agente.log do computador.`, variante: 'danger', duracao: 15000 }, doc);
        } finally {
            link.removeAttribute('aria-busy');
        }
    });
}
