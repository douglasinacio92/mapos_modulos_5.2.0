// Módulos Daitech: impressão direta (assets/js/modules/daitech/impressao.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { limparHtml, absolutizarCss, cssParaTela, fatiasDaFolha, enviarAoAgente, agenteNoAr, TIPOS, pretoEBranco } from '../../assets/js/modules/daitech/impressao.js';

test('tira os scripts (o window.print) e põe o <base>', () => {
    const html = '<html><head><title>x</title></head><body><p>OS</p><script src="imprimir.js"></script><SCRIPT>alert(1)</SCRIPT></body></html>';
    const limpo = limparHtml(html, 'https://sistema.exemplo/index.php/os/');

    assert.equal(/<script/i.test(limpo), false);
    assert.match(limpo, /<head><base href="https:\/\/sistema\.exemplo\/index\.php\/os\/">/);
});

test('as regras de impressão valem na imagem tirada na tela', () => {
    assert.equal(cssParaTela('@media print { .a { margin: 0 } } @media screen { .b {} }'), '@media all { .a { margin: 0 } } @media screen { .b {} }');
});

test('o CSS colado no HTML mantém os endereços das fontes e imagens', () => {
    const css = absolutizarCss('a{src:url(../fonts/x.woff2)} b{src:url("data:x")} c{src:url(https://a/b)}', 'https://s.com/mapos/assets/dist/imprimir.css?v=1');
    assert.match(css, /url\(https:\/\/s\.com\/mapos\/assets\/fonts\/x\.woff2\)/);
    assert.match(css, /url\("data:x"\)/);
    assert.match(css, /url\(https:\/\/a\/b\)/);
});

test('cupom e etiqueta na resolução das térmicas (203 dpi)', () => {
    assert.equal(TIPOS.cupom.pixels / TIPOS.cupom.larguraMm, 8);
    assert.equal(TIPOS.etiqueta.pixels / TIPOS.etiqueta.larguraMm, 8);
});

test('uma OS em A4 alta vira várias folhas na proporção da área útil', () => {
    assert.deepEqual(fatiasDaFolha(1000, 1654), [[0, 1000]]);
    const fatias = fatiasDaFolha(5000, 1900);
    assert.equal(fatias[0][1], 2770);
    assert.deepEqual(fatias[1], [2770, 2230]);
});

test('erro do agente vira exceção com o motivo', async () => {
    const falso = async () => ({ ok: false, status: 500, json: async () => ({ ok: false, erro: 'Impressora offline' }) });
    await assert.rejects(enviarAoAgente('http://127.0.0.1:9123', {}, falso), /Impressora offline/);

    const certo = async (url, opcoes) => {
        assert.equal(url, 'http://127.0.0.1:9123/imprimir');
        assert.equal(JSON.parse(opcoes.body).impressora, 'ELGIN i9');

        return { ok: true, status: 200, json: async () => ({ ok: true, paginas: 2 }) };
    };
    assert.equal((await enviarAoAgente('http://127.0.0.1:9123', { impressora: 'ELGIN i9' }, certo)).paginas, 2);
});

test('sem o agente no ar, o sistema sabe que deve usar a impressão do navegador', async () => {
    assert.equal(await agenteNoAr('http://127.0.0.1:9123', async () => ({ ok: true })), true);
    assert.equal(await agenteNoAr('http://127.0.0.1:9123', async () => { throw new TypeError('Failed to fetch'); }), false);
    assert.equal(await agenteNoAr('http://127.0.0.1:9123', async () => ({ ok: false })), false);
});

test('na térmica, cinza vira preto ou branco puro (sem pontilhado)', () => {
    const pixels = new Uint8ClampedArray([0, 0, 0, 255, 120, 120, 120, 255, 200, 200, 200, 255, 255, 255, 255, 0]);
    assert.deepEqual(Array.from(pretoEBranco(pixels)), [0, 0, 0, 255, 0, 0, 0, 255, 255, 255, 255, 255, 255, 255, 255, 255]);
});
