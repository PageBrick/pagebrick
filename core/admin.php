<?php
// The admin panel (/admin): routes and screens.

function pb_admin(string $method, string $path): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');

    // "METHOD /path" => [minimum role or null for public, handler]
    $routes = [
        'GET /admin/login' => [null, 'pb_admin_login_form'],
        'POST /admin/login' => [null, 'pb_admin_login'],
        'GET /admin/forgot' => [null, 'pb_admin_forgot_form'],
        'POST /admin/forgot' => [null, 'pb_admin_forgot'],
        'GET /admin/reset' => [null, 'pb_admin_reset_form'],
        'POST /admin/reset' => [null, 'pb_admin_reset'],
        'POST /admin/logout' => ['editor', 'pb_admin_logout'],
        'GET /admin' => ['editor', 'pb_admin_dashboard'],
        'POST /admin/site-mode' => ['admin', 'pb_admin_site_mode_save'],
        'GET /admin/pages' => ['editor', 'pb_admin_pages'],
        'POST /admin/pages' => ['editor', 'pb_admin_pages_create'],
        'GET /admin/pages/edit' => ['editor', 'pb_admin_page_edit'],
        'POST /admin/pages/edit' => ['editor', 'pb_admin_page_save'],
        'POST /admin/pages/preview' => ['editor', 'pb_admin_page_preview'],
        'POST /admin/pages/delete' => ['editor', 'pb_admin_page_delete'],
        'POST /admin/pages/home' => ['editor', 'pb_admin_page_home'],
        'POST /admin/pages/translate' => ['editor', 'pb_admin_page_translate'],
        'POST /admin/pages/restore' => ['editor', 'pb_admin_page_restore'],
        'GET /admin/media' => ['editor', 'pb_admin_media'],
        'POST /admin/media' => ['editor', 'pb_admin_media_upload'],
        'POST /admin/media/alt' => ['editor', 'pb_admin_media_alt'],
        'POST /admin/media/delete' => ['editor', 'pb_admin_media_delete'],
        'GET /admin/menus' => ['editor', 'pb_admin_menus'],
        'POST /admin/menus' => ['editor', 'pb_admin_menus_save'],
        'GET /admin/settings' => ['editor', 'pb_admin_settings'],
        'POST /admin/settings' => ['editor', 'pb_admin_settings_save'],
        'GET /admin/general' => ['admin', 'pb_admin_general'],
        'POST /admin/general' => ['admin', 'pb_admin_general_save'],
        'GET /admin/account' => ['editor', 'pb_admin_account'],
        'POST /admin/account' => ['editor', 'pb_admin_account_save'],
        'GET /admin/users' => ['admin', 'pb_admin_users'],
        'POST /admin/users' => ['admin', 'pb_admin_users_create'],
        'GET /admin/users/edit' => ['admin', 'pb_admin_user_edit'],
        'POST /admin/users/edit' => ['admin', 'pb_admin_user_save'],
        'POST /admin/users/delete' => ['admin', 'pb_admin_users_delete'],
        'GET /admin/plugins' => ['admin', 'pb_admin_plugins'],
        'POST /admin/plugins' => ['admin', 'pb_admin_plugins_action'],
        'GET /admin/plugins/settings' => ['admin', 'pb_admin_plugin_settings'],
        'POST /admin/plugins/settings' => ['admin', 'pb_admin_plugin_settings_save'],
        'POST /admin/safe-mode/exit' => ['editor', 'pb_admin_safe_mode_exit'],
        'GET /admin/themes' => ['admin', 'pb_admin_themes'],
        'POST /admin/themes' => ['admin', 'pb_admin_themes_action'],
        'GET /admin/updates' => ['admin', 'pb_admin_updates'],
        'POST /admin/updates' => ['admin', 'pb_admin_updates_action'],
        'GET /admin/email' => ['admin', 'pb_admin_email'],
        'POST /admin/email' => ['admin', 'pb_admin_email_save'],
        'POST /admin/email/test' => ['admin', 'pb_admin_email_test'],
    ];
    [$role, $handler] = $routes["$method $path"] ?? [null, 'pb_admin_not_found'];

    // Screens added by plugins: /admin/p/{slug}
    if (preg_match('~^/admin/p/([a-z0-9-]+)$~', $path, $m) && isset($GLOBALS['pb_admin_pages'][$m[1]])) {
        $role = $GLOBALS['pb_admin_pages'][$m[1]]['role'];
        $handler = fn() => pb_admin_plugin_page($m[1]);
    }

    if ($role !== null) {
        $user = pb_current_user() ?? pb_redirect('/admin/login');
        if (!pb_has_role($user, $role)) {
            http_response_code(403);
            pb_render('message', ['title' => __('Acesso negado'), 'message' => __('Sua conta não tem permissão para esta área.')]);
            return;
        }
    }
    $handler();
}

