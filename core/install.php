<?php
// The web installer: runs while config.php doesn't exist.

/** Official plugins switched on in a new site, so a non-technical owner has them working from day one. */
const PB_PREINSTALLED_PLUGINS = ['contact-form'];

/** The installer's language: chosen on its screen, otherwise the browser's. */
function pb_install_locale(): string
{
    $chosen = pb_post('locale') ?: pb_query('lang');
    return isset(PB_LOCALES[$chosen]) ? $chosen : pb_browser_locale();
}

/** Problems that block installation, as messages the user can read. */
function pb_install_requirements(): array
{
    $problems = [];
    if (!extension_loaded('pdo_mysql')) {
        $problems[] = __('A extensão pdo_mysql do PHP não está ativa. Ative-a no painel da hospedagem.');
    }
    return $problems;
}

/**
 * Creates the tables, the site options and the first administrator, then writes config.php into $root.
 * Returns ['admin_id', 'written', 'php']; when the folder isn't writable, 'php' holds the config for manual creation.
 * Throws with a message the user can read.
 */
function pb_install(array $in, string $root): array
{
    $siteTitle = trim($in['site_title'] ?? '');
    if ($siteTitle === '') {
        throw new InvalidArgumentException(__('Informe o nome do site.'));
    }
    [$adminName, $adminEmail] = pb_validate_user($in['admin_name'] ?? '', $in['admin_email'] ?? '', $in['admin_password'] ?? '', 'admin');

    $db = [
        'host' => trim($in['db_host'] ?? '') ?: 'localhost',
        'name' => trim($in['db_name'] ?? ''),
        'user' => trim($in['db_user'] ?? ''),
        'pass' => $in['db_pass'] ?? '',
        'prefix' => trim($in['db_prefix'] ?? '') ?: 'pb_',
    ];
    if ($db['name'] === '' || $db['user'] === '') {
        throw new InvalidArgumentException(__('Informe o nome do banco de dados e o usuário.'));
    }
    // The prefix goes straight into SQL, so only safe characters are allowed.
    if (!preg_match('/^[A-Za-z0-9_]{1,20}$/', $db['prefix'])) {
        throw new InvalidArgumentException(__('O prefixo das tabelas só pode ter letras, números e _ (até 20).'));
    }

    try {
        $pdo = pb_db_connect($db);
    } catch (PDOException $e) {
        throw new InvalidArgumentException(__('Não consegui conectar ao banco de dados. Confira os dados.') . ' (' . $e->getMessage() . ')');
    }

    $GLOBALS['pb_config'] = ['db' => $db, 'debug' => false];
    $GLOBALS['pb_db'] = $pdo;
    try {
        if (pb_installed_version() > 0) {
            throw new InvalidArgumentException(__('Já existe um PageBrick instalado neste banco com esse prefixo de tabelas.'));
        }
        pb_migrate();
        pb_set_option('locale', pb_locale()); // the language the installer was used in becomes the site's
        pb_set_option('site_title', $siteTitle);
        if (!empty($in['site_url'])) {
            pb_set_option('site_url', $in['site_url']);
        }
        $adminId = pb_create_user($adminName, $adminEmail, $in['admin_password'], 'admin');
        pb_seed_demo(); // the owner starts from a finished example site
        foreach (PB_PREINSTALLED_PLUGINS as $slug) {
            try {
                pb_activate_plugin($slug);
            } catch (InvalidArgumentException $e) {
                error_log("PageBrick: plugin $slug not activated at install: {$e->getMessage()}");
            }
        }
    } catch (Throwable $e) {
        $GLOBALS['pb_config'] = null; // still not installed
        unset($GLOBALS['pb_db']);
        throw $e;
    }

    $php = "<?php\n// " . __('Gerado pelo instalador do PageBrick. Contém a senha do banco: não compartilhe.') . "\nreturn "
        . var_export($GLOBALS['pb_config'], true) . ";\n";
    $written = @file_put_contents($root . '/config.php', $php, LOCK_EX) !== false;
    return ['admin_id' => $adminId, 'written' => $written, 'php' => $php];
}

function pb_install_page(string $method): void
{
    $errors = pb_install_requirements();
    $fields = ['site_title', 'db_host', 'db_name', 'db_user', 'db_pass', 'db_prefix', 'admin_name', 'admin_email', 'admin_password'];
    $in = array_combine($fields, array_map('pb_post', $fields));

    if ($method === 'POST' && !$errors) {
        try {
            // The address used to reach the installer becomes the site's official address (canonical, sitemap).
            $siteUrl = (pb_is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . pb_base_path();
            $result = pb_install($in + ['site_url' => $siteUrl], PB_ROOT);
            if (!$result['written']) {
                pb_render('install-manual', ['title' => __('Quase lá'), 'php' => $result['php']]);
                return;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $result['admin_id'];
            pb_flash('ok', __('PageBrick instalado. Bem-vindo!'));
            pb_redirect('/admin');
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $errors[] = $e->getMessage();
        }
    }

    unset($in['db_pass'], $in['admin_password']); // never echo passwords back
    pb_render('install', ['title' => __('Instalar'), 'errors' => $errors, 'old' => $in, 'locale' => pb_locale()]);
}
