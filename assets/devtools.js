/* DevTools plugin: filter the rows of a table by the text of an input field. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-devtools-filter]').forEach(function (input) {
        var table = document.getElementById(input.getAttribute('data-devtools-filter'));
        if (!table) {
            return;
        }
        input.addEventListener('input', function () {
            var needle = input.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = needle === '' || row.textContent.toLowerCase().indexOf(needle) !== -1 ? '' : 'none';
            });
        });
    });
});

/*
 * Preferences diff: filter by status (toggle buttons), owner and text; sort by clicking a column
 * header. A click sorts by that column alone, a click with Shift adds it as a further sort key, a
 * second click on a sorted column reverses its direction.
 */
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('adm_plg_devtools_preferences_table');
    if (!table) {
        return;
    }
    var tbody = table.querySelector('tbody');
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var statusButtons = document.querySelectorAll('[data-devtools-status]');
    var owner = document.getElementById('devtools_owner');
    var search = document.getElementById('devtools_preferences_search');
    var counter = document.getElementById('adm_plg_devtools_preferences_count');
    var statusOrder = { changed: 0, missing: 1, unknown: 2, 'default': 3 };
    var sortKeys = [{ key: 'name', direction: 1 }];

    function selectedStatuses() {
        var selected = {};
        statusButtons.forEach(function (button) {
            if (button.getAttribute('aria-pressed') === 'true') {
                selected[button.getAttribute('data-devtools-status')] = true;
            }
        });
        return selected;
    }

    function applyFilter() {
        var statuses = selectedStatuses();
        var ownerValue = owner.value;
        var needle = search.value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var show = statuses[row.dataset.status] === true
                && (ownerValue === '' || row.dataset.owner === ownerValue)
                && (needle === '' || row.textContent.toLowerCase().indexOf(needle) !== -1);
            row.style.display = show ? '' : 'none';
            if (show) {
                visible++;
            }
        });
        counter.textContent = visible + ' / ' + rows.length;
    }

    function compare(a, b) {
        for (var i = 0; i < sortKeys.length; i++) {
            var key = sortKeys[i].key;
            var result;
            if (key === 'status') {
                result = statusOrder[a.dataset.status] - statusOrder[b.dataset.status];
            } else {
                result = (a.dataset[key] || '').localeCompare(b.dataset[key] || '', undefined, { numeric: true, sensitivity: 'base' });
            }
            if (result !== 0) {
                return result * sortKeys[i].direction;
            }
        }
        return 0;
    }

    function applySort() {
        rows.sort(compare);
        rows.forEach(function (row) {
            tbody.appendChild(row);
        });
        table.querySelectorAll('th[data-devtools-sort]').forEach(function (header) {
            var icon = header.querySelector('i');
            var position = -1;
            sortKeys.forEach(function (sortKey, index) {
                if (sortKey.key === header.getAttribute('data-devtools-sort')) {
                    position = index;
                }
            });
            icon.className = position === -1 ? 'bi' : 'bi ' + (sortKeys[position].direction === 1 ? 'bi-sort-down-alt' : 'bi-sort-up');
            icon.textContent = position > 0 ? String(position + 1) : '';
        });
    }

    statusButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var pressed = button.getAttribute('aria-pressed') !== 'true';
            var cssClass = button.getAttribute('data-devtools-class');
            button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            button.classList.toggle('btn-' + cssClass, pressed);
            button.classList.toggle('btn-outline-' + cssClass, !pressed);
            applyFilter();
        });
    });
    owner.addEventListener('change', applyFilter);
    search.addEventListener('input', applyFilter);

    table.querySelectorAll('th[data-devtools-sort]').forEach(function (header) {
        header.addEventListener('click', function (event) {
            var key = header.getAttribute('data-devtools-sort');
            var existing = null;
            sortKeys.forEach(function (sortKey) {
                if (sortKey.key === key) {
                    existing = sortKey;
                }
            });
            if (event.shiftKey) {
                if (existing) {
                    existing.direction = -existing.direction;
                } else {
                    sortKeys.push({ key: key, direction: 1 });
                }
            } else if (existing && sortKeys.length === 1) {
                existing.direction = -existing.direction;
            } else {
                sortKeys = [{ key: key, direction: existing ? existing.direction : 1 }];
            }
            applySort();
        });
    });

    applySort();
    applyFilter();
});
