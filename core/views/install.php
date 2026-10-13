<?php
/**
 * @var int        $step     1 language, 2 before you start, 3 database, 4 site and administrator
 * @var array      $checks   pb_install_checks()
 * @var bool       $blocked  a required check failed
 * @var string[]   $errors
 * @var array      $old      what was typed (never passwords)
 * @var string     $locale
 */
$names = [1 => __('Idioma'), 2 => __('Antes de começar'), 3 => __('Banco de dados'), 4 => __('Site e administrador')];
$url = fn(int $step) => pb_url('/?step=' . $step);
?>
<div class="card install">
    <ol class="steps" aria-label="<?= e(__('Etapas')) ?>">
        <?php foreach ($names as $number => $name): ?>
            <li<?= $number === $step ? ' aria-current="step"' : ($number < $step ? ' class="done"' : '') ?>><span><?= $number ?></span> <?= e($name) ?></li>
        <?php endforeach ?>
    </ol>
    <?php foreach ($errors as $error): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endforeach ?>

    <?php if ($step === 1): ?>
        <h1>PageBrick</h1>
        <form method="post" action="<?= e($url(2)) ?>">
            <?= pb_csrf_field() ?>
            <fieldset class="languages">
                <legend>Idioma · Language · Idioma</legend>
                <?php foreach (PB_LOCALES as $code => $name): ?>
                    <label lang="<?= e($code) ?>"><input type="radio" name="locale" value="<?= e($code) ?>"<?= $code === $locale ? ' checked' : '' ?>> <?= e($name) ?></label>
                <?php endforeach ?>
            </fieldset>
            <p class="help"><?= e(__('O site, o painel e o conteúdo de exemplo serão criados neste idioma. Dá para trocar depois.')) ?></p>
            <button type="submit"><?= e(__('Continuar')) ?> →</button>
        </form>

    <?php elseif ($step === 2): ?>
        <h1><?= e(__('Bem-vindo ao PageBrick')) ?></h1>
        <p><?= e(__('A instalação leva uns cinco minutos. Antes de começar, tenha em mãos os dados do banco de dados MySQL:')) ?></p>
        <ol>
            <li><?= e(__('nome do banco de dados')) ?></li>
            <li><?= e(__('usuário do banco de dados')) ?></li>
            <li><?= e(__('senha desse usuário')) ?></li>
            <li><?= e(__('servidor do banco (quase sempre localhost)')) ?></li>
        </ol>
        <p class="help"><?= e(__('Ainda não tem um banco? No cPanel da hospedagem, abra "Assistente de banco de dados MySQL": ele cria o banco, o usuário e a senha e liga um ao outro com todos os privilégios.')) ?></p>
        <h2><?= e(__('Conferência do servidor')) ?></h2>
        <ul class="checks">
            <?php foreach ($checks as [$what, $ok, $todo, $required]): ?>
                <li class="<?= $ok ? 'ok' : ($required ? 'bad' : 'warn') ?>">
                    <strong><?= $ok ? '✓' : ($required ? '✕' : '!') ?></strong> <?= e($what) ?>
                    <?php if (!$ok): ?><span><?= e($todo) ?></span><?php endif ?>
                </li>
            <?php endforeach ?>
            <li id="rewrite-check" class="warn">
                <strong>…</strong> <?= e(__('Endereços amigáveis (arquivo .htaccess)')) ?>
                <span hidden><?= e(__('O arquivo .htaccess não está funcionando. Confira se ele foi enviado junto com os outros arquivos (alguns programas escondem arquivos que começam com ponto) e se a hospedagem usa Apache com mod_rewrite.')) ?></span>
            </li>
        </ul>
        <?php if ($blocked): ?>
            <p class="error" role="alert"><?= e(__('Resolva os itens marcados com ✕ e recarregue esta página.')) ?></p>
        <?php else: ?>
            <p><a class="button" href="<?= e($url(3)) ?>"><?= e(__('Vamos lá')) ?> →</a></p>
        <?php endif ?>
        <script>
            fetch(<?= json_encode(pb_url('/install-check'), JSON_UNESCAPED_SLASHES) ?>, {cache: 'no-store'})
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(() => { const li = document.getElementById('rewrite-check'); li.className = 'ok'; li.querySelector('strong').textContent = '✓'; })
                .catch(() => { const li = document.getElementById('rewrite-check'); li.querySelector('strong').textContent = '!'; li.querySelector('span').hidden = false; });
        </script>

    <?php elseif ($step === 3): ?>
        <h1><?= e(__('Banco de dados')) ?></h1>
        <p><?= e(__('Preencha com os dados do banco criado na hospedagem. Se não souber algum, a hospedagem informa.')) ?></p>
        <form method="post" action="<?= e($url(3)) ?>">
            <?= pb_csrf_field() ?>
            <label for="db_name"><?= e(__('Nome do banco')) ?></label>
            <input id="db_name" name="db_name" required value="<?= e($old['db_name'] ?? '') ?>">
            <label for="db_user"><?= e(__('Usuário do banco')) ?></label>
            <input id="db_user" name="db_user" required autocomplete="off" value="<?= e($old['db_user'] ?? '') ?>">
            <label for="db_pass"><?= e(__('Senha do banco')) ?></label>
            <input id="db_pass" name="db_pass" type="password" autocomplete="off">
            <label for="db_host"><?= e(__('Servidor')) ?></label>
            <input id="db_host" name="db_host" placeholder="localhost" value="<?= e($old['db_host'] ?? '') ?>">
            <p class="help"><?= e(__('Deixe em branco para usar localhost, o padrão da maioria das hospedagens.')) ?></p>
            <label for="db_prefix"><?= e(__('Prefixo das tabelas')) ?></label>
            <input id="db_prefix" name="db_prefix" placeholder="pb_" value="<?= e($old['db_prefix'] ?? '') ?>">
            <p class="help"><?= e(__('Só mude se quiser instalar mais de um PageBrick no mesmo banco.')) ?></p>
            <p class="actions"><a href="<?= e($url(2)) ?>">← <?= e(__('Voltar')) ?></a> <button type="submit"><?= e(__('Testar a conexão e continuar')) ?> →</button></p>
        </form>

    <?php else: ?>
        <p class="flash ok"><?= e(__('Tudo certo com o banco de dados.')) ?></p>
        <h1><?= e(__('Site e administrador')) ?></h1>
        <form method="post" action="<?= e($url(4)) ?>">
            <?= pb_csrf_field() ?>
            <label for="site_title"><?= e(__('Nome do site')) ?></label>
            <input id="site_title" name="site_title" required value="<?= e($old['site_title'] ?? '') ?>">
            <label for="admin_name"><?= e(__('Seu nome')) ?></label>
            <input id="admin_name" name="admin_name" required maxlength="100" value="<?= e($old['admin_name'] ?? '') ?>">
            <label for="admin_email"><?= e(__('Seu e-mail')) ?></label>
            <input id="admin_email" name="admin_email" type="email" required autocomplete="username" value="<?= e($old['admin_email'] ?? '') ?>">
            <p class="help"><?= e(__('É com ele que você entra no painel e recebe as mensagens do formulário de contato.')) ?></p>
            <label for="admin_password"><?= e(__('Senha (mínimo 8 caracteres)')) ?></label>
            <div class="password">
                <input id="admin_password" name="admin_password" type="password" required minlength="8" autocomplete="new-password">
                <button type="button" class="secondary" data-generate="admin_password"><?= e(__('Gerar senha forte')) ?></button>
            </div>
            <p class="help"><?= e(__('Guarde a senha num lugar seguro, como um gerenciador de senhas.')) ?></p>
            <p class="actions"><button type="submit"><?= e(__('Instalar o PageBrick')) ?></button></p>
        </form>
    <?php endif ?>
</div>
<script>
    document.querySelectorAll('[data-generate]').forEach(button => button.addEventListener('click', () => {
        const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
        const input = document.getElementById(button.dataset.generate);
        input.value = Array.from(crypto.getRandomValues(new Uint32Array(18)), n => chars[n % chars.length]).join('');
        if (input.type === 'password') {
            input.parentElement.querySelector('.reveal')?.click(); // shown, so it can be copied (the eye hides it again)
        }
    }));
</script>
