// Módulos Daitech: impressão direta (assets/js/modules/daitech/impressao.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { limparHtml, cssDaPagina, enviarAoAgente, ajusteDoTipo, absolutizarCss } from '../../assets/js/modules/daitech/impressao.js';

test('tira os scripts (o window.print) e põe o <base>', () => {
    const html = '<html><head><title>x</title></head><body><p>OS</p><script src="imprimir.js"></script><SCRIPT>alert(1)</SCRIPT></body></html>';
    const limpo = limparHtml(html, 'https://sistema.exemplo/index.php/os/');

    assert.equal(/<script/i.test(limpo), false);
    assert.match(limpo, /<head><base href="https:\/\/sistema\.exemplo\/index\.php\/os\/">/);
});

test('as vias do cupom vão em páginas de 80mm, com folga na altura', () => {
    const css = cssDaPagina('cupom', 100);
    assert.match(css, /@page \{ size: 80mm 114mm; margin: 0; \}/);
    assert.match(css, /\.cupom:not\(:last-of-type\) \{ break-after: page !important; \}/);
    assert.match(cssDaPagina('a4'), /size: A4/);
    assert.equal(cssDaPagina('etiqueta'), '', 'a etiqueta já traz o @page dela');
});

test('erro do agente vira exceção para o sistema abrir a impressão do navegador', async () => {
    const falso = async () => ({ ok: false, status: 500, json: async () => ({ ok: false, erro: 'Impressora offline' }) });
    await assert.rejects(enviarAoAgente('http://127.0.0.1:9123', {}, falso), /Impressora offline/);

    const certo = async (url, opcoes) => {
        assert.equal(url, 'http://127.0.0.1:9123/imprimir');
        assert.equal(JSON.parse(opcoes.body).impressora, 'ELGIN i9');

        return { ok: true, status: 200, json: async () => ({ ok: true, documentos: 2 }) };
    };
    assert.equal((await enviarAoAgente('http://127.0.0.1:9123', { impressora: 'ELGIN i9' }, certo)).documentos, 2);
});

test('a etiqueta vai com a orientação configurada; o cupom e o A4 não', () => {
    assert.equal(ajusteDoTipo('etiqueta', 'portrait'), 'noscale,portrait');
    assert.equal(ajusteDoTipo('etiqueta', 'auto'), 'noscale');
    assert.equal(ajusteDoTipo('cupom', 'portrait'), 'noscale');
    assert.equal(ajusteDoTipo('a4'), 'fit');
});

test('o CSS colado no HTML mantém os endereços das fontes e imagens', () => {
    const css = absolutizarCss('a{src:url(../fonts/x.woff2)} b{src:url("data:x")} c{src:url(https://a/b)}', 'https://s.com/mapos/assets/dist/imprimir.css?v=1');
    assert.match(css, /url\(https:\/\/s\.com\/mapos\/assets\/fonts\/x\.woff2\)/);
    assert.match(css, /url\("data:x"\)/);
    assert.match(css, /url\(https:\/\/a\/b\)/);
});

test('sem o agente no ar, o sistema sabe que deve usar a impressão do navegador', async () => {
    const { agenteNoAr } = await import('../../assets/js/modules/daitech/impressao.js');
    assert.equal(await agenteNoAr('http://127.0.0.1:9123', async () => ({ ok: true })), true);
    assert.equal(await agenteNoAr('http://127.0.0.1:9123', async () => { throw new TypeError('Failed to fetch'); }), false);
    assert.equal(await agenteNoAr('http://127.0.0.1:9123', async () => ({ ok: false })), false);
});
