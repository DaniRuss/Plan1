(function () {
    'use strict';
    const form=document.querySelector('.pc-builder');
    if(!form) return;
    const type=()=>form.elements.comparison_type.value;
    const navigate=params=>{ document.getElementById('pc-builder-status').textContent='Loading authorized selections…'; window.location.assign(form.action+'?'+params.toString()+'#comparison-filters'); };
    form.querySelectorAll('[data-builder-refresh]').forEach(control=>control.addEventListener('change',()=>{
        const params=new URLSearchParams(new FormData(form));
        params.set('configure','1'); params.delete('reference_entity'); params.delete('current_entity');
        if(control.dataset.builderRefresh==='type') {
            params.delete('current_period'); params.delete('reference_period');
            if(type()==='department') {
                for(const key of ['reporting_frequency','result_scope','search','movement','comparison_sort','performance_kpi','reference_department','department_scope','department_search','department_sort']) params.delete(key);
                if(params.get('reference_source')===params.get('current_source')) params.delete('reference_source');
            } else if(type()==='target') {
                let source=params.get('current_source');
                if(!source?.startsWith('plan:')) source=Array.from(form.querySelector('[name=current_source]').options).find(o=>o.value.startsWith('plan:'))?.value;
                if(source) {params.set('current_source',source);params.set('reference_source',source);}
            } else if(type()==='period') {
                const department=params.get('current_department');
                if(department) params.set('reference_department',department);else params.delete('reference_department');
                if([params.get('reference_source'),params.get('current_source')].some(s=>s?.startsWith('history:'))) {
                    params.set('reference_period','annual');params.set('current_period','annual');
                }
            } else if(['year','objective','kpi'].includes(type()) && params.get('reference_source')===params.get('current_source')) {
                params.delete('reference_source');
            }
        } else if(control.dataset.builderRefresh==='source') {
            const side=control.name.replace('_source','');
            if(control.value.startsWith('history:')) params.set(side+'_period','annual');
            if(type()==='target') {
                params.set('reference_source',control.value);params.set('current_source',control.value);
            } else if(type()==='period' && [params.get('reference_source'),params.get('current_source')].some(s=>s?.startsWith('history:'))) {
                params.set('reference_period','annual');params.set('current_period','annual');
            } else if(type()==='department' && params.get('reference_source')===params.get('current_source')) {
                params.delete(side==='reference'?'current_source':'reference_source');
            } else if(type()==='period' && params.get('reference_source')===params.get('current_source') && params.get('current_period')===params.get('reference_period')) {
                params.delete('reference_period');params.delete('current_period');
            }
        } else if(control.dataset.builderRefresh==='department' && type()==='period') {
            params.set('reference_department',control.value);params.set('current_department',control.value);
        }
        navigate(params);
    }));
    const referenceEntity=form.querySelector('[data-builder-match]');
    if(referenceEntity) referenceEntity.addEventListener('change',()=>{const current=form.querySelector('[name=current_entity]');if(current) current.value='';});
    const swap=document.getElementById('pc-swap');
    if(type()==='target') swap.hidden=true;
    swap.addEventListener('click',()=>{
        const params=new URLSearchParams(new FormData(form));
        for(const field of ['source','period','department','entity']) {
            const left=params.get('reference_'+field),right=params.get('current_'+field);
            if(right!==null) params.set('reference_'+field,right);else params.delete('reference_'+field);
            if(left!==null) params.set('current_'+field,left);else params.delete('current_'+field);
        }
        navigate(params);
    });
})();
