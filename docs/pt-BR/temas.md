# Criando um tema

Um tema é todo o front-end de um site PageBrick: cada byte de HTML, CSS e JavaScript que o visitante recebe. O PageBrick entrega o conteúdo, já preenchido pelo seu cliente em um painel simples, e não atrapalha você.

[English](../en/themes.md) · **Português** · [Español](../es/temas.md)

## Sua liberdade, e com o que você pode contar

- **O núcleo não adiciona nada ao site que você não pediu.** Nada de CSS, JavaScript, cookies ou Content-Security-Policy (o dono pode pedir o Google Analytics 4 e o pixel da Meta em **Configurações → Rastreamento**: até lá `pb_head()` e `pb_footer()` não imprimem nada disso, e depois o visitante é consultado primeiro, por padrão). As tags de SEO aparecem só onde você chama `pb_head()`, os scripts de plugins só onde você chama `pb_footer()` e o HTML de plugins só onde você chama `pb_slot()`. A única exceção é para quem está logado no painel, nunca para visitantes: onde você chama `pb_footer()` essas pessoas ganham uma barra pequena no pé da página (o caminho de volta ao painel, ações rápidas e a visão ao vivo). Ela se desenha dentro de um shadow root, longe do seu CSS, e dá para desligar: `pb_add_filter('admin_bar', fn() => false);` no seu `layout.php`, antes do `pb_footer()`.
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

O jeito mais rápido de começar é copiar `content/themes/default/` com outro nome e mudar o nome no `theme.json`. Não edite o tema padrão no lugar: ele é do próprio PageBrick (o site volta para ele quando o seu tema quebra), então uma atualização o renova (a pasta antiga fica guardada nos backups):

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

Todo site PageBrick tem o mesmo conteúdo básico, definido pelo núcleo em [core/standard.php](../../core/standard.php): quatro tipos de página (`home`, `page`, `services`, `contact`), as configurações de **Contato** e **Aparência** e dois menus (`main`, `footer`). Todo tema precisa mostrar esse conteúdo. É por isso que o cliente pode trocar de tema sem perder nada, e é por isso que o seu tema funciona com qualquer site.

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

As configurações que você adiciona aparecem em **Contato**, a tela que qualquer pessoa que edita o site abre. Um grupo sobre o visual (cores, tipografia) pode pedir **Configurações → Aparência**, que só administradores abrem, com `'screen' => 'appearance'`.

**Tipos de campo:** `text`, `textarea`, `richtext` (um editor de texto: títulos, negrito, itálico, listas, citações, links, tabelas, divisores e imagens da biblioteca de mídia; o HTML é limpo ao salvar), `image`, `url`, `email`, `tel`, `color`, `select` (com `'options' => ['value' => 'Label']`), `link` (uma página do site ou qualquer endereço), `list` (itens repetíveis, com `fields`), `group` (com `fields`; `'toggle' => true` deixa o cliente esconder o grupo). Todo campo aceita `label`, `help` e `default`.

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
| `->img($class = '', $size = 'full', $lazy = true, $sizes = '')` | `<img>` com `alt`, `width`, `height` e carregamento sob demanda (lazy loading); `lazy: false` para imagens no topo. Com `sizes:` imprime também um `srcset` (veja abaixo) |
| um grupo: `->visible()` | `false` quando o cliente escondeu aquela seção |
| uma lista: `foreach`, `count()`, `->isEmpty()` | os itens dela, cada um sendo um grupo |

Um celular não precisa de uma foto de 1920 pixels. O PageBrick guarda três cópias de cada foto (480, 960 e a largura inteira, até 1920), e `img(sizes: '…')` deixa o navegador pegar a menor que ainda fique nítida. `sizes` é o atributo do HTML: diga que largura a foto ocupa na página, por exemplo `img(sizes: '(min-width: 900px) 560px, 94vw')`. Para uma foto lá embaixo (lazy), `sizes: 'auto'` deixa o navegador medir o lugar sozinho; para uma no topo, informe a largura real, como `'100vw'` num banner de ponta a ponta. Sem `sizes` a tag é exatamente a das versões anteriores, então um tema só muda quando o autor quer. As fotos enviadas antes do PageBrick 1.8.0 só têm a miniatura e a inteira até que se use **Mídia → Refazer os tamanhos**.

