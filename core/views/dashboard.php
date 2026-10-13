<?php
/**
 * @var array  $user
 * @var string $siteTitle
 * @var string $siteMode         'live', 'construction' or 'maintenance'
 * @var string $siteModeMessage
 * @var array  $stats            published, drafts, media, languages
 * @var array  $recent           the pages edited last (translations too)
 */
$statuses = ['draft' => __('Rascunho'), 'published' => __('Publicada')];
?>
<?php if (pb_has_role($user, 'admin')):
    $updates = pb_available_updates();
    $extras = count($updates['plugin']) + count($updates['theme']); ?>
    <?php if ($updates['core'] || $extras): ?>
        <p class="flash ok update-notice">
            <?php if ($updates['core']): ?>
                <?= e(sprintf(__('O PageBrick %s está disponível.'), $updates['core']['version'])) ?>
                <?php if (pb_auto_update_allows((string) $updates['core']['version'], pb_auto_update_mode())): ?><?= e(__('Ele se instala sozinho nas próximas horas.')) ?><?php endif ?>
            <?php else: ?>
                <?= e(__('Há atualizações de plugins ou temas.')) ?>
            <?php endif ?>
            <a href="<?= e(pb_url('/admin/updates')) ?>"><?= e(__('Ver atualizações')) ?></a>
        </p>
    <?php endif ?>
<?php endif ?>
<div class="page-head">
    <div>
        <h1><?= e(sprintf(__('Olá, %s!'), $user['name'])) ?></h1>
        <p class="muted"><?= e(sprintf(__('Você está no painel do site %s. O que quer fazer?'), $siteTitle)) ?></p>
    </div>
</div>

<ul class="stats">
    <?php foreach ([
        ['/admin/pages', 'file-text', $stats['published'], __('Páginas publicadas')],
        ['/admin/pages', 'pencil', $stats['drafts'], __('Rascunhos')],
        ['/admin/media', 'image', $stats['media'], __('Mídia')],
        [pb_has_role($user, 'admin') ? '/admin/general' : '/admin/settings', 'globe', $stats['languages'], __('Idiomas')],
    ] as [$path, $icon, $number, $label]): ?>
        <li><a class="stat" href="<?= e(pb_url($path)) ?>">
            <span class="tile"><?= pb_admin_icon($icon) ?></span>
            <div><strong><?= (int) $number ?></strong><span><?= e($label) ?></span></div>
        </a></li>
    <?php endforeach ?>
</ul>

<div class="dash">
    <div class="card flush">
        <div class="card-head">
            <h2><?= e(__('Editadas recentemente')) ?></h2>
            <a href="<?= e(pb_url('/admin/pages')) ?>"><?= e(__('Ver todas')) ?></a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th><?= e(__('Título')) ?></th><th><?= e(__('Situação')) ?></th><th class="col-when"><?= e(__('Atualizada em')) ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $page): ?>
                    <tr>
                        <td>
                            <a class="row-title" href="<?= e(pb_url('/admin/pages/edit?id=' . (int) $page['id'])) ?>"><?= e($page['title']) ?></a>
                            <?php if ($page['translation_of'] !== null): ?><span class="badge" title="<?= e(PB_LOCALES[$page['locale']] ?? $page['locale']) ?>"><?= e(strtoupper(substr($page['locale'], 0, 2))) ?></span><?php endif ?>
                        </td>
                        <td><span class="pill<?= $page['status'] === 'published' ? ' ok' : '' ?>"><?= e($statuses[$page['status']] ?? $page['status']) ?></span></td>
                        <td class="muted col-when"><?= e(pb_date($page['updated_at'], true)) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card flush">
        <div class="card-head"><h2><?= e(__('Atalhos')) ?></h2></div>
        <ul class="shortcuts">
            <li><a href="<?= e(pb_url('/admin/pages')) ?>"><?= pb_admin_icon('file-text') ?><div><strong><?= e(__('Editar páginas')) ?></strong><span><?= e(__('Textos, fotos e serviços do site')) ?></span></div></a></li>
            <li><a href="<?= e(pb_url('/admin/settings')) ?>"><?= pb_admin_icon('contact') ?><div><strong><?= e(__('Contato')) ?></strong><span><?= e(__('Telefone, WhatsApp, endereço e redes sociais')) ?></span></div></a></li>
            <li><a href="<?= e(pb_url('/admin/media')) ?>"><?= pb_admin_icon('image') ?><div><strong><?= e(__('Mídia')) ?></strong><span><?= e(__('Fotos e arquivos enviados')) ?></span></div></a></li>
            <li><a href="<?= e(pb_url('/admin/menus')) ?>"><?= pb_admin_icon('list') ?><div><strong><?= e(__('Menus')) ?></strong><span><?= e(__('Os links do topo e do rodapé')) ?></span></div></a></li>
            <?php if (pb_has_role($user, 'admin')): ?>
                <li><a href="<?= e(pb_url('/admin/appearance')) ?>"><?= pb_admin_icon('palette') ?><div><strong><?= e(__('Aparência')) ?></strong><span><?= e(__('Logo, cores e estilo das letras')) ?></span></div></a></li>
            <?php endif ?>
        </ul>
    </div>
</div>

<?php
$modes = [
    'live' => [__('No ar'), __('Todo mundo vê o site.')],
    'construction' => [__('Em construção'), __('Para um site que ainda não foi lançado. Visitantes veem só um aviso; quem está logado no painel vê o site normalmente.')],
    'maintenance' => [__('Em manutenção'), __('Para uma pausa curta. Visitantes veem um aviso de que o site volta logo.')],
];
?>
<?php if (pb_has_role($user, 'admin')): ?>
<form class="card dash-mode" method="post" action="<?= e(pb_url('/admin/site-mode')) ?>">
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
<div class="card dash-mode"><p><strong><?= e(__('Situação do site')) ?>:</strong> <?= e($modes[$siteMode][0]) ?>. <?= e($modes[$siteMode][1]) ?></p></div>
<?php endif ?>
