(function ($) {

    $(document).ready(function () {

        let pageIsAlive = true;

        window.addEventListener('beforeunload', () => {
            pageIsAlive = false;
        });

        const ajaxUrl = window.ajaxurl || (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url);

        function applyDefaultDateSort() {
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
        }

        const $filters = $('#ldl-filters');

        function reloadTable() {
            const url = $filters.attr('action');
            const data = $filters.serialize();
            $.get(url, data, function (html) {
                if (!pageIsAlive) return;
                const $newTable = $(html).find('#ldl-logs-table');
                if ($newTable.length) {
                    $('#ldl-logs-table').replaceWith($newTable);
                    applyDefaultDateSort();
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

        const $settingsForm = $('#ldl-settings-form');

        if ($settingsForm.length) {

            function saveSettings(callback) {
                const data = $settingsForm.serializeArray();
                data.push({ name: 'action', value: 'likoton_debug_logs_save_settings' });
                $.post(ajaxUrl, data, function () {
                    if (!pageIsAlive) return;
                    if (typeof callback === 'function') callback();
                });
            }

            $settingsForm.on('change', '#likoton_debug_logs_capability', function () {
                saveSettings(showToast);
            });

            $settingsForm.on('change', 'select[name="likoton_debug_logs_retention"]:not([disabled])', function () {
                saveSettings(showToast);
            });

            $settingsForm.on('change', '#likoton_debug_logs_dark_mode', function () {
                saveSettings(function () {
                    location.reload();
                });
            });

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

        applyDefaultDateSort();

        function showToast() {
            const $toast = $('#ldl-toast');
            $toast.addClass('show').show();
            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => $toast.hide(), 250);
            }, 1800);
        }

        function initClearSearch() {
            const $wrapper = $('.ldl-search-wrapper');
            if (!$wrapper.length) return;
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
            applyDefaultDateSort();
        });

        const $wrapper = $('.ldl-logs-wrapper');

        if ($wrapper.length) {

            const $btnTop = $('<div class="ldl-go-top">↑</div>');
            $wrapper.append($btnTop);

            $wrapper.on('scroll', function () {
                if (this.scrollTop > 200) {
                    $btnTop.css('opacity', 1);
                } else {
                    $btnTop.css('opacity', 0);
                }
            });

            $btnTop.on('click', function () {
                $wrapper.animate({ scrollTop: 0 }, 300);
            });

            let page = 1;
            let loading = false;
            let done = false;

            const $loader = $('<div class="ldl-scroll-loader">Loading…</div>').css({
                padding: '15px',
                textAlign: 'center',
                color: '#666',
                opacity: 0,
                transition: 'opacity .25s ease'
            });

            $wrapper.append($loader);

            function loadMore() {
                if (loading || done) return;
                const $tableBody = $('#ldl-logs-table tbody');
                if (!$tableBody.length) return;
                loading = true;
                $loader.css('opacity', 1);
                const params = new URLSearchParams(window.location.search);
                $.get(ajaxUrl, {
                    action: 'likoton_debug_logs_load_more_logs',
                    page_num: page + 1,
                    s: params.get('s') || '',
                    level: params.get('level') || '',
                    source: params.get('source') || '',
                    last: params.get('last') || 50,
                    likoton_debug_logs_nonce: likotonDebugLogsData.nonce
                }, function (response) {
                    if (response.success) {
                        if (response.data.done) {
                            done = true;
                            $loader.text('✔ All logs loaded');
                            return;
                        }
                        page++;
                        $tableBody.append(response.data.html);
                    }
                }).always(function () {
                    loading = false;
                    $loader.css('opacity', 0);
                });
            }

            $wrapper.on('scroll', function () {
                const scrollBottom = this.scrollHeight - this.scrollTop - this.clientHeight;
                if (scrollBottom < 150) {
                    loadMore();
                }
            });
        }

        if (!$('#ldl-logs-table').length) return;

        function autoRefreshLogs() {
            let data = $filters.serializeArray();

            let last = data.find(x => x.name === 'last');
            if (!last || last.value === '' || last.value === 'all') {
                data = data.filter(x => x.name !== 'last');
                data.push({ name: 'last', value: 50 });
            }

            const url = $filters.attr('action');

            $.get(url, data, function (html) {
                if (!pageIsAlive) return;

                const $newTable = $(html).find('#ldl-logs-table');
                if ($newTable.length) {
                    $('#ldl-logs-table').replaceWith($newTable);
                    applyDefaultDateSort();
                }
            });
        }

        function autoRefreshIfAtBottom() {
            const wrapper = document.querySelector('.ldl-logs-wrapper');
            if (!wrapper) return;
            const scrollBottom = wrapper.scrollHeight - wrapper.scrollTop - wrapper.clientHeight;
            if (scrollBottom < 50) {
                autoRefreshLogs();
            }
        }

        setInterval(autoRefreshIfAtBottom, 8000);

    });

})(jQuery);