**O aviso de cookies e o rastreamento.** Quando o dono salva um código do Google Analytics 4 ou do pixel da Meta em Configurações → Rastreamento, `pb_head()` e `pb_footer()` escrevem o trecho oficial sozinhas (o tema não muda nada) e, por padrão, `pb_footer()` também imprime um aviso simples "Aceitar / Recusar"; o Google e a Meta só são carregados depois do "Aceitar", e a escolha fica guardada no navegador. Quem está logado no painel não é contado, e nada é contado enquanto o site não estiver no ar. O aviso tem estilos em linha, como as barras, e um tema pode desenhar o seu com o filtro `consent_html` (recebe o aviso e os nomes das ferramentas): os botões são qualquer elemento com `data-pb-consent="yes"` ou `"no"`, a caixa é `#pb-consent` (escondida até precisar, aparece com `display:flex`), e `data-pb-consent="ask"` em qualquer link mostra o aviso de novo. A mesma tela recebe o coletor de comportamento do Path Ranker: o dono cola o snippet, o PageBrick confere que é exatamente um `<script src=".../pr-collect.js" data-key="…">` (https; http só para localhost) e escreve a tag de novo, com escape, no `pb_head()`. Ele não usa cookies, então não espera o aviso.

Com `'debug' => true` no `config.php`, ler um campo que não existe gera um aviso, então os erros de digitação aparecem enquanto você desenvolve.

### Texto rico: estilize o que o cliente pode escrever

Um campo `richtext` pode ter parágrafos, `h2`/`h3`, `strong`, `em`, `u`, `s`, `a`, listas, `blockquote`, `hr`, `table` (com `thead`/`tbody`, `tr`, `th`, `td`) e `img`. O núcleo não adiciona CSS: imprima o campo dentro de um contêiner seu e estilize esses elementos nele, inclusive tabelas e imagens:

```css
.prose img { max-width: 100%; height: auto; }
.prose table { display: block; overflow-x: auto; border-collapse: collapse; } /* uma tabela larga rola dentro do texto */
.prose th, .prose td { padding: .5rem .75rem; border: 1px solid #ddd; }
```

As imagens vêm só da biblioteca de mídia: o PageBrick escreve o `<img>` (com `alt`, `width`, `height` e carregamento sob demanda), então ele continua funcionando se o site mudar de lugar. Um texto escrito antes do PageBrick 1.3 (com `div` e `br`) aparece exatamente como foi salvo.

### Funções auxiliares

| Função | Uso |
|---|---|
| `e($text)` | escapar qualquer texto que você mesmo imprime |
| `pb_url('/path')`, `pb_absolute_url('/path')` | endereços que funcionam quando o site fica em uma subpasta |
| `pb_theme_url('assets/app.js')` | um arquivo do seu tema, com `?v=` para os navegadores pegarem as mudanças |
| `pb_head($options)` | título, descrição, canonical, Open Graph e tags de plugins. Se preferir, deixe de fora e escreva as suas |
| `pb_footer()` | scripts de plugins, a barra de quem está logado no painel e, para um administrador pré-visualizando um tema, a barra de pré-visualização; e, só quando o dono salvou códigos do Google Analytics ou da Meta em Rastreamento, o aviso de cookies (veja abaixo) |
| `pb_slot('home')`, `pb_slot('contact')` | onde os plugins adicionam HTML; chame nesses dois templates |
| `pb_menu_html('main', 'class')`, `pb_menu('main')` | um menu como `<ul>`, ou como um array de `label`, `url`, `current` para montar o seu próprio markup |
| `pb_whatsapp_url($number, $message)`, `pb_map_url($address)` | links de WhatsApp e de mapa |
| `pb_text_color_on($color)`, `pb_readable_color($color)`, `pb_contrast($a, $b)` | cores que continuam legíveis, seja qual for a cor da marca que o cliente escolher |
| `pb_is_logged_in()` | mostrar dicas que só o dono do site vê ("preencha o seu telefone") |
| `pb_locale()`, `pb_date($datetime)`, `__('text')` | idioma e datas do site |

A lista completa do que temas e plugins podem usar com segurança está em [core/api.php](../../core/api.php). Todo o resto, mesmo que comece com `pb_`, é interno e pode mudar.

## Deixando o cliente reordenar as seções

No editor de páginas o cliente vê a página ao lado do formulário, muda o que quiser, arrasta as seções para outro lugar (na lista ou na própria página) e só depois clica em **Publicar**; até lá os visitantes continuam vendo a versão publicada. Quem faz a ordenação funcionar é o seu tema, em dois passos, e um tema que não faz nada mantém a ordem fixa dele (nada quebra):

