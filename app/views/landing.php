<?php
declare(strict_types=1);
?>
<header class="site-nav">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="DiarKeep, ke beranda">
        <span class="brand-mark"><img src="<?= e(url('/assets/logo.svg')) ?>" alt="" width="30" height="30"></span>
        <span class="brand-word">Diar<strong>Keep</strong></span>
    </a>

    <nav class="nav-links" aria-label="Navigasi utama">
        <a href="#fitur">Fitur</a>
        <a href="#alur">Cara pakai</a>
        <a href="#privasi">Privasi</a>
    </nav>

    <div class="nav-actions">
        <button class="icon-btn" type="button" data-theme-toggle aria-label="Ganti tema">
            <svg class="icon icon-sun"><use href="#i-sun"></use></svg>
            <svg class="icon icon-moon"><use href="#i-moon"></use></svg>
        </button>
        <button class="btn btn-primary" type="button" data-auth="login">Masuk</button>
    </div>
</header>

<main class="landing">
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Self-hosted · ramah server kecil</p>
            <h1>Ide datang cepat.<br><em>Simpan sebelum hilang.</em></h1>
            <p class="hero-lead">
                DiarKeep adalah papan catatan yang ringan—seperti Google Keep, tapi tinggal di
                server milikmu sendiri. Checklist, label, gambar, arsip. Tanpa langganan,
                tanpa iklan, tanpa data yang keluar dari rumah.
            </p>

            <div class="hero-actions">
                <button class="btn btn-primary btn-lg" type="button" data-auth="login">
                    Masuk ke catatan
                    <svg class="icon"><use href="#i-arrow-right"></use></svg>
                </button>
                <a class="btn btn-outline btn-lg" href="#fitur">Lihat fiturnya</a>
            </div>

            <ul class="hero-meta">
                <li><svg class="icon"><use href="#i-zap"></use></svg> PHP 8.5 + MariaDB</li>
                <li><svg class="icon"><use href="#i-server"></use></svg> Jalan di RAM 2 GB</li>
                <li><svg class="icon"><use href="#i-lock"></use></svg> Data milikmu</li>
            </ul>
        </div>

        <div class="hero-visual" aria-hidden="true">
            <div class="hero-glow"></div>

            <article class="mock-note mock-yellow mock-a">
                <div class="mock-pin"><svg class="icon"><use href="#i-pin"></use></svg></div>
                <p class="mock-kicker">Rencana minggu ini</p>
                <ul class="mock-checks">
                    <li class="done"><span class="mock-check"><svg class="icon"><use href="#i-check"></use></svg></span> Rapikan meja kerja</li>
                    <li class="done"><span class="mock-check"><svg class="icon"><use href="#i-check"></use></svg></span> Backup catatan lama</li>
                    <li><span class="mock-check"></span> Tulis catatan rilis</li>
                </ul>
                <div class="mock-labels"><span>kerja</span><span>mingguan</span></div>
            </article>

            <article class="mock-note mock-blue mock-b">
                <div class="mock-image-placeholder">
                    <span class="mock-sun"></span>
                    <span class="mock-hill mock-hill-1"></span>
                    <span class="mock-hill mock-hill-2"></span>
                </div>
                <p class="mock-title">Ide nama proyek</p>
                <p class="mock-body">DiarKeep — catatan yang tinggal di rumah, bukan di cloud orang lain.</p>
            </article>

            <article class="mock-note mock-green mock-c">
                <p class="mock-title">Resep kopi pagi</p>
                <p class="mock-body">18 g biji · 240 ml air · 92°C.<br>Tuang perlahan, nikmati pelan-pelan.</p>
                <div class="mock-labels"><span>resep</span></div>
            </article>

            <article class="mock-note mock-pink mock-d">
                <p class="mock-quote">“Catat dulu.<br>Rapikan nanti.<br>Tenang kemudian.”</p>
            </article>

            <div class="server-chip">
                <span class="server-chip-dot"></span>
                <div>
                    <strong>serverdiar</strong>
                    <span>online · 1.2 GB terpakai</span>
                </div>
                <svg class="icon"><use href="#i-server"></use></svg>
            </div>
        </div>
    </section>

    <section class="trust-strip" aria-label="Ringkasan">
        <div><strong>0</strong><span>langganan</span></div>
        <div><strong>1</strong><span>server sendiri</span></div>
        <div><strong>∞</strong><span>catatan</span></div>
        <div><strong>100%</strong><span>data milikmu</span></div>
    </section>

    <section class="section features" id="fitur">
        <div class="section-head">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Semua yang kamu butuhkan</p>
            <h2>Rapi tanpa terasa ribet.</h2>
            <p>Fitur yang cukup untuk menangkap isi kepala, tanpa membuat server kecil kepayahan.</p>
        </div>

        <div class="feature-grid">
            <article class="feature-card">
                <span class="feature-icon feature-icon-amber"><svg class="icon"><use href="#i-note"></use></svg></span>
                <h3>Catatan &amp; checklist</h3>
                <p>Tulis bebas atau pecah jadi daftar centang. Keduanya bisa hidup dalam satu papan yang sama.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon feature-icon-rose"><svg class="icon"><use href="#i-tag"></use></svg></span>
                <h3>Label warna</h3>
                <p>Kelompokkan catatan dengan label. Filter sekali klik, tanpa perlu folder bertingkat.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon feature-icon-blue"><svg class="icon"><use href="#i-image"></use></svg></span>
                <h3>Gambar di catatan</h3>
                <p>Lampirkan foto atau tangkapan layar. Dioptimalkan otomatis supaya hemat ruang dan memori.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon feature-icon-teal"><svg class="icon"><use href="#i-search"></use></svg></span>
                <h3>Cari kilat</h3>
                <p>Cari di judul, isi, checklist, sampai nama label. Tekan <kbd>/</kbd> dan langsung ketik.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon feature-icon-purple"><svg class="icon"><use href="#i-pin"></use></svg></span>
                <h3>Pin, arsip, sampah</h3>
                <p>Sematkan yang penting, arsipkan yang selesai, dan biarkan sampah menunggu sebelum hilang.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon feature-icon-slate"><svg class="icon"><use href="#i-moon"></use></svg></span>
                <h3>Hemat &amp; responsif</h3>
                <p>Tanpa framework berat. Tema gelap, mobile-friendly, dan cepat dibuka di jaringan rumah.</p>
            </article>
        </div>
    </section>

    <section class="section steps" id="alur">
        <div class="section-head">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Tiga langkah</p>
            <h2>Dari server nganggur ke papan catatan.</h2>
        </div>

        <div class="step-grid">
            <article class="step-card">
                <span class="step-number">01</span>
                <h3>Pasang</h3>
                <p>Letakkan folder DiarKeep, arahkan Caddy ke <code>public/</code>, lalu impor skema MariaDB.</p>
            </article>
            <article class="step-card">
                <span class="step-number">02</span>
                <h3>Masuk</h3>
                <p>Masuk dengan akun yang dibuat di server. Setiap akun punya catatan, label, dan lampiran yang terpisah.</p>
            </article>
            <article class="step-card">
                <span class="step-number">03</span>
                <h3>Menempel</h3>
                <p>Mulai dari satu catatan. Sematkan yang sering dibuka, arsipkan yang sudah selesai.</p>
            </article>
        </div>
    </section>

    <section class="privacy" id="privasi">
        <div class="privacy-copy">
            <p class="eyebrow"><span class="eyebrow-dot"></span> Privasi sejak awal</p>
            <h2>Catatanmu tidak perlu ikut ke cloud orang lain.</h2>
            <p>
                Semua disimpan di MariaDB milikmu. Kata sandi di-hash, akses divalidasi per akun,
                dan setiap permintaan tulis dilindungi token CSRF.
            </p>
            <ul class="privacy-list">
                <li><svg class="icon"><use href="#i-lock"></use></svg> Kata sandi memakai <code>password_hash</code> bawaan PHP.</li>
                <li><svg class="icon"><use href="#i-shield"></use></svg> Catatan terisolasi per pengguna dan dilindungi CSRF.</li>
                <li><svg class="icon"><use href="#i-upload"></use></svg> Gambar diperkecil di server, metadata dibuang.</li>
                <li><svg class="icon"><use href="#i-server"></use></svg> Tanpa layanan pihak ketiga, tanpa telemetri.</li>
            </ul>
        </div>

        <div class="privacy-panel" aria-hidden="true">
            <div class="privacy-row"><span class="privacy-dot green"></span> Koneksi lokal <strong>aktif</strong></div>
            <div class="privacy-row"><span class="privacy-dot green"></span> Database MariaDB <strong>sehat</strong></div>
            <div class="privacy-row"><span class="privacy-dot amber"></span> Sinkronisasi cloud <strong>tidak ada</strong></div>
            <div class="privacy-row"><span class="privacy-dot green"></span> Pemilik data <strong>kamu</strong></div>
            <div class="privacy-note">
                <svg class="icon"><use href="#i-feather"></use></svg>
                <span>Dibuat untuk server kecil yang masih punya ruang.</span>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div>
            <p class="eyebrow"><span class="eyebrow-dot"></span> Siap dipakai</p>
            <h2>Beri server nganggurmu pekerjaan yang berguna.</h2>
        </div>
        <div class="cta-actions">
            <button class="btn btn-primary btn-lg" type="button" data-auth="login">
                Masuk ke catatan
                <svg class="icon"><use href="#i-arrow-right"></use></svg>
            </button>
            <a class="btn btn-outline btn-lg" href="#fitur">Lihat fitur</a>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="footer-brand">
        <span class="brand-mark"><img src="<?= e(url('/assets/logo.svg')) ?>" alt="" width="26" height="26"></span>
        <div>
            <strong>DiarKeep</strong>
            <p>Catatan pribadi, di server sendiri.</p>
        </div>
    </div>
    <p class="footer-note">PHP 8.5 · MariaDB · Caddy · dibuat untuk server kecil</p>
