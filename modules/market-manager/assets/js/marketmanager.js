document.addEventListener("DOMContentLoaded",function(){
    var buttons=document.querySelectorAll(".we-warehouse-item");
    var slot=document.getElementById("we-market-slot");
    var selected=document.getElementById("we-market-selection");

    for(var i=0;i<buttons.length;i++){
        (function(button){
            button.addEventListener("click",function(){
                for(var j=0;j<buttons.length;j++){
                    buttons[j].classList.remove("is-selected");
                }
                button.classList.add("is-selected");
                if(slot) slot.value=button.getAttribute("data-slot")||"";
                if(selected){
                    selected.textContent=(button.getAttribute("data-name")||"Objeto")+
                        " · "+(button.getAttribute("data-serial")||"");
                }
            });
        })(buttons[i]);
    }
});