function pb_admin_not_found(): void
{
    http_response_code(404);
    pb_render('message', ['title' => __('Página não encontrada'), 'message' => __('Este endereço não existe no painel.')]);
}

function pb_current_user_id(): ?int
{
    return isset(pb_current_user()['id']) ? (int) pb_current_user()['id'] : null;
}

// ------------------------------------------------------------------ login

function pb_admin_login_form(): void
{
    if (pb_current_user()) {
        pb_redirect('/admin');
    }
    pb_render('login', ['title' => __('Entrar'), 'narrow' => true]);
}

function pb_admin_login(): void
{
    try {
        $user = pb_login(pb_post('email'), pb_post('password'), $_SERVER['REMOTE_ADDR'] ?? '');
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_render('login', ['title' => __('Entrar'), 'narrow' => true, 'error' => $e->getMessage(), 'email' => pb_post('email')]);
        return;
    }
    session_regenerate_id(true); // new session id after login: blocks session fixation
    $_SESSION['user_id'] = (int) $user['id'];
    pb_redirect('/admin');
}

function pb_admin_forgot_form(): void
{
    pb_render('forgot', ['title' => __('Esqueci minha senha'), 'narrow' => true]);
}

function pb_admin_forgot(): void
{
    try {
        pb_password_reset_request(pb_post('email'), $_SERVER['REMOTE_ADDR'] ?? '');
    } catch (InvalidArgumentException $e) {
        http_response_code(429);
        pb_render('forgot', ['title' => __('Esqueci minha senha'), 'narrow' => true, 'error' => $e->getMessage(), 'email' => pb_post('email')]);
        return;
    }
    pb_render('forgot', ['title' => __('Esqueci minha senha'), 'narrow' => true, 'sent' => true]);
}

function pb_admin_reset_form(): void
{
    header('Referrer-Policy: no-referrer'); // the link's token stays on this page
    $token = pb_query('token');
    pb_render('reset', ['title' => __('Criar uma senha nova'), 'narrow' => true, 'token' => $token, 'valid' => pb_password_reset_user($token) !== null]);
}

function pb_admin_reset(): void
{
    header('Referrer-Policy: no-referrer');
    $token = pb_post('token');
    try {
        pb_password_reset($token, pb_post('password'), pb_post('password_repeat'));
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_render('reset', ['title' => __('Criar uma senha nova'), 'narrow' => true, 'token' => $token,
            'valid' => pb_password_reset_user($token) !== null, 'error' => $e->getMessage()]);
        return;
    }
    pb_flash('ok', __('Senha nova salva. Entre com ela.'));
    pb_redirect('/admin/login');
}

function pb_admin_logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    pb_redirect('/admin/login');
}

function pb_admin_dashboard(): void
{
    pb_render('dashboard', [
        'title' => __('Painel'),
        'user' => pb_current_user(),
        'siteTitle' => pb_option('site_title', 'PageBrick'),
        'siteMode' => pb_site_mode(),
        'siteModeMessage' => pb_option('site_mode_message', ''),
    ]);
}

