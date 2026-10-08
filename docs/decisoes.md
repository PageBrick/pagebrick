# PageBrick — decisões do projeto

Esta é a "planta oficial" do PageBrick. Toda mudança de rumo deve ser registrada aqui.

**Em uma frase:** um CMS em PHP + MySQL para agências — o desenvolvedor constrói o site em cima de uma estrutura pronta, o cliente só preenche o conteúdo, e plugins vêm de uma loja simples e protegida.

## Decisões

| # | Tema | Decisão |
|---|---|---|
| 1 | Público | Agências e freelancers que fazem sites para clientes |
| 2 | Diferencial | Simplicidade: o cliente não se perde e não quebra o layout |
| 3 | Quem programa | Claude programa (com testes automáticos e manuais); o autor decide e aprova |
| 4 | Negócio | Código aberto; receita com serviços (criar, hospedar e manter sites) |
| 5 | Edição | Campos definidos pelo dev, incluindo "lista repetível". Estrutura pronta para virar blocos (itens de tipos diferentes na lista) |
| 6 | Base técnica | PHP puro 8.2+ e MySQL 5.7+ / MariaDB 10.4+, pouquíssimas dependências |
| 7 | Tema | Uma pasta de tema por site, um tema ativo, tema inicial comentado. Sem loja de temas por enquanto |
| 8 | Loja de plugins | Catálogo no GitHub (instalação com 1 clique) + envio de .zip. O painel lê o catálogo de uma URL num formato fixo, para um site-loja futuro servir o mesmo formato |
| 9 | Usuários | Dois papéis: Administrador (agência) e Editor (cliente) |
| 10 | Segurança dos plugins | "Disjuntores": API oficial versionada, teste antes de ativar, desligamento automático do plugin que der erro, modo de segurança, tabelas próprias por plugin, backup + "voltar versão" |
| 11 | Núcleo | Páginas, mídia, menus, usuários, configurações e SEO básico. Formulário de contato e notícias são plugins oficiais |
| 12 | Templates | PHP puro com o conteúdo do cliente já escapado (protegido contra código malicioso) |
| 13 | Painel | PHP renderizado no servidor + JavaScript mínimo |
| 14 | Idiomas | Painel em português com textos traduzíveis desde o início (`__()`); o banco guarda o idioma de cada página |
| 15 | Instalação | Subir o .zip no cPanel + instalador na tela; docker-compose para desenvolvimento local |
| 16 | Atualizações | Aviso + botão "Atualizar", com conferência de assinatura, backup e opção de voltar |
| 17 | Licença | GPL v3 (GPL-3.0-or-later) |
| 18 | Nome | PageBrick |
| 19 | Pasta local | `OneDrive\1. Projetos\20. DNTB\alcateia.digital\sistemas\pagebrick` |
| 20 | Repositório | Organização própria `github.com/pagebrick` ("criado pela Alcateia Digital") |
| 21 | Domínio | pagebrick.org, registrado na GoDaddy |
| — | Público ampliado | Além de agências, o layout padrão precisa permitir que um **usuário não técnico publique o próprio site institucional** |
| 22 | Visual do layout padrão | Identidade básica: logo, ícone, cor principal e 3 estilos de letra. Seções vazias somem sozinhas. Sem "cara de IA" (gradientes, emojis, frases vazias) |
| 23 | Conteúdo pronto | Site de exemplo criado na instalação: Início, Sobre, Serviços, Contato e menus. Textos de exemplo são instruções honestas. **Cada seção pode ser ocultada** no painel. Depoimentos e números de exemplo começam ocultos |
| 24 | Formulário de contato | Plugin oficial pré-instalado (etapa 3). Até lá, botões de WhatsApp, telefone e e-mail |
| — | Marca | Pacote em `docs/brand/`. Tijolo `#D24E2B`, grafite `#171923`, claro `#F3F3F6`. Painel e layout padrão usam a marca; o cliente troca pela dele em "Aparência e contato" |
| — | Temas modulares | Temas são pacotes (como plugins): ativar, pré-visualizar, enviar .zip, atualizar e voltar versão. **Ao trocar de tema, o conteúdo vai junto** |
| 25 | Blog | Um plugin "Blog" com **nome e endereço configuráveis** (Blog em /blog, Notícias em /noticias…) e **categorias com subcategorias**. Substituiu o plugin "Notícias" |
| 26 | Conteúdo entre temas | **Contrato de conteúdo padrão** no núcleo (`core/standard.php`): tipos de página Início, Página simples, Serviços e Contato, mais as configurações de identidade, contato, redes e rodapé. Todo tema exibe esse padrão e pode **acrescentar** campos e tipos de página; os extras ficam guardados se o site trocar de tema |
| — | Atualização segura | Atualizar pelo painel **não pode quebrar sites já feitos** em cima do PageBrick (ver "Lançamento 0.1") |
| 28 | Idiomas | Português, inglês e espanhol no CMS e na documentação. **Um idioma por site**, escolhido na instalação e trocável depois; cada usuário pode ver o painel em outro idioma. Documentação: README principal em inglês, com versões em português e espanhol |
| 29 | Headless | **API de conteúdo em JSON no núcleo já na 0.1** (`/api/v1/site`, `/api/v1/pages`, `/api/v1/pages/{slug}`; o Blog acrescenta `/api/v1/blog`), para front-ends feitos com Next.js, Astro ou aplicativos. Só leitura, só conteúdo publicado, nunca senhas. O formato das respostas entra na promessa de compatibilidade |
| — | Front-end sem limites | O desenvolvedor tem **liberdade total no front-end** e precisa confiar que o site não vai quebrar: o núcleo não coloca nada no site além do que o tema pede, o tema pode substituir qualquer template ou CSS de plugin, e uma atualização **não muda nem um caractere** do HTML que o site entrega |

