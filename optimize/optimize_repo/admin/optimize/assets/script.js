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

                formData.append('name', input.name);
                formData.append('value', input.checked ? '1' : '0');

                input.disabled = true;

                fetch('/admin/optimize/ajax.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || data.status !== 'ok') {
                            input.checked = oldChecked;
                            alert(data && data.message ? data.message : 'Ошибка сохранения настройки');
                            return;
                        }

                        updateStats(data.stats);
                    })
                    .catch(function () {
                        input.checked = oldChecked;
                        alert('Ошибка AJAX-запроса');
                    })
                    .finally(function () {
                        input.disabled = false;
                    });
            });
        });
    }

    initOptimizeToggles();

    if (window.jQuery) {
        jQuery(document).ajaxComplete(initOptimizeToggles);
    }
})();
