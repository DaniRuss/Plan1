(function () {
    'use strict';
    const table = document.getElementById('pc-table');
    if (!table) return;
    const rows = Array.from(table.querySelectorAll('.pc-data-row'));
    const size = document.getElementById('pc-page-size');
    const previous = document.getElementById('pc-prev');
    const next = document.getElementById('pc-next');
    const caption = document.getElementById('pc-page-caption');
    let page = 0;
    function render() {
        const count = Number(size.value) || rows.length || 1;
        const pages = Math.max(1, Math.ceil(rows.length / count));
        page = Math.max(0, Math.min(page, pages - 1));
        rows.forEach((row, index) => { row.hidden = index < page * count || index >= (page + 1) * count; });
        const rowLabel = table.dataset.rowLabel || 'activity rows';
        caption.textContent = rows.length ? `${page * count + 1}–${Math.min((page + 1) * count, rows.length)} of ${rows.length} ${rowLabel}` : 'No results in this view';
        previous.disabled = page === 0;
        next.disabled = page >= pages - 1;
    }
    size.addEventListener('change', () => { page = 0; render(); });
    previous.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    render();
    const download = document.getElementById('pc-download');
    download.addEventListener('click', () => {
        const data = JSON.parse(document.getElementById('pc-export-data').textContent);
        if (!data.length) return;
        // Spreadsheet formula injection must not turn text from a KPI or source into executable content.
        const cell = value => {
            let text = value === null || value === undefined ? '' : String(value);
            if (typeof value === 'string' && /^[\s]*[=+\-@]/.test(text)) text = "'" + text;
            return '"' + text.replace(/"/g, '""') + '"';
        };
        const headers = Object.keys(data[0]);
        const csv = '\uFEFF' + [headers.map(cell).join(','), ...data.map(row => headers.map(key => cell(row[key])).join(','))].join('\r\n');
        const url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8;'}));
        const anchor = document.createElement('a');
        anchor.href = url; anchor.download = table.dataset.exportFilename || 'IPRPMS-annual-performance-comparison.csv'; anchor.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
})();