## Decisões técnicas menores

- Editor de texto do cliente: Trix (poucos botões de propósito); HTML limpo no servidor.
- Histórico: últimas 10 versões de cada página, com "restaurar".
- Rascunho / publicado, com pré-visualização.
- Imagens redimensionadas automaticamente e convertidas para WebP (extensão GD).
- E-mail: PHPMailer com SMTP configurável no painel.
- Anti-spam do formulário: campo invisível + tempo mínimo de preenchimento.
- Segurança: `password_hash`, CSRF em todo formulário, limite de tentativas de login, assinatura de pacotes com libsodium.
- Ganchos para plugins no estilo WordPress (ações e filtros).
- Pastas: `core/` é substituída nas atualizações; `content/` (temas, plugins, uploads) nunca é tocada.
- URLs amigáveis via `.htaccess` (Apache, padrão do cPanel).
- Banco do Docker local num volume nomeado, fora do OneDrive.
- Testes: PHPUnit (só desenvolvimento) + GitHub Actions em PHP 8.2/8.3/8.4 com MySQL e MariaDB + testes manuais no navegador.
- Código (nomes e comentários) em inglês; textos da interface em português via `__()`.

- Fontes do layout padrão hospedadas no próprio site (Public Sans, Fraunces, Nunito; licença OFL), sem Google Fonts: mais rápido e sem enviar dados de visitantes a terceiros (LGPD).
- A cor do texto sobre a cor da marca é calculada automaticamente (contraste WCAG), para o site nunca ficar ilegível.
- O endereço usado no instalador vira o endereço oficial do site (canonical e sitemap).
- Fotos de exemplo do layout padrão: só imagens CC0 (domínio público), compatíveis com a GPL. Origem em `core/demo/CREDITS.md`.

## Plugins (etapa 3)

