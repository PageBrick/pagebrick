# Criando um plugin

Um plugin adiciona algo que o núcleo não faz: um formulário, um blog, um widget de agendamento, uma integração. Os plugins são pequenos de propósito: eles se ligam ao site por uma lista curta e congelada de funções, e um plugin que quebra é desligado sozinho enquanto o site continua funcionando.

[English](../en/plugins.md) · **Português** · [Español](../es/plugins.md)

Antes de escrever um plugin, veja se o tema resolve: um novo tipo de página ou campo é trabalho do tema ([temas.md](temas.md)). Plugins são para comportamento: guardar dados, responder a endereços, enviar e-mails, adicionar telas ao painel.

## Arquivos

```
content/plugins/my-plugin/
├── plugin.json         nome, versão, versão da API
├── plugin.php          roda em todas as requisições enquanto o plugin está ligado e registra o que ele adiciona
├── templates/          opcional: o que ele mostra no site (um tema pode substituir esses arquivos)
├── style.css           opcional
└── lang/en.php, es.php opcional: traduções
```

```json
{
    "name": "My plugin",
    "version": "1.0.0",
    "description": "One sentence shown on the Plugins screen.",
    "author": "Your agency",
    "api": 1,
    "i18n": {"pt-BR": {"name": "Meu plugin", "description": "…"}, "es": {"name": "Mi plugin", "description": "…"}}
}
```

O nome da pasta é o identificador do plugin (`my-plugin`: letras minúsculas, números e hífens). `"api": 1` é a API do PageBrick para a qual ele foi escrito; um plugin para outra versão da API é recusado em vez de quebrar o site. O `i18n` é opcional: o nome e a descrição em outros idiomas.

## O que um plugin pode adicionar

Tudo é registrado no `plugin.php`:

```php
<?php
// Tabelas próprias, versionadas. As migrações só adicionam: nunca apagam nem renomeiam o que uma versão anterior usava.
pb_plugin_migrations([
    1 => ['CREATE TABLE ' . pb_table('myplugin_leads') . ' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
]);

// Uma tela de configurações (Plugins → Configurar), feita com os mesmos campos dos temas.
pb_plugin_settings([
    'notify' => ['type' => 'email', 'label' => __('Enviar novos contatos para')],
]);

// HTML onde o tema chama pb_slot('home') ou pb_slot('contact').
pb_add_filter('slot:contact', fn(string $html) => $html . pb_include(__DIR__ . '/templates/form.php', []));

// Tags no <head> e antes de </body>.
pb_add_filter('head_html', fn(string $html) => $html . '<link rel="stylesheet" href="' . e(pb_plugin_url('my-plugin', 'style.css')) . "\">\n");

// Endereços públicos. '/offers/*' responde a tudo dentro de /offers/ e repassa o resto do caminho.
pb_add_route('GET', '/offers', 'myplugin_offers_page');
pb_add_route('POST', '/offers/lead', 'myplugin_save_lead');

// Endereços oferecidos nos menus e botões, e no sitemap.xml.
pb_add_filter('link_targets', fn(array $targets) => $targets + ['/offers' => __('Ofertas')]);
pb_add_filter('sitemap_urls', fn(array $urls) => [...$urls, ['/offers', '2026-10-01 00:00:00']]);

// Uma tela no painel em /admin/p/leads ('editor' ou 'admin' podem abrir).
pb_add_admin_page('leads', __('Contatos'), 'myplugin_admin', 'admin');

// Código que roda no início de cada requisição.
pb_add_action('init', fn() => null);
```

