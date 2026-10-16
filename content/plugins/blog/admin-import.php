<?php
/**
 * The "Importar" tab of the blog screen: posts and categories from a JSON file.
 *
 * @var bool        $allowed  only administrators import
 * @var string|null $error    what is wrong with the file (one problem per line)
 */
?>
<nav class="tabs"><a href="<?= e(pb_url('/admin/p/blog')) ?>"><?= e(__('Textos')) ?></a><a href="<?= e(pb_url('/admin/p/blog?aba=categorias')) ?>"><?= e(__('Categorias')) ?></a><?php if ($allowed): ?><a href="<?= e(pb_url('/admin/p/blog?aba=importar')) ?>" aria-current="page"><?= e(__('Importar')) ?></a><?php endif ?></nav>
<?php if ($error): ?><p class="error" role="alert"><?= nl2br(e($error), false) ?></p><?php endif ?>
<div class="card">
    <h1><?= e(__('Importar textos')) ?></h1>
    <?php if (!$allowed): ?>
        <p class="muted"><?= e(__('Só administradores importam textos.')) ?></p>
    <?php else: ?>
        <p class="help"><?= e(__('Cadastra de uma vez vários textos (e as categorias deles) a partir de um arquivo JSON. Tudo é conferido antes: se algo estiver errado, nada é importado e a lista do que corrigir aparece aqui.')) ?></p>
        <ul class="help">
            <li><?= e(__('Cada texto entra com o título, o endereço, a data, o resumo e o texto do arquivo. O texto passa pela mesma limpeza do editor.')) ?></li>
            <li><?= e(__('Só os textos com "status": "published" ficam públicos; sem "status", entram como rascunho.')) ?></li>
            <li><?= e(__('Um texto cujo endereço já existe não é tocado: importar o mesmo arquivo de novo não duplica nada.')) ?></li>
            <li><?= e(__('Fotos não são importadas: depois, abra o texto e escolha a foto.')) ?></li>
        </ul>
        <p><a href="<?= e(pb_url('/admin/p/blog?aba=importar&exemplo=1')) ?>"><?= e(__('Baixar arquivo de exemplo')) ?></a></p>
        <form method="post" action="<?= e(pb_url('/admin/p/blog')) ?>" enctype="multipart/form-data">
            <?= pb_csrf_field() ?>
            <label for="blog-import-file"><?= e(__('Arquivo .json')) ?></label>
            <input id="blog-import-file" type="file" name="file" accept=".json,application/json">
            <label for="blog-import-json"><?= e(__('Ou cole o conteúdo do arquivo')) ?></label>
            <textarea id="blog-import-json" name="json" rows="8" spellcheck="false"><?= e($error ? pb_post('json') : '') ?></textarea>
            <button type="submit" name="action" value="import"><?= e(__('Importar')) ?></button>
        </form>
    <?php endif ?>
</div>
