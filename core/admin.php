<?php
// The admin panel (/admin): routes and screens.

function pb_admin(string $method, string $path): void
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');

    // "METHOD /path" => [minimum role or null for public, handler]
    $routes = [
        'GET /admin/login' => [null, 'pb_admin_login_form'],
        'POST /admin/login' => [null, 'pb_admin_login'],
        'POST /admin/logout' => ['editor', 'pb_admin_logout'],
        'GET /admin' => ['editor', 'pb_admin_dashboard'],
        'GET /admin/users' => ['admin', 'pb_admin_users'],
        'POST /admin/users' => ['admin', 'pb_admin_users_create'],
        'POST /admin/users/delete' => ['admin', 'pb_admin_users_delete'],
    ];
    [$role, $handler] = $routes["$method $path"] ?? [null, 'pb_admin_not_found'];

    if ($role !== null) {
        $user = pb_current_user() ?? pb_redirect('/admin/login');
        if (!pb_has_role($user, $role)) {
            http_response_code(403);
            pb_render('message', ['title' => __('Acesso negado'), 'message' => __('Sua conta não tem permissão para esta área.')]);
            return;
        }
    }
    $handler();
}

function pb_admin_not_found(): void
{
    http_response_code(404);
    pb_render('message', ['title' => __('Página não encontrada'), 'message' => __('Este endereço não existe no painel.')]);
}

function pb_admin_login_form(): void
{
    if (pb_current_user()) {
        pb_redirect('/admin');
    }
    pb_render('login', ['title' => __('Entrar')]);
}

function pb_admin_login(): void
{
    try {
        $user = pb_login(pb_post('email'), pb_post('password'), $_SERVER['REMOTE_ADDR'] ?? '');
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_render('login', ['title' => __('Entrar'), 'error' => $e->getMessage(), 'email' => pb_post('email')]);
        return;
    }
    session_regenerate_id(true); // new session id after login: blocks session fixation
    $_SESSION['user_id'] = (int) $user['id'];
    pb_redirect('/admin');
}

function pb_admin_logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    pb_redirect('/admin/login');
}

function pb_admin_dashboard(): void
{
    pb_render('dashboard', [
        'title' => __('Painel'),
        'user' => pb_current_user(),
        'siteTitle' => pb_option('site_title', 'PageBrick'),
    ]);
}

function pb_admin_users(?string $error = null): void
{
    pb_render('users', [
        'title' => __('Usuários'),
        'users' => pb_list_users(),
        'current' => pb_current_user(),
        'error' => $error,
        'old' => $error ? ['name' => pb_post('name'), 'email' => pb_post('email'), 'role' => pb_post('role')] : [],
    ]);
}

function pb_admin_users_create(): void
{
    try {
        pb_create_user(pb_post('name'), pb_post('email'), pb_post('password'), pb_post('role'));
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        pb_admin_users($e->getMessage());
        return;
    }
    pb_flash('ok', __('Usuário criado.'));
    pb_redirect('/admin/users');
}

function pb_admin_users_delete(): void
{
    try {
        pb_delete_user((int) pb_post('id'), (int) pb_current_user()['id']);
        pb_flash('ok', __('Usuário excluído.'));
    } catch (InvalidArgumentException $e) {
        pb_flash('error', $e->getMessage());
    }
    pb_redirect('/admin/users');
}
