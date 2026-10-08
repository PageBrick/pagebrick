<?php
// Packages: plugins, themes and PageBrick itself, installed from the catalog or from a .zip, always with a backup.
//
// Trust:
// - From the catalog, a package must carry an Ed25519 signature from a key in PB_TRUSTED_KEYS (or 'trusted_keys'
//   in config.php). The signature covers type, slug, version and the file's SHA-256, so a signed package can't be
//   swapped for another one or passed off as a newer version. The catalog itself needs no signature for that reason.
// - A .zip sent by an administrator is accepted unsigned: it is their own code, like uploading by FTP.
//
// Every replaced version is zipped into content/backups first, so "Voltar versão" is just installing that zip again.
// A plugin or theme that breaks within an hour of an update is rolled back automatically (see pb_plugin_failed).

/** Public keys (base64) whose signatures are trusted: the PageBrick project key. Its private half never enters the repository. */
const PB_TRUSTED_KEYS = ['4+kXUQueEw7340wtg5X0mR4F0hQgCat/kWYXEC4xIaQ='];
const PB_CATALOG_URL = 'https://raw.githubusercontent.com/pagebrick/catalog/main/catalog.json';
const PB_CATALOG_TTL = 12 * 3600;
const PB_PACKAGE_MAX_BYTES = 30 * 1024 * 1024;
const PB_PACKAGE_MAX_UNPACKED = 150 * 1024 * 1024;
const PB_BACKUPS_KEPT = 3;
const PB_ROLLBACK_WINDOW = 3600;

function pb_themes_dir(): string
{
    return $GLOBALS['pb_config']['themes_dir'] ?? PB_ROOT . '/content/themes';
}

function pb_backups_dir(): string
{
    return $GLOBALS['pb_config']['backups_dir'] ?? PB_ROOT . '/content/backups';
}

function pb_package_dir(string $type): string
{
    return match ($type) {
        'plugin' => pb_plugins_dir(),
        'theme' => pb_themes_dir(),
    };
}

function pb_package_manifest_name(string $type): string
{
    return $type === 'theme' ? 'theme.json' : 'plugin.json';
}

// ------------------------------------------------------------------ checking and installing a .zip

/**
 * Checks a plugin or theme .zip without extracting it: one main folder (the slug) with the manifest inside,
 * no paths escaping the folder, no links, no server config files. Returns ['slug', 'manifest'].
 */
function pb_inspect_package(string $zipFile, string $type): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new InvalidArgumentException(__('O arquivo não é um .zip válido.'));
    }
    try {
        $top = null;
        $unpacked = 0;
        foreach (pb_zip_entries($zip) as $i => $name) {
            $first = explode('/', $name)[0];
            $top ??= $first;
            if ($first !== $top || !str_contains($name, '/')) {
                throw new InvalidArgumentException(__('O .zip precisa ter uma única pasta principal com o pacote dentro (compacte a pasta, não os arquivos soltos).'));
            }
            $unpacked += (int) $zip->statIndex($i)['size'];
            if ($unpacked > PB_PACKAGE_MAX_UNPACKED) {
                throw new InvalidArgumentException(__('O conteúdo do .zip é grande demais.'));
            }
        }
        if ($top === null || !preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $top)) {
            throw new InvalidArgumentException(__('O nome da pasta principal só pode ter letras minúsculas, números e hífen.'));
        }
        $manifest = json_decode((string) $zip->getFromName("$top/" . pb_package_manifest_name($type)), true);
        if (!is_array($manifest) || !is_string($manifest['name'] ?? null)) {
            throw new InvalidArgumentException(sprintf(__('Falta o arquivo %s (ou ele está com defeito) na pasta principal do .zip.'), pb_package_manifest_name($type)));
        }
        if ($type === 'plugin' && $zip->locateName("$top/plugin.php") === false) {
            throw new InvalidArgumentException(__('Falta o arquivo plugin.php.'));
        }
        if ($type === 'theme' && ($zip->locateName("$top/theme.php") === false || $zip->locateName("$top/layout.php") === false)) {
            throw new InvalidArgumentException(__('Falta o arquivo theme.php ou layout.php.'));
        }
        return ['slug' => $top, 'manifest' => $manifest];
    } finally {
        $zip->close();
    }
}

/**
 * Entry names of a zip, after refusing anything that could escape the destination or reconfigure the server.
 * Only a signed PageBrick release may carry .htaccess files ($serverConfig).
 */
