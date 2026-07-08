(function () {
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

    function syncDependentToggles(settings) {
        var combineCss = document.querySelector('input.optimize-toggle[name="combine_css"]');
        var minifyCss = document.querySelector('input.optimize-toggle[name="minify_css"]');
        var combineJs = document.querySelector('input.optimize-toggle[name="combine_js"]');
        var minifyJs = document.querySelector('input.optimize-toggle[name="minify_js"]');

        if (settings) {
            if (typeof settings.combine_css !== 'undefined' && combineCss) combineCss.checked = !!settings.combine_css;
            if (typeof settings.minify_css !== 'undefined' && minifyCss) minifyCss.checked = !!settings.minify_css;
            if (typeof settings.combine_js !== 'undefined' && combineJs) combineJs.checked = !!settings.combine_js;
            if (typeof settings.minify_js !== 'undefined' && minifyJs) minifyJs.checked = !!settings.minify_js;
        }

        if (combineCss && minifyCss) {
            minifyCss.disabled = !combineCss.checked;
            minifyCss.closest('.optimize-switch-field').classList.toggle('is-disabled', !combineCss.checked);
        }

        if (combineJs && minifyJs) {
            minifyJs.disabled = !combineJs.checked;
            minifyJs.closest('.optimize-switch-field').classList.toggle('is-disabled', !combineJs.checked);
        }
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

    function initTextEditors() {
        var textareas = document.querySelectorAll('textarea.optimize-setting-text');

        textareas.forEach(function (textarea) {
            if (textarea.dataset.optimizeBound === '1') {
                return;
            }

            textarea.dataset.optimizeBound = '1';
            textarea.dataset.optimizeValue = textarea.value;

            textarea.addEventListener('blur', function () {
                if (textarea.value !== textarea.dataset.optimizeValue) {
                    textarea.dataset.optimizeValue = textarea.value;
                    saveTextSetting(textarea);
                }
            });

            if (window.CodeMirror && textarea.classList.contains('optimize-code-css')) {
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
            }
        });
    }

    function initOptimizeAdmin() {
        initOptimizeToggles();
        initTextEditors();
    }

    initOptimizeAdmin();

    if (window.jQuery) {
        jQuery(document).ajaxComplete(initOptimizeAdmin);
    }
})();
