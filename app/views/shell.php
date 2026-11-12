<?php
declare(strict_types=1);

// The app shell lives on /app. It is rendered without a user identity: the
// browser only carries the token in localStorage, so the SPA loads /api/me.
$isApp     = request_path() === '/app';
$nonce     = csp_nonce();
$name      = (string) config('app.name', 'DiarKeep');
$pageTitle = $isApp
    ? $name . ' — Catatanmu'
    : $name . ' — Catatan pribadi di server sendiri';

$clientConfig = [
    'base'      => base_path(),
    'uploadMax' => (int) config('app.upload_max'),
    'version'   => '1.1.0',
];
?>
<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f5efe4">
    <meta name="description" content="DiarKeep — papan catatan ringan seperti Google Keep yang berjalan di server milikmu sendiri. Checklist, label, gambar, arsip, tanpa iklan.">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="Catatan pribadi, checklist, dan gambar di server sendiri. Ringan untuk server kecil.">
    <meta property="og:type" content="website">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e(url('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= e(url('/assets/favicon.svg')) ?>">
    <script nonce="<?= e($nonce) ?>">
        (function () {
            try {
                var theme = localStorage.getItem('diarkeep.theme');
                if (theme !== 'dark' && theme !== 'light') {
                    theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', theme);
                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) meta.setAttribute('content', theme === 'dark' ? '#15130f' : '#f5efe4');
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/app.css')) ?>">
    <script nonce="<?= e($nonce) ?>">
        window.DIAR = <?= json_encode($clientConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    </script>
</head>
<body class="<?= $isApp ? 'is-app' : 'is-landing' ?>">
    <?php require __DIR__ . '/icons.php'; ?>
    <?php require __DIR__ . ($isApp ? '/app.php' : '/landing.php'); ?>

    <noscript>
        <div class="noscript-banner">DiarKeep butuh JavaScript untuk editor catatannya. Aktifkan JavaScript lalu muat ulang halaman.</div>
    </noscript>

    <script src="<?= e(asset_url($isApp ? '/assets/app.js' : '/assets/landing.js')) ?>" nonce="<?= e($nonce) ?>" defer></script>
</body>
</html>
