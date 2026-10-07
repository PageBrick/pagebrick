<div class="card">
    <h1><?= e(sprintf(__('Olá, %s!'), $user['name'])) ?></h1>
    <p><?= e(sprintf(__('Você está no painel do site %s.'), $siteTitle)) ?></p>
    <p class="muted"><?= e(__('Páginas, mídia e menus chegam na próxima etapa.')) ?></p>
    <p><a href="<?= e(pb_url('/')) ?>"><?= e(__('Ver o site')) ?></a></p>
</div>
