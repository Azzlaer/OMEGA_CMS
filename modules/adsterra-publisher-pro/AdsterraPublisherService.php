<?php
declare(strict_types=1);

use Omega\Core\Database;
use Omega\Core\Security;

final class OmegaAdsterraPublisherService
{
    private const UNITS='cms_adsterra_units';
    private const CACHE='cms_adsterra_api_cache';
    private const API_BASE='https://api3.adsterratools.com/publisher';

    public function __construct(
        private readonly Database $db,
        private readonly array $config=[]
    ) {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.".self::UNITS."','U') IS NULL
            CREATE TABLE dbo.".self::UNITS."(
                Id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                Name NVARCHAR(140) NOT NULL,
                FormatCode VARCHAR(30) NOT NULL,
                PlacementKey VARCHAR(40) NOT NULL,
                Enabled BIT NOT NULL DEFAULT 1,
                Audience VARCHAR(20) NOT NULL DEFAULT 'all',
                Device VARCHAR(20) NOT NULL DEFAULT 'all',
                PathRule NVARCHAR(700) NULL,
                RequiresConsent BIT NOT NULL DEFAULT 1,
                CodeHtml NVARCHAR(MAX) NULL,
                SmartlinkUrl NVARCHAR(1200) NULL,
                SubId VARCHAR(120) NULL,
                CspHosts NVARCHAR(2000) NULL,
                SortOrder INT NOT NULL DEFAULT 100,
                StartsAt DATETIME2 NULL,
                EndsAt DATETIME2 NULL,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::CACHE."','U') IS NULL
            CREATE TABLE dbo.".self::CACHE."(
                CacheKey VARCHAR(160) NOT NULL PRIMARY KEY,
                StatusCode INT NULL,
                Payload NVARCHAR(MAX) NULL,
                ExpiresAt DATETIME2 NULL,
                UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        $this->db->execute("
            IF NOT EXISTS(
                SELECT 1 FROM sys.indexes
                WHERE name='IX_cms_adsterra_units_zone'
                  AND object_id=OBJECT_ID('dbo.".self::UNITS."')
            )
            CREATE INDEX IX_cms_adsterra_units_zone
            ON dbo.".self::UNITS."(PlacementKey,Enabled,SortOrder)
        ");
    }

    public function units(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM dbo.".self::UNITS." ORDER BY PlacementKey,SortOrder,Id"
        );
    }

    public function findUnit(int $id): ?array
    {
        if($id<1) return null;
        return $this->db->fetchOne(
            "SELECT TOP 1 * FROM dbo.".self::UNITS." WHERE Id=:id",
            ['id'=>$id]
        );
    }

    public function createBannerPreset(array $p): int
    {
        $width=(int)($p['BannerWidth']??0);
        $height=(int)($p['BannerHeight']??0);
        $key=strtolower(trim((string)($p['BannerKey']??'')));
        $scriptBase=rtrim(trim((string)($p['BannerScriptBase']??'https://bicea.org/22')),'/');

        if(!in_array([$width,$height],[[320,50],[160,300]],true)){
            throw new RuntimeException('Tamaño de banner preset no soportado.');
        }
        if(!preg_match('/^[a-f0-9]{32}$/',$key)){
            throw new RuntimeException('La key del banner no tiene un formato válido.');
        }
        if(!filter_var($scriptBase,FILTER_VALIDATE_URL) || strtolower((string)parse_url($scriptBase,PHP_URL_SCHEME))!=='https'){
            throw new RuntimeException('La URL base del script debe ser HTTPS.');
        }

        $code="<script>\n".
            "  atOptions = {\n".
            "    'key' : '".$key."',\n".
            "    'format' : 'iframe',\n".
            "    'height' : ".$height.",\n".
            "    'width' : ".$width.",\n".
            "    'params' : {}\n".
            "  };\n".
            "</script>\n".
            "<script src=\"".$scriptBase."/".$key."\"></script>";

        $data=$p;
        $data['Name']=trim((string)($p['Name']??''))!==''?(string)$p['Name']:'Banner '.$width.'x'.$height;
        $data['FormatCode']='banner';
        $data['CodeHtml']=$code;
        $data['SmartlinkUrl']='';
        $data['CspHosts']=trim((string)($p['CspHosts']??''));
        $data['Enabled']=1;

        return $this->saveUnit($data);
    }

    public function saveUnit(array $p): int
    {
        $id=max(0,(int)($p['Id']??0));
        $name=$this->text((string)($p['Name']??''),140);
        if($name==='') throw new RuntimeException('Ingresa un nombre para la unidad.');

        $format=strtolower(trim((string)($p['FormatCode']??'banner')));
        if(!in_array($format,['popunder','social_bar','native_banner','banner','smartlink'],true)){
            throw new RuntimeException('Formato Adsterra no soportado.');
        }

        $placement=strtolower(trim((string)($p['PlacementKey']??'content_bottom')));
        if(!in_array($placement,['head','global_header','below_navigation','content_top','content_bottom','global_footer'],true)){
            throw new RuntimeException('Zona de publicación inválida.');
        }

        $audience=strtolower(trim((string)($p['Audience']??'all')));
        if(!in_array($audience,['all','guests','members'],true)) $audience='all';

        $device=strtolower(trim((string)($p['Device']??'all')));
        if(!in_array($device,['all','desktop','mobile'],true)) $device='all';

        $pathRule=trim((string)($p['PathRule']??''));
        if(strlen($pathRule)>700) throw new RuntimeException('La regla de rutas es demasiado larga.');

        $code=$this->validateAdCode((string)($p['CodeHtml']??''));
        $smartlink=$this->validateOptionalUrl((string)($p['SmartlinkUrl']??''));
        $subId=$this->safeSubId((string)($p['SubId']??''));

        if($format==='smartlink' && $smartlink===''){
            throw new RuntimeException('Smartlink necesita una URL de Adsterra.');
        }
        if($format!=='smartlink' && $code===''){
            throw new RuntimeException('Pega el código oficial de la unidad Adsterra.');
        }

        $autoHosts=$this->extractOrigins($code);
        $manualHosts=$this->parseOrigins((string)($p['CspHosts']??''));
        $hosts=array_values(array_unique(array_merge($autoHosts,$manualHosts)));
        $csp=implode("\n",$hosts);

        $sort=max(0,min(9999,(int)($p['SortOrder']??100)));
        $starts=$this->dateOrNull((string)($p['StartsAt']??''));
        $ends=$this->dateOrNull((string)($p['EndsAt']??''));
        if($starts && $ends && strtotime($ends)<strtotime($starts)){
            throw new RuntimeException('La fecha final no puede ser anterior a la inicial.');
        }

        $params=[
            'n'=>$name,'f'=>$format,'pk'=>$placement,
            'en'=>!empty($p['Enabled'])?1:0,'aud'=>$audience,'dev'=>$device,
            'rule'=>$pathRule!==''?$pathRule:null,'cons'=>!empty($p['RequiresConsent'])?1:0,
            'code'=>$code!==''?$code:null,'link'=>$smartlink!==''?$smartlink:null,
            'sub'=>$subId!==''?$subId:null,'csp'=>$csp!==''?$csp:null,
            'sort'=>$sort,'start'=>$starts,'end'=>$ends
        ];

        if($id>0){
            $params['id']=$id;
            $this->db->execute("
                UPDATE dbo.".self::UNITS." SET
                    Name=:n,FormatCode=:f,PlacementKey=:pk,Enabled=:en,
                    Audience=:aud,Device=:dev,PathRule=:rule,RequiresConsent=:cons,
                    CodeHtml=:code,SmartlinkUrl=:link,SubId=:sub,CspHosts=:csp,
                    SortOrder=:sort,StartsAt=:start,EndsAt=:end,UpdatedAt=SYSUTCDATETIME()
                WHERE Id=:id
            ",$params);
            return $id;
        }

        $this->db->execute("
            INSERT INTO dbo.".self::UNITS."(
                Name,FormatCode,PlacementKey,Enabled,Audience,Device,PathRule,
                RequiresConsent,CodeHtml,SmartlinkUrl,SubId,CspHosts,SortOrder,StartsAt,EndsAt
            ) VALUES(
                :n,:f,:pk,:en,:aud,:dev,:rule,:cons,:code,:link,:sub,:csp,:sort,:start,:end
            )
        ",$params);

        $row=$this->db->fetchOne(
            "SELECT TOP 1 Id FROM dbo.".self::UNITS." WHERE Name=:n AND FormatCode=:f ORDER BY Id DESC",
            ['n'=>$name,'f'=>$format]
        );
        return (int)($row['Id']??0);
    }

    public function deleteUnit(int $id): void
    {
        if($id>0){
            $this->db->execute("DELETE FROM dbo.".self::UNITS." WHERE Id=:id",['id'=>$id]);
        }
    }

    public function cspOrigins(): array
    {
        $rows=$this->db->fetchAll(
            "SELECT CspHosts FROM dbo.".self::UNITS." WHERE Enabled=1 AND CspHosts IS NOT NULL"
        );
        $out=[];
        foreach($rows as $row){
            $out=array_merge($out,$this->parseOrigins((string)($row['CspHosts']??'')));
        }
        return array_values(array_unique($out));
    }

    public function renderHead(array $ctx,string $nonce): string
    {
        return $this->renderZone('head',$ctx,$nonce);
    }

    public function renderZone(string $zone,array $ctx,string $nonce): string
    {
        if($this->hiddenForContext($ctx)) return '';

        $rows=$this->db->fetchAll("
            SELECT * FROM dbo.".self::UNITS."
            WHERE PlacementKey=:z AND Enabled=1
              AND (StartsAt IS NULL OR StartsAt<=SYSUTCDATETIME())
              AND (EndsAt IS NULL OR EndsAt>=SYSUTCDATETIME())
            ORDER BY SortOrder,Id
        ",['z'=>$zone]);

        if(!$rows) return '';

        $path=(string)($ctx['path']??'/');
        $logged=!empty($ctx['logged']);
        $mobile=$this->isMobile();
        $adsConsent=$this->adsConsent();

        $html=[];
        foreach($rows as $row){
            if(!$this->audienceMatches((string)$row['Audience'],$logged)) continue;
            if(!$this->deviceMatches((string)$row['Device'],$mobile)) continue;
            if(!$this->pathMatches((string)($row['PathRule']??''),$path)) continue;

            $respect=!array_key_exists('respect_consent',$this->config) || !empty($this->config['respect_consent']);
            if($respect && (int)$row['RequiresConsent']===1 && !$adsConsent) continue;

            $format=(string)$row['FormatCode'];
            if($format==='smartlink'){
                $url=$this->smartlinkUrl((string)$row['SmartlinkUrl'],(string)($row['SubId']??''));
                $html[]='<div class="omega-adsterra-unit omega-adsterra-smartlink"><a href="'.
                    Security::e($url).'" target="_blank" rel="sponsored noopener noreferrer">'.
                    Security::e((string)$row['Name']).'</a></div>';
                continue;
            }

            $code=$this->nonceScripts((string)($row['CodeHtml']??''),$nonce);
            if($code==='') continue;

            if($zone==='head'){
                $html[]=$code;
            }else{
                $html[]='<section class="omega-adsterra-unit omega-adsterra-'.
                    Security::e($format).'" data-adsterra-unit="'.(int)$row['Id'].'">'.
                    '<small class="omega-adsterra-label">Publicidad</small>'.$code.'</section>';
            }
        }

        return implode("\n",$html);
    }

    public function apiConfigured(): bool
    {
        return !empty($this->config['api_enabled']) && trim((string)($this->config['api_key']??''))!=='';
    }

    public function testApi(): array
    {
        if(!$this->apiConfigured()){
            throw new RuntimeException('Configura y activa el Publisher API Key del módulo.');
        }
        $response=$this->request('domains.csv',[],false,60);
        if($response['status']!==200){
            throw new RuntimeException('Adsterra Publisher API respondió HTTP '.$response['status'].'.');
        }
        return [
            'ok'=>true,
            'status'=>$response['status'],
            'preview'=>substr((string)$response['body'],0,240)
        ];
    }

    public function stats(?int $days=null): array
    {
        if(!$this->apiConfigured()) return ['configured'=>false,'rows'=>[],'raw'=>null];

        $days=$days??max(1,min(90,(int)($this->config['stats_days']??7)));
        $finish=date('Y-m-d');
        $start=date('Y-m-d',strtotime('-'.($days-1).' days'));

        $res=$this->request('stats.json',[
            'start_date'=>$start,
            'finish_date'=>$finish,
            'group_by'=>'date'
        ],true,300);

        $decoded=json_decode((string)$res['body'],true);
        if(!is_array($decoded)){
            return ['configured'=>true,'status'=>$res['status'],'rows'=>[],'raw'=>null];
        }

        $rows=[];
        if(array_is_list($decoded)){
            $rows=$decoded;
        }elseif(isset($decoded['data']) && is_array($decoded['data'])){
            $rows=$decoded['data'];
        }elseif(isset($decoded['items']) && is_array($decoded['items'])){
            $rows=$decoded['items'];
        }

        return [
            'configured'=>true,
            'status'=>$res['status'],
            'rows'=>$rows,
            'raw'=>$decoded,
            'start'=>$start,
            'finish'=>$finish
        ];
    }

    public function diagnostic(): array
    {
        $units=$this->units();
        return [
            'units'=>count($units),
            'enabled_units'=>count(array_filter($units,fn($u)=>(int)($u['Enabled']??0)===1)),
            'csp_origins'=>$this->cspOrigins(),
            'api_enabled'=>!empty($this->config['api_enabled']),
            'api_key_present'=>trim((string)($this->config['api_key']??''))!==''
        ];
    }

    private function request(string $endpoint,array $query=[],bool $json=true,int $ttl=300): array
    {
        if(!in_array($endpoint,['domains.csv','stats.json'],true)){
            throw new RuntimeException('Endpoint Publisher API no permitido.');
        }
        if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL no está disponible.');

        $key=trim((string)($this->config['api_key']??''));
        if($key==='') throw new RuntimeException('Publisher API Key no configurada.');

        $cacheKey=hash('sha256',$endpoint.'?'.http_build_query($query));
        $cached=$this->db->fetchOne("
            SELECT StatusCode,Payload FROM dbo.".self::CACHE."
            WHERE CacheKey=:k AND ExpiresAt>SYSUTCDATETIME()
        ",['k'=>$cacheKey]);
        if($cached){
            $cachedStatus=(int)$cached['StatusCode'];
            if($cachedStatus<200 || $cachedStatus>=300){
                throw new RuntimeException('Adsterra Publisher API respondió HTTP '.$cachedStatus.'.');
            }
            return ['status'=>$cachedStatus,'body'=>(string)$cached['Payload'],'cached'=>true];
        }

        $url=self::API_BASE.'/'.$endpoint;
        if($query) $url.='?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);

        $ch=curl_init($url);
        if($ch===false) throw new RuntimeException('No fue posible iniciar cURL.');
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_TIMEOUT=>12,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_HTTPHEADER=>[
                'Accept: '.($json?'application/json':'text/csv, text/plain;q=0.9, */*;q=0.8'),
                'X-API-Key: '.$key
            ],
            CURLOPT_USERAGENT=>'OMEGA-CMS/AdsterraPublisherPRO'
        ]);

        $body=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $err=curl_error($ch);
        curl_close($ch);

        if(!is_string($body)){
            throw new RuntimeException('Adsterra API no respondió: '.$err);
        }

        $this->db->execute("
            MERGE dbo.".self::CACHE." AS target
            USING(
                SELECT CAST(:k AS VARCHAR(160)) CacheKey,
                       CAST(:s AS INT) StatusCode,
                       CAST(:p AS NVARCHAR(MAX)) Payload
            ) source
            ON target.CacheKey=source.CacheKey
            WHEN MATCHED THEN UPDATE SET
                StatusCode=source.StatusCode,Payload=source.Payload,
                ExpiresAt=DATEADD(SECOND,:ttl,SYSUTCDATETIME()),UpdatedAt=SYSUTCDATETIME()
            WHEN NOT MATCHED THEN INSERT(CacheKey,StatusCode,Payload,ExpiresAt)
                VALUES(source.CacheKey,source.StatusCode,source.Payload,DATEADD(SECOND,:ttl2,SYSUTCDATETIME()));
        ",['k'=>$cacheKey,'s'=>$status,'p'=>$body,'ttl'=>$ttl,'ttl2'=>$ttl]);

        if($status<200 || $status>=300){
            throw new RuntimeException('Adsterra Publisher API respondió HTTP '.$status.'.');
        }

        return ['status'=>$status,'body'=>$body,'cached'=>false];
    }

    private function hiddenForContext(array $ctx): bool
    {
        if(!empty($this->config['hide_admin']) && !empty($ctx['is_admin'])) return true;
        if(!empty($this->config['hide_logged']) && !empty($ctx['logged'])) return true;
        return false;
    }

    private function adsConsent(): bool
    {
        $raw=(string)($_COOKIE['omega_consent']??'');
        if(!preg_match('/^v1\.a([01])\.d([01])\.p([01])$/',$raw,$m)) return false;
        return $m[2]==='1';
    }

    private function audienceMatches(string $audience,bool $logged): bool
    {
        return $audience==='all' || ($audience==='guests' && !$logged) || ($audience==='members' && $logged);
    }

    private function deviceMatches(string $device,bool $mobile): bool
    {
        return $device==='all' || ($device==='mobile' && $mobile) || ($device==='desktop' && !$mobile);
    }

    private function isMobile(): bool
    {
        $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
        return (bool)preg_match('/mobile|android|iphone|ipad|ipod/i',$ua);
    }

    private function pathMatches(string $rule,string $path): bool
    {
        $rule=trim($rule);
        if($rule==='') return true;
        foreach(preg_split('/[\r\n,]+/',$rule)?:[] as $part){
            $part=trim($part);
            if($part==='' ) continue;
            if($part==='*') return true;
            if(str_ends_with($part,'*') && str_starts_with($path,substr($part,0,-1))) return true;
            if($path===$part) return true;
        }
        return false;
    }

    private function smartlinkUrl(string $url,string $subId): string
    {
        if($subId==='') return $url;
        $sep=str_contains($url,'?')?'&':'?';
        return $url.$sep.'psid='.rawurlencode($subId);
    }

    private function nonceScripts(string $html,string $nonce): string
    {
        if($nonce==='') return $html;
        return (string)preg_replace_callback('/<script\b([^>]*)>/i',function(array $m) use($nonce): string {
            if(preg_match('/\bnonce\s*=/i',$m[1])) return $m[0];
            return '<script nonce="'.Security::e($nonce).'"'.$m[1].'>';
        },$html);
    }

    private function validateAdCode(string $html): string
    {
        $html=trim($html);
        if($html==='') return '';
        if(strlen($html)>60000) throw new RuntimeException('El código publicitario supera 60 KB.');
        if(str_contains($html,'<?') || str_contains($html,'?>')){
            throw new RuntimeException('No se permite código PHP dentro de una unidad publicitaria.');
        }
        if(preg_match('/javascript\s*:/i',$html)){
            throw new RuntimeException('No se permiten URLs javascript: en el código.');
        }
        return $html;
    }

    private function extractOrigins(string $html): array
    {
        if($html==='') return [];
        preg_match_all('#(?:src|href)\s*=\s*["\']((?:https?:)?//[^/"\']+)#i',$html,$m);
        $out=[];
        foreach($m[1]??[] as $raw){
            $origin=$this->origin((string)$raw);
            if($origin!=='') $out[]=$origin;
        }
        return array_values(array_unique($out));
    }

    private function parseOrigins(string $text): array
    {
        $out=[];
        foreach(preg_split('/[\r\n,\s]+/',trim($text))?:[] as $raw){
            if($raw==='') continue;
            $origin=$this->origin($raw);
            if($origin==='') throw new RuntimeException('Host CSP inválido: '.$raw);
            $out[]=$origin;
        }
        return array_values(array_unique($out));
    }

    private function origin(string $raw): string
    {
        $raw=trim($raw);
        if(str_starts_with($raw,'//')) $raw='https:'.$raw;
        if(!preg_match('#^https://[A-Za-z0-9.-]+(?::\d+)?$#',$raw)){
            if(filter_var($raw,FILTER_VALIDATE_URL)){
                $scheme=strtolower((string)parse_url($raw,PHP_URL_SCHEME));
                $host=(string)parse_url($raw,PHP_URL_HOST);
                $port=parse_url($raw,PHP_URL_PORT);
                if($scheme!=='https' || $host==='') return '';
                return 'https://'.$host.($port?':'.$port:'');
            }
            return '';
        }
        return rtrim($raw,'/');
    }

    private function validateOptionalUrl(string $url): string
    {
        $url=trim($url);
        if($url==='') return '';
        if(strlen($url)>1200 || !filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('URL Smartlink inválida.');
        if(strtolower((string)parse_url($url,PHP_URL_SCHEME))!=='https') throw new RuntimeException('Smartlink debe usar HTTPS.');
        return $url;
    }

    private function safeSubId(string $value): string
    {
        $value=trim($value);
        if($value==='') return '';
        if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/',$value)){
            throw new RuntimeException('Sub ID solo admite letras, números, guion y guion bajo.');
        }
        return $value;
    }

    private function text(string $value,int $max): string
    {
        $value=trim(strip_tags($value));
        return strlen($value)>$max?substr($value,0,$max):$value;
    }

    private function dateOrNull(string $value): ?string
    {
        $value=trim($value);
        if($value==='') return null;
        $ts=strtotime($value);
        if($ts===false) throw new RuntimeException('Fecha inválida.');
        return date('Y-m-d H:i:s',$ts);
    }
}
