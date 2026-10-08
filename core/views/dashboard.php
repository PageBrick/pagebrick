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
<?php
$modes = [
    'live' => [__('No ar'), __('Todo mundo vê o site.')],
    'construction' => [__('Em construção'), __('Para um site que ainda não foi lançado. Visitantes veem só um aviso; quem está logado no painel vê o site normalmente.')],
    'maintenance' => [__('Em manutenção'), __('Para uma pausa curta. Visitantes veem um aviso de que o site volta logo.')],
];
?>
<?php if (pb_has_role($user, 'admin')): ?>
<form class="card" method="post" action="<?= e(pb_url('/admin/site-mode')) ?>">
    <?= pb_csrf_field() ?>
    <h2><?= e(__('Situação do site')) ?></h2>
    <div class="modes">
        <?php foreach ($modes as $value => [$label, $help]): ?>
            <label class="mode">
                <input type="radio" name="mode" value="<?= $value ?>"<?= $siteMode === $value ? ' checked' : '' ?>>
                <span><strong><?= e($label) ?></strong><span><?= e($help) ?></span></span>
            </label>
        <?php endforeach ?>
    </div>
    <label for="mode-message"><?= e(__('Mensagem para os visitantes (opcional)')) ?></label>
    <textarea id="mode-message" name="message" rows="2" maxlength="500" placeholder="<?= e(__('Em branco: uma mensagem padrão, conforme a situação.')) ?>"><?= e($siteModeMessage) ?></textarea>
    <button type="submit"><?= e(__('Salvar')) ?></button>
</form>
<?php elseif ($siteMode !== 'live'): ?>
<div class="card"><p><strong><?= e(__('Situação do site')) ?>:</strong> <?= e($modes[$siteMode][0]) ?>. <?= e($modes[$siteMode][1]) ?></p></div>
<?php endif ?>
