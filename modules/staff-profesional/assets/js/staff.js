(function(){
"use strict";

var root=document.getElementById("staff-pro");
if(!root) return;

var cards=Array.prototype.slice.call(root.querySelectorAll(".staff-card:not(.staff-empty)"));
var search=document.getElementById("staff-search");
var rank=document.getElementById("staff-rank-filter");
var dept=document.getElementById("staff-department-filter");
var empty=document.getElementById("staff-no-results");

function value(el){
    return el ? String(el.value||"").trim().toLowerCase() : "";
}

function filter(){
    var q=value(search);
    var r=value(rank);
    var d=value(dept);
    var visible=0;

    cards.forEach(function(card){
        var ok=
            (!q || String(card.getAttribute("data-search")||"").indexOf(q)!==-1) &&
            (!r || String(card.getAttribute("data-rank")||"")===r) &&
            (!d || String(card.getAttribute("data-department")||"")===d);

        card.hidden=!ok;
        if(ok) visible++;
    });

    if(empty) empty.hidden=visible>0;
}

if(search) search.addEventListener("input",filter);
if(rank) rank.addEventListener("change",filter);
if(dept) dept.addEventListener("change",filter);

var buttons=root.querySelectorAll(".staff-copy");
for(var i=0;i<buttons.length;i++){
    (function(btn){
        btn.addEventListener("click",function(){
            var value=btn.getAttribute("data-copy")||"";
            var old=btn.textContent;

            if(navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(value).then(function(){
                    btn.textContent="✅ Copiado";
                    window.setTimeout(function(){btn.textContent=old;},1400);
                }).catch(function(){
                    window.prompt("Copia el contacto:",value);
                });
            }else{
                window.prompt("Copia el contacto:",value);
            }
        });
    })(buttons[i]);
}
})();