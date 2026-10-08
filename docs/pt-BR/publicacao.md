# Publicação: catálogo, assinaturas e versões

Como os plugins, os temas e o próprio PageBrick chegam aos sites pelo catálogo oficial. Mais voltado para os mantenedores.

[English](../en/publishing.md) · **Português** · [Español](../es/publicacion.md)

## Como funciona a confiança

- O catálogo é um arquivo JSON público, por padrão `https://raw.githubusercontent.com/pagebrick/catalog/main/catalog.json`.
- Todo pacote nele leva uma **assinatura Ed25519** que cobre o tipo, o nome, a versão e o SHA-256 do arquivo. Um site só instala o pacote se a assinatura bater com uma chave em que ele confia (`PB_TRUSTED_KEYS` em [core/packages.php](../../core/packages.php)), então um arquivo trocado ou uma falsa "versão mais nova" é recusado mesmo que o próprio catálogo seja adulterado.
- A **chave privada nunca vai para o repositório** nem para nenhuma pasta sincronizada. Quem tem a chave pode publicar código que todo site PageBrick vai instalar. Guarde-a na máquina do mantenedor, com um backup offline.
- Um `.zip` enviado por um administrador no painel não precisa de assinatura: é o código dele mesmo, como enviar por FTP.

## O formato do catálogo

```json
{
    "format": 1,
    "core": {
        "version": "1.0.1", "api": [1], "requires_php": "8.2",
        "url": "https://github.com/pagebrick/pagebrick/releases/download/v1.0.1/pagebrick-1.0.1.zip",
        "sha256": "…", "signature": "…"
    },
    "plugins": [
        {
            "slug": "blog", "name": "Blog", "description": "…", "author": "PageBrick",
            "version": "1.0.0", "api": 1,
            "url": "https://…/blog-1.0.0.zip", "sha256": "…", "signature": "…",
            "price": "", "homepage": ""
        }
    ],
    "themes": []
}
```

`price` e `homepage` são para pacotes pagos: em vez de instalar, o painel mostra o preço e um link para o site do autor; depois, quem comprou envia o .zip que o autor entregou. Os sites guardam o catálogo em cache por 12 horas.

Um site pode usar outro catálogo, ou confiar em outra chave, no `config.php`:

```php
'catalog_url' => 'https://example.com/catalog.json',
'trusted_keys' => ['chave pública em base64'],
```

## Comandos

Todos em [tools/pagebrick.php](../../tools/pagebrick.php), rodados a partir do repositório (precisa de PHP com `sodium` e `zip`):

```bash
# Uma vez só: cria o par de chaves. Imprime a chave pública para o PB_TRUSTED_KEYS.
php tools/pagebrick.php keygen ~/.pagebrick/catalog.key

# Um plugin ou tema: compacta a pasta, roda as mesmas verificações que um site vai rodar, assina e imprime a entrada dele no catálogo.
php tools/pagebrick.php package plugin content/plugins/blog ~/.pagebrick/catalog.key https://example.com/packages

# Uma versão do PageBrick: gera o pagebrick-{version}.zip (o que as pessoas enviam para a hospedagem) e,
# com uma chave e um endereço de download, imprime a entrada "core" assinada.
php tools/pagebrick.php release ./dist ~/.pagebrick/catalog.key https://github.com/pagebrick/pagebrick/releases/download/v1.0.1/pagebrick-1.0.1.zip
```

## Lançando uma nova versão do PageBrick

1. Mude o `PB_VERSION` em [core/bootstrap.php](../../core/bootstrap.php) e adicione a versão ao [CHANGELOG.md](../../CHANGELOG.md).
2. Rode todos os testes: `docker compose exec app vendor/bin/phpunit`. Os testes de compatibilidade precisam passar sem nenhuma alteração.
3. Gere e assine: `php tools/pagebrick.php release ./dist <key> <download-url>`.
4. Crie a release `v{version}` no GitHub com o `pagebrick-{version}.zip` anexado (o endereço de download precisa ser o mesmo que foi assinado).
5. Coloque a entrada impressa em `"core"` no repositório do catálogo.

Os sites veem a versão nova em até 12 horas, ou na hora com **Verificar agora** em **Sistema → Atualizações**.

## Publicando um plugin ou tema no catálogo oficial

1. Confira se ele declara `"api": 1`, funciona com o conteúdo padrão e passa na ativação em um site novo.
2. Abra uma issue ou um pull request no repositório do catálogo com um link para o código-fonte.
3. Um mantenedor empacota e assina com a chave do projeto e adiciona a entrada dele.

A assinatura garante que o arquivo é o mesmo que os mantenedores publicaram. Ela não é uma revisão de código: instale só aquilo em que você confia.
