(function () {
    var onText = 'Вкл';
    var offText = 'Выкл';

    function updateStats(stats) {
        if (!stats) {
            return;
        }

        Object.keys(stats).forEach(function (key) {
            var el = document.querySelector('[data-optimize-stat="' + key + '"]');

            if (el) {
                el.textContent = stats[key];
            }
        });
    }

    function updateStatus(settings) {
        if (!settings) {
            settings = {};
            document.querySelectorAll('input.optimize-toggle[type="checkbox"]').forEach(function (input) {
                settings[input.name] = input.checked;
            });
        }

        Object.keys(settings).forEach(function (key) {
            var el = document.querySelector('[data-optimize-status="' + key + '"]');

            if (!el) {
                return;
            }

            var active = !!settings[key];
            el.classList.toggle('is-active', active);
            el.classList.toggle('is-inactive', !active);

            var state = el.querySelector('em');
            if (state) {
                state.textContent = active ? onText : offText;
            }
        });
    }

    function postOptimize(formData) {
        return fetch('/admin/optimize/ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json();
        });
    }

    function refreshOptimizeStats() {
        var formData = new FormData();
        formData.append('action', 'stats');

        postOptimize(formData)
            .then(function (data) {
                if (!data || data.status !== 'ok') {
                    return;
                }

                updateStats(data.stats);
                updateStatus(data.settings);
                syncDependentToggles(data.settings);
            })
            .catch(function () {});
    }

    function syncDependentToggles(settings) {
        var combineCss = document.querySelector('input.optimize-toggle[name="combine_css"]');
        var minifyCss = document.querySelector('input.optimize-toggle[name="minify_css"]');
        var combineJs = document.querySelector('input.optimize-toggle[name="combine_js"]');
        var minifyJs = document.querySelector('input.optimize-toggle[name="minify_js"]');

        if (settings) {
            document.querySelectorAll('input.optimize-toggle[type="checkbox"]').forEach(function (input) {
                if (typeof settings[input.name] !== 'undefined') {
                    input.checked = !!settings[input.name];
                }
            });
        }

        if (combineCss && minifyCss) {
            minifyCss.disabled = !combineCss.checked;
            minifyCss.closest('.optimize-switch-field').classList.toggle('is-disabled', !combineCss.checked);
        }

        if (combineJs && minifyJs) {
            minifyJs.disabled = !combineJs.checked;
            minifyJs.closest('.optimize-switch-field').classList.toggle('is-disabled', !combineJs.checked);
        }

        updateStatus(settings);
    }

    function initOptimizeToggles() {
        var inputs = document.querySelectorAll('input.optimize-toggle[type="checkbox"]');

        inputs.forEach(function (input) {
            if (input.dataset.optimizeBound === '1') {
                return;
            }

            input.dataset.optimizeBound = '1';

            input.addEventListener('change', function () {
                var oldChecked = !input.checked;
                var formData = new FormData();

                formData.append('action', 'toggle');
                formData.append('name', input.name);
                formData.append('value', input.checked ? '1' : '0');

                input.disabled = true;

                postOptimize(formData)
                    .then(function (data) {
                        if (!data || data.status !== 'ok') {
                            input.checked = oldChecked;
                            alert(data && data.message ? data.message : 'Ошибка сохранения настройки');
                            return;
                        }

                        syncDependentToggles(data.settings);
                        updateStats(data.stats);
                    })
                    .catch(function () {
                        input.checked = oldChecked;
                        alert('Ошибка AJAX-запроса');
                    })
                    .finally(function () {
                        input.disabled = false;
                        syncDependentToggles();
                    });
            });
        });

        syncDependentToggles();
    }

    function saveTextSetting(textarea) {
        var formData = new FormData();
        formData.append('action', 'text');
        formData.append('name', textarea.name);
        formData.append('value', textarea.value);

        textarea.classList.add('is-saving');

        postOptimize(formData)
            .then(function (data) {
                if (!data || data.status !== 'ok') {
                    alert(data && data.message ? data.message : 'Ошибка сохранения настройки');
                    return;
                }

                updateStats(data.stats);
                textarea.classList.add('is-saved');
                setTimeout(function () {
                    textarea.classList.remove('is-saved');
                }, 800);
            })
            .catch(function () {
                alert('Ошибка AJAX-запроса');
            })
            .finally(function () {
                textarea.classList.remove('is-saving');
            });
    }

    function initAceEditor(textarea) {
        if (!window.ace || textarea.dataset.optimizeAce === '1') {
            return false;
        }

        textarea.dataset.optimizeAce = '1';
        textarea.style.display = 'none';

        var holder = document.createElement('div');
        holder.className = 'optimize-ace ace_editor';
        holder.textContent = textarea.value;
        textarea.parentNode.insertBefore(holder, textarea.nextSibling);

        var editor = window.ace.edit(holder);
        editor.session.setMode('ace/mode/css');
        editor.session.setUseWrapMode(true);
        editor.setShowPrintMargin(false);
        editor.setOptions({
            fontSize: '13px',
            minLines: 18,
            maxLines: 36
        });

        editor.on('blur', function () {
            textarea.value = editor.getValue();
            if (textarea.value !== textarea.dataset.optimizeValue) {
                textarea.dataset.optimizeValue = textarea.value;
                saveTextSetting(textarea);
            }
        });

        return true;
    }

    function initCodeMirrorEditor(textarea) {
        if (!window.CodeMirror || textarea.dataset.optimizeCodeMirror === '1') {
            return false;
        }

        textarea.dataset.optimizeCodeMirror = '1';

        var editor = window.CodeMirror.fromTextArea(textarea, {
            mode: 'css',
            lineNumbers: true,
            lineWrapping: true
        });

        editor.on('blur', function () {
            editor.save();
            if (textarea.value !== textarea.dataset.optimizeValue) {
                textarea.dataset.optimizeValue = textarea.value;
                saveTextSetting(textarea);
            }
        });

        return true;
    }

    function initTextEditors() {
        var textareas = document.querySelectorAll('textarea.optimize-setting-text');

        textareas.forEach(function (textarea) {
            if (textarea.dataset.optimizeBound === '1') {
                return;
            }

            textarea.dataset.optimizeBound = '1';
            textarea.dataset.optimizeValue = textarea.value;

            if (textarea.classList.contains('optimize-code-css')) {
                if (initAceEditor(textarea) || initCodeMirrorEditor(textarea)) {
                    return;
                }
            }

            textarea.addEventListener('blur', function () {
                if (textarea.value !== textarea.dataset.optimizeValue) {
                    textarea.dataset.optimizeValue = textarea.value;
                    saveTextSetting(textarea);
                }
            });
        });
    }

    function initOptimizeAdmin() {
        initOptimizeToggles();
        initTextEditors();
    }

    initOptimizeAdmin();
    refreshOptimizeStats();

    if (window.jQuery) {
        jQuery(document).ajaxComplete(initOptimizeAdmin);
    }
})();
