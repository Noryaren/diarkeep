<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (array_key_exists('diarkeep_current_user', $GLOBALS)) {
        return $GLOBALS['diarkeep_current_user'];
    }

    $user = null;
    $id   = $_SESSION['user_id'] ?? null;

    if ($id !== null) {
        $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();

        if ($row) {
            $row['id'] = (int) $row['id'];
            $user      = $row;
        } else {
            unset($_SESSION['user_id']);
        }
    }

    $GLOBALS['diarkeep_current_user'] = $user;

    return $user;
}

function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        throw new ApiException('Silakan masuk terlebih dahulu.', 401);
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    unset($_SESSION['csrf'], $_SESSION['login_attempts']);

    unset($GLOBALS['diarkeep_current_user']);
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
    unset($GLOBALS['diarkeep_current_user']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');

    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        throw new ApiException('Sesi kamu sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.', 419);
    }
}

function user_initials(array $user): string
{
    $name  = trim((string) ($user['name'] ?? ''));
    $parts = preg_split('/\s+/', $name) ?: [];
    $parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));

    if (count($parts) === 0) {
        return mb_strtoupper(mb_substr((string) ($user['email'] ?? '?'), 0, 1));
    }

    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

    return mb_strtoupper($first . $last);
}
