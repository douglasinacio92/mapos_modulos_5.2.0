// Módulos Daitech: aviso de instalação do aplicativo (assets/js/modules/daitech/instalar.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { podeMostrar, ehIos, jaInstalado } from '../../assets/js/modules/daitech/instalar.js';

function janela({ standalone = false, adiado = null } = {}) {
    return {
        matchMedia: () => ({ matches: standalone }),
        navigator: {},
        localStorage: { getItem: () => adiado },
    };
}

test('mostra o aviso quando o app não está instalado e não foi adiado', () => {
    assert.equal(podeMostrar(janela(), 1_000_000_000_000), true);
});

test('não mostra no aplicativo já instalado', () => {
    assert.equal(jaInstalado(janela({ standalone: true })), true);
    assert.equal(podeMostrar(janela({ standalone: true }), 1_000_000_000_000), false);
});

test('"Agora não" esconde por 30 dias', () => {
    const agora = 1_000_000_000_000;
    const dia = 24 * 60 * 60 * 1000;
    assert.equal(podeMostrar(janela({ adiado: String(agora - 10 * dia) }), agora), false);
    assert.equal(podeMostrar(janela({ adiado: String(agora - 31 * dia) }), agora), true);
});

test('sem localStorage (aba anônima) o aviso continua funcionando', () => {
    const j = janela();
    j.localStorage = { getItem() { throw new Error('bloqueado'); } };
    assert.equal(podeMostrar(j, 1_000_000_000_000), true);
});

test('reconhece iPhone e iPad', () => {
    assert.equal(ehIos({ userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)' }), true);
    assert.equal(ehIos({ userAgent: 'Mozilla/5.0 (Macintosh)', platform: 'MacIntel', maxTouchPoints: 5 }), true);
    assert.equal(ehIos({ userAgent: 'Mozilla/5.0 (Linux; Android 14)', platform: 'Linux' }), false);
});