/** On the air, under construction or in maintenance (dashboard). */
function pb_admin_site_mode_save(): void
{
    try {
        pb_set_site_mode(pb_post('mode'), pb_post('message'));
        pb_flash('ok', [
            'live' => __('O site está no ar para todo mundo.'),
            'construction' => __('Site em construção: visitantes veem só o aviso.'),
            'maintenance' => __('Site em manutenção: visitantes veem só o aviso.'),
        ][pb_site_mode()]);
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    }
    pb_redirect('/admin');
}

// ------------------------------------------------------------------ pages

function pb_admin_pages(): void
{
    $all = pb_page_list();
    $translations = [];
    foreach ($all as $row) {
        if ($row['translation_of'] !== null) {
            $translations[(int) $row['translation_of']][$row['locale']] = $row;
        }
    }
    pb_render('pages', [
        'title' => __('Páginas'),
        'pages' => array_filter($all, fn($row) => $row['translation_of'] === null),
        'translations' => $translations,
        'locales' => array_slice(pb_site_locales(), 1),
        'templates' => array_map(fn($t) => $t['label'] ?? '', pb_theme()['templates']),
        'homeId' => pb_home_page_id(),
    ]);
}

function pb_admin_pages_create(): void
{
    try {
        $id = pb_page_create(pb_post('title'), pb_post('template'));
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
        pb_redirect('/admin/pages');
    }
    pb_flash('ok', __('Página criada como rascunho. Preencha e publique quando quiser.'));
    pb_redirect("/admin/pages/edit?id=$id");
}

function pb_admin_page_edit(?array $page = null, ?string $error = null): void
{
    $page ??= pb_page_find((int) pb_query('id'));
    if (!$page) {
        pb_admin_not_found();
        return;
    }
    pb_render('page-edit', [
        'title' => $page['title'],
        'editor' => true,
        'page' => $page,
        'fields' => pb_template_fields($page['template']),
        'templateLabel' => pb_theme()['templates'][$page['template']]['label'] ?? $page['template'],
        'isHome' => $page['id'] === pb_home_page_id(),
        'fixedAddress' => pb_page_is_home($page),
        'original' => $page['translation_of'] !== null ? pb_page_find($page['translation_of']) : null,
        'translations' => $page['translation_of'] === null
            ? array_filter(array_map(fn($l) => pb_page_translation($page, $l), array_slice(pb_site_locales(), 1))) : [],
        'revisions' => pb_page_revisions($page['id']),
        'error' => $error,
    ]);
}

function pb_admin_page_save(): void
{
    $id = (int) pb_post('id');
    try {
        pb_page_save($id, $_POST, pb_current_user_id());
    } catch (InvalidArgumentException $e) {
        $page = pb_page_find($id);
        if (!$page) {
            pb_admin_not_found();
            return;
        }
        http_response_code(422);
        // Show what the user typed, not what is saved, so nothing is lost.
        $page = array_merge($page, ['title' => pb_post('title'), 'slug' => pb_post('slug'), 'status' => pb_post('status'),
            'seo_title' => pb_post('seo_title'), 'seo_description' => pb_post('seo_description'),
            'data' => pb_collect_fields(pb_template_fields($page['template']), $_POST['f'] ?? [])]);
        pb_admin_page_edit($page, $e->getMessage());
        return;
    }
    pb_flash('ok', __('Página salva.'));
    pb_redirect("/admin/pages/edit?id=$id");
}

/** Shows the page with the form's current content, without saving. */
function pb_admin_page_preview(): void
{
    $page = pb_page_find((int) pb_post('id'));
    if (!$page) {
        pb_admin_not_found();
        return;
    }
    try {
        $page = pb_page_from_input($page, $_POST);
    } catch (InvalidArgumentException) {
        $page['data'] = pb_collect_fields(pb_template_fields($page['template']), $_POST['f'] ?? []);
    }
    $GLOBALS['pb_public_request'] = true; // rendered exactly like the site (the theme's copies of plugin templates too)
    echo pb_render_page($page, true);
}

