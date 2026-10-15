# Contributing to PageBrick

Thank you for wanting to help. PageBrick is a small CMS, and it stays small and safe because people use it, break it, translate it and fix it. You don't need to be an expert: a clear bug report or a corrected sentence is a real contribution.

**English** · the same rules apply in any language: write issues and pull requests in English, Portuguese or Spanish.

## Ways to help

- **Use it and tell us what was confusing.** Open an [issue](https://github.com/PageBrick/pagebrick/issues/new). What you tried, what you expected, what happened, and your PHP and database versions. A screenshot helps.
- **Translate the panel** into a new language (French, Italian, German...). It needs no PHP knowledge: see [Translating](#translating).
- **Improve the docs** in [docs/](docs). Fix a typo, make an example clearer, translate a page.
- **Fix a bug or add something small.** Issues labelled [good first issue](https://github.com/PageBrick/pagebrick/labels/good%20first%20issue) are meant for newcomers. For anything bigger, open an issue first so we can agree on the idea before you spend your time.

## Running it on your computer

You need Docker. From the repository folder:

```bash
docker compose up -d --build
docker compose exec app composer install
```

Open http://localhost:8080 and follow the installer (server `db`, database `pagebrick`, user `pagebrick`, password `pagebrick`). The [README](README.md#for-developers) has the details, including the local test accounts and Mailpit for e-mails.

Run all tests before you send anything:

```bash
docker compose exec app vendor/bin/phpunit
```

The same tests run on GitHub for every push and pull request, on the oldest and newest PHP and database versions PageBrick supports (PHP 8.2 to 8.4, MySQL 5.7 and 8.0, MariaDB 10.4 to 11.4).

## Branches and pull requests

- **`main` holds only published releases.** Everything the world downloads comes from `main`, and only the maintainer merges there.
- **Work goes to `develop`.** Fork the repository, branch from `develop`, and open your pull request against `develop`.
- One pull request, one idea. A short description of *why* is worth more than a long description of *what*.
- A pull request needs green tests. If you fix a bug, add a test that fails without your fix. If you add a feature, add a test for it.

## The rules that keep updates safe

PageBrick promises that clicking **Update** never breaks a site ([the compatibility promise](docs/en/compatibility.md)). A few rules protect that promise:

- **The public API is frozen.** What is listed in [core/api.php](core/api.php) is never removed or changed within an API version; you may add to it, then run `php tools/pagebrick.php api-snapshot`. Anything not listed there is internal.
- **Never edit `tests/fixtures/sites/v1.0` or `v1.0-output`.** They are a real site built on 1.0; if your change makes them fail, the change is what must be fixed.
- **Migrations only add.** Never drop or rename a table or column an older version used.
- **The HTML the core prints for themes doesn't change** unless a security fix needs it, and then it is noted in [CHANGELOG.md](CHANGELOG.md).
- **Every text the user can see has an English and a Spanish translation** (see below). A test checks it.
- Plain, procedural PHP 8.2+ in the style of the code around it. No framework, and no new runtime dependency without opening an issue first.
- Add a line to [CHANGELOG.md](CHANGELOG.md) for anything a site owner would notice.

## Translating

The texts in the code are written in Portuguese, and each language file maps the Portuguese text to its translation:

```php
'Salvar' => 'Save',
```

- The panel's texts are in [core/lang](core/lang) (`en.php` and `es.php`). The official plugins have their own `lang/` folders (`content/plugins/blog/lang`, `content/plugins/contact-form/lang`).
- To add a language, copy `en.php` to your language's name (for example `fr.php`) and translate the right-hand side, keeping the left-hand side as it is. Do the same for the plugins' `lang/` files. Partial translations are welcome: anything missing simply shows in Portuguese.
- Don't worry about the code that registers a new language: send the files and a maintainer will wire it in, or do it with you in the pull request.
- To fix a translation that is already there, edit the same files.

## The text editor

The panel's rich text editor is [TipTap](https://tiptap.dev) (on ProseMirror), built into a single file, `core/assets/editor.js`, that is committed like any other asset: running or contributing to PageBrick never needs Node. To change the editor, edit [tools/editor/src/editor.js](tools/editor/src/editor.js) and rebuild:

```bash
cd tools/editor
npm install --ignore-scripts
npm run build
```

The build also writes `core/assets/editor.LICENSE.txt` with the licences of everything inside the file. Commit both. What the editor may save is decided on the server by `pb_sanitize_html()` in [core/fields.php](core/fields.php), never by the browser.

## Reporting a security problem

Please **don't open a public issue** for a security problem. Use **Security → Report a vulnerability** on the repository page, so it can be fixed before it is public.

## License

PageBrick is released under the [MIT License](LICENSE). By sending a contribution you agree that it is published under the same license.
