(function () {
    'use strict';

    var CONFIG = window.DIAR || {};
    var base = CONFIG.base || '';
    var COLORS = ['default', 'yellow', 'orange', 'red', 'pink', 'purple', 'blue', 'teal', 'green', 'gray'];
    var LABEL_COLORS = ['#d97706', '#dc2626', '#db2777', '#7c3aed', '#2563eb', '#0891b2', '#059669', '#65a30d', '#ca8a04', '#78716c'];

    var state = {
        view: 'notes',
        labelId: null,
        query: '',
        notes: [],
        labels: [],
        counts: { notes: 0, archive: 0, trash: 0 },
        loading: true,
        editing: null,
        dirty: false,
        force: false,
        saveTimer: null,
        savePromise: null,
        needsReload: false,
        popover: null,
        popoverAnchor: null,
        popoverOutside: null,
        confirmResolver: null,
        labelResolver: null,
        labelEditing: null,
        lastFocus: null,
        loadSeq: 0,
        actionBusy: false
    };

    var el = {};
    var ICONS = {
        check: 'i-check',
        pin: 'i-pin',
        note: 'i-note',
        search: 'i-search',
        archive: 'i-archive',
        trash: 'i-trash',
        restore: 'i-restore',
        trashX: 'i-trash-x',
        x: 'i-x',
        list: 'i-list',
        text: 'i-text',
        palette: 'i-palette',
        tag: 'i-tag',
        image: 'i-image',
        plus: 'i-plus',
        more: 'i-more',
        pencil: 'i-pencil',
        feather: 'i-feather',
        alert: 'i-alert'
    };

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    function $$(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
    }

    function icon(name) {
        return '<svg class="icon" aria-hidden="true"><use href="#' + (ICONS[name] || name) + '"></use></svg>';
    }

    var TOKEN_KEY = 'diarkeep.token';

    function getToken() {
        try {
            return localStorage.getItem(TOKEN_KEY) || '';
        } catch (error) {
            return '';
        }
    }

    function clearToken() {
        try {
            localStorage.removeItem(TOKEN_KEY);
        } catch (error) {}
    }

    function goToLogin() {
        window.location.replace(base + '/');
    }

    function api(path, options) {
        options = options || {};
        var method = options.method || 'GET';
        var headers = { 'Accept': 'application/json' };

        var token = getToken();
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }

        var body;

        if (options.form) {
            body = options.form;
        } else if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(options.body);
        }

        return fetch(base + '/api' + path, {
            method: method,
            headers: headers,
            body: body
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (response.status === 401) {
                    clearToken();
                    goToLogin();
                }
                if (!response.ok) {
                    var error = new Error(data.error || 'Terjadi kesalahan. Coba lagi.');
                    error.status = response.status;
                    throw error;
                }
                return data;
            });
        });
    }

    function toast(message, type) {
        var node = document.createElement('div');
        node.className = 'toast' + (type === 'error' ? ' is-error' : '');
        node.innerHTML = icon(type === 'error' ? 'alert' : 'check') + '<span>' + esc(message) + '</span>';
        el.toastStack.appendChild(node);

        window.setTimeout(function () {
            node.style.opacity = '0';
            node.style.transform = 'translateY(6px)';
            node.style.transition = 'opacity .2s ease, transform .2s ease';
            window.setTimeout(function () { node.remove(); }, 220);
        }, type === 'error' ? 4600 : 3000);
    }

    function updateScrollLock() {
        var open = document.querySelector('.modal.is-open');
        document.body.style.overflow = open ? 'hidden' : '';
    }

    function isTyping(target) {
        if (!target) {
            return false;
        }
        return target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable;
    }

    function labelColor(name) {
        var hash = 0;
        for (var i = 0; i < name.length; i++) {
            hash = (hash * 31 + name.charCodeAt(i)) % 9973;
        }
        return LABEL_COLORS[hash % LABEL_COLORS.length];
    }

    function findLabel(id) {
        for (var i = 0; i < state.labels.length; i++) {
            if (state.labels[i].id === id) {
                return state.labels[i];
            }
        }
        return null;
    }

    function currentLabel() {
        return state.labelId ? findLabel(state.labelId) : null;
    }

    /* ------------------------------------------------------------------ */
    /* loading & rendering                                                */
    /* ------------------------------------------------------------------ */

    function loadNotes(options) {
        options = options || {};
        var seq = ++state.loadSeq;
        var params = new URLSearchParams();
        params.set('view', state.view);
        if (state.labelId) {
            params.set('label', String(state.labelId));
        }
        if (state.query) {
            params.set('q', state.query);
        }

        if (!options.silent) {
            renderSkeleton();
        }

        return api('/notes?' + params.toString()).then(function (data) {
            if (seq !== state.loadSeq) {
                return;
            }
            state.notes = data.notes || [];
            state.labels = data.labels || [];
            state.counts = data.counts || state.counts;
            state.loading = false;

            if (state.labelId && !findLabel(state.labelId)) {
                state.labelId = null;
            }

            renderLabels();
            renderCounts();
            renderPageHead();
            renderNotes();
        }).catch(function (error) {
            if (seq !== state.loadSeq) {
                return;
            }
            state.loading = false;
            renderLoadError(error);
        });
    }

    function renderSkeleton() {
        var cards = '';
        for (var i = 0; i < 8; i++) {
            cards += '<div class="skeleton-card"></div>';
        }
        el.notesArea.innerHTML = '<div class="skeleton-grid">' + cards + '</div>';
    }

    function renderLoadError(error) {
        el.notesArea.innerHTML =
            '<div class="empty-state">' +
            '<div class="empty-art">' + icon('alert') + '</div>' +
            '<h3>Tidak bisa memuat catatan</h3>' +
            '<p>' + esc(error.message || 'Periksa koneksi ke server lalu coba lagi.') + '</p>' +
            '<button class="btn btn-outline" type="button" data-retry>Muat ulang</button>' +
            '</div>';
    }

    function renderCounts() {
        el.countNotes.textContent = String(state.counts.notes || 0);
        el.countArchive.textContent = String(state.counts.archive || 0);
        el.countTrash.textContent = String(state.counts.trash || 0);
    }

    function renderLabels() {
        if (!state.labels.length) {
            el.labelList.innerHTML = '';
            el.labelEmpty.hidden = false;
            return;
        }

        el.labelEmpty.hidden = true;
        el.labelList.innerHTML = state.labels.map(function (label) {
            var active = state.labelId === label.id;
            return '<div class="label-item' + (active ? ' is-active' : '') + '" data-label-id="' + label.id + '" role="button" tabindex="0">' +
                '<span class="label-dot" style="background:' + labelColor(label.name) + '"></span>' +
                '<span class="label-name">' + esc(label.name) + '</span>' +
                '<span class="label-count">' + (label.count || 0) + '</span>' +
                '<button class="label-more" type="button" data-label-menu="' + label.id + '" aria-label="Opsi label ' + esc(label.name) + '">' + icon('more') + '</button>' +
                '</div>';
        }).join('');
    }

    function renderPageHead() {
        var label = currentLabel();
        var title = 'Catatan';
        var subtitle = 'Semua catatanmu, dalam satu papan.';

        if (label) {
            title = label.name;
            subtitle = 'Catatan dengan label ini.';
        } else if (state.view === 'archive') {
            title = 'Arsip';
            subtitle = 'Catatan yang sudah selesai, tapi sayang dibuang.';
        } else if (state.view === 'trash') {
            title = 'Sampah';
            subtitle = 'Catatan di sini akan hilang saat sampah dikosongkan.';
        }

        if (state.query) {
            subtitle = 'Hasil pencarian untuk “' + state.query + '”.';
        }

        el.pageTitle.textContent = title;
        el.pageSubtitle.textContent = subtitle;
        el.emptyTrashBtn.hidden = !(state.view === 'trash' && (state.counts.trash || 0) > 0);
        el.searchInput.placeholder = state.view === 'trash' ? 'Cari di sampah...' : 'Cari catatan...';

        $$('.nav-item').forEach(function (button) {
            var view = button.getAttribute('data-view');
            var active = !label && state.view === view;
            button.classList.toggle('is-active', active);
        });

        document.title = (label ? label.name + ' — ' : '') + 'DiarKeep';
    }

    function renderNotes() {
        if (state.loading) {
            return;
        }

        if (!state.notes.length) {
            el.notesArea.innerHTML = emptyState();
            return;
        }

        if (state.query) {
            el.notesArea.innerHTML = section('Hasil pencarian', 'search', state.notes);
            return;
        }

        if (state.view === 'trash') {
            el.notesArea.innerHTML = section('', '', state.notes);
            return;
        }

        var pinned = state.notes.filter(function (note) { return note.pinned; });
        var rest = state.notes.filter(function (note) { return !note.pinned; });
        var html = '';

        if (pinned.length) {
            html += section('Disematkan', 'pin', pinned);
        }

        if (rest.length) {
            html += section(pinned.length ? 'Lainnya' : '', '', rest);
        }

        el.notesArea.innerHTML = html;
    }

    function section(title, iconName, notes) {
        var head = title
            ? '<h2 class="notes-section-head">' + (iconName ? icon(iconName) : '') + esc(title) + '</h2>'
            : '';

        return '<section class="notes-section">' + head +
            '<div class="notes-grid">' + notes.map(noteCard).join('') + '</div>' +
            '</section>';
    }

    function emptyState() {
        if (state.query) {
            return '<div class="empty-state">' +
                '<div class="empty-art">' + icon('search') + '</div>' +
                '<h3>Tidak ada hasil</h3>' +
                '<p>Tidak ada catatan yang cocok dengan “' + esc(state.query) + '”. Coba kata lain atau lihat semua catatan.</p>' +
                '<button class="btn btn-outline" type="button" data-clear-search>Bersihkan pencarian</button>' +
                '</div>';
        }

        if (state.labelId) {
            return '<div class="empty-state">' +
                '<div class="empty-art">' + icon('tag') + '</div>' +
                '<h3>Label ini masih sepi</h3>' +
                '<p>Belum ada catatan dengan label ini. Buka sebuah catatan lalu tambahkan labelnya.</p>' +
                '<button class="btn btn-outline" type="button" data-goto-notes>Lihat semua catatan</button>' +
                '</div>';
        }

        if (state.view === 'archive') {
            return '<div class="empty-state">' +
                '<div class="empty-art">' + icon('archive') + '</div>' +
                '<h3>Arsip masih kosong</h3>' +
                '<p>Catatan yang diarsipkan akan muncul di sini, tetap tersimpan tapi tidak mengganggu papan utama.</p>' +
                '<button class="btn btn-outline" type="button" data-goto-notes>Kembali ke catatan</button>' +
                '</div>';
        }

        if (state.view === 'trash') {
            return '<div class="empty-state">' +
                '<div class="empty-art">' + icon('trash') + '</div>' +
                '<h3>Sampah kosong</h3>' +
                '<p>Tidak ada catatan yang menunggu dihapus. Papanmu bersih.</p>' +
                '</div>';
        }

        return '<div class="empty-state">' +
            '<div class="empty-art">' + icon('note') + '</div>' +
            '<h3>Belum ada catatan</h3>' +
            '<p>Mulai dari satu ide kecil. Tekan tombol di bawah atau langsung ketik <kbd>n</kbd> di mana saja.</p>' +
            '<button class="btn btn-primary" type="button" data-empty-new>' + icon('plus') + 'Tulis catatan pertama</button>' +
            '</div>';
    }

    function noteCard(note) {
        var actions = '';

        if (note.trashed) {
            actions += cardAction('restore', 'Pulihkan', 'restore');
            actions += cardAction('delete-forever', 'Hapus permanen', 'trashX');
        } else {
            if (state.view === 'archive') {
                actions += cardAction('unarchive', 'Keluarkan dari arsip', 'archive');
            } else {
                actions += cardAction('archive', 'Arsipkan', 'archive');
            }
            actions += cardAction('trash', 'Pindahkan ke sampah', 'trash');
        }

        var pin = note.pinned ? '<span class="note-pin" title="Disematkan">' + icon('pin') + '</span>' : '';
        var title = note.title ? '<h3 class="note-title">' + esc(note.title) + '</h3>' : '';
        var body = note.body ? '<p class="note-body">' + esc(note.body) + '</p>' : '';
        var image = '';

        if (note.attachments && note.attachments.length) {
            image = '<div class="note-image"><img src="' + esc(note.attachments[0].thumb) + '" alt="" loading="lazy"></div>';
        }

        var checklist = '';

        if (note.type === 'list' && note.items && note.items.length) {
            var shown = note.items.slice(0, 7);
            checklist = '<div class="note-checklist">' + shown.map(function (item) {
                return '<div class="check-row' + (item.checked ? ' done' : '') + '">' +
                    '<span class="check-box">' + (item.checked ? icon('check') : '') + '</span>' +
                    '<span>' + esc(item.content) + '</span>' +
                    '</div>';
            }).join('') + '</div>';

            if (note.items.length > shown.length) {
                checklist += '<p class="note-more-items">+' + (note.items.length - shown.length) + ' item lagi</p>';
            }
        }

        var chips = '';

        if (note.labels && note.labels.length) {
            chips = '<div class="note-chips">' + note.labels.slice(0, 5).map(function (label) {
                return '<span class="chip">' + esc(label.name) + '</span>';
            }).join('') + (note.labels.length > 5 ? '<span class="chip">+' + (note.labels.length - 5) + '</span>' : '') + '</div>';
        }

        var empty = (!note.title && !note.body && !checklist && !image)
            ? '<p class="note-body note-empty">Catatan kosong</p>'
            : '';

        return '<article class="note-card" data-note-id="' + note.id + '" data-color="' + esc(note.color) + '" tabindex="0" role="button" aria-label="Buka catatan">' +
            '<div class="note-card-top">' + pin + '<div class="card-actions">' + actions + '</div></div>' +
            title + body + image + checklist + empty + chips +
            '</article>';
    }

    function cardAction(action, title, iconName) {
        return '<button class="card-action" type="button" data-note-action="' + action + '" title="' + esc(title) + '" aria-label="' + esc(title) + '">' + icon(iconName) + '</button>';
    }

    /* ------------------------------------------------------------------ */
    /* note actions                                                        */
    /* ------------------------------------------------------------------ */

    function handleNoteAction(action, id) {
        if (state.actionBusy) {
            return;
        }

        var note = state.notes.filter(function (item) { return item.id === id; })[0];
        if (!note) {
            return;
        }

        state.actionBusy = true;

        var done = function (message) {
            state.actionBusy = false;
            if (message) {
                toast(message);
            }
            loadNotes({ silent: true });
        };

        var failed = function (error) {
            state.actionBusy = false;
            toast(error.message || 'Tindakan gagal.', 'error');
        };

        if (action === 'pin') {
            api('/notes/' + id, { method: 'PATCH', body: { pinned: !note.pinned } }).then(function () {
                done(note.pinned ? 'Pin dilepas' : 'Catatan disematkan');
            }).catch(failed);
            return;
        }

        if (action === 'archive' || action === 'unarchive') {
            api('/notes/' + id, { method: 'PATCH', body: { archived: action === 'archive' } }).then(function () {
                done(action === 'archive' ? 'Catatan diarsipkan' : 'Catatan dikembalikan');
            }).catch(failed);
            return;
        }

        if (action === 'trash') {
            api('/notes/' + id, { method: 'DELETE' }).then(function () {
                done('Catatan dipindahkan ke sampah');
            }).catch(failed);
            return;
        }

        if (action === 'restore') {
            api('/notes/' + id + '/restore', { method: 'POST' }).then(function () {
                done('Catatan dipulihkan');
            }).catch(failed);
            return;
        }

        if (action === 'delete-forever') {
            confirmDialog({
                title: 'Hapus permanen?',
                message: 'Catatan ini akan dihapus selamanya beserta gambarnya. Tindakan ini tidak bisa dibatalkan.',
                confirmLabel: 'Hapus permanen'
            }).then(function (yes) {
                if (!yes) {
                    state.actionBusy = false;
                    return;
                }
                api('/notes/' + id + '?permanent=1', { method: 'DELETE' }).then(function () {
                    done('Catatan dihapus permanen');
                }).catch(failed);
            });
            return;
        }

        state.actionBusy = false;
    }

    /* ------------------------------------------------------------------ */
    /* editor                                                              */
    /* ------------------------------------------------------------------ */

    function cloneNote(note) {
        if (!note) {
            return {
                id: null,
                type: 'text',
                title: '',
                body: '',
                color: 'default',
                pinned: false,
                archived: false,
                trashed: false,
                items: [],
                labels: [],
                attachments: []
            };
        }

        return {
            id: note.id,
            type: note.type,
            title: note.title || '',
            body: note.body || '',
            color: note.color || 'default',
            pinned: !!note.pinned,
            archived: !!note.archived,
            trashed: !!note.trashed,
            items: (note.items || []).map(function (item) {
                return { content: item.content, checked: !!item.checked };
            }),
            labels: (note.labels || []).map(function (label) {
                return { id: label.id, name: label.name };
            }),
            attachments: (note.attachments || []).slice()
        };
    }

    function openEditor(note) {
        closePopover();
        state.editing = cloneNote(note);
        state.dirty = false;
        state.force = false;
        state.needsReload = false;
        state.lastFocus = document.activeElement;

        if (!note) {
            state.view = 'notes';
            var label = currentLabel();
            if (label) {
                state.editing.labels = [{ id: label.id, name: label.name }];
            }
            renderPageHead();
            $$('.nav-item').forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-view') === 'notes');
            });
        }

        el.editorHost.innerHTML = editorMarkup(state.editing);
        el.editorModal.classList.add('is-open');
        el.editorModal.setAttribute('aria-hidden', 'false');
        updateScrollLock();

        renderChecks();
        renderAttachments();
        renderEditorLabels();
        autosize(el.editorBody());

        window.setTimeout(function () {
            var title = el.editorTitle();
            if (title) {
                title.focus();
                title.setSelectionRange(title.value.length, title.value.length);
            }
        }, 70);
    }

    function openEditorById(id) {
        var note = state.notes.filter(function (item) { return item.id === id; })[0];
        if (note) {
            openEditor(note);
            return;
        }

        api('/notes/' + id).then(function (data) {
            openEditor(data.note);
        }).catch(function (error) {
            toast(error.message, 'error');
        });
    }

    function editorMarkup(draft) {
        var listMode = draft.type === 'list';
        return '<div class="editor" data-color="' + esc(draft.color) + '" role="dialog" aria-modal="true" aria-label="Editor catatan">' +
            '<div class="editor-top">' +
            '<button class="icon-btn editor-pin' + (draft.pinned ? ' is-active' : '') + '" type="button" data-ed="pin" title="Sematkan catatan" aria-pressed="' + draft.pinned + '">' + icon('pin') + '</button>' +
            '<span class="editor-state" id="editorState"></span>' +
            '<button class="icon-btn editor-close" type="button" data-ed="close" aria-label="Tutup editor">' + icon('x') + '</button>' +
            '</div>' +
            '<input class="editor-title" id="editorTitle" type="text" maxlength="255" placeholder="Judul" value="' + esc(draft.title) + '" autocomplete="off">' +
            '<textarea class="editor-body" id="editorBody" placeholder="Tulis sesuatu...">' + esc(draft.body) + '</textarea>' +
            '<div class="editor-checks' + (listMode ? '' : ' hidden') + '" id="editorChecks">' +
            '<div class="check-list" id="checkList"></div>' +
            '<button class="check-add" type="button" data-ed="add-item">+ Tambah item</button>' +
            '</div>' +
            '<div class="editor-attachments" id="editorAttachments"></div>' +
            '<div class="editor-label-chips" id="editorLabelChips"></div>' +
            '<div class="editor-toolbar">' +
            '<div class="editor-tools">' +
            '<button class="editor-tool" type="button" data-ed="color" title="Warna catatan">' + icon('palette') + '</button>' +
            '<button class="editor-tool" type="button" data-ed="label" title="Label">' + icon('tag') + '</button>' +
            '<button class="editor-tool" type="button" data-ed="image" title="Tambah gambar">' + icon('image') + '</button>' +
            '<button class="editor-tool' + (listMode ? ' is-active' : '') + '" type="button" data-ed="type" title="' + (listMode ? 'Matikan checklist' : 'Jadikan checklist') + '">' + icon('list') + '</button>' +
            '<button class="editor-tool' + (draft.archived ? ' is-active' : '') + '" type="button" data-ed="archive" title="' + (draft.archived ? 'Keluarkan dari arsip' : 'Arsipkan') + '">' + icon('archive') + '</button>' +
            '<button class="editor-tool' + (draft.trashed ? ' danger' : '') + '" type="button" data-ed="trash" title="' + (draft.trashed ? 'Hapus permanen' : 'Pindahkan ke sampah') + '">' + icon(draft.trashed ? 'trashX' : 'trash') + '</button>' +
            '</div>' +
            '<button class="editor-close-btn" type="button" data-ed="close">Tutup</button>' +
            '</div>' +
            '<input type="file" id="editorFile" accept="image/jpeg,image/png,image/webp,image/gif" hidden>' +
            '</div>';
    }

    function editorEl(selector) {
        return el.editorHost.querySelector(selector);
    }

    function editorTitle() { return editorEl('#editorTitle'); }
    function editorBody() { return editorEl('#editorBody'); }
    function editorState() { return editorEl('#editorState'); }

    function renderChecks() {
        var draft = state.editing;
        var list = editorEl('#checkList');
        if (!draft || !list) {
            return;
        }

        list.innerHTML = draft.items.map(function (item, index) {
            return '<div class="check-item' + (item.checked ? ' done' : '') + '" data-check-row="' + index + '">' +
                '<button class="check-toggle" type="button" data-check-toggle="' + index + '" aria-label="' + (item.checked ? 'Tandai belum selesai' : 'Tandai selesai') + '">' + (item.checked ? icon('check') : '') + '</button>' +
                '<input class="check-input" type="text" data-check-input="' + index + '" value="' + esc(item.content) + '" placeholder="Item daftar" maxlength="1000" autocomplete="off">' +
                '<button class="check-remove" type="button" data-check-remove="' + index + '" aria-label="Hapus item">' + icon('x') + '</button>' +
                '</div>';
        }).join('');
    }

    function renderAttachments() {
        var draft = state.editing;
        var wrap = editorEl('#editorAttachments');
        if (!draft || !wrap) {
            return;
        }

        wrap.innerHTML = draft.attachments.map(function (attachment) {
            return '<div class="attachment-tile">' +
                '<img src="' + esc(attachment.thumb) + '" alt="">' +
                '<button class="attachment-remove" type="button" data-attachment-remove="' + attachment.id + '" aria-label="Hapus gambar">' + icon('x') + '</button>' +
                '</div>';
        }).join('');
    }

    function renderEditorLabels() {
        var draft = state.editing;
        var wrap = editorEl('#editorLabelChips');
        if (!draft || !wrap) {
            return;
        }

        wrap.innerHTML = draft.labels.map(function (label) {
            return '<span class="chip">' + esc(label.name) + '</span>';
        }).join('');
    }

    function autosize(textarea) {
        if (!textarea) {
            return;
        }
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 460) + 'px';
        textarea.style.overflowY = textarea.scrollHeight > 460 ? 'auto' : 'hidden';
    }

    function updateEditorType() {
        var draft = state.editing;
        var box = editorEl('#editorChecks');
        var button = el.editorHost.querySelector('[data-ed="type"]');

        if (!draft || !box || !button) {
            return;
        }

        var listMode = draft.type === 'list';
        box.classList.toggle('hidden', !listMode);
        button.classList.toggle('is-active', listMode);
        button.title = listMode ? 'Matikan checklist' : 'Jadikan checklist';
        editorBody().placeholder = listMode ? 'Catatan tambahan (opsional)...' : 'Tulis sesuatu...';
        renderChecks();
        autosize(editorBody());
    }

    function setEditorState(type, message) {
        var node = editorState();
        if (!node) {
            return;
        }
        node.className = 'editor-state' + (type ? ' is-' + type : '');
        node.textContent = message || '';
    }

    function scheduleSave(delay) {
        if (!state.editing) {
            return;
        }
        state.dirty = true;
        window.clearTimeout(state.saveTimer);
        state.saveTimer = window.setTimeout(function () {
            runSave().catch(function () {});
        }, delay == null ? 850 : delay);
    }

    function runSave() {
        if (state.savePromise) {
            return state.savePromise;
        }

        state.savePromise = (function () {
            return new Promise(function (resolve, reject) {
                (async function () {
                    try {
                        while (state.editing && state.dirty) {
                            state.dirty = false;
                            await persist();
                        }
                        resolve(state.editing);
                    } catch (error) {
                        state.dirty = true;
                        reject(error);
                    } finally {
                        state.savePromise = null;
                    }
                })();
            });
        })();

        return state.savePromise;
    }

    async function persist() {
        var draft = state.editing;
        if (!draft) {
            return;
        }

        var force = state.force;
        state.force = false;

        var hasContent = draft.title.trim() !== '' ||
            draft.body.trim() !== '' ||
            draft.items.some(function (item) { return item.content.trim() !== ''; }) ||
            draft.attachments.length > 0;

        if (!draft.id && !hasContent && !force) {
            return;
        }

        setEditorState('saving', 'Menyimpan...');

        var payload = {
            type: draft.type,
            title: draft.title,
            body: draft.body,
            color: draft.color,
            pinned: draft.pinned,
            archived: draft.archived,
            items: draft.items.map(function (item) {
                return { content: item.content, checked: !!item.checked };
            }),
            label_ids: draft.labels.map(function (label) { return label.id; })
        };

        if (draft.id) {
            var updated = await api('/notes/' + draft.id, { method: 'PATCH', body: payload });
            draft.updated_at = updated.note.updated_at;
        } else {
            var created = await api('/notes', { method: 'POST', body: payload });
            draft.id = created.note.id;
            draft.created_at = created.note.created_at;
            draft.updated_at = created.note.updated_at;
        }

        state.needsReload = true;
        setEditorState('saved', 'Tersimpan');
    }

    function saveNow(force) {
        window.clearTimeout(state.saveTimer);
        state.saveTimer = null;

        if (!state.editing) {
            return Promise.resolve(null);
        }

        if (force) {
            state.force = true;
            state.dirty = true;
        }

        if (!state.dirty && !state.force) {
            return Promise.resolve(state.editing);
        }

        return runSave();
    }

    async function closeEditor() {
        if (!state.editing) {
            return true;
        }

        closePopover();
        window.clearTimeout(state.saveTimer);
        state.saveTimer = null;

        try {
            if (state.dirty || state.force) {
                await saveNow(false);
            }
        } catch (error) {
            setEditorState('error', 'Gagal menyimpan');
            toast(error.message || 'Gagal menyimpan catatan.', 'error');
            return false;
        }

        var reload = state.needsReload;
        state.editing = null;
        state.dirty = false;
        state.force = false;
        state.needsReload = false;
        el.editorHost.innerHTML = '';
        el.editorModal.classList.remove('is-open');
        el.editorModal.setAttribute('aria-hidden', 'true');
        updateScrollLock();

        if (reload) {
            loadNotes({ silent: true });
        }

        if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
            state.lastFocus.focus();
        }

        state.lastFocus = null;

        return true;
    }

    function toggleEditorPin() {
        var draft = state.editing;
        if (!draft) {
            return;
        }
        draft.pinned = !draft.pinned;
        var button = el.editorHost.querySelector('[data-ed="pin"]');
        button.classList.toggle('is-active', draft.pinned);
        button.setAttribute('aria-pressed', String(draft.pinned));
        scheduleSave(200);
        toast(draft.pinned ? 'Catatan disematkan' : 'Pin dilepas');
    }

    function toggleEditorType() {
        var draft = state.editing;
        if (!draft) {
            return;
        }
        draft.type = draft.type === 'list' ? 'text' : 'list';
        if (draft.type === 'list' && draft.items.length === 0) {
            draft.items.push({ content: '', checked: false });
        }
        updateEditorType();
        scheduleSave(200);

        if (draft.type === 'list') {
            var inputs = el.editorHost.querySelectorAll('[data-check-input]');
            if (inputs.length) {
                inputs[0].focus();
            }
        }
    }

    function addChecklistItem(afterIndex) {
        var draft = state.editing;
        if (!draft) {
            return;
        }

        var item = { content: '', checked: false };
        var index;

        if (typeof afterIndex === 'number' && afterIndex >= 0) {
            index = afterIndex + 1;
            draft.items.splice(index, 0, item);
        } else {
            draft.items.push(item);
            index = draft.items.length - 1;
        }

        renderChecks();
        scheduleSave(300);

        var input = el.editorHost.querySelector('[data-check-input="' + index + '"]');
        if (input) {
            input.focus();
        }
    }

    async function removeAttachment(id) {
        var draft = state.editing;
        if (!draft) {
            return;
        }

        try {
            await api('/attachments/' + id, { method: 'DELETE' });
            draft.attachments = draft.attachments.filter(function (item) { return item.id !== id; });
            renderAttachments();
            state.needsReload = true;
            toast('Gambar dihapus');
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    async function uploadAttachment(file) {
        if (!state.editing || !file) {
            return;
        }

        if (file.size > (CONFIG.uploadMax || 5242880)) {
            toast('Ukuran gambar maksimal ' + Math.round((CONFIG.uploadMax || 5242880) / 1048576) + ' MB.', 'error');
            return;
        }

        try {
            var draft = await saveNow(true);
            if (!draft || !draft.id) {
                throw new Error('Catatan belum tersimpan.');
            }

            setEditorState('saving', 'Mengunggah gambar...');

            var form = new FormData();
            form.append('image', file);

            var response = await api('/notes/' + draft.id + '/attachments', { method: 'POST', form: form });
            draft.attachments.push(response.attachment);
            renderAttachments();
            state.needsReload = true;
            setEditorState('saved', 'Tersimpan');
            toast('Gambar ditambahkan');
        } catch (error) {
            setEditorState('error', 'Gagal mengunggah');
            toast(error.message, 'error');
        }
    }

    async function archiveFromEditor() {
        var draft = state.editing;
        if (!draft) {
            return;
        }

        draft.archived = !draft.archived;
        state.dirty = true;
        state.needsReload = true;

        var message = draft.archived ? 'Catatan diarsipkan' : 'Catatan dikembalikan dari arsip';
        var closed = await closeEditor();
        if (closed) {
            toast(message);
        }
    }

    async function trashFromEditor() {
        var draft = state.editing;
        if (!draft) {
            return;
        }

        if (draft.trashed) {
            var yes = await confirmDialog({
                title: 'Hapus permanen?',
                message: 'Catatan ini akan dihapus selamanya beserta gambarnya. Tindakan ini tidak bisa dibatalkan.',
                confirmLabel: 'Hapus permanen'
            });
            if (!yes) {
                return;
            }

            if (draft.id) {
                try {
                    await api('/notes/' + draft.id + '?permanent=1', { method: 'DELETE' });
                } catch (error) {
                    toast(error.message, 'error');
                    return;
                }
            }
        } else if (draft.id) {
            try {
                await api('/notes/' + draft.id, { method: 'DELETE' });
            } catch (error) {
                toast(error.message, 'error');
                return;
            }
        }

        state.editing = null;
        state.dirty = false;
        state.force = false;
        el.editorHost.innerHTML = '';
        el.editorModal.classList.remove('is-open');
        el.editorModal.setAttribute('aria-hidden', 'true');
        updateScrollLock();
        loadNotes({ silent: true });
        toast(draft.trashed ? 'Catatan dihapus permanen' : 'Catatan dipindahkan ke sampah');
    }

    /* ------------------------------------------------------------------ */
    /* editor popovers                                                     */
    /* ------------------------------------------------------------------ */

    function openColorPicker(anchor) {
        var draft = state.editing;
        if (!draft) {
            return;
        }

        var html = '<p class="popover-title">Warna catatan</p><div class="color-grid">' +
            COLORS.map(function (color) {
                return '<button class="color-swatch sw-' + color + (draft.color === color ? ' is-active' : '') + '" type="button" data-color-pick="' + color + '" aria-label="Warna ' + color + '"></button>';
            }).join('') + '</div>';

        var popover = openPopover(html, anchor, 'color-popover');

        popover.addEventListener('click', function (event) {
            var button = event.target.closest('[data-color-pick]');
            if (!button) {
                return;
            }
            draft.color = button.getAttribute('data-color-pick');
            var editor = el.editorHost.querySelector('.editor');
            if (editor) {
                editor.setAttribute('data-color', draft.color);
            }
            $$('[data-color-pick]', popover).forEach(function (swatch) {
                swatch.classList.toggle('is-active', swatch.getAttribute('data-color-pick') === draft.color);
            });
            scheduleSave(150);
            closePopover();
        });
    }

    function openLabelPicker(anchor) {
        var draft = state.editing;
        if (!draft) {
            return;
        }

        var selected = {};
        draft.labels.forEach(function (label) { selected[label.id] = true; });

        var list = state.labels.length
            ? state.labels.map(function (label) {
                var active = !!selected[label.id];
                return '<button class="picker-item' + (active ? ' is-selected' : '') + '" type="button" data-pick-label="' + label.id + '">' +
                    '<span class="picker-check">' + (active ? icon('check') : '') + '</span>' +
                    '<span class="label-dot" style="background:' + labelColor(label.name) + '"></span>' +
                    '<span class="label-name">' + esc(label.name) + '</span>' +
                    '</button>';
            }).join('')
            : '<p class="picker-empty">Belum ada label.</p>';

        var html = '<p class="popover-title">Label</p><div class="picker-list">' + list + '</div>' +
            '<div class="picker-divider"></div>' +
            '<div class="picker-create">' +
            '<input type="text" id="newLabelInline" maxlength="80" placeholder="Label baru..." autocomplete="off">' +
            '<button type="button" data-create-label aria-label="Buat label">' + icon('plus') + '</button>' +
            '</div>';

        var popover = openPopover(html, anchor, 'label-picker');

        function toggleLabel(button) {
            var id = Number(button.getAttribute('data-pick-label'));
            var label = findLabel(id);
            if (!label) {
                return;
            }

            var exists = draft.labels.some(function (item) { return item.id === id; });

            if (exists) {
                draft.labels = draft.labels.filter(function (item) { return item.id !== id; });
            } else {
                draft.labels.push({ id: label.id, name: label.name });
            }

            button.classList.toggle('is-selected', !exists);
            var check = button.querySelector('.picker-check');
            check.innerHTML = exists ? '' : icon('check');
            renderEditorLabels();
            scheduleSave(200);
        }

        popover.addEventListener('click', function (event) {
            var pick = event.target.closest('[data-pick-label]');
            if (pick) {
                toggleLabel(pick);
                return;
            }

            if (event.target.closest('[data-create-label]')) {
                createInlineLabel();
            }
        });

        var input = popover.querySelector('#newLabelInline');

        async function createInlineLabel() {
            var name = (input.value || '').trim();
            if (!name) {
                input.focus();
                return;
            }

            try {
                var response = await api('/labels', { method: 'POST', body: { name: name } });
                state.labels.push(response.label);
                state.labels.sort(function (a, b) { return a.name.localeCompare(b.name, 'id'); });
                draft.labels.push({ id: response.label.id, name: response.label.name });
                renderEditorLabels();
                renderLabels();
                scheduleSave(200);
                closePopover();
                toast('Label “' + response.label.name + '” dibuat');
            } catch (error) {
                toast(error.message, 'error');
            }
        }

        if (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    createInlineLabel();
                }
            });
            window.setTimeout(function () { input.focus(); }, 70);
        }
    }

    /* ------------------------------------------------------------------ */
    /* generic popovers                                                    */
    /* ------------------------------------------------------------------ */

    function openPopover(html, anchor, className) {
        closePopover();

        var popover = document.createElement('div');
        popover.className = 'popover ' + (className || '');
        popover.innerHTML = html;
        document.body.appendChild(popover);

        positionPopover(popover, anchor);

        state.popover = popover;
        state.popoverAnchor = anchor;

        state.popoverOutside = function (event) {
            if (popover.contains(event.target) || (anchor && anchor.contains(event.target))) {
                return;
            }
            closePopover();
        };

        window.setTimeout(function () {
            document.addEventListener('pointerdown', state.popoverOutside, true);
        }, 0);

        return popover;
    }

    function positionPopover(popover, anchor) {
        var rect = anchor.getBoundingClientRect();
        var margin = 10;
        var width = popover.offsetWidth;
        var height = popover.offsetHeight;

        var left = rect.left + rect.width / 2 - width / 2;
        left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));

        var top = rect.top - height - 8;
        if (top < margin) {
            top = rect.bottom + 8;
        }
        if (top + height > window.innerHeight - margin) {
            top = Math.max(margin, window.innerHeight - height - margin);
        }

        popover.style.left = left + 'px';
        popover.style.top = top + 'px';
    }

    function closePopover() {
        if (state.popoverOutside) {
            document.removeEventListener('pointerdown', state.popoverOutside, true);
            state.popoverOutside = null;
        }
        if (state.popover) {
            state.popover.remove();
            state.popover = null;
        }
        state.popoverAnchor = null;
    }

    /* ------------------------------------------------------------------ */
    /* confirm + label dialogs                                             */
    /* ------------------------------------------------------------------ */

    function confirmDialog(options) {
        options = options || {};
        return new Promise(function (resolve) {
            state.confirmResolver = resolve;
            $('#confirmTitle').textContent = options.title || 'Hapus catatan?';
            $('#confirmBody').textContent = options.message || 'Tindakan ini tidak bisa dibatalkan.';
            $('#confirmOk').textContent = options.confirmLabel || 'Hapus';
            $('#confirmOk').className = 'btn ' + (options.danger === false ? 'btn-primary' : 'btn-danger');
            el.confirmModal.classList.add('is-open');
            el.confirmModal.setAttribute('aria-hidden', 'false');
            updateScrollLock();
            window.setTimeout(function () { $('#confirmOk').focus(); }, 60);
        });
    }

    function resolveConfirm(value) {
        if (!state.confirmResolver) {
            return;
        }
        el.confirmModal.classList.remove('is-open');
        el.confirmModal.setAttribute('aria-hidden', 'true');
        updateScrollLock();
        var resolve = state.confirmResolver;
        state.confirmResolver = null;
        resolve(value);
    }

    function openLabelDialog(existing) {
        return new Promise(function (resolve) {
            state.labelResolver = resolve;
            state.labelEditing = existing || null;
            $('#labelDialogTitle').textContent = existing ? 'Ubah label' : 'Tambah label';
            $('#labelName').value = existing ? existing.name : '';
            $('#labelError').hidden = true;
            $('#labelError').textContent = '';
            el.labelModal.classList.add('is-open');
            el.labelModal.setAttribute('aria-hidden', 'false');
            updateScrollLock();
            window.setTimeout(function () {
                $('#labelName').focus();
                $('#labelName').select();
            }, 60);
        });
    }

    function closeLabelDialog(value) {
        if (!state.labelResolver) {
            return;
        }
        el.labelModal.classList.remove('is-open');
        el.labelModal.setAttribute('aria-hidden', 'true');
        updateScrollLock();
        var resolve = state.labelResolver;
        state.labelResolver = null;
        state.labelEditing = null;
        resolve(value);
    }

    async function submitLabelForm(event) {
        event.preventDefault();

        var name = ($('#labelName').value || '').trim();
        var error = $('#labelError');
        var submit = $('#labelSubmit');

        if (!name) {
            error.textContent = 'Nama label tidak boleh kosong.';
            error.hidden = false;
            return;
        }

        submit.classList.add('is-loading');
        error.hidden = true;

        try {
            var editing = !!state.labelEditing;
            var result;
            if (editing) {
                result = await api('/labels/' + state.labelEditing.id, { method: 'PATCH', body: { name: name } });
            } else {
                result = await api('/labels', { method: 'POST', body: { name: name } });
            }

            var label = result.label;
            closeLabelDialog(label);
            toast(editing ? 'Label diperbarui' : 'Label dibuat');
            loadNotes({ silent: true });
        } catch (err) {
            error.textContent = err.message;
            error.hidden = false;
        } finally {
            submit.classList.remove('is-loading');
        }
    }

    function openLabelMenu(id, anchor) {
        var label = findLabel(id);
        if (!label) {
            return;
        }

        var html = '<button class="menu-item" type="button" data-label-edit>' + icon('pencil') + 'Ubah nama</button>' +
            '<button class="menu-item danger" type="button" data-label-delete>' + icon('trash') + 'Hapus label</button>';

        var popover = openPopover(html, anchor, 'mini-menu');

        popover.addEventListener('click', async function (event) {
            if (event.target.closest('[data-label-edit]')) {
                closePopover();
                var updated = await openLabelDialog(label);
                if (updated) {
                    loadNotes({ silent: true });
                }
                return;
            }

            if (event.target.closest('[data-label-delete]')) {
                closePopover();
                var yes = await confirmDialog({
                    title: 'Hapus label?',
                    message: 'Label “' + label.name + '” akan dilepas dari semua catatan. Catatannya tetap aman.',
                    confirmLabel: 'Hapus label'
                });
                if (!yes) {
                    return;
                }

                try {
                    await api('/labels/' + label.id, { method: 'DELETE' });
                    if (state.labelId === label.id) {
                        state.labelId = null;
                    }
                    toast('Label dihapus');
                    loadNotes({ silent: true });
                } catch (error) {
                    toast(error.message, 'error');
                }
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* editor event wiring                                                 */
    /* ------------------------------------------------------------------ */

    function wireEditorEvents() {
        el.editorHost.addEventListener('click', function (event) {
            var tool = event.target.closest('[data-ed]');
            if (tool) {
                var action = tool.getAttribute('data-ed');
                if (action === 'close') {
                    closeEditor();
                } else if (action === 'pin') {
                    toggleEditorPin();
                } else if (action === 'type') {
                    toggleEditorType();
                } else if (action === 'add-item') {
                    addChecklistItem();
                } else if (action === 'color') {
                    openColorPicker(tool);
                } else if (action === 'label') {
                    openLabelPicker(tool);
                } else if (action === 'image') {
                    var file = editorEl('#editorFile');
                    if (file) {
                        file.click();
                    }
                } else if (action === 'archive') {
                    archiveFromEditor();
                } else if (action === 'trash') {
                    trashFromEditor();
                }
                return;
            }

            var toggle = event.target.closest('[data-check-toggle]');
            if (toggle) {
                var toggleIndex = Number(toggle.getAttribute('data-check-toggle'));
                var item = state.editing.items[toggleIndex];
                if (item) {
                    item.checked = !item.checked;
                    renderChecks();
                    scheduleSave(250);
                }
                return;
            }

            var remove = event.target.closest('[data-check-remove]');
            if (remove) {
                var removeIndex = Number(remove.getAttribute('data-check-remove'));
                state.editing.items.splice(removeIndex, 1);
                renderChecks();
                scheduleSave(250);
                return;
            }

            var attachment = event.target.closest('[data-attachment-remove]');
            if (attachment) {
                removeAttachment(Number(attachment.getAttribute('data-attachment-remove')));
            }
        });

        el.editorHost.addEventListener('input', function (event) {
            var draft = state.editing;
            if (!draft) {
                return;
            }

            if (event.target.id === 'editorTitle') {
                draft.title = event.target.value;
                scheduleSave();
            } else if (event.target.id === 'editorBody') {
                draft.body = event.target.value;
                autosize(event.target);
                scheduleSave();
            } else if (event.target.hasAttribute('data-check-input')) {
                var index = Number(event.target.getAttribute('data-check-input'));
                if (draft.items[index]) {
                    draft.items[index].content = event.target.value;
                    scheduleSave();
                }
            }
        });

        el.editorHost.addEventListener('keydown', function (event) {
            var draft = state.editing;
            if (!draft) {
                return;
            }

            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                event.preventDefault();
                closeEditor();
                return;
            }

            if (event.target.id === 'editorTitle' && event.key === 'Enter') {
                event.preventDefault();
                if (draft.type === 'list') {
                    addChecklistItem();
                    var inputs = el.editorHost.querySelectorAll('[data-check-input]');
                    if (inputs.length) {
                        inputs[0].focus();
                    }
                } else {
                    editorBody().focus();
                }
                return;
            }

            if (event.target.hasAttribute('data-check-input')) {
                var index = Number(event.target.getAttribute('data-check-input'));
                var value = event.target.value;

                if (event.key === 'Enter') {
                    event.preventDefault();
                    addChecklistItem(index);
                } else if (event.key === 'Backspace' && value === '' && draft.items.length > 1) {
                    event.preventDefault();
                    draft.items.splice(index, 1);
                    renderChecks();
                    scheduleSave(250);
                    var previous = el.editorHost.querySelector('[data-check-input="' + Math.max(0, index - 1) + '"]');
                    if (previous) {
                        previous.focus();
                        previous.setSelectionRange(previous.value.length, previous.value.length);
                    }
                }
            }
        });

        el.editorHost.addEventListener('change', function (event) {
            if (event.target.id === 'editorFile') {
                var file = event.target.files && event.target.files[0];
                event.target.value = '';
                if (file) {
                    uploadAttachment(file);
                }
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* global wiring                                                       */
    /* ------------------------------------------------------------------ */

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('diarkeep.theme', theme);
        } catch (error) {}
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', theme === 'dark' ? '#15130f' : '#f5efe4');
        }
    }

    function toggleTheme() {
        applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    }

    function setView(view) {
        state.view = view;
        state.labelId = null;
        state.query = '';
        el.searchInput.value = '';
        el.searchClear.hidden = true;
        loadNotes();
        closeSidebar();
    }

    function setLabel(id) {
        state.labelId = id;
        state.view = 'notes';
        loadNotes();
        closeSidebar();
    }

    function openSidebar() {
        document.body.classList.add('sidebar-open');
        el.mobileOverlay.hidden = false;
        el.menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        el.mobileOverlay.hidden = true;
        el.menuBtn.setAttribute('aria-expanded', 'false');
    }

    function clearSearch() {
        state.query = '';
        el.searchInput.value = '';
        el.searchClear.hidden = true;
        loadNotes();
    }

    function toggleUserMenu() {
        var open = el.userPopover.hidden;
        el.userPopover.hidden = !open;
        el.userBtn.setAttribute('aria-expanded', String(open));
    }

    function closeUserMenu() {
        el.userPopover.hidden = true;
        el.userBtn.setAttribute('aria-expanded', 'false');
    }

    async function logout() {
        try {
            await api('/auth/logout', { method: 'POST' });
        } catch (error) {}
        clearToken();
        window.location.href = base + '/';
    }

    function applyUser(user) {
        if (!user) {
            return;
        }

        var name  = String(user.name || '').trim();
        var email = String(user.email || '').trim();
        var parts = name ? name.split(/\s+/) : [];
        var first = parts.length ? parts[0].charAt(0) : (email ? email.charAt(0) : '');
        var last  = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        var initials = (first + last).toUpperCase() || '··';

        $$('[data-user-initials]').forEach(function (node) { node.textContent = initials; });
        $$('[data-user-name]').forEach(function (node) { node.textContent = name || email; });
        $$('[data-user-email]').forEach(function (node) { node.textContent = email; });
    }

    function openNewNote() {
        closeSidebar();
        openEditor(null);
    }

    function wireGlobalEvents() {
        var searchTimer = null;

        el.searchInput.addEventListener('input', function () {
            state.query = el.searchInput.value.trim();
            el.searchClear.hidden = !el.searchInput.value;
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () {
                loadNotes({ silent: true });
            }, 260);
        });

        el.searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (state.query) {
                    clearSearch();
                } else {
                    el.searchInput.blur();
                }
            }
        });

        el.searchClear.addEventListener('click', function () {
            clearSearch();
            el.searchInput.focus();
        });

        $$('.nav-item').forEach(function (button) {
            button.addEventListener('click', function () {
                setView(button.getAttribute('data-view'));
            });
        });

        el.labelList.addEventListener('click', function (event) {
            var menu = event.target.closest('[data-label-menu]');
            if (menu) {
                event.stopPropagation();
                openLabelMenu(Number(menu.getAttribute('data-label-menu')), menu);
                return;
            }

            var item = event.target.closest('[data-label-id]');
            if (item) {
                setLabel(Number(item.getAttribute('data-label-id')));
            }
        });

        el.labelList.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            var item = event.target.closest('[data-label-id]');
            if (!item || event.target.closest('[data-label-menu]')) {
                return;
            }
            event.preventDefault();
            setLabel(Number(item.getAttribute('data-label-id')));
        });

        el.addLabelBtn.addEventListener('click', async function () {
            var label = await openLabelDialog(null);
            if (label) {
                toast('Label “' + label.name + '” dibuat');
                loadNotes({ silent: true });
            }
        });

        el.newNoteBtn.addEventListener('click', openNewNote);
        el.composerBtn.addEventListener('click', openNewNote);

        el.notesArea.addEventListener('click', function (event) {
            var action = event.target.closest('[data-note-action]');
            if (action) {
                event.preventDefault();
                event.stopPropagation();
                var card = action.closest('[data-note-id]');
                if (card) {
                    handleNoteAction(action.getAttribute('data-note-action'), Number(card.getAttribute('data-note-id')));
                }
                return;
            }

            var card = event.target.closest('[data-note-id]');
            if (card) {
                openEditorById(Number(card.getAttribute('data-note-id')));
                return;
            }

            if (event.target.closest('[data-empty-new]')) {
                openNewNote();
            } else if (event.target.closest('[data-clear-search]')) {
                clearSearch();
            } else if (event.target.closest('[data-goto-notes]')) {
                setView('notes');
            } else if (event.target.closest('[data-retry]')) {
                loadNotes();
            }
        });

        el.notesArea.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            var card = event.target.closest('[data-note-id]');
            if (card && !event.target.closest('[data-note-action]')) {
                event.preventDefault();
                openEditorById(Number(card.getAttribute('data-note-id')));
            }
        });

        el.emptyTrashBtn.addEventListener('click', async function () {
            var count = state.counts.trash || 0;
            var yes = await confirmDialog({
                title: 'Kosongkan sampah?',
                message: count + ' catatan di sampah akan dihapus permanen beserta gambarnya. Tindakan ini tidak bisa dibatalkan.',
                confirmLabel: 'Kosongkan sampah'
            });
            if (!yes) {
                return;
            }

            try {
                var result = await api('/trash/empty', { method: 'POST' });
                toast(result.deleted + ' catatan dihapus permanen');
                loadNotes({ silent: true });
            } catch (error) {
                toast(error.message, 'error');
            }
        });

        el.menuBtn.addEventListener('click', function () {
            if (document.body.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        el.mobileOverlay.addEventListener('click', closeSidebar);

        el.userBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleUserMenu();
        });

        el.userPopover.addEventListener('click', function (event) {
            if (event.target.closest('[data-action="logout"]')) {
                logout();
            }
        });

        document.addEventListener('click', function (event) {
            if (!el.userPopover.hidden && !el.userPopover.contains(event.target) && !el.userBtn.contains(event.target)) {
                closeUserMenu();
            }
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-theme-toggle]'), function (button) {
            button.addEventListener('click', toggleTheme);
        });

        $$('[data-close-editor]').forEach(function (button) {
            button.addEventListener('click', closeEditor);
        });

        $$('[data-confirm-cancel]').forEach(function (button) {
            button.addEventListener('click', function () { resolveConfirm(false); });
        });

        $('#confirmOk').addEventListener('click', function () { resolveConfirm(true); });

        $$('[data-label-close]').forEach(function (button) {
            button.addEventListener('click', function () { closeLabelDialog(null); });
        });

        $('#labelForm').addEventListener('submit', submitLabelForm);

        window.addEventListener('scroll', function (event) {
            if (state.popover && !state.popover.contains(event.target)) {
                closePopover();
            }
        }, true);

        window.addEventListener('resize', closePopover);

        window.addEventListener('beforeunload', function (event) {
            if (state.dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (state.popover) {
                    closePopover();
                    return;
                }
                if (state.confirmResolver) {
                    resolveConfirm(false);
                    return;
                }
                if (state.labelResolver) {
                    closeLabelDialog(null);
                    return;
                }
                if (state.editing) {
                    closeEditor();
                    return;
                }
                if (!el.userPopover.hidden) {
                    closeUserMenu();
                    return;
                }
                if (document.body.classList.contains('sidebar-open')) {
                    closeSidebar();
                }
                return;
            }

            if (event.metaKey || event.ctrlKey || event.altKey || isTyping(event.target)) {
                return;
            }

            if (state.editing || state.confirmResolver || state.labelResolver) {
                return;
            }

            if (event.key === '/') {
                event.preventDefault();
                el.searchInput.focus();
                el.searchInput.select();
            } else if (event.key === 'n' || event.key === 'N') {
                event.preventDefault();
                openNewNote();
            }
        });
    }

    function init() {
        if (!getToken()) {
            goToLogin();
            return;
        }

        el.notesArea = $('#notesArea');
        el.pageTitle = $('#pageTitle');
        el.pageSubtitle = $('#pageSubtitle');
        el.searchInput = $('#searchInput');
        el.searchClear = $('#searchClear');
        el.labelList = $('#labelList');
        el.labelEmpty = $('#labelEmpty');
        el.countNotes = $('#countNotes');
        el.countArchive = $('#countArchive');
        el.countTrash = $('#countTrash');
        el.emptyTrashBtn = $('#emptyTrashBtn');
        el.menuBtn = $('#menuBtn');
        el.mobileOverlay = $('#mobileOverlay');
        el.userBtn = $('#userBtn');
        el.userPopover = $('#userPopover');
        el.addLabelBtn = $('#addLabelBtn');
        el.newNoteBtn = $('#newNoteBtn');
        el.composerBtn = $('#composerBtn');
        el.editorModal = $('#editorModal');
        el.editorHost = $('#editorHost');
        el.confirmModal = $('#confirmModal');
        el.labelModal = $('#labelModal');
        el.toastStack = $('#toastStack');

        wireGlobalEvents();
        wireEditorEvents();

        api('/me').then(function (data) {
            applyUser(data.user);
        }).catch(function () {});

        loadNotes();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
