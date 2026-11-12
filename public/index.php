<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri    = request_path();

if ($uri === '/api' || str_starts_with($uri, '/api/')) {
    require __DIR__ . '/../app/api.php';
    api_dispatch($method, substr($uri, 4) ?: '/');
    exit;
}

if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

// Let PHP's built-in server serve static files while developing.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . $uri;
    if ($uri !== '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../app/views/shell.php';
