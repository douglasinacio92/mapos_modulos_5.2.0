// Módulos Daitech: um modal de formulário que serve para cadastrar e editar.
//
// O gatilho (botão da linha ou da topbar) abre o modal pelo
// componentes/modal.js (data-modal-abrir + data-valor-*) e, com
// data-form-acao="<url>", troca o action do <form> do modal; com
// data-form-titulo, o título. Assim o id editado vai na URL, como no Map-OS,
// e não num campo do POST.

export function prepararFormulario(gatilho, doc = document) {
    const dialogo = doc.getElementById(gatilho.dataset.modalAbrir ?? '');
    if (!dialogo) {
        return;
    }

    const form = dialogo.querySelector('form') ?? doc.getElementById(dialogo.dataset.form ?? '');
    if (form && gatilho.dataset.formAcao) {
        form.action = gatilho.dataset.formAcao;
    }
    if (form && gatilho.dataset.formLimpar !== undefined) {
        form.reset();
    }

    const titulo = dialogo.querySelector('h2');
    if (titulo && gatilho.dataset.formTitulo) {
        titulo.textContent = gatilho.dataset.formTitulo;
    }
}

export default function iniciar(_raiz, doc = document) {
    if (doc.documentElement.dataset.daitechModalForm === 'true') {
        return;
    }
    doc.documentElement.dataset.daitechModalForm = 'true';

    // Fase de captura: roda antes do modal.js preencher os campos, para o
    // reset() não apagar o que ele preenche.
    doc.addEventListener('click', (evento) => {
        const gatilho = evento.target.closest?.('[data-modal-abrir][data-form-acao]');
        if (gatilho) {
            prepararFormulario(gatilho, doc);
        }
    }, true);
}