/** Starts (or opens) the translation of a page into an extra language. */
function pb_admin_page_translate(): void
{
    try {
        $id = pb_page_translate((int) pb_post('id'), pb_post('locale'));
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
        pb_redirect('/admin/pages');
    }
    pb_flash('ok', __('Tradução criada como rascunho, com o conteúdo original. Traduza os textos e publique.'));
    pb_redirect("/admin/pages/edit?id=$id");
}

function pb_admin_page_delete(): void
{
    try {
        pb_page_delete((int) pb_post('id'));
        pb_flash('ok', __('Página excluída.'));
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    }
    pb_redirect('/admin/pages');
}

function pb_admin_page_home(): void
{
    try {
        pb_set_home_page((int) pb_post('id'));
        pb_flash('ok', __('Página inicial alterada.'));
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    }
    pb_redirect('/admin/pages');
}

function pb_admin_page_restore(): void
{
    try {
        $id = pb_page_restore((int) pb_post('revision'), pb_current_user_id());
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
        pb_redirect('/admin/pages');
    }
    pb_flash('ok', __('Versão restaurada. A versão anterior foi guardada no histórico.'));
    pb_redirect("/admin/pages/edit?id=$id");
}

// ------------------------------------------------------------------ media

function pb_admin_media(): void
{
    pb_render('media', ['title' => __('Mídia'), 'items' => pb_media_list()]);
}

/** One or more files from <input name="file[]">; answers JSON when the picker asks for it. */
function pb_admin_media_upload(): void
{
    $files = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    if (is_array($files['name'] ?? null)) {
        $files = array_map(fn($i) => array_combine(array_keys($files), array_column($files, $i)), array_keys($files['name']));
    } else {
        $files = [$files];
    }

    $ids = [];
    $errors = [];
    foreach ($files as $file) {
        try {
            $ids[] = pb_media_upload($file);
        } catch (InvalidArgumentException $e) {
            $errors[] = (is_string($file['name'] ?? null) && $file['name'] !== '' ? $file['name'] . ': ' : '') . $e->getMessage();
        }
    }

    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        if (!$ids) {
            pb_json(['error' => implode(' ', $errors)], 422);
        }
        $media = pb_media_find($ids[0]);
        pb_json(['id' => (int) $media['id'], 'thumb' => pb_media_url($media, 'thumb')]);
    }
    foreach ($errors as $error) {
        pb_flash('error', $error);
    }
    if ($ids) {
        pb_flash('ok', sprintf(__('%d arquivo(s) enviado(s).'), count($ids)));
    }
    pb_redirect('/admin/media');
}

function pb_admin_media_alt(): void
{
    pb_media_set_alt((int) pb_post('id'), pb_post('alt'));
    pb_flash('ok', __('Descrição salva.'));
    pb_redirect('/admin/media');
}

function pb_admin_media_delete(): void
{
    pb_media_delete((int) pb_post('id'));
    pb_flash('ok', __('Arquivo excluído.'));
    pb_redirect('/admin/media');
}

// ------------------------------------------------------------------ menus and settings

function pb_admin_menus(): void
{
    pb_render('menus', ['title' => __('Menus'), 'editor' => true, 'locations' => pb_theme()['menus']]);
}

function pb_admin_menus_save(): void
{
    $input = is_array($_POST['menu'] ?? null) ? $_POST['menu'] : [];
    foreach (array_keys(pb_theme()['menus']) as $location) {
        pb_save_menu($location, $input[$location] ?? []);
    }
    pb_flash('ok', __('Menus salvos.'));
    pb_redirect('/admin/menus');
}

function pb_admin_settings(?string $error = null): void
{
    pb_render('settings', [
        'title' => __('Aparência e contato'),
        'editor' => true,
        'siteTitle' => $error ? pb_post('site_title') : pb_option('site_title', ''),
        'fields' => pb_settings_fields(),
        'data' => $error ? pb_collect_fields(pb_settings_fields(), $_POST['f'] ?? []) : (json_decode(pb_option('theme_settings', '{}'), true) ?: []),
        'error' => $error,
        'translating' => in_array(pb_query('idioma'), array_slice(pb_site_locales(), 1), true) ? pb_query('idioma') : null,
    ]);
}

