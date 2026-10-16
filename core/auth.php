<?php
// Users, roles and login.

/** Role => level. A user can do everything a lower level can. */
const PB_ROLES = ['editor' => 1, 'admin' => 2];

/** Wrong passwords allowed per IP + e-mail every 15 minutes. */
const PB_LOGIN_MAX_FAILURES = 5;

function pb_role_labels(): array
{
    return ['admin' => __('Administrador'), 'editor' => __('Editor')];
}

function pb_has_role(array $user, string $role): bool
{
    return (PB_ROLES[$user['role']] ?? 0) >= (PB_ROLES[$role] ?? PHP_INT_MAX);
}

/** Checks user data and returns the normalized [name, email]. Throws with a message the user can read. */
function pb_validate_user(string $name, string $email, string $password, string $role): array
{
    $name = trim($name);
    $email = strtolower(trim($email));
    if (!preg_match('/^.{1,100}$/su', $name)) {
        throw new InvalidArgumentException(__('Informe um nome com até 100 caracteres.'));
    }
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(__('Informe um e-mail válido.'));
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException(__('A senha precisa ter pelo menos 8 caracteres.'));
    }
    if (!isset(PB_ROLES[$role])) {
        throw new InvalidArgumentException(__('Papel inválido.'));
    }
    return [$name, $email];
}

function pb_create_user(string $name, string $email, string $password, string $role): int
{
    [$name, $email] = pb_validate_user($name, $email, $password, $role);
    if (pb_find_user_by_email($email)) {
        throw new InvalidArgumentException(__('Já existe um usuário com este e-mail.'));
    }
    // The panel has its own language, apart from the site's: it starts as the one spoken where the account is made
    // (the installer's at installation), and each person can change theirs.
    pb_db()->prepare('INSERT INTO ' . pb_table('users') . ' (name, email, password_hash, role, locale) VALUES (?, ?, ?, ?, ?)')
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, pb_locale()]);
    return (int) pb_db()->lastInsertId();
}

/** Changes a user's data. An empty $newPassword keeps the current one. */
function pb_update_user(int $id, string $name, string $email, string $role, string $newPassword = ''): void
{
    [$name, $email] = pb_validate_user($name, $email, $newPassword !== '' ? $newPassword : 'unchanged', $role);
    $other = pb_find_user_by_email($email);
    if ($other && (int) $other['id'] !== $id) {
        throw new InvalidArgumentException(__('Já existe um usuário com este e-mail.'));
    }
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET name = ?, email = ?, role = ? WHERE id = ?')->execute([$name, $email, $role, $id]);
    if ($newPassword !== '') {
        // a password given here also ends a pending invitation
        pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET password_hash = ?, invited_at = NULL WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        pb_db()->prepare('DELETE FROM ' . pb_table('password_resets') . ' WHERE user_id = ?')->execute([$id]);
    }
}

/** "Minha conta": the user confirms with the current password before changing anything. */
function pb_update_own_account(array $user, string $currentPassword, string $name, string $email, string $newPassword): void
{
    if (!password_verify($currentPassword, $user['password_hash'])) {
        throw new InvalidArgumentException(__('A senha atual não confere.'));
    }
    pb_update_user((int) $user['id'], $name, $email, $user['role'], $newPassword);
}

/** The panel language for one user; '' follows the site's language. */
// ------------------------------------------------------------------ the person's photo

/** The photo is a square of this many pixels: twice what the header draws, so it stays sharp on dense screens. */
const PB_AVATAR_SIZE = 160;
const PB_AVATAR_MAX_BYTES = 5 * 1024 * 1024;

/** The address of a person's photo, or '' when they have none. */
function pb_avatar_url(array $user): string
{
    return !empty($user['avatar']) ? pb_url('content/uploads/' . $user['avatar']) : '';
}

/** Saves the photo sent in one entry of $_FILES. Throws with a message the person can read. */
function pb_avatar_set(int $userId, array $file): void
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(__('O arquivo passa do limite da hospedagem (%s).'), ini_get('upload_max_filesize')),
            UPLOAD_ERR_NO_FILE => __('Escolha uma foto.'),
            default => __('O envio falhou. Tente de novo.'),
        });
    }
    pb_avatar_store($userId, $file['tmp_name']);
}

/**
 * Makes the photo from a file on disk: its type is read from the content, it is cut to a centred square, shrunk, and written under a random name
 * (nothing from the person reaches the file system). The one it replaces is deleted.
 */
