<div class="card">
    <h1><?= e(__('Instalar o PageBrick')) ?></h1>
    <p class="muted"><?= e(__('Crie um banco de dados MySQL na sua hospedagem (no cPanel: "Bancos de dados MySQL") e preencha os dados abaixo.')) ?></p>
    <?php foreach ($errors as $error): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endforeach ?>
    <form method="post" action="<?= e(pb_url('/')) ?>">
        <?= pb_csrf_field() ?>
        <fieldset>
            <legend><?= e(__('Site')) ?></legend>
            <label for="site_title"><?= e(__('Nome do site')) ?></label>
            <input id="site_title" name="site_title" required value="<?= e($old['site_title']) ?>">
        </fieldset>
        <fieldset>
            <legend><?= e(__('Banco de dados')) ?></legend>
            <label for="db_host"><?= e(__('Servidor')) ?></label>
            <input id="db_host" name="db_host" placeholder="localhost" value="<?= e($old['db_host']) ?>">
            <label for="db_name"><?= e(__('Nome do banco')) ?></label>
            <input id="db_name" name="db_name" required value="<?= e($old['db_name']) ?>">
            <label for="db_user"><?= e(__('Usuário do banco')) ?></label>
            <input id="db_user" name="db_user" required autocomplete="off" value="<?= e($old['db_user']) ?>">
            <label for="db_pass"><?= e(__('Senha do banco')) ?></label>
            <input id="db_pass" name="db_pass" type="password" autocomplete="off">
            <label for="db_prefix"><?= e(__('Prefixo das tabelas')) ?></label>
            <input id="db_prefix" name="db_prefix" placeholder="pb_" value="<?= e($old['db_prefix']) ?>">
        </fieldset>
        <fieldset>
            <legend><?= e(__('Administrador')) ?></legend>
            <label for="admin_name"><?= e(__('Seu nome')) ?></label>
            <input id="admin_name" name="admin_name" required maxlength="100" value="<?= e($old['admin_name']) ?>">
            <label for="admin_email"><?= e(__('Seu e-mail')) ?></label>
            <input id="admin_email" name="admin_email" type="email" required autocomplete="username" value="<?= e($old['admin_email']) ?>">
            <label for="admin_password"><?= e(__('Senha (mínimo 8 caracteres)')) ?></label>
            <input id="admin_password" name="admin_password" type="password" required minlength="8" autocomplete="new-password">
        </fieldset>
        <button type="submit"><?= e(__('Instalar')) ?></button>
    </form>
</div>
