(function () {
    'use strict';
    document.querySelectorAll('[data-open-department]').forEach(link=>link.addEventListener('click',event=>{
        const evidence=document.getElementById(link.dataset.openDepartment);
        if(!evidence) return;
        event.preventDefault();
        evidence.open=true;
        evidence.scrollIntoView({behavior:'smooth',block:'start'});
        evidence.querySelector('summary').focus({preventScroll:true});
    }));
})();
