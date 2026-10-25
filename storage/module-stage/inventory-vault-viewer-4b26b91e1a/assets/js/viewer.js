(()=>{const tip=document.getElementById('iv-tooltip');if(!tip)return;document.querySelectorAll('.iv-item,.iv-equip-item,.iv-equip-slot').forEach(i=>{i.addEventListener('mouseenter',()=>{tip.innerHTML=i.dataset.tip;tip.hidden=false});i.addEventListener('mousemove',e=>{tip.style.left=(e.clientX+16)+'px';tip.style.top=(e.clientY+16)+'px'});i.addEventListener('mouseleave',()=>tip.hidden=true)});})();
document.querySelectorAll('.iv-item img,.iv-equip-item img').forEach(img=>{
 img.addEventListener('error',()=>{
  const holder=img.parentElement;
  const missing=document.createElement('span');
  missing.className='iv-missing';
  missing.textContent='?';
  missing.title='Imagen no encontrada: '+(img.dataset.image||img.src);
  img.replaceWith(missing);
 });
});

const statsButton=document.getElementById('iv-toggle-stats');
const statsPanel=document.getElementById('iv-stats');
if(statsButton&&statsPanel){
 statsButton.addEventListener('click',()=>{
  const hidden=statsPanel.hidden;
  statsPanel.hidden=!hidden;
  statsButton.textContent=hidden?'📊 Ocultar estadísticas':'📊 Mostrar estadísticas';
 });
}