function pb_admin_settings_save(): void
{
    if (pb_post('translation') !== '') {
        // The texts of "Aparência e contato" in an extra language.
        try {
            pb_save_settings_translation(pb_post('translation'), $_POST['f'] ?? []);
            pb_flash('ok', __('Tradução salva.'));
        } catch (InvalidArgumentException $e) {
            pb_flash('error', $e->getMessage());
        }
        pb_redirect('/admin/settings?idioma=' . rawurlencode(pb_post('translation')));
    }
    $siteTitle = trim(pb_post('site_title'));
    if (!preg_match('/^.{1,100}$/su', $siteTitle)) {
        http_response_code(422);
        pb_admin_settings(__('Informe o nome do site (até 100 caracteres).'));
        return;
    }
    pb_set_option('site_title', $siteTitle);
    pb_save_settings($_POST['f'] ?? []);
    pb_flash('ok', __('Configurações salvas.'));
    pb_redirect('/admin/settings');
}

/** Settings → Address and languages: technical, for administrators. */
function pb_admin_general(?string $error = null): void
{
    pb_render('general', ['title' => __('Endereço e idiomas'), 'error' => $error,
        'old' => $error ? ['site_url' => pb_post('site_url'), 'locale' => pb_post('locale'), 'locales' => (array) ($_POST['locales'] ?? [])] : []]);
}

