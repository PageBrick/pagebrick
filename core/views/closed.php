<?php
/**
 * The page visitors see while the site is under construction or in maintenance (a theme can replace it with templates/closed.php).
 * @var string  $mode      'construction' or 'maintenance'
 * @var string  $title
 * @var string  $message
 * @var PbGroup $site      settings from "Aparência e contato"
 * @var string  $siteName
 */
$color = $site->identity->color->raw() ?: '#d24e2b';
$email = $site->contact->email->raw();
$whatsapp = pb_whatsapp_url($site->contact->whatsapp->raw());
?>
<!doctype html>
<html lang="<?= e(pb_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($siteName !== '' ? "$title · $siteName" : $title) ?></title>
<?php if (!$site->identity->icon->isEmpty()): ?><link rel="icon" href="<?= e($site->identity->icon->url('thumb')) ?>"><?php endif ?>
<style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f7f7f8; color: #171923; font: 17px/1.6 system-ui, -apple-system, "Segoe UI", sans-serif; }
    main { max-width: 34rem; padding: 3rem 1.5rem; text-align: center; }
    img { max-width: 12rem; max-height: 5rem; width: auto; height: auto; }
    .name { font-weight: 600; letter-spacing: .02em; }
    h1 { margin: 1.5rem 0 .75rem; font-size: clamp(1.8rem, 5vw, 2.6rem); font-weight: 300; line-height: 1.15; }
    p { margin: 0; color: #4a4f5c; }
    hr { width: 3rem; margin: 1.5rem auto; border: 0; border-top: 3px solid <?= e(pb_readable_color($color, '#f7f7f8')) ?>; }
    a { color: <?= e(pb_readable_color($color, '#f7f7f8')) ?>; font-weight: 600; }
    .contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; margin-top: 1.5rem; }
</style>
</head>
<body>
<main>
    <?php if (!$site->identity->logo->isEmpty()): ?>
        <?= $site->identity->logo->img(lazy: false) ?>
    <?php elseif ($siteName !== ''): ?>
        <div class="name"><?= e($siteName) ?></div>
    <?php endif ?>
    <h1><?= e($title) ?></h1>
    <hr>
    <p><?= nl2br(e($message)) ?></p>
    <?php if ($email !== '' || $whatsapp !== ''): ?>
        <div class="contact">
            <?php if ($whatsapp !== ''): ?><a href="<?= e($whatsapp) ?>">WhatsApp</a><?php endif ?>
            <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif ?>
        </div>
    <?php endif ?>
</main>
</body>
</html>
