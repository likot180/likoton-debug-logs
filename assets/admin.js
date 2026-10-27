(function ($) {

    $(document).ready(function () {

        let pageIsAlive = true;

        window.addEventListener('beforeunload', () => {
            pageIsAlive = false;
            // A file download (e.g. the CSV export) or a cancelled navigation fires beforeunload
            // but keeps this page alive, so the flag must not stay false forever.
            setTimeout(() => {
                pageIsAlive = true;
            }, 0);
        });

        const ajaxUrl = window.ajaxurl || (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url);

        // Index 0 is the (non-sortable) checkbox column.
        var SORT_COLUMNS = ['', 'id', 'level', 'source', 'message', 'created_at'];

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

        // Bumped on every manual reload; responses from older requests are ignored so a late
        // response can never overwrite the table (and reset the checkboxes) after the user acted on it.
        let tableGeneration = 0;

        // While a reload is in flight the old rows are about to be replaced, so block clicks on them
        // (otherwise a checkbox toggles and is then reset by the arriving table).
        function setTableLoading(loading) {
            $('#ldl-toolbar, #ldl-logs-table').toggleClass('is-loading', loading);
        }

        function reloadTable() {
            const generation = ++tableGeneration;
            const url = $filters.attr('action');
            const data = $filters.serialize();

            setTableLoading(true);

            $.get(url, data, function (html) {
                if (!pageIsAlive || generation !== tableGeneration) return;
                const $newTable = $(html).find('#ldl-logs-table');
                if ($newTable.length) {
                    $('#ldl-logs-table').replaceWith($newTable);
                    applySortIndicator();
                }
                scrollPage = 1;
                scrollDone = false;
            }).always(function () {
                if (generation === tableGeneration) {
                    setTableLoading(false);
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
            if ($(this).hasClass('column-cb')) {
                return;
            }
            var colIndex = $(this).index();
            var asc = !$(this).hasClass('sorted-asc');
            $('#ldl-orderby').val(SORT_COLUMNS[colIndex] || 'created_at');
            $('#ldl-order').val(asc ? 'asc' : 'desc');
            applySortIndicator();
            reloadTable();
        });

        applySortIndicator();

        // ---- Row selection and bulk actions (delete / download CSV / copy) ----

        const bulkStrings = (window.likotonDebugLogsData && likotonDebugLogsData.i18n) || {};

        function formatString(template) {
            const args = Array.prototype.slice.call(arguments, 1);
            let next = 0;
            return String(template).replace(/%(\d+\$)?d/g, function (match, position) {
                return position ? args[parseInt(position, 10) - 1] : args[next++];
            });
        }

        function selectedBoxes() {
            return $('#ldl-logs-table tbody .ldl-row-cb:checked');
        }

        function selectedIds() {
            return selectedBoxes().map(function () { return this.value; }).get();
        }

        function updateSelectionUI() {
            if (!$('#ldl-toolbar').length) {
                return;
            }

            const $boxes = $('#ldl-logs-table tbody .ldl-row-cb');
            const total = $boxes.length;
            const selected = $boxes.filter(':checked').length;

            $('#ldl-select-all').prop({
                checked: total > 0 && selected === total,
                indeterminate: selected > 0 && selected < total,
                disabled: total === 0
            });
            $('.ldl-bulk-action').prop('disabled', selected === 0);
            $('#ldl-toolbar-info').text(selected > 0
                ? formatString(bulkStrings.selectedCount || '%1$d of %2$d selected', selected, total)
                : formatString(bulkStrings.logsCount || 'Logs: %d', total));
        }

        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }

            return new Promise(function (resolve, reject) {
                const area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                let ok = false;
                try {
                    ok = document.execCommand('copy');
                } catch (e) {}
                document.body.removeChild(area);
                return ok ? resolve() : reject();
            });
        }

        // After a mouse "deselect" (all rows or a single row) drop the focus ring; keyboard users (detail === 0) keep their focus.
        $(document).on('click', '#ldl-select-all, .ldl-row-cb', function (e) {
            if (!this.checked && e.detail > 0) {
                this.blur();
            }
        });

        $(document).on('change', '#ldl-select-all', function () {
            $('#ldl-logs-table tbody .ldl-row-cb').prop('checked', this.checked);
            updateSelectionUI();
        });

        $(document).on('change', '.ldl-row-cb', updateSelectionUI);

        // Default (selected rows) texts are rendered server-side in the modal markup.
        const modalDefaults = {
            title: $('#ldl-modal-title').text(),
            message: $('#ldl-modal-message').text()
        };

        // Styled replacement for window.confirm(); resolves true on "Delete", false otherwise.
        // options: { title, message, meta } — each falls back to the default / empty.
        function confirmDelete(options) {
            options = options || {};

            return new Promise(function (resolve) {
                const $modal = $('#ldl-confirm-modal');
                const opener = document.activeElement;
                const $focusable = $modal.find('button');

                $('#ldl-modal-title').text(options.title || modalDefaults.title);
                $('#ldl-modal-message').text(options.message || modalDefaults.message);
                $('#ldl-modal-meta').text(options.meta || '');
                $modal.prop('hidden', false);
                $('#ldl-modal-cancel').trigger('focus');

                const close = function (result) {
                    $modal.prop('hidden', true).off('.ldlModal');
                    $(document).off('keydown.ldlModal');
                    if (opener && typeof opener.focus === 'function') {
                        opener.focus();
                    }
                    resolve(result);
                };

                $modal.on('click.ldlModal', '[data-ldl-modal-close]', function () {
                    close(false);
                });

                $modal.on('click.ldlModal', '#ldl-modal-confirm', function () {
                    close(true);
                });

                $(document).on('keydown.ldlModal', function (e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        close(false);
                        return;
                    }

                    if (e.key === 'Tab') {
                        const first = $focusable.first()[0];
                        const last = $focusable.last()[0];
                        if (e.shiftKey && document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        } else if (!e.shiftKey && document.activeElement === last) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                });
            });
        }

        function deleteSelected(ids) {
            const failed = function () {
                window.alert(bulkStrings.deleteFailed || 'Could not delete the selected logs.');
                updateSelectionUI();
            };

            $('.ldl-bulk-action').prop('disabled', true);

            $.post(ajaxUrl, {
                action: 'likoton_debug_logs_delete_selected',
                likoton_debug_logs_nonce: likotonDebugLogsData.deleteNonce,
                ids: ids.join(',')
            }).done(function (response) {
                if (!response || !response.success) {
                    failed();
                    return;
                }
                selectedBoxes().closest('tr').remove();
                updateSelectionUI();
                reloadTable();
            }).fail(failed);
        }

        $(document).on('click', '#ldl-bulk-delete', function () {
            const ids = selectedIds();
            if (!ids.length) {
                return;
            }

            const total = $('#ldl-logs-table tbody .ldl-row-cb').length;

            confirmDelete({
                meta: formatString(bulkStrings.selectedCount || '%1$d of %2$d selected', ids.length, total)
            }).then(function (confirmed) {
                if (confirmed) {
                    deleteSelected(ids);
                }
            });
        });

        // "Delete all logs" is a regular form post; ask first, then submit it natively.
        $(document).on('submit', '.ldl-delete-form', function (e) {
            e.preventDefault();
            const form = this;

            confirmDelete({
                title: bulkStrings.deleteAllTitle,
                message: bulkStrings.deleteAllConfirm
            }).then(function (confirmed) {
                if (confirmed) {
                    form.submit();
                }
            });
        });

        $(document).on('click', '#ldl-bulk-download', function () {
            const ids = selectedIds();
            if (!ids.length) {
                return;
            }

            const $form = $('<form method="post" style="display:none;"></form>').attr('action', likotonDebugLogsData.exportUrl);
            $('<input type="hidden" name="action">').val('likoton_debug_logs_export_logs').appendTo($form);
            $('<input type="hidden" name="likoton_debug_logs_export_logs_nonce">').val(likotonDebugLogsData.exportNonce).appendTo($form);
            $('<input type="hidden" name="ids">').val(ids.join(',')).appendTo($form);
            $('body').append($form);
            $form[0].submit();
            setTimeout(function () { $form.remove(); }, 1000);
        });

        $(document).on('click', '#ldl-bulk-copy', function () {
            const text = selectedBoxes().closest('tr').map(function () {
                return $(this).children('td').not('.column-cb').map(function () {
                    return $(this).text().replace(/\s+/g, ' ').trim();
                }).get().join('\t');
            }).get().join('\n');

            if (!text) {
                return;
            }

            copyToClipboard(text).then(function () {
                showToast(bulkStrings.copied || 'Copied to clipboard');
            }).catch(function () {
                window.alert(bulkStrings.copyFailed || 'Could not copy to clipboard.');
            });
        });

        function showToast(message) {
            const $toast = $('#ldl-toast');
            if (typeof message === 'string') {
                $toast.text('✔ ' + message);
            }
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
            updateSelectionUI();
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
                const generation = tableGeneration;
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
                    // The table was reloaded meanwhile: this page belongs to the old filters/order.
                    if (generation !== tableGeneration) return;
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
            const generation = tableGeneration;

            $.get(url, data, function (html) {
                if (!pageIsAlive || generation !== tableGeneration) return;
                // Rows were picked while the request was in flight: keep the user's table.
                if (selectedBoxes().length) return;

                const $newTable = $(html).find('#ldl-logs-table');
                if (!$newTable.length) return;

                // Nothing new: leave the DOM alone so clicks aren't lost to a pointless re-render.
                // (innerHTML ignores the checkboxes' checked state, which is a property.)
                if ($newTable.find('tbody').html() === $('#ldl-logs-table tbody').html()) return;

                $('#ldl-logs-table').replaceWith($newTable);
                applySortIndicator();
            });
        }

        function autoRefreshIfAtBottom() {
            const wrapper = document.querySelector('.ldl-logs-wrapper');
            if (!wrapper) return;
            // Don't swap the table out from under the user while rows are selected.
            if (selectedBoxes().length) return;
            const scrollBottom = wrapper.scrollHeight - wrapper.scrollTop - wrapper.clientHeight;
            if (scrollBottom < 50) {
                autoRefreshLogs();
            }
        }

        setInterval(autoRefreshIfAtBottom, 8000);

    });

})(jQuery);
