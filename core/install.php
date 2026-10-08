<?php
// The web installer: runs while config.php doesn't exist. Four steps, like WordPress's:
//   1. language   2. before you start (what you'll need + server check)   3. database   4. site and administrator
// The database settings are kept in the installer's session between steps 3 and 4, and dropped once installed.

/** Official plugins switched on in a new site, so a non-technical owner has them working from day one. */
const PB_PREINSTALLED_PLUGINS = ['contact-form'];

/** The installer's language: chosen on its first screen (kept for the next steps), otherwise the browser's. */
function pb_install_locale(): string
{
    $chosen = pb_post('locale') ?: pb_query('lang');
    if (isset(PB_LOCALES[$chosen])) {
        $_SESSION['pb_install_locale'] = $chosen;
    }
    $locale = $_SESSION['pb_install_locale'] ?? '';
    return isset(PB_LOCALES[$locale]) ? $locale : pb_browser_locale();
}

/**
 * What the server needs, checked before installing: name => [what, ok, what to do, blocks the installation].
 * Addresses (.htaccess) are checked by the browser on the same screen (see /install-check).
 */
function pb_install_checks(): array
{
    $writable = fn(string $dir) => is_dir($dir) && is_writable($dir);
    return [
        'php' => [sprintf(__('PHP %s ou mais novo'), '8.2'), PHP_VERSION_ID >= 80200,
            sprintf(__('O servidor usa o PHP %s. No cPanel, troque em "Selecionar versão do PHP" ou "MultiPHP Manager".'), PHP_VERSION), true],
        'pdo_mysql' => [__('Conexão com MySQL (extensão pdo_mysql)'), extension_loaded('pdo_mysql'), __('Peça à hospedagem para ativar a extensão pdo_mysql do PHP.'), true],
        'gd' => [__('Tratamento de fotos (extensão gd)'), function_exists('imagecreatefromstring'), __('Peça à hospedagem para ativar a extensão gd do PHP.'), true],
        'uploads' => [__('Pasta de fotos com permissão de escrita (content/uploads)'), $writable(PB_ROOT . '/content/uploads'),
            __('No gerenciador de arquivos da hospedagem, dê permissão de escrita à pasta content/uploads (755).'), true],
        'webp' => [__('Fotos convertidas para WebP, mais leves'), function_exists('imagewebp'), __('Sem isso, as fotos ficam em JPG ou PNG. O site funciona normalmente.'), false],
        'zip' => [__('Instalar plugins, temas e atualizações pelo painel (extensão zip)'), class_exists('ZipArchive'),
            __('Sem ela, plugins, temas e atualizações precisam ser enviados por FTP.'), false],
        'sodium' => [__('Conferir a assinatura dos pacotes (extensão sodium)'), function_exists('sodium_crypto_sign_verify_detached'),
            __('Sem ela, a loja de plugins e as atualizações pelo painel não funcionam.'), false],
        'backups' => [__('Pasta de backups com permissão de escrita (content/backups)'), $writable(PB_ROOT . '/content/backups'),
            __('Sem ela, o PageBrick não consegue guardar a versão anterior antes de atualizar.'), false],
        'config' => [__('Gravar o arquivo config.php'), is_writable(PB_ROOT),
            __('Tudo bem: no fim, o instalador mostra o conteúdo do config.php para você criar o arquivo.'), false],
    ];
}

/** A database error the person installing can act on. */
function pb_install_db_error(PDOException $e, array $db): string
{
    $code = preg_match('/\[(\d{4})\]/', $e->getMessage(), $m) ? (int) $m[1] : (int) $e->getCode();
    return match ($code) {
        1045 => __('O usuário ou a senha do banco não conferem.'),
        1044 => sprintf(__('O usuário "%s" não tem permissão no banco "%s". No cPanel, em "Bancos de dados MySQL", adicione o usuário ao banco com todos os privilégios.'), $db['user'], $db['name']),
        1049 => sprintf(__('O banco "%s" não existe. Confira o nome: no cPanel ele costuma começar com o nome da sua conta, como conta_pagebrick.'), $db['name']),
        2002, 2003, 2005 => sprintf(__('Não encontrei o servidor de banco "%s". Na maioria das hospedagens ele é localhost.'), $db['host']),
        default => __('Não consegui conectar ao banco de dados. Confira os dados.') . ' (' . $e->getMessage() . ')',
    };
}

/**
 * Checks the database settings typed in the installer and connects. Returns [settings, connection].
 * Throws with a message the user can read.
 */
function pb_install_db(array $in): array
{
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
        throw new InvalidArgumentException(pb_install_db_error($e, $db));
    }
    $installed = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote(str_replace('_', '\_', $db['prefix']) . 'options'))->fetchColumn();
    if ($installed) {
        throw new InvalidArgumentException(__('Já existe um PageBrick instalado neste banco com esse prefixo de tabelas.'));
    }
    return [$db, $pdo];
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
    [$db, $pdo] = pb_install_db($in);

    $GLOBALS['pb_config'] = ['db' => $db, 'debug' => false];
    $GLOBALS['pb_db'] = $pdo;
    try {
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
    if (pb_request_path() === '/install-check') {
        // Reached only when .htaccess sends every address to index.php: the browser checks it on step 2.
        header('Content-Type: application/json');
        echo '{"rewrite": true}';
        return;
    }
    $checks = pb_install_checks();
    $blocked = (bool) array_filter($checks, fn($check) => $check[3] && !$check[1]);
    $step = max(1, min(4, (int) pb_query('step') ?: 1));
    if ($step > 2 && $blocked) {
        $step = 2;
    }
    $errors = [];
    $old = [];

    if ($step === 3 && $method === 'POST') {
        $fields = ['db_host', 'db_name', 'db_user', 'db_pass', 'db_prefix'];
        $in = array_combine($fields, array_map('pb_post', $fields));
        try {
            [$_SESSION['pb_install_db']] = pb_install_db($in);
            $step = 4;
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $errors[] = $e->getMessage();
            $old = $in;
        }
    } elseif ($step === 4 && empty($_SESSION['pb_install_db'])) {
        $step = 3; // the session expired, or someone jumped ahead
    } elseif ($step === 4 && $method === 'POST') {
        $fields = ['site_title', 'admin_name', 'admin_email', 'admin_password'];
        $in = array_combine($fields, array_map('pb_post', $fields));
        $db = $_SESSION['pb_install_db'];
        try {
            // The address used to reach the installer becomes the site's official address (canonical, sitemap).
            $siteUrl = (pb_is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . pb_base_path();
            $result = pb_install($in + ['db_host' => $db['host'], 'db_name' => $db['name'], 'db_user' => $db['user'],
                'db_pass' => $db['pass'], 'db_prefix' => $db['prefix'], 'site_url' => $siteUrl], PB_ROOT);
            unset($_SESSION['pb_install_db'], $_SESSION['pb_install_locale']);
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
            $old = $in;
        }
    }

    unset($old['db_pass'], $old['admin_password']); // never echo passwords back
    pb_render('install', ['title' => __('Instalar o PageBrick'), 'step' => $step, 'checks' => $checks, 'blocked' => $blocked,
        'errors' => $errors, 'old' => $old, 'locale' => pb_locale()]);
}
