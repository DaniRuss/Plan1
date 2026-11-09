const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function form(mode = 'manual', initial = {}) {
    const fields = {};
    for (const name of ['target_distribution','q1','q2','q3','q4','july','august','september','october','november','december','january','february','march','april','may','june']) {
        const id = name === 'target_distribution' ? name : name + '_target';
        fields[id] = {value: '', dataset: {}, listeners: {}, classList: {toggle() {}}, parentElement: {querySelector: () => ({textContent: ''})}, addEventListener(event, fn) {this.listeners[event] = fn;}};
    }
    fields.target_distribution.value = mode;
    fields.q1_target.value = '127.625';
    for (const [name, value] of Object.entries(initial)) fields[name + '_target'].value = String(value);
    const monthly = Object.entries(fields).filter(([id]) => !id.startsWith('q') && id !== 'target_distribution').map(([,field]) => field);
    const document = {readyState: 'complete', getElementById: id => fields[id], querySelectorAll: () => monthly, addEventListener() {}};
    const context = {document}; context.window = context;
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('public/js/number-format.js','utf8'), context);
    vm.runInContext(fs.readFileSync('public/js/objective-targets.js','utf8'), context);
    return {fields, mode(value) {fields.target_distribution.value = value; fields.target_distribution.listeners.change();}, edit(month, value) {fields[month + '_target'].value = value; fields[month + '_target'].listeners.input();}, read(name) {return context.numberInputValue(fields[name + '_target']);}};
}
test('mode changes recalculate immediately, preserve manual values and keep blank distinct from zero', () => {
    const f = form();
    f.mode('sum'); assert.equal(f.read('q1'), '');
    f.edit('july','0'); assert.equal(f.read('q1'), '0');
    f.edit('august','5'); assert.equal(f.read('q1'), '5');
    f.mode('checkpoint'); assert.equal(f.read('q1'), '');
    f.edit('september','12'); assert.equal(f.read('q1'), '12');
    f.mode('manual'); assert.equal(f.read('q1'), '127.625');
    assert.equal(f.fields.q1_target.readOnly, false);
});
test('sum reads full precision from rounded monthly inputs', () => {
    const f = form('sum');
    f.fields.july_target.dataset.originalNumber = '42.54166667';
    f.edit('july','42.542');
    f.edit('august','42.54166667');
    assert.ok(Math.abs(Number(f.read('q1')) - 85.08333334) < 0.00000001);
    assert.equal(f.fields.q1_target.readOnly,true);
});

test('saved cumulative objective switches to sum immediately without changing any monthly input', () => {
    const months = ['july','august','september','october','november','december','january','february','march','april','may','june'];
    const values = Object.fromEntries(months.map(month => [month,43]));
    const f = form('checkpoint', values);
    for (const q of ['q1','q2','q3','q4']) assert.equal(f.read(q),'43');
    const before = months.map(month => f.fields[month + '_target'].value);
    f.mode('sum');
    for (const q of ['q1','q2','q3','q4']) assert.equal(f.read(q),'129');
    assert.deepEqual(months.map(month => f.fields[month + '_target'].value), before);
    f.mode('checkpoint');
    for (const q of ['q1','q2','q3','q4']) assert.equal(f.read(q),'43');
    assert.deepEqual(months.map(month => f.fields[month + '_target'].value), before);
});
