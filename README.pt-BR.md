<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/brand/svg/pagebrick-logo-horizontal-negativo.svg"><img src="docs/brand/svg/pagebrick-logo-horizontal.svg" alt="PageBrick" width="320"></picture></p>

<p align="center"><a href="README.md">English</a> · <b>Português</b> · <a href="README.es.md">Español</a></p>

Um CMS simples para sites de empresas. Um dono de empresa sem conhecimento técnico instala e publica o site com um layout pronto; agências criam os próprios temas sobre uma estrutura organizada, com liberdade total no front-end e a garantia de que as atualizações não vão quebrar os sites entregues.

## O que ele faz

- Instalador em etapas como o do WordPress (idioma, conferência do servidor, banco de dados, site), que cria um site de exemplo pronto: Início, Sobre, Serviços, Contato e Política de privacidade, com fotos. Em português, inglês ou espanhol.
- Páginas com campos, seções que o cliente pode esconder, histórico com "restaurar", rascunhos e pré-visualização.
- Logo, cor, estilo de letra, WhatsApp, dados de contato e redes sociais em **Aparência e contato**.
- Temas que você troca sem perder conteúdo, com pré-visualização privada antes de ativar.
- Formulário de contato (mensagens no painel e por e-mail, proteção contra spam sem captcha) e blog com categorias, como plugins oficiais.
- Loja de plugins e temas com pacotes assinados, envio de .zip, atualizações com backup, "voltar versão" e desfazer automático se uma versão nova quebrar.
- Disjuntores: um plugin ou tema quebrado é desligado sozinho e o site continua no ar. Modo de segurança com link de socorro.
- Atualizações do próprio PageBrick em um clique, que conferem o site inteiro depois e voltam sozinhas se algo quebrou.
- Sites em mais de um idioma: o principal na raiz e os outros em /pt-br, /es-es ou /en-us, com páginas, menus e textos traduzidos.
- Painel claro e escuro, seguindo o sistema ou um botão no topo.
- API de conteúdo (JSON) para front-ends feitos com Next.js, Astro ou qualquer outro framework.
- Chaves de "Em construção" e "Em manutenção": visitantes veem um aviso, quem está logado vê o site.
- SEO básico (título e descrição por página, endereços amigáveis, sitemap.xml, robots.txt), imagens redimensionadas para WebP, nenhum cookie para os visitantes.
- Dois papéis: Administrador (a agência) e Editor (o cliente).

## Requisitos

PHP 8.2+ com `pdo_mysql` e `gd`, MySQL 5.7+ ou MariaDB 10.4+ e Apache com `mod_rewrite`: qualquer hospedagem com cPanel.

## Instalação

1. Baixe o `pagebrick-x.y.z.zip` em [Releases](https://github.com/pagebrick/pagebrick/releases) e envie o conteúdo da pasta `pagebrick/` dele para a sua hospedagem.
2. Crie um banco de dados MySQL (no cPanel: "Bancos de dados MySQL").
3. Abra o endereço do site e preencha o instalador.

## Para desenvolvedores

- [Criando um tema](docs/pt-BR/temas.md): o front-end é todo seu.
- [Criando um plugin](docs/pt-BR/plugins.md)
- [API de conteúdo (headless)](docs/pt-BR/headless.md): o conteúdo em JSON, para front-ends feitos fora do PageBrick.
- [A promessa de compatibilidade](docs/pt-BR/compatibilidade.md): por que as atualizações não quebram os seus sites.
- [Publicação: catálogo, assinaturas e versões](docs/pt-BR/publicacao.md)

### Desenvolvimento local

```bash
docker compose up -d --build
docker compose exec app composer install
```

Abra http://localhost:8080. No instalador, use o servidor `db`, o banco de dados `pagebrick`, o usuário `pagebrick` e a senha `pagebrick`.

Contas de teste usadas no desenvolvimento local: `admin@pagebrick.test` (administrador) e `editor@pagebrick.test` (editor), as duas com a senha `pagebrick-local`.

Os e-mails enviados pelo site local são capturados pelo Mailpit em http://localhost:8025. Para usá-lo, configure o servidor `mailpit`, a porta `1025` e a segurança "Nenhuma" em **Sistema → E-mail**.

Testes:

```bash
docker compose exec app vendor/bin/phpunit
```

Para começar do zero: apague o `config.php` e rode `docker compose down -v`.

## Licença

[GPL-3.0-or-later](LICENSE). Criado pela [Alcateia Digital](https://alcateia.digital). Inclui o editor [Trix](https://github.com/basecamp/trix) (MIT), o [PHPMailer](https://github.com/PHPMailer/PHPMailer) (LGPL 2.1), as fontes Public Sans, Fraunces e Nunito (OFL) e fotos de exemplo em domínio público (CC0).
