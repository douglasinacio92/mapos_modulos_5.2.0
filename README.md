# Módulos Daitech para o Map-OS v5.2.0

Este pacote leva para o Map-OS **v5.2.0** (a versão mais nova, de 27/10/2026) tudo o que a Daitech tinha a mais na 4.53.2. As telas foram reescritas no layout novo da v5: Tailwind, componentes da v5, modo claro e escuro, celular e JavaScript em módulos (sem jQuery e sem script inline, que a v5 bloqueia).

## O que vem no pacote

| Módulo | Onde fica | O que faz |
| --- | --- | --- |
| **PDV (frente de caixa)** | Botão "PDV" ao lado de "Nova venda", no topo da tela de Vendas | Tela compacta, sem barra de rolagem no computador: só a lista de itens rola. Leitor de código de barras, produtos, serviços e itens avulsos, preço editável, desconto em R$ ou %, troco, venda a prazo (caderninho) e cupom 80mm. Grava a venda já faturada e o lançamento no financeiro. |
| **Orçamentos** | Menu Operação › Orçamentos | Lista só com a busca, igual à de Vendas. Orçamento rápido no mesmo carrinho compacto do PDV, impressão A4 com validade, "Finalizar no PDV" ou "Converter em venda" (segue na tela de venda da v5). |
| **Kanban** | Menu Operação › Kanban | Quadros de OS, agenda externa e tarefas internas. Os cartões são arrastados entre colunas ou movidos pelo "Mover para", com o prazo em cores. As tarefas agora têm cadastro (criar, editar e excluir). |
| **Agenda externa** | Menu Operação › Agenda externa | Calendário do mês ao lado da lista de visitas do dia (ponto azul nos dias com visita), WhatsApp com a mensagem pronta, envio automático pela Evolution API, Maps e Google Agenda. As visitas também aparecem no calendário do painel. |
| **Películas** | Menu Operação › Películas | Busca de compatibilidade de películas, com cadastro, edição e exclusão. |
| **Mural de notas** | Chat flutuante em todas as telas (a tela cheia continua em `index.php/notas`, fora do menu) | Recados da equipe, contador de não lidas, aviso e som de nota nova. |
| **Dados do aparelho na OS** | Formulário e tela da OS | Um bloco só, "Equipamento e serviço", com todos os campos do mesmo tamanho: equipamento, marca, modelo, série/IMEI, senha (texto ou desenho do padrão) e assinatura digital do cliente. Tudo sai na OS A4, no "Cupom 2 vias" (com termos e assinatura) e nas "Etiquetas (3x)" de 35x21mm. A previsão de entrega já vem com +3 dias. |
| **Acompanhar OS** | `index.php/acompanhar/os/ID?doc=CPF` | É o antigo link `mine/os_direta/ID?doc=`, que continua funcionando. Mostra o resumo da OS para quem informa o número e o CPF/CNPJ. |
| **API do agente (n8n)** | `index.php/agente/...` | As mesmas rotas da 4.x: `buscar_os`, `buscar_cliente`, `buscar_ordens_cliente`, `buscar_produto` e `buscar_servico`. |
| **Módulos Daitech** | Configurações › Módulos Daitech | Termos da OS, mensagem da visita, validade do orçamento e o cliente "balcão" do PDV. |
| **Etiquetas de produtos** | Menu Operação › Etiquetas | Bipe ou busque os produtos, escolha quantas etiquetas de cada um e se o preço sai. Imprime etiquetas de 35x21mm, 3 por fileira, com nome, preço e código de barras (EAN-13 ou Code 128). |
| **Impressões da OS** | Cabeçalho da tela da OS | "Imprimir A4", "Cupom 2 vias" e "Etiquetas (3x)" lado a lado (o "Cupom 80mm" simples saiu). O cupom é compacto e sai sempre em duas vias. A do cliente traz o QR "Acompanhe sua OS" ao lado dos termos. A da loja traz a senha do aparelho, a assinatura do cliente e o QR do WhatsApp dele. |
| **Acompanhar pelo QR** | `index.php/acompanhar/qr/ID/CÓDIGO` | O cliente aponta a câmera para o QR do cupom e confirma o celular do cadastro para ver a OS, sem senha. O código do link é único de cada OS, então trocar o número no endereço não abre a OS de outra pessoa. Tentativas erradas contam no limite de tentativas, como no login. |
| **Impressão direta** | Configurações › Módulos Daitech › Impressão direta | Imprime o cupom, o A4 e as etiquetas direto na impressora certa, sem a janela de impressão. Precisa do **agente de impressão** instalado no computador do balcão (pasta `agente-impressao`, veja o `LEIAME.txt` dela). O sistema transforma cada página em imagem e o agente imprime com a impressão do próprio Windows, sem programas extras. Sem o agente, abre a impressão do navegador, como antes. |
| **Aplicativo (PWA)** | Login e todas as telas | Ao abrir o site no Chrome, Edge ou Android aparece o aviso "Instale o Daitech-OS". O sistema vira um ícone na tela inicial e abre em janela própria. No iPhone, o aviso ensina o caminho do Safari (Compartilhar › Adicionar à Tela de Início). Funciona só com **HTTPS**. Sem internet, abre uma página "Sem conexão". |
| **Nome Daitech-OS** | Todas as telas | O rodapé mostra "Douglas Inacio - Daitech-OS" e os textos que diziam "Map-OS" passam a dizer "Daitech-OS". No `application/.env`, troque `APP_NAME="Map-OS"` por `APP_NAME="Daitech-OS"`: é ele que aparece no login. Os avisos de autoria da licença do Map-OS (Apache 2.0) continuam nos arquivos, como a licença exige. |
| **Mais cores do sistema** | Menu do usuário › Cor do tema | Quatro temas novos: Rosa, Ciano, Âmbar e Índigo, com contraste conferido (WCAG) no claro e no escuro. |

