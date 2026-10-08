# API de conteúdo (headless)

Prefere fazer o front-end com Next.js, Astro, Nuxt, SvelteKit ou um aplicativo para celular? O PageBrick também entrega o conteúdo do site em JSON. O seu cliente continua editando no mesmo painel simples; o seu front-end lê o conteúdo e faz o resto.

[English](../en/headless.md) · **Português** · [Español](../es/headless.md)

## Endereços

Todos são públicos, só de leitura, `GET`, em `/api/v1/` do site PageBrick:

| Endereço | O que entrega |
|---|---|
| `/api/v1/site` | nome do site, idioma, endereço, as configurações de **Aparência e contato** e todos os menus |
| `/api/v1/pages` | as páginas publicadas, sem o conteúdo: `id`, `title`, `slug`, `path`, `template`, `home`, `updated_at` |
| `/api/v1/pages/{slug}` | uma página publicada: o mesmo, mais `seo` (`title`, `description`) e `fields` |
| `/api/v1/blog?page=2&category={slug}` | com o plugin Blog ativo: os posts (mais recentes primeiro, sem o texto), as páginas, o total e todas as categorias |
| `/api/v1/blog/{slug}` | com o plugin Blog ativo: um post com o texto |

Qualquer outro endereço em `/api/` responde `404` com `{"error": "not_found"}`.

```bash
curl https://example.com/api/v1/pages/sobre
```

```json
{
    "id": 2,
    "title": "Sobre",
    "slug": "sobre",
    "path": "/sobre",
    "template": "page",
    "home": false,
    "updated_at": "2026-10-07T22:48:00+00:00",
    "seo": {"title": "Sobre · Padaria Acme", "description": ""},
    "fields": {
        "intro": "Conte como o negócio começou…",
        "image": {"url": "https://example.com/content/uploads/2026/10/a408f3b279821d30.webp", "thumb": "https://example.com/content/uploads/2026/10/a408f3b279821d30-thumb.webp", "alt": "Nossa equipe", "width": 1600, "height": 1067},
        "body": "<h2>Nossa história</h2><p>…</p>"
    }
}
```

## Valores dos campos

Os campos são os mesmos que o painel mostra: o [conteúdo padrão](temas.md#conteúdo-o-contrato-padrão) mais o que o tema ativo adicionar.

| Tipo de campo | No JSON |
|---|---|
| `text`, `textarea`, `email`, `tel`, `url`, `color`, `select` | o texto como foi digitado (um `select` entrega a chave da opção) |
| `richtext` | HTML, já limpo quando foi salvo |
| `image` | `{url, thumb, alt, width, height}` com endereços completos, ou `null` |
| `link` | uma página do site como caminho (`"/sobre"`, `"/"` para a página inicial); qualquer outro link como foi digitado (`"https://…"`) |
| `list` | um array de objetos |
| `group` | um objeto, ou `null` quando o cliente escondeu aquela seção |

Campos de senha nunca são incluídos.

## Como usar

- **Rotas:** busque `/api/v1/pages` para montar as suas rotas (cada página tem o seu `path`) e depois `/api/v1/pages/{slug}` para cada uma. A página com `"home": true` fica em `/`.
- **Links:** um link que começa com `/` é uma página do site: passe para o seu roteador. Qualquer outro é um endereço externo.
- **Tipos de página:** `template` diz qual layout usar (`home`, `page`, `services`, `contact` ou um tipo que o seu tema adiciona).
- **Seus próprios campos:** adicione em um tema, como em [temas.md](temas.md#adicionando-seus-próprios-campos-e-tipos-de-página). O tema só precisa de `theme.json`, `theme.php`, um `layout.php` e os quatro templates obrigatórios (eles podem ser mínimos ou redirecionar para o seu front-end); o painel monta os formulários a partir dele e a API entrega os campos.
- **Cache:** as respostas podem ficar em cache por 60 segundos. Para builds estáticos, refaça o build quando o cliente publicar (por exemplo, com um build agendado).
- **Outros domínios:** a API envia `Access-Control-Allow-Origin: *`, então navegadores em qualquer domínio conseguem ler. Ela nunca usa cookies.
- **Rascunhos:** não estão na API. Só as páginas publicadas aparecem.

## Com o que você pode contar

O formato dessas respostas faz parte da [promessa de compatibilidade](compatibilidade.md): dentro de `/api/v1/`, nada é removido nem renomeado. Um teste automático congela as respostas da API de um site feito no PageBrick 1.0 e falha se alguma delas mudar. Campos novos podem aparecer; o seu código deve ignorar as chaves que não conhece.

## Endereços para plugins

Um plugin pode adicionar os próprios endereços em `/api/v1/{plugin}`:

```php
pb_add_route('GET', '/api/v1/offers', function () {
    pb_content_send(['offers' => myplugin_offers()]);     // JSON e cabeçalhos de CORS e de cache; null responde 404
});
pb_add_route('GET', '/api/v1/offers/*', function (string $slug) {
    $offer = myplugin_find($slug);
    pb_content_send($offer ? ['title' => $offer['title'], 'fields' => pb_content_json(myplugin_fields(), $offer['data'])] : null);
});
```

`pb_content_json($fields, $data)` transforma os valores salvos com as definições de campo no JSON descrito acima. Veja [core/headless.php](../../core/headless.php) e o plugin Blog para um exemplo completo.
