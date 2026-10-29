// Módulos Daitech: "Testar o agente" na tela de configurações. Pergunta ao
// agente de impressão deste computador se ele está aberto e preenche a lista
// de sugestões dos campos com as impressoras instaladas no Windows.

export async function consultarAgente(agente, fetchImpl = fetch) {
    const controle = typeof AbortController === 'function' ? new AbortController() : null;
    const prazo = controle ? setTimeout(() => controle.abort(), 5000) : null;
    let resposta;
    try {
        resposta = await fetchImpl(`${agente}/status`, { signal: controle?.signal });
    } finally {
        clearTimeout(prazo);
    }
    if (!resposta.ok) {
        throw new Error(`agente ${resposta.status}`);
    }

    return resposta.json();
}

export default function iniciar(raiz, { doc = document } = {}) {
    const botao = raiz.querySelector('[data-impressao-testar]');
    const status = raiz.querySelector('[data-impressao-status]');
    const lista = raiz.querySelector('[data-impressao-lista]');

    botao?.addEventListener('click', async () => {
        status.textContent = 'Procurando o agente neste computador…';
        status.className = 'mt-2 text-caption text-muted';
        try {
            const dados = await consultarAgente(raiz.dataset.agente);
            const nomes = Array.isArray(dados.impressoras) ? dados.impressoras : (dados.impressoras ? [String(dados.impressoras)] : []);
            lista.replaceChildren(...nomes.map((nome) => {
                const opcao = doc.createElement('option');
                opcao.value = nome;

                return opcao;
            }));
            status.textContent = nomes.length > 0
                ? `Agente funcionando. Impressoras deste computador: ${nomes.join(', ')}. Elas aparecem como sugestão nos campos.`
                : 'Agente funcionando, mas nenhuma impressora foi encontrada neste computador.';
            status.className = 'mt-2 text-caption text-success-ink';
        } catch {
            status.textContent = 'O agente não respondeu em 5 segundos. Confira: 1) o agente está instalado e aberto neste computador (instalar.bat); 2) o navegador tem permissão de acessar a rede local (cadeado ao lado do endereço › Permissões › Acesso à rede local › Permitir); 3) o "site" do config.json é o endereço deste sistema.';
            status.className = 'mt-2 text-caption text-danger-ink';
        }
    });
}
