<?php
declare(strict_types=1);
namespace Omega\Services;

use Omega\Core\Database;
use Omega\Core\Security;
use RuntimeException;

final class MonetizationCenter
{
    private const DEFAULTS = [
        'enabled'=>'0',
        'ga4_id'=>'',
        'google_ads_id'=>'',
        'gtm_id'=>'',
        'google_site_verification'=>'',
        'adsense_client'=>'',
        'adsense_auto_ads'=>'0',
        'consent_enabled'=>'1',
        'consent_title'=>'Privacidad y cookies',
        'consent_text'=>'Usamos cookies opcionales para estadísticas y publicidad. Puedes aceptar todas o conservar solo las necesarias.',
        'consent_policy_url'=>'',
        'hide_ads_logged'=>'0',
        'hide_ads_admin'=>'1',
        'ads_txt_enabled'=>'0',
        'ads_txt_content'=>'',
    ];

    public function __construct(private readonly Database $db) {}

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.cms_monetization_settings','U') IS NULL
            CREATE TABLE dbo.cms_monetization_settings(
                setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
                setting_value NVARCHAR(MAX) NULL,
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
        $this->db->execute("
            IF OBJECT_ID('dbo.cms_ad_placements','U') IS NULL
            CREATE TABLE dbo.cms_ad_placements(
                id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                placement_key VARCHAR(80) NOT NULL,
                name NVARCHAR(120) NOT NULL,
                provider VARCHAR(30) NOT NULL DEFAULT 'house',
                enabled BIT NOT NULL DEFAULT 1,
                audience VARCHAR(20) NOT NULL DEFAULT 'all',
                device VARCHAR(20) NOT NULL DEFAULT 'all',
                path_rule NVARCHAR(700) NULL,
                adsense_slot VARCHAR(32) NULL,
                ad_format VARCHAR(30) NULL,
                image_url NVARCHAR(700) NULL,
                target_url NVARCHAR(700) NULL,
                label_text NVARCHAR(180) NULL,
                html_code NVARCHAR(MAX) NULL,
                sort_order INT NOT NULL DEFAULT 100,
                starts_at DATETIME2 NULL,
                ends_at DATETIME2 NULL,
                created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
        $this->db->execute("
            IF NOT EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name='IX_cms_ad_placements_key_active'
                AND object_id=OBJECT_ID('dbo.cms_ad_placements')
            )
            CREATE INDEX IX_cms_ad_placements_key_active
            ON dbo.cms_ad_placements(placement_key,enabled,sort_order)
        ");
    }

    public function settings(): array
    {
        $out = self::DEFAULTS;
        foreach ($this->db->fetchAll("SELECT setting_key,setting_value FROM dbo.cms_monetization_settings") as $row) {
            $key=(string)($row['setting_key']??'');
            if (array_key_exists($key,$out)) $out[$key]=(string)($row['setting_value']??'');
        }
        return $out;
    }

    public function saveSettings(array $input): void
    {
        $values = [
            'enabled'=>!empty($input['enabled'])?'1':'0',
            'ga4_id'=>$this->validateOptionalId((string)($input['ga4_id']??''),'/^G-[A-Z0-9]{5,20}$/i','GA4'),
            'google_ads_id'=>$this->validateOptionalId((string)($input['google_ads_id']??''),'/^AW-[0-9]{5,20}$/i','Google Ads'),
            'gtm_id'=>$this->validateOptionalId((string)($input['gtm_id']??''),'/^GTM-[A-Z0-9]{4,20}$/i','Google Tag Manager'),
            'google_site_verification'=>$this->validateVerification((string)($input['google_site_verification']??'')),
            'adsense_client'=>$this->validateOptionalId((string)($input['adsense_client']??''),'/^ca-pub-[0-9]{10,20}$/i','AdSense Publisher ID'),
            'adsense_auto_ads'=>!empty($input['adsense_auto_ads'])?'1':'0',
            'consent_enabled'=>!empty($input['consent_enabled'])?'1':'0',
            'consent_title'=>$this->cleanText((string)($input['consent_title']??''),100),
            'consent_text'=>$this->cleanText((string)($input['consent_text']??''),700),
            'consent_policy_url'=>$this->validateOptionalUrl((string)($input['consent_policy_url']??''),true),
            'hide_ads_logged'=>!empty($input['hide_ads_logged'])?'1':'0',
            'hide_ads_admin'=>!empty($input['hide_ads_admin'])?'1':'0',
            'ads_txt_enabled'=>!empty($input['ads_txt_enabled'])?'1':'0',
            'ads_txt_content'=>$this->validateAdsTxt((string)($input['ads_txt_content']??'')),
        ];
        foreach ($values as $key=>$value) {
            $this->db->execute("
                MERGE dbo.cms_monetization_settings AS target
                USING (SELECT :setting_key AS setting_key) AS source
                ON target.setting_key=source.setting_key
                WHEN MATCHED THEN UPDATE SET setting_value=:setting_value,updated_at=SYSUTCDATETIME()
                WHEN NOT MATCHED THEN INSERT(setting_key,setting_value) VALUES(source.setting_key,:setting_value);
            ",['setting_key'=>$key,'setting_value'=>$value]);
        }
    }

    public function placements(): array
    {
        return $this->db->fetchAll("SELECT * FROM dbo.cms_ad_placements ORDER BY placement_key,sort_order,id DESC");
    }

    public function findPlacement(int $id): ?array
    {
        if ($id < 1) return null;
        return $this->db->fetchOne("SELECT TOP 1 * FROM dbo.cms_ad_placements WHERE id=:id",['id'=>$id]);
    }

    public function savePlacement(array $input): int
    {
        $id=(int)($input['id']??0);
        $key=strtolower(trim((string)($input['placement_key']??'')));
        if (!preg_match('/^[a-z0-9_-]{3,80}$/',$key)) throw new RuntimeException('La clave del bloque no es válida.');

        $name=$this->cleanText((string)($input['name']??''),120);
        if ($name==='') throw new RuntimeException('Ingresa un nombre para el bloque.');

        $provider=strtolower((string)($input['provider']??'house'));
        if (!in_array($provider,['adsense','image','house','html'],true)) $provider='house';

        $audience=strtolower((string)($input['audience']??'all'));
        if (!in_array($audience,['all','guests','members'],true)) $audience='all';

        $device=strtolower((string)($input['device']??'all'));
        if (!in_array($device,['all','desktop','mobile'],true)) $device='all';

        $slot=trim((string)($input['adsense_slot']??''));
        if ($provider==='adsense' && !preg_match('/^[0-9]{4,32}$/',$slot)) {
            throw new RuntimeException('El bloque AdSense necesita un Ad Slot numérico válido.');
        }
        if ($slot!=='' && !preg_match('/^[0-9]{4,32}$/',$slot)) throw new RuntimeException('Ad Slot inválido.');

        $format=strtolower(trim((string)($input['ad_format']??'auto')));
        if (!in_array($format,['auto','rectangle','horizontal','vertical','fluid'],true)) $format='auto';

        $image=$this->validateOptionalUrl((string)($input['image_url']??''),false);
        $target=$this->validateOptionalUrl((string)($input['target_url']??''),true);
        $label=$this->cleanText((string)($input['label_text']??''),180);
        $html=$this->sanitizeBlockHtml((string)($input['html_code']??''));
        $pathRule=trim((string)($input['path_rule']??''));
        if (strlen($pathRule)>700) throw new RuntimeException('La regla de rutas es demasiado larga.');
        $sort=max(0,min(9999,(int)($input['sort_order']??100)));
        $starts=$this->dateOrNull((string)($input['starts_at']??''));
        $ends=$this->dateOrNull((string)($input['ends_at']??''));
        if ($starts && $ends && strtotime($ends)<strtotime($starts)) throw new RuntimeException('La fecha de término no puede ser anterior al inicio.');

        $params=[
            'placement_key'=>$key,'name'=>$name,'provider'=>$provider,
            'enabled'=>!empty($input['enabled'])?1:0,'audience'=>$audience,'device'=>$device,
            'path_rule'=>$pathRule!==''?$pathRule:null,'adsense_slot'=>$slot!==''?$slot:null,
            'ad_format'=>$format,'image_url'=>$image!==''?$image:null,'target_url'=>$target!==''?$target:null,
            'label_text'=>$label!==''?$label:null,'html_code'=>$html!==''?$html:null,
            'sort_order'=>$sort,'starts_at'=>$starts,'ends_at'=>$ends,
        ];

        if ($id>0) {
            $params['id']=$id;
            $this->db->execute("UPDATE dbo.cms_ad_placements SET
                placement_key=:placement_key,name=:name,provider=:provider,enabled=:enabled,
                audience=:audience,device=:device,path_rule=:path_rule,adsense_slot=:adsense_slot,
                ad_format=:ad_format,image_url=:image_url,target_url=:target_url,label_text=:label_text,
                html_code=:html_code,sort_order=:sort_order,starts_at=:starts_at,ends_at=:ends_at,
                updated_at=SYSUTCDATETIME()
                WHERE id=:id",$params);
            return $id;
        }

        $this->db->execute("INSERT INTO dbo.cms_ad_placements(
            placement_key,name,provider,enabled,audience,device,path_rule,adsense_slot,ad_format,
            image_url,target_url,label_text,html_code,sort_order,starts_at,ends_at
        ) VALUES(
            :placement_key,:name,:provider,:enabled,:audience,:device,:path_rule,:adsense_slot,:ad_format,
            :image_url,:target_url,:label_text,:html_code,:sort_order,:starts_at,:ends_at
        )",$params);
        $row=$this->db->fetchOne("SELECT TOP 1 id FROM dbo.cms_ad_placements ORDER BY id DESC");
        return (int)($row['id']??0);
    }

    public function deletePlacement(int $id): void
    {
        if ($id>0) $this->db->execute("DELETE FROM dbo.cms_ad_placements WHERE id=:id",['id'=>$id]);
    }

    public function publicContext(?string $accountId, bool $isAdmin, string $path): array
    {
        $s=$this->settings();
        $enabled=$s['enabled']==='1';
        $consent=$this->consentState();
        $analyticsAllowed=$s['consent_enabled']!=='1' || $consent['analytics'];
        $adsAllowed=$s['consent_enabled']!=='1' || $consent['ads'];

        return [
            'enabled'=>$enabled,
            'settings'=>$s,
            'consent'=>$consent,
            'analyticsAllowed'=>$analyticsAllowed,
            'adsAllowed'=>$adsAllowed,
            'headHtml'=>$enabled ? $this->headHtml($s,$analyticsAllowed,$adsAllowed) : '',
            'showConsent'=>$enabled && $s['consent_enabled']==='1' && !$consent['decided'],
            'path'=>$path,
            'loggedAccount'=>$accountId,
            'isAdmin'=>$isAdmin,
        ];
    }

    public function securityGoogleEnabled(): bool
    {
        $s=$this->settings();
        return $s['enabled']==='1' && ($s['ga4_id']!=='' || $s['google_ads_id']!=='' || $s['gtm_id']!=='' || $s['adsense_client']!=='');
    }

    public function renderPlacement(string $key, array $context): string
    {
        if (empty($context['enabled'])) return '';
        $s=$context['settings']??self::DEFAULTS;
        if (($s['hide_ads_admin']??'1')==='1' && !empty($context['isAdmin'])) return '';
        if (($s['hide_ads_logged']??'0')==='1' && !empty($context['loggedAccount'])) return '';

        $rows=$this->db->fetchAll("SELECT * FROM dbo.cms_ad_placements
            WHERE placement_key=:k AND enabled=1
              AND (starts_at IS NULL OR starts_at<=SYSUTCDATETIME())
              AND (ends_at IS NULL OR ends_at>=SYSUTCDATETIME())
            ORDER BY sort_order,id",['k'=>$key]);
        if (!$rows) return '';

        $path=(string)($context['path']??'/');
        $out=[];
        foreach ($rows as $r) {
            if (!$this->audienceMatches((string)($r['audience']??'all'),!empty($context['loggedAccount']))) continue;
            if (!$this->pathMatches((string)($r['path_rule']??''),$path)) continue;
            $provider=(string)($r['provider']??'house');
            if ($provider==='adsense' && empty($context['adsAllowed'])) continue;
            $html=$this->renderBlock($r,$context);
            if ($html!=='') $out[]=$html;
        }
        if (!$out) return '';
        return '<section class="omega-ad-zone omega-ad-zone-'.Security::e($key).'" data-placement="'.Security::e($key).'">'.implode('',$out).'</section>';
    }

    public function adsTxt(): ?string
    {
        $s=$this->settings();
        if ($s['enabled']!=='1' || $s['ads_txt_enabled']!=='1') return null;
        $content=trim((string)$s['ads_txt_content']);
        if ($content==='') return null;
        return $content."\n";
    }

    private function headHtml(array $s, bool $analyticsAllowed, bool $adsAllowed): string
    {
        $html=[];
        $gtm=(string)$s['gtm_id'];
        $ga=(string)$s['ga4_id'];
        $aw=(string)$s['google_ads_id'];

        if ($analyticsAllowed) {
            if ($gtm!=='') $html[]='<meta name="omega-gtm-id" content="'.Security::e($gtm).'">';
            if ($ga!=='') $html[]='<meta name="omega-ga4-id" content="'.Security::e($ga).'">';
            if ($aw!=='') $html[]='<meta name="omega-google-ads-id" content="'.Security::e($aw).'">';
        }
        if ((string)($s['google_site_verification']??'')!=='') {
            $html[]='<meta name="google-site-verification" content="'.Security::e((string)$s['google_site_verification']).'">';
        }
        if ($adsAllowed && $s['adsense_client']!=='' && ($s['adsense_auto_ads']??'0')==='1') {
            $html[]='<meta name="omega-adsense-client" content="'.Security::e((string)$s['adsense_client']).'">';
        }
        return implode("\n",$html);
    }

    private function renderBlock(array $r, array $context): string
    {
        $provider=(string)($r['provider']??'house');
        $device=Security::e((string)($r['device']??'all'));
        $name=Security::e((string)($r['name']??'Publicidad'));
        $label=trim((string)($r['label_text']??''));
        $inner='';

        if ($provider==='adsense') {
            $client=Security::e((string)(($context['settings']['adsense_client']??'')));
            $slot=Security::e((string)($r['adsense_slot']??''));
            if ($client==='' || $slot==='') return '';
            $format=Security::e((string)($r['ad_format']??'auto'));
            $inner='<ins class="adsbygoogle" style="display:block" data-ad-client="'.$client.'" data-ad-slot="'.$slot.'" data-ad-format="'.$format.'" data-full-width-responsive="true"></ins>';
        } elseif ($provider==='image' || $provider==='house') {
            $img=trim((string)($r['image_url']??''));
            $target=trim((string)($r['target_url']??''));
            if ($img!=='') {
                $image='<img src="'.Security::e($img).'" alt="'.$name.'" loading="lazy">';
                $inner=$target!==''?'<a href="'.Security::e($target).'" target="_blank" rel="sponsored noopener">'.$image.'</a>':$image;
            } elseif ($label!=='') {
                $copy='<span>'.Security::e($label).'</span>';
                $inner=$target!==''?'<a class="omega-house-ad-copy" href="'.Security::e($target).'">'.$copy.'</a>':$copy;
            }
        } elseif ($provider==='html') {
            $inner=(string)($r['html_code']??'');
        }
        if ($inner==='') return '';
        $tag=$label!==''?'<small class="omega-ad-label">'.Security::e($label).'</small>':'<small class="omega-ad-label">Publicidad</small>';
        return '<div class="omega-ad-block omega-ad-device-'.$device.'" data-ad-provider="'.Security::e($provider).'" aria-label="'.$name.'">'.$tag.$inner.'</div>';
    }

    private function audienceMatches(string $audience, bool $logged): bool
    {
        return $audience==='all' || ($audience==='guests' && !$logged) || ($audience==='members' && $logged);
    }

    private function pathMatches(string $rule, string $path): bool
    {
        $rule=trim($rule);
        if ($rule==='') return true;
        foreach (preg_split('/[\r\n,]+/',$rule) ?: [] as $part) {
            $part=trim($part);
            if ($part==='') continue;
            if ($part==='*') return true;
            if (str_ends_with($part,'*') && str_starts_with($path,substr($part,0,-1))) return true;
            if ($path===$part) return true;
        }
        return false;
    }

    private function consentState(): array
    {
        $raw=(string)($_COOKIE['omega_consent']??'');
        if (!preg_match('/^v1\.a([01])\.d([01])\.p([01])$/',$raw,$m)) {
            return ['decided'=>false,'analytics'=>false,'ads'=>false,'personalization'=>false];
        }
        return ['decided'=>true,'analytics'=>$m[1]==='1','ads'=>$m[2]==='1','personalization'=>$m[3]==='1'];
    }

    private function validateOptionalId(string $value, string $regex, string $label): string
    {
        $value=trim($value);
        if ($value!=='' && !preg_match($regex,$value)) throw new RuntimeException($label.' no tiene un formato válido.');
        return $value;
    }

    private function validateVerification(string $value): string
    {
        $value=trim($value);
        if ($value==='') return '';
        if (strlen($value)>180 || !preg_match('/^[A-Za-z0-9_-]+$/',$value)) {
            throw new RuntimeException('El token de Google Site Verification no es válido.');
        }
        return $value;
    }

    private function cleanText(string $value, int $max): string
    {
        $value=trim(strip_tags($value));
        if (strlen($value)>$max) $value=substr($value,0,$max);
        return $value;
    }

    private function validateOptionalUrl(string $url, bool $allowRelative): string
    {
        $url=trim($url);
        if ($url==='') return '';
        if ($allowRelative && str_starts_with($url,'/')) return substr($url,0,700);
        if (strlen($url)>700 || !filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('Una URL configurada no es válida.');
        if (!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)) throw new RuntimeException('Solo se permiten URLs HTTP/HTTPS.');
        return $url;
    }

    private function validateAdsTxt(string $value): string
    {
        $value=str_replace(["\r\n","\r"],"\n",trim($value));
        if (strlen($value)>20000) throw new RuntimeException('ads.txt supera el tamaño permitido por OMEGA.');
        foreach (explode("\n",$value) as $line) {
            $line=trim($line);
            if ($line==='' || str_starts_with($line,'#')) continue;
            if (substr_count($line,',')<2) throw new RuntimeException('ads.txt contiene una línea inválida: '.$line);
        }
        return $value;
    }

    private function sanitizeBlockHtml(string $html): string
    {
        $html=trim($html);
        return $html==='' ? '' : GuideManager::sanitizeHtml($html);
    }

    private function dateOrNull(string $value): ?string
    {
        $value=trim($value);
        if ($value==='') return null;
        $ts=strtotime($value);
        if ($ts===false) throw new RuntimeException('Fecha de campaña inválida.');
        return date('Y-m-d H:i:s',$ts);
    }
}
