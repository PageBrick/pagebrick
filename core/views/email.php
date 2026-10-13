<form method="post" action="<?= e(pb_url('/admin/email')) ?>">
    <?= pb_csrf_field() ?>
    <div class="card">
        <h1><?= e(__('E-mail')) ?></h1>
        <p class="help"><?= e(__('Como o site envia e-mails (por exemplo, as mensagens do formulário de contato). Sem servidor SMTP, usa o envio padrão da hospedagem, que às vezes cai no spam.')) ?></p>
        <?= pb_field_inputs($fields, $data, 'f') ?>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>
<form method="post" action="<?= e(pb_url('/admin/email/test')) ?>" class="card">
    <?= pb_csrf_field() ?>
    <h2><?= e(__('Testar')) ?></h2>
    <p><?= e(sprintf(__('Envia um e-mail de teste para %s.'), $user['email'])) ?></p>
    <button type="submit" class="secondary"><?= e(__('Enviar e-mail de teste')) ?></button>
</form>
