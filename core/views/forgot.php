<div class="card auth">
    <h1><?= e(__('Esqueci minha senha')) ?></h1>
    <?php if (!empty($sent)): ?>
        <p class="flash ok" role="status"><?= e(__('Se esse e-mail tiver uma conta, enviamos para ele um link para criar uma senha nova. O link vale por 1 hora.')) ?></p>
        <p class="help"><?= e(__('Não chegou em alguns minutos? Olhe a caixa de spam. Se mesmo assim não chegar, o envio de e-mails do site pode não estar configurado: peça a outro administrador para trocar a sua senha em Usuários.')) ?></p>
    <?php else: ?>
        <p class="help"><?= e(__('Informe o e-mail da sua conta. Vamos enviar um link para você criar uma senha nova.')) ?></p>
        <?php if (!empty($error)): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <form method="post" action="<?= e(pb_url('/admin/forgot')) ?>">
            <?= pb_csrf_field() ?>
            <label for="email"><?= e(__('E-mail')) ?></label>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= e($email ?? '') ?>">
            <button type="submit"><?= e(__('Enviar o link')) ?></button>
        </form>
    <?php endif ?>
    <p class="auth-back"><a href="<?= e(pb_url('/admin/login')) ?>"><?= e(__('Voltar para o login')) ?></a></p>
</div>
