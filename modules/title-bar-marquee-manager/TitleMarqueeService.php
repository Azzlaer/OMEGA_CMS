<?php
declare(strict_types=1);

use Omega\Core\Database;
final class OmegaTitleMarqueeService
{
    private const TABLE='cms_title_marquee_settings';

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
                    "INSERT INTO ".self::TABLE."(SettingKey,SettingValue) VALUES(:key,:value)",
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
            'use_original_title'=>'1',
            'custom_text'=>'',
            'prefix'=>'',
            'suffix'=>'',
            'separator'=>'✦',
            'speed'=>'180',
            'direction'=>'left',
            'mode'=>'characters',
            'padding_spaces'=>'3',
            'pause_on_blur'=>'1',
            'restore_on_blur'=>'0',
            'restore_on_hover'=>'0',
            'start_delay'=>'700',
            'stop_after_cycles'=>'0',
            'preserve_module_title'=>'1',
            'trim_whitespace'=>'1',
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
                    ['value'=>$value,'key'=>$key]
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
        $this->save($this->defaultsAsPost());
    }

    private function defaultsAsPost(): array
    {
        $d=$this->defaults();
        $out=$d;

        foreach([
            'enabled','disable_admincp','use_original_title',
            'pause_on_blur','restore_on_blur','restore_on_hover',
            'preserve_module_title','trim_whitespace','debug_mode'
        ] as $key){
            if(($d[$key]??'0')==='1') $out[$key]='1';
            else unset($out[$key]);
        }

        return $out;
    }

    public function sanitize(array $post): array
    {
        $direction=(string)($post['direction']??'left');
        if(!in_array($direction,['left','right'],true)) $direction='left';

        $mode=(string)($post['mode']??'characters');
        if(!in_array($mode,['characters','words','bounce'],true)) $mode='characters';

        return [
            'enabled'=>isset($post['enabled'])?'1':'0',
            'disable_admincp'=>isset($post['disable_admincp'])?'1':'0',
            'use_original_title'=>isset($post['use_original_title'])?'1':'0',
            'custom_text'=>$this->text((string)($post['custom_text']??''),500),
            'prefix'=>$this->text((string)($post['prefix']??''),120),
            'suffix'=>$this->text((string)($post['suffix']??''),160),
            'separator'=>$this->text((string)($post['separator']??'✦'),40),
            'speed'=>$this->clampInt($post['speed']??180,40,5000,180),
            'direction'=>$direction,
            'mode'=>$mode,
            'padding_spaces'=>$this->clampInt($post['padding_spaces']??3,0,30,3),
            'pause_on_blur'=>isset($post['pause_on_blur'])?'1':'0',
            'restore_on_blur'=>isset($post['restore_on_blur'])?'1':'0',
            'restore_on_hover'=>isset($post['restore_on_hover'])?'1':'0',
            'start_delay'=>$this->clampInt($post['start_delay']??700,0,30000,700),
            'stop_after_cycles'=>$this->clampInt($post['stop_after_cycles']??0,0,9999,0),
            'preserve_module_title'=>isset($post['preserve_module_title'])?'1':'0',
            'trim_whitespace'=>isset($post['trim_whitespace'])?'1':'0',
            'debug_mode'=>isset($post['debug_mode'])?'1':'0',
        ];
    }

    public function clientConfig(): array
    {
        $s=$this->settings();

        return [
            'enabled'=>($s['enabled']??'0')==='1',
            'useOriginalTitle'=>($s['use_original_title']??'1')==='1',
            'customText'=>$s['custom_text']??'',
            'prefix'=>$s['prefix']??'',
            'suffix'=>$s['suffix']??'',
            'separator'=>$s['separator']??'✦',
            'speed'=>(int)($s['speed']??180),
            'direction'=>$s['direction']??'left',
            'mode'=>$s['mode']??'characters',
            'paddingSpaces'=>(int)($s['padding_spaces']??3),
            'pauseOnBlur'=>($s['pause_on_blur']??'1')==='1',
            'restoreOnBlur'=>($s['restore_on_blur']??'0')==='1',
            'restoreOnHover'=>($s['restore_on_hover']??'0')==='1',
            'startDelay'=>(int)($s['start_delay']??700),
            'stopAfterCycles'=>(int)($s['stop_after_cycles']??0),
            'preserveModuleTitle'=>($s['preserve_module_title']??'1')==='1',
            'trimWhitespace'=>($s['trim_whitespace']??'1')==='1',
            'debugMode'=>($s['debug_mode']??'0')==='1',
        ];
    }

    public function disabledInAdmin(): bool
    {
        $s=$this->settings();
        if(($s['disable_admincp']??'1')!=='1') return false;

        $path=(string)($_SERVER['REQUEST_URI']??'');
        return str_contains($path,'/admin');
    }

    private function text(string $value,int $max): string
    {
        $value=trim(str_replace(["\0","\r"],'',$value));
        return mb_substr($value,0,$max);
    }

    private function clampInt(mixed $value,int $min,int $max,int $fallback): string
    {
        if(!is_numeric($value)) return (string)$fallback;
        return (string)max($min,min($max,(int)$value));
    }
}