function pb_zip_entries(ZipArchive $zip, bool $serverConfig = false): array
{
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        if (str_starts_with($name, '__MACOSX/') || str_ends_with($name, '/.DS_Store')) {
            continue; // noise added by macOS
        }
        if ($name === '' || $name[0] === '/' || str_contains($name, "\0") || preg_match('~(^|/)\.\.(/|$)|^[a-zA-Z]:~', $name)) {
            throw new InvalidArgumentException(__('O .zip tem caminhos inválidos.'));
        }
        if ($zip->getExternalAttributesIndex($i, $system, $attributes) && $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
            throw new InvalidArgumentException(__('O .zip contém atalhos (links), que não são permitidos.'));
        }
        if (!$serverConfig && preg_match('~(^|/)(\.htaccess|\.user\.ini|php\.ini)$~i', $name)) {
            throw new InvalidArgumentException(__('O .zip contém arquivos de configuração do servidor, que não são permitidos.'));
        }
        $names[$i] = $name;
    }
    return $names;
}

/**
 * Puts a plugin or theme .zip in place. The version it replaces is zipped into the backups first.
 * Returns ['slug', 'version', 'backup' => backup file or null].
 */
function pb_install_package(string $zipFile, string $type, ?string $expectedSlug = null, ?string $expectedVersion = null): array
{
    $info = pb_inspect_package($zipFile, $type);
    $slug = $info['slug'];
    $version = (string) ($info['manifest']['version'] ?? '');
    if (($expectedSlug !== null && $slug !== $expectedSlug) || ($expectedVersion !== null && $version !== $expectedVersion)) {
        throw new InvalidArgumentException(__('O pacote baixado não corresponde ao que o catálogo anunciou. Por segurança, ele não foi instalado.'));
    }
    return pb_with_lock(function () use ($zipFile, $type, $slug, $version) {
        $dir = pb_package_dir($type);
        $target = "$dir/$slug";
        $tmp = "$dir/.tmp-" . bin2hex(random_bytes(4));
        $zip = new ZipArchive();
        $zip->open($zipFile);
        $ok = $zip->extractTo($tmp, array_values(pb_zip_entries($zip)));
        $zip->close();
        if (!$ok || !is_dir("$tmp/$slug")) {
            pb_rmtree($tmp);
            throw new RuntimeException("Could not extract $zipFile");
        }
        $backup = null;
        if (is_dir($target)) {
            $old = json_decode((string) @file_get_contents("$target/" . pb_package_manifest_name($type)), true);
            $backup = pb_backup_folder($target, $slug, "{$type}_{$slug}_" . pb_slugify((string) ($old['version'] ?? 'antiga')));
            rename($target, "$tmp/old");
        }
        rename("$tmp/$slug", $target);
        pb_rmtree($tmp);
        pb_forget_compiled_code($target);
        pb_prune_backups("{$type}_{$slug}_");
        return ['slug' => $slug, 'version' => $version, 'backup' => $backup];
    });
}

/** Removes a plugin or theme folder (a backup is kept). */
function pb_delete_package(string $type, string $slug): void
{
    $dir = pb_package_dir($type) . "/$slug";
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug) || !is_dir($dir)) {
        throw new InvalidArgumentException(__('Pacote não encontrado.'));
    }
    pb_with_lock(function () use ($dir, $slug, $type) {
        $manifest = json_decode((string) @file_get_contents("$dir/" . pb_package_manifest_name($type)), true);
        pb_backup_folder($dir, $slug, "{$type}_{$slug}_" . pb_slugify((string) ($manifest['version'] ?? 'antiga')));
        pb_rmtree($dir);
    });
}

// ------------------------------------------------------------------ backups

/** Zips $folder as "$top/..." into the backups folder. Returns the backup file. */
function pb_backup_folder(string $folder, string $top, string $name): string
{
    $dir = pb_backups_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file = "$dir/{$name}_" . date('YmdHis') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create backup $file");
    }
    pb_zip_add_folder($zip, $folder, $top);
    $zip->close();
    return $file;
}

function pb_zip_add_folder(ZipArchive $zip, string $folder, string $prefix): void
{
    $zip->addEmptyDir($prefix);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        $relative = $prefix . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1));
        $file->isDir() ? $zip->addEmptyDir($relative) : $zip->addFile($file->getPathname(), $relative);
    }
}

