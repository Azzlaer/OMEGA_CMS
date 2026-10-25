(function(){
    'use strict';
    const editor=document.getElementById('guideHtmlEditor');
    if(!editor) return;
    document.querySelectorAll('[data-html-toolbar] button').forEach(function(btn){
        btn.addEventListener('click',function(){
            const start=editor.selectionStart;
            const end=editor.selectionEnd;
            const selected=editor.value.substring(start,end);
            const template=btn.dataset.template;
            let insert='';
            if(template){
                insert=template;
            }else{
                insert=(btn.dataset.open||'')+selected+(btn.dataset.close||'');
            }
            editor.setRangeText(insert,start,end,'end');
            editor.focus();
        });
    });
})();
