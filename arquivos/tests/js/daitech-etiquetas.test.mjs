// Módulos Daitech: etiquetas de produtos (assets/js/modules/etiquetas/tela.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { urlDaImpressao, quantidadeValida, resumo } from '../../assets/js/modules/etiquetas/tela.js';

test('o link da impressão leva os produtos, as quantidades e o preço', () => {
    const url = urlDaImpressao('https://s/index.php/etiquetas/imprimir', [{ id: 1, quantidade: 2 }, { id: 107, quantidade: 5 }], true);
    assert.equal(url, 'https://s/index.php/etiquetas/imprimir?i=1%3A2%2C107%3A5&preco=1');
    assert.match(urlDaImpressao('x', [{ id: 1, quantidade: 1 }], false), /preco=0$/);
});

test('quantidade é um inteiro de 1 a 300', () => {
    assert.equal(quantidadeValida('3'), 3);
    assert.equal(quantidadeValida('999'), 300);
    assert.equal(quantidadeValida('0'), null);
    assert.equal(quantidadeValida('1,5'), null);
    assert.equal(quantidadeValida(''), null);
});

test('resumo conta etiquetas e fileiras de 3', () => {
    assert.equal(resumo([]), 'Nenhuma etiqueta');
    assert.equal(resumo([{ quantidade: 1 }]), '1 etiqueta em 1 fileira');
    assert.equal(resumo([{ quantidade: 2 }, { quantidade: 5 }]), '7 etiquetas em 3 fileiras');
});