/** Backups of a package, newest first: [['file', 'version', 'date']]. */
function pb_backups(string $prefix): array
{
    $list = [];
    foreach (glob(pb_backups_dir() . "/$prefix*.zip") ?: [] as $file) {
        // Name: {type}_{slug}_{version}_{date}.zip — "_" never appears in slugs or slugified versions.
        if (preg_match('/^' . preg_quote($prefix, '/') . '([a-z0-9-]+)_(\d{14})\.zip$/', basename($file), $m)) {
            $list[] = ['file' => $file, 'version' => str_replace('-', '.', $m[1]), 'date' => DateTime::createFromFormat('YmdHis', $m[2])];
        }
    }
    usort($list, fn($a, $b) => $b['date'] <=> $a['date']);
    return $list;
}

function pb_prune_backups(string $prefix): void
{
    foreach (array_slice(pb_backups($prefix), PB_BACKUPS_KEPT) as $old) {
        unlink($old['file']);
    }
}

/** "Voltar versão": reinstalls the newest backup of a plugin or theme. */
function pb_restore_package(string $type, string $slug): array
{
    $backup = pb_backups("{$type}_{$slug}_")[0] ?? throw new InvalidArgumentException(__('Não há versão anterior guardada.'));
    $result = pb_install_package($backup['file'], $type, $slug);
    unlink($backup['file']); // it's in place now; the version it replaced got its own backup
    return $result;
}

// ------------------------------------------------------------------ catalog, download and signatures

function pb_catalog_url(): string
{
    return $GLOBALS['pb_config']['catalog_url'] ?? PB_CATALOG_URL;
}

/**
 * The catalog: ['format' => 1, 'core' => entry, 'plugins' => [entries], 'themes' => [entries]].
 * Cached for 12 hours, also after a failure, so a slow catalog never slows the panel down. Null when never fetched.
 */
function pb_catalog(bool $refresh = false): ?array
{
    $cache = json_decode(pb_option('catalog_cache', 'null'), true);
    if (!$refresh && is_array($cache) && time() - $cache['fetched_at'] < PB_CATALOG_TTL) {
        return $cache['data'];
    }
    try {
        $file = pb_download(pb_catalog_url(), 2 * 1024 * 1024);
        $data = json_decode((string) file_get_contents($file), true);
        unlink($file);
        if (!is_array($data) || ($data['format'] ?? null) !== 1) {
            throw new InvalidArgumentException(__('O catálogo veio num formato desconhecido.'));
        }
        $error = null;
    } catch (InvalidArgumentException $e) {
        [$data, $error] = [$cache['data'] ?? null, $e->getMessage()];
    }
    pb_set_option('catalog_cache', json_encode(['fetched_at' => time(), 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE));
    return $data;
}

/** Why the last catalog fetch failed, or null. */
function pb_catalog_error(): ?string
{
    return json_decode(pb_option('catalog_cache', 'null'), true)['error'] ?? null;
}

function pb_catalog_entry(string $type, string $slug): ?array
{
    if ($type === 'core') {
        return pb_catalog()['core'] ?? null;
    }
    foreach (pb_catalog()[$type . 's'] ?? [] as $entry) {
        if (is_array($entry) && ($entry['slug'] ?? null) === $slug) {
            return $entry;
        }
    }
    return null;
}

/** Downloads to a temporary file. Only https, unless 'allow_insecure_urls' is set in config.php (local development). */
function pb_download(string $url, int $maxBytes): string
{
    if (!preg_match('~^https://~i', $url) && empty($GLOBALS['pb_config']['allow_insecure_urls'])) {
        throw new InvalidArgumentException(__('Endereço de download inseguro: só https é aceito.'));
    }
    $context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1, 'max_redirects' => 5, 'user_agent' => 'PageBrick/' . PB_VERSION]]);
    $data = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
    if ($data === false) {
        throw new InvalidArgumentException(__('Não consegui baixar. Verifique se o servidor tem acesso à internet e tente de novo.'));
    }
    if (strlen($data) > $maxBytes) {
        throw new InvalidArgumentException(__('O arquivo baixado é grande demais.'));
    }
    $file = tempnam(sys_get_temp_dir(), 'pb');
    file_put_contents($file, $data);
    return $file;
}

function pb_trusted_keys(): array
{
    return array_merge(PB_TRUSTED_KEYS, $GLOBALS['pb_config']['trusted_keys'] ?? []);
}

/** The exact text that gets signed for a package. */
function pb_package_message(string $type, string $slug, string $version, string $sha256): string
{
    return "pagebrick-package-v1\n$type\n$slug\n$version\n$sha256";
}

