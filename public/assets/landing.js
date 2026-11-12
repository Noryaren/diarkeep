(function () {
    'use strict';

    var CONFIG = window.DIAR || {};
    var base = CONFIG.base || '';
    var TOKEN_KEY = 'diarkeep.token';
    var modal = document.getElementById('authModal');
    var loginForm = document.getElementById('loginForm');
    var lastTrigger = null;

    function getToken() {
        try {
            return localStorage.getItem(TOKEN_KEY) || '';
        } catch (error) {
            return '';
        }
    }

    function setToken(token) {
        try {
            localStorage.setItem(TOKEN_KEY, token);
        } catch (error) {}
    }

    function clearToken() {
        try {
            localStorage.removeItem(TOKEN_KEY);
        } catch (error) {}
    }

    function appUrl() {
        return base + '/app';
    }

    function api(path, options) {
        options = options || {};
        var headers = { 'Accept': 'application/json' };
        var body;

        if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(options.body);
        }

        var token = getToken();
        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }

        return fetch(base + '/api' + path, {
            method: options.method || 'GET',
            headers: headers,
            body: body
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                if (response.status === 401) {
                    clearToken();
                }
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
                .then(function (result) {
                    if (result && result.token) {
                        setToken(result.token);
                    }
                    window.location.replace(appUrl());
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
            method: 'POST',
            body: {
                email: String(data.get('email') || '').trim(),
                password: String(data.get('password') || '')
            }
        });
    });

    // Already holding a valid token? Jump straight to the board.
    if (getToken()) {
        api('/me').then(function () {
            window.location.replace(appUrl());
        }).catch(function () {});
    }
})();
