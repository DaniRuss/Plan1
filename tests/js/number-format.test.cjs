const assert = require('node:assert/strict');
global.window = {};
require('../../public/js/number-format.js');
for (const [value, expected] of [
    ['55.0000', '55'], ['55.1234', '55.123'], ['55.5678', '55.568'],
    [100, '100'], [-55.5678, '-55.568'], [-0.0004, '0'],
    [9.9999, '10'], [1.2345, '1.235'], [null, ''],
]) assert.equal(window.formatNumber(value), expected);
console.log('Number formatter: 9 JavaScript cases passed');