- **API versão 1.** Um plugin é uma pasta em `content/plugins/` com `plugin.json` e `plugin.php`. Ele se encaixa por ações, filtros, "slots" no tema (`pb_slot('contact')`), rotas públicas, telas no painel, configurações (o mesmo sistema de campos dos temas) e tabelas próprias com migrações. A referência está no topo de `core/plugins.php`.
- **Disjuntores:** todo código de plugin roda protegido. Se ele lançar um erro, devolver um tipo errado num filtro ou derrubar o PHP com um erro fatal, só ele é desligado; o erro fica registrado e o painel avisa. Um plugin também é testado ao ser ativado.
- **Modo de segurança:** pelo link de socorro (mostrado em Plugins), vale só para a sessão de quem usou; ou com `'safe_mode' => true` no `config.php`, para quando só restar acesso ao gerenciador de arquivos.
- **Visitantes não recebem cookies.** A sessão só existe no painel. Formulários públicos se protegem com um carimbo de tempo assinado.
- **Plugins oficiais:** Formulário de contato (vem ativo: mensagens ficam no painel e chegam por e-mail, antispam sem captcha, apagamento automático conforme a LGPD) e Blog (vem desativado; ver decisão 25).
- **E-mail:** PHPMailer com SMTP configurável no painel e botão de teste; sem SMTP, usa o envio da hospedagem. Se o envio falhar, a mensagem do formulário continua guardada.
- **Página "Política de privacidade"** criada no site de exemplo: modelo baseado na LGPD para a empresa completar e revisar.

## Pacotes, loja e atualizações (etapa 4)

- **Um só sistema de pacotes** para plugins, temas e o próprio PageBrick (`core/packages.php`).
- **Catálogo oficial:** um arquivo JSON público (padrão: repositório `pagebrick/catalog` no GitHub). O formato já prevê preço, para um futuro site-loja.
- **Assinatura digital Ed25519** em todo pacote do catálogo, cobrindo tipo, nome, versão e o SHA-256 do arquivo. A chave pública do projeto fica em `PB_TRUSTED_KEYS`; a privada fica **fora do repositório**, com quem publica (ferramenta `tools/pagebrick.php`).
- **.zip enviado pelo administrador** é aceito sem assinatura (é código dele, como enviar por FTP), com o aviso de instalar só de quem confia.
- **Conferências do .zip:** uma pasta principal, nada de caminhos que escapem da pasta, atalhos, `.htaccess` ou "bomba de zip".
- **Backup antes de trocar qualquer versão** (últimas 3 por pacote, em `content/backups/`, inacessível pela web) e botão **"Voltar para x.y"**.
- **Reversão automática:** se um plugin ou tema atualizado der erro na primeira hora, a versão anterior volta sozinha.
- **Atualização do PageBrick** troca só `core/`, `vendor/` e `index.php`. Nunca toca em `content/`, `config.php` ou `.htaccess`.
- **Temas com disjuntor:** antes de ativar, o tema é testado com todas as páginas do site; se quebrar depois, o visitante recebe aquela página no tema padrão e o painel avisa. O tema padrão não pode ser excluído (é a rede de segurança).
- **Pré-visualização de tema** só para o administrador logado, com barra "Ativar este tema / Sair da pré-visualização".
- Os textos do painel não prometem revisão de plugins: o projeto não tem equipe para isso. O que se garante é a assinatura.

## Lançamento 0.1 (etapa 5)

**Garantia de que atualizar não quebra o site** — cinco travas, todas com testes automáticos (`tests/CompatibilityTest.php`):

