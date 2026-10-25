(function(){
    "use strict";

    var script=document.getElementById("omega-title-marquee-script");
    if(!script) return;

    function bool(name, fallback){
        var v=script.getAttribute(name);
        if(v===null) return fallback;
        return v==="1" || v==="true";
    }

    function num(name, fallback){
        var v=parseInt(script.getAttribute(name)||"",10);
        return isFinite(v)?v:fallback;
    }

    function str(name, fallback){
        var v=script.getAttribute(name);
        return v===null?fallback:v;
    }

    var cfg={
        enabled:bool("data-enabled",true),
        useOriginalTitle:bool("data-use-original-title",true),
        customText:str("data-custom-text",""),
        prefix:str("data-prefix",""),
        suffix:str("data-suffix",""),
        separator:str("data-separator","✦"),
        speed:num("data-speed",180),
        direction:str("data-direction","left"),
        mode:str("data-mode","characters"),
        paddingSpaces:num("data-padding-spaces",3),
        pauseOnBlur:bool("data-pause-on-blur",true),
        restoreOnBlur:bool("data-restore-on-blur",false),
        restoreOnHover:bool("data-restore-on-hover",false),
        startDelay:num("data-start-delay",700),
        stopAfterCycles:num("data-stop-after-cycles",0),
        preserveModuleTitle:bool("data-preserve-module-title",true),
        trimWhitespace:bool("data-trim-whitespace",true),
        debugMode:bool("data-debug-mode",false)
    };

    if(!cfg.enabled) return;

    var originalTitle=document.title||"";
    var currentBaseTitle="";
    var timer=null;
    var running=false;
    var cycles=0;
    var direction=cfg.direction==="right"?"right":"left";
    var position=0;
    var bounceForward=direction==="left";
    var hoverRestore=false;
    var charTrack="";
    var wordTrack=[];

    function clean(value){
        value=String(value||"");
        if(cfg.trimWhitespace){
            value=value.replace(/\s+/g," ").replace(/^\s+|\s+$/g,"");
        }
        return value;
    }

    function buildBaseTitle(){
        var content;

        if(cfg.useOriginalTitle){
            content=originalTitle;
        }else{
            content=clean(cfg.customText)||originalTitle;

            if(cfg.preserveModuleTitle && originalTitle && content!==originalTitle){
                content=content+" | "+originalTitle;
            }
        }

        content=clean(content);

        var prefix=String(cfg.prefix||"");
        var suffix=String(cfg.suffix||"");

        return clean(prefix+content+suffix);
    }

    function spaces(count){
        var out="";
        for(var i=0;i<count;i++) out+=" ";
        return out;
    }

    function buildCharacterTrack(){
        var gap=spaces(Math.max(0,cfg.paddingSpaces||0));
        var separator=String(cfg.separator||"");
        return currentBaseTitle+gap+separator+gap;
    }

    function rotateLeft(text){
        return text.length<2?text:text.slice(1)+text.charAt(0);
    }

    function rotateRight(text){
        return text.length<2?text:text.charAt(text.length-1)+text.slice(0,-1);
    }

    function characterTick(){
        if(!charTrack) charTrack=buildCharacterTrack();

        charTrack=direction==="right"
            ? rotateRight(charTrack)
            : rotateLeft(charTrack);

        document.title=charTrack;
        cycles++;
    }

    function wordTick(){
        if(!wordTrack.length){
            var source=currentBaseTitle+" "+String(cfg.separator||"");
            wordTrack=source.split(/\s+/).filter(function(v){return !!v;});
        }

        if(wordTrack.length<2){
            document.title=currentBaseTitle;
            cycles++;
            return;
        }

        if(direction==="right"){
            wordTrack.unshift(wordTrack.pop());
        }else{
            wordTrack.push(wordTrack.shift());
        }

        document.title=wordTrack.join(" ");
        cycles++;
    }

    function bounceTick(){
        var text=buildCharacterTrack();
        var max=Math.max(0,text.length-1);

        if(bounceForward){
            position++;
            if(position>=max){
                position=max;
                bounceForward=false;
                cycles++;
            }
        }else{
            position--;
            if(position<=0){
                position=0;
                bounceForward=true;
                cycles++;
            }
        }

        document.title=text.slice(position)+text.slice(0,position);
    }

    function shouldStop(){
        return cfg.stopAfterCycles>0 && cycles>=cfg.stopAfterCycles;
    }

    function tick(){
        if(hoverRestore){
            document.title=originalTitle;
            return;
        }

        if(cfg.mode==="words") wordTick();
        else if(cfg.mode==="bounce") bounceTick();
        else characterTick();

        if(shouldStop()) stop(true);
    }

    function start(){
        if(running || shouldStop()) return;
        running=true;
        timer=window.setInterval(tick,Math.max(40,cfg.speed||180));
    }

    function stop(restore){
        running=false;
        if(timer){
            window.clearInterval(timer);
            timer=null;
        }
        if(restore) document.title=originalTitle;
    }

    function resetTracks(resetCycles){
        charTrack="";
        wordTrack=[];
        position=0;
        if(resetCycles) cycles=0;
        bounceForward=direction==="left";
    }

    function resume(){
        currentBaseTitle=buildBaseTitle();
        resetTracks(false);
        start();
    }

    currentBaseTitle=buildBaseTitle();
    resetTracks(true);

    window.setTimeout(start,Math.max(0,cfg.startDelay||0));

    document.addEventListener("visibilitychange",function(){
        if(document.hidden){
            if(cfg.restoreOnBlur) stop(true);
            else if(cfg.pauseOnBlur) stop(false);
        }else{
            resume();
        }
    });

    window.addEventListener("blur",function(){
        if(cfg.restoreOnBlur) stop(true);
        else if(cfg.pauseOnBlur) stop(false);
    });

    window.addEventListener("focus",function(){
        resume();
    });

    if(cfg.restoreOnHover){
        document.addEventListener("mouseenter",function(){
            hoverRestore=true;
            document.title=originalTitle;
        });

        document.addEventListener("mouseleave",function(){
            hoverRestore=false;
        });
    }

    window.addEventListener("beforeunload",function(){
        stop(true);
    });

    if(cfg.debugMode && window.console){
        console.info("Omega Title Bar Marquee Manager activo",cfg);
    }
})();