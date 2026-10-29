# Arquivos do Map-OS v5.2.0 original que citam "Ramon" ou "Map-OS"

Busca sem diferenciar maiúsculas e minúsculas, no código original da tag v5.2.0, sem os módulos Daitech. Total: **83 arquivos**.

Os que **aparecem para o usuário** estão no grupo 1 (telas) e em alguns do grupo 2 (o nome padrão do sistema e o rodapé). O restante é código interno, testes e documentação: trocar ali não muda nada na tela.

## 1. Telas (aparece para o usuário)

| Arquivo | Ocorrências | Exemplo |
| --- | ---: | --- |
| `application/views/cobrancas/cobrancas.php` | 1 | linha 206: `' será cancelada no gateway e removida do Map-OS. Essa ação não pode ser desfeita.',` |
| `application/views/cobrancas/visualizarCobranca.php` | 1 | linha 137: `'message' => 'A cobrança será cancelada no gateway e removida do Map-OS. Essa ação não pode ser desfeita.',` |
| `application/views/componentes/catalogo.php` | 2 | linha 42: `<title>Componentes — Map-OS</title>` |
| `application/views/components/pagination.php` | 1 | linha 7: `* models do Map-OS recebem). Com {offset}, per_page é obrigatório.` |
| `application/views/conecte/template.php` | 1 | linha 29: `$nomeSistema = (string) ($this->config->item('app_name') ?: 'Map-OS');` |
| `application/views/entrada/fim.php` | 1 | linha 13: `<a href="https://github.com/RamonSilva20/mapos" class="rounded-xs underline-offset-4 hover:text-on-dark hov…` |
| `application/views/entrada/inicio.php` | 1 | linha 23: `$nomeSistema = (string) ($this->config->item('app_name') ?: 'Map-OS');` |
| `application/views/errors/html/_pagina.php` | 1 | linha 28: `<title><?= htmlspecialchars($titulo . ' — Map-OS', ENT_QUOTES, 'UTF-8') ?></title>` |
| `application/views/mapos/atualizacao.php` | 3 | linha 3: `* Atualização do Map-OS (#2871): versão atual, última release do GitHub, o` |
| `application/views/mapos/banco-desatualizado.php` | 1 | linha 24: `<title><?= e($titulo) ?> — Map-OS</title>` |
| `application/views/mapos/configurar.php` | 3 | linha 12: `*   e leva à tela mapos/atualizacao para atualizar o Map-OS (#2871).` |
| `application/views/mapos/painel.php` | 1 | linha 313: `<?= component('empty-state', ['title' => 'Bem-vindo ao Map-OS', 'message' => 'Seu usuário ainda não tem per…` |
| `application/views/relatorios/imprimir/partes/inicio.php` | 1 | linha 16: `<title>Map-OS</title>` |
| `application/views/tema/rodape.php` | 2 | linha 16: `<a href="https://github.com/RamonSilva20/mapos" target="_blank" rel="noopener" class="rounded-sm hover:text…` |
| `application/views/tema/topo.php` | 1 | linha 20: `<title><?= e(layoutTitulo($layout['breadcrumb'], (string) ($configuration['app_name'] ?: 'Map-OS'))) ?></ti…` |
| `install/view/configuration.php` | 1 | linha 52: `<input type="url" id="base_url" name="base_url" placeholder="Endereço onde o Map-OS será acessado" required…` |
| `install/view/index.php` | 1 | linha 20: `<img class="h-16 w-auto" src="<?= htmlspecialchars($dashboard_url, ENT_QUOTES) ?>assets/img/logo.png" alt="…` |

## 2. Código do sistema

