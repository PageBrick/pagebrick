<form method="post" action="<?= e(pb_url('/admin/pages/edit')) ?>" class="editor">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">

    <div class="editor-main">
        <p class="muted"><a href="<?= e(pb_url('/admin/pages')) ?>">← <?= e(__('Páginas')) ?></a> · <?= e(sprintf(__('Modelo: %s'), $templateLabel)) ?>
            <?php if ($original): ?>
                · <?= e(sprintf(__('Tradução em %s de'), PB_LOCALES[$page['locale']] ?? $page['locale'])) ?>
                <a href="<?= e(pb_url('/admin/pages/edit?id=' . $original['id'])) ?>"><?= e($original['title']) ?></a>
            <?php endif ?>
            <?php foreach ($translations as $translation): ?>
                · <a href="<?= e(pb_url('/admin/pages/edit?id=' . $translation['id'])) ?>" lang="<?= e($translation['locale']) ?>"><?= e(PB_LOCALES[$translation['locale']] ?? $translation['locale']) ?></a>
            <?php endforeach ?>
        </p>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <div class="card">
            <label for="title"><?= e(__('Título da página')) ?></label>
            <input id="title" name="title" required maxlength="200" class="big" value="<?= e($page['title']) ?>">
            <?= pb_field_inputs($fields, $page['data'], 'f') ?>
        </div>

        <details class="card">
            <summary><?= e(__('Google e redes sociais (SEO)')) ?></summary>
            <label for="seo_title"><?= e(__('Título no Google')) ?></label>
            <input id="seo_title" name="seo_title" maxlength="200" value="<?= e($page['seo_title']) ?>">
            <p class="help"><?= e(__('Em branco: usa o título da página e o nome do site.')) ?></p>
            <label for="seo_description"><?= e(__('Descrição no Google')) ?></label>
            <textarea id="seo_description" name="seo_description" maxlength="300" rows="3"><?= e($page['seo_description']) ?></textarea>
            <p class="help"><?= e(__('Uma ou duas frases sobre a página. Aparece embaixo do título nos resultados de busca.')) ?></p>
            <?php if (!$fixedAddress): ?>
                <label for="slug"><?= e(__('Endereço da página')) ?></label>
                <input id="slug" name="slug" maxlength="80" value="<?= e($page['slug']) ?>">
                <p class="help"><?= e(sprintf(__('Fica assim: %s'), pb_absolute_url(pb_locale_path($page['locale'], '/' . $page['slug'])))) ?></p>
            <?php else: ?>
                <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
            <?php endif ?>
        </details>
    </div>

    <aside class="editor-side">
        <div class="card sticky">
            <?php if ($isHome): ?>
                <input type="hidden" name="status" value="published">
                <p><span class="badge"><?= e(__('Página inicial')) ?></span></p>
            <?php else: ?>
                <label for="status"><?= e(__('Situação')) ?></label>
                <select id="status" name="status">
                    <option value="published"<?= $page['status'] === 'published' ? ' selected' : '' ?>><?= e(__('Publicada')) ?></option>
                    <option value="draft"<?= $page['status'] === 'draft' ? ' selected' : '' ?>><?= e(__('Rascunho')) ?></option>
                </select>
                <p class="help"><?= e(__('Rascunho só aparece aqui no painel.')) ?></p>
            <?php endif ?>
            <button type="submit"><?= e(__('Salvar')) ?></button>
            <button type="submit" class="secondary" formaction="<?= e(pb_url('/admin/pages/preview')) ?>" formtarget="_blank" formnovalidate><?= e(__('Pré-visualizar')) ?></button>
            <?php if ($page['status'] === 'published'): ?>
                <p><a href="<?= e(pb_page_url($page)) ?>" target="_blank"><?= e(__('Ver no site')) ?></a></p>
            <?php endif ?>
        </div>
        <?php if ($revisions): ?>
            <details class="card">
                <summary><?= e(__('Histórico')) ?></summary>
                <p class="help"><?= e(__('Cada vez que você salva, a versão anterior fica guardada aqui (as últimas 10).')) ?></p>
                <ul class="revisions">
                    <?php foreach ($revisions as $r): ?>
                        <li>
                            <?= e(pb_date($r['created_at'], true)) ?>
                            <?= $r['user_name'] ? '· ' . e($r['user_name']) : '' ?>
                            <button type="submit" class="link" form="restore-<?= (int) $r['id'] ?>"><?= e(__('Restaurar')) ?></button>
                        </li>
                    <?php endforeach ?>
                </ul>
            </details>
        <?php endif ?>
    </aside>
</form>

<?php foreach ($revisions as $r): ?>
    <form id="restore-<?= (int) $r['id'] ?>" method="post" action="<?= e(pb_url('/admin/pages/restore')) ?>"
          data-confirm="<?= e(__('Restaurar esta versão? O conteúdo atual vai para o histórico.')) ?>" hidden>
        <?= pb_csrf_field() ?>
        <input type="hidden" name="revision" value="<?= (int) $r['id'] ?>">
    </form>
<?php endforeach ?>
