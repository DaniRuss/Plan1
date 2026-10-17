/* Shared display formatting. Numeric input values remain ungrouped. */
window.formatNumber = function (value) {
    if (value === null || value === undefined || value === '') return '';
    const number = Number(value);
    if (!Number.isFinite(number)) return '';
    const rounded = Math.sign(number) * Math.round((Math.abs(number) + Number.EPSILON) * 1000) / 1000;
    return Object.is(rounded, -0) ? '0' : rounded.toLocaleString('en-US', {useGrouping: false, maximumFractionDigits: 3});
};

// Preserve full precision when a rounded display field has not been changed.
window.preserveUnchangedNumberInputs = function (form) {
    for (const field of Array.from(form.elements || [])) {
        if (field.disabled || field.dataset?.originalNumber === undefined) continue;
        const original = field.dataset.originalNumber;
        if (field.value === window.formatNumber(original)) field.value = original;
    }
};
if (typeof document !== 'undefined') {
    document.addEventListener('submit', function (event) {
        window.preserveUnchangedNumberInputs(event.target);
    }, true);
}

window.numberInputValue = function (field) {
    const original = field.dataset?.originalNumber;
    return original !== undefined && field.value === window.formatNumber(original) ? original : field.value;
};
window.setNumberInput = function (field, value) {
    const original = value === null || value === undefined ? '' : String(value);
    field.dataset.originalNumber = original;
    field.value = window.formatNumber(original);
};
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function () {
        // jQuery calculations and serialization read full precision while inputs display rounded values.
        if (window.jQuery) {
            window.jQuery.valHooks.number = {
                get: window.numberInputValue,
                set: function (field, value) { window.setNumberInput(field, value); return value; }
            };
        }
    });
    document.addEventListener('blur', function (event) {
        const field = event.target;
        if (field.type === 'number' && field.value !== '' && Number.isFinite(Number(field.value))) {
            window.setNumberInput(field, window.numberInputValue(field));
        }
    }, true);
}
