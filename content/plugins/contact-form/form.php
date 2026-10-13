<?php
/**
 * @var string[] $errors
 * @var array    $old
 * @var string   $phone    optional | required | hidden
 * @var string   $privacy  privacy policy address, or ''
 * @var string   $time
 * @var int      $pageId
 */
$value = fn(string $key) => e($old[$key] ?? '');
?>
<form class="pb-form" id="contato-form" method="post" action="<?= e(pb_url(pb_locale_path(pb_content_locale(), '/contato/enviar'))) ?>"><?php /* in the page's language: /en-us/contato/enviar */ ?>
    <h2><?= e(__('Envie uma mensagem')) ?></h2>
    <?php if ($errors): ?>
        <div class="pb-form-errors" role="alert">
            <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach ?>
        </div>
    <?php endif ?>
    <input type="hidden" name="page" value="<?= $pageId ?>">
    <input type="hidden" name="_t" value="<?= e($time) ?>">
    <input type="hidden" name="_s" value="<?= e(pb_sign("contact-form|$time")) ?>">

    <label for="cf-name"><?= e(__('Nome')) ?></label>
    <input id="cf-name" name="name" required maxlength="100" autocomplete="name" value="<?= $value('name') ?>">

    <label for="cf-email"><?= e(__('E-mail')) ?></label>
    <input id="cf-email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $value('email') ?>">

    <?php if ($phone !== 'hidden'): ?>
        <label for="cf-phone"><?= e(__('Telefone')) ?><?= $phone === 'optional' ? ' <span>' . e(__('(opcional)')) . '</span>' : '' ?></label>
        <input id="cf-phone" name="phone" type="tel" maxlength="30" autocomplete="tel"<?= $phone === 'required' ? ' required' : '' ?> value="<?= $value('phone') ?>">
    <?php endif ?>

    <label for="cf-message"><?= e(__('Mensagem')) ?></label>
    <textarea id="cf-message" name="message" required rows="6" maxlength="5000"><?= $value('message') ?></textarea>

    <div class="pb-form-trap" aria-hidden="true">
        <label for="cf-website"><?= e(__('Deixe este campo em branco')) ?></label>
        <input id="cf-website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <button type="submit" class="button button--large"><?= e(__('Enviar mensagem')) ?></button>
    <?php if ($privacy !== ''): ?>
        <p class="pb-form-note"><?= e(__('Usamos seus dados só para responder.')) ?> <a href="<?= e($privacy) ?>"><?= e(__('Política de privacidade')) ?></a></p>
    <?php endif ?>
</form>
