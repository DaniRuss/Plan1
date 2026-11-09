const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const code = fs.readFileSync('public/js/comparison-workbench.js', 'utf8');

function setup(type = 'kpi') {
    const fields = {};
    function control(name, value, refresh) {
        const listeners = {};
        const node = {name, value, hidden:false, dataset:refresh?{builderRefresh:refresh}:{}, options:[{value:'plan:2026'},{value:'plan:2027'},{value:'history:2018'}], addEventListener(event, listener){listeners[event]=listener;}, change(){listeners.change?.();}, click(){listeners.click?.();}};
        fields[name] = node;
        return node;
    }
    control('comparison_type',type,'type'); control('comparison_view','master');
    for (const side of ['reference','current']) {
        control(side+'_source',side==='reference'?'history:2018':'plan:2026','source');
        control(side+'_period','annual'); control(side+'_department','12','department');
        control(side+'_entity',side==='reference'?'h:50':'o:317');
    }
    control('reporting_frequency','all','frequency');
    const swap=control('swap','');
    const status={textContent:''}; let navigated=null;
    const form={action:'http://localhost/performance-comparison',elements:fields,
        querySelector(selector){const match=selector.match(/name=([^\]]+)/);return match?fields[match[1]]:selector==='[data-builder-match]'?fields.reference_entity:null;},
        querySelectorAll(selector){return selector==='[data-builder-refresh]'?Object.values(fields).filter(f=>f.dataset.builderRefresh):Object.values(fields).filter(f=>f.name.endsWith('_period'));}};
    class FormData {constructor(){this.values=Object.entries(fields).filter(([key])=>key!=='swap').map(([key,node])=>[key,node.value]);}[Symbol.iterator](){return this.values[Symbol.iterator]();}}
    vm.runInNewContext(code,{URLSearchParams,FormData,window:{location:{assign(url){navigated=new URL(url);}}},document:{querySelector(){return form;},getElementById(id){return id==='pc-swap'?swap:status;}}});
    return {fields,swap,params:()=>navigated.searchParams};
}

test('switching Master to a period comparison clears obsolete entities and periods',()=>{
    const h=setup();h.fields.comparison_type.value='period';h.fields.comparison_type.change();
    assert.equal(h.params().get('reference_source'),'history:2018');
    assert.equal(h.params().get('comparison_view'),'master');
    assert.equal(h.params().get('configure'),'1');
    assert.equal(h.params().has('reference_entity'),false);
    assert.equal(h.params().get('current_period'),'annual');
});
test('historical source refresh requests only annual evidence',()=>{
    const h=setup();h.fields.current_period.value='july';h.fields.current_source.value='history:2018';h.fields.current_source.change();
    assert.equal(h.params().get('current_period'),'annual');
    assert.equal(h.params().has('current_entity'),false);
});
test('opening KPI comparison from a same-year preset defaults back to a distinct reference source',()=>{
    const h=setup('period');h.fields.reference_source.value='plan:2026';h.fields.comparison_type.value='kpi';h.fields.comparison_type.change();
    assert.equal(h.params().has('reference_source'),false);
    assert.equal(h.params().get('current_source'),'plan:2026');
});
test('period department changes use one consistent department scope',()=>{
    const h=setup('period');h.fields.current_department.value='32';h.fields.current_department.change();
    assert.equal(h.params().get('reference_department'),'32');
    assert.equal(h.params().get('current_department'),'32');
});
test('selecting a different Reference KPI requests automatic counterpart matching',()=>{
    const h=setup();h.fields.reference_entity.change();
    assert.equal(h.fields.current_entity.value,'');
});
test('Swap preserves the complete two-sided pair, not just its year labels',()=>{
    const h=setup();h.fields.reference_department.value='32';h.swap.click();
    assert.equal(h.params().get('reference_entity'),'o:317');
    assert.equal(h.params().get('current_entity'),'h:50');
    assert.equal(h.params().get('current_source'),'history:2018');
    assert.equal(h.params().get('current_department'),'32');
    assert.equal(h.params().has('configure'),false);
});
test('Actual versus Target has no meaningless Swap action',()=>{
    assert.equal(setup('target').swap.hidden,true);
});
test('opening annual department comparison preserves an imported annual source',()=>{
    const h=setup('year');h.fields.current_source.value='history:2018';h.fields.comparison_type.value='department';h.fields.comparison_type.change();
    assert.equal(h.params().get('current_source'),'history:2018');
    assert.equal(h.params().has('department_scope'),false);
    assert.equal(h.params().has('reporting_frequency'),false);
    assert.equal(h.params().get('current_department'),'12');
});

test('switching a department pair to periods keeps one consistent department',()=>{
    const h=setup('department');
    h.fields.current_department.value='32';h.fields.reference_department.value='12';
    h.fields.comparison_type.value='period';h.fields.comparison_type.change();
    assert.equal(h.params().get('current_department'),'32');
    assert.equal(h.params().get('reference_department'),'32');
});

test('a period source refresh never overwrites the independently selected other year',()=>{
    const h=setup('period');h.fields.reference_source.value='plan:2026';h.fields.current_source.value='plan:2027';
    h.fields.reference_period.value='september';h.fields.current_period.value='october';h.fields.reference_source.change();
    assert.equal(h.params().get('current_source'),'plan:2027');
    assert.equal(h.params().get('reference_source'),'plan:2026');
    assert.equal(h.params().get('reference_period'),'september');
});

test('department years refresh independently and keep the single department',()=>{
    const h=setup('department');h.fields.reference_source.value='history:2018';h.fields.current_source.value='plan:2027';h.fields.current_source.change();
    assert.equal(h.params().get('reference_source'),'history:2018');
    assert.equal(h.params().get('current_source'),'plan:2027');
    assert.equal(h.params().get('current_department'),'12');
    assert.equal(h.swap.hidden,false);
});

test('an imported period source selects annual on both sides without changing years',()=>{
    const h=setup('period');h.fields.current_source.value='plan:2027';h.fields.current_period.value='october';h.fields.reference_period.value='september';h.fields.reference_source.change();
    assert.equal(h.params().get('current_source'),'plan:2027');
    assert.equal(h.params().get('current_period'),'annual');
    assert.equal(h.params().get('reference_period'),'annual');
});