| Função | O que faz |
|---|---|
| `pb_add_action($hook, $callback, $priority = 10)` / `pb_do_action($hook, ...$args)` | rodar código em um momento; os plugins podem disparar as próprias ações |
| `pb_add_filter($hook, $callback, $priority = 10)` / `pb_apply_filters($hook, $value, ...$args)` | alterar um valor; um filtro precisa retornar o mesmo tipo que recebeu |
| `pb_add_route($method, $path, $handler)` | um endereço público; o handler imprime a resposta com `echo` |
| `pb_render_in_theme($file, $vars, ['title' => …, 'description' => …, 'path' => …])` | mostrar um template do plugin como uma página do site, dentro do layout do tema, com as tags de SEO |
| `pb_add_admin_page($slug, $label, $handler, $role = 'editor')` | uma tela do painel, sob o ícone de Plugins; o handler imprime a tela com `echo`, em GET e POST. `'editor'` deixa entrar editores e administradores, `'admin'` só administradores. Instalar, ligar, desligar e excluir plugins é sempre dos administradores |
| `pb_plugin_settings($fields)` / `pb_plugin_settings_values($slug)` | a tela de configurações e os valores dela (lidos como os campos de tema: `->notify->raw()`) |
| `pb_plugin_migrations([1 => [sql, …], 2 => …])` | as tabelas do plugin; dê nome a elas com `pb_table('myplugin_…')` |
| `pb_plugin_url($slug, $path)` | endereço de um arquivo do plugin (ou da cópia dele no tema) |
| `pb_db()`, `pb_table($name)`, `pb_option()`, `pb_set_option()` | o banco de dados (PDO; use sempre prepared statements) e pequenos valores salvos |
| `pb_mail($to, $subject, $body, $replyTo = '')` | enviar um e-mail com as configurações do site |
| `pb_json($data)`, `pb_post($name)`, `pb_query($name)` | responder em JSON; ler valores de formulário e da query string como strings |
| `pb_http($url, $headers = [], $timeout = 8, $body = null)` | uma chamada a outro serviço (só https): devolve `['status' => 200, 'body' => '…']` seja qual for o status e lança erro se não conectar; com $body vira um POST. Os testes podem responder no lugar da rede com `$GLOBALS['pb_config']['http']` |
| `pb_sign($data)`, `pb_signature_valid($data, $signature)` | proteger formulários públicos sem cookies (veja abaixo) |
| `pb_csrf_field()` | campo oculto para os formulários das suas telas no painel (o núcleo confere em todo POST do painel) |

Ganchos (hooks) disparados pelo núcleo: `init`, `head_html`, `footer_html`, `sitemap_urls`, `link_targets`, `slot:home`, `slot:contact`. A lista completa do que os plugins podem usar com segurança está em [core/api.php](../../core/api.php). Todo o resto, mesmo que comece com `pb_`, é interno e pode mudar.

## Regras que mantêm os sites seguros

- **Escape tudo o que você imprime** com `e()`. Os valores lidos por campos (`pb_plugin_settings_values()`) já são seguros quando impressos com `<?= ?>`.
- **As telas do painel** recebem proteção contra CSRF do núcleo; coloque `<?= pb_csrf_field() ?>` em todo formulário.
- **Os formulários públicos** não usam sessões (os visitantes não recebem cookies). Assine um timestamp quando mostrar o formulário e confira quando ele voltar:

  ```php
  // templates/form.php
  <?php $t = (string) time(); ?>
  <input type="hidden" name="t" value="<?= e($t) ?>">
  <input type="hidden" name="s" value="<?= e(pb_sign("my-plugin|$t")) ?>">

  // a rota do POST
  if (!pb_signature_valid('my-plugin|' . pb_post('t'), pb_post('s'))) { http_response_code(400); return; }
  ```
- **Os templates que o visitante vê** passam por `pb_include()` ou `pb_render_in_theme()`, para que os temas possam substituí-los ([temas.md](temas.md#substituindo-os-templates-de-um-plugin)). Mantenha o CSS em um `style.css` carregado com `pb_plugin_url()`, para que os temas possam substituí-lo também.
- **Use um prefixo** com o nome do seu plugin nas suas funções, tabelas e opções.

## Disjuntores

Todo trecho de código de plugin roda protegido. Se ele lançar uma exceção, retornar o tipo errado em um filtro ou derrubar o PHP com um erro fatal, só aquele plugin é desligado, o erro fica registrado e o painel mostra o que houve. O plugin também é testado quando é ativado. Na primeira hora depois de uma atualização, um plugin que quebra volta para a versão anterior em vez de ser desligado.

Se o próprio painel ficar inacessível, o **Link de socorro** mostrado na tela de Plugins (ou `'safe_mode' => true` no `config.php`) abre o site com todos os plugins desligados.

Isso protege contra plugins quebrados, não contra plugins maliciosos: instale código só de fontes em que você confia.

## Testando e entregando

1. Coloque a pasta em `content/plugins/` e ative o plugin em **Configurações → Plugins**.
2. Compacte a pasta (`my-plugin.zip` contendo `my-plugin/`) para enviar em outro site em **Configurações → Plugins → Enviar plugin (.zip)**.
3. Para oferecer o plugin no catálogo oficial, veja [publicacao.md](publicacao.md).

Os plugins oficiais em `content/plugins/` (formulário de contato e blog) são exemplos completos.
