document.addEventListener('DOMContentLoaded', function () {
    const root=document.getElementById('enterpriseAdmin');
    if(!root) return;

    const storageKey='omega_admin_sidebar_groups_v3';
    const compactKey='omega_admin_sidebar_compact_v3';
    const openBtn=document.getElementById('adminMenuButton');
    const closeBtn=document.getElementById('adminSidebarClose');
    const overlay=document.getElementById('adminMobileOverlay');
    const compactBtn=document.getElementById('adminSidebarCompact');
    const search=document.getElementById('adminMenuSearch');
    const noResults=document.getElementById('adminMenuNoResults');
    const groups=[...document.querySelectorAll('[data-admin-group]')];

    let savedGroups={};
    try{savedGroups=JSON.parse(localStorage.getItem(storageKey)||'{}');}catch(e){savedGroups={};}

    function saveGroups(){
        const state={};
        groups.forEach(group=>{
            state[group.dataset.adminGroup]=group.classList.contains('is-open');
        });
        try{localStorage.setItem(storageKey,JSON.stringify(state));}catch(e){}
    }

    function setGroup(group,open,save=true){
        if(!group) return;
        group.classList.toggle('is-open',!!open);
        const toggle=group.querySelector('[data-group-toggle]');
        if(toggle) toggle.setAttribute('aria-expanded',open?'true':'false');
        if(save) saveGroups();
    }

    groups.forEach(group=>{
        const key=group.dataset.adminGroup;
        if(group.classList.contains('group-current')){
            setGroup(group,true,false);
        }else if(Object.prototype.hasOwnProperty.call(savedGroups,key)){
            setGroup(group,!!savedGroups[key],false);
        }
    });

    document.querySelectorAll('[data-group-toggle]').forEach(button=>{
        button.addEventListener('click',function(){
            const group=this.closest('[data-admin-group]');
            setGroup(group,!group.classList.contains('is-open'));
        });
    });

    function openSidebar(){
        root.classList.add('sidebar-open');
        if(openBtn) openBtn.setAttribute('aria-expanded','true');
    }

    function closeSidebar(){
        root.classList.remove('sidebar-open');
        if(openBtn) openBtn.setAttribute('aria-expanded','false');
    }

    if(openBtn) openBtn.addEventListener('click',openSidebar);
    if(closeBtn) closeBtn.addEventListener('click',closeSidebar);
    if(overlay) overlay.addEventListener('click',closeSidebar);

    if(localStorage.getItem(compactKey)==='1'){
        root.classList.add('sidebar-compact');
    }

    function syncCompactButton(){
        if(!compactBtn) return;
        const compact=root.classList.contains('sidebar-compact');
        compactBtn.textContent=compact?'⇥':'⇤';
        compactBtn.title=compact?'Expandir menú':'Contraer menú';
        compactBtn.setAttribute('aria-label',compact?'Expandir menú':'Contraer menú');
    }

    syncCompactButton();

    if(compactBtn){
        compactBtn.addEventListener('click',function(){
            root.classList.toggle('sidebar-compact');
            try{localStorage.setItem(compactKey,root.classList.contains('sidebar-compact')?'1':'0');}catch(e){}
            syncCompactButton();
        });
    }

    function restoreGroupsAfterSearch(){
        groups.forEach(group=>{
            group.hidden=false;
            const key=group.dataset.adminGroup;
            if(group.classList.contains('group-current')){
                setGroup(group,true,false);
            }else if(Object.prototype.hasOwnProperty.call(savedGroups,key)){
                setGroup(group,!!savedGroups[key],false);
            }
            group.querySelectorAll('[data-menu-item]').forEach(item=>item.hidden=false);
        });
    }

    function filterMenu(){
        const q=(search?.value||'').trim().toLowerCase();
        if(!q){
            restoreGroupsAfterSearch();
            if(noResults) noResults.hidden=true;
            return;
        }

        let total=0;
        groups.forEach(group=>{
            let visible=0;
            group.querySelectorAll('[data-menu-item]').forEach(item=>{
                const hay=(item.dataset.search||item.textContent||'').toLowerCase();
                const match=hay.includes(q);
                item.hidden=!match;
                if(match){visible++;total++;}
            });
            group.hidden=visible===0;
            if(visible>0) setGroup(group,true,false);
        });

        if(noResults) noResults.hidden=total!==0;
    }

    if(search){
        search.addEventListener('input',filterMenu);
    }

    document.addEventListener('keydown',function(e){
        const tag=document.activeElement?.tagName||'';
        if(e.key==='/' && !['INPUT','TEXTAREA','SELECT'].includes(tag)){
            e.preventDefault();
            search?.focus();
        }
        if(e.key==='Escape'){
            closeSidebar();
            if(document.activeElement===search){
                search.value='';
                filterMenu();
                search.blur();
            }
        }
    });

    document.querySelectorAll('[data-menu-item]').forEach(link=>{
        link.addEventListener('click',function(){
            const group=this.closest('[data-admin-group]');
            if(group) setGroup(group,true);
            closeSidebar();
        });
    });

    const active=document.querySelector('[data-menu-item].active');
    if(active){
        active.closest('[data-admin-group]')?.classList.add('is-open');
        active.scrollIntoView({block:'nearest'});
    }
});