## Atualizando um servidor que já tem o pacote anterior

1. Copie **toda** a pasta `arquivos/` por cima de novo, **incluindo `assets/`** (o `assets/dist` com o CSS e o `assets/vendor/lucide` com os ícones). Os ícones faltando e as telas "empilhadas" que você viu eram CSS e ícones antigos.
2. Rode `php index.php tools migrate` (nada novo no banco nesta versão, mas não faz mal).
3. Abra o sistema e aperte **Ctrl + F5** uma vez. Daqui pra frente o CSS, o JS e os ícones levam `?v=` com a data do arquivo, então o navegador e o cache do aaPanel pegam a versão nova sozinhos.

## Como instalar (vindo da 4.53.2)

> A v5 exige **PHP 8.5** e troca o layout inteiro. Faça tudo primeiro numa **cópia** do servidor e do banco.

1. **Backup:** faça o dump do banco e guarde também `application/.env`, `assets/uploads`, `assets/userImage`, `assets/anexos` e `assets/arquivos`.
2. **Instale o Map-OS v5.2.0 numa pasta nova**, pelo zip da release ou por `git clone --branch v5.2.0` + `composer install --no-dev`. Não use o botão "Atualizar Mapos" da 4.x. O guia oficial é o `docs/migracao-4x-para-5.md`.
3. **Copie a pasta `arquivos/` deste pacote por cima da pasta nova**, mantendo a estrutura. Ela traz:
   - os arquivos novos dos módulos;
   - os poucos arquivos do Map-OS que recebem um "gancho" (lista abaixo);
   - o CSS e os ícones já compilados (`assets/dist`, `assets/vendor/lucide`).
