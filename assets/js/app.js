(function () {
    var doc = document;
    var body = doc.body;

    function on(selector, event, handler) {
        doc.addEventListener(event, function (e) {
            var target = e.target.closest(selector);
            if (target) {
                handler(e, target);
            }
        });
    }

    var sidebar = doc.getElementById('sidebar');

    function setSidebar(open) {
        if (!sidebar) {
            return;
        }
        sidebar.classList.toggle('is-open', open);
        body.classList.toggle('nav-open', open);
    }

    on('[data-sidebar-open]', 'click', function () { setSidebar(true); });
    on('[data-sidebar-close]', 'click', function () { setSidebar(false); });

    on('[data-theme-toggle]', 'click', function () {
        var root = doc.documentElement;
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('theme', next); } catch (err) {}
    });

    on('[data-menu-toggle]', 'click', function (e, el) {
        el.closest('.menu').classList.toggle('is-open');
    });

    doc.addEventListener('click', function (e) {
        doc.querySelectorAll('.menu.is-open').forEach(function (menu) {
            if (!menu.contains(e.target)) {
                menu.classList.remove('is-open');
            }
        });
    });

    on('.flash-close', 'click', function (e, el) {
        var flash = el.closest('.flash');
        flash.remove();
    });

    setTimeout(function () {
        doc.querySelectorAll('.flash-success').forEach(function (el) { el.remove(); });
    }, 6000);

    function fillForm(form, data) {
        form.reset();
        Object.keys(data).forEach(function (key) {
            var fields = form.querySelectorAll('[name="' + key + '"]');
            fields.forEach(function (field) {
                if (field.type === 'radio') {
                    field.checked = String(field.value) === String(data[key]);
                } else if (field.type === 'checkbox') {
                    field.checked = !!Number(data[key]);
                } else {
                    field.value = data[key] === null ? '' : data[key];
                }
            });
        });
        form.dispatchEvent(new CustomEvent('filled'));
    }

    on('[data-open-dialog]', 'click', function (e, el) {
        var dialog = doc.getElementById(el.getAttribute('data-open-dialog'));
        if (!dialog) {
            return;
        }
        var form = dialog.querySelector('form');
        var data = {};
        try { data = JSON.parse(el.getAttribute('data-fill') || '{}'); } catch (err) {}
        if (form) {
            fillForm(form, data);
        }
        var title = dialog.querySelector('[data-dialog-title]');
        if (title && el.getAttribute('data-title')) {
            title.textContent = el.getAttribute('data-title');
        }
        dialog.showModal();
        var first = dialog.querySelector('input:not([type=hidden]):not([type=radio]):not([type=checkbox]), select');
        if (first) {
            first.focus();
        }
    });

    on('[data-close-dialog]', 'click', function (e, el) {
        e.preventDefault();
        el.closest('dialog').close();
    });

    doc.querySelectorAll('dialog.dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog) {
                dialog.close();
            }
        });
    });

    var confirmDialog = doc.getElementById('confirmDialog');
    var pendingForm = null;

    doc.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm') || form.dataset.confirmed === '1') {
            return;
        }
        e.preventDefault();
        pendingForm = form;
        doc.getElementById('confirmTitle').textContent = form.getAttribute('data-confirm-title') || 'Are you sure?';
        doc.getElementById('confirmText').textContent = form.getAttribute('data-confirm');
        confirmDialog.querySelector('.btn-danger').textContent = form.getAttribute('data-confirm-button') || 'Delete';
        confirmDialog.showModal();
    });

    if (confirmDialog) {
        confirmDialog.addEventListener('close', function () {
            if (confirmDialog.returnValue === 'ok' && pendingForm) {
                pendingForm.dataset.confirmed = '1';
                pendingForm.submit();
            }
            pendingForm = null;
            confirmDialog.returnValue = '';
        });
        confirmDialog.addEventListener('click', function (e) {
            if (e.target === confirmDialog) {
                confirmDialog.close();
            }
        });
    }

    on('[data-toggle-password]', 'click', function (e, el) {
        var input = el.closest('.input-group').querySelector('input');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        el.querySelector('.eye-on').style.display = show ? 'none' : 'block';
        el.querySelector('.eye-off').style.display = show ? 'block' : 'none';
    });

    var strength = doc.querySelector('[data-strength]');
    var strengthInput = doc.querySelector('[data-strength-input]');
    if (strength && strengthInput) {
        strengthInput.addEventListener('input', function () {
            var v = strengthInput.value;
            var level = 0;
            if (v.length >= 8) { level++; }
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) { level++; }
            if (/\d/.test(v)) { level++; }
            if (/[^A-Za-z0-9]/.test(v) || v.length >= 14) { level++; }
            strength.setAttribute('data-level', v ? level : 0);
        });
    }

    var txnForm = doc.getElementById('txnForm');
    if (txnForm) {
        var typeRadios = txnForm.querySelectorAll('input[name="type"]');
        var categorySelect = txnForm.querySelector('[name="category_id"]');
        var allOptions = categorySelect ? Array.prototype.slice.call(categorySelect.querySelectorAll('option[data-type]')) : [];

        function currentType() {
            var checked = txnForm.querySelector('input[name="type"]:checked');
            return checked ? checked.value : 'expense';
        }

        function syncType() {
            var type = currentType();
            txnForm.querySelectorAll('[data-show]').forEach(function (el) {
                var types = el.getAttribute('data-show').split(' ');
                var visible = types.indexOf(type) !== -1;
                el.hidden = !visible;
                el.querySelectorAll('input, select, textarea').forEach(function (input) {
                    input.disabled = !visible;
                });
            });
            if (categorySelect) {
                var selected = categorySelect.value;
                allOptions.forEach(function (option) {
                    var match = option.getAttribute('data-type') === type;
                    option.hidden = !match;
                    option.disabled = !match;
                });
                var current = categorySelect.querySelector('option[value="' + selected + '"]');
                if (!current || current.disabled) {
                    categorySelect.value = '';
                }
            }
            var label = txnForm.querySelector('[data-party-label]');
            if (label) {
                label.textContent = type === 'income' ? 'Received from' : 'Paid to';
            }
            var reason = txnForm.querySelector('[data-reason-label]');
            if (reason) {
                reason.textContent = type === 'income' ? 'Why did you receive it?' : (type === 'transfer' ? 'Reason' : 'Why did you spend it?');
            }
            var submit = txnForm.querySelector('[data-submit-label]');
            if (submit) {
                submit.textContent = submit.getAttribute('data-' + type) || 'Save';
            }
        }

        typeRadios.forEach(function (radio) { radio.addEventListener('change', syncType); });
        syncType();
    }

    on('[data-fill-amount]', 'click', function (e, el) {
        var target = doc.querySelector(el.getAttribute('data-fill-target'));
        if (target) {
            target.value = el.getAttribute('data-fill-amount');
        }
    });

    on('[data-submit-on-change]', 'change', function (e, el) {
        el.form.submit();
    });

    on('[data-print]', 'click', function () { window.print(); });

    var periodSelect = doc.querySelector('[data-period-select]');
    if (periodSelect) {
        var customFields = doc.querySelectorAll('[data-custom-range]');
        var syncPeriod = function () {
            customFields.forEach(function (el) { el.hidden = periodSelect.value !== 'custom'; });
        };
        periodSelect.addEventListener('change', syncPeriod);
        syncPeriod();
    }

    var installmentRadios = doc.querySelectorAll('input[name="mode"]');
    if (installmentRadios.length) {
        var syncMode = function () {
            var checked = doc.querySelector('input[name="mode"]:checked');
            var mode = checked ? checked.value : 'once';
            doc.querySelectorAll('[data-mode]').forEach(function (el) {
                var visible = el.getAttribute('data-mode') === mode;
                el.hidden = !visible;
                el.querySelectorAll('input').forEach(function (input) { input.disabled = !visible; });
            });
        };
        installmentRadios.forEach(function (r) { r.addEventListener('change', syncMode); });
        syncMode();
    }
})();
