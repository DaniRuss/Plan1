const assert = require('node:assert/strict');
global.window = {};
require('../../public/js/number-format.js');
for (const [value, expected] of [
    ['55.0000', '55'], ['55.1234', '55.123'], ['55.5678', '55.568'],
    [100, '100'], [-55.5678, '-55.568'], [-0.0004, '0'],
    [9.9999, '10'], [1.2345, '1.235'], [null, ''],
]) assert.equal(window.formatNumber(value), expected);
console.log('Number formatter: 9 JavaScript cases passed');

const unchanged = {value: '55.123', dataset: {originalNumber: '55.123456789'}};
const edited = {value: '60.5', dataset: {originalNumber: '55.123456789'}};
const zero = {value: '0', dataset: {originalNumber: '0.000000000000'}};
const blank = {value: '', dataset: {originalNumber: ''}};
window.preserveUnchangedNumberInputs({elements: [unchanged, edited, zero, blank]});
assert.equal(unchanged.value, '55.123456789');
assert.equal(edited.value, '60.5');
assert.equal(zero.value, '0.000000000000');
assert.equal(blank.value, '');
console.log('Unchanged input precision: 4 cases passed');

const live = {value: '', dataset: {}};
window.setNumberInput(live, '55.567812345');
assert.equal(live.value, '55.568');
assert.equal(window.numberInputValue(live), '55.567812345');
live.value = '60.1234';
assert.equal(window.numberInputValue(live), '60.1234');
window.setNumberInput(live, window.numberInputValue(live));
assert.equal(live.value, '60.123');
assert.equal(window.numberInputValue(live), '60.1234');
console.log('Live calculation precision: 5 cases passed');
