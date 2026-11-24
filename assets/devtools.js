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
