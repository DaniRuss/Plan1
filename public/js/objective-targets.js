/* Target scheduling for both objective create and edit forms. */
(function () {
    function init() {
        const mode = document.getElementById('target_distribution');
        if (!mode) return;
        const groups = [
            ['q1', ['july', 'august', 'september']], ['q2', ['october', 'november', 'december']],
            ['q3', ['january', 'february', 'march']], ['q4', ['april', 'may', 'june']]
        ];
        const read = field => window.numberInputValue ? window.numberInputValue(field) : field.value;
        const write = (field, value) => {
            if (window.setNumberInput) window.setNumberInput(field, value);
            else field.value = value === null ? '' : String(value);
        };
        const manual = {};
        let previous = mode.value;
        groups.forEach(([quarter]) => { manual[quarter] = read(document.getElementById(quarter + '_target')); });
        function calculate() {
            groups.forEach(([quarter, months]) => {
                const field = document.getElementById(quarter + '_target');
                const note = field.parentElement.querySelector('.quarter-calculation-note');
                if (previous === 'manual' && mode.value !== 'manual') manual[quarter] = read(field);
                field.readOnly = mode.value !== 'manual';
                field.classList.toggle('bg-light', field.readOnly);
                if (mode.value === 'manual') {
                    if (previous !== 'manual') write(field, manual[quarter]);
                    note.textContent = 'Enter the quarter target manually';
                } else {
                    const values = months.map(month => read(document.getElementById(month + '_target')));
                    const present = values.filter(value => value !== '' && value !== null);
                    const value = mode.value === 'sum'
                        ? (present.length ? present.reduce((sum, value) => sum + Number(value), 0) : null)
                        : (values[2] === '' ? null : values[2]);
                    write(field, value);
                    note.textContent = mode.value === 'sum' ? 'Sum of entered months; blank if none are planned' : 'Copied from ' + months[2];
                }
            });
            previous = mode.value;
        }
        mode.addEventListener('change', calculate);
        document.querySelectorAll('.target-month').forEach(field => field.addEventListener('input', calculate));
        calculate();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
