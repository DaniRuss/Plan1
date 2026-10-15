/* Shared display formatting. Numeric input values remain ungrouped. */
window.formatNumber = function (value) {
    if (value === null || value === undefined || value === '') return '';
    const number = Number(value);
    if (!Number.isFinite(number)) return '';
    const rounded = Math.sign(number) * Math.round((Math.abs(number) + Number.EPSILON) * 1000) / 1000;
    return Object.is(rounded, -0) ? '0' : rounded.toLocaleString('en-US', {useGrouping: false, maximumFractionDigits: 3});
};
