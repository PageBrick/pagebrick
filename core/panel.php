<?php
// The frame of the panel: the icons and the navigation (the narrow bar of sections and the list of screens beside it).

/** An icon of the panel as inline SVG (Lucide, see core/assets/LUCIDE-LICENSE); an unknown name gives nothing. */
function pb_admin_icon(string $name): string
{
    static $icons = [
        'layout-grid' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
        'house' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'pencil' => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'list' => '<path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'plug' => '<path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z"/>',
        'package' => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>',
        'sliders' => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
        'layout-template' => '<rect width="18" height="7" x="3" y="3" rx="1"/><rect width="9" height="7" x="3" y="14" rx="1"/><rect width="5" height="7" x="16" y="14" rx="1"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'menu' => '<path d="M4 12h16"/><path d="M4 6h16"/><path d="M4 18h16"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'contact' => '<path d="M16 2v2"/><path d="M17.915 22a6 6 0 0 0-12 0"/><path d="M8 2v2"/><circle cx="12" cy="12" r="4"/><rect x="3" y="4" width="18" height="18" rx="2"/>',
        'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'undo' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'moon' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'circle-alert' => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
        'trash' => '<path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'grip-vertical' => '<circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/>',
        'ellipsis' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
        'copy' => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        'images' => '<path d="m22 11-1.296-1.296a2.4 2.4 0 0 0-3.408 0L11 16"/><path d="M4 8a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2"/><circle cx="13" cy="7" r="1" fill="currentColor"/><rect x="8" y="2" width="14" height="14" rx="2"/>',
        'hash' => '<line x1="4" x2="20" y1="9" y2="9"/><line x1="4" x2="20" y1="15" y2="15"/><line x1="10" x2="8" y1="3" y2="21"/><line x1="16" x2="14" y1="3" y2="21"/>',
        'quote' => '<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>',
        'megaphone' => '<path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/>',
        'columns-2' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M12 3v18"/>',
        'align-left' => '<path d="M21 5H3"/><path d="M15 12H3"/><path d="M17 19H3"/>',
        'circle-play' => '<path d="M9 9.003a1 1 0 0 1 1.517-.859l4.997 2.997a1 1 0 0 1 0 1.718l-4.997 2.997A1 1 0 0 1 9 14.996z"/><circle cx="12" cy="12" r="10"/>',
        'circle-question-mark' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
    ];
    return isset($icons[$name]) ? '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>' : '';
}

/**
 * What the navigation shows this person, from the sections (Content, Plugins, Settings: the narrow bar) to the screens
 * of each (the list beside it). 'open' marks the section whose screens the list shows: the one the person is in, or Content.
 * Also the notices an administrator should see (an update, a plugin that was switched off).
 *
 * @return array{sections: list<array>, notices: list<array{text: string, url: string}>, updates: int, broken: list<string>}
 */
