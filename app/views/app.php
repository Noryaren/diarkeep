<?php
declare(strict_types=1);
?>
<header class="appbar">
    <div class="appbar-left">
        <button class="icon-btn only-mobile" type="button" id="menuBtn" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
            <svg class="icon"><use href="#i-menu"></use></svg>
        </button>
        <a class="brand" href="<?= e(url('/app')) ?>" aria-label="DiarKeep">
            <span class="brand-mark"><img src="<?= e(url('/assets/logo.svg')) ?>" alt="" width="30" height="30"></span>
            <span class="brand-word">Diar<strong>Keep</strong></span>
        </a>
    </div>

    <div class="search-box" role="search">
        <svg class="icon search-icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" id="searchInput" placeholder="Cari catatan..." aria-label="Cari catatan" autocomplete="off" spellcheck="false">
        <button class="search-clear" type="button" id="searchClear" aria-label="Bersihkan pencarian" hidden>
            <svg class="icon"><use href="#i-x"></use></svg>
        </button>
        <kbd class="search-kbd">/</kbd>
    </div>

    <div class="appbar-right">
        <button class="icon-btn" type="button" data-theme-toggle aria-label="Ganti tema">
            <svg class="icon icon-sun"><use href="#i-sun"></use></svg>
            <svg class="icon icon-moon"><use href="#i-moon"></use></svg>
        </button>

        <button class="btn btn-primary new-note-btn" type="button" id="newNoteBtn">
            <svg class="icon"><use href="#i-plus"></use></svg>
            <span>Catatan baru</span>
        </button>

        <div class="user-menu">
            <button class="avatar" type="button" id="userBtn" aria-label="Menu akun" aria-haspopup="true" aria-expanded="false">
                <span data-user-initials>··</span>
            </button>
            <div class="menu-popover user-popover" id="userPopover" hidden>
                <div class="user-card">
                    <span class="avatar avatar-lg" data-user-initials>··</span>
                    <div>
                        <strong data-user-name>…</strong>
                        <span data-user-email>…</span>
                    </div>
                </div>
                <div class="menu-separator"></div>
                <button class="menu-item" type="button" data-action="logout">
                    <svg class="icon"><use href="#i-logout"></use></svg>
                    Keluar
                </button>
            </div>
        </div>
    </div>
</header>

<aside class="sidebar" id="sidebar" aria-label="Navigasi catatan">
    <nav class="sidebar-nav">
        <button class="nav-item is-active" type="button" data-view="notes">
            <svg class="icon"><use href="#i-note"></use></svg>
            <span>Catatan</span>
            <span class="nav-count" id="countNotes">0</span>
        </button>
        <button class="nav-item" type="button" data-view="archive">
            <svg class="icon"><use href="#i-archive"></use></svg>
            <span>Arsip</span>
            <span class="nav-count" id="countArchive">0</span>
        </button>
        <button class="nav-item" type="button" data-view="trash">
            <svg class="icon"><use href="#i-trash"></use></svg>
            <span>Sampah</span>
            <span class="nav-count" id="countTrash">0</span>
        </button>
    </nav>

    <div class="sidebar-section">
        <div class="sidebar-heading">
            <span>Label</span>
            <button class="icon-btn icon-btn-sm" type="button" id="addLabelBtn" aria-label="Tambah label">
                <svg class="icon"><use href="#i-plus"></use></svg>
            </button>
        </div>
        <div class="label-list" id="labelList"></div>
        <p class="label-empty" id="labelEmpty">Belum ada label. Buat satu untuk mulai merapikan.</p>
    </div>

    <div class="sidebar-foot">
        <div class="tip-card">
            <svg class="icon"><use href="#i-feather"></use></svg>
            <div>
                <strong>Tip cepat</strong>
                <p>Tekan <kbd>n</kbd> untuk catatan baru, <kbd>/</kbd> untuk mencari.</p>
            </div>
        </div>
    </div>
</aside>

<div class="mobile-overlay" id="mobileOverlay" hidden></div>

<main class="main" id="mainContent">
    <div class="page-head">
        <div>
            <h1 id="pageTitle">Catatan</h1>
            <p id="pageSubtitle">Semua catatanmu, dalam satu papan.</p>
        </div>
        <div class="page-actions">
            <button class="btn btn-outline btn-sm danger-outline" type="button" id="emptyTrashBtn" hidden>
                <svg class="icon"><use href="#i-trash-x"></use></svg>
                Kosongkan sampah
            </button>
        </div>
    </div>

    <button class="composer" type="button" id="composerBtn" aria-label="Buat catatan baru">
        <span class="composer-placeholder">Buat catatan...</span>
        <span class="composer-tools" aria-hidden="true">
            <span class="composer-tool"><svg class="icon"><use href="#i-list"></use></svg></span>
            <span class="composer-tool"><svg class="icon"><use href="#i-image"></use></svg></span>
            <span class="composer-tool"><svg class="icon"><use href="#i-palette"></use></svg></span>
        </span>
    </button>

    <div class="notes-area" id="notesArea" aria-live="polite"></div>
</main>

<div class="modal" id="editorModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-editor></div>
    <div class="editor-host" id="editorHost"></div>
</div>

<div class="modal" id="confirmModal" aria-hidden="true">
    <div class="modal-backdrop" data-confirm-cancel></div>
    <div class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmBody">
        <h2 id="confirmTitle">Hapus catatan?</h2>
        <p id="confirmBody">Tindakan ini tidak bisa dibatalkan.</p>
        <div class="dialog-actions">
            <button class="btn btn-ghost" type="button" data-confirm-cancel>Batal</button>
            <button class="btn btn-danger" type="button" id="confirmOk">Hapus</button>
        </div>
    </div>
</div>

<div class="modal" id="labelModal" aria-hidden="true">
    <div class="modal-backdrop" data-label-close></div>
    <form class="dialog" id="labelForm" role="dialog" aria-modal="true" aria-labelledby="labelDialogTitle">
        <h2 id="labelDialogTitle">Tambah label</h2>
        <label class="field">
            <span>Nama label</span>
            <input type="text" id="labelName" name="name" maxlength="80" placeholder="mis. kerja, resep, ide" required autocomplete="off">
        </label>
        <p class="form-error" id="labelError" hidden></p>
        <div class="dialog-actions">
            <button class="btn btn-ghost" type="button" data-label-close>Batal</button>
            <button class="btn btn-primary" type="submit" id="labelSubmit">Simpan</button>
        </div>
    </form>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>
