<?php
/**
 * The page editor: the form on the left (the sections can be dragged), the page as visitors will see it on the right, live.
 * Changes wait in a draft, saved by themselves, until someone clicks Publish (core/assets/studio.js).
 *
 * @var array  $page       the page as it is being edited: the draft over the live content ('has_draft' says whether there is one)
 * @var array  $sections   what can be dragged: name => ['label' => ..., 'group' => the field's definition or null]
 * @var array  $fields     the template's other fields
 * @var string $draftAt    when the draft was saved
 */
$id = (int) $page['id'];
$published = $page['status'] === 'published';
$state = !$published ? 'draft' : ($page['has_draft'] ? 'changes' : 'live');
$states = [
    'draft' => __('Rascunho: ainda não está no site'),
    'changes' => __('Publicada · com alterações não publicadas'),
    'live' => __('Publicada · no site'),
];
?>
<form method="post" action="<?= e(pb_url('/admin/pages/edit')) ?>" id="page-form" class="studio-form" data-studio data-state="<?= e($state) ?>"
      data-id="<?= $id ?>" data-preview-url="<?= e(pb_url('/admin/pages/preview')) ?>" data-autosave-url="<?= e(pb_url('/admin/pages/autosave')) ?>"
      data-texts="<?= e(json_encode([
          'saving' => __('Salvando…'),
          'saved' => __('Rascunho salvo às %s'),
          'savedPage' => __('Salvo às %s'),
          'failed' => __('Não foi possível salvar: %s'),
          'offline' => __('Sem conexão. Tente de novo.'),
          'refused' => __('A hospedagem recusou o envio (erro %s). Costuma ser o filtro de segurança dela: um texto que parece código ou um comando, como "<?php" ou "curl https://…", pode causar isso. Mude ou apague esse texto e tente de novo.'),
          'moved' => __('"%1$s" agora é a seção %2$s de %3$s'),
          'loading' => __('Atualizando a página…'),
          'pieceAdded' => __('Seção "%s" adicionada'),
          'pieceCopied' => __('Seção "%s" duplicada'),
          'pieceRemoved' => __('Seção "%s" removida'),
          'pieceBack' => __('Seção "%s" de volta'),
          'undo' => __('Desfazer'),
          'changes' => $states['changes'],
          'live' => $states['live'],
      ], JSON_UNESCAPED_UNICODE)) ?>">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="studio-bar">
        <div class="studio-crumb">
            <a href="<?= e(pb_url('/admin/pages')) ?>" class="studio-back" title="<?= e(__('Páginas')) ?>"><?= pb_admin_icon('arrow-left') ?><span><?= e(__('Páginas')) ?></span></a>
            <h1 class="studio-title" title="<?= e($page['title']) ?>"><?= e($page['title']) ?></h1>
            <span class="pill <?= $state === 'live' ? 'ok' : ($state === 'changes' ? 'warn' : '') ?>" data-state-pill><?= e($states[$state]) ?></span>
            <?php if ($isHome): ?><span class="pill info"><?= e(__('Página inicial')) ?></span><?php endif ?>
        </div>
        <p class="studio-note" role="status" aria-live="polite" data-save-note><?= $state === 'changes' && $draftAt !== '' ? e(sprintf(__('Rascunho salvo em %s'), $draftAt)) : '' ?></p>
        <div class="studio-actions">
            <button type="submit" class="secondary" name="action" value="draft"><?= e(__('Salvar rascunho')) ?></button>
            <button type="submit" name="action" value="publish" data-publish><?= pb_admin_icon('send') ?><?= e($published ? __('Publicar alterações') : __('Publicar')) ?></button>
            <details class="menu studio-more">
                <summary class="icon-btn" title="<?= e(__('Mais ações')) ?>" aria-label="<?= e(__('Mais ações')) ?>"><?= pb_admin_icon('ellipsis') ?></summary>
                <div>
                    <button type="submit" class="menu-item" formaction="<?= e(pb_url('/admin/pages/preview')) ?>" formtarget="_blank" formnovalidate><?= pb_admin_icon('external') ?><?= e(__('Pré-visualizar em outra aba')) ?></button>
                    <?php if ($published): ?>
                        <a class="menu-item" href="<?= e(pb_page_url($page)) ?>" target="_blank" rel="noopener"><?= pb_admin_icon('eye') ?><?= e(__('Ver no site')) ?></a>
                        <button type="submit" class="menu-item danger" name="action" value="discard" data-discard formnovalidate
                                data-confirm="<?= e(__('Descartar as alterações que ainda não foram publicadas? A página volta à versão que está no site.')) ?>"<?= $page['has_draft'] ? '' : ' hidden' ?>><?= pb_admin_icon('undo') ?><?= e(__('Descartar alterações não publicadas')) ?></button>
                        <?php if (!$isHome): ?>
                            <button type="submit" class="menu-item danger" name="action" value="unpublish" formnovalidate
                                    data-confirm="<?= e(__('Tirar esta página do site? Ela vira rascunho e deixa de aparecer para os visitantes.')) ?>"><?= pb_admin_icon('lock') ?><?= e(__('Tirar do ar (voltar a rascunho)')) ?></button>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </details>
        </div>
    </div>

    <div class="studio-tabs" role="tablist" aria-label="<?= e(__('Editar ou ver a página')) ?>">
        <button type="button" role="tab" aria-selected="true" data-tab="edit"><?= e(__('Editar')) ?></button>
        <button type="button" role="tab" aria-selected="false" data-tab="preview"><?= e(__('Ver a página')) ?></button>
    </div>

    <div class="studio-body" data-view="edit">
        <div class="studio-edit thin-scroll">
            <p class="muted studio-meta"><?= e(sprintf(__('Modelo: %s'), $templateLabel)) ?>
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

            <?php if ($sections || $pieceTypes): ?>
                <section class="pb-sections-box" aria-labelledby="sections-title" data-sections-box>
                    <h2 id="sections-title"><?= e(__('Seções da página')) ?></h2>
                    <p class="help"><?= e($pieceTypes ? __('Arraste pela alça para mudar a ordem, ou use + para adicionar uma seção. A página ao lado mostra o resultado na hora.') : __('Arraste pela alça para mudar a ordem. A página ao lado mostra o resultado na hora.')) ?></p>
                    <div class="pb-sections" data-sections data-sortable>
                        <?php foreach ($sections as $name => $section): ?>
                            <?php if ($section['piece']): ?>
                                <?= pb_piece_row($section['piece']['id'], $section['piece']['type'], $section['piece']['data']) ?>
                            <?php elseif ($section['group']): ?>
                                <?= pb_group_input($section['group'], is_array($page['data'][$name] ?? null) ? $page['data'][$name] : [], "f[$name]", $name,
                                    pb_row_actions($section['label'], $section['copy'] !== '' && $pieceTypes !== [], false, $pieceTypes !== [])) ?>
                            <?php else: ?>
                                <div class="pb-section pb-section-fixed" data-section="<?= e($name) ?>" data-sort="<?= e($name) ?>" data-tools="0">
                                    <?= pb_row_tools($section['label'], pb_row_actions($section['label'], false, false, $pieceTypes !== [])) ?>
                                    <div class="pb-section-row"><span class="pb-section-name"><?= e($section['label']) ?></span></div>
                                    <p class="help"><?= e($section['help']) ?></p>
                                    <input type="hidden" name="f[_order][]" value="<?= e($name) ?>">
                                </div>
                            <?php endif ?>
                        <?php endforeach ?>
                    </div>
                    <?php if ($pieceTypes): ?>
                        <p class="pb-sections-empty" data-sections-empty<?= $sections ? ' hidden' : '' ?>><?= e(__('Esta página ainda não tem seções. Escolha uma para começar.')) ?></p>
                        <button type="button" class="pb-add" data-open-gallery><?= pb_admin_icon('plus') ?><span><?= e(__('Adicionar seção')) ?></span></button>
                    <?php endif ?>
                </section>
            <?php endif ?>

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

            <?php if ($revisions): ?>
                <details class="card">
                    <summary><?= e(__('Histórico')) ?></summary>
                    <p class="help"><?= e(__('Cada vez que você publica, a versão anterior fica guardada aqui (as últimas 10).')) ?></p>
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
        </div>

        <?php require __DIR__ . '/studio-preview.php'; ?>
    </div>

    <?php if ($pieceTypes): ?>
        <dialog class="pb-gallery" data-gallery aria-labelledby="gallery-title" data-section-url="<?= e(pb_url('/admin/pages/section')) ?>">
            <div class="pb-gallery-head">
                <h2 id="gallery-title"><?= e(__('Que seção você quer adicionar?')) ?></h2>
                <button type="button" class="icon-btn" data-gallery-close title="<?= e(__('Fechar')) ?>" aria-label="<?= e(__('Fechar')) ?>"><?= pb_admin_icon('x') ?></button>
            </div>
            <div class="pb-gallery-grid"><?= pb_block_gallery($pieceTypes) ?></div>
        </dialog>
    <?php endif ?>
</form>

<?php foreach ($revisions as $r): ?>
    <form id="restore-<?= (int) $r['id'] ?>" method="post" action="<?= e(pb_url('/admin/pages/restore')) ?>"
          data-confirm="<?= e(__('Restaurar esta versão? O conteúdo atual vai para o histórico.')) ?>" hidden>
        <?= pb_csrf_field() ?>
        <input type="hidden" name="revision" value="<?= (int) $r['id'] ?>">
    </form>
<?php endforeach ?>
