<?php
declare(strict_types=1);

/**
 * Stateless authentication: JWT (HS256) sent as "Authorization: Bearer <token>".
 * Nothing is stored server side (no session, no cookie, no CSRF token).
 */

function jwt_secret(): string
{
    static $secret = null;

    if ($secret !== null) {
        return $secret;
    }

    $configured = config('app.jwt_secret');
    if (is_string($configured) && trim($configured) !== '') {
        $secret = trim($configured);
        return $secret;
    }

    $file = ROOT_PATH . '/storage/jwt.secret';

    if (is_file($file)) {
        $existing = trim((string) file_get_contents($file));
        if ($existing !== '') {
            $secret = $existing;
            return $secret;
        }
    }

    $secret = bin2hex(random_bytes(32));

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }

    if (@file_put_contents($file, $secret, LOCK_EX) === false) {
        error_log('DiarKeep: tidak bisa menyimpan secret JWT di ' . $file);
    } else {
        @chmod($file, 0600);
    }

    return $secret;
}

function token_ttl(): int
{
    $ttl = (int) config('app.token_ttl', 31536000);
    return $ttl > 0 ? $ttl : 31536000;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $b64 = strtr($data, '-_', '+/');
    $pad = strlen($b64) % 4;

    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }

    return (string) base64_decode($b64, true);
}

function issue_token(int $userId): string
{
    $now = time();

    $header = base64url_encode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $claims = base64url_encode((string) json_encode([
        'iss' => 'diarkeep',
        'sub' => $userId,
        'iat' => $now,
        'exp' => $now + token_ttl(),
        'jti' => bin2hex(random_bytes(16)),
    ]));

    $signature = hash_hmac('sha256', $header . '.' . $claims, jwt_secret(), true);

    return $header . '.' . $claims . '.' . base64url_encode($signature);
}

/**
 * Validate signature + expiry. Returns the claims array or null when invalid.
 */
function token_claims(?string $token): ?array
{
    if ($token === null || $token === '' || strlen($token) > 4096) {
        return null;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$rawHeader, $rawClaims, $rawSignature] = $parts;
    if ($rawHeader === '' || $rawClaims === '' || $rawSignature === '') {
        return null;
    }

    $header = json_decode(base64url_decode($rawHeader), true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
        return null;
    }

    $expected = base64url_encode(hash_hmac('sha256', $rawHeader . '.' . $rawClaims, jwt_secret(), true));
    if (!hash_equals($expected, $rawSignature)) {
        return null;
    }

    $claims = json_decode(base64url_decode($rawClaims), true);
    if (!is_array($claims)) {
        return null;
    }

    if (!isset($claims['exp']) || !is_numeric($claims['exp']) || time() >= (int) $claims['exp']) {
        return null;
    }

    if (isset($claims['nbf']) && is_numeric($claims['nbf']) && time() < (int) $claims['nbf']) {
        return null;
    }

    if (!isset($claims['sub'])) {
        return null;
    }

    return $claims;
}

function bearer_token(): ?string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if ($header === '') {
        $header = (string) ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    }

    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = (string) $value;
                    break;
                }
            }
        }
    }

    $token = preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) === 1 ? $matches[1] : null;

    return $token;
}

function current_user(): ?array
{
    if (array_key_exists('diarkeep_current_user', $GLOBALS)) {
        return $GLOBALS['diarkeep_current_user'];
    }

    $user  = null;
    $claims = token_claims(bearer_token());

    if ($claims !== null) {
        $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $stmt->execute([(int) $claims['sub']]);
        $row = $stmt->fetch();

        if ($row) {
            $row['id'] = (int) $row['id'];
            $user      = $row;
        }
    }

    $GLOBALS['diarkeep_current_user'] = $user;

    return $user;
}

/**
 * Middleware: every protected endpoint must carry a valid bearer token.
 */
function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        header('WWW-Authenticate: Bearer realm="DiarKeep", error="invalid_token"');
        throw new ApiException('Sesi kamu sudah kedaluwarsa. Silakan masuk kembali.', 401);
    }
    return $user;
}

/**
 * Issue a fresh token for a user and drop any cached identity for this request.
 */
function login_user(int $userId): string
{
    unset($GLOBALS['diarkeep_current_user']);

    return issue_token($userId);
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