function pb_avatar_store(int $userId, string $source): void
{
    if (filesize($source) > PB_AVATAR_MAX_BYTES) {
        throw new InvalidArgumentException(__('A foto passa de 5 MB.'));
    }
    if (!in_array((new finfo(FILEINFO_MIME_TYPE))->file($source), PB_IMAGE_TYPES, true)) {
        throw new InvalidArgumentException(__('Tipo de arquivo não aceito. Envie JPG, PNG, WebP ou GIF.'));
    }
    [$width, $height] = @getimagesize($source) ?: [0, 0];
    if ($width * $height > PB_IMAGE_MAX_PIXELS) {
        throw new InvalidArgumentException(__('A imagem tem pixels demais para a hospedagem processar. Reduza o tamanho (por exemplo, para 4000 pixels de largura) e envie de novo.'));
    }
    @ini_set('memory_limit', '256M');
    $image = pb_image_open($source, (string) (new finfo(FILEINFO_MIME_TYPE))->file($source));
    [$width, $height] = [imagesx($image), imagesy($image)];
    $side = min($width, $height);
    $square = imagecreatetruecolor(PB_AVATAR_SIZE, PB_AVATAR_SIZE);
    imagealphablending($square, false);
    imagesavealpha($square, true);
    imagecopyresampled($square, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), PB_AVATAR_SIZE, PB_AVATAR_SIZE, $side, $side);

    $dir = pb_uploads_dir() . '/avatars';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException("Cannot create $dir");
    }
    $ext = function_exists('imagewebp') ? 'webp' : 'png';
    $path = "avatars/$userId-" . bin2hex(random_bytes(6)) . ".$ext";
    pb_image_save($square, $path, $ext);
    $before = (string) (pb_find_user($userId)['avatar'] ?? '');
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET avatar = ? WHERE id = ?')->execute([$path, $userId]);
    pb_avatar_forget($before);
}

/** Takes the photo away: the initials come back. */
function pb_avatar_remove(int $userId): void
{
    $before = (string) (pb_find_user($userId)['avatar'] ?? '');
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET avatar = NULL WHERE id = ?')->execute([$userId]);
    pb_avatar_forget($before);
}

/** Deletes a photo file; only a path of the shape this code writes is ever touched. */
function pb_avatar_forget(string $path): void
{
    if (preg_match('~^avatars/\d+-[0-9a-f]{12}\.(webp|png)$~', $path)) {
        @unlink(pb_uploads_dir() . "/$path");
    }
}

/** The preferences a person keeps in the panel: ['theme' => 'dark' | 'light']. The language has its own column. */
const PB_USER_PREFS = ['theme' => ['light', 'dark']];

function pb_user_prefs(array $user): array
{
    $prefs = json_decode((string) ($user['prefs'] ?? ''), true);
    return is_array($prefs) ? $prefs : [];
}

/** Saves one preference of a person (null forgets it: the panel then follows the system). Only known ones, with known values. */
function pb_set_user_pref(int $id, string $key, ?string $value): void
{
    if (!isset(PB_USER_PREFS[$key]) || ($value !== null && !in_array($value, PB_USER_PREFS[$key], true))) {
        throw new InvalidArgumentException(__('Preferência inválida.'));
    }
    $prefs = pb_user_prefs(pb_find_user($id) ?? []);
    if ($value === null) {
        unset($prefs[$key]);
    } else {
        $prefs[$key] = $value;
    }
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET prefs = ? WHERE id = ?')->execute([$prefs ? json_encode($prefs) : null, $id]);
}

function pb_set_user_locale(int $id, string $locale): void
{
    if ($locale !== '' && !isset(PB_LOCALES[$locale])) {
        throw new InvalidArgumentException(__('Idioma inválido.'));
    }
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET locale = ? WHERE id = ?')->execute([$locale === '' ? null : $locale, $id]);
}

function pb_delete_user(int $id, int $actingUserId): void
{
    if ($id === $actingUserId) {
        throw new InvalidArgumentException(__('Você não pode excluir a sua própria conta.'));
    }
    $photo = (string) (pb_find_user($id)['avatar'] ?? '');
    pb_db()->prepare('DELETE FROM ' . pb_table('users') . ' WHERE id = ?')->execute([$id]);
    pb_avatar_forget($photo);
    pb_db()->prepare('DELETE FROM ' . pb_table('password_resets') . ' WHERE user_id = ?')->execute([$id]); // an invitation not used yet goes with the person
}

