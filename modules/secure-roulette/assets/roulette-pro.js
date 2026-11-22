document.addEventListener('DOMContentLoaded',function(){
    const root=document.querySelector('.srw[data-winning-rotation]');
    if(root){
        const rotation=Number(root.dataset.winningRotation||0);
        const wheel=document.getElementById('srwWheel');
        if(wheel && rotation>0){
            wheel.style.transform='rotate(0deg)';
            requestAnimationFrame(function(){
                requestAnimationFrame(function(){
                    wheel.style.transform='rotate('+rotation+'deg)';
                });
            });
        }
    }

    const form=document.getElementById('srwForm');
    if(form){
        form.addEventListener('submit',function(){
            const btn=document.getElementById('srwSpinButton');
            if(btn){
                btn.disabled=true;
                btn.textContent='Procesando premio...';
            }
        });
    }
});