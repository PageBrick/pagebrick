<?php
// A tiny server for HttpTest (php -S): answers what pb_http() is asked, so the real code path is exercised.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/ok') {
    header('Content-Type: application/json');
    echo json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'key' => $_SERVER['HTTP_X_API_KEY'] ?? null, 'body' => file_get_contents('php://input')]);
} elseif ($path === '/redirect') {
    header('Location: /ok', true, 302);
} elseif ($path === '/denied') {
    http_response_code(403);
    echo '{"error":"no"}';
} elseif ($path === '/big') {
    echo str_repeat('x', 3 * 1024 * 1024);
} else {
    http_response_code(404);
}
