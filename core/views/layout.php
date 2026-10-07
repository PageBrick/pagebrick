<?php
/** @var string $content */
$user = pb_current_user();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? "$title · PageBrick" : 'PageBrick') ?></title>
<style>
:root{--bg:#f6f7f9;--card:#fff;--text:#1d2330;--muted:#5b6475;--line:#dde1e7;--accent:#c2410c;--on-accent:#fff;--ok:#166534;--ok-bg:#dcfce7;--err:#991b1b;--err-bg:#fee2e2}
@media (prefers-color-scheme:dark){:root{--bg:#14161a;--card:#1d2026;--text:#e8eaee;--muted:#9aa3b2;--line:#2e333c;--accent:#fb923c;--on-accent:#1d1206;--ok:#86efac;--ok-bg:#12321f;--err:#fca5a5;--err-bg:#3a1414}}
*{box-sizing:border-box}
body{margin:0;font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--bg);color:var(--text)}
a{color:var(--accent)}
.top{display:flex;flex-wrap:wrap;gap:.5rem 1.5rem;align-items:center;padding:.75rem 1.5rem;background:var(--card);border-bottom:1px solid var(--line)}
.top nav{display:flex;gap:1rem;flex:1}
.top form{display:flex;gap:.75rem;align-items:center;color:var(--muted)}
main{max-width:720px;margin:2rem auto;padding:0 1rem}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:1.5rem;margin-bottom:1.5rem}
h1{margin-top:0;font-size:1.6rem}
label{display:block;margin:.75rem 0 .25rem;font-weight:600}
input,select,textarea{width:100%;padding:.6rem .7rem;border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--text);font:inherit}
textarea{font-family:ui-monospace,monospace;font-size:.85rem}
fieldset{border:1px solid var(--line);border-radius:8px;padding:.5rem 1rem 1rem;margin:1rem 0}
legend{font-weight:700;padding:0 .4rem}
button{margin-top:1.25rem;padding:.6rem 1.1rem;border:0;border-radius:6px;background:var(--accent);color:var(--on-accent);font:inherit;font-weight:600;cursor:pointer}
button.link{margin:0;padding:0;background:none;color:var(--accent);font-weight:400;text-decoration:underline}
.flash,.error{padding:.75rem 1rem;border-radius:6px}
.flash.ok{background:var(--ok-bg);color:var(--ok)}
.flash.error,.error{background:var(--err-bg);color:var(--err)}
.muted{color:var(--muted)}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:.5rem;border-bottom:1px solid var(--line)}
</style>
</head>
<body>
<?php if ($user): ?>
<header class="top">
    <strong>PageBrick</strong>
    <nav>
        <a href="<?= e(pb_url('/admin')) ?>"><?= e(__('Painel')) ?></a>
        <?php if (pb_has_role($user, 'admin')): ?>
            <a href="<?= e(pb_url('/admin/users')) ?>"><?= e(__('Usuários')) ?></a>
        <?php endif ?>
    </nav>
    <form method="post" action="<?= e(pb_url('/admin/logout')) ?>">
        <?= pb_csrf_field() ?>
        <span><?= e($user['name']) ?></span>
        <button type="submit" class="link"><?= e(__('Sair')) ?></button>
    </form>
</header>
<?php endif ?>
<main>
    <?php foreach (pb_take_flashes() as [$type, $message]): ?>
        <p class="flash <?= e($type) ?>" role="status"><?= e($message) ?></p>
    <?php endforeach ?>
    <?= $content ?>
</main>
</body>
</html>
