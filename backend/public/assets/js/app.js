/* ==========================================================================
   Plataforma Estilo - guion del sitio publico
   --------------------------------------------------------------------------
   Sin dependencias externas. Todo lo dinamico (publicidad, agendamiento)
   habla con el servidor mediante peticiones con token anti-CSRF.
   ========================================================================== */
(function () {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

    var baseUrl = document.body.getAttribute('data-base-url') || '';

    function post(url, data) {
        return fetch(baseUrl + url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data || {})
        }).then(function (response) {
            if (!response.ok && response.status !== 204) {
                return response.json().then(function (payload) {
                    throw new Error((payload.error && payload.error.message) || 'Error de red');
                });
            }
            return response.status === 204 ? null : response.json();
        });
    }

    function get(url) {
        return fetch(baseUrl + url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    /* ---------- Menu movil ---------- */
    var navToggle = document.querySelector('.nav-toggle');
    var nav = document.querySelector('.nav');

    if (navToggle && nav) {
        navToggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        nav.addEventListener('click', function (event) {
            if (event.target.tagName === 'A') { nav.classList.remove('is-open'); }
        });
    }

    /* ---------- Aviso de cookies ---------- */
    var cookieBanner = document.querySelector('[data-cookie-banner]');

    if (cookieBanner) {
        var accepted = false;
        try { accepted = localStorage.getItem('cookies_ok') === '1'; } catch (e) { accepted = false; }

        if (!accepted) {
            cookieBanner.classList.add('is-visible');
        }

        var acceptBtn = cookieBanner.querySelector('[data-cookie-accept]');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                try { localStorage.setItem('cookies_ok', '1'); } catch (e) { /* modo privado */ }
                cookieBanner.classList.remove('is-visible');
            });
        }
    }

    /* ---------- Publicidad ---------- */
    var adModal = document.querySelector('[data-ad-modal]');
    var adShown = {};

    function trackAd(bannerId, event, placement) {
        post('/publicidad/evento', { banner_id: bannerId, event: event, placement: placement })
            .catch(function () { /* la medicion nunca debe romper la pagina */ });
    }

    function renderAdModal(banner) {
        if (!adModal || !banner || adShown[banner.placement]) { return; }
        adShown[banner.placement] = true;

        var box = adModal.querySelector('.ad-modal__box');
        var image = banner.mobile_image_url && window.innerWidth < 720
            ? banner.mobile_image_url
            : banner.image_url;

        var html = '';
        html += '<button type="button" class="ad-modal__close" aria-label="Cerrar">&times;</button>';
        if (image) {
            html += '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(banner.title) + '">';
        }
        html += '<div class="ad-modal__body">';
        if (banner.title) { html += '<h3>' + escapeHtml(banner.title) + '</h3>'; }
        if (banner.subtitle) { html += '<p>' + escapeHtml(banner.subtitle) + '</p>'; }
        if (banner.cta_label && banner.cta_url) {
            html += '<a class="btn btn--primary" data-ad-cta href="' + escapeHtml(banner.cta_url) + '">'
                + escapeHtml(banner.cta_label) + '</a>';
        }
        html += '</div>';

        box.innerHTML = html;
        box.style.background = banner.background_color || '#141b2d';
        box.style.color = banner.text_color || '#ffffff';

        adModal.classList.add('is-open');
        adModal.setAttribute('aria-hidden', 'false');
        trackAd(banner.id, 'impression', banner.placement);

        function close(reason) {
            adModal.classList.remove('is-open');
            adModal.setAttribute('aria-hidden', 'true');
            if (reason === 'dismiss') { trackAd(banner.id, 'dismiss', banner.placement); }
        }

        box.querySelector('.ad-modal__close').addEventListener('click', function () { close('dismiss'); });
        adModal.addEventListener('click', function (event) {
            if (event.target === adModal && banner.is_dismissible !== false) { close('dismiss'); }
        });

        var cta = box.querySelector('[data-ad-cta]');
        if (cta) {
            cta.addEventListener('click', function () { trackAd(banner.id, 'click', banner.placement); });
        }

        if (banner.auto_close_seconds > 0) {
            setTimeout(function () { close('auto'); }, banner.auto_close_seconds * 1000);
        }

        document.addEventListener('keydown', function onEsc(event) {
            if (event.key === 'Escape') { close('dismiss'); document.removeEventListener('keydown', onEsc); }
        });
    }

    function loadAd(placement) {
        return get('/publicidad/obtener?placement=' + encodeURIComponent(placement))
            .then(function (payload) {
                if (payload && payload.ok && payload.data && payload.data.banner) {
                    var banner = payload.data.banner;
                    setTimeout(function () { renderAdModal(banner); }, (banner.delay_seconds || 0) * 1000);
                }
            })
            .catch(function () { /* sin publicidad si algo falla */ });
    }

    if (adModal) {
        var config = document.body.dataset;

        // 1. Al iniciar sesion
        if (config.adOnLogin === '1') { loadAd('on_login'); }

        // 2. Mientras navega
        if (config.adBrowsing === '1') {
            var delay = parseInt(config.adBrowsingDelay || '45', 10);
            setTimeout(function () { loadAd('while_browsing'); }, delay * 1000);
        }

        // 3. Al intentar salir (el cursor sube fuera de la ventana)
        if (config.adOnExit === '1') {
            var exitArmed = false;
            setTimeout(function () { exitArmed = true; }, 8000);

            document.addEventListener('mouseleave', function (event) {
                if (exitArmed && event.clientY <= 0 && !adShown.on_exit) {
                    exitArmed = false;
                    loadAd('on_exit');
                }
            });
        }
    }

    // Medicion de los banners incrustados en la pagina
    document.querySelectorAll('[data-banner-id]').forEach(function (element) {
        var bannerId = parseInt(element.getAttribute('data-banner-id'), 10);
        var placement = element.getAttribute('data-banner-placement') || '';

        if (!bannerId) { return; }

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        trackAd(bannerId, 'impression', placement);
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.5 });
            observer.observe(element);
        } else {
            trackAd(bannerId, 'impression', placement);
        }

        element.querySelectorAll('[data-banner-cta]').forEach(function (cta) {
            cta.addEventListener('click', function () { trackAd(bannerId, 'click', placement); });
        });

        var closer = element.querySelector('[data-banner-close]');
        if (closer) {
            closer.addEventListener('click', function () {
                element.remove();
                trackAd(bannerId, 'dismiss', placement);
            });
        }
    });

    /* ---------- Copiar datos bancarios ---------- */
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var text = button.getAttribute('data-copy') || '';
            var original = button.textContent;

            function done() {
                button.textContent = 'Copiado';
                setTimeout(function () { button.textContent = original; }, 1600);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done).catch(function () { fallback(text, done); });
            } else {
                fallback(text, done);
            }
        });
    });

    function fallback(text, done) {
        var input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'absolute';
        input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* sin portapapeles */ }
        document.body.removeChild(input);
    }

    /* ---------- Vista previa de la subida de comprobante ---------- */
    document.querySelectorAll('[data-upload]').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        var preview = zone.querySelector('[data-upload-preview]');

        if (!input) { return; }

        zone.addEventListener('click', function (event) {
            if (event.target !== input) { input.click(); }
        });

        ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function (event) {
                event.preventDefault();
                zone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function (event) {
                event.preventDefault();
                zone.classList.remove('is-dragging');
            });
        });

        zone.addEventListener('drop', function (event) {
            if (event.dataTransfer.files.length > 0) {
                input.files = event.dataTransfer.files;
                showPreview();
            }
        });

        input.addEventListener('change', showPreview);

        function showPreview() {
            if (!preview || !input.files || !input.files[0]) { return; }

            var file = input.files[0];
            preview.innerHTML = '';

            if (file.type.indexOf('image/') === 0) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = 'Vista previa del comprobante';
                img.onload = function () { URL.revokeObjectURL(img.src); };
                preview.appendChild(img);
            } else {
                var note = document.createElement('p');
                note.className = 'text-muted mt-2';
                note.textContent = file.name;
                preview.appendChild(note);
            }
        }
    });

    /* ---------- Cierre de avisos ---------- */
    document.querySelectorAll('[data-dismiss-alert]').forEach(function (button) {
        button.addEventListener('click', function () {
            var alertBox = button.closest('.alert');
            if (alertBox) { alertBox.remove(); }
        });
    });

    /* ---------- Confirmacion en acciones destructivas ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    /* ---------- Evita el doble envio de formularios ---------- */
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[type="submit"]');
            if (submit && !form.hasAttribute('data-allow-resubmit')) {
                setTimeout(function () {
                    submit.disabled = true;
                    submit.classList.add('is-disabled');
                }, 10);
            }
        });
    });

    // Se expone lo minimo para el guion del agendamiento.
    window.Estilo = { post: post, get: get, escapeHtml: escapeHtml };
})();
