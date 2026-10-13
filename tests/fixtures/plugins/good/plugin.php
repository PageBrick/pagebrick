<?php
pb_plugin_migrations([1 => ['CREATE TABLE ' . pb_table('good_items') . ' (id INT PRIMARY KEY)']]);
pb_plugin_settings(['greeting' => ['type' => 'text', 'label' => 'Saudação']]);
pb_add_filter('slot:test', fn(string $html) => $html . '<p>bom</p>');
pb_add_route('GET', '/bom', function () { echo 'olá do plugin'; });
pb_add_route('GET', '/bom/*', function (string $rest) { echo "resto: $rest"; });
pb_add_admin_page('bom', 'Bom', function () { echo '<p>tela do plugin</p>'; });
