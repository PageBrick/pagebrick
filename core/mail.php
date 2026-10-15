<?php
// Sending e-mail: through the SMTP server set in the panel, or the hosting's own mail() when none is set.

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

function pb_mail_fields(): array
{
    return [
        'from_email' => ['type' => 'email', 'label' => __('E-mail que envia'), 'help' => __('De preferência um e-mail do domínio do site, como contato@suaempresa.com.br. Ajuda a mensagem a não cair no spam.')],
        'from_name' => ['type' => 'text', 'label' => __('Nome de quem envia'), 'help' => __('Em branco: o nome do site.')],
        'host' => ['type' => 'text', 'label' => __('Servidor SMTP'), 'help' => __('Em branco: usa o envio padrão da hospedagem. O cPanel mostra estes dados em "Contas de e-mail" → "Conectar dispositivos". No Titan: smtp.titan.email, com o usuário e a senha da caixa de e-mail.')],
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

/** The address the mail leaves from: the one set in the panel, or no-reply@ the site's domain. */
function pb_mail_sender(array $settings): string
{
    $email = $settings['from_email'] ?: 'no-reply@' . preg_replace('/^www\./', '', (string) parse_url(pb_absolute_url(), PHP_URL_HOST));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'no-reply@pagebrick.invalid'; // e.g. "localhost" during development
}

/** PHPMailer's English reason, with what to do about the common ones; the original text stays at the end for whoever helps. */
function pb_mail_explain(string $reason): string
{
    $known = [
        '/Could not authenticate/' => __('O servidor recusou o usuário ou a senha. Confira os dois; se o e-mail é do Titan, ligue também "Ative o Titan em outros aplicativos" no painel do Titan e desligue a verificação em duas etapas dessa caixa.'),
        '/Could not connect|connect\(\) failed/' => __('Não consegui falar com o servidor SMTP. Confira o endereço do servidor, a porta e a segurança (587 com TLS, ou 465 com SSL).'),
        '/From address failed/' => __('O servidor não aceitou o e-mail que envia. Use o mesmo endereço do usuário SMTP.'),
    ];
    foreach ($known as $pattern => $advice) {
        if (preg_match($pattern, $reason)) {
            return "$advice ($reason)";
        }
    }
    return $reason;
}

/**
 * What the panel says after the test e-mail left. With no SMTP set, mail to an address of the site's own domain stays on the server, and when that
 * domain's mailboxes live elsewhere (Titan, Google, Zoho…) it never arrives: that case gets a hint.
 */
function pb_mail_test_notice(string $to): string
{
    $settings = pb_mail_settings();
    $domain = fn(string $email) => strtolower(substr((string) strrchr($email, '@'), 1));
    $notice = sprintf(__('E-mail de teste enviado para %s. Confira a caixa de entrada (e o spam).'), $to);
    if ($settings['host'] === '' && $domain($to) === $domain(pb_mail_sender($settings))) {
        $notice .= ' ' . __('Se ele não chegar e o e-mail do seu domínio fica em outro serviço (Titan, Google, Zoho…), preencha nos campos abaixo o servidor SMTP desse serviço.');
    }
    return $notice;
}

/** Sends a plain text e-mail. Throws RuntimeException with a readable reason when it fails. */
function pb_mail(string $to, string $subject, string $text, string $replyTo = ''): void
{
    $settings = pb_mail_settings();
    $fromEmail = pb_mail_sender($settings);
    $fromName = $settings['from_name'] ?: pb_option('site_title', 'PageBrick');

    if (($GLOBALS['pb_config']['mail'] ?? '') === 'fail') {
        throw new RuntimeException(__('O e-mail não foi enviado: a hospedagem recusou o envio.')); // tests
    }
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
        throw new RuntimeException(sprintf(__('O e-mail não foi enviado: %s'), pb_mail_explain($mail->ErrorInfo ?: $e->getMessage())), 0, $e);
    }
}
