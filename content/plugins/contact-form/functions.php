<?php
// Contact form: shows the form, checks it, keeps the message and sends it by e-mail.
//
// No captcha. Spam is stopped by:
//   - an invisible field that only robots fill in;
//   - a signed timestamp: sending in under PBCF_MIN_SECONDS is a robot, older than a day is expired;
//   - at most PBCF_MAX_PER_HOUR messages per IP address per hour.
// Visitors get no cookie: the signed timestamp replaces the session a CSRF token would need.

const PBCF_MIN_SECONDS = 3;
const PBCF_MAX_PER_HOUR = 5;

function pbcf_settings(): PbGroup
{
    return pb_plugin_settings_values('contact-form');
}

/** The form's HTML; after an error it comes back with what the person typed. */
function pbcf_form(): string
{
    $GLOBALS['pbcf_used'] = true;
    if (($_GET['enviado'] ?? '') === '1') {
        $message = pbcf_settings()->success->raw() ?: __('Recebemos sua mensagem e respondemos em breve.');
        return '<div class="pb-form" id="contato-form"><p class="pb-form-ok" role="status">' . e($message) . '</p></div>';
    }
    return pb_include(__DIR__ . '/form.php', [
        'errors' => $GLOBALS['pbcf_state']['errors'] ?? [],
        'old' => $GLOBALS['pbcf_state']['old'] ?? [],
        'phone' => pbcf_settings()->phone->raw() ?: 'optional',
        'privacy' => pbcf_privacy_url(),
        'time' => (string) time(),
        'pageId' => (int) ($GLOBALS['pb_current_page']['id'] ?? 0),
    ]);
}

/** The stylesheet goes only on pages that show the form (templates render before the layout's <head>). */
function pbcf_stylesheet(): string
{
    return empty($GLOBALS['pbcf_used']) ? '' : '<link rel="stylesheet" href="' . e(pb_plugin_url('contact-form', 'style.css')) . "\">\n";
}

function pbcf_privacy_url(): string
{
    $setting = pbcf_settings()->privacy;
    if (!$setting->isEmpty()) {
        return $setting->url();
    }
    $page = pb_page_by_slug(__('politica-de-privacidade'), pb_content_locale());
    return $page && $page['status'] === 'published' ? pb_page_url($page) : '';
}

/** POST /contato/enviar */
function pbcf_submit(): void
{
    $page = pb_page_find((int) pb_post('page'));
    $back = $page && $page['status'] === 'published' ? pb_page_url($page) : pb_url('/');
    if (pb_post('website') !== '') {
        pbcf_redirect($back . '?enviado=1#contato-form'); // a robot filled the invisible field: pretend it worked
    }
    $in = [
        'name' => trim(pb_post('name')),
        'email' => strtolower(trim(pb_post('email'))),
        'phone' => trim(pb_post('phone')),
        'message' => trim(pb_post('message')),
    ];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $errors = pbcf_validate($in, pb_post('_t'), pb_post('_s'), $ip);
    if (!$errors) {
        pbcf_store_and_send($in, $page['id'] ?? null, $ip);
        pbcf_redirect($back . '?enviado=1#contato-form');
    }
    if (!$page) {
        pbcf_redirect($back);
    }
    http_response_code(422);
    $GLOBALS['pbcf_state'] = ['errors' => $errors, 'old' => $in];
    echo pb_render_page($page);
}