</footer>

<div class="auth-modal" id="authModal" aria-hidden="true">
    <div class="auth-backdrop" data-auth-close></div>
    <div class="auth-dialog" role="dialog" aria-modal="true" aria-labelledby="authTitle">
        <button class="icon-btn auth-close" type="button" data-auth-close aria-label="Tutup">
            <svg class="icon"><use href="#i-x"></use></svg>
        </button>

        <div class="auth-brand">
            <span class="brand-mark"><img src="<?= e(url('/assets/logo.svg')) ?>" alt="" width="34" height="34"></span>
            <span class="brand-word">Diar<strong>Keep</strong></span>
        </div>

        <h2 id="authTitle">Selamat datang kembali</h2>
        <p class="auth-subtitle" id="authSubtitle">Masuk untuk membuka papan catatanmu.</p>

        <form class="auth-form" id="loginForm" novalidate>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" autocomplete="email" placeholder="kamu@contoh.id" required>
            </label>
            <label class="field">
                <span>Kata sandi</span>
                <input type="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
            </label>
            <p class="form-error" data-auth-error hidden></p>
            <button class="btn btn-primary btn-block" type="submit">
                <span class="btn-label">Masuk</span>
            </button>
        </form>

        <p class="auth-foot">
            <svg class="icon"><use href="#i-lock"></use></svg>
            Data tersimpan di server ini. Akun dibuat oleh pemilik server.
        </p>
    </div>
</div>
