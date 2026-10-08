# Criando um tema

Um tema é todo o front-end de um site PageBrick: cada byte de HTML, CSS e JavaScript que o visitante recebe. O PageBrick entrega o conteúdo, já preenchido pelo seu cliente em um painel simples, e não atrapalha você.

[English](../en/themes.md) · **Português** · [Español](../es/temas.md)

## Sua liberdade, e com o que você pode contar

- **O núcleo não adiciona nada ao site que você não pediu.** Nada de CSS, JavaScript, cookies ou Content-Security-Policy. As tags de SEO aparecem só onde você chama `pb_head()`, os scripts de plugins só onde você chama `pb_footer()` e o HTML de plugins só onde você chama `pb_slot()`.
- **Qualquer HTML, CSS e JavaScript.** Use Tailwind, Sass, Vite, Alpine, ilhas de React, CSS puro: o que você criar vai na pasta do seu tema. Qualquer arquivo estático dentro do tema é servido como está (arquivos PHP, nunca).
- **Os plugins não impõem o markup deles.** O seu tema pode substituir qualquer template ou folha de estilo que um plugin mostra no site (veja [Substituindo os templates de um plugin](#substituindo-os-templates-de-um-plugin)).
- **Os cabeçalhos também são seus.** O núcleo envia `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN` e `Referrer-Policy`; chame `header()` no seu layout para mudar esses cabeçalhos ou adicionar os seus.
- **Ou dispense o PHP.** Faça o front-end com Next.js, Astro ou um aplicativo e leia o conteúdo em JSON pela [API de conteúdo](headless.md).
- **As atualizações não quebram o seu site.** Tudo o que um tema pode usar é uma API congelada e versionada ([compatibilidade.md](compatibilidade.md)). A cada versão, um teste automático renderiza um site feito no PageBrick 1.0 e falha se um único caractere do HTML dele (ou das respostas da API de conteúdo) mudar. Se mesmo assim uma atualização quebrar algo em um site real, ela é desfeita automaticamente na primeira visita.
- **Um tema quebrado não derruba o site.** O tema é testado com todas as páginas antes de ser ativado; se falhar depois, aquela página é mostrada com o tema padrão e o painel avisa o administrador do que aconteceu.

## Arquivos

```
content/themes/my-theme/
├── theme.json          nome, versão, versão da API
├── theme.php           os campos extras, tipos de página, configurações e menus que o seu tema adiciona
├── layout.php          o HTML em volta de todas as páginas
├── templates/
│   ├── home.php        obrigatório
│   ├── page.php        obrigatório
│   ├── services.php    obrigatório
│   ├── contact.php     obrigatório
│   ├── 404.php         opcional
│   ├── closed.php      opcional: a página inteira mostrada enquanto o site está em construção ou em manutenção
│   └── landing.php     qualquer tipo de página que você adicionar
├── plugins/            opcional: suas cópias de templates e folhas de estilo de plugins
├── assets/             qualquer coisa: CSS, JS, fontes, imagens, o resultado do seu build
├── lang/en.php, es.php opcional: traduções dos textos do seu tema
├── demo.php, demo/     opcional: conteúdo pronto (páginas, fotos, menus, configurações)
└── screenshot.webp     opcional: imagem mostrada na tela de Temas (800×500)
```

O jeito mais rápido de começar é copiar `content/themes/default/` e mudar o nome no `theme.json`:

```json
{
    "name": "Meu tema",
    "version": "1.0.0",
    "description": "Uma frase mostrada na tela de Temas.",
    "author": "Sua agência",
    "api": 1
}
```

`"api": 1` é a versão da API do PageBrick para a qual o tema foi escrito. Um tema para outra versão da API é recusado em vez de quebrar o site.

## Conteúdo: o contrato padrão

Todo site PageBrick tem o mesmo conteúdo básico, definido pelo núcleo em [core/standard.php](../../core/standard.php): quatro tipos de página (`home`, `page`, `services`, `contact`), as configurações de **Aparência e contato** e dois menus (`main`, `footer`). Todo tema precisa mostrar esse conteúdo. É por isso que o cliente pode trocar de tema sem perder nada, e é por isso que o seu tema funciona com qualquer site.

| Tipo de página | Campos |
|---|---|
| `home` | `hero` (title, text, button_label, button_link, image) · `about` (title, text, image) · `services` (title, intro, items[title, text, image], link_label, link) · `numbers` (title, items[value, label]) · `testimonials` (title, items[quote, name, role]) · `cta` (title, text, button_label, button_link). O cliente pode esconder qualquer seção. |
| `page` | `intro`, `image`, `body` |
| `services` | `intro`, `items[title, text, image]`, `cta` (title, text, button_label, button_link) |
| `contact` | `intro`, `body` (o plugin de formulário de contato adiciona o formulário por meio de `pb_slot('contact')`) |

| Configurações | Campos |
|---|---|
| `identity` | `logo`, `icon`, `color`, `fonts` (`sobria`, `elegante` ou `acolhedora`), `share_image` |
| `contact` | `whatsapp`, `whatsapp_message`, `phone`, `email`, `address`, `hours` |
| `social` | `instagram`, `facebook`, `linkedin`, `youtube`, `tiktok` |
| `footer` | `text`, `credit` |

## Adicionando seus próprios campos e tipos de página

O `theme.php` retorna só o que o seu tema **adiciona**. O painel monta os formulários sozinho.

```php
<?php
return [
    'templates' => [
        // Um campo adicionado à página inicial padrão:
        'home' => ['fields' => [
            'video' => ['type' => 'url', 'label' => 'Vídeo do destaque'],
        ]],
        // Um novo tipo de página (templates/landing.php):
        'landing' => ['label' => 'Página de campanha', 'fields' => [
            'offer' => ['type' => 'text', 'label' => 'Oferta'],
            'perks' => ['type' => 'list', 'label' => 'Vantagens', 'item_label' => 'Vantagem', 'add_label' => 'Adicionar vantagem', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Vantagem'],
                'icon' => ['type' => 'image', 'label' => 'Ícone'],
            ]],
            'cta' => ['type' => 'group', 'label' => 'Botão', 'toggle' => true, 'fields' => [
                'label' => ['type' => 'text', 'label' => 'Texto'],
                'link' => ['type' => 'link', 'label' => 'Leva para'],
            ]],
        ]],
    ],
    'settings' => [
        'agency' => ['type' => 'group', 'label' => 'Agência', 'fields' => [
            'accent' => ['type' => 'color', 'label' => 'Cor de destaque', 'default' => '#0a7c66'],
        ]],
    ],
    'menus' => ['top' => 'Menu da barra superior'],
];
```

Você pode adicionar campos, tipos de página, configurações e menus; não pode remover nem alterar os padrões (o núcleo mantém a versão dele). O que você adiciona continua salvo se o site trocar de tema, e volta se ele voltar para o seu.

**Tipos de campo:** `text`, `textarea`, `richtext` (um editor simples; o HTML é limpo ao salvar), `image`, `url`, `email`, `tel`, `color`, `select` (com `'options' => ['value' => 'Label']`), `link` (uma página do site ou qualquer endereço), `list` (itens repetíveis, com `fields`), `group` (com `fields`; `'toggle' => true` deixa o cliente esconder o grupo). Todo campo aceita `label`, `help` e `default`.

Precisa de funções auxiliares? Coloque-as em um arquivo e use `require_once __DIR__ . '/functions.php';` no início do `theme.php`.

## Layout e templates

O `layout.php` é o esqueleto das suas páginas. Ele recebe `$content` (o HTML do template), `$site` (as configurações) e `$siteName`:

```php
<!doctype html>
<html lang="<?= e(pb_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= e(pb_theme_url('assets/style.css')) ?>">
<?= pb_head(['image' => $site->identity->share_image->url()]) ?>
</head>
<body>
<header>
    <a href="<?= e(pb_url('/')) ?>"><?= $siteName ?></a>
    <nav><?= pb_menu_html('main') ?></nav>
</header>
<main><?= $content ?></main>
<?= pb_footer() ?>
</body>
</html>
```

Um template recebe `$page` (os campos da página), `$title` e `$isHome`:

```php
<h1><?= $title ?></h1>
<?php if ($page->hero->visible()): ?>
    <section class="hero">
        <h2><?= $page->hero->title ?></h2>
        <?= $page->hero->image->img(lazy: false) ?>
        <?php if (!$page->hero->button_label->isEmpty()): ?>
            <a href="<?= e($page->hero->button_link->url()) ?>"><?= $page->hero->button_label ?></a>
        <?php endif ?>
    </section>
<?php endif ?>
<?php foreach ($page->services->items as $item): ?>
    <article><h3><?= $item->title ?></h3><p><?= $item->text ?></p></article>
<?php endforeach ?>
<?= pb_slot('home') ?>
```

### Os valores são seguros por padrão

Imprimir um campo com `<?= ?>` é sempre seguro: o texto é escapado, o texto rico foi limpo ao salvar e uma imagem vira uma tag `<img>`. O seu cliente não consegue quebrar o seu layout nem injetar scripts.

| Em um campo | Você recebe |
|---|---|
| `<?= $page->title ?>` | HTML seguro |
| `->raw()` | o valor armazenado, sem escape: escape você mesmo com `e()` |
| `->isEmpty()` | `true` quando o cliente deixou em branco |
| `->url($size = 'full')` | o endereço de um link, página, e-mail (`mailto:`), telefone (`tel:`) ou imagem (`'thumb'` para a pequena). Escape o resultado: `e($value->url())` |
| `->img($class = '', $size = 'full', $lazy = true)` | `<img>` com `alt`, `width`, `height` e carregamento sob demanda (lazy loading); `lazy: false` para imagens no topo |
| um grupo: `->visible()` | `false` quando o cliente escondeu aquela seção |
| uma lista: `foreach`, `count()`, `->isEmpty()` | os itens dela, cada um sendo um grupo |

Com `'debug' => true` no `config.php`, ler um campo que não existe gera um aviso, então os erros de digitação aparecem enquanto você desenvolve.

### Funções auxiliares

| Função | Uso |
|---|---|
| `e($text)` | escapar qualquer texto que você mesmo imprime |
| `pb_url('/path')`, `pb_absolute_url('/path')` | endereços que funcionam quando o site fica em uma subpasta |
| `pb_theme_url('assets/app.js')` | um arquivo do seu tema, com `?v=` para os navegadores pegarem as mudanças |
| `pb_head($options)` | título, descrição, canonical, Open Graph e tags de plugins. Se preferir, deixe de fora e escreva as suas |
| `pb_footer()` | scripts de plugins e, para um administrador pré-visualizando um tema, a barra de pré-visualização |
| `pb_slot('home')`, `pb_slot('contact')` | onde os plugins adicionam HTML; chame nesses dois templates |
| `pb_menu_html('main', 'class')`, `pb_menu('main')` | um menu como `<ul>`, ou como um array de `label`, `url`, `current` para montar o seu próprio markup |
| `pb_whatsapp_url($number, $message)`, `pb_map_url($address)` | links de WhatsApp e de mapa |
| `pb_text_color_on($color)`, `pb_readable_color($color)`, `pb_contrast($a, $b)` | cores que continuam legíveis, seja qual for a cor da marca que o cliente escolher |
| `pb_is_logged_in()` | mostrar dicas que só o dono do site vê ("preencha o seu telefone") |
| `pb_locale()`, `pb_date($datetime)`, `__('text')` | idioma e datas do site |

A lista completa do que temas e plugins podem usar com segurança está em [core/api.php](../../core/api.php). Todo o resto, mesmo que comece com `pb_`, é interno e pode mudar.

## Substituindo os templates de um plugin

Os plugins renderizam as páginas públicas deles dentro do seu layout. Quando o markup não é o que você quer, copie o arquivo para o seu tema, em `plugins/{plugin}/` com o mesmo caminho, e altere:

```
content/plugins/blog/templates/list.php        →  content/themes/my-theme/plugins/blog/templates/list.php
content/plugins/contact-form/form.php          →  content/themes/my-theme/plugins/contact-form/form.php
content/plugins/contact-form/style.css         →  content/themes/my-theme/plugins/contact-form/style.css
```

- Os arquivos que você não copia continuam vindo do plugin, mesmo quando a sua cópia os inclui (`pb_include(__DIR__ . '/cards.php', …)` ainda encontra o `cards.php` do plugin).
- Uma cópia vazia de `style.css` remove os estilos do plugin, para você estilizar o markup dele no seu próprio CSS.
- O painel nunca usa as suas cópias: as telas do plugin ficam como o plugin fez.
- Se a sua cópia quebrar, a culpa é do tema, não do plugin: a página volta para o tema padrão e o plugin continua funcionando.
- Quando um plugin muda um template em uma versão nova, a sua cópia continua funcionando como está. Compare com a versão nova quando atualizar o seu tema.

## Conteúdo pronto

Um tema pode trazer o próprio conteúdo, para um site novo parecer pronto assim que o tema é ativado. Crie um `demo.php` que devolve páginas, fotos (arquivos em `demo/`), menus e configurações, no mesmo formato do site de exemplo do núcleo ([core/standard.php](../../core/standard.php), `pb_standard_demo()`). Dentro dele, `page:{slug}` e `media:{chave}` apontam para as páginas e fotos do próprio conteúdo.

O administrador importa com **Importar o conteúdo do tema** em **Sistema → Temas**. Páginas com o mesmo endereço recebem o conteúdo novo (a versão anterior fica no histórico), páginas novas são criadas, os menus são trocados e as configurações que o conteúdo não menciona continuam como estão.

## Em construção e em manutenção

Enquanto o site está **Em construção** ou **Em manutenção** (Painel → Situação do site), os visitantes recebem uma resposta 503 e um aviso curto; quem está logado vê o site normalmente. Desenhe esse aviso em `templates/closed.php`: um documento HTML completo que recebe `$mode`, `$title`, `$message`, `$site` e `$siteName`. Se ele quebrar, aparece o aviso do próprio núcleo.

## Traduções

Envolva os textos do seu tema em `__()` e adicione `lang/en.php` e `lang/es.php` retornando `['texto original em português' => 'tradução']`. O idioma do site é escolhido na instalação (português, inglês ou espanhol) e o visitante vê o site nesse idioma.

## Testando e entregando

1. Ative o tema em **Sistema → Temas**. O PageBrick antes renderiza todas as páginas do site com o seu tema e recusa o tema, com o motivo, se algo falhar. **Pré-visualizar** mostra o site com o seu tema só para você.
2. Compacte a pasta do tema (`my-theme.zip` contendo `my-theme/`) e envie em outro site em **Sistema → Temas → Enviar tema (.zip)**.
3. Para oferecer o tema no catálogo oficial, veja [publicacao.md](publicacao.md).
