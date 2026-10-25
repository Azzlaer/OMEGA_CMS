document.addEventListener('DOMContentLoaded',()=>{
 const btn=document.querySelector('.nav-toggle');
 const nav=document.querySelector('.nav-links');
 if(btn&&nav) btn.addEventListener('click',()=>nav.classList.toggle('open'));
});
