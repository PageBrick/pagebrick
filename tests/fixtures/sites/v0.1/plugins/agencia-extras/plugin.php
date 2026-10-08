<?php
// FROZEN: an agency plugin built on PageBrick 0.1 (see ../../README.md). Never edit.

pb_plugin_migrations([
    1 => ['CREATE TABLE ' . pb_table('agencia_leads') . ' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
    2 => ['ALTER TABLE ' . pb_table('agencia_leads') . " ADD COLUMN origin VARCHAR(50) NOT NULL DEFAULT 'site'"],
]);

pb_plugin_settings([
    'banner' => ['type' => 'text', 'label' => 'Faixa da página inicial'],
    'notify' => ['type' => 'email', 'label' => 'Avisar este e-mail'],
]);

pb_add_action('init', function () {
    pb_set_option('agencia_visits', (string) ((int) pb_option('agencia_visits', '0') + 1));
});

pb_add_filter('head_html', fn(string $html) => $html . '<meta name="agencia" content="' . e(pb_option('site_title', '')) . "\">\n");
pb_add_filter('footer_html', fn(string $html) => $html . '<script>/* AGENCIA-FOOTER */</script>');

pb_add_filter('slot:home', function (string $html, array $context = []): string {
    $banner = pb_plugin_settings_values('agencia-extras')->banner;
    return $html . '<p class="agencia-slot">AGENCIA-SLOT ' . $banner . ' ' . e(pb_apply_filters('agencia_title', 'padrão')) . '</p>';
});
pb_add_filter('agencia_title', fn(string $title) => strtoupper($title), 20);

pb_add_filter('sitemap_urls', fn(array $urls) => [...$urls, ['/agencia/oferta', '2026-01-01 00:00:00']]);
pb_add_filter('link_targets', fn(array $targets) => $targets + ['/agencia/oferta' => 'Oferta da agência']);

pb_add_route('GET', '/agencia/oferta', function () {
    pb_do_action('agencia_event', 'oferta');
    echo pb_render_in_theme(__DIR__ . '/oferta.php', ['time' => (string) time()], [
        'title' => 'Oferta da agência', 'description' => 'Uma oferta de exemplo.', 'path' => '/agencia/oferta',
    ]);
});

pb_add_route('POST', '/agencia/lead', function () {
    if (!pb_signature_valid('lead|' . pb_post('t'), pb_post('s'))) {
        http_response_code(400);
        echo 'assinatura inválida';
        return;
    }
    $email = strtolower(trim(pb_post('email')));
    pb_db()->prepare('INSERT INTO ' . pb_table('agencia_leads') . ' (email, origin) VALUES (?, ?)')->execute([$email, 'oferta']);
    $notify = pb_plugin_settings_values('agencia-extras')->notify->raw();
    if ($notify !== '') {
        pb_mail($notify, 'Novo contato da oferta', "E-mail: $email", $email);
    }
    pb_json(['ok' => true]);
});

pb_add_admin_page('agencia', 'Agência', function () {
    $count = (int) pb_db()->query('SELECT COUNT(*) FROM ' . pb_table('agencia_leads'))->fetchColumn();
    echo '<div class="card"><h1>ADMIN-AGENCIA</h1><p>' . $count . ' contatos</p>'
        . '<form method="post">' . pb_csrf_field() . '<button>Ok</button></form>'
        . pb_field_inputs(['nota' => ['type' => 'textarea', 'label' => 'Nota']], [], 'f') . '</div>';
}, 'admin');
