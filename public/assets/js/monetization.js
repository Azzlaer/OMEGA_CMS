document.addEventListener('DOMContentLoaded', function () {
    const meta = (name) => document.querySelector('meta[name="'+name+'"]')?.content || '';
    const loadScript = (src, attrs={}) => {
        const s=document.createElement('script');
        s.async=true; s.src=src;
        Object.entries(attrs).forEach(([k,v])=>s.setAttribute(k,v));
        document.head.appendChild(s);
        return s;
    };

    const gtm=meta('omega-gtm-id');
    const ga4=meta('omega-ga4-id');
    const adsId=meta('omega-google-ads-id');
    const adsense=meta('omega-adsense-client');

    window.dataLayer = window.dataLayer || [];

    if (gtm) {
        window.dataLayer.push({'gtm.start':Date.now(),event:'gtm.js'});
        loadScript('https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(gtm));
    } else if (ga4 || adsId) {
        const first=ga4 || adsId;
        loadScript('https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(first));
        window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        if (ga4) window.gtag('config',ga4);
        if (adsId) window.gtag('config',adsId);
    }

    if (adsense) {
        loadScript(
            'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='+encodeURIComponent(adsense),
            {crossorigin:'anonymous'}
        ).addEventListener('load', function(){
            document.querySelectorAll('ins.adsbygoogle').forEach(function(el){
                if (el.dataset.omegaLoaded==='1') return;
                el.dataset.omegaLoaded='1';
                try { (window.adsbygoogle=window.adsbygoogle||[]).push({}); } catch(e) {}
            });
        });
    }

    const banner=document.querySelector('[data-omega-consent]');
    if (!banner) return;

    const modal=banner.querySelector('[data-consent-modal]');
    const secure=location.protocol==='https:' ? '; Secure' : '';
    const save=(a,d,p)=>{
        document.cookie='omega_consent=v1.a'+(a?1:0)+'.d'+(d?1:0)+'.p'+(p?1:0)+'; Path=/; Max-Age=31536000; SameSite=Lax'+secure;
        location.reload();
    };

    banner.querySelector('[data-consent-accept]')?.addEventListener('click',()=>save(true,true,true));
    banner.querySelector('[data-consent-reject]')?.addEventListener('click',()=>save(false,false,false));
    banner.querySelector('[data-consent-config]')?.addEventListener('click',()=>modal?.classList.add('open'));
    banner.querySelector('[data-consent-close]')?.addEventListener('click',()=>modal?.classList.remove('open'));
    banner.querySelector('[data-consent-save]')?.addEventListener('click',()=>{
        save(
            !!banner.querySelector('[name="consent_analytics"]')?.checked,
            !!banner.querySelector('[name="consent_ads"]')?.checked,
            !!banner.querySelector('[name="consent_personalization"]')?.checked
        );
    });
});