/** Throws unless $file matches the catalog entry's SHA-256 and carries a valid signature from a trusted key. */
function pb_verify_package(string $file, array $entry, string $type): void
{
    $sha256 = hash_file('sha256', $file);
    if (!is_string($entry['sha256'] ?? null) || !hash_equals(strtolower($entry['sha256']), $sha256)) {
        throw new InvalidArgumentException(__('O arquivo baixado não confere com o catálogo (pode ter sido corrompido ou alterado). Ele não foi instalado.'));
    }
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        throw new InvalidArgumentException(__('A hospedagem não tem a extensão sodium do PHP, necessária para conferir assinaturas.'));
    }
    $signature = base64_decode((string) ($entry['signature'] ?? ''), true);
    $message = pb_package_message($type, (string) ($entry['slug'] ?? $type), (string) ($entry['version'] ?? ''), $sha256);
    foreach (pb_trusted_keys() as $key) {
        $public = base64_decode($key, true);
        if ($signature !== false && strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES && $public !== false
            && strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES && sodium_crypto_sign_verify_detached($signature, $message, $public)) {
            return;
        }
    }
    throw new InvalidArgumentException(__('A assinatura digital do pacote não é válida. Por segurança, ele não foi instalado.'));
}

/** Downloads, checks and installs a plugin or theme from the catalog (also used for updates). */
function pb_install_from_catalog(string $type, string $slug): array
{
    $entry = pb_catalog_entry($type, $slug) ?? throw new InvalidArgumentException(__('Este pacote não está no catálogo.'));
    if ((int) ($entry['api'] ?? 0) !== PB_API_VERSION) {
        throw new InvalidArgumentException(__('Este pacote foi feito para outra versão do PageBrick.'));
    }
    if (!empty($entry['price'])) {
        throw new InvalidArgumentException(__('Este é um pacote pago: compre no site do autor e envie o .zip que ele entregar.'));
    }
    $file = pb_download((string) ($entry['url'] ?? ''), PB_PACKAGE_MAX_BYTES);
    try {
        pb_verify_package($file, $entry, $type);
        return pb_install_package($file, $type, $slug, (string) $entry['version']);
    } finally {
        @unlink($file);
    }
}

/** Installed version of a plugin or theme ('' when not installed). */
function pb_package_version(string $type, string $slug): string
{
    $manifest = json_decode((string) @file_get_contents(pb_package_dir($type) . "/$slug/" . pb_package_manifest_name($type)), true);
    return is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';
}

/** Newer versions in the catalog: ['core' => entry?, 'plugin' => [slug => entry], 'theme' => [slug => entry]]. Uses the cache only. */
function pb_available_updates(): array
{
    $catalog = json_decode(pb_option('catalog_cache', 'null'), true)['data'] ?? null;
    $updates = ['core' => null, 'plugin' => [], 'theme' => []];
    if (!is_array($catalog)) {
        return $updates;
    }
    if (isset($catalog['core']['version']) && version_compare((string) $catalog['core']['version'], PB_VERSION, '>')) {
        $updates['core'] = $catalog['core'];
    }
    foreach (['plugin', 'theme'] as $type) {
        foreach ($catalog[$type . 's'] ?? [] as $entry) {
            $installed = pb_package_version($type, (string) ($entry['slug'] ?? ''));
            if ($installed !== '' && version_compare((string) ($entry['version'] ?? ''), $installed, '>')) {
                $updates[$type][$entry['slug']] = $entry;
            }
        }
    }
    return $updates;
}

// ------------------------------------------------------------------ PageBrick itself

/**
 * Replaces PageBrick's own files (core/, vendor/, index.php) with those in a release .zip whose main folder is "pagebrick".
 * content/, config.php and .htaccess are never touched. The current files are zipped into the backups first.
 * Returns the backup file. Database changes run by themselves on the next request.
 */
