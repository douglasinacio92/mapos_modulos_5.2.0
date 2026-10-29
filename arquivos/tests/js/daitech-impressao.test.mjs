// Módulos Daitech: impressão direta (assets/js/modules/daitech/impressao.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { limparHtml, cssDaPagina, enviarAoAgente } from '../../assets/js/modules/daitech/impressao.js';

test('tira os scripts (o window.print) e põe o <base>', () => {
    const html = '<html><head><title>x</title></head><body><p>OS</p><script src="imprimir.js"></script><SCRIPT>alert(1)</SCRIPT></body></html>';
    const limpo = limparHtml(html, 'https://sistema.exemplo/index.php/os/');

    assert.equal(/<script/i.test(limpo), false);
    assert.match(limpo, /<head><base href="https:\/\/sistema\.exemplo\/index\.php\/os\/">/);
});

test('cada via do cupom vira uma página de 80mm com a altura dela', () => {
    assert.match(cssDaPagina('cupom', 183.2), /@page \{ size: 80mm 190mm; margin: 0; \}/);
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
