<?php
declare(strict_types=1);

/**
 * Load configuration once. A local config/config.php overrides the defaults.
 */
function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $config = [
            'app' => [
                'name'        => getenv('APP_NAME') ?: 'DiarKeep',
                'debug'       => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL),
                'timezone'    => 'UTC',
                'base_path'   => null,
                'trust_proxy' => filter_var(getenv('TRUST_PROXY') ?: '1', FILTER_VALIDATE_BOOL),
                'upload_max'  => 5 * 1024 * 1024,
                'upload_dir'  => ROOT_PATH . '/public/uploads',
            ],
            'db' => [
                'dsn'  => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=diarkeep;charset=utf8mb4',
                'user' => getenv('DB_USER') ?: 'diarkeep',
                'pass' => getenv('DB_PASS') ?: '',
            ],
        ];

        $file = ROOT_PATH . '/config/config.php';
        if (is_file($file)) {
            $local = require $file;
            if (is_array($local)) {
                $config = array_replace_recursive($config, $local);
            }
        }
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Path prefix when the app is mounted in a sub-directory. Empty string at root.
 */
function base_path(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $configured = config('app.base_path');
    if ($configured !== null) {
        $base = rtrim('/' . trim((string) $configured, '/'), '/');
        if ($base === '/') {
            $base = '';
        }
        return $base;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // PHP's built-in server reports the requested static file as SCRIPT_NAME,
    // which would be mistaken for a sub-directory mount.
    if (PHP_SAPI === 'cli-server' || $script === '' || !str_ends_with($script, '.php')) {
        return '';
    }

    $dir  = str_replace('\\', '/', dirname($script));
    $base = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');

    return $base;
}

function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return base_path() . ($path === '/' ? '/' : $path);
}

function upload_url(?string $filename): string
{
    if ($filename === null || $filename === '') {
        return '';
    }
    return url('/uploads/' . rawurlencode($filename));
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (config('app.trust_proxy')) {
        $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto === 'https') {
            return true;
        }

        $cf = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
        if ($cf !== '' && str_contains($cf, '"scheme":"https"')) {
            return true;
        }
    }

    return false;
}

function csp_nonce(): string
{
    if (empty($GLOBALS['diarkeep_nonce'])) {
        $GLOBALS['diarkeep_nonce'] = base64_encode(random_bytes(18));
    }
    return $GLOBALS['diarkeep_nonce'];
}

function iso_datetime(?string $datetime): ?string
{
    if ($datetime === null || $datetime === '') {
        return null;
    }
    return str_replace(' ', 'T', $datetime) . 'Z';
}

function sqlish(PDO $pdo): string
{
    return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/**
 * Escape a user supplied search term for a SQL LIKE clause.
 */
function like_term(string $term): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
}
