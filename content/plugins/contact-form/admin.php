<?php
/**
 * @var array $messages
 * @var bool  $mailOk   an SMTP server is configured
 */
?>
<div class="card">
    <h1><?= e(__('Mensagens')) ?></h1>
    <p class="help"><?= e(__('Mensagens recebidas pelo formulário de contato. Cada uma também é enviada por e-mail. Mensagens antigas são apagadas sozinhas, conforme as configurações do plugin.')) ?></p>
    <?php if (!$messages): ?>
        <p class="muted"><?= e(__('Nenhuma mensagem ainda.')) ?></p>
    <?php endif ?>
    <?php foreach ($messages as $m): ?>
        <details class="message">
            <summary>
                <?= e(pb_date($m['created_at'], true)) ?> · <strong><?= e($m['name']) ?></strong>
                <?php if ($m['read_at'] === null): ?><span class="badge"><?= e(__('nova')) ?></span><?php endif ?>
            </summary>
            <p>
                <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                <?php if ($m['phone'] !== ''): ?> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $m['phone'])) ?>"><?= e($m['phone']) ?></a><?php endif ?>
            </p>
            <p><?= nl2br(e($m['message']), false) ?></p>
            <?php if ($m['mail_error']): ?>
                <p class="error"><?= e(sprintf(__('O aviso por e-mail não foi enviado: %s.'), $m['mail_error'])) ?>
                    <?php if (!$mailOk): ?><a href="<?= e(pb_url('/admin/email')) ?>"><?= e(__('Configurar o envio de e-mail')) ?></a><?php endif ?></p>
            <?php endif ?>
            <form method="post" action="<?= e(pb_url('/admin/p/mensagens')) ?>" data-confirm="<?= e(__('Apagar esta mensagem? Isso não pode ser desfeito.')) ?>">
                <?= pb_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="link danger"><?= e(__('Apagar')) ?></button>
            </form>
        </details>
    <?php endforeach ?>
</div>
