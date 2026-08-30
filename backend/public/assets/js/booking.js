/* ==========================================================================
   Agendamiento en la web
   --------------------------------------------------------------------------
   Consulta la disponibilidad real al servidor y va armando el resumen.
   El servidor vuelve a validar todo al confirmar: aqui solo se guia al
   usuario, nunca se confia en estos calculos.
   ========================================================================== */
(function () {
    'use strict';

    var form = document.getElementById('booking-form');
    if (!form) { return; }

    var config = window.BOOKING_CONFIG || {};
    var api = window.Estilo;

    var branchInput = document.getElementById('branch_id');
    var staffInput = document.getElementById('staff_id');
    var dateInput = document.getElementById('selected_date');
    var timeInput = document.getElementById('selected_time');
    var branchSelect = document.getElementById('branch-select');
    var customRequest = document.getElementById('custom_request');
    var dayStrip = document.getElementById('day-strip');
    var slotArea = document.getElementById('slot-area');
    var submitButton = document.getElementById('submit-booking');
    var transferNote = document.getElementById('transfer-note');

    var state = { services: [], staffId: 0, date: '', time: '', duration: 0, total: 0 };

    function formatMoney(amount) {
        var value = Number(amount).toFixed(config.decimals != null ? config.decimals : 2);
        return config.currencyPosition === 'after'
            ? value + ' ' + (config.currencySymbol || '$')
            : (config.currencySymbol || '$') + ' ' + value;
    }

    function formatDuration(minutes) {
        if (minutes < 60) { return minutes + ' min'; }
        var hours = Math.floor(minutes / 60);
        var rest = minutes % 60;
        return rest === 0 ? hours + ' h' : hours + ' h ' + rest + ' min';
    }

    /* ---------- Seleccion de servicios ---------- */
    function collectServices() {
        var inputs = form.querySelectorAll('input[name="service_ids[]"]:checked');
        var services = [];

        inputs.forEach(function (input) {
            services.push({
                id: parseInt(input.value, 10),
                name: input.getAttribute('data-name') || '',
                price: parseFloat(input.getAttribute('data-price') || '0'),
                duration: parseInt(input.getAttribute('data-duration') || '0', 10)
            });
        });

        return services;
    }

    function enforceServiceLimit() {
        var max = config.maxServices || 4;
        var checked = form.querySelectorAll('input[name="service_ids[]"]:checked');

        form.querySelectorAll('input[name="service_ids[]"]').forEach(function (input) {
            if (input.type === 'checkbox') {
                input.disabled = !input.checked && checked.length >= max;
            }
        });
    }

    function updateSummary() {
        state.services = collectServices();
        state.total = 0;
        state.duration = 0;

        var container = document.getElementById('summary-services');
        container.innerHTML = '';

        if (state.services.length === 0) {
            var hasCustom = customRequest && customRequest.value.trim() !== '';

            if (hasCustom) {
                state.duration = config.customMinutes || 30;
                container.innerHTML = '<div class="summary-row"><span>'
                    + api.escapeHtml(customRequest.value.trim())
                    + '</span><strong>A convenir</strong></div>';
            } else {
                container.innerHTML = '<p class="text-muted text-small">Aun no has elegido ningun servicio.</p>';
            }
        } else {
            state.services.forEach(function (service) {
                state.total += service.price;
                state.duration += service.duration;

                var row = document.createElement('div');
                row.className = 'summary-row';
                row.innerHTML = '<span>' + api.escapeHtml(service.name) + '</span>'
                    + '<strong>' + formatMoney(service.price) + '</strong>';
                container.appendChild(row);
            });
        }

        toggleRow('summary-duration-row', state.duration > 0);
        document.getElementById('summary-duration').textContent = formatDuration(state.duration);
        document.getElementById('summary-total').textContent = formatMoney(state.total);

        enforceServiceLimit();
        updateSubmitState();
    }

    function toggleRow(id, visible) {
        var row = document.getElementById(id);
        if (row) { row.hidden = !visible; }
    }

    function updateSubmitState() {
        var ready = state.duration > 0 && state.date !== '' && state.time !== '';
        submitButton.disabled = !ready;
        submitButton.classList.toggle('is-disabled', !ready);

        markStep(1, state.duration > 0);
        markStep(3, state.date !== '' && state.time !== '');
    }

    function markStep(number, done) {
        var indicator = document.querySelector('[data-step-indicator="' + number + '"]');
        if (indicator) { indicator.classList.toggle('is-done', done); }
    }

    /* ---------- Disponibilidad ---------- */
    function requestPayload(date) {
        return {
            branch_id: parseInt(branchInput.value, 10) || 0,
            staff_id: state.staffId,
            service_ids: state.services.map(function (s) { return s.id; }),
            date: date || ''
        };
    }

    function loadDays() {
        if (state.duration === 0) {
            dayStrip.innerHTML = '<p class="text-muted text-small">Elige un servicio para ver los dias disponibles.</p>';
            slotArea.innerHTML = '';
            return;
        }

        dayStrip.innerHTML = '<div class="spinner"></div>';
        slotArea.innerHTML = '';

        api.post('/agendar/disponibilidad', requestPayload(''))
            .then(function (payload) {
                var days = (payload && payload.data && payload.data.days) || [];
                dayStrip.innerHTML = '';

                if (days.length === 0) {
                    dayStrip.innerHTML = '<div class="alert alert--warning" style="width:100%">'
                        + 'No hay horarios disponibles con esta combinacion. '
                        + 'Prueba con otro profesional o escribenos.</div>';
                    return;
                }

                days.forEach(function (day, index) {
                    var chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'day-chip';
                    chip.setAttribute('data-date', day.date);

                    var parts = day.date.split('-');
                    chip.innerHTML = '<span class="day-chip__weekday">' + api.escapeHtml(day.label) + '</span>'
                        + '<span class="day-chip__num">' + api.escapeHtml(parts[2]) + '</span>'
                        + '<span class="day-chip__slots">' + day.slots + ' libres</span>';

                    chip.addEventListener('click', function () { selectDay(day.date, chip); });
                    dayStrip.appendChild(chip);

                    if (index === 0) { selectDay(day.date, chip); }
                });
            })
            .catch(function (error) {
                dayStrip.innerHTML = '<div class="alert alert--error" style="width:100%">'
                    + api.escapeHtml(error.message || 'No pudimos consultar la disponibilidad.') + '</div>';
            });
    }

    function selectDay(date, chip) {
        state.date = date;
        state.time = '';
        dateInput.value = date;
        timeInput.value = '';

        dayStrip.querySelectorAll('.day-chip').forEach(function (element) {
            element.classList.remove('is-selected');
        });
        if (chip) { chip.classList.add('is-selected'); }

        slotArea.innerHTML = '<div class="spinner"></div>';
        updateSubmitState();

        api.post('/agendar/disponibilidad', requestPayload(date))
            .then(function (payload) {
                var slots = (payload && payload.data && payload.data.slots) || [];
                slotArea.innerHTML = '';

                if (slots.length === 0) {
                    slotArea.innerHTML = '<div class="alert alert--warning">Ese dia se lleno. Elige otra fecha.</div>';
                    return;
                }

                var grid = document.createElement('div');
                grid.className = 'slot-grid';

                slots.forEach(function (slot) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'slot';
                    button.textContent = slot.label;
                    button.addEventListener('click', function () {
                        grid.querySelectorAll('.slot').forEach(function (element) {
                            element.classList.remove('is-selected');
                        });
                        button.classList.add('is-selected');
                        state.time = slot.time;
                        timeInput.value = slot.time;
                        updateWhen();
                        updateSubmitState();
                    });
                    grid.appendChild(button);
                });

                slotArea.appendChild(grid);
            })
            .catch(function (error) {
                slotArea.innerHTML = '<div class="alert alert--error">'
                    + api.escapeHtml(error.message || 'No pudimos cargar los horarios.') + '</div>';
            });
    }

    function updateWhen() {
        toggleRow('summary-when-row', state.date !== '' && state.time !== '');
        var target = document.getElementById('summary-when');

        if (state.date && state.time) {
            var parts = state.date.split('-');
            target.textContent = parts[2] + '/' + parts[1] + '/' + parts[0] + ' a las ' + state.time;
        }
    }

    /* ---------- Escuchadores ---------- */
    var debounceTimer = null;

    function scheduleReload() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadDays, 250);
    }

    form.querySelectorAll('input[name="service_ids[]"]').forEach(function (input) {
        input.addEventListener('change', function () {
            updateSummary();
            scheduleReload();
        });
    });

    form.querySelectorAll('input[name="staff_choice"]').forEach(function (input) {
        input.addEventListener('change', function () {
            state.staffId = parseInt(input.value, 10) || 0;
            staffInput.value = state.staffId;

            var label = input.closest('.service-option');
            var name = label ? label.querySelector('.service-option__name') : null;

            toggleRow('summary-staff-row', true);
            document.getElementById('summary-staff').textContent = name ? name.textContent : 'Sin preferencia';

            markStep(2, true);
            scheduleReload();
        });
    });

    if (branchSelect) {
        branchSelect.addEventListener('change', function () {
            branchInput.value = branchSelect.value;
            scheduleReload();
        });
    }

    if (customRequest) {
        customRequest.addEventListener('input', function () {
            updateSummary();
            scheduleReload();
        });
    }

    form.querySelectorAll('input[name="payment_method_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (transferNote) {
                transferNote.classList.toggle('hidden', input.getAttribute('data-shows-bank') !== '1');
            }
        });
    });

    // No permite enviar sin fecha ni hora, aunque alguien manipule el boton.
    form.addEventListener('submit', function (event) {
        if (!dateInput.value || !timeInput.value) {
            event.preventDefault();
            window.alert('Selecciona una fecha y un horario antes de confirmar.');
        }
    });

    // Arranque
    updateSummary();
    if (state.duration > 0) { loadDays(); }
})();
