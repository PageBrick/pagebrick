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
        pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
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
    pb_db()->prepare('DELETE FROM ' . pb_table('users') . ' WHERE id = ?')->execute([$id]);
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
    return pb_db()->query('SELECT id, name, email, role FROM ' . pb_table('users') . ' ORDER BY name')->fetchAll();
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
        ?? throw new InvalidArgumentException(__('Este link não vale mais: ele dura 1 hora e só pode ser usado uma vez. Peça um novo.'));
    if (strlen($password) < 8) {
        throw new InvalidArgumentException(__('A senha precisa ter pelo menos 8 caracteres.'));
    }
    if ($password !== $repeat) {
        throw new InvalidArgumentException(__('As duas senhas não são iguais.'));
    }
    pb_db()->prepare('UPDATE ' . pb_table('users') . ' SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    pb_db()->prepare('DELETE FROM ' . pb_table('password_resets') . ' WHERE user_id = ?')->execute([$user['id']]);
    pb_db()->prepare('DELETE FROM ' . pb_table('login_attempts') . ' WHERE email = ?')->execute([$user['email']]); // wrong guesses before don't count
    return $user;
}