| Arquivo | Ocorrências | Exemplo |
| --- | ---: | --- |
| `application/.env.example` | 3 | linha 1: `APP_NAME="Map-OS"` |
| `application/config/config.php` | 1 | linha 15: `$config['app_name'] = $_ENV['APP_NAME'] ?? 'Map-OS';` |
| `application/config/github_updater.php` | 1 | linha 10: `$config['github_user'] = 'RamonSilva20';` |
| `application/controllers/Mapos.php` | 6 | linha 774: `* Tela da atualização do Map-OS (#2871): consulta a última release do` |
| `application/controllers/api/v1/ApiController.php` | 1 | linha 137: `'message' => 'Dados do Map-OS',` |
| `application/core/MY_Controller.php` | 2 | linha 11: `'app_name' => 'Map-OS',` |
| `application/database/seeds/Configuracoes.php` | 1 | linha 15: `'valor' => 'Map-OS',` |
| `application/helpers/componente_helper.php` | 1 | linha 591: `* deslocamento (página - 1) × per_page, que é o que os models do Map-OS` |
| `application/helpers/configuracoes_helper.php` | 1 | linha 121: `'API_ENABLED' => ['destino' => 'env', 'tipo' => 'bool_env', 'rotulo' => 'API ligada', 'ajuda' => 'Usada pel…` |
| `application/helpers/layout_helper.php` | 2 | linha 210: `* e do último nome ("Ramon da Silva" → "RS"); com um nome só, as duas` |
| `application/helpers/seguranca_cabecalhos_helper.php` | 3 | linha 19: `* dois modos chegam ao endpoint do Map-OS e ajudam a achar o que ficou de fora.` |
| `application/helpers/usuarios_helper.php` | 1 | linha 61: `'cSistema' => ['Configurações do sistema', 'Preferências, atualização do banco e do Map-OS.'],` |
| `application/libraries/Github_updater.php` | 2 | linha 8: `* Atualizador do Map-OS por releases do GitHub (#2871).` |
| `application/libraries/MY_Email.php` | 1 | linha 17: `* Updated by @RamonSilva for Map-OS` |
| `application/libraries/Permission.php` | 2 | linha 12: `* @author      Ramon Silva` |
| `application/libraries/Totp.php` | 1 | linha 54: `$this->emissor = (string) ($opcoes['emissor'] ?? (config_item('app_name') ?: 'Map-OS'));` |
| `assets/js/lib/script.js` | 1 | linha 4: `// vem do próprio Map-OS, então a CSP (script-src 'self') aceita.` |
| `assets/src/app.css` | 1 | linha 5: `* commitado: quem só instala ou atualiza o Map-OS não precisa de Node.` |
| `assets/src/instalador.css` | 1 | linha 5: `* O instalador roda antes de o Map-OS estar configurado e é independente do` |
| `assets/src/stickers/README.md` | 1 | linha 3: `Adesivos do 'DESIGN.md' ("Sticker Illustration Layer"), feitos para o Map-OS` |
| `assets/src/stickers/caixa.svg` | 1 | linha 2: `<!-- Map-OS, adesivo "caixa de entrega". Apache-2.0, ver assets/src/stickers/README.md -->` |
| `assets/src/stickers/celular.svg` | 1 | linha 2: `<!-- Map-OS, adesivo "celular". Apache-2.0, ver assets/src/stickers/README.md -->` |
| `assets/src/stickers/chave.svg` | 1 | linha 2: `<!-- Map-OS, adesivo "chave". Apache-2.0, ver assets/src/stickers/README.md -->` |
| `assets/src/stickers/notebook.svg` | 1 | linha 2: `<!-- Map-OS, adesivo "notebook". Apache-2.0, ver assets/src/stickers/README.md -->` |
| `assets/src/stickers/tecnico.svg` | 1 | linha 2: `<!-- Map-OS, adesivo "técnico". Apache-2.0, ver assets/src/stickers/README.md -->` |
| `index.php` | 2 | linha 64: `$mensagem = 'O Map-OS não encontrou as dependências (pasta application/vendor). Instale a versão completa d…` |

## 3. Instalador e banco

| Arquivo | Ocorrências | Exemplo |
| --- | ---: | --- |
| `banco.sql` | 1 | linha 639: `(2, 'app_name', 'Map-OS'),` |
| `install.bat` | 15 | linha 1: `TITLE Instalador Map-OS Windows 10/11` |
| `install.sh` | 11 | linha 23: `# etapa5 - Instalação MAP-OS` |
| `install/assets/js/main.js` | 1 | linha 1: `// Instalador do Map-OS: troca de etapas e envio do formulário por fetch.` |
| `install/instalador.php` | 3 | linha 40: `exit(json_encode(['success' => false, 'message' => 'O Map-OS já está instalado.']));` |
| `updates/update_3.14.2_to_3.15.0.sql` | 1 | linha 4: `(2, 'app_name', 'Map-OS'),` |

## 4. Testes e ferramentas

