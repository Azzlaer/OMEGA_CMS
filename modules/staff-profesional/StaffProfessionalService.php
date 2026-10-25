<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaStaffProfessionalService
{
    private const SETTINGS='cms_staff_settings';
    private const MEMBERS='cms_staff_members';

    private array $settingsCache=[];

    public function __construct(
        private readonly Database $cmsDb,
        private readonly Database $muDb
    ){
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.".self::SETTINGS."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::SETTINGS."(
                    SettingKey VARCHAR(100) NOT NULL PRIMARY KEY,
                    SettingValue NVARCHAR(MAX) NULL,
                    UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
                )
            END
        ");

        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.".self::MEMBERS."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::MEMBERS."(
                    StaffId INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    CharacterName VARCHAR(50) NOT NULL,
                    RankName VARCHAR(80) NOT NULL DEFAULT('Game Master'),
                    RankColor VARCHAR(20) NOT NULL DEFAULT('#d4af37'),
                    Department VARCHAR(100) NULL,
                    ShortDescription NVARCHAR(255) NULL,
                    Biography NVARCHAR(MAX) NULL,
                    AvatarUrl NVARCHAR(500) NULL,
                    Country NVARCHAR(80) NULL,
                    Languages NVARCHAR(150) NULL,
                    Timezone NVARCHAR(80) NULL,
                    Schedule NVARCHAR(150) NULL,
                    ContactPhone NVARCHAR(50) NULL,
                    ContactWhatsapp NVARCHAR(80) NULL,
                    ContactDiscord NVARCHAR(100) NULL,
                    ContactEmail NVARCHAR(150) NULL,
                    ContactWebsite NVARCHAR(500) NULL,
                    PublicStatus VARCHAR(20) NOT NULL DEFAULT('available'),
                    ShowPhone BIT NOT NULL DEFAULT(0),
                    ShowWhatsapp BIT NOT NULL DEFAULT(0),
                    ShowDiscord BIT NOT NULL DEFAULT(1),
                    ShowEmail BIT NOT NULL DEFAULT(0),
                    ShowStats BIT NOT NULL DEFAULT(1),
                    ShowGuild BIT NOT NULL DEFAULT(1),
                    ShowOnline BIT NOT NULL DEFAULT(1),
                    Featured BIT NOT NULL DEFAULT(0),
                    Active BIT NOT NULL DEFAULT(1),
                    SortOrder INT NOT NULL DEFAULT(0),
                    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                    UpdatedAt DATETIME2 NULL
                )
            END
        ");

        foreach($this->defaults() as $key=>$value){
            $exists=$this->cmsDb->fetchOne(
                "SELECT TOP 1 SettingKey FROM ".self::SETTINGS." WHERE SettingKey=:key",
                ['key'=>$key]
            );
            if(!$exists){
                $this->cmsDb->execute(
                    "INSERT INTO ".self::SETTINGS."(SettingKey,SettingValue)
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
            'title'=>'Nuestro Equipo',
            'subtitle'=>'Conoce a las personas que trabajan para mantener el servidor seguro, activo y entretenido.',
            'warning_title'=>'Información importante',
            'warning_text'=>'Los miembros oficiales del staff nunca solicitarán tu contraseña. Verifica siempre su personaje, rango y clan desde esta página.',
            'show_warning'=>'1',
            'show_empty_slots'=>'0',
            'empty_slots_count'=>'0',
            'empty_slot_title'=>'Cargo disponible',
            'empty_slot_text'=>'Estamos evaluando futuros integrantes para este puesto.',
            'background_image'=>'',
            'header_image'=>'',
            'default_avatar'=>'assets/images/default-avatar.png',
            'accent_color'=>'#d4af37',
            'card_columns'=>'3',
            'show_search'=>'1',
            'show_rank_filter'=>'1',
            'show_department_filter'=>'1',
            'show_character_class'=>'1',
            'show_level'=>'1',
            'show_resets'=>'1',
            'show_master_resets'=>'1',
            'show_pk'=>'0',
            'show_map'=>'1',
            'contact_notice'=>'Los datos de contacto son publicados voluntariamente por cada integrante.',
            'footer_text'=>'Todo integrante mostrado en esta página ha sido validado por la administración.',
        ];
    }

    public function settings(): array
    {
        if($this->settingsCache) return $this->settingsCache;

        $out=$this->defaults();
        foreach($this->cmsDb->fetchAll(
            "SELECT SettingKey,SettingValue FROM ".self::SETTINGS
        ) as $row){
            $out[(string)$row['SettingKey']]=(string)($row['SettingValue']??'');
        }

        return $this->settingsCache=$out;
    }

    public function saveSettings(array $post): void
    {
        $data=[
            'enabled'=>isset($post['enabled'])?'1':'0',
            'title'=>$this->text((string)($post['title']??''),150),
            'subtitle'=>$this->text((string)($post['subtitle']??''),500),
            'warning_title'=>$this->text((string)($post['warning_title']??''),150),
            'warning_text'=>$this->text((string)($post['warning_text']??''),1500),
            'show_warning'=>isset($post['show_warning'])?'1':'0',
            'show_empty_slots'=>isset($post['show_empty_slots'])?'1':'0',
            'empty_slots_count'=>(string)max(0,min(12,(int)($post['empty_slots_count']??0))),
            'empty_slot_title'=>$this->text((string)($post['empty_slot_title']??''),120),
            'empty_slot_text'=>$this->text((string)($post['empty_slot_text']??''),400),
            'background_image'=>$this->url((string)($post['background_image']??'')),
            'header_image'=>$this->url((string)($post['header_image']??'')),
            'default_avatar'=>$this->assetOrUrl((string)($post['default_avatar']??'assets/images/default-avatar.png')),
            'accent_color'=>$this->color((string)($post['accent_color']??'#d4af37'),'#d4af37'),
            'card_columns'=>(string)max(1,min(4,(int)($post['card_columns']??3))),
            'show_search'=>isset($post['show_search'])?'1':'0',
            'show_rank_filter'=>isset($post['show_rank_filter'])?'1':'0',
            'show_department_filter'=>isset($post['show_department_filter'])?'1':'0',
            'show_character_class'=>isset($post['show_character_class'])?'1':'0',
            'show_level'=>isset($post['show_level'])?'1':'0',
            'show_resets'=>isset($post['show_resets'])?'1':'0',
            'show_master_resets'=>isset($post['show_master_resets'])?'1':'0',
            'show_pk'=>isset($post['show_pk'])?'1':'0',
            'show_map'=>isset($post['show_map'])?'1':'0',
            'contact_notice'=>$this->text((string)($post['contact_notice']??''),500),
            'footer_text'=>$this->text((string)($post['footer_text']??''),500),
        ];

        $this->cmsDb->transaction(function(Database $db) use($data): void {
            foreach($data as $key=>$value){
                $changed=$db->execute(
                    "UPDATE ".self::SETTINGS."
                     SET SettingValue=:value,UpdatedAt=SYSUTCDATETIME()
                     WHERE SettingKey=:key",
                    ['key'=>$key,'value'=>$value]
                );
                if($changed===0){
                    $db->execute(
                        "INSERT INTO ".self::SETTINGS."(SettingKey,SettingValue)
                         VALUES(:key,:value)",
                        ['key'=>$key,'value'=>$value]
                    );
                }
            }
        });

        $this->settingsCache=[];
    }

    public function characters(): array
    {
        return $this->muDb->fetchAll("
            SELECT TOP 500
                LTRIM(RTRIM(Name)) AS Name,
                LTRIM(RTRIM(AccountID)) AS AccountID,
                Class,cLevel,ResetCount,GrandResetCount
            FROM Character
            ORDER BY Name
        ");
    }

    public function members(bool $activeOnly=true): array
    {
        $sql="SELECT * FROM ".self::MEMBERS;
        if($activeOnly) $sql.=" WHERE Active=1";
        $sql.=" ORDER BY Featured DESC,SortOrder ASC,StaffId ASC";

        $rows=$this->cmsDb->fetchAll($sql);

        foreach($rows as &$row){
            $name=trim((string)$row['CharacterName']);

            $char=$this->muDb->fetchOne("
                SELECT TOP 1
                    LTRIM(RTRIM(AccountID)) AccountID,
                    Class,cLevel,MapNumber,MapPosX,MapPosY,PkLevel,
                    ResetCount AS Resets,
                    GrandResetCount AS MasterResets
                FROM Character
                WHERE LTRIM(RTRIM(Name))=:name
            ",['name'=>$name]) ?? [];

            $row=array_merge($row,[
                'AccountID'=>$char['AccountID']??'',
                'Class'=>$char['Class']??null,
                'cLevel'=>$char['cLevel']??null,
                'MapNumber'=>$char['MapNumber']??null,
                'MapPosX'=>$char['MapPosX']??null,
                'MapPosY'=>$char['MapPosY']??null,
                'PkLevel'=>$char['PkLevel']??null,
                'Resets'=>$char['Resets']??0,
                'MasterResets'=>$char['MasterResets']??0,
                'GuildName'=>'',
                'GuildMaster'=>'',
                'GuildScore'=>0,
                'IsOnline'=>0,
            ]);

            $guild=$this->muDb->fetchOne("
                SELECT TOP 1
                    ISNULL(gm.G_Name,'') GuildName,
                    ISNULL(g.G_Master,'') GuildMaster,
                    ISNULL(g.G_Score,0) GuildScore
                FROM GuildMember gm
                LEFT JOIN Guild g ON g.G_Name=gm.G_Name
                WHERE LTRIM(RTRIM(gm.Name))=:name
            ",['name'=>$name]);

            if($guild){
                $row['GuildName']=$guild['GuildName']??'';
                $row['GuildMaster']=$guild['GuildMaster']??'';
                $row['GuildScore']=(int)($guild['GuildScore']??0);
            }

            if(!empty($row['AccountID'])){
                $online=$this->muDb->fetchOne("
                    SELECT TOP 1 ConnectStat
                    FROM MEMB_STAT
                    WHERE LTRIM(RTRIM(memb___id))=:account
                ",['account'=>(string)$row['AccountID']]);

                $row['IsOnline']=(int)($online['ConnectStat']??0)>0 ? 1 : 0;
            }
        }
        unset($row);

        return $rows;
    }

    public function find(int $id): ?array
    {
        return $this->cmsDb->fetchOne(
            "SELECT TOP 1 * FROM ".self::MEMBERS." WHERE StaffId=:id",
            ['id'=>$id]
        );
    }

    public function add(array $post): void
    {
        $d=$this->memberData($post);

        $exists=$this->cmsDb->fetchOne(
            "SELECT TOP 1 StaffId FROM ".self::MEMBERS."
             WHERE LTRIM(RTRIM(CharacterName))=:name",
            ['name'=>$d['CharacterName']]
        );
        if($exists) throw new \RuntimeException('Ese personaje ya pertenece al Staff.');

        $this->cmsDb->execute("
            INSERT INTO ".self::MEMBERS."
            (CharacterName,RankName,RankColor,Department,ShortDescription,Biography,
             AvatarUrl,Country,Languages,Timezone,Schedule,ContactPhone,ContactWhatsapp,
             ContactDiscord,ContactEmail,ContactWebsite,PublicStatus,ShowPhone,
             ShowWhatsapp,ShowDiscord,ShowEmail,ShowStats,ShowGuild,ShowOnline,
             Featured,Active,SortOrder)
            VALUES
            (:CharacterName,:RankName,:RankColor,:Department,:ShortDescription,:Biography,
             :AvatarUrl,:Country,:Languages,:Timezone,:Schedule,:ContactPhone,:ContactWhatsapp,
             :ContactDiscord,:ContactEmail,:ContactWebsite,:PublicStatus,:ShowPhone,
             :ShowWhatsapp,:ShowDiscord,:ShowEmail,:ShowStats,:ShowGuild,:ShowOnline,
             :Featured,:Active,:SortOrder)
        ",$d);
    }

    public function update(int $id,array $post): void
    {
        if($id<=0) throw new \RuntimeException('Miembro inválido.');

        $d=$this->memberData($post);
        $d['StaffId']=$id;

        $this->cmsDb->execute("
            UPDATE ".self::MEMBERS." SET
                CharacterName=:CharacterName,
                RankName=:RankName,
                RankColor=:RankColor,
                Department=:Department,
                ShortDescription=:ShortDescription,
                Biography=:Biography,
                AvatarUrl=:AvatarUrl,
                Country=:Country,
                Languages=:Languages,
                Timezone=:Timezone,
                Schedule=:Schedule,
                ContactPhone=:ContactPhone,
                ContactWhatsapp=:ContactWhatsapp,
                ContactDiscord=:ContactDiscord,
                ContactEmail=:ContactEmail,
                ContactWebsite=:ContactWebsite,
                PublicStatus=:PublicStatus,
                ShowPhone=:ShowPhone,
                ShowWhatsapp=:ShowWhatsapp,
                ShowDiscord=:ShowDiscord,
                ShowEmail=:ShowEmail,
                ShowStats=:ShowStats,
                ShowGuild=:ShowGuild,
                ShowOnline=:ShowOnline,
                Featured=:Featured,
                Active=:Active,
                SortOrder=:SortOrder,
                UpdatedAt=SYSUTCDATETIME()
            WHERE StaffId=:StaffId
        ",$d);
    }

    public function toggle(int $id): void
    {
        $this->cmsDb->execute(
            "UPDATE ".self::MEMBERS."
             SET Active=CASE WHEN Active=1 THEN 0 ELSE 1 END,
                 UpdatedAt=SYSUTCDATETIME()
             WHERE StaffId=:id",
            ['id'=>$id]
        );
    }

    public function delete(int $id): void
    {
        $this->cmsDb->execute(
            "DELETE FROM ".self::MEMBERS." WHERE StaffId=:id",
            ['id'=>$id]
        );
    }

    public function diagnostics(): array
    {
        $tables=['Character','GuildMember','Guild','MEMB_STAT'];
        $out=[
            'cms_staff_settings'=>true,
            'cms_staff_members'=>true,
        ];

        foreach($tables as $table){
            try{
                $row=$this->muDb->fetchOne(
                    "SELECT CASE WHEN OBJECT_ID('dbo.".$table."','U') IS NULL
                     THEN 0 ELSE 1 END Available"
                );
                $out[$table]=(int)($row['Available']??0)===1;
            }catch(\Throwable){
                $out[$table]=false;
            }
        }

        return $out;
    }

    public function assetUrl(string $path): string
    {
        $path=trim(str_replace('\\','/',$path));
        if($path==='') return '';

        if(preg_match('#^https://#i',$path)) return $path;
        if(str_starts_with($path,'/')) return $path;

        if(str_starts_with($path,'assets/')){
            $parts=explode('/',$path);
            foreach($parts as $part){
                if($part==='..') return '';
            }
            return '/module-assets/staff-profesional/'.
                implode('/',array_map('rawurlencode',$parts));
        }

        return '';
    }

    public static function className(int $class): string
    {
        return match($class){
            0=>'Dark Wizard',
            1=>'Soul Master',
            16=>'Dark Knight',
            17=>'Blade Knight',
            32=>'Fairy Elf',
            33=>'Muse Elf',
            48=>'Magic Gladiator',
            default=>'Clase '.$class,
        };
    }

    public static function mapName(int $map): string
    {
        return match($map){
            0=>'Lorencia',
            1=>'Dungeon',
            2=>'Devias',
            3=>'Noria',
            4=>'Lost Tower',
            6=>'Arena',
            7=>'Atlans',
            8=>'Tarkan',
            10=>'Icarus',
            30=>'Valley of Loren',
            31=>'Land of Trials',
            default=>'Mapa '.$map,
        };
    }

    private function memberData(array $p): array
    {
        $name=$this->text((string)($p['CharacterName']??''),50);
        if($name==='') throw new \RuntimeException('Selecciona un personaje.');

        $char=$this->muDb->fetchOne(
            "SELECT TOP 1 Name FROM Character WHERE LTRIM(RTRIM(Name))=:name",
            ['name'=>$name]
        );
        if(!$char) throw new \RuntimeException('El personaje no existe en Character.');

        $status=(string)($p['PublicStatus']??'available');
        if(!in_array($status,['available','busy','away','offline'],true)) $status='available';

        return [
            'CharacterName'=>$name,
            'RankName'=>$this->text((string)($p['RankName']??'Game Master'),80),
            'RankColor'=>$this->color((string)($p['RankColor']??'#d4af37'),'#d4af37'),
            'Department'=>$this->text((string)($p['Department']??''),100),
            'ShortDescription'=>$this->text((string)($p['ShortDescription']??''),255),
            'Biography'=>$this->text((string)($p['Biography']??''),5000),
            'AvatarUrl'=>$this->assetOrUrl((string)($p['AvatarUrl']??'')),
            'Country'=>$this->text((string)($p['Country']??''),80),
            'Languages'=>$this->text((string)($p['Languages']??''),150),
            'Timezone'=>$this->text((string)($p['Timezone']??''),80),
            'Schedule'=>$this->text((string)($p['Schedule']??''),150),
            'ContactPhone'=>$this->text((string)($p['ContactPhone']??''),50),
            'ContactWhatsapp'=>$this->text((string)($p['ContactWhatsapp']??''),80),
            'ContactDiscord'=>$this->text((string)($p['ContactDiscord']??''),100),
            'ContactEmail'=>$this->email((string)($p['ContactEmail']??'')),
            'ContactWebsite'=>$this->url((string)($p['ContactWebsite']??'')),
            'PublicStatus'=>$status,
            'ShowPhone'=>isset($p['ShowPhone'])?1:0,
            'ShowWhatsapp'=>isset($p['ShowWhatsapp'])?1:0,
            'ShowDiscord'=>isset($p['ShowDiscord'])?1:0,
            'ShowEmail'=>isset($p['ShowEmail'])?1:0,
            'ShowStats'=>isset($p['ShowStats'])?1:0,
            'ShowGuild'=>isset($p['ShowGuild'])?1:0,
            'ShowOnline'=>isset($p['ShowOnline'])?1:0,
            'Featured'=>isset($p['Featured'])?1:0,
            'Active'=>isset($p['Active'])?1:0,
            'SortOrder'=>max(0,min(9999,(int)($p['SortOrder']??0))),
        ];
    }

    private function text(string $v,int $max): string
    {
        return mb_substr(trim(str_replace(["\0","\r"],'',$v)),0,$max);
    }

    private function color(string $v,string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/',trim($v)) ? strtolower(trim($v)) : $fallback;
    }

    private function email(string $v): string
    {
        $v=trim($v);
        return $v==='' ? '' : (filter_var($v,FILTER_VALIDATE_EMAIL) ? mb_substr($v,0,150) : '');
    }

    private function url(string $v): string
    {
        $v=trim($v);
        if($v==='') return '';
        return filter_var($v,FILTER_VALIDATE_URL) && preg_match('#^https://#i',$v)
            ? mb_substr($v,0,500)
            : '';
    }

    private function assetOrUrl(string $v): string
    {
        $v=trim(str_replace('\\','/',$v));
        if($v==='') return '';
        if(str_starts_with($v,'assets/') && !str_contains('/'.$v.'/','/../')) return mb_substr($v,0,500);
        return $this->url($v);
    }
}
