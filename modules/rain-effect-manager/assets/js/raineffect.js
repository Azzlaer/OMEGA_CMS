(function(){
    "use strict";

    var script=document.getElementById("omega-rain-effect-script");
    if(!script) return;

    function bool(name,fallback){
        var v=script.getAttribute(name);
        if(v===null) return fallback;
        return v==="1"||v==="true";
    }
    function num(name,fallback){
        var v=parseFloat(script.getAttribute(name)||"");
        return isFinite(v)?v:fallback;
    }
    function str(name,fallback){
        var v=script.getAttribute(name);
        return v===null?fallback:v;
    }

    var cfg={
        enabled:bool("data-enabled",true),
        effectType:str("data-effect-type","rain"),
        placementMode:str("data-placement-mode","viewport"),
        targetSelector:str("data-target-selector","body"),
        zIndex:num("data-z-index",9990),
        density:num("data-density",110),
        speedMin:num("data-speed-min",650),
        speedMax:num("data-speed-max",1200),
        dropLengthMin:num("data-drop-length-min",14),
        dropLengthMax:num("data-drop-length-max",30),
        dropWidth:num("data-drop-width",1),
        wind:num("data-wind",-8),
        opacity:num("data-opacity",.65),
        color:str("data-color","#a8d8ff"),
        splashEnabled:bool("data-splash-enabled",true),
        splashDensity:num("data-splash-density",35),
        splashColor:str("data-splash-color","#d9f1ff"),
        lightningEnabled:bool("data-lightning-enabled",false),
        lightningIntervalMin:num("data-lightning-interval-min",12),
        lightningIntervalMax:num("data-lightning-interval-max",30),
        lightningColor:str("data-lightning-color","#ffffff"),
        audioEnabled:bool("data-audio-enabled",false),
        audioUrl:str("data-audio-url",""),
        audioVolume:num("data-audio-volume",.25),
        audioLoop:bool("data-audio-loop",true),
        pauseWhenHidden:bool("data-pause-when-hidden",true),
        respectReducedMotion:bool("data-respect-reduced-motion",true),
        disableMobile:bool("data-disable-mobile",false),
        mobileDensity:num("data-mobile-density",45),
        maxFps:num("data-max-fps",60),
        debugMode:bool("data-debug-mode",false)
    };

    if(!cfg.enabled) return;

    var isMobile=window.matchMedia&&window.matchMedia("(max-width:700px)").matches;
    if(isMobile&&cfg.disableMobile) return;

    if(cfg.respectReducedMotion&&window.matchMedia&&
        window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

    var target=document.body;

    if(cfg.placementMode==="element"){
        try{
            target=document.querySelector(cfg.targetSelector||"body")||document.body;
        }catch(error){
            target=document.body;
        }

        if(window.getComputedStyle(target).position==="static"){
            target.style.position="relative";
        }
    }

    var root=document.createElement("div");
    root.id="omega-rain-effect-root";
    root.setAttribute("data-respect-reduced-motion",cfg.respectReducedMotion?"1":"0");
    root.style.zIndex=String(cfg.zIndex||9990);

    if(cfg.placementMode==="element"){
        root.className="omega-rain-element";
    }

    var canvas=document.createElement("canvas");
    canvas.className="omega-rain-canvas";
    root.appendChild(canvas);

    var lightning=document.createElement("div");
    lightning.className="omega-rain-lightning";
    lightning.style.background=cfg.lightningColor||"#ffffff";
    root.appendChild(lightning);

    target.appendChild(root);

    var ctx=canvas.getContext("2d",{alpha:true});
    if(!ctx){
        root.remove();
        return;
    }

    var drops=[];
    var splashes=[];
    var width=0;
    var height=0;
    var dpr=1;
    var running=true;
    var lastTime=0;
    var frameInterval=1000/Math.max(15,Math.min(120,cfg.maxFps||60));

    function random(min,max){
        return min+Math.random()*(max-min);
    }

    function density(){
        var base=isMobile?(cfg.mobileDensity||45):(cfg.density||110);

        if(cfg.effectType==="drizzle") base*=0.65;
        if(cfg.effectType==="storm") base*=1.45;

        return Math.max(5,Math.floor(base));
    }

    function createDrop(initial){
        var minSpeed=Math.min(cfg.speedMin||650,cfg.speedMax||1200);
        var maxSpeed=Math.max(cfg.speedMin||650,cfg.speedMax||1200);
        var speedMs=random(minSpeed,maxSpeed);

        if(cfg.effectType==="drizzle") speedMs*=1.4;
        if(cfg.effectType==="storm") speedMs*=0.72;

        var minLength=Math.min(cfg.dropLengthMin||14,cfg.dropLengthMax||30);
        var maxLength=Math.max(cfg.dropLengthMin||14,cfg.dropLengthMax||30);
        var length=random(minLength,maxLength);

        return {
            x:random(-width*.1,width*1.1),
            y:initial?random(-height,height):random(-height*.35,-10),
            length:length,
            width:Math.max(.5,cfg.dropWidth||1),
            speed:(height+length)/(speedMs/1000),
            wind:cfg.wind||0,
            alpha:random(
                Math.max(.08,(cfg.opacity||.65)*.55),
                Math.max(.08,cfg.opacity||.65)
            )
        };
    }

    function buildDrops(){
        drops=[];
        var total=density();

        for(var i=0;i<total;i++){
            drops.push(createDrop(true));
        }

        splashes=[];
    }

    function resize(){
        var rect=root.getBoundingClientRect();

        width=Math.max(1,Math.floor(rect.width||window.innerWidth));
        height=Math.max(1,Math.floor(rect.height||window.innerHeight));
        dpr=Math.min(window.devicePixelRatio||1,2);

        canvas.width=Math.floor(width*dpr);
        canvas.height=Math.floor(height*dpr);
        canvas.style.width=width+"px";
        canvas.style.height=height+"px";

        ctx.setTransform(dpr,0,0,dpr,0,0);
        buildDrops();
    }

    function spawnSplash(x,y){
        if(!cfg.splashEnabled) return;
        if(Math.random()*100>(cfg.splashDensity||35)) return;

        var count=2+Math.floor(Math.random()*3);

        for(var i=0;i<count;i++){
            splashes.push({
                x:x,
                y:y,
                vx:random(-30,30),
                vy:random(-55,-15),
                life:random(.12,.35),
                age:0,
                size:random(.7,1.8)
            });
        }
    }

    function update(dt){
        for(var i=0;i<drops.length;i++){
            var d=drops[i];

            d.y+=d.speed*dt;
            d.x+=d.wind*dt;

            if(d.y>height){
                spawnSplash(d.x,height-2);
                drops[i]=createDrop(false);
                continue;
            }

            if(d.x>width+100) d.x=-50;
            if(d.x<-100) d.x=width+50;
        }

        for(var j=splashes.length-1;j>=0;j--){
            var s=splashes[j];

            s.age+=dt;
            s.x+=s.vx*dt;
            s.y+=s.vy*dt;
            s.vy+=180*dt;

            if(s.age>=s.life){
                splashes.splice(j,1);
            }
        }
    }

    function colorWithAlpha(hex,alpha){
        var value=String(hex||"#ffffff").replace("#","");
        var r=parseInt(value.substring(0,2),16)||255;
        var g=parseInt(value.substring(2,4),16)||255;
        var b=parseInt(value.substring(4,6),16)||255;

        return "rgba("+r+","+g+","+b+","+
            Math.max(0,Math.min(1,alpha))+")";
    }

    function draw(){
        ctx.clearRect(0,0,width,height);
        ctx.lineCap="round";

        for(var i=0;i<drops.length;i++){
            var d=drops[i];
            var slant=(cfg.wind||0)*.08;

            ctx.beginPath();
            ctx.strokeStyle=colorWithAlpha(cfg.color||"#a8d8ff",d.alpha);
            ctx.lineWidth=d.width;
            ctx.moveTo(d.x,d.y);
            ctx.lineTo(d.x+slant,d.y+d.length);
            ctx.stroke();
        }

        if(cfg.splashEnabled){
            for(var j=0;j<splashes.length;j++){
                var s=splashes[j];
                var alpha=Math.max(0,1-(s.age/s.life));

                ctx.beginPath();
                ctx.fillStyle=colorWithAlpha(
                    cfg.splashColor||"#d9f1ff",
                    alpha*.65
                );
                ctx.arc(s.x,s.y,s.size,0,Math.PI*2);
                ctx.fill();
            }
        }
    }

    function loop(time){
        if(!running){
            window.requestAnimationFrame(loop);
            return;
        }

        if(!lastTime) lastTime=time;

        var elapsed=time-lastTime;

        if(elapsed>=frameInterval){
            var dt=Math.min(.05,elapsed/1000);
            lastTime=time-(elapsed%frameInterval);
            update(dt);
            draw();
        }

        window.requestAnimationFrame(loop);
    }

    function flashLightning(){
        lightning.style.opacity="0.48";

        window.setTimeout(function(){
            lightning.style.opacity="0";
        },80);

        window.setTimeout(function(){
            lightning.style.opacity="0.22";

            window.setTimeout(function(){
                lightning.style.opacity="0";
            },65);
        },150);
    }

    function scheduleLightning(){
        if(!cfg.lightningEnabled) return;

        var min=Math.max(2,cfg.lightningIntervalMin||12);
        var max=Math.max(min,cfg.lightningIntervalMax||30);
        var delay=random(min,max)*1000;

        window.setTimeout(function(){
            if(running){
                flashLightning();
            }

            scheduleLightning();
        },delay);
    }

    function setupAudio(){
        if(!cfg.audioEnabled||!cfg.audioUrl) return;

        var audio=new Audio(cfg.audioUrl);
        audio.loop=cfg.audioLoop!==false;
        audio.volume=Math.max(0,Math.min(1,cfg.audioVolume||.25));
        audio.preload="none";

        var button=document.createElement("button");
        button.type="button";
        button.className="omega-rain-audio-control";
        button.setAttribute("aria-label","Activar o silenciar sonido de lluvia");
        button.textContent="🔇";
        document.body.appendChild(button);

        var started=false;

        function play(){
            var promise=audio.play();

            if(promise&&typeof promise.then==="function"){
                promise.then(function(){
                    started=true;
                    button.textContent="🔊";
                }).catch(function(){});
            }
        }

        function firstInteraction(){
            if(!started) play();
            document.removeEventListener("click",firstInteraction,true);
            document.removeEventListener("keydown",firstInteraction,true);
        }

        document.addEventListener("click",firstInteraction,true);
        document.addEventListener("keydown",firstInteraction,true);

        button.addEventListener("click",function(event){
            event.preventDefault();
            event.stopPropagation();

            if(audio.paused){
                play();
            }else{
                audio.pause();
                button.textContent="🔇";
            }
        });
    }

    if(cfg.pauseWhenHidden){
        document.addEventListener("visibilitychange",function(){
            running=!document.hidden;
            if(running){
                lastTime=performance.now();
            }
        });
    }

    var resizeTimer=null;

    window.addEventListener("resize",function(){
        if(resizeTimer) window.clearTimeout(resizeTimer);

        resizeTimer=window.setTimeout(function(){
            resize();
        },120);
    });

    resize();
    window.requestAnimationFrame(loop);
    scheduleLightning();
    setupAudio();

    if(cfg.debugMode&&window.console){
        console.info("Omega Rain Effect Manager activo",cfg);
    }
})();