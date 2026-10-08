# Publishing: catalog, signatures and releases

How plugins, themes and PageBrick itself reach sites through the official catalog. Mostly for maintainers.

**English** · [Português](../pt-BR/publicacao.md) · [Español](../es/publicacion.md)

## How trust works

- The catalog is a public JSON file, by default `https://raw.githubusercontent.com/pagebrick/catalog/main/catalog.json`.
- Every package in it carries an **Ed25519 signature** covering its type, name, version and the SHA-256 of the file. A site installs it only if the signature matches a key it trusts (`PB_TRUSTED_KEYS` in [core/packages.php](../../core/packages.php)), so a swapped file or a fake "newer version" is refused even if the catalog itself is tampered with.
- The **private key never goes in the repository** or in any synced folder. Whoever holds it can publish code that every PageBrick site will install. Keep it on the maintainer's machine, with an offline backup.
- A `.zip` uploaded by an administrator in the panel needs no signature: it is their own code, like uploading by FTP.

## The catalog format

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

`price` and `homepage` are for paid packages: the panel shows the price and a link to the author's site instead of installing it; the buyer then uploads the .zip the author delivers. Sites cache the catalog for 12 hours.

A site can use another catalog, or trust another key, in `config.php`:

```php
'catalog_url' => 'https://example.com/catalog.json',
'trusted_keys' => ['base64 public key'],
```

## Commands

All in [tools/pagebrick.php](../../tools/pagebrick.php), run from the repository (needs PHP with `sodium` and `zip`):

```bash
# Once: create the key pair. Prints the public key for PB_TRUSTED_KEYS.
php tools/pagebrick.php keygen ~/.pagebrick/catalog.key

# A plugin or theme: zips the folder, runs the same checks a site will, signs it, prints its catalog entry.
php tools/pagebrick.php package plugin content/plugins/blog ~/.pagebrick/catalog.key https://example.com/packages

# A PageBrick release: builds pagebrick-{version}.zip (what people upload to their hosting) and,
# with a key and a download address, prints the signed "core" entry.
php tools/pagebrick.php release ./dist ~/.pagebrick/catalog.key https://github.com/pagebrick/pagebrick/releases/download/v1.0.1/pagebrick-1.0.1.zip
```

## Releasing a new version of PageBrick

1. Change `PB_VERSION` in [core/bootstrap.php](../../core/bootstrap.php) and add the version to [CHANGELOG.md](../../CHANGELOG.md).
2. Run all tests: `docker compose exec app vendor/bin/phpunit`. The compatibility tests must pass untouched.
3. Build and sign: `php tools/pagebrick.php release ./dist <key> <download-url>`.
4. Create the GitHub release `v{version}` with `pagebrick-{version}.zip` attached (the download address must match the one signed).
5. Put the printed entry under `"core"` in the catalog repository.

Sites see the new version within 12 hours, or right away with **Check now** in **System → Updates**.

## Publishing a plugin or theme in the official catalog

1. Make sure it declares `"api": 1`, works with the default content and passes activation on a fresh site.
2. Open an issue or pull request in the catalog repository with a link to the source code.
3. A maintainer packages and signs it with the project key and adds its entry.

The signature guarantees that the file is the one the maintainers published. It is not a code review: install only what you trust.
