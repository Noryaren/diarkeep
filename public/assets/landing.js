(function () {
    'use strict';

    var CONFIG = window.DIAR || {};
    var base = CONFIG.base || '';
    var modal = document.getElementById('authModal');
    var loginForm = document.getElementById('loginForm');
    var lastTrigger = null;

    function api(path, body) {
        return fetch(base + '/api' + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': CONFIG.csrf || ''
            },
            body: JSON.stringify(body || {})
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (!response.ok) {
                    throw new Error(data.error || 'Terjadi kesalahan. Coba lagi.');
                }
                return data;
            });
        });
    }

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

    Array.prototype.forEach.call(document.querySelectorAll('[data-theme-toggle]'), function (button) {
        button.addEventListener('click', toggleTheme);
    });

    function setError(form, message) {
        var box = form.querySelector('[data-auth-error]');
        if (!box) {
            return;
        }
        box.textContent = message || '';
        box.hidden = !message;
    }

    function openAuth(trigger) {
        lastTrigger = trigger || null;
        setError(loginForm, '');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        var field = loginForm.querySelector('input');
        if (field) {
            window.setTimeout(function () { field.focus(); }, 80);
        }
    }

    function closeAuth() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setError(loginForm, '');
        if (lastTrigger && typeof lastTrigger.focus === 'function') {
            lastTrigger.focus();
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-auth]'), function (button) {
        button.addEventListener('click', function () {
            openAuth(button);
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-auth-close]'), function (button) {
        button.addEventListener('click', closeAuth);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeAuth();
        }
    });

    function submit(form, handler) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            setError(form, '');

            var button = form.querySelector('button[type="submit"]');
            var data = new FormData(form);

            if (!form.reportValidity()) {
                return;
            }

            button.classList.add('is-loading');

            handler(data)
                .then(function () {
                    window.location.reload();
                })
                .catch(function (error) {
                    setError(form, error.message);
                    button.classList.remove('is-loading');
                    return false;
                });
        });
    }

    submit(loginForm, function (data) {
        return api('/auth/login', {
            email: String(data.get('email') || '').trim(),
            password: String(data.get('password') || '')
        });
    });
})();