function pb_apply_core_package(string $zipFile, string $root = PB_ROOT, string $currentVersion = PB_VERSION): string
{
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new InvalidArgumentException(__('O arquivo não é um .zip válido.'));
    }
    $entries = array_filter(pb_zip_entries($zip, true), fn($name) => preg_match('~^pagebrick/(core/|vendor/|index\.php$)~', $name));
    if (!in_array('pagebrick/core/bootstrap.php', $entries, true) || !in_array('pagebrick/index.php', $entries, true)) {
        $zip->close();
        throw new InvalidArgumentException(__('Este .zip não é uma versão do PageBrick.'));
    }
    return pb_with_lock(function () use ($zip, $entries, $root, $currentVersion) {
        $tmp = "$root/.update-" . bin2hex(random_bytes(4));
        $ok = $zip->extractTo($tmp, array_values($entries));
        $zip->close();
        if (!$ok) {
            pb_rmtree($tmp);
            throw new RuntimeException('Could not extract the update');
        }
        $backup = pb_backup_core($root, $currentVersion);
        // Swap folder by folder: each rename is instant, so visitors never get half a core.
        foreach (['core', 'vendor'] as $folder) {
            if (is_dir("$tmp/pagebrick/$folder")) {
                if (is_dir("$root/$folder")) {
                    rename("$root/$folder", "$tmp/old-$folder");
                }
                rename("$tmp/pagebrick/$folder", "$root/$folder");
            }
        }
        copy("$tmp/pagebrick/index.php", "$root/index.php");
        pb_rmtree($tmp);
        pb_forget_compiled_code("$root/core");
        pb_forget_compiled_code("$root/vendor");
        pb_prune_backups('core_');
        return $backup;
    });
}

function pb_backup_core(string $root, string $version): string
{
    $dir = pb_backups_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file = "$dir/core_" . pb_slugify($version) . '_' . date('YmdHis') . '.zip';
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addEmptyDir('pagebrick');
    foreach (['core', 'vendor'] as $folder) {
        if (is_dir("$root/$folder")) {
            pb_zip_add_folder($zip, "$root/$folder", "pagebrick/$folder");
        }
    }
    $zip->addFile("$root/index.php", 'pagebrick/index.php');
    $zip->close();
    return $file;
}

/** Where PageBrick's own files live (tests point it at a scratch copy). */
function pb_core_root(): string
{
    return $GLOBALS['pb_config']['core_root'] ?? PB_ROOT;
}

/**
 * Reasons why updating to the catalog's $entry could break this site. Empty means it's safe.
 * The catalog says which PHP the new version needs ('requires_php') and which plugin/theme API versions it keeps ('api').
 */
function pb_core_update_blockers(array $entry): array
{
    $blockers = [];
    $php = (string) ($entry['requires_php'] ?? '8.2');
    if (version_compare(PHP_VERSION, $php, '<')) {
        $blockers[] = sprintf(__('A versão nova precisa do PHP %s ou mais novo e a hospedagem usa o %s. Peça à hospedagem para atualizar o PHP (no cPanel: "Selecionar versão do PHP").'), $php, PHP_VERSION);
    }
    $apis = array_map('intval', (array) ($entry['api'] ?? [PB_API_VERSION]));
    $states = pb_plugin_states();
    foreach (pb_plugins_available() as $slug => $plugin) {
        if (!empty($states[$slug]['active']) && !in_array((int) $plugin['api'], $apis, true)) {
            $blockers[] = sprintf(__('O plugin "%s" ainda não é compatível com a versão nova. Atualize ou desative o plugin antes.'), $plugin['name']);
        }
    }
    $theme = pb_themes_available()[pb_option('theme', PB_FALLBACK_THEME)] ?? null;
    if ($theme !== null && !in_array((int) $theme['api'], $apis, true)) {
        $blockers[] = sprintf(__('O tema "%s" ainda não é compatível com a versão nova. Atualize o tema antes.'), $theme['name']);
    }
    return $blockers;
}

/**
 * Downloads, checks and applies the newest PageBrick from the catalog. Returns the backup file.
 * The new version then checks the whole site on its first request and goes back by itself if anything breaks
 * (pb_verify_core_update); index.php puts the backup back if it can't even start.
 */
function pb_update_core(): string
{
    $entry = pb_catalog_entry('core', 'core') ?? throw new InvalidArgumentException(__('O catálogo não informou nenhuma versão do PageBrick.'));
    if (!version_compare((string) ($entry['version'] ?? ''), PB_VERSION, '>')) {
        throw new InvalidArgumentException(__('O PageBrick já está na versão mais nova.'));
    }
    if ($blockers = pb_core_update_blockers($entry)) {
        throw new InvalidArgumentException(implode(' ', $blockers));
    }
    $file = pb_download((string) ($entry['url'] ?? ''), 60 * 1024 * 1024);
    try {
        pb_verify_package($file, $entry + ['slug' => 'core'], 'core');
        $backup = pb_apply_core_package($file, pb_core_root());
    } finally {
        @unlink($file);
    }
    pb_set_option('core_update', json_encode(['from' => PB_VERSION, 'to' => (string) $entry['version'], 'backup' => $backup, 'at' => time()]));
    // Read by index.php without the core: the safety net for a version that can't start at all.
    file_put_contents(pb_backups_dir() . '/update-pending.json', json_encode(['backup' => $backup, 'until' => time() + PB_ROLLBACK_WINDOW]));
    return $backup;
}

