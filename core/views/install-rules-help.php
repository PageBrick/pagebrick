<?php
/**
 * What to do when the addresses of the panel give a 404 (the installer and the last screen show it): the .htaccess is missing, or the host doesn't use it.
 *
 * @var bool $rulesIn  the site's .htaccess already has the rules (the installer put them there, or they were)
 */
?>
<?php if ($rulesIn): ?>
    <p><?= e(__('O arquivo .htaccess está no lugar, mas a hospedagem não o está usando, e sem ele o painel não abre. Peça ao suporte da hospedagem para ativar o mod_rewrite e permitir arquivos .htaccess (AllowOverride All) neste site.')) ?></p>
<?php else: ?>
    <p><?= e(__('O PageBrick não conseguiu criar o arquivo .htaccess, e sem ele o painel não abre. No gerenciador de arquivos da hospedagem, mostre os arquivos ocultos (no cPanel: Configurações, "Mostrar arquivos ocultos"), abra o arquivo .htaccess da pasta do PageBrick (ou crie um, se não existir), cole as linhas abaixo no começo dele, sem apagar o que já estiver lá, e salve.')) ?></p>
    <textarea rows="12" readonly aria-label="<?= e(__('Linhas para o arquivo .htaccess')) ?>"><?= e(pb_dotfile_rules()['.htaccess']) ?></textarea>
<?php endif ?>
