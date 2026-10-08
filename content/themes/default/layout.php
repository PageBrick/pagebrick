<?php
/**
 * @var PbGroup $site      settings from "Aparência e contato"
 * @var PbValue $siteName
 * @var string  $content   the template's HTML
 */
$identity = $site->identity;
$contact = $site->contact;
$color = $identity->color->raw() ?: '#d24e2b';
$whatsapp = pb_whatsapp_url($contact->whatsapp->raw(), $contact->whatsapp_message->raw());
$social = array_filter([
    'Instagram' => $site->social->instagram->url(),
    'Facebook' => $site->social->facebook->url(),
    'LinkedIn' => $site->social->linkedin->url(),
    'YouTube' => $site->social->youtube->url(),
    'TikTok' => $site->social->tiktok->url(),
]);
?>
<!doctype html>
<html lang="<?= e(pb_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= e(pb_theme_url('assets/style.css')) ?>">
<?= pb_head(['image' => $identity->share_image->url()]) /* after the theme's CSS, so plugin styles can refine it */ ?>
<?php if (!$identity->icon->isEmpty()): ?>
<link rel="icon" href="<?= e($identity->icon->url('thumb')) ?>">
<?php endif ?>
<style>:root { --brand: <?= e($color) ?>; --on-brand: <?= e(pb_text_color_on($color)) ?>; --brand-text: <?= e(pb_readable_color($color)) ?>; }</style>
<script>document.documentElement.classList.add('js')</script>
<script src="<?= e(pb_theme_url('assets/site.js')) ?>" defer></script>
</head>
<body class="fonts-<?= e($identity->fonts->raw() ?: 'sobria') ?>">
<a class="skip" href="#conteudo"><?= e(__('Pular para o conteúdo')) ?></a>

<header class="site-header">
    <div class="wrap header-row">
        <a class="logo" href="<?= e(pb_url('/')) ?>">
            <?= $identity->logo->isEmpty() ? '<span>' . $siteName . '</span>' : $identity->logo->img('logo-img') ?>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-principal"><?= e(__('Menu')) ?></button>
        <nav id="menu-principal" class="main-nav" aria-label="<?= e(__('Menu principal')) ?>">
            <?= pb_menu_html('main') ?>
            <?php if ($whatsapp): ?>
                <a class="button" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif ?>
        </nav>
    </div>
</header>

<main id="conteudo">
<?= $content ?>
</main>

<footer class="site-footer">
    <div class="wrap footer-grid">
        <div>
            <p class="footer-name"><?= $siteName ?></p>
            <?php if (!$site->footer->text->isEmpty()): ?><p><?= $site->footer->text ?></p><?php endif ?>
        </div>
        <?php if (!$contact->address->isEmpty() || !$contact->phone->isEmpty() || !$contact->email->isEmpty() || !$contact->hours->isEmpty()): ?>
            <address>
                <?php if (!$contact->address->isEmpty()): ?>
                    <p><?= $contact->address ?><br><a href="<?= e(pb_map_url($contact->address->raw())) ?>" target="_blank" rel="noopener"><?= e(__('Ver no mapa')) ?></a></p>
                <?php endif ?>
                <?php if (!$contact->phone->isEmpty()): ?><p><a href="<?= $contact->phone ?>"><?= e($contact->phone->raw()) ?></a></p><?php endif ?>
                <?php if (!$contact->email->isEmpty()): ?><p><a href="<?= $contact->email ?>"><?= e($contact->email->raw()) ?></a></p><?php endif ?>
                <?php if (!$contact->hours->isEmpty()): ?><p><?= $contact->hours ?></p><?php endif ?>
            </address>
        <?php endif ?>
        <div>
            <?= pb_menu_html('footer', 'footer-menu') ?>
            <?php if ($social): ?>
                <ul class="social">
                    <?php foreach ($social as $network => $url): ?>
                        <li><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($network) ?></a></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
    <div class="wrap footer-bottom">
        <span>© <?= date('Y') ?> <?= $siteName ?></span>
        <?php if ($site->footer->credit->raw() !== 'hide'): ?>
            <a href="https://pagebrick.org" target="_blank" rel="noopener"><?= e(__('Feito com PageBrick')) ?></a>
        <?php endif ?>
    </div>
</footer>
<?= pb_footer() ?>
</body>
</html>
