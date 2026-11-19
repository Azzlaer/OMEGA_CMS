document.addEventListener('DOMContentLoaded',function(){
    const script=document.getElementById('omega-live-traffic');
    if(!script) return;

    const cfg={
        type:script.dataset.widgetType||'summary',
        placement:script.dataset.placement||'footer',
        refresh:Math.max(5,parseInt(script.dataset.refresh||'15',10)||15),
        showOnline:script.dataset.showOnline==='1',
        showToday:script.dataset.showToday==='1',
        showTotal:script.dataset.showTotal==='1',
        showPageviews:script.dataset.showPageviews==='1',
        label:script.dataset.label||'Visitantes',
        accent:script.dataset.accent||'#d5a33d',
        side:script.dataset.floatingSide||'right'
    };

    let stats={
        online:Number(script.dataset.online||0),
        unique_today:Number(script.dataset.uniqueToday||0),
        unique_total:Number(script.dataset.uniqueTotal||0),
        pageviews_total:Number(script.dataset.pageviewsTotal||0),
        pageviews_today:Number(script.dataset.pageviewsToday||0),
        devices:{desktop:0,mobile:0,other:0}
    };

    const nf=new Intl.NumberFormat(document.documentElement.lang||'es-CL');

    function metric(key,label,value){
        return '<div class="omega-traffic-metric omega-traffic-'+key+'"><span>'+label+'</span><strong>'+nf.format(value||0)+'</strong></div>';
    }

    function inner(){
        if(cfg.type==='online') return metric('online','Online ahora',stats.online);
        if(cfg.type==='total') return metric('total','Visitantes únicos',stats.unique_total);
        if(cfg.type==='today') return metric('today','Visitantes hoy',stats.unique_today);
        if(cfg.type==='pageviews') return metric('pageviews','Pageviews',stats.pageviews_total);

        const items=[];
        if(cfg.showOnline) items.push(metric('online','Online',stats.online));
        if(cfg.showToday) items.push(metric('today','Hoy',stats.unique_today));
        if(cfg.showTotal) items.push(metric('total','Únicos',stats.unique_total));
        if(cfg.showPageviews) items.push(metric('pageviews','Views',stats.pageviews_total));

        if(cfg.type==='compact'){
            return '<div class="omega-traffic-compact-row">'+items.join('')+'</div>';
        }

        if(cfg.type==='bar'){
            return '<div class="omega-traffic-bar-row">'+items.join('')+'</div>';
        }

        if(cfg.type==='floating'){
            return '<button class="omega-traffic-floating-toggle" type="button" aria-expanded="false">● '+nf.format(stats.online)+' online</button>'+
                '<div class="omega-traffic-floating-panel"><div class="omega-traffic-title">'+cfg.label+'</div>'+
                '<div class="omega-traffic-grid">'+items.join('')+'</div>'+
                '<div class="omega-traffic-devices"><span>Desktop '+nf.format(stats.devices?.desktop||0)+'</span><span>Mobile '+nf.format(stats.devices?.mobile||0)+'</span></div></div>';
        }

        return '<div class="omega-traffic-title">'+cfg.label+'</div>'+
            '<div class="omega-traffic-grid">'+items.join('')+'</div>'+
            '<div class="omega-traffic-devices"><span>Desktop '+nf.format(stats.devices?.desktop||0)+'</span><span>Mobile '+nf.format(stats.devices?.mobile||0)+'</span></div>';
    }

    const root=document.createElement('section');
    root.className='omega-live-traffic omega-live-traffic-'+cfg.type+' omega-live-traffic-side-'+cfg.side;
    root.style.setProperty('--traffic-accent',cfg.accent);
    root.setAttribute('aria-label',cfg.label);

    function render(){
        root.innerHTML=inner();
        const toggle=root.querySelector('.omega-traffic-floating-toggle');
        const panel=root.querySelector('.omega-traffic-floating-panel');
        if(toggle&&panel){
            toggle.addEventListener('click',function(){
                const open=root.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded',open?'true':'false');
            });
        }
    }

    function place(){
        if(cfg.type==='floating' || cfg.placement==='floating'){
            document.body.appendChild(root);
            return;
        }

        const main=document.querySelector('main');
        const footer=document.querySelector('.site-footer');

        if(cfg.placement==='content_top' && main){
            main.insertBefore(root,main.firstChild);
        }else if(cfg.placement==='content_bottom' && main){
            main.appendChild(root);
        }else if(footer){
            footer.insertBefore(root,footer.firstChild);
        }else{
            document.body.appendChild(root);
        }
    }

    async function refresh(){
        try{
            const res=await fetch('/module-api/live-traffic-counter-pro/stats',{credentials:'same-origin',cache:'no-store'});
            if(!res.ok) return;
            const data=await res.json();
            if(data&&data.ok&&data.stats){
                stats=data.stats;
                render();
            }
        }catch(e){}
    }

    render();
    place();
    window.setInterval(refresh,cfg.refresh*1000);
});