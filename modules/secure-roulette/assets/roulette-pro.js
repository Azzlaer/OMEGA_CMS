document.addEventListener('DOMContentLoaded',function(){
    const root=document.querySelector('.srw[data-winning-rotation]');
    const wheel=document.getElementById('srwWheel');

    if(root && wheel){
        const rotation=Number(root.dataset.winningRotation||0);
        const winningSlot=Number(root.dataset.winningSlot||0);
        const duration=Math.max(1200,Number(root.dataset.spinDuration||5600));

        if(rotation>0 && winningSlot>0){
            // Estado inicial real: la rueda SIEMPRE empieza en 0°.
            wheel.style.transition='none';
            wheel.style.transform='rotate(0deg)';

            // Forzar layout garantiza que el navegador pinte el estado inicial
            // antes de aplicar la transición al ángulo ganador.
            void wheel.offsetWidth;

            root.classList.add('srw-animating');

            requestAnimationFrame(function(){
                requestAnimationFrame(function(){
                    wheel.style.transform='rotate('+rotation+'deg)';
                });
            });

            // Efecto mecánico del puntero mientras pasan los segmentos.
            const tickInterval=window.setInterval(function(){
                root.classList.add('srw-tick');
                window.setTimeout(function(){
                    root.classList.remove('srw-tick');
                },70);
            },145);

            window.setTimeout(function(){
                window.clearInterval(tickInterval);
                root.classList.remove('srw-tick','srw-animating');
                root.classList.add('srw-settled');

                const prize=root.querySelector('.srw-prize-pending');
                if(prize){
                    prize.setAttribute('aria-live','polite');
                }
            },duration+120);
        }else{
            root.classList.add('srw-settled');
        }
    }

    const form=document.getElementById('srwForm');
    if(form){
        form.addEventListener('submit',function(){
            const btn=document.getElementById('srwSpinButton');
            if(btn){
                btn.disabled=true;
                btn.textContent='🎰 Girando...';
            }
        });
    }
});