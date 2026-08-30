/* ==========================================================================
   Panel de administracion - interacciones minimas, sin dependencias
   ========================================================================== */
(function () {
    'use strict';

    /* ---------- Menu lateral en pantallas pequenas ---------- */
    var toggle = document.querySelector('.menu-toggle');
    var sidebar = document.querySelector('.sidebar');
    var backdrop = document.querySelector('.sidebar-backdrop');

    function closeSidebar() {
        if (sidebar) { sidebar.classList.remove('is-open'); }
        if (backdrop) { backdrop.classList.remove('is-visible'); }
        if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
    }

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('is-open');
            if (backdrop) { backdrop.classList.toggle('is-visible', open); }
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    if (backdrop) { backdrop.addEventListener('click', closeSidebar); }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeSidebar(); }
    });

    /* ---------- Confirmacion en acciones destructivas ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    /* ---------- Visor de comprobantes ---------- */
    var lightbox = document.querySelector('[data-lightbox]');

    if (lightbox) {
        var lightboxImage = lightbox.querySelector('img');

        document.querySelectorAll('[data-lightbox-src]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                lightboxImage.src = thumb.getAttribute('data-lightbox-src');
                lightbox.classList.add('is-open');
            });
        });

        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox || event.target.hasAttribute('data-lightbox-close')) {
                lightbox.classList.remove('is-open');
                lightboxImage.src = '';
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
                lightbox.classList.remove('is-open');
                lightboxImage.src = '';
            }
        });
    }

    /* ---------- Vista previa de imagenes antes de subirlas ---------- */
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var target = document.querySelector(input.getAttribute('data-preview'));

        if (!target) { return; }

        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) { return; }

            var file = input.files[0];

            if (file.type.indexOf('image/') !== 0) { return; }

            var url = URL.createObjectURL(file);
            target.src = url;
            target.classList.remove('hidden');
            target.onload = function () { URL.revokeObjectURL(url); };
        });
    });

    /* ---------- Selección múltiple con "marcar todos" ---------- */
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        var selector = master.getAttribute('data-check-all');

        master.addEventListener('change', function () {
            document.querySelectorAll(selector).forEach(function (box) {
                box.checked = master.checked;
            });
        });
    });

    /* ---------- Evita el doble envio ---------- */
    document.querySelectorAll('form:not([data-allow-resubmit])').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[type="submit"]');

            if (submit) {
                setTimeout(function () { submit.disabled = true; }, 20);
                // Si el navegador vuelve atras, el boton debe reactivarse.
                window.addEventListener('pageshow', function () { submit.disabled = false; });
            }
        });
    });

    /* ---------- Envio automatico de filtros ---------- */
    document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
        control.addEventListener('change', function () {
            var form = control.closest('form');
            if (form) { form.submit(); }
        });
    });

    /* ---------- Copiar al portapapeles ---------- */
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = button.getAttribute('data-copy') || '';
            var original = button.textContent;

            function done() {
                button.textContent = 'Copiado';
                setTimeout(function () { button.textContent = original; }, 1500);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done).catch(function () {});
            } else {
                var input = document.createElement('textarea');
                input.value = text;
                input.style.position = 'absolute';
                input.style.left = '-9999px';
                document.body.appendChild(input);
                input.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(input);
            }
        });
    });

    /* ---------- Sincroniza el color escrito con el selector ---------- */
    document.querySelectorAll('[data-color-sync]').forEach(function (picker) {
        var text = document.querySelector(picker.getAttribute('data-color-sync'));

        if (!text) { return; }

        picker.addEventListener('input', function () { text.value = picker.value; });
        text.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(text.value)) { picker.value = text.value; }
        });
    });
})();
