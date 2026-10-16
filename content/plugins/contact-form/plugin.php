<?php
// Official PageBrick plugin: contact form.
// This file only registers what the plugin adds; the code lives in functions.php. Texts are translated in lang/.

require_once __DIR__ . '/functions.php';

pb_plugin_migrations([
    1 => ['CREATE TABLE ' . pb_table('contact_messages') . " (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(30) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        page_id INT UNSIGNED NULL,
        ip VARCHAR(45) NOT NULL,
        mail_error VARCHAR(500) NULL,
        read_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY ip_created (ip, created_at),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
]);

pb_plugin_settings([
    'to' => ['type' => 'email', 'label' => __('Enviar as mensagens para'), 'help' => __('Em branco: o e-mail do primeiro administrador.')],
    'phone' => ['type' => 'select', 'label' => __('Campo de telefone'), 'default' => 'optional',
        'options' => ['optional' => __('Opcional'), 'required' => __('Obrigatório'), 'hidden' => __('Não mostrar')]],
    'success' => ['type' => 'textarea', 'label' => __('Mensagem depois do envio'), 'help' => __('Em branco: "Recebemos sua mensagem e respondemos em breve."')],
    'privacy' => ['type' => 'link', 'label' => __('Página da política de privacidade'), 'help' => __('Em branco: a página de política de privacidade criada na instalação, se existir.')],
    'keep' => ['type' => 'select', 'label' => __('Apagar mensagens antigas'), 'default' => '12',
        'help' => __('Guardar dados pessoais só pelo tempo necessário é uma exigência das leis de proteção de dados, como a LGPD.'),
        'options' => ['6' => __('Depois de 6 meses'), '12' => __('Depois de 12 meses'), '24' => __('Depois de 24 meses'), '0' => __('Nunca')]],
]);

pb_add_filter('slot:contact', fn(string $html) => $html . pbcf_form());
pb_add_filter('head_html', fn(string $html) => $html . pbcf_stylesheet());
pb_add_route('POST', '/contato/enviar', 'pbcf_submit');
// In the Content list of the menu (after Contact), with an envelope: the messages people send are what the client opens most. A PageBrick whose
// pb_add_admin_page() does not know the last two arguments (before 1.6.0) ignores them and lists the screen under Plugins, where it always worked.
pb_add_admin_page('mensagens', __('Mensagens'), 'pbcf_admin', 'editor', 'content', 'mail');
// Messages nobody opened yet: a number beside "Mensagens" in the menu and a line in the bell, for whoever can open the screen. They are asked for by
// PageBrick 1.7.0 or newer ('panel_badges' and 'panel_notices'); an older one never fires those filters, so nothing happens there.
pb_add_filter('panel_badges', fn(array $badges) => $badges + ['mensagens' => pbcf_unread()]);
pb_add_filter('panel_notices', function (array $notices) {
    $unread = pbcf_unread();
    if ($unread > 0) {
        $notices[] = ['text' => $unread === 1 ? __('Você tem 1 mensagem nova do formulário de contato.') : sprintf(__('Você tem %d mensagens novas do formulário de contato.'), $unread), 'url' => '/admin/p/mensagens'];
    }
    return $notices;
});
