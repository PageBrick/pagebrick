<?php
// PageBrick maintainer tools (command line only; not part of a site's release).
//
//   php tools/pagebrick.php keygen <private-key-file>
//       Creates the signing key pair. Keep the private key OFF the repository and backed up;
//       paste the printed public key into PB_TRUSTED_KEYS (core/packages.php).
//
//   php tools/pagebrick.php package <plugin|theme> <folder> <private-key-file> <download-url-base> [out-dir]
//       Zips a plugin or theme folder, signs it and prints its catalog entry (JSON).
//
//   php tools/pagebrick.php sign-core <release.zip> <version> <private-key-file> <download-url>
//       Signs a PageBrick release .zip and prints the catalog's "core" entry.
//
//   php tools/pagebrick.php api-snapshot
//       Freezes the public API (core/api.php) into tests/fixtures/api-v{N}.json, the promise to themes and plugins.
//
//   php tools/pagebrick.php release <out-dir> [<private-key-file> <download-url>]
//       Builds pagebrick-{version}.zip, the file people upload to their hosting (and the core update in the catalog).
//       With a key, also signs it and prints the catalog's "core" entry.

if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('PB_ROOT', dirname(__DIR__));
require PB_ROOT . '/core/bootstrap.php';

function fail(string $message): never
{
    fwrite(STDERR, "$message\n");
    exit(1);
}

function private_key(string $file): string
{
    $key = base64_decode(trim((string) @file_get_contents($file)), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        fail("Not a private key: $file");
    }
    return $key;
}

function copy_tree(string $from, string $to): void
{
    @mkdir($to, 0777, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $target = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
        $item->isDir() ? @mkdir($target) : copy($item->getPathname(), $target);
    }
}

/** Signs a package file and returns its catalog entry. */
function entry(string $type, string $slug, string $version, string $zip, string $keyFile, string $url): array
{
    $sha256 = hash_file('sha256', $zip);
    $signature = sodium_crypto_sign_detached(pb_package_message($type, $slug, $version, $sha256), private_key($keyFile));
    return ['slug' => $slug, 'version' => $version, 'api' => PB_API_VERSION, 'url' => $url, 'sha256' => $sha256, 'signature' => base64_encode($signature)];
}

$args = array_slice($argv, 1);
switch ($args[0] ?? '') {
    case 'keygen':
        $file = $args[1] ?? fail('Usage: keygen <private-key-file>');
        if (file_exists($file)) {
            fail("$file already exists; refusing to overwrite a key.");
        }
        $pair = sodium_crypto_sign_keypair();
        file_put_contents($file, base64_encode(sodium_crypto_sign_secretkey($pair)) . "\n");
        @chmod($file, 0600);
        echo "Private key saved to $file\nPublic key (put it in PB_TRUSTED_KEYS):\n" . base64_encode(sodium_crypto_sign_publickey($pair)) . "\n";
        break;

    case 'package':
        [$type, $folder, $keyFile, $base] = array_slice($args, 1, 4) + [null, null, null, null];
        if (!in_array($type, ['plugin', 'theme'], true) || !is_dir((string) $folder) || !$keyFile || !$base) {
            fail('Usage: package <plugin|theme> <folder> <private-key-file> <download-url-base> [out-dir]');
        }
        $folder = rtrim(realpath($folder), '/\\');
        $slug = basename($folder);
        $manifest = json_decode((string) file_get_contents("$folder/" . pb_package_manifest_name($type)), true) ?: fail('Manifest missing or invalid.');
        $version = (string) ($manifest['version'] ?? fail('Manifest has no version.'));
        $outDir = $args[5] ?? getcwd();
        $zipFile = "$outDir/$slug-$version.zip";
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        pb_zip_add_folder($zip, $folder, $slug);
        $zip->close();
        pb_inspect_package($zipFile, $type); // the same checks a site will run
        $entry = entry($type, $slug, $version, $zipFile, $keyFile, rtrim($base, '/') . "/$slug-$version.zip");
        $entry = ['name' => $manifest['name'], 'description' => $manifest['description'] ?? '', 'author' => $manifest['author'] ?? ''] + $entry;
        fwrite(STDERR, "Wrote $zipFile\n");
        echo json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'sign-core':
        [$zipFile, $version, $keyFile, $url] = array_slice($args, 1, 4) + [null, null, null, null];
        if (!is_file((string) $zipFile) || !$version || !$keyFile || !$url) {
            fail('Usage: sign-core <release.zip> <version> <private-key-file> <download-url>');
        }
        $entry = entry('core', 'core', $version, $zipFile, $keyFile, $url);
        unset($entry['slug'], $entry['api']);
        echo json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'api-snapshot':
        // Freezes core/api.php as the promise of this API version. Refuses if the code already breaks the old promise.
        require __DIR__ . '/api.php';
        $file = PB_ROOT . '/tests/fixtures/api-v' . PB_API_VERSION . '.json';
        $now = pb_api_describe();
        if (is_file($file) && ($breaks = pb_api_breaks(json_decode((string) file_get_contents($file), true), $now))) {
            fail("The current code breaks the frozen API, so it can't be frozen again:\n- " . implode("\n- ", $breaks));
        }
        file_put_contents($file, json_encode($now, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        echo "Frozen in $file\n";
        break;

    case 'release':
        $outDir = rtrim($args[1] ?? fail('Usage: release <out-dir> [<private-key-file> <download-url>]'), '/\\');
        $build = sys_get_temp_dir() . '/pagebrick-release-' . bin2hex(random_bytes(4));
        $root = "$build/pagebrick";
        // Only what a site needs: tests, tools, docs and Docker files stay out.
        foreach (['core', 'content/themes/default', 'content/plugins/contact-form', 'content/plugins/blog'] as $folder) {
            copy_tree(PB_ROOT . "/$folder", "$root/$folder");
        }
        foreach (['index.php', '.htaccess', 'LICENSE', 'CHANGELOG.md', 'README.md', 'README.pt-BR.md', 'README.es.md', 'composer.json', 'composer.lock',
                     'content/uploads/.htaccess', 'content/backups/.htaccess'] as $file) {
            if (is_file(PB_ROOT . "/$file")) {
                @mkdir(dirname("$root/$file"), 0777, true);
                copy(PB_ROOT . "/$file", "$root/$file");
            }
        }
        passthru('composer install --no-dev --optimize-autoloader --no-interaction --quiet --working-dir=' . escapeshellarg($root), $code);
        $code === 0 || fail('composer install failed');
        unlink("$root/composer.json");
        unlink("$root/composer.lock");
        $zipFile = "$outDir/pagebrick-" . PB_VERSION . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        pb_zip_add_folder($zip, $root, 'pagebrick');
        $zip->close();
        pb_rmtree($build);
        fwrite(STDERR, "Wrote $zipFile\n");
        if (isset($args[2], $args[3])) {
            $entry = entry('core', 'core', PB_VERSION, $zipFile, $args[2], $args[3]);
            // What sites check before updating: the API versions this release still runs, and its PHP.
            $entry = ['version' => PB_VERSION, 'api' => [PB_API_VERSION], 'requires_php' => '8.2'] + $entry;
            unset($entry['slug']);
            echo json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
        break;

    default:
        fail("Commands: keygen, package, sign-core, api-snapshot. See the top of this file.");
}
