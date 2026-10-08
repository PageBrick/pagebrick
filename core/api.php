<?php
// The public API: everything themes and plugins may rely on. It is a promise.
//
// Within one API version (PB_API_VERSION), nothing listed here is removed or changed in a way that breaks
// existing code: no function or method disappears, no parameter is renamed, reordered or made required,
// no return type changes, no hook stops being called, no standard content field disappears or changes type.
// New things may be added (new functions, new optional parameters at the end, new hooks, new fields).
//
// tests/fixtures/api-v1.json is the frozen copy of this list as released; CompatibilityTest fails if the
// code stops keeping the promise. After adding something here, freeze it with:
//   php tools/pagebrick.php api-snapshot
// Anything NOT listed here (functions starting with pb_ included) is internal and may change.

return [
    'functions' => [
        // helpers
        'e', '__', 'pb_url', 'pb_absolute_url', 'pb_base_path', 'pb_request_path', 'pb_is_https', 'pb_redirect',
        'pb_post', 'pb_query', 'pb_json', 'pb_limit', 'pb_include', 'pb_flash',
        'pb_csrf_token', 'pb_csrf_field', 'pb_csrf_valid', 'pb_sign', 'pb_signature_valid',
        // languages: pb_locale() for <html lang>, pb_date() writes dates the way the site language does
        'pb_locale', 'pb_site_locale', 'pb_date', 'pb_site_locales', 'pb_content_locale', 'pb_language_links', 'pb_page_translation', 'pb_locale_path',
        // data
        'pb_db', 'pb_table', 'pb_option', 'pb_set_option',
        // users
        'pb_current_user', 'pb_has_role', 'pb_is_logged_in',
        // fields
        'pb_collect_fields', 'pb_field_inputs', 'pb_sanitize_html', 'pb_clean_url',
        // content
        'pb_page_find', 'pb_page_by_slug', 'pb_page_list', 'pb_page_url', 'pb_home_page_id', 'pb_link_url',
        'pb_template_fields', 'pb_settings', 'pb_menu', 'pb_menu_html', 'pb_slugify', 'pb_validate_page_title', 'pb_slug_reserved',
        'pb_media_find', 'pb_media_url',
        // themes
        'pb_head', 'pb_footer', 'pb_slot', 'pb_theme_url', 'pb_render_in_theme', 'pb_render_not_found',
        'pb_whatsapp_url', 'pb_map_url', 'pb_contrast', 'pb_text_color_on', 'pb_readable_color',
        // plugins
        'pb_add_action', 'pb_add_filter', 'pb_do_action', 'pb_apply_filters', 'pb_add_route', 'pb_add_admin_page',
        // content API (core/headless.php): plugins answer /api/v1/{plugin} with these
        'pb_content_json', 'pb_content_send',
        'pb_plugin_settings', 'pb_plugin_settings_values', 'pb_plugin_migrations', 'pb_plugin_url',
        // e-mail
        'pb_mail',
    ],
    'classes' => ['PbGroup', 'PbList', 'PbValue'],
    'constants' => ['PB_VERSION', 'PB_API_VERSION'],
    // Hooks the core fires. Themes are expected to call pb_slot('home') and pb_slot('contact') in those templates.
    'hooks' => ['init', 'head_html', 'footer_html', 'sitemap_urls', 'link_targets'],
];