function pbcf_redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Problems with a submission, as messages for the visitor. Empty means it can be accepted. */
function pbcf_validate(array $in, string $time, string $signature, string $ip, ?int $now = null): array
{
    $now ??= time();
    $errors = [];
    if (!ctype_digit($time) || !pb_signature_valid("contact-form|$time", $signature) || $now - (int) $time > 86400) {
        $errors[] = __('O formulário expirou. Confira os campos e envie de novo.');
    } elseif ($now - (int) $time < PBCF_MIN_SECONDS) {
        $errors[] = __('O envio foi rápido demais. Confira os campos e envie de novo.');
    }
    if (!preg_match('/^[^\x00-\x1F\x7F]{2,100}$/u', $in['name'])) {
        $errors[] = __('Informe seu nome.');
    }
    if (strlen($in['email']) > 190 || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('Informe um e-mail válido, para podermos responder.');
    }
    $phoneMode = pbcf_settings()->phone->raw() ?: 'optional';
    if ($phoneMode === 'required' && !preg_match('/\d{8,}/', preg_replace('/\D/', '', $in['phone']))) {
        $errors[] = __('Informe seu telefone com DDD.');
    }
    if (strlen($in['phone']) > 30) {
        $errors[] = __('O telefone está longo demais.');
    }
    if (!preg_match('/^.{5,5000}$/su', $in['message'])) {
        $errors[] = __('Escreva sua mensagem (de 5 a 5.000 caracteres).');
    }
    if (pbcf_recent_count($ip) >= PBCF_MAX_PER_HOUR) {
        $errors[] = __('Recebemos muitas mensagens deste endereço na última hora. Tente de novo mais tarde.');
    }
    return $errors;
}

function pbcf_recent_count(string $ip): int
{
    $st = pb_db()->prepare('SELECT COUNT(*) FROM ' . pb_table('contact_messages') . ' WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $st->execute([$ip]);
    return (int) $st->fetchColumn();
}

/** Saves the message first, then e-mails it: if e-mail fails, nothing is lost. Returns the message id. */
function pbcf_store_and_send(array $in, ?int $pageId, string $ip): int
{
    $table = pb_table('contact_messages');
    pb_db()->prepare("INSERT INTO $table (name, email, phone, message, page_id, ip) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$in['name'], $in['email'], pb_limit($in['phone'], 30), $in['message'], $pageId, substr($ip, 0, 45)]);
    $id = (int) pb_db()->lastInsertId();

    $months = (int) (pbcf_settings()->keep->raw() ?: 12);
    if ($months > 0) {
        pb_db()->exec("DELETE FROM $table WHERE created_at < NOW() - INTERVAL $months MONTH");
    }

    $siteName = pb_option('site_title', '');
    $text = __('Nome') . ": {$in['name']}\n" . __('E-mail') . ": {$in['email']}\n" . ($in['phone'] !== '' ? __('Telefone') . ": {$in['phone']}\n" : '')
        . "\n{$in['message']}\n\n--\n" . sprintf(__('Enviada pelo formulário de contato do site %s.'), $siteName) . "\n"
        . __('Também está guardada no painel, em Mensagens. Para responder, é só responder este e-mail.');
    try {
        pb_mail(pbcf_recipient(), sprintf(__('Nova mensagem pelo site: %s'), $in['name']), $text, $in['email']);
    } catch (RuntimeException $e) {
        error_log('PageBrick contact form: ' . $e->getMessage());
        pb_db()->prepare("UPDATE $table SET mail_error = ? WHERE id = ?")->execute([pb_limit($e->getMessage(), 500), $id]);
    }
    return $id;
}

function pbcf_recipient(): string
{
    $to = pbcf_settings()->to->raw();
    if ($to !== '') {
        return $to;
    }
    return (string) pb_db()->query('SELECT email FROM ' . pb_table('users') . " WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
}

/** The "Mensagens" screen in the panel. */
function pbcf_admin(): void
{
    $table = pb_table('contact_messages');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        pb_db()->prepare("DELETE FROM $table WHERE id = ?")->execute([(int) pb_post('id')]);
        pb_flash('ok', __('Mensagem apagada.'));
        pb_redirect('/admin/p/mensagens');
    }
    // ponytail: shows the newest 200; add paging if a site really keeps more than that.
    $messages = pb_db()->query("SELECT * FROM $table ORDER BY id DESC LIMIT 200")->fetchAll();
    pb_db()->exec("UPDATE $table SET read_at = NOW() WHERE read_at IS NULL");
    echo pb_include(__DIR__ . '/admin.php', ['messages' => $messages, 'mailOk' => pb_mail_settings()['host'] !== '']);
}