4. **Ajuste o `application/.env`:** siga o passo 3.3 do guia oficial e acrescente as variáveis do arquivo `env.exemplo` deste pacote.
5. **Traga os seus arquivos:** copie `assets/uploads`, `assets/userImage`, `assets/anexos` e `assets/arquivos` para a pasta nova. Defina o `STORAGE_PATH` como manda o guia oficial.
6. **Atualize o banco:** em Configurações › Sistema › Atualizar banco de dados, ou pelo terminal com `php index.php tools migrate`. Isso roda as migrations da v5 e a dos módulos (`20261028000000_daitech_modulos`), que:
   - mantém as tabelas que você já tem (agendamentos, películas, mural, tarefas) e as colunas da OS (marca, modelo...), sem apagar nada;
   - cria `vendas_itens_extras`, onde ficam os serviços e itens avulsos do PDV e dos orçamentos;
   - cria as configurações dos módulos com os seus termos e o cliente BALCAO como cliente de balcão;
   - dá as permissões novas (Agenda externa e Películas) aos grupos que já viam OS e produtos;
   - troca o status "Em Atendimento" da agenda por "Em Andamento", porque na 4.x eram dois nomes para o mesmo passo.
7. **Confira as permissões** em Configurações › Permissões: as linhas novas são "Agenda externa (visitas)" e "Películas".
8. Depois de testar, **apague a pasta `install/`** e confira se o servidor bloqueia `application/.env` e os `.sql` (passo 3.5 do guia oficial).

## Arquivos do Map-OS alterados (os "ganchos")

São mudanças pequenas, todas marcadas com o comentário `Módulos Daitech`. O diff exato está em `patches/nucleo-mapos-5.2.0.patch`.

| Arquivo | Mudança |
| --- | --- |
| `application/config/autoload.php` | Carrega o `daitech_helper`. |
| `application/config/permissions_map.php` | Inclui o `daitech_permissions_map.php`. |
| `application/config/routes.php` | Rota antiga `mine/os_direta/ID`. |
| `application/helpers/layout_helper.php` | Itens do menu, nomes no breadcrumb e versão `?v=` no CSS e JS (para o navegador não usar arquivo velho). |
| `application/helpers/icone_helper.php`, `assets/js/lib/icone.js` | Versão `?v=` no `sprite.svg` dos ícones. |
| `application/helpers/tema_helper.php`, `assets/js/tema.js`, `assets/js/lib/tema.js`, `assets/src/tokens.css`, `scripts/contraste-temas.mjs` | Temas de cor novos. |
| `application/views/tema/topo.php`, `tema/conteudo.php`, `entrada/inicio.php` | Versão `?v=` e as amostras de cor em 5 colunas. |
| `application/controllers/Vendas.php`, `application/views/tema/conteudo.php` | Botão "PDV" ao lado de "Nova venda" (o topo aceita uma ação secundária, `topbar_acao_extra`). |
| `application/helpers/usuarios_helper.php` | Permissões Agenda externa e Películas na tela de grupos. |
| `application/controllers/Os.php` | Grava os dados do aparelho e define a previsão de +3 dias. |
| `application/controllers/Mapos.php` | Visitas no calendário do painel. |
| `application/models/Vendas_model.php` | Serviços e avulsos entram no total da venda. |
| `application/views/os/formulario.php`, `visualizar.php`, `imprimirOs.php` | Seções e impressões do aparelho. |
| `application/views/tema/topo.php`, `entrada/inicio.php`, `tema/rodape.php`, `entrada/fim.php` | Manifesto do aplicativo, ícones e o aviso de instalação. Os arquivos novos `manifest.webmanifest` e `sw.js` ficam na raiz, ao lado do `index.php`. |
| `application/views/os/visualizar.php` | Botões "Imprimir A4", "Cupom 2 vias" e "Etiquetas (3x)" no cabeçalho. |
| `application/helpers/seguranca_cabecalhos_helper.php` | Libera o agente de impressão (`http://127.0.0.1:9123`) na política de segurança (CSP). |
| `application/views/vendas/visualizar.php`, `partes/totais.php` | Serviços e avulsos na tela da venda. |
| `application/views/tema/rodape.php` | Chat flutuante do mural. |
| `assets/js/modules/painel/painel.js` | Clique na visita do calendário abre a agenda. |
| `tests/...` | Testes do Map-OS ajustados aos temas novos e à versão no sprite. |
| `assets/src/app.css`, `imprimir.css`, `icones.json` | Fontes do CSS e ícones novos. Os compilados (`assets/dist`, `assets/vendor/lucide`) já vão prontos. |