function pb_find_user(int $id): ?array
{
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('users') . ' WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function pb_find_user_by_email(string $email): ?array
{
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('users') . ' WHERE email = ?');
    $st->execute([strtolower(trim($email))]);
    return $st->fetch() ?: null;
}

function pb_list_users(): array
{
    return pb_db()->query('SELECT id, name, email, role, invited_at FROM ' . pb_table('users') . ' ORDER BY name')->fetchAll();
}

/** The logged-in user, or null. */
function pb_current_user(): ?array
{
    if (empty($_SESSION['user_id']) || ($GLOBALS['pb_config'] ?? null) === null) {
        return null;
    }
    return pb_find_user((int) $_SESSION['user_id']);
}

/**
 * Checks e-mail and password and returns the user. Throws with a message the user can read.
 * Starting the session is the caller's job.
 */
function pb_login(string $email, string $password, string $ip): array
{
    $email = substr(strtolower(trim($email)), 0, 190);
    // ponytail: throttles by REMOTE_ADDR + e-mail; behind a CDN/proxy everyone shares one IP, so read the real client IP header when that setup shows up.
    $ip = substr($ip, 0, 45);
    $attempts = pb_table('login_attempts');

    if (pb_login_failures($email, $ip) >= PB_LOGIN_MAX_FAILURES) {
        throw new InvalidArgumentException(__('Muitas tentativas erradas. Aguarde 15 minutos e tente de novo.'));
    }

    $user = pb_find_user_by_email($email);
    // Hash something even when the e-mail doesn't exist, so response time doesn't reveal registered e-mails.
    $hash = $user['password_hash'] ?? password_hash('no-such-user', PASSWORD_DEFAULT);
    if (!$user || !password_verify($password, $hash)) {
        pb_db()->prepare("INSERT INTO $attempts (ip, email) VALUES (?, ?)")->execute([$ip, $email]);
        pb_db()->exec("DELETE FROM $attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY");
        throw new InvalidArgumentException(__('E-mail ou senha incorretos.'));
    }

    pb_db()->prepare("DELETE FROM $attempts WHERE ip = ? AND email = ?")->execute([$ip, $email]);
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    return $user;
}

