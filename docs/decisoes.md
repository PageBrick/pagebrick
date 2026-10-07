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

## Fica para depois

Agendamento de publicação, comentários, multi-site, editor de blocos, site-loja, conteúdo multi-idioma (plugin), API headless.

## Ordem de construção

1. **Base:** instalador, login com os 2 papéis, Docker local.
2. **Conteúdo:** temas, páginas com campos, mídia, menus, SEO, tema inicial.
3. **Plugins:** disjuntores, modo de segurança, plugins oficiais (formulário de contato, notícias).
4. **Loja e atualizações:** catálogo, envio de .zip, atualização assinada com backup e "voltar".
5. **Lançamento 0.1** no GitHub com documentação para devs.

Cada etapa termina com testes passando e uma demonstração.
