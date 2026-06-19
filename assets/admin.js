(function ($) {

    $(document).ready(function () {

        let pageIsAlive = true;

        window.addEventListener('beforeunload', () => {
            pageIsAlive = false;
        });

        /* 1. AJAX table reload on filter change */

        const $filters = $('#ldl-filters');

        function reloadTable() {
            const url = $filters.attr('action');
            const data = $filters.serialize();

            $.get(url, data, function (html) {
                if (!pageIsAlive) return;

                const $newTable = $(html).find('#ldl-logs-table');
                if ($newTable.length) {
                    $('#ldl-logs-table').replaceWith($newTable);
                }
            });
        }

        if ($filters.length) {
            let timer = null;

            $filters.on('input', 'input[type="search"]', function () {
                clearTimeout(timer);
                timer = setTimeout(reloadTable, 250);
            });

            $filters.on('change', 'select', function () {
                reloadTable();
            });
        }

        /* 2. Auto-save settings (AJAX) */

        const $settingsForm = $('#ldl-settings-form');

        if ($settingsForm.length) {

            const ajaxUrl = window.ajaxurl || (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url);

            function saveSettings(callback) {
                const data = $settingsForm.serializeArray();
                data.push({ name: 'action', value: 'ldl_save_settings' });

                $.post(ajaxUrl, data, function () {
                    if (!pageIsAlive) return;
                    if (typeof callback === 'function') callback();
                });
            }

            // capability + log retention
            $settingsForm.on('change', '#ldl_capability', function () {
                saveSettings(showToast);
            });

            $settingsForm.on('change', 'select[name="ldl_log_retention"]:not([disabled])', function () {
                saveSettings(showToast);
            });

            // dark mode → autosave + reload
            $settingsForm.on('change', '#ldl_dark_mode', function () {
                saveSettings(function () {
                    location.reload();
                });
            });

            /* Segmented Control — auto save */
            $(document).on('change', '.ldl-segment input', function () {
                saveSettings(showToast);
            });

            $(document).on('click', '.ldl-select-all', function () {
                $('.ldl-segment input').prop('checked', true).trigger('change');
            });

            $(document).on('click', '.ldl-deselect-all', function () {
                $('.ldl-segment input').prop('checked', false).trigger('change');
            });
        }

        /* 3. Table sorting */

        $(document).on('click', '#ldl-logs-table th', function () {
            const $table = $('#ldl-logs-table');
            const $tbody = $table.find('tbody');
            const index = $(this).index();
            const rows = $tbody.find('tr').get();

            const asc = !$(this).hasClass('sorted-asc');
            $('#ldl-logs-table th').removeClass('sorted-asc sorted-desc sorted-column');
            $(this).addClass(asc ? 'sorted-asc' : 'sorted-desc').addClass('sorted-column');

            rows.sort(function (a, b) {
                const A = $(a).children('td').eq(index).text().toUpperCase();
                const B = $(b).children('td').eq(index).text().toUpperCase();
                return asc ? A.localeCompare(B) : B.localeCompare(A);
            });

            $.each(rows, function (i, row) {
                $tbody.append(row);
            });
        });

        /* 4. Default sort by Date column */

        const $dateHeader = $('#ldl-logs-table th.column-date');
        if ($dateHeader.length) {
            $dateHeader.addClass('sorted-desc sorted-column');

            const $tbody = $('#ldl-logs-table tbody');
            const rows = $tbody.find('tr').get();

            rows.sort(function (a, b) {
                const A = $(a).children('td').eq($dateHeader.index()).text().toUpperCase();
                const B = $(b).children('td').eq($dateHeader.index()).text().toUpperCase();
                return B.localeCompare(A);
            });

            $.each(rows, function (i, row) {
                $tbody.append(row);
            });
        }

        /* 5. Notice for autosave */
        function showToast() {
            const $toast = $('#ldl-toast');
            $toast.addClass('show').show();

            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => $toast.hide(), 250);
            }, 1800);
        }

        /* 6. Clear search field (X button) */

        function initClearSearch() {
            const $wrapper = $('.ldl-search-wrapper');
            const $input = $wrapper.find('input[type="search"]');
            const $clear = $wrapper.find('.ldl-clear-search');

            $input.off('input');
            $clear.off('click');

            function toggleClear() {
                $clear.toggle($input.val().length > 0);
            }

            $input.on('input', toggleClear);

            $clear.on('click', function () {
                $input.val('');
                toggleClear();
                $input.trigger('input').trigger('change');
            });

            toggleClear();
        }

        initClearSearch();

        $(document).ajaxComplete(function () {
            initClearSearch();
        });

    });

})(jQuery);