function pb_login_failures(string $email, string $ip): int
{
    $st = pb_db()->prepare('SELECT COUNT(*) FROM ' . pb_table('login_attempts')
        . ' WHERE ip = ? AND email = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$ip, $email]);
    return (int) $st->fetchColumn();
}

// ------------------------------------------------------------------ forgot my password

/**
 * When $email has an account, e-mails it a link to choose a new password: it works once, for an hour, and only
 * its hash is stored. The answer is the same whether the e-mail exists or not, so nobody can find out who has an
 * account. Throws only when this IP asked too often (requests count like wrong passwords).
 */
function pb_password_reset_request(string $email, string $ip): void
{
    $email = substr(strtolower(trim($email)), 0, 190);
    $ip = substr($ip, 0, 45);
    $attempts = pb_table('login_attempts');
    if (pb_login_failures('#reset', $ip) >= PB_LOGIN_MAX_FAILURES) { // '#reset': a key no e-mail can be
        throw new InvalidArgumentException(__('Muitas tentativas. Aguarde 15 minutos e tente de novo.'));
    }
    pb_db()->prepare("INSERT INTO $attempts (ip, email) VALUES (?, '#reset')")->execute([$ip]);
    pb_db()->exec("DELETE FROM $attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY");

    $user = pb_find_user_by_email($email);
    if (!$user) {
        return;
    }
    // ponytail: sending takes longer than not sending, so timing could hint at an account; queue the e-mail if that matters.
    $token = bin2hex(random_bytes(32));
    pb_db()->prepare('REPLACE INTO ' . pb_table('password_resets') . ' (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)')
        ->execute([$user['id'], hash('sha256', $token)]);
    $site = pb_option('site_title', 'PageBrick');
    try {
        pb_mail($user['email'], sprintf(__('Criar uma senha nova no painel de %s'), $site), sprintf(
            __("Olá, %1\$s.\n\nAlguém pediu para criar uma senha nova para a sua conta no painel de %2\$s. Para criar, abra este link em até 1 hora:\n\n%3\$s\n\nSe não foi você, ignore este e-mail: a sua senha continua a mesma."),
            $user['name'], $site, pb_absolute_url('/admin/reset?token=' . $token)
        ));
    } catch (RuntimeException $e) {
        error_log("PageBrick: password reset e-mail not sent: {$e->getMessage()}"); // the answer stays the same
    }
}

/** The user a password reset link belongs to, while the link still works; null otherwise. */
function pb_password_reset_user(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    $st = pb_db()->prepare('SELECT user_id FROM ' . pb_table('password_resets') . ' WHERE token_hash = ? AND expires_at > NOW()');
    $st->execute([hash('sha256', $token)]);
    $id = $st->fetchColumn();
    return $id ? pb_find_user((int) $id) : null;
}

/** Saves the new password of a reset link and spends the link. Throws with a message the user can read. */
function pb_password_reset(string $token, string $password, string $repeat): array
{
    $user = pb_password_reset_user($token)
        ?? throw new InvalidArgumentException(__('Este link não vale mais: ele só pode ser usado uma vez e tem prazo (1 hora para trocar a senha, 7 dias para um convite). Peça um novo.'));
    if (strlen($password) < 8) {
        throw new InvalidArgumentException(__('A senha precisa ter pelo menos 8 caracteres.'));
    }
    if ($password !== $repeat) {
        throw new InvalidArgumentException(__('As duas senhas não são iguais.'));
    }
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET password_hash = ?, invited_at = NULL WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    pb_db()->prepare('DELETE FROM ' . pb_table('password_resets') . ' WHERE user_id = ?')->execute([$user['id']]);
    pb_db()->prepare('DELETE FROM ' . pb_table('login_attempts') . ' WHERE email = ?')->execute([$user['email']]); // wrong guesses before don't count
    return $user; // as it was before the change: 'invited_at' tells whether the link was an invitation
}

// ------------------------------------------------------------------ invitations

/** How long the link of an invitation works: a person may take days to open the e-mail. */
const PB_INVITE_DAYS = 7;

/**
 * Makes the account of a person who will choose their own password. It starts with one nobody knows, and the link e-mailed to them (one use, a week,
 * only its hash kept) is the way in. Returns what pb_send_invite() returns.
 */
function pb_invite_user(string $name, string $email, string $role, string $invitedBy): array
{
    $id = pb_create_user($name, $email, bin2hex(random_bytes(16)), $role);
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET invited_at = NOW() WHERE id = ?')->execute([$id]);
    return pb_send_invite($id, $invitedBy);
}

/**
 * Sends (again) the invitation of a person who has not chosen a password yet: a new link, and the old one stops working. Returns
 * ['user' => the person, 'link' => the address, 'sent' => whether the e-mail left]; the link comes back either way, to hand over some other way.
 */
function pb_send_invite(int $userId, string $invitedBy): array
{
    $user = pb_find_user($userId);
    if (!$user || empty($user['invited_at'])) {
        throw new InvalidArgumentException(__('Esta pessoa já criou a senha: não há convite para enviar.'));
    }
    $token = bin2hex(random_bytes(32));
    pb_db()->prepare('REPLACE INTO ' . pb_table('password_resets') . ' (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ' . PB_INVITE_DAYS . ' DAY)')
        ->execute([$userId, hash('sha256', $token)]);
    $link = pb_absolute_url('/admin/reset?token=' . $token);
    $site = pb_option('site_title', 'PageBrick');
    $sent = true;
    $previous = pb_locale();
    pb_set_locale(pb_site_locale()); // the e-mail speaks the language the person will find in the panel
    try {
        pb_mail($user['email'], sprintf(__('Convite para o painel de %s'), $site), sprintf(
            __("Olá, %1\$s.\n\n%2\$s convidou você para o painel de %3\$s, como %4\$s. Para criar a sua senha e entrar, abra este link em até %5\$d dias:\n\n%6\$s\n\nSe você não esperava este convite, ignore este e-mail: nada acontece enquanto o link não for aberto."),
            $user['name'], $invitedBy, $site, pb_role_labels()[$user['role']] ?? $user['role'], PB_INVITE_DAYS, $link
        ));
    } catch (RuntimeException $e) {
        error_log("PageBrick: invitation e-mail not sent: {$e->getMessage()}");
        $sent = false;
    } finally {
        pb_set_locale($previous);
    }
    return ['user' => $user, 'link' => $link, 'sent' => $sent];
}
