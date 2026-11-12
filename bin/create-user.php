#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/app/helpers.php';
require ROOT_PATH . '/app/db.php';

date_default_timezone_set('UTC');

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function prompt_password(string $label): string
{
    $env = getenv('DIAR_PASSWORD');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    $hidden = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec') && @stream_isatty(STDIN);

    fwrite(STDOUT, $label);
    if ($hidden) {
        @shell_exec('stty -echo');
    }

    $value = trim((string) fgets(STDIN));

    if ($hidden) {
        @shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }

    return $value;
}

$name  = trim((string) ($argv[1] ?? ''));
$email = mb_strtolower(trim((string) ($argv[2] ?? '')));

if ($name === '' || $email === '') {
    fail('Penggunaan: php bin/create-user.php "Nama" email@contoh.id');
}

if (!is_file(ROOT_PATH . '/config/config.php')) {
    fail('config/config.php belum ada. Salin dari config/config.example.php terlebih dahulu.');
}

if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
    fail('Nama harus 2-80 karakter.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    fail('Alamat email tidak valid.');
}

$password = prompt_password('Kata sandi (min. 8 karakter): ');

if (strlen($password) < 8 || strlen($password) > 200) {
    fail('Kata sandi harus 8-200 karakter.');
}

if (getenv('DIAR_PASSWORD') === false) {
    $confirm = prompt_password('Ulangi kata sandi: ');

    if (!hash_equals($password, $confirm)) {
        fail('Kata sandi tidak sama.');
    }
}

try {
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        fail('Email itu sudah terdaftar.');
    }
    fail('Gagal membuat akun: ' . $e->getMessage());
}

fwrite(STDOUT, 'Akun dibuat untuk ' . $email . PHP_EOL);
