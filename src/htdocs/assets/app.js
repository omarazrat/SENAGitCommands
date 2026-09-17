(function () {
    'use strict';

    var html = document.documentElement;
    var KEY = 'theme';

    function current() {
        var s = localStorage.getItem(KEY);
        if (s === 'light' || s === 'dark') {
            return s;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function apply(t) {
        html.setAttribute('data-bs-theme', t);
    }

    function refreshIcon() {
        var icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = current() === 'dark'
                ? 'bi bi-sun-fill'
                : 'bi bi-moon-stars-fill';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        apply(current());
        refreshIcon();

        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', function () {
                var next = current() === 'dark' ? 'light' : 'dark';
                apply(next);
                localStorage.setItem(KEY, next);
                refreshIcon();
            });
        }
    });
})();