(function ($) {

    $(document).ready(function () {

        let pageIsAlive = true;

        window.addEventListener('beforeunload', () => {
            pageIsAlive = false;
        });

        const ajaxUrl = window.ajaxurl || (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url);

        var SORT_COLUMNS = ['id', 'level', 'source', 'message', 'created_at'];

        // Sorting is done server-side (ORDER BY) and the current orderby/order is
        // remembered per-user on the server (like the other filters), so the hidden
        // fields below always already reflect the correct state on page load — no
        // client-side storage or "fix up after the fact" reload is needed here.
        function applySortIndicator() {
            var orderby = $('#ldl-orderby').val() || 'created_at';
            var order = $('#ldl-order').val() || 'desc';
            var colIndex = SORT_COLUMNS.indexOf(orderby);
            if (colIndex === -1) {
                colIndex = SORT_COLUMNS.length - 1;
            }
            var asc = order === 'asc';
            var $headers = $('#ldl-logs-table th');
            $headers.removeClass('sorted-asc sorted-desc sorted-column');
            $headers.eq(colIndex).addClass(asc ? 'sorted-asc' : 'sorted-desc').addClass('sorted-column');
        }

        const $filters = $('#ldl-filters');

        let scrollPage = 1;
        let scrollLoading = false;
        let scrollDone = false;

        function reloadTable() {
            const url = $filters.attr('action');
            const data = $filters.serialize();
            $.get(url, data, function (html) {
                if (!pageIsAlive) return;
                const $newTable = $(html).find('#ldl-logs-table');
                if ($newTable.length) {
                    $('#ldl-logs-table').replaceWith($newTable);
                    applySortIndicator();
                }
                scrollPage = 1;
                scrollDone = false;
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

            const $dateRange = $('#ldl-date-range');
            const $dateFrom = $('#ldl-date-from');
            const $dateTo = $('#ldl-date-to');

            function toggleDateRange() {
                $dateRange.toggleClass('is-visible', $filters.find('select[name="last"]').val() === 'range');
            }

            function syncDateConstraints() {
                if ($dateFrom.val()) {
                    $dateTo.attr('data-min', $dateFrom.val());
                }
                if ($dateTo.val()) {
                    $dateFrom.attr('data-max', $dateTo.val());
                }
            }

            toggleDateRange();
            syncDateConstraints();

            $filters.on('change', 'select[name="last"]', toggleDateRange);

            $filters.on('change', '.ldl-date-input', function () {
                syncDateConstraints();
                reloadTable();
            });

            initDatePicker();
        }

        function initDatePicker() {
            const locale = (window.likotonDebugLogsData && likotonDebugLogsData.locale) || 'en-US';
            const i18n = (window.likotonDebugLogsData && likotonDebugLogsData.i18n) || {};
            const labelToday = i18n.today || 'Today';
            const labelClear = i18n.clear || 'Clear';

            const $panel = $(
                '<div class="ldl-datepicker">' +
                    '<div class="ldl-datepicker-header">' +
                        '<button type="button" class="ldl-datepicker-nav ldl-datepicker-prev">&lsaquo;</button>' +
                        '<span class="ldl-datepicker-title"></span>' +
                        '<button type="button" class="ldl-datepicker-nav ldl-datepicker-next">&rsaquo;</button>' +
                    '</div>' +
                    '<div class="ldl-datepicker-weekdays"></div>' +
                    '<div class="ldl-datepicker-days"></div>' +
                    '<div class="ldl-datepicker-footer">' +
                        '<button type="button" class="ldl-datepicker-clear"></button>' +
                        '<button type="button" class="ldl-datepicker-today"></button>' +
                    '</div>' +
                '</div>'
            );

            $panel.find('.ldl-datepicker-clear').text(labelClear);
            $panel.find('.ldl-datepicker-today').text(labelToday);

            let $activeInput = null;
            let viewYear;
            let viewMonth;

            function pad(n) {
                return (n < 10 ? '0' : '') + n;
            }

            function toISO(y, m, d) {
                return y + '-' + pad(m + 1) + '-' + pad(d);
            }

            function parseISO(str) {
                const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(str || '');
                if (!parts) {
                    return null;
                }
                return { y: parseInt(parts[1], 10), m: parseInt(parts[2], 10) - 1, d: parseInt(parts[3], 10) };
            }

            function weekdayNames() {
                const fmt = new Intl.DateTimeFormat(locale, { weekday: 'short', timeZone: 'UTC' });
                const names = [];
                const base = Date.UTC(2023, 0, 2); // A Monday.
                for (let i = 0; i < 7; i++) {
                    names.push(fmt.format(new Date(base + i * 86400000)));
                }
                return names;
            }

            function monthTitle(y, m) {
                const fmt = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
                return fmt.format(new Date(y, m, 1));
            }

            function render() {
                $panel.find('.ldl-datepicker-title').text(monthTitle(viewYear, viewMonth));

                const $wd = $panel.find('.ldl-datepicker-weekdays').empty();
                weekdayNames().forEach(function (n) {
                    $('<span></span>').text(n).appendTo($wd);
                });

                const $days = $panel.find('.ldl-datepicker-days').empty();

                const firstOfMonth = new Date(viewYear, viewMonth, 1);
                const startOffset = (firstOfMonth.getDay() + 6) % 7; // Monday = 0.
                const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

                const minAttr = $activeInput.attr('data-min') || '';
                const maxAttr = $activeInput.attr('data-max') || '';
                const selectedISO = $activeInput.val();
                const now = new Date();
                const todayISO = toISO(now.getFullYear(), now.getMonth(), now.getDate());

                for (let i = 0; i < startOffset; i++) {
                    $('<span class="ldl-datepicker-day is-empty"></span>').appendTo($days);
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const iso = toISO(viewYear, viewMonth, d);
                    const disabled = (minAttr && iso < minAttr) || (maxAttr && iso > maxAttr);
                    const $btn = $('<button type="button" class="ldl-datepicker-day"></button>')
                        .text(d)
                        .attr('data-date', iso)
                        .prop('disabled', disabled);

                    if (iso === todayISO) {
                        $btn.addClass('is-today');
                    }
                    if (iso === selectedISO) {
                        $btn.addClass('is-selected');
                    }

                    $btn.appendTo($days);
                }
            }

            function open($input) {
                if ($activeInput) {
                    $activeInput.removeClass('is-active');
                }
                $activeInput = $input;
                const parsed = parseISO($input.val()) || (function () {
                    const now = new Date();
                    return { y: now.getFullYear(), m: now.getMonth(), d: now.getDate() };
                }());
                viewYear = parsed.y;
                viewMonth = parsed.m;
                render();

                const $field = $input.closest('.ldl-date-field');
                $field.append($panel.detach());
                $panel.addClass('is-open');
                $input.addClass('is-active');
            }

            function close() {
                $panel.removeClass('is-open');
                if ($activeInput) {
                    $activeInput.removeClass('is-active');
                }
                $activeInput = null;
            }

            $(document).on('mousedown', '.ldl-date-input', function (e) {
                e.preventDefault();
            });

            $(document).on('click', '.ldl-date-trigger, .ldl-date-input', function (e) {
                e.preventDefault();
                if (document.activeElement && document.activeElement !== document.body) {
                    document.activeElement.blur();
                }
                const $field = $(this).closest('.ldl-date-field');
                const $input = $field.find('.ldl-date-input');
                if ($activeInput && $activeInput[0] === $input[0] && $panel.hasClass('is-open')) {
                    close();
                } else {
                    open($input);
                }
            });

            $(document).on('click', function (e) {
                if ($panel.hasClass('is-open') && !$(e.target).closest('.ldl-datepicker, .ldl-date-field').length) {
                    close();
                }
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $panel.hasClass('is-open')) {
                    close();
                }
            });

            $panel.on('click', '.ldl-datepicker-prev', function () {
                viewMonth--;
                if (viewMonth < 0) {
                    viewMonth = 11;
                    viewYear--;
                }
                render();
            });

            $panel.on('click', '.ldl-datepicker-next', function () {
                viewMonth++;
                if (viewMonth > 11) {
                    viewMonth = 0;
                    viewYear++;
                }
                render();
            });

            $panel.on('click', '.ldl-datepicker-day:not(:disabled):not(.is-empty)', function () {
                const iso = $(this).attr('data-date');
                $activeInput.val(iso).trigger('change');
                close();
            });

            $panel.on('click', '.ldl-datepicker-today', function () {
                const now = new Date();
                const iso = toISO(now.getFullYear(), now.getMonth(), now.getDate());
                const minAttr = $activeInput.attr('data-min') || '';
                const maxAttr = $activeInput.attr('data-max') || '';
                if ((minAttr && iso < minAttr) || (maxAttr && iso > maxAttr)) {
                    return;
                }
                $activeInput.val(iso).trigger('change');
                close();
            });

            $panel.on('click', '.ldl-datepicker-clear', function () {
                const isFrom = $activeInput.attr('id') === 'ldl-date-from';
                const $from = $('#ldl-date-from');
                const $to = $('#ldl-date-to');

                const now = new Date();
                const today = toISO(now.getFullYear(), now.getMonth(), now.getDate());
                const yestDate = new Date(now);
                yestDate.setDate(now.getDate() - 1);
                const yesterday = toISO(yestDate.getFullYear(), yestDate.getMonth(), yestDate.getDate());

                if (isFrom) {
                    $from.val(yesterday);
                    if ($to.val() && $to.val() < yesterday) {
                        $to.val(today);
                    }
                } else {
                    $to.val(today);
                    if ($from.val() && $from.val() > today) {
                        $from.val(yesterday);
                    }
                }

                $activeInput.trigger('change');
                close();
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

            $settingsForm.on('change', 'select[name="likoton_debug_logs_dedup_interval"]', function () {
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
            var colIndex = $(this).index();
            var asc = !$(this).hasClass('sorted-asc');
            $('#ldl-orderby').val(SORT_COLUMNS[colIndex] || 'created_at');
            $('#ldl-order').val(asc ? 'asc' : 'desc');
            applySortIndicator();
            reloadTable();
        });

        applySortIndicator();

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
            applySortIndicator();
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

            const $loader = $('<div class="ldl-scroll-loader">Loading…</div>').css({
                padding: '15px',
                textAlign: 'center',
                color: '#666',
                opacity: 0,
                transition: 'opacity .25s ease'
            });

            $wrapper.append($loader);

            function filterValue(name) {
                const found = $filters.serializeArray().find(function (item) {
                    return item.name === name;
                });
                return found ? found.value : '';
            }

            function loadMore() {
                if (scrollLoading || scrollDone) return;
                const $tableBody = $('#ldl-logs-table tbody');
                if (!$tableBody.length) return;
                scrollLoading = true;
                $loader.css('opacity', 1);
                $.get(ajaxUrl, {
                    action: 'likoton_debug_logs_load_more_logs',
                    page_num: scrollPage + 1,
                    s: filterValue('s'),
                    level: filterValue('level'),
                    source: filterValue('source'),
                    last: filterValue('last') || 50,
                    orderby: filterValue('orderby'),
                    order: filterValue('order'),
                    likoton_debug_logs_nonce: likotonDebugLogsData.nonce
                }, function (response) {
                    if (response.success) {
                        if (response.data.done) {
                            scrollDone = true;
                            $loader.text('✔ All logs loaded');
                            return;
                        }
                        scrollPage++;
                        $tableBody.append(response.data.html);
                    }
                }).always(function () {
                    scrollLoading = false;
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

            data.push({ name: 'auto_refresh', value: '1' });

            const url = $filters.attr('action');

            $.get(url, data, function (html) {
                if (!pageIsAlive) return;

                const $newTable = $(html).find('#ldl-logs-table');
                if ($newTable.length) {
                    $('#ldl-logs-table').replaceWith($newTable);
                    applySortIndicator();
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