1. Liste as seções no `theme.php`, em `'sections'` do modelo. Cada uma é o nome de um campo `group` desse modelo (os padrões `hero`, `about`… da página inicial, ou grupos seus), ou `'plugins'`, o lugar onde os plugins colocam as seções deles com `pb_slot('home')`.
2. Imprima as seções na ordem do cliente no template: `$page->order($nomes)` devolve os seus nomes nessa ordem (os que o cliente ainda não colocou vão por último), e `pb_section($nome, $html)` devolve o seu HTML exatamente como está para os visitantes, só marcando a seção enquanto o cliente edita, para ela poder ser escolhida e arrastada na página.

```php
// theme.php: the sections of the home page the client may put in any order
return [
    'templates' => [
        'home' => ['sections' => ['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta']],
    ],
];
```

```php
<?php // templates/home.php
foreach ($page->order(['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta']) as $section) {
    echo pb_section($section, pb_include(__DIR__ . "/parts/home-$section.php", ['page' => $page, 'title' => $title]));
}
```

Campos que não estão na lista (um destaque que sempre fica no topo, por exemplo) são impressos onde você os imprimir. A API de conteúdo acrescenta `section_order` a uma página só quando o cliente escolheu uma ordem. Enquanto o cliente edita, a página na pré-visualização tem a classe `pb-editing` no `<html>`: use-a para desligar o que espera uma rolagem para aparecer (entradas com fade, animações de chegada), porque as seções são trocadas ali enquanto são olhadas.

Para um tema que também precise rodar num PageBrick anterior à 1.3, confira antes `method_exists($page, 'order')` e `function_exists('pb_section')`.

## Deixando o cliente adicionar seções

Além de mexer nas seções que você fez, o cliente pode **adicionar** outras: texto, foto com texto, cartões, perguntas e respostas, depoimentos, chamada para ação, números, galeria de fotos e vídeo do YouTube. No editor da página, um botão **Adicionar seção** e um "+" entre as seções (na lista e na própria página) abrem uma galeria com um desenho de cada tipo; o cliente também pode duplicar uma seção ou remover uma (com **Desfazer**). O núcleo guarda tudo junto com a página e imprime como **HTML simples, sem CSS e sem script**, com nomes de classe estáveis: o visual é trabalho seu, no seu tema.

1. Ligue no `theme.php`, em cada tipo de página que deve ter: `'blocks' => true` para todos os tipos, ou uma lista como `'blocks' => ['text', 'cards']`. Um tema que não diz nada não ganha nenhuma, e nada muda.
2. Imprima a página com `pb_sections($page, $names, $render)`. Ele percorre as seções na ordem que o cliente escolheu, chama o seu `$render($name)` para cada nome seu e imprime cada seção que o cliente adicionou, marcando todas enquanto o cliente edita.

```php
// theme.php
return ['templates' => [
    'page' => ['sections' => ['content'], 'blocks' => true], // 'content': o que os campos do próprio modelo imprimem (título, texto…)
    'home' => [
        'sections' => ['hero', 'about' => 'image_text', 'services' => 'cards', 'cta' => 'cta'],
        'blocks' => true,
    ],
]];
```

```php
<?php // templates/page.php
echo pb_sections($page, ['content'], fn($name) => pb_include(__DIR__ . '/parts/page-content.php', ['page' => $page, 'title' => $title]));
```

Um nome com um tipo (`'about' => 'image_text'`) diz que a sua seção guarda a mesma coisa que aquele tipo de seção: o painel passa a oferecer **Duplicar** nela, e a cópia é uma seção que o cliente também pode remover. Um nome sem tipo (`'hero'`) não pode ser copiado. `'content'` é o nome para o que os campos do próprio modelo imprimem (o título, a introdução, o texto), para que o cliente possa pôr seções antes dele também.

### O que cada tipo imprime

Cada um é um `<section class="pb-block pb-block-{tipo}">` com um `h2` quando tem título; o que está vazio fica de fora.

| Tipo | Campos | Dentro |
|---|---|---|
| `text` | `title`, `text` | `.pb-block-body` |
| `image_text` | `title`, `text`, `image`, `side` (`left` ou `right`) | `.pb-block-image` e `.pb-block-words`, na ordem em que aparecem; classe `pb-side-left` ou `pb-side-right` |
| `cards` | `title`, `intro`, `items` (`title`, `text`, `image`, `link`), `link_label`, `link` | `ul.pb-cards > li.pb-card` |
| `faq` | `title`, `items` (`question`, `answer`) | `details.pb-faq-item`: o do próprio navegador, sem script |
| `testimonials` | `title`, `items` (`quote`, `name`, `role`) | `figure.pb-quote` |
| `cta` | `title`, `text`, `button_label`, `button_link` | `a.pb-button` |
| `numbers` | `title`, `items` (`value`, `label`) | `ul.pb-numbers > li.pb-number` |
| `gallery` | `title`, `items` (`image`, `caption`) | `ul.pb-gallery`, com um `figure` em cada |
| `video` | `title`, `url`, `caption` | `figure.pb-video > iframe` de youtube-nocookie.com; de um endereço do YouTube só se usa o código do vídeo (`pb_youtube_id()`) |

