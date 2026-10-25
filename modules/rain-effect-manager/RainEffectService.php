<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaRainEffectService
{
    private const TABLE='cms_rain_effect_settings';

    private array $cache=[];

    public function __construct(private readonly Database $db)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.".self::TABLE."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::TABLE."(
                    SettingKey VARCHAR(120) NOT NULL PRIMARY KEY,
                    SettingValue NVARCHAR(MAX) NULL,
                    UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
                )
            END
        ");

        foreach($this->defaults() as $key=>$value){
            $exists=$this->db->fetchOne(
                "SELECT TOP 1 SettingKey FROM ".self::TABLE." WHERE SettingKey=:key",
                ['key'=>$key]
            );

            if(!$exists){
                $this->db->execute(
                    "INSERT INTO ".self::TABLE."(SettingKey,SettingValue)
                     VALUES(:key,:value)",
                    ['key'=>$key,'value'=>$value]
                );
            }
        }
    }

    public function defaults(): array
    {
        return [
            'enabled'=>'1',
            'disable_admincp'=>'1',
            'effect_type'=>'rain',
            'placement_mode'=>'viewport',
            'target_selector'=>'body',
            'z_index'=>'9990',
            'density'=>'110',
            'speed_min'=>'650',
            'speed_max'=>'1200',
            'drop_length_min'=>'14',
            'drop_length_max'=>'30',
            'drop_width'=>'1',
            'wind'=>'-8',
            'opacity'=>'0.65',
            'color'=>'#a8d8ff',
            'splash_enabled'=>'1',
            'splash_density'=>'35',
            'splash_color'=>'#d9f1ff',
            'lightning_enabled'=>'0',
            'lightning_interval_min'=>'12',
            'lightning_interval_max'=>'30',
            'lightning_color'=>'#ffffff',
            'audio_enabled'=>'0',
            'audio_url'=>'',
            'audio_volume'=>'0.25',
            'audio_loop'=>'1',
            'pause_when_hidden'=>'1',
            'respect_reduced_motion'=>'1',
            'disable_mobile'=>'0',
            'mobile_density'=>'45',
            'max_fps'=>'60',
            'debug_mode'=>'0',
        ];
    }

    public function settings(): array
    {
        if($this->cache) return $this->cache;

        $out=$this->defaults();

        foreach($this->db->fetchAll(
            "SELECT SettingKey,SettingValue FROM ".self::TABLE
        ) as $row){
            $out[(string)$row['SettingKey']]=(string)($row['SettingValue']??'');
        }

        return $this->cache=$out;
    }

    public function save(array $post): void
    {
        $data=$this->sanitize($post);

        $this->db->transaction(function(Database $db) use($data): void {
            foreach($data as $key=>$value){
                $updated=$db->execute(
                    "UPDATE ".self::TABLE."
                     SET SettingValue=:value,UpdatedAt=SYSUTCDATETIME()
                     WHERE SettingKey=:key",
                    ['key'=>$key,'value'=>$value]
                );

                if($updated===0){
                    $db->execute(
                        "INSERT INTO ".self::TABLE."(SettingKey,SettingValue)
                         VALUES(:key,:value)",
                        ['key'=>$key,'value'=>$value]
                    );
                }
            }
        });

        $this->cache=[];
    }

    public function resetDefaults(): void
    {
        $post=$this->defaults();

        foreach([
            'enabled','disable_admincp','splash_enabled','lightning_enabled',
            'audio_enabled','audio_loop','pause_when_hidden',
            'respect_reduced_motion','disable_mobile','debug_mode'
        ] as $key){
            if(($post[$key]??'0')!=='1') unset($post[$key]);
        }

        $this->save($post);
    }

    public function sanitize(array $post): array
    {
        $effect=(string)($post['effect_type']??'rain');
        if(!in_array($effect,['rain','storm','drizzle'],true)) $effect='rain';

        $placement=(string)($post['placement_mode']??'viewport');
        if(!in_array($placement,['viewport','element'],true)) $placement='viewport';

        $speedMin=(int)$this->clampInt($post['speed_min']??650,100,5000,650);
        $speedMax=(int)$this->clampInt($post['speed_max']??1200,100,8000,1200);
        if($speedMax<$speedMin) [$speedMin,$speedMax]=[$speedMax,$speedMin];

        $lengthMin=(int)$this->clampInt($post['drop_length_min']??14,2,100,14);
        $lengthMax=(int)$this->clampInt($post['drop_length_max']??30,2,160,30);
        if($lengthMax<$lengthMin) [$lengthMin,$lengthMax]=[$lengthMax,$lengthMin];

        $lightMin=(int)$this->clampInt($post['lightning_interval_min']??12,2,600,12);
        $lightMax=(int)$this->clampInt($post['lightning_interval_max']??30,2,900,30);
        if($lightMax<$lightMin) [$lightMin,$lightMax]=[$lightMax,$lightMin];

        return [
            'enabled'=>isset($post['enabled'])?'1':'0',
            'disable_admincp'=>isset($post['disable_admincp'])?'1':'0',
            'effect_type'=>$effect,
            'placement_mode'=>$placement,
            'target_selector'=>$this->selector((string)($post['target_selector']??'body')),
            'z_index'=>$this->clampInt($post['z_index']??9990,1,999999,9990),
            'density'=>$this->clampInt($post['density']??110,5,500,110),
            'speed_min'=>(string)$speedMin,
            'speed_max'=>(string)$speedMax,
            'drop_length_min'=>(string)$lengthMin,
            'drop_length_max'=>(string)$lengthMax,
            'drop_width'=>$this->clampFloat($post['drop_width']??1,0.5,8,1),
            'wind'=>$this->clampInt($post['wind']??-8,-100,100,-8),
            'opacity'=>$this->clampFloat($post['opacity']??0.65,0.05,1,0.65),
            'color'=>$this->color((string)($post['color']??'#a8d8ff'),'#a8d8ff'),
            'splash_enabled'=>isset($post['splash_enabled'])?'1':'0',
            'splash_density'=>$this->clampInt($post['splash_density']??35,0,200,35),
            'splash_color'=>$this->color((string)($post['splash_color']??'#d9f1ff'),'#d9f1ff'),
            'lightning_enabled'=>isset($post['lightning_enabled'])?'1':'0',
            'lightning_interval_min'=>(string)$lightMin,
            'lightning_interval_max'=>(string)$lightMax,
            'lightning_color'=>$this->color((string)($post['lightning_color']??'#ffffff'),'#ffffff'),
            'audio_enabled'=>isset($post['audio_enabled'])?'1':'0',
            'audio_url'=>$this->audioUrl((string)($post['audio_url']??'')),
            'audio_volume'=>$this->clampFloat($post['audio_volume']??0.25,0,1,0.25),
            'audio_loop'=>isset($post['audio_loop'])?'1':'0',
            'pause_when_hidden'=>isset($post['pause_when_hidden'])?'1':'0',
            'respect_reduced_motion'=>isset($post['respect_reduced_motion'])?'1':'0',
            'disable_mobile'=>isset($post['disable_mobile'])?'1':'0',
            'mobile_density'=>$this->clampInt($post['mobile_density']??45,5,250,45),
            'max_fps'=>$this->clampInt($post['max_fps']??60,15,120,60),
            'debug_mode'=>isset($post['debug_mode'])?'1':'0',
        ];
    }

    public function clientConfig(): array
    {
        $s=$this->settings();

        return [
            'enabled'=>($s['enabled']??'0')==='1',
            'effectType'=>$s['effect_type']??'rain',
            'placementMode'=>$s['placement_mode']??'viewport',
            'targetSelector'=>$s['target_selector']??'body',
            'zIndex'=>(int)($s['z_index']??9990),
            'density'=>(int)($s['density']??110),
            'speedMin'=>(int)($s['speed_min']??650),
            'speedMax'=>(int)($s['speed_max']??1200),
            'dropLengthMin'=>(int)($s['drop_length_min']??14),
            'dropLengthMax'=>(int)($s['drop_length_max']??30),
            'dropWidth'=>(float)($s['drop_width']??1),
            'wind'=>(int)($s['wind']??-8),
            'opacity'=>(float)($s['opacity']??0.65),
            'color'=>$s['color']??'#a8d8ff',
            'splashEnabled'=>($s['splash_enabled']??'1')==='1',
            'splashDensity'=>(int)($s['splash_density']??35),
            'splashColor'=>$s['splash_color']??'#d9f1ff',
            'lightningEnabled'=>($s['lightning_enabled']??'0')==='1',
            'lightningIntervalMin'=>(int)($s['lightning_interval_min']??12),
            'lightningIntervalMax'=>(int)($s['lightning_interval_max']??30),
            'lightningColor'=>$s['lightning_color']??'#ffffff',
            'audioEnabled'=>($s['audio_enabled']??'0')==='1',
            'audioUrl'=>$s['audio_url']??'',
            'audioVolume'=>(float)($s['audio_volume']??0.25),
            'audioLoop'=>($s['audio_loop']??'1')==='1',
            'pauseWhenHidden'=>($s['pause_when_hidden']??'1')==='1',
            'respectReducedMotion'=>($s['respect_reduced_motion']??'1')==='1',
            'disableMobile'=>($s['disable_mobile']??'0')==='1',
            'mobileDensity'=>(int)($s['mobile_density']??45),
            'maxFps'=>(int)($s['max_fps']??60),
            'debugMode'=>($s['debug_mode']??'0')==='1',
        ];
    }

    public function disabledInAdmin(): bool
    {
        $s=$this->settings();
        if(($s['disable_admincp']??'1')!=='1') return false;

        return str_contains((string)($_SERVER['REQUEST_URI']??''),'/admin');
    }

    private function selector(string $selector): string
    {
        $selector=trim(str_replace(["\0","\r","\n"],'',$selector));
        if($selector==='') return 'body';
        return mb_substr($selector,0,180);
    }

    private function audioUrl(string $url): string
    {
        $url=trim(str_replace(["\0","\r","\n"],'',$url));
        if($url==='') return '';

        // Relative/self-hosted URLs are the most compatible with Omega CSP.
        if(str_starts_with($url,'/')) return mb_substr($url,0,700);

        if(filter_var($url,FILTER_VALIDATE_URL) && preg_match('#^https://#i',$url)){
            return mb_substr($url,0,700);
        }

        return '';
    }

    private function color(string $value,string $fallback): string
    {
        $value=trim($value);
        return preg_match('/^#[0-9A-Fa-f]{6}$/',$value)
            ? strtolower($value)
            : $fallback;
    }

    private function clampInt(mixed $value,int $min,int $max,int $fallback): string
    {
        if(!is_numeric($value)) return (string)$fallback;
        return (string)max($min,min($max,(int)$value));
    }

    private function clampFloat(mixed $value,float $min,float $max,float $fallback): string
    {
        if(!is_numeric($value)) return (string)$fallback;
        return (string)max($min,min($max,(float)$value));
    }
}
