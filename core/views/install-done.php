<?php
/**
 * The last screen of the installer: opens the panel by itself once the browser has seen that /admin answers; if it gets a 404 (no .htaccess working), says why.
 *
 * @var bool $rulesIn  the site's .htaccess has the rules
 */
$panel = pb_url('/admin');
?>
<div class="card install">
    <h1><?= e(__('PageBrick instalado')) ?></h1>
    <p id="opening"><?= e(__('Abrindo o painel…')) ?> <noscript><a href="<?= e($panel) ?>"><?= e(__('Entrar no painel')) ?></a></noscript></p>
    <div id="rewrite-help" hidden>
        <p class="error" role="alert"><?= e(__('O endereço do painel não foi encontrado (erro 404).')) ?></p>
        <?php require __DIR__ . '/install-rules-help.php' ?>
        <p><a class="button" href="<?= e($panel) ?>"><?= e(__('Tentar de novo')) ?> →</a></p>
    </div>
</div>
<script>
    // Without following the redirect: a working /admin/login sends a signed-in person on to /admin, and following it would use up the welcome message.
    fetch(<?= json_encode(pb_url('/admin/login'), JSON_UNESCAPED_SLASHES) ?>, {cache: 'no-store', redirect: 'manual'})
        .then(r => r.status === 404 ? Promise.reject() : location.replace(<?= json_encode($panel, JSON_UNESCAPED_SLASHES) ?>))
        .catch(() => { document.getElementById('opening').hidden = true; document.getElementById('rewrite-help').hidden = false; });
</script>
