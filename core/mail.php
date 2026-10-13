<?php
// Sending e-mail: through the SMTP server set in the panel, or the hosting's own mail() when none is set.

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

function pb_mail_fields(): array
{
    return [
        'from_email' => ['type' => 'email', 'label' => __('E-mail que envia'), 'help' => __('De preferência um e-mail do domínio do site, como contato@suaempresa.com.br. Ajuda a mensagem a não cair no spam.')],
        'from_name' => ['type' => 'text', 'label' => __('Nome de quem envia'), 'help' => __('Em branco: o nome do site.')],
        'host' => ['type' => 'text', 'label' => __('Servidor SMTP'), 'help' => __('Em branco: usa o envio padrão da hospedagem. O cPanel mostra estes dados em "Contas de e-mail" → "Conectar dispositivos".')],
        'port' => ['type' => 'text', 'label' => __('Porta'), 'help' => __('Normalmente 587 (TLS) ou 465 (SSL).')],
        'secure' => ['type' => 'select', 'label' => __('Segurança'), 'default' => 'tls', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('Nenhuma')]],
        'user' => ['type' => 'text', 'label' => __('Usuário SMTP')],
        'pass' => ['type' => 'password', 'label' => __('Senha SMTP')],
    ];
}

function pb_mail_settings(): array
{
    return (json_decode(pb_option('mail', '{}'), true) ?: []) + array_fill_keys(array_keys(pb_mail_fields()), '');
}

/** Saves the e-mail settings; an empty password keeps the saved one. */
function pb_save_mail_settings(mixed $input): void
{
    $settings = pb_collect_fields(pb_mail_fields(), $input);
    if ($settings['pass'] === '') {
        $settings['pass'] = pb_mail_settings()['pass'];
    }
    pb_set_option('mail', json_encode($settings, JSON_UNESCAPED_UNICODE));
}

/** Sends a plain text e-mail. Throws RuntimeException with a readable reason when it fails. */
function pb_mail(string $to, string $subject, string $text, string $replyTo = ''): void
{
    $settings = pb_mail_settings();
    $fromEmail = $settings['from_email'] ?: 'no-reply@' . preg_replace('/^www\./', '', (string) parse_url(pb_absolute_url(), PHP_URL_HOST));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = 'no-reply@pagebrick.invalid'; // e.g. "localhost" during development
    }
    $fromName = $settings['from_name'] ?: pb_option('site_title', 'PageBrick');

    if (($GLOBALS['pb_config']['mail'] ?? '') === 'memory') {
        $GLOBALS['pb_sent_mail'][] = compact('to', 'subject', 'text', 'replyTo', 'fromEmail'); // tests
        return;
    }
    if (!class_exists(PHPMailer::class)) {
        // vendor/ missing (code taken straight from git without "composer install"): plain mail() still works.
        $headers = "From: $fromName <$fromEmail>\r\nContent-Type: text/plain; charset=UTF-8" . ($replyTo !== '' ? "\r\nReply-To: $replyTo" : '');
        if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers)) {
            throw new RuntimeException(__('O e-mail não foi enviado: a hospedagem recusou o envio.'));
        }
        return;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        if ($settings['host'] !== '') {
            $mail->isSMTP();
            $mail->Host = $settings['host'];
            $mail->Port = (int) ($settings['port'] ?: ($settings['secure'] === 'ssl' ? 465 : 587));
            $mail->SMTPSecure = match ($settings['secure']) {
                'ssl' => PHPMailer::ENCRYPTION_SMTPS,
                'none' => '',
                default => PHPMailer::ENCRYPTION_STARTTLS,
            };
            $mail->SMTPAutoTLS = $settings['secure'] !== 'none';
            $mail->SMTPAuth = $settings['user'] !== '';
            $mail->Username = $settings['user'];
            $mail->Password = $settings['pass'];
            $mail->Timeout = 15;
        }
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        if ($replyTo !== '') {
            $mail->addReplyTo($replyTo);
        }
        $mail->Subject = $subject;
        $mail->Body = $text;
        $mail->send();
    } catch (PHPMailerException $e) {
        throw new RuntimeException(sprintf(__('O e-mail não foi enviado: %s'), $mail->ErrorInfo ?: $e->getMessage()), 0, $e);
    }
}
