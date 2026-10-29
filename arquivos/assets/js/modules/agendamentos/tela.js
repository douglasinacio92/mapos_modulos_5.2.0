// Agenda externa (Daitech), views/agendamentos/agendamentos.php.
//
// - Liga o autocomplete do cliente no modal "Novo agendamento"
//   (lib/autocomplete.js). Ao escolher, o endereço do cadastro entra no campo
//   do endereço se ele ainda estiver vazio.
// - Botões [data-enviar-confirmacao] mandam a confirmação pela Evolution API
//   (agendamentos/enviarWhatsApp) e mostram o resultado num toast.

import { iniciarAutocompletes } from '../../lib/autocomplete.js';
import { post as postPadrao } from '../../lib/http.js';
import { mostrarToast } from '../../lib/toast.js';

export default function iniciar(raiz, { post = postPadrao, doc = document } = {}) {
    iniciarAutocompletes(doc.getElementById('agendamento-novo') ?? raiz);

    const wrapper = doc.querySelector('[data-agenda-cliente]');
    const endereco = doc.querySelector('[data-agenda-endereco]');
    wrapper?.addEventListener('autocomplete-escolha', (evento) => {
        const sugerido = evento.detail?.dados?.endereco ?? '';
        if (endereco && endereco.value.trim() === '' && sugerido) {
            endereco.value = sugerido;
        }
    });

    raiz.addEventListener('click', async (evento) => {
        const botao = evento.target.closest('[data-enviar-confirmacao]');
        if (!botao || botao.disabled) {
            return;
        }

        botao.disabled = true;
        try {
            const resposta = await post(raiz.dataset.enviarUrl, { id: botao.dataset.enviarConfirmacao });
            mostrarToast({ mensagem: resposta.message || 'Confirmação enviada.', variante: 'success' }, doc);
        } catch (erro) {
            mostrarToast({ mensagem: erro?.message || 'Não foi possível enviar agora.', variante: 'danger', duracao: 8000 }, doc);
        } finally {
            botao.disabled = false;
        }
    });
}