**Ao atualizar o Map-OS no futuro** (5.2.1, 5.3...), os arquivos acima são sobrescritos. Depois de atualizar:

1. Reaplique o patch com `git apply patches/nucleo-mapos-5.2.0.patch` (ou faça as mesmas mudanças à mão, se o arquivo mudou).
2. Rode `npm ci && npm run build` para recompilar o CSS e os ícones.

Os arquivos novos dos módulos não são tocados pela atualização.

## Outros documentos

- `docs/arquivos-com-ramon-e-map-os.md`: lista dos arquivos do Map-OS original que citam "Ramon" ou "Map-OS".

## Diferenças em relação à 4.x

- **Estoque:** o PDV e os orçamentos seguem a regra da v5. O estoque só baixa com o "Controle de estoque" ligado nas configurações, e baixa quando o item entra na venda ou no orçamento. No seu banco esse controle está **desligado**. Na 4.x o PDV baixava sempre: se quiser a baixa, ligue o controle.
- **Totais:** são sempre recalculados no servidor, com o preço do cadastro ou o preço digitado no carrinho. O navegador não manda mais o total.
- **Kanban:** não fatura nem cancela OS. Esses dois passos mexem no financeiro e no estoque, então ficam na tela da OS. O Kanban também registra cada mudança no histórico da OS.
- **Acompanhar OS (`os_direta`):** agora é uma tela só de leitura e não faz mais login automático na área do cliente. Ela tem limite de tentativas, como o login.
- **API do agente:** o token saiu do código e foi para o `.env` (`AGENTE_API_TOKEN`). Na 4.x havia uma falha: com o token errado a resposta 401 era montada, mas os dados saíam mesmo assim. Agora a requisição é bloqueada de verdade.
- **Serviços e avulsos no PDV e no orçamento:** na 4.x eles ficavam só no texto das observações. Agora são itens de verdade, que entram no total, na tela da venda e no cupom. Também continuam nas observações, para aparecer nas impressões padrão da v5.
- **Assinatura e padrão:** feitos sem bibliotecas de CDN, que a v5 bloqueia.
- **Não portados, porque a v5 já resolve:** "Celular 2" (a v5 já tem os campos Telefone e Celular) e o e-mail e a senha automáticos do cliente (na v5, cliente sem senha entra na área do cliente com o CPF/CNPJ).

## ⚠️ Segurança: troque estas chaves

A chave da Evolution API e o token do agente estavam escritos no código da 4.x e no arquivo que você me enviou. **Gere chaves novas** e coloque-as só no `.env`. Use a mesma chave nova no n8n.

## Testes feitos

Tudo foi testado num Map-OS v5.2.0 limpo, com o **seu banco** importado e migrado:

- **Navegador:** PDV (código de barras, serviço, avulso, desconto, troco, cupom), orçamento (criar, imprimir, levar ao PDV), agenda (criar, editar, excluir), Kanban (mover OS, visita e tarefa), películas, mural/chat, dados do aparelho com padrão e assinatura, cupom 2 vias, etiquetas, OS A4, acompanhamento público e API do agente.
- **Testes automáticos do Map-OS:** passam todos. São 1.665 testes do PHPUnit, incluindo 17 dos módulos (`tests/Unit/DaitechModulosTest.php`), os 196 testes de JavaScript, o `check-escape` e o `check-inline-script`.
- **Limitação:** o ambiente de teste tinha PHP 8.3 (a v5 pede 8.5). Confira no servidor com PHP 8.5 antes de usar em produção.
