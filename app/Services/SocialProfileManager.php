<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class SocialProfileManager
{
    public const CONSENT_VERSION = '2026-09-10-v1';

    public function __construct(
        private readonly Database $cmsDb,
        private readonly Database $muDb,
        private readonly string $root
    ) {}

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_social_profiles','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_social_profiles(
                    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    account_id VARCHAR(10) NOT NULL,
                    display_name NVARCHAR(50) NULL,
                    country_code CHAR(2) NULL,
                    bio NVARCHAR(500) NULL,

                    discord NVARCHAR(255) NULL,
                    tiktok NVARCHAR(255) NULL,
                    youtube NVARCHAR(255) NULL,
                    website NVARCHAR(255) NULL,
                    facebook NVARCHAR(255) NULL,
                    instagram NVARCHAR(255) NULL,
                    whatsapp NVARCHAR(40) NULL,
                    linkedin NVARCHAR(255) NULL,

                    show_discord BIT NOT NULL CONSTRAINT DF_social_show_discord DEFAULT 0,
                    show_tiktok BIT NOT NULL CONSTRAINT DF_social_show_tiktok DEFAULT 0,
                    show_youtube BIT NOT NULL CONSTRAINT DF_social_show_youtube DEFAULT 0,
                    show_website BIT NOT NULL CONSTRAINT DF_social_show_website DEFAULT 0,
                    show_facebook BIT NOT NULL CONSTRAINT DF_social_show_facebook DEFAULT 0,
                    show_instagram BIT NOT NULL CONSTRAINT DF_social_show_instagram DEFAULT 0,
                    show_whatsapp BIT NOT NULL CONSTRAINT DF_social_show_whatsapp DEFAULT 0,
                    show_linkedin BIT NOT NULL CONSTRAINT DF_social_show_linkedin DEFAULT 0,

                    banner_path NVARCHAR(255) NULL,
                    public_enabled BIT NOT NULL CONSTRAINT DF_social_public_enabled DEFAULT 0,

                    consent_accepted BIT NOT NULL CONSTRAINT DF_social_consent DEFAULT 0,
                    consent_version VARCHAR(40) NULL,
                    consent_accepted_at DATETIME2 NULL,

                    created_at DATETIME2 NOT NULL CONSTRAINT DF_social_created DEFAULT SYSUTCDATETIME(),
                    updated_at DATETIME2 NOT NULL CONSTRAINT DF_social_updated DEFAULT SYSUTCDATETIME()
                );

                CREATE UNIQUE INDEX UX_cms_social_profiles_account
                    ON dbo.cms_social_profiles(account_id);
            END
        ");
    }

    public function profile(string $accountId): array
    {
        $this->ensureSchema();

        $row=$this->cmsDb->fetchOne(
            "SELECT TOP 1 * FROM dbo.cms_social_profiles WHERE account_id=:id",
            ['id'=>$accountId]
        );

        return $row ?: [
            'account_id'=>$accountId,
            'display_name'=>'',
            'country_code'=>'',
            'bio'=>'',
            'discord'=>'',
            'tiktok'=>'',
            'youtube'=>'',
            'website'=>'',
            'facebook'=>'',
            'instagram'=>'',
            'whatsapp'=>'',
            'linkedin'=>'',
            'show_discord'=>0,
            'show_tiktok'=>0,
            'show_youtube'=>0,
            'show_website'=>0,
            'show_facebook'=>0,
            'show_instagram'=>0,
            'show_whatsapp'=>0,
            'show_linkedin'=>0,
            'banner_path'=>'',
            'public_enabled'=>0,
            'consent_accepted'=>0,
            'consent_version'=>'',
            'consent_accepted_at'=>null,
        ];
    }

    public function save(string $accountId,array $post,array $files=[]): void
    {
        $this->ensureSchema();

        $displayName=$this->cleanText((string)($post['display_name']??''),50);
        $countryCode=strtoupper(trim((string)($post['country_code']??'')));
        if($countryCode!=='' && !preg_match('/^[A-Z]{2}$/',$countryCode)){
            throw new RuntimeException('Código de país inválido.');
        }

        $bio=$this->cleanText((string)($post['bio']??''),500);

        $publicEnabled=(string)($post['public_enabled']??'0')==='1';
        $consentAccepted=(string)($post['consent_accepted']??'0')==='1';

        if($publicEnabled && !$consentAccepted){
            throw new RuntimeException(
                'Para publicar tu perfil debes aceptar el consentimiento de publicación pública.'
            );
        }

        $discord=$this->cleanText((string)($post['discord']??''),255);
        $whatsapp=$this->cleanPhone((string)($post['whatsapp']??''));

        $urls=[];
        foreach(['tiktok','youtube','website','facebook','instagram','linkedin'] as $field){
            $urls[$field]=$this->cleanUrl((string)($post[$field]??''),$field);
        }

        $current=$this->profile($accountId);
        $banner=(string)($current['banner_path']??'');

        if((string)($post['remove_banner']??'0')==='1'){
            $this->deleteBanner($banner);
            $banner='';
        }

        if(isset($files['banner']) && is_array($files['banner'])){
            $new=$this->saveBanner($accountId,$files['banner']);
            if($new!==null){
                if($banner!=='' && $banner!==$new) $this->deleteBanner($banner);
                $banner=$new;
            }
        }

        $show=[];
        foreach(['discord','tiktok','youtube','website','facebook','instagram','whatsapp','linkedin'] as $field){
            $show[$field]=(string)($post['show_'.$field]??'0')==='1' ? 1 : 0;
        }

        $exists=$this->cmsDb->fetchOne(
            "SELECT TOP 1 id FROM dbo.cms_social_profiles WHERE account_id=:id",
            ['id'=>$accountId]
        );

        $params=[
            'id'=>$accountId,
            'display_name'=>$displayName,
            'country_code'=>$countryCode,
            'bio'=>$bio,
            'discord'=>$discord,
            'tiktok'=>$urls['tiktok'],
            'youtube'=>$urls['youtube'],
            'website'=>$urls['website'],
            'facebook'=>$urls['facebook'],
            'instagram'=>$urls['instagram'],
            'whatsapp'=>$whatsapp,
            'linkedin'=>$urls['linkedin'],
            'show_discord'=>$show['discord'],
            'show_tiktok'=>$show['tiktok'],
            'show_youtube'=>$show['youtube'],
            'show_website'=>$show['website'],
            'show_facebook'=>$show['facebook'],
            'show_instagram'=>$show['instagram'],
            'show_whatsapp'=>$show['whatsapp'],
            'show_linkedin'=>$show['linkedin'],
            'banner_path'=>$banner,
            'public_enabled'=>$publicEnabled?1:0,
            'consent_accepted'=>$consentAccepted?1:0,
            'consent_version'=>$consentAccepted?self::CONSENT_VERSION:null,
        ];

        if($exists){
            $sql="
                UPDATE dbo.cms_social_profiles SET
                    display_name=:display_name,
                    country_code=:country_code,
                    bio=:bio,
                    discord=:discord,
                    tiktok=:tiktok,
                    youtube=:youtube,
                    website=:website,
                    facebook=:facebook,
                    instagram=:instagram,
                    whatsapp=:whatsapp,
                    linkedin=:linkedin,

                    show_discord=:show_discord,
                    show_tiktok=:show_tiktok,
                    show_youtube=:show_youtube,
                    show_website=:show_website,
                    show_facebook=:show_facebook,
                    show_instagram=:show_instagram,
                    show_whatsapp=:show_whatsapp,
                    show_linkedin=:show_linkedin,

                    banner_path=:banner_path,
                    public_enabled=:public_enabled,
                    consent_accepted=:consent_accepted,
                    consent_version=:consent_version,
                    consent_accepted_at=
                        CASE
                            WHEN :consent_accepted_at_flag=1
                                 AND (consent_accepted=0 OR consent_version<>:consent_version_compare)
                            THEN SYSUTCDATETIME()
                            WHEN :consent_accepted_at_flag2=0 THEN NULL
                            ELSE consent_accepted_at
                        END,
                    updated_at=SYSUTCDATETIME()
                WHERE account_id=:id
            ";

            $params['consent_accepted_at_flag']=$consentAccepted?1:0;
            $params['consent_accepted_at_flag2']=$consentAccepted?1:0;
            $params['consent_version_compare']=self::CONSENT_VERSION;

            $this->cmsDb->execute($sql,$params);
        }else{
            $this->cmsDb->execute("
                INSERT INTO dbo.cms_social_profiles(
                    account_id,display_name,country_code,bio,
                    discord,tiktok,youtube,website,facebook,instagram,whatsapp,linkedin,
                    show_discord,show_tiktok,show_youtube,show_website,
                    show_facebook,show_instagram,show_whatsapp,show_linkedin,
                    banner_path,public_enabled,
                    consent_accepted,consent_version,consent_accepted_at
                ) VALUES(
                    :id,:display_name,:country_code,:bio,
                    :discord,:tiktok,:youtube,:website,:facebook,:instagram,:whatsapp,:linkedin,
                    :show_discord,:show_tiktok,:show_youtube,:show_website,
                    :show_facebook,:show_instagram,:show_whatsapp,:show_linkedin,
                    :banner_path,:public_enabled,
                    :consent_accepted,:consent_version,
                    CASE WHEN :consent_insert_flag=1 THEN SYSUTCDATETIME() ELSE NULL END
                )
            ", $params + ['consent_insert_flag'=>$consentAccepted?1:0]);
        }
    }

    public function publicOwnerProfileByAccount(string $accountId): ?array
    {
        $this->ensureSchema();

        $row=$this->cmsDb->fetchOne("
            SELECT TOP 1 *
            FROM dbo.cms_social_profiles
            WHERE account_id=:id
              AND public_enabled=1
              AND consent_accepted=1
        ",['id'=>$accountId]);

        if(!$row) return null;

        $row['country_name']=$this->countryName((string)($row['country_code']??''));
        $row['country_flag']=$this->countryFlag((string)($row['country_code']??''));
        $row['socials']=$this->publicSocials($row);

        return $row;
    }

    public function publicCharacter(string $name): ?array
    {
        $row=$this->muDb->fetchOne("
            SELECT TOP 1
                c.AccountID,c.Name,c.Class,c.cLevel,c.ResetCount,c.GrandResetCount,
                c.LevelUpPoint,c.PkLevel,c.PkCount,c.MapNumber,c.MapPosX,c.MapPosY,
                c.Strength,c.Dexterity,c.Vitality,c.Energy,
                gm.G_Name
            FROM dbo.Character c
            LEFT JOIN dbo.GuildMember gm ON gm.Name=c.Name
            WHERE c.Name=:name
        ",['name'=>$name]);

        return $row ?: null;
    }

    public function publicSocials(array $row): array
    {
        $out=[];

        $defs=[
            'website'=>['label'=>'Web','icon'=>'🌐'],
            'discord'=>['label'=>'Discord','icon'=>'💬'],
            'facebook'=>['label'=>'Facebook','icon'=>'f'],
            'instagram'=>['label'=>'Instagram','icon'=>'◎'],
            'whatsapp'=>['label'=>'WhatsApp','icon'=>'☎'],
            'tiktok'=>['label'=>'TikTok','icon'=>'♪'],
            'youtube'=>['label'=>'YouTube','icon'=>'▶'],
            'linkedin'=>['label'=>'LinkedIn','icon'=>'in'],
        ];

        foreach($defs as $key=>$meta){
            if(empty($row['show_'.$key])) continue;
            $value=trim((string)($row[$key]??''));
            if($value==='') continue;

            $url=$value;
            if($key==='discord'){
                if(!preg_match('#^https?://#i',$value)){
                    $url='';
                }
            }elseif($key==='whatsapp'){
                $digits=preg_replace('/\D+/','',$value) ?: '';
                $url=$digits!=='' ? 'https://wa.me/'.$digits : '';
            }

            if($key!=='discord' && $key!=='whatsapp' && !$this->isSafeHttpUrl($url)){
                continue;
            }
            if($key==='discord' && $url!=='' && !$this->isSafeHttpUrl($url)){
                $url='';
            }

            $out[]=[
                'key'=>$key,
                'label'=>$meta['label'],
                'icon'=>$meta['icon'],
                'value'=>$value,
                'url'=>$url,
            ];
        }

        return $out;
    }

    public function countries(): array
    {
        return [
            ''=>'Seleccionar país',
            'AR'=>'Argentina','BO'=>'Bolivia','BR'=>'Brasil','CL'=>'Chile','CO'=>'Colombia',
            'CR'=>'Costa Rica','CU'=>'Cuba','DO'=>'República Dominicana','EC'=>'Ecuador',
            'SV'=>'El Salvador','GT'=>'Guatemala','HN'=>'Honduras','MX'=>'México',
            'NI'=>'Nicaragua','PA'=>'Panamá','PY'=>'Paraguay','PE'=>'Perú','PR'=>'Puerto Rico',
            'UY'=>'Uruguay','VE'=>'Venezuela',
            'US'=>'Estados Unidos','CA'=>'Canadá',
            'ES'=>'España','PT'=>'Portugal','FR'=>'Francia','DE'=>'Alemania','IT'=>'Italia',
            'GB'=>'Reino Unido','IE'=>'Irlanda','NL'=>'Países Bajos','BE'=>'Bélgica',
            'CH'=>'Suiza','AT'=>'Austria','SE'=>'Suecia','NO'=>'Noruega','FI'=>'Finlandia',
            'PL'=>'Polonia','RO'=>'Rumania','GR'=>'Grecia','TR'=>'Turquía',
            'RU'=>'Rusia','UA'=>'Ucrania',
            'JP'=>'Japón','KR'=>'Corea del Sur','CN'=>'China','TW'=>'Taiwán',
            'PH'=>'Filipinas','ID'=>'Indonesia','MY'=>'Malasia','SG'=>'Singapur',
            'AU'=>'Australia','NZ'=>'Nueva Zelanda',
        ];
    }

    public function countryName(string $code): string
    {
        $countries=$this->countries();
        return $countries[strtoupper($code)] ?? strtoupper($code);
    }

    public function countryFlag(string $code): string
    {
        $code=strtoupper(trim($code));
        if(!preg_match('/^[A-Z]{2}$/',$code)) return '🌍';

        // Regional indicator symbols built without intl extension.
        $out='';
        foreach(str_split($code) as $ch){
            $cp=0x1F1E6 + (ord($ch)-65);
            $out.=$this->utf8Chr($cp);
        }
        return $out;
    }

    private function saveBanner(string $accountId,array $file): ?string
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error===UPLOAD_ERR_NO_FILE) return null;
        if($error!==UPLOAD_ERR_OK){
            throw new RuntimeException('No fue posible subir el banner.');
        }

        $size=(int)($file['size']??0);
        if($size<=0 || $size>4*1024*1024){
            throw new RuntimeException('El banner debe pesar entre 1 byte y 4 MB.');
        }

        $tmp=(string)($file['tmp_name']??'');
        if($tmp==='' || !is_uploaded_file($tmp)){
            throw new RuntimeException('Upload de banner inválido.');
        }

        $info=@getimagesize($tmp);
        if(!$info){
            throw new RuntimeException('El archivo no es una imagen válida.');
        }

        [$width,$height]=$info;
        if($width<600 || $height<160 || $width>5000 || $height>2500){
            throw new RuntimeException(
                'El banner debe medir al menos 600x160 y como máximo 5000x2500 píxeles.'
            );
        }

        $mime=(string)($info['mime']??'');
        $ext=match($mime){
            'image/jpeg'=>'jpg',
            'image/png'=>'png',
            'image/webp'=>'webp',
            default=>null,
        };
        if(!$ext){
            throw new RuntimeException('Formato no permitido. Usa JPG, PNG o WEBP.');
        }

        $dir=$this->root.'/public/uploads/social-banners';
        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)){
            throw new RuntimeException('No fue posible crear el directorio de banners.');
        }

        $safe=preg_replace('/[^A-Za-z0-9_-]/','',$accountId) ?: 'user';
        $name=$safe.'-'.bin2hex(random_bytes(8)).'.'.$ext;
        $dest=$dir.'/'.$name;

        if(!@move_uploaded_file($tmp,$dest)){
            throw new RuntimeException('No fue posible guardar el banner.');
        }

        return '/uploads/social-banners/'.$name;
    }

    private function deleteBanner(string $publicPath): void
    {
        if($publicPath==='' || !str_starts_with($publicPath,'/uploads/social-banners/')) return;

        $name=basename($publicPath);
        if(!preg_match('/^[A-Za-z0-9_-]+\.(?:jpg|png|webp)$/i',$name)) return;

        $path=$this->root.'/public/uploads/social-banners/'.$name;
        if(is_file($path)) @unlink($path);
    }

    private function cleanText(string $value,int $max): string
    {
        $value=trim(strip_tags($value));
        if(mb_strlen($value)>$max) $value=mb_substr($value,0,$max);
        return $value;
    }

    private function cleanPhone(string $value): string
    {
        $value=trim($value);
        if($value==='') return '';
        if(!preg_match('/^[+0-9 ()-]{6,40}$/',$value)){
            throw new RuntimeException('WhatsApp contiene caracteres no permitidos.');
        }
        return $value;
    }

    private function cleanUrl(string $value,string $label): string
    {
        $value=trim($value);
        if($value==='') return '';

        if(!$this->isSafeHttpUrl($value)){
            throw new RuntimeException(
                ucfirst($label).' debe ser una URL http:// o https:// válida.'
            );
        }

        if(mb_strlen($value)>255){
            throw new RuntimeException(ucfirst($label).' es demasiado largo.');
        }

        return $value;
    }

    private function isSafeHttpUrl(string $url): bool
    {
        if($url==='') return false;
        if(!filter_var($url,FILTER_VALIDATE_URL)) return false;

        $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
        return in_array($scheme,['http','https'],true);
    }

    private function utf8Chr(int $cp): string
    {
        if($cp<=0x7F) return chr($cp);
        if($cp<=0x7FF){
            return chr(0xC0|($cp>>6)).chr(0x80|($cp&0x3F));
        }
        if($cp<=0xFFFF){
            return chr(0xE0|($cp>>12)).
                   chr(0x80|(($cp>>6)&0x3F)).
                   chr(0x80|($cp&0x3F));
        }
        return chr(0xF0|($cp>>18)).
               chr(0x80|(($cp>>12)&0x3F)).
               chr(0x80|(($cp>>6)&0x3F)).
               chr(0x80|($cp&0x3F));
    }
}