function pb_admin_general_save(): void
{
    try {
        $siteUrl = pb_validate_site_url(pb_post('site_url'));
        if (pb_post('locale') !== pb_site_locale() && isset(PB_LOCALES[pb_post('locale')])) {
            pb_set_site_locale(pb_post('locale')); // first: when it can't change, nothing else is saved either
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_admin_general($e->getMessage());
        return;
    }
    pb_set_option('site_url', $siteUrl);
    pb_set_site_locales(is_array($_POST['locales'] ?? null) ? $_POST['locales'] : []);
    pb_flash('ok', __('Configurações salvas.'));
    pb_redirect('/admin/general');
}

// ------------------------------------------------------------------ plugins

function pb_admin_plugins(): void
{
    $catalog = pb_catalog();
    pb_render('plugins', [
        'title' => __('Plugins'),
        'plugins' => pb_plugins_for_panel(),
        'states' => pb_plugin_states(),
        'withSettings' => array_keys($GLOBALS['pb_plugin_settings'] ?? []),
        'recoveryUrl' => pb_recovery_url(),
        'catalog' => $catalog['plugins'] ?? [],
        'catalogError' => pb_catalog_error(),
        'updates' => pb_available_updates()['plugin'],
    ]);
}

function pb_admin_plugins_action(): void
{
    $slug = pb_post('plugin');
    try {
        $message = match (pb_post('action')) {
            'activate' => [pb_activate_plugin($slug), __('Plugin ativado.')][1],
            'deactivate' => [pb_deactivate_plugin($slug), __('Plugin desativado.')][1],
            'dismiss' => [pb_dismiss_plugin_error($slug), __('Aviso dispensado.')][1],
            'install' => sprintf(__('Plugin "%s" instalado. Agora é só ativar.'), pb_install_from_catalog('plugin', $slug)['slug']),
            'update' => sprintf(__('Plugin atualizado para a versão %s. Se ela der erro na próxima hora, a anterior volta sozinha.'), pb_update_plugin($slug)['version']),
            'restore' => sprintf(__('Versão %s restaurada.'), pb_restore_package('plugin', $slug)['version']),
            'delete' => pb_admin_delete_plugin($slug),
            'upload' => pb_admin_upload_package('plugin'),
            'refresh' => [pb_catalog(true), pb_catalog_error() ?? __('Catálogo atualizado.')][1],
        };
        pb_flash('ok', $message);
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    } catch (UnhandledMatchError) {
        pb_flash('error', __('Ação inválida.'));
    }
    pb_redirect('/admin/plugins');
}

function pb_admin_delete_plugin(string $slug): string
{
    if (!empty(pb_plugin_states()[$slug]['active'])) {
        throw new InvalidArgumentException(__('Desative o plugin antes de excluir.'));
    }
    pb_delete_package('plugin', $slug);
    return __('Plugin excluído. Uma cópia ficou guardada nos backups; os dados que ele salvou no banco continuam lá.');
}

/** A .zip sent from the panel. Replacing an active plugin or theme gets the same one-hour automatic rollback as an update. */
function pb_admin_upload_package(string $type): string
{
    $file = $_FILES['package'] ?? [];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException(($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE
            ? sprintf(__('O arquivo passa do limite da hospedagem (%s).'), ini_get('upload_max_filesize'))
            : __('Escolha um arquivo .zip.'));
    }
    if ($file['size'] > PB_PACKAGE_MAX_BYTES) {
        throw new InvalidArgumentException(__('O arquivo é grande demais.'));
    }
    $result = pb_install_package($file['tmp_name'], $type);
    if ($result['backup'] !== null) {
        $type === 'plugin'
            ? pb_set_plugin_state($result['slug'], ['rollback_until' => time() + PB_ROLLBACK_WINDOW])
            : pb_set_theme_state($result['slug'], ['rollback_until' => time() + PB_ROLLBACK_WINDOW]);
        return sprintf(__('"%s" atualizado para a versão %s. A versão anterior ficou guardada.'), $result['slug'], $result['version']);
    }
    return sprintf(__('"%s" instalado. Agora é só ativar.'), $result['slug']);
}

// ------------------------------------------------------------------ themes

function pb_admin_themes(): void
{
    pb_render('themes', [
        'title' => __('Temas'),
        'themes' => pb_themes_available(),
        'active' => pb_option('theme', PB_FALLBACK_THEME),
        'states' => pb_theme_states(),
        'catalog' => pb_catalog()['themes'] ?? [],
        'updates' => pb_available_updates()['theme'],
    ]);
}

function pb_admin_themes_action(): void
{
    $slug = pb_post('theme');
    try {
        $message = match (pb_post('action')) {
            'activate' => [pb_activate_theme($slug), $_SESSION['pb_preview_theme'] = null, __('Tema ativado. O conteúdo do site foi mantido.')][2],
            'preview' => pb_admin_preview_theme($slug),
            'end-preview' => [$_SESSION['pb_preview_theme'] = null, __('Pré-visualização encerrada.')][1],
            'install' => sprintf(__('Tema "%s" instalado.'), pb_install_from_catalog('theme', $slug)['slug']),
            'update' => sprintf(__('Tema atualizado para a versão %s. Se ela der erro na próxima hora, a anterior volta sozinha.'), pb_update_theme($slug)['version']),
            'restore' => sprintf(__('Versão %s restaurada.'), pb_restore_package('theme', $slug)['version']),
            'delete' => pb_admin_delete_theme($slug),
            'upload' => pb_admin_upload_package('theme'),
            'dismiss' => [pb_set_theme_state($slug, ['error' => null, 'error_at' => null]), __('Aviso dispensado.')][1],
            'import-demo' => pb_admin_import_theme_demo($slug),
        };
        pb_flash('ok', $message);
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    } catch (UnhandledMatchError) {
        pb_flash('error', __('Ação inválida.'));
    }
    pb_redirect('/admin/themes');
}

function pb_admin_preview_theme(string $slug): never
{
    $theme = pb_themes_available()[$slug] ?? throw new InvalidArgumentException(__('Tema não encontrado.'));
    if (isset($theme['problem'])) {
        throw new InvalidArgumentException($theme['problem']);
    }
    $_SESSION['pb_preview_theme'] = $slug;
    pb_redirect('/');
}

/** Imports the active theme's ready-made content (demo.php): pages, photos, menus and settings. */
function pb_admin_import_theme_demo(string $slug): string
{
    $demo = $slug === pb_option('theme', PB_FALLBACK_THEME) ? pb_theme_demo() : null;
    if ($demo === null) {
        throw new InvalidArgumentException(__('Este tema não tem conteúdo para importar.'));
    }
    pb_import_content($demo, pb_theme()['dir'] . '/demo', pb_current_user_id());
    return __('Conteúdo do tema importado. As páginas que já existiam guardaram a versão anterior no histórico.');
}

function pb_admin_delete_theme(string $slug): string
{
    if ($slug === pb_option('theme', PB_FALLBACK_THEME) || $slug === PB_FALLBACK_THEME) {
        throw new InvalidArgumentException(__('Não dá para excluir o tema ativo nem o tema padrão (ele é a rede de segurança dos outros temas).'));
    }
    pb_delete_package('theme', $slug);
    return __('Tema excluído. Uma cópia ficou guardada nos backups.');
}

// ------------------------------------------------------------------ updates

function pb_admin_updates(): void
{
    pb_catalog(); // refreshes the cache when it's older than 12 hours
    $updates = pb_available_updates();
    pb_render('updates', [
        'title' => __('Atualizações'),
        'updates' => $updates,
        'blockers' => $updates['core'] ? pb_core_update_blockers($updates['core']) : [],
        'lastResult' => json_decode(pb_option('core_update_result', 'null'), true),
        'catalogError' => pb_catalog_error(),
        'checkedAt' => json_decode(pb_option('catalog_cache', 'null'), true)['fetched_at'] ?? null,
        'coreBackups' => pb_backups('core_'),
    ]);
}

function pb_admin_updates_action(): void
{
    try {
        $message = match (pb_post('action')) {
            'check' => [pb_catalog(true), pb_catalog_error() ?? __('Verificação concluída.')][1],
            'update-core' => [pb_update_core(), __('Atualização instalada. O resultado da conferência do site aparece logo abaixo.')][1],
            'restore-core' => [pb_restore_core(), __('Versão anterior do PageBrick restaurada.')][1],
        };
        pb_flash('ok', $message);
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    } catch (UnhandledMatchError) {
        pb_flash('error', __('Ação inválida.'));
    }
    pb_redirect('/admin/updates');
}

function pb_admin_plugin_settings(?string $error = null): void
{
    $slug = pb_query('plugin') ?: pb_post('plugin');
    $fields = $GLOBALS['pb_plugin_settings'][$slug] ?? null;
    if ($fields === null) {
        pb_admin_not_found();
        return;
    }
    pb_render('plugin-settings', [
        'title' => pb_plugins_available()[$slug]['name'] ?? $slug,
        'editor' => true,
        'slug' => $slug,
        'fields' => $fields,
        'data' => json_decode(pb_option("plugin_settings_$slug", '{}'), true) ?: [],
    ]);
}

function pb_admin_plugin_settings_save(): void
{
    $slug = pb_post('plugin');
    if (!isset($GLOBALS['pb_plugin_settings'][$slug])) {
        pb_admin_not_found();
        return;
    }
    pb_save_plugin_settings($slug, $_POST['f'] ?? []);
    pb_flash('ok', __('Configurações do plugin salvas.'));
    pb_redirect('/admin/plugins/settings?plugin=' . rawurlencode($slug));
}

/** A screen added by a plugin, shown inside the panel. If it breaks, the plugin is switched off and the panel goes on. */
function pb_admin_plugin_page(string $slug): void
{
    $page = $GLOBALS['pb_admin_pages'][$slug];
    ob_start();
    $ok = pb_run_plugin($page['owner'] ?? '', fn() => ($page['handler'])() ?? true, false);
    $html = ob_get_clean();
    if ($ok === false) {
        http_response_code(500);
        pb_render('message', ['title' => $page['label'], 'message' => __('Esta tela deu erro e o plugin foi desligado. Veja os detalhes em Plugins.')]);
        return;
    }
    pb_render('plugin-page', ['title' => $page['label'], 'editor' => true, 'html' => $html]);
}

function pb_admin_safe_mode_exit(): void
{
    unset($_SESSION['pb_safe_mode']);
    pb_flash('ok', __('Modo de segurança desligado. Os plugins ativos voltaram a funcionar.'));
    pb_redirect('/admin/plugins');
}

// ------------------------------------------------------------------ e-mail

function pb_admin_email(): void
{
    $settings = pb_mail_settings();
    pb_render('email', ['title' => __('E-mail'), 'fields' => pb_mail_fields(), 'data' => $settings, 'user' => pb_current_user()]);
}

function pb_admin_email_save(): void
{
    pb_save_mail_settings($_POST['f'] ?? []);
    pb_flash('ok', __('Configurações de e-mail salvas. Envie um e-mail de teste para conferir.'));
    pb_redirect('/admin/email');
}

function pb_admin_email_test(): void
{
    $user = pb_current_user();
    try {
        pb_mail($user['email'], __('Teste de e-mail do PageBrick'), __('Se você recebeu esta mensagem, o envio de e-mails do site está funcionando.'));
        pb_flash('ok', sprintf(__('E-mail de teste enviado para %s. Confira a caixa de entrada (e o spam).'), $user['email']));
    } catch (RuntimeException $e) {
        pb_flash('error', $e->getMessage());
    }
    pb_redirect('/admin/email');
}

// ------------------------------------------------------------------ users

function pb_admin_users(?string $error = null): void
{
    pb_render('users', [
        'title' => __('Usuários'),
        'users' => pb_list_users(),
        'current' => pb_current_user(),
        'error' => $error,
        'old' => $error ? ['name' => pb_post('name'), 'email' => pb_post('email'), 'role' => pb_post('role')] : [],
    ]);
}

function pb_admin_users_create(): void
{
    try {
        pb_create_user(pb_post('name'), pb_post('email'), pb_post('password'), pb_post('role'));
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_admin_users($e->getMessage());
        return;
    }
    pb_flash('ok', __('Usuário criado.'));
    pb_redirect('/admin/users');
}

function pb_admin_user_edit(?array $user = null, ?string $error = null): void
{
    $user ??= pb_find_user((int) pb_query('id'));
    if (!$user) {
        pb_admin_not_found();
        return;
    }
    if ((int) $user['id'] === pb_current_user_id()) {
        pb_redirect('/admin/account'); // your own role can't be changed here, so you never lock yourself out
    }
    pb_render('user-edit', ['title' => $user['name'], 'user' => $user, 'error' => $error]);
}

function pb_admin_user_save(): void
{
    $user = pb_find_user((int) pb_post('id'));
    if (!$user || (int) $user['id'] === pb_current_user_id()) {
        pb_admin_not_found();
        return;
    }
    try {
        pb_update_user((int) $user['id'], pb_post('name'), pb_post('email'), pb_post('role'), pb_post('password'));
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_admin_user_edit(array_merge($user, ['name' => pb_post('name'), 'email' => pb_post('email'), 'role' => pb_post('role')]), $e->getMessage());
        return;
    }
    pb_flash('ok', __('Usuário atualizado.'));
    pb_redirect('/admin/users');
}

function pb_admin_account(?string $error = null): void
{
    $user = pb_current_user();
    if ($error) {
        $user = array_merge($user, ['name' => pb_post('name'), 'email' => pb_post('email')]);
    }
    pb_render('account', ['title' => __('Minha conta'), 'user' => $user, 'error' => $error]);
}

function pb_admin_account_save(): void
{
    try {
        pb_update_own_account(pb_current_user(), pb_post('current_password'), pb_post('name'), pb_post('email'), pb_post('password'));
        pb_set_user_locale((int) pb_current_user_id(), pb_post('locale'));
        pb_set_locale(pb_post('locale') ?: pb_site_locale()); // so the confirmation already speaks the chosen language
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_admin_account($e->getMessage());
        return;
    }
    pb_flash('ok', __('Seus dados foram atualizados.'));
    pb_redirect('/admin/account');
}

function pb_admin_users_delete(): void
{
    try {
        pb_delete_user((int) pb_post('id'), (int) pb_current_user_id());
        pb_flash('ok', __('Usuário excluído.'));
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    }
    pb_redirect('/admin/users');
}