function pb_admin_nav(array $user, string $current): array
{
    $admin = pb_has_role($user, 'admin');
    $link = fn(string $url, string $label, string $icon, bool $on, int $count = 0) => ['url' => $url, 'label' => $label, 'icon' => $icon, 'current' => $on, 'count' => $count];
    $in = fn(string $path) => $current === $path || str_starts_with($current, "$path/");

    $updates = $admin ? pb_available_updates() : ['core' => null, 'plugin' => [], 'theme' => []]; // from the saved copy of the catalog: no network here
    $updateCount = ($updates['core'] ? 1 : 0) + count($updates['plugin']) + count($updates['theme']);
    $names = pb_plugins_available();
    $broken = [];
    foreach ($admin ? pb_plugin_states() : [] as $slug => $state) {
        if (!empty($state['error'])) {
            $broken[] = $names[$slug]['name'] ?? $slug;
        }
    }

    $sections = [['id' => 'content', 'label' => __('Conteúdo'), 'icon' => 'layout-grid', 'dot' => false, 'items' => [
        $link('/admin', __('Início'), 'house', $current === '/admin'),
        $link('/admin/pages', __('Páginas'), 'file-text', $in('/admin/pages')),
        $link('/admin/media', __('Mídia'), 'image', $in('/admin/media')),
        $link('/admin/menus', __('Menus'), 'list', $in('/admin/menus')),
        $link('/admin/settings', __('Contato'), 'contact', $in('/admin/settings')),
    ]]];

    // What plugins add: their screens (for whoever each plugin allows), the settings of the active ones and, for administrators, managing them.
    $screens = [];
    foreach ($GLOBALS['pb_admin_pages'] ?? [] as $slug => $page) {
        if (pb_has_role($user, $page['role'])) {
            $screens[] = $link("/admin/p/$slug", $page['label'], 'plug', $current === "/admin/p/$slug");
        }
    }
    $settings = [];
    foreach (array_keys($GLOBALS['pb_plugin_settings'] ?? []) as $slug) {
        if (isset($names[$slug])) {
            $settings[] = $link("/admin/plugins/settings?plugin=$slug", sprintf(__('Configurar %s'), $names[$slug]['name']), 'sliders',
                $current === '/admin/plugins/settings' && pb_query('plugin') === $slug);
        }
    }
    $manage = $admin ? [$link('/admin/plugins', __('Gerenciar plugins'), 'package', $current === '/admin/plugins')] : [];
    if ($screens || $settings || $manage) {
        $groups = array_values(array_filter([$screens, $settings, $manage]));
        $items = [];
        foreach ($groups as $i => $group) {
            $items = array_merge($items, $i ? [['divider' => true]] : [], $group);
        }
        $sections[] = ['id' => 'plugins', 'label' => __('Plugins'), 'icon' => 'plug', 'dot' => (bool) $broken, 'items' => $items];
    }

    // The technical screens, rarely touched (administrators only).
    if ($admin) {
        $sections[] = ['id' => 'settings', 'label' => __('Configurações'), 'icon' => 'settings', 'dot' => $updateCount > 0, 'items' => [
            $link('/admin/general', __('Endereço e idiomas'), 'globe', $in('/admin/general')),
            $link('/admin/themes', __('Temas'), 'layout-template', $in('/admin/themes')),
            $link('/admin/appearance', __('Aparência'), 'palette', $in('/admin/appearance')),
            $link('/admin/updates', __('Atualizações'), 'refresh', $in('/admin/updates'), $updateCount),
            $link('/admin/email', __('E-mail'), 'mail', $in('/admin/email')),
            $link('/admin/users', __('Usuários'), 'users', $in('/admin/users')),
        ]];
    }

    $opened = false;
    foreach ($sections as &$section) {
        $section['items'] = array_values($section['items']);
        $section['url'] = ($section['items'][0]['url'] ?? '/admin');
        $section['active'] = (bool) array_filter($section['items'], fn($item) => !empty($item['current']));
        $section['open'] = $section['active'] && !$opened;
        $opened = $opened || $section['open'];
    }
    unset($section);
    $sections[0]['open'] = $sections[0]['open'] || !$opened; // not in any section (My account, for one): the list of Content

    $notices = [];
    if ($updates['core']) {
        $notices[] = ['text' => sprintf(__('O PageBrick %s está disponível.'), $updates['core']['version']), 'url' => '/admin/updates'];
    }
    if ($updates['plugin'] || $updates['theme']) {
        $notices[] = ['text' => __('Há atualizações de plugins ou temas.'), 'url' => '/admin/updates'];
    }
    if ($broken) {
        $notices[] = ['text' => sprintf(__('Plugin desligado automaticamente por erro: %s.'), implode(', ', $broken)), 'url' => '/admin/plugins'];
    }
    return ['sections' => $sections, 'notices' => $notices, 'updates' => $updateCount, 'broken' => $broken];
}

// ------------------------------------------------------------------ the bar on the site, for people signed in to the panel

/**
 * A short fingerprint of everything that shapes the site, so the bar can tell that something was saved in the panel:
 * pages, media, the site's name, theme and settings, menus. Plugins add what they own with the 'live_stamp' filter.
 */
function pb_live_stamp(): string
{
    $db = pb_db();
    $parts = [
        $db->query("SELECT COUNT(*), MAX(updated_at), SUM(status = 'published') FROM " . pb_table('pages'))->fetch(PDO::FETCH_NUM),
        $db->query('SELECT COUNT(*), MAX(id), SUM(CRC32(alt)) FROM ' . pb_table('media'))->fetch(PDO::FETCH_NUM),
        $db->query('SELECT name, value FROM ' . pb_table('options')
            . " WHERE name IN ('site_title', 'theme', 'home_page_id', 'site_mode', 'site_mode_message', 'locale', 'locales')"
            . " OR LEFT(name, 14) = 'theme_settings' OR LEFT(name, 5) = 'menu_' ORDER BY name")->fetchAll(PDO::FETCH_NUM),
        pb_apply_filters('live_stamp', ''),
    ];
    return substr(md5(json_encode($parts)), 0, 12);
}

/**
 * The discreet bar at the bottom of the site, only for people signed in to the panel (visitors get nothing): the way back
 * to the panel, a few quick actions and a live view that reloads the page when something is saved in the panel. It lives in
 * a shadow root, so the theme's CSS can't touch it. A theme or plugin turns it off with pb_add_filter('admin_bar', fn() => false).
 */
function pb_admin_bar(): string
{
    $user = pb_current_user();
    $page = $GLOBALS['pb_current_page'] ?? null;
    if (!$user || !empty($page['preview']) || !empty($GLOBALS['pb_theme_testing']) || !pb_apply_filters('admin_bar', true)) {
        return '';
    }
    $site = pb_locale();
    pb_set_locale($user['locale'] ?: $site); // the bar speaks the person's panel language, not the site's
    try {
        return pb_include(PB_ROOT . '/core/views/admin-bar.php', [
            'mode' => pb_site_mode(),
            'editUrl' => !empty($page['id']) ? pb_url('/admin/pages/edit?id=' . (int) $page['id']) : '',
            'stamp' => pb_live_stamp(),
        ]);
    } finally {
        pb_set_locale($site);
    }
}