/** The update waiting to be checked by its first request, or null. */
function pb_pending_core_update(): ?array
{
    $pending = json_decode(pb_option('core_update', 'null'), true);
    return is_array($pending) ? $pending : null;
}

/**
 * Runs on the first request after an update, with the new code and the plugins already loaded.
 * Shows every published page behind the scenes; if anything breaks (or a plugin got switched off),
 * puts back the previous version and the plugin/theme states as they were. Returns the problem, or null.
 */
function pb_verify_core_update(array $statesBefore): ?string
{
    $pending = pb_pending_core_update();
    if ($pending === null) {
        return null;
    }
    if (!empty($pending['verifying'])) {
        $problem = __('a verificação foi interrompida por um erro grave');
    } else {
        pb_set_option('core_update', json_encode($pending + ['verifying' => true])); // if PHP dies during the check, the next request knows
        $problem = pb_check_site();
    }
    pb_delete_option('core_update');
    @unlink(pb_backups_dir() . '/update-pending.json');
    if ($problem !== null) {
        pb_set_option('plugins', $statesBefore['plugins']);
        pb_set_option('themes', $statesBefore['themes']);
        try {
            pb_apply_core_package($pending['backup'], pb_core_root(), $pending['to']);
        } catch (Throwable $e) {
            $problem .= ' ' . sprintf(__('(A volta automática também falhou: %s. Use "Voltar para a versão anterior" em Atualizações.)'), $e->getMessage());
        }
    }
    pb_set_option('core_update_result', json_encode(['ok' => $problem === null, 'from' => $pending['from'], 'to' => $pending['to'],
        'problem' => $problem, 'at' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE));
    return $problem;
}

/** Shows every published page, the 404 page and the sitemap without sending them. Returns the first problem, or null. */
function pb_check_site(): ?string
{
    $GLOBALS['pb_theme_testing'] = true; // a theme error must show up here, not be covered by the default theme
    try {
        foreach (pb_page_list() as $row) {
            if ($row['status'] !== 'published') {
                continue;
            }
            try {
                pb_render_page(pb_page_find((int) $row['id']));
            } catch (Throwable $e) {
                return sprintf(__('a página "%s" deu erro: %s'), $row['title'], $e->getMessage());
            }
        }
        pb_render_not_found();
        pb_sitemap_xml();
    } catch (Throwable $e) {
        return $e->getMessage();
    } finally {
        unset($GLOBALS['pb_theme_testing'], $GLOBALS['pb_current_page'], $GLOBALS['pb_page_title']);
    }
    foreach (array_keys($GLOBALS['pb_failed_plugins'] ?? []) as $slug) {
        return sprintf(__('o plugin "%s" deu erro: %s'), $slug, pb_plugin_states()[$slug]['error'] ?? '');
    }
    return null;
}

/** "Voltar versão" for PageBrick itself. */
function pb_restore_core(?string $root = null): string
{
    $backup = pb_backups('core_')[0] ?? throw new InvalidArgumentException(__('Não há versão anterior guardada.'));
    $result = pb_apply_core_package($backup['file'], $root ?? pb_core_root(), PB_VERSION);
    unlink($backup['file']);
    return $result;
}

// ------------------------------------------------------------------ helpers

/** Runs $work while holding a lock, so two people can't install or update at the same time. */
function pb_with_lock(callable $work): mixed
{
    $dir = pb_backups_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $lock = fopen("$dir/.lock", 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new InvalidArgumentException(__('Outra instalação ou atualização está em andamento. Aguarde um minuto e tente de novo.'));
    }
    try {
        return $work();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Hosts with OPcache may keep running the old version for a couple of seconds; this makes the new one count at once. */
function pb_forget_compiled_code(string $dir): void
{
    if (!function_exists('opcache_invalidate') || !is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php') {
            @opcache_invalidate($file->getPathname(), true);
        }
    }
}

function pb_rmtree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