### Desenhando um do seu jeito

Ponha um arquivo com o nome do tipo em `templates/blocks/` do seu tema (`templates/blocks/cards.php`). Ele roda com `$block` (os campos, lidos como `$page`: `$block->title`, `$block->items`), `$type`, `$id` e `$page`. Um tipo sem arquivo seu é desenhado pelo núcleo; copie um de `core/blocks/` para começar.

As seções ficam nos dados da página, então um tema que não as liga as esconde, e voltar ao tema as traz de volta. A API de conteúdo lista as que estão no site em `blocks` (cada uma `{id, type, fields}`), e `section_order` as chama de `block-{id}`. Para um tema que também precise rodar num PageBrick anterior à 1.3, confira antes `function_exists('pb_sections')`.

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

O administrador importa com **Importar o conteúdo do tema** em **Configurações → Temas**. Páginas com o mesmo endereço recebem o conteúdo novo (a versão anterior fica no histórico), páginas novas são criadas, os menus são trocados e as configurações que o conteúdo não menciona continuam como estão. Para transformar uma página em outra (a página padrão Serviços em Recursos, por exemplo), dê à página nova `'replaces' => 'servicos'`: ela assume aquela página e as traduções dela, no endereço novo. Se as páginas principais estão escritas num idioma, informe com `'locale' => 'en'`: o PageBrick recusa o conteúdo num site de outro idioma principal, em vez de misturar.

## Em construção e em manutenção

Enquanto o site está **Em construção** ou **Em manutenção** (Painel → Situação do site), os visitantes recebem uma resposta 503 e um aviso curto; quem está logado vê o site normalmente. Desenhe esse aviso em `templates/closed.php`: um documento HTML completo que recebe `$mode`, `$title`, `$message`, `$site` e `$siteName`. Se ele quebrar, aparece o aviso do próprio núcleo.

## Sites em mais de um idioma

Um site pode ser oferecido em idiomas extras (Configurações, a engrenagem do menu lateral → Endereço e idiomas). O idioma principal fica na raiz (`/sobre`); os outros, com prefixo: `/en-us/about`, `/es-es/nosotros`, `/pt-br/sobre`. Cada página ganha as suas traduções em **Páginas**, os menus levam às páginas traduzidas e os textos de Aparência e contato também podem ser traduzidos. Os seus templates não mudam: `$page`, `$site`, os menus e `__()` já falam o idioma de quem visita, e o `pb_head()` acrescenta as tags `hreflang` que os buscadores precisam.

Para um seletor de idioma, use `pb_language_links()`. Ela devolve os idiomas do site com `locale`, `name`, `url` e `current`, cada um levando a esta página naquele idioma (ou à página inicial dele), e uma lista vazia num site de um idioma só:

```php
<?php if (count($languages = pb_language_links()) > 1): ?>
    <select onchange="location.href = this.value" aria-label="<?= e(__('Idioma')) ?>">
        <?php foreach ($languages as $language): ?>
            <option value="<?= e($language['url']) ?>"<?= $language['current'] ? ' selected' : '' ?>><?= e($language['name']) ?></option>
        <?php endforeach ?>
    </select>
<?php endif ?>
```

`pb_content_locale()` diz em que idioma a pessoa está lendo, e `pb_page_translation($page, $locale)` encontra uma página em outro idioma.

## Traduções

Envolva os textos do seu tema em `__()` e adicione `lang/en.php` e `lang/es.php` retornando `['texto original em português' => 'tradução']`. O idioma do site é escolhido na instalação (português, inglês ou espanhol) e o visitante vê o site nesse idioma.

## Testando e entregando

1. Ative o tema em **Configurações → Temas**. O PageBrick antes renderiza todas as páginas do site com o seu tema e recusa o tema, com o motivo, se algo falhar. **Pré-visualizar** mostra o site com o seu tema só para você.
2. Compacte a pasta do tema (`my-theme.zip` contendo `my-theme/`) e envie em outro site em **Configurações → Temas → Enviar tema (.zip)**.
3. Para oferecer o tema no catálogo oficial, veja [publicacao.md](publicacao.md).
