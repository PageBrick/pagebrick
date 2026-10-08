<div class="card">
    <h1><?= e(sprintf(__('Olá, %s!'), $user['name'])) ?></h1>
    <p><?= e(sprintf(__('Você está no painel do site %s. O que quer fazer?'), $siteTitle)) ?></p>
    <ul class="shortcuts">
        <li><a href="<?= e(pb_url('/admin/pages')) ?>"><strong><?= e(__('Editar páginas')) ?></strong><span><?= e(__('Textos, fotos e serviços do site')) ?></span></a></li>
        <li><a href="<?= e(pb_url('/admin/settings')) ?>"><strong><?= e(__('Aparência e contato')) ?></strong><span><?= e(__('Logo, cores, telefone, WhatsApp e redes sociais')) ?></span></a></li>
        <li><a href="<?= e(pb_url('/admin/media')) ?>"><strong><?= e(__('Mídia')) ?></strong><span><?= e(__('Fotos e arquivos enviados')) ?></span></a></li>
        <li><a href="<?= e(pb_url('/admin/menus')) ?>"><strong><?= e(__('Menus')) ?></strong><span><?= e(__('Os links do topo e do rodapé')) ?></span></a></li>
    </ul>
</div>