| Arquivo | Ocorrências | Exemplo |
| --- | ---: | --- |
| `package.json` | 1 | linha 4: `"description": "Assets do Map-OS: Tailwind CSS e bibliotecas de JavaScript",` |
| `phpstan-stubs/ci3.php` | 2 | linha 159: `// O construtor é declarado porque many subclasses do Map-OS chamam` |
| `phpstan.neon` | 1 | linha 28: `# third_party e vendor não são código do Map-OS: entram na análise` |
| `phpunit.xml` | 1 | linha 16: `<testsuite name="Map-OS">` |
| `scripts/build-vendor.mjs` | 1 | linha 2: `// para assets/vendor/<lib>/, que é commitado. Assim quem instala o Map-OS` |
| `tests/MaposTestCase.php` | 1 | linha 6: `* Base dos testes do Map-OS.` |
| `tests/Unit/AcessibilidadeTest.php` | 8 | linha 13: `$this->assertSame('Adicionar — Clientes — Map-OS', layoutTitulo($itens, 'Map-OS'));` |
| `tests/Unit/AreaDoClienteTest.php` | 2 | linha 166: `return ['app_name' => 'Map-OS'][$nome] ?? null;` |
| `tests/Unit/CobrancasTest.php` | 1 | linha 22: `* da listagem, ações por POST que voltam para uma tela do Map-OS e as views.` |
| `tests/Unit/ContaEBuscaTest.php` | 1 | linha 72: `'usuario' => (object) ['nome' => 'Ramon da Silva', 'email' => 'r@x.com', 'telefone' => '', 'permissao' => '…` |
| `tests/Unit/DoisFatoresTest.php` | 2 | linha 22: `$this->totp = new Totp(['chave' => 'chave-da-instalacao-de-teste', 'emissor' => 'Map-OS']);` |
| `tests/Unit/HardeningServidorTest.php` | 2 | linha 101: `* pelo Map-OS não pode chegar nele, e um mPDF comum chega (controle).` |
| `tests/Unit/LayoutTest.php` | 2 | linha 354: `['Ramon da Silva', 'RS'],` |
| `tests/Unit/LoginViewTest.php` | 1 | linha 27: `$config += ['app_name' => 'Map-OS', 'app_version' => '5.0.0-alpha', 'app_subname' => 'Sistema de Controle d…` |
| `tests/Unit/PaginasDeErroTest.php` | 2 | linha 38: `$this->assertStringContainsString('<title>Página não encontrada — Map-OS</title>', $html);` |
| `tests/Unit/PainelTest.php` | 1 | linha 280: `$this->assertStringContainsString('Bem-vindo ao Map-OS', $html);` |

## 5. Documentação e outros

| Arquivo | Ocorrências | Exemplo |
| --- | ---: | --- |
| `.github/PULL_REQUEST_TEMPLATE.md` | 2 | linha 2: `Obrigado por contribuir com o Map-OS!` |
| `.github/workflows/release.yml` | 1 | linha 98: `# O zip vai para quem instala o Map-OS, não para quem develope.` |
| `AGENTS.md` | 4 | linha 1: `# Guidelines for AI Agents (Map-OS)` |
| `CHANGELOG.md` | 143 | linha 14: `- Temas de cor (Azul, Verde, Violeta e Grafite, além do laranja padrão), independentes do modo Claro, Escur…` |
| `CONTRIBUTING.md` | 11 | linha 1: `# Contribuindo com o Map-OS` |
| `Cronjobs no Windows para enviar os e-emails.md` | 4 | linha 1: `Esse tutorial ensinará a criar cronjobs para envio de e-mails do sistema Map-os utlizando um programa do pr…` |
| `DESIGN.md` | 4 | linha 1: `# Map-OS — Design System` |
| `Instalacao_xampp_windows.md` | 1 | linha 33: `3. AGORA BAIXE O MAPOS ATRAVÉS DO LINK: https://github.com/RamonSilva20/mapos/releases` |
| `LICENSE.txt` | 1 | linha 189: `Copyright 2024 Ramon Silva` |
| `README.md` | 22 | linha 2: `![MapOS](https://raw.githubusercontent.com/RamonSilva20/mapos/master/assets/img/logo.png)` |
| `SECURITY.md` | 7 | linha 3: `O Map-OS é usado por empresas para gerenciar ordens de serviço, dados de clientes e informações financeiras…` |
| `docs/armazenamento-de-arquivos.md` | 1 | linha 3: `Os anexos das OS e os arquivos da tela **Arquivos** ficam fora de 'assets/' e só saem pelo Map-OS, com logi…` |
| `docs/atualizacao.md` | 1 | linha 1: `# Atualização do Map-OS` |
| `docs/cabecalhos-de-seguranca.md` | 2 | linha 28: `\| 'APP_CSP_REPORT' \| 'true' \| manda os relatórios ao endpoint do Map-OS \|` |
| `docs/cookies-e-sessao.md` | 2 | linha 3: `A partir da v5, os cookies do Map-OS saem com os atributos de segurança ligados por padrão. As variáveis fi…` |
| `docs/migracao-4x-para-5.md` | 4 | linha 1: `# Migração do Map-OS 4.x para a v5` |
| `docs/servidor-web.md` | 3 | linha 9: `O '.htaccess' que acompanha o Map-OS já cobre o **Apache** (com` |

## Arquivos binários (imagens)

- `docs/sysgo.png`
