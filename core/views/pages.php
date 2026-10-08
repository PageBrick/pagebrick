<?php
/**
 * @var array    $pages         main-language pages
 * @var array    $translations  page id => [locale => translation row]
 * @var string[] $locales       the site's extra languages
 */
$statuses = ['draft' => __('Rascunho'), 'published' => __('Publicada')];
$short = fn(string $locale) => strtoupper(substr($locale, 0, 2));
?>
<div class="card">
    <h1><?= e(__('Páginas')) ?></h1>
    <table>
        <thead>
            <tr>
                <th><?= e(__('Título')) ?></th><th><?= e(__('Modelo')) ?></th><th><?= e(__('Situação')) ?></th>
                <?php if ($locales): ?><th><?= e(__('Traduções')) ?></th><?php endif ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $p): $id = (int) $p['id']; ?>
            <tr>
                <td>
                    <a href="<?= e(pb_url('/admin/pages/edit?id=' . $id)) ?>"><?= e($p['title']) ?></a>
                    <?php if ($id === $homeId): ?><span class="badge"><?= e(__('Página inicial')) ?></span><?php endif ?>
                </td>
                <td><?= e($templates[$p['template']] ?? $p['template']) ?></td>
                <td><?= e($statuses[$p['status']] ?? $p['status']) ?></td>
                <?php if ($locales): ?>
                    <td class="translations">
                        <?php foreach ($locales as $locale): $t = $translations[$id][$locale] ?? null; ?>
                            <?php if ($t): ?>
                                <a href="<?= e(pb_url('/admin/pages/edit?id=' . (int) $t['id'])) ?>" class="badge<?= $t['status'] !== 'published' ? ' draft' : '' ?>"
                                   title="<?= e(PB_LOCALES[$locale] . ' · ' . ($statuses[$t['status']] ?? $t['status'])) ?>"><?= e($short($locale)) ?></a>
                            <?php else: ?>
                                <form method="post" action="<?= e(pb_url('/admin/pages/translate')) ?>">
                                    <?= pb_csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="locale" value="<?= e($locale) ?>">
                                    <button type="submit" class="link" title="<?= e(sprintf(__('Traduzir para %s'), PB_LOCALES[$locale])) ?>">+ <?= e($short($locale)) ?></button>
                                </form>
                            <?php endif ?>
                        <?php endforeach ?>
                    </td>
                <?php endif ?>
                <td class="actions">
                    <?php if ($id !== $homeId && $p['status'] === 'published'): ?>
                        <form method="post" action="<?= e(pb_url('/admin/pages/home')) ?>">
                            <?= pb_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit" class="link"><?= e(__('Tornar inicial')) ?></button>
                        </form>
                    <?php endif ?>
                    <?php if ($id !== $homeId): ?>
                        <form method="post" action="<?= e(pb_url('/admin/pages/delete')) ?>" data-confirm="<?= e(sprintf(isset($translations[$id]) ? __('Excluir a página "%s" e as traduções dela? Isso não pode ser desfeito.') : __('Excluir a página "%s"? Isso não pode ser desfeito.'), $p['title'])) ?>">
                            <?= pb_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit" class="link danger"><?= e(__('Excluir')) ?></button>
                        </form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php if ($locales): ?>
        <p class="help"><?= e(__('Traduções: clique na sigla para editar; "+" cria a tradução a partir do conteúdo original. Uma tradução em rascunho não aparece no site.')) ?></p>
    <?php endif ?>
</div>

<div class="card">
    <h2><?= e(__('Nova página')) ?></h2>
    <form method="post" action="<?= e(pb_url('/admin/pages')) ?>">
        <?= pb_csrf_field() ?>
        <label for="title"><?= e(__('Título')) ?></label>
        <input id="title" name="title" required maxlength="200">
        <label for="template"><?= e(__('Modelo')) ?></label>
        <select id="template" name="template">
            <?php foreach ($templates as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <button type="submit"><?= e(__('Criar página')) ?></button>
    </form>
</div>
