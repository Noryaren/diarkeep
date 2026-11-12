<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

date_default_timezone_set('UTC');

ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Stateless auth: no session_start(), no session cookie. The client sends
// "Authorization: Bearer <token>" on every API request instead.

if (!headers_sent()) {
    $nonce = csp_nonce();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "img-src 'self' data: blob:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self' 'nonce-{$nonce}'; "
        . "connect-src 'self'; "
        . "font-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'"
    );
}

final class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