1. **API pública congelada.** Tudo o que temas e plugins podem usar está listado em `core/api.php` e fotografado em `tests/fixtures/api-v1.json`. Uma versão nova pode acrescentar, nunca tirar ou mudar. As migrações do banco só acrescentam.
2. **Site congelado da 0.1.** `tests/fixtures/sites/v0.1` é um site de agência feito na 0.1 (tema próprio, plugin próprio, plugin oficial). Ele nunca é editado; toda versão nova precisa rodá-lo.
3. **HTML congelado.** `tests/fixtures/sites/v0.1-output` guarda as páginas exatas desse site. Se uma versão nova mudar um caractere do HTML, o teste falha.
4. **Conferência antes de atualizar.** O painel recusa a atualização se o PHP do servidor, um plugin ativo ou o tema não forem compatíveis, e diz qual.
5. **Conferência depois de atualizar, com volta automática.** O primeiro acesso depois da atualização confere todas as páginas; se algo quebrou, a versão anterior volta sozinha, com plugins e tema como estavam. Se a versão nova nem conseguir iniciar, o `index.php` restaura o backup.

**Front-end sem limites:**

- O núcleo não coloca no site nada que o tema não peça: nada de CSS, JavaScript, cookies ou política de conteúdo (CSP). As tags de SEO saem só onde o tema chama `pb_head()`, e os scripts dos plugins onde chama `pb_footer()`.
- HTML, CSS e JavaScript livres: qualquer framework, ferramenta de build ou arquivo em `assets/`. Os cabeçalhos de segurança do núcleo (`X-Frame-Options` etc.) podem ser trocados pelo tema com `header()`.
- **O tema pode substituir qualquer arquivo que um plugin mostra no site**, copiando-o para `themes/{tema}/plugins/{plugin}/{mesmo caminho}` (ex.: `plugins/blog/templates/list.php`, `plugins/contact-form/style.css`). O que não for copiado continua vindo do plugin. O painel nunca usa essas cópias.
- Se uma cópia do tema der erro, a culpa é do tema (o disjuntor do tema mostra a página no tema padrão) e o plugin continua ligado.
- Rotas próprias (uma página `/api/...`, por exemplo) ficam num plugin pequeno do próprio site.

**Idiomas (decisão 28):** textos em `__()` com o português como original; traduções em `lang/en.php` e `lang/es.php` do núcleo, do tema padrão e de cada plugin. Os endereços das páginas de exemplo também são traduzidos (`/about`, `/nosotros`). Fora do português, o número do WhatsApp precisa do código do país. Um teste confere que todo texto tem tradução e outro percorre o site e 17 telas do painel em inglês procurando português esquecido.

**Lançamento:**

- `php tools/pagebrick.php release` gera o `pagebrick-x.y.z.zip` (só o necessário: sem testes, ferramentas, documentação ou Docker) e, com a chave, a entrada assinada do catálogo.
- Testes em PHP 8.2 e 8.4 com MariaDB 10.11, MySQL 5.7 e MySQL 8.0. No GitHub Actions: PHP 8.2/8.3/8.4 com MySQL 5.7/8.0 e MariaDB 10.4/10.11/11.4, mais a montagem do pacote.
- Teste ao vivo (07/10/2026): instalação em inglês a partir do .zip num servidor separado; atualização boa pelo painel (0.1.0 → 0.1.1) mantida; atualização quebrada de propósito (0.1.2) desfeita sozinha no primeiro acesso, com o aviso de qual página quebrou.
- Documentação para desenvolvedores em `docs/en`, `docs/pt-BR` e `docs/es`: temas, plugins, compatibilidade e publicação. README principal em inglês, com versões em português e espanhol.

## Fica para depois

Agendamento de publicação, comentários, multi-site, editor de blocos, site-loja, conteúdo multi-idioma (plugin).

## Ordem de construção

1. **Base:** instalador, login com os 2 papéis, Docker local.
2. **Conteúdo:** temas, páginas com campos, mídia, menus, SEO, tema inicial.
3. **Plugins:** disjuntores, modo de segurança, plugins oficiais (formulário de contato, blog).
4. **Loja e atualizações:** catálogo, envio de .zip, atualização assinada com backup e "voltar".
5. **Lançamento 0.1** no GitHub com documentação para devs.

Cada etapa termina com testes passando e uma demonstração.
