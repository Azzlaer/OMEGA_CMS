<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaInventoryViewerService
{
    private const SETTINGS='cms_inventory_viewer_settings';
    private const CATALOG='MUCore_Shop_Items';

    private array $settingsCache=[];

    public function __construct(private readonly Database $db)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.".self::SETTINGS."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::SETTINGS."(
                    SettingKey VARCHAR(120) NOT NULL PRIMARY KEY,
                    SettingValue NVARCHAR(MAX) NULL,
                    UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
                )
            END
        ");

        /*
         * Conservamos MUCore_Shop_Items porque el WebEngine original y el
         * Market v0.4.4 usan este catálogo como fuente canónica de nombres X/Y.
         * Si ya existe, Omega no la reemplaza.
         */
        $this->db->execute("
            IF OBJECT_ID('dbo.".self::CATALOG."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::CATALOG."(
                    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    name NVARCHAR(80) NULL,
                    i_type INT NULL,
                    i_id INT NULL,
                    credits INT NULL,
                    serial INT NULL,
                    i_luck_option INT NULL,
                    i_skill_option INT NULL,
                    size_x INT NULL,
                    size_y INT NULL,
                    i_default_durability INT NULL,
                    i_maximum_durability INT NULL,
                    category INT NULL,
                    i_max_excelent_option INT NULL,
                    i_max_option INT NULL,
                    i_max_level INT NULL,
                    i_refined_option INT NULL,
                    i_harmony_option INT NULL,
                    i_socket_option INT NULL,
                    i_option INT NULL,
                    i_exc_option INT NULL,
                    class_requirement VARCHAR(MAX) NULL,
                    ancient_id INT NULL,
                    i_stock NVARCHAR(25) NULL,
                    i_image NVARCHAR(80) NULL,
                    i_sell INT NULL,
                    i_stick_level INT NULL,
                    exc_anc INT NULL
                );
                CREATE INDEX IX_MUCore_Shop_Items_TypeId
                    ON dbo.".self::CATALOG."(i_type,i_id);
            END
        ");

        foreach($this->defaults() as $key=>$value){
            $exists=$this->db->fetchOne(
                "SELECT TOP 1 SettingKey FROM ".self::SETTINGS." WHERE SettingKey=:k",
                ['k'=>$key]
            );
            if(!$exists){
                $this->db->execute(
                    "INSERT INTO ".self::SETTINGS."(SettingKey,SettingValue)
                     VALUES(:k,:v)",
                    ['k'=>$key,'v'=>$value]
                );
            }
        }

        $count=$this->db->fetchOne("SELECT COUNT(*) Total FROM ".self::CATALOG);
        if((int)($count['Total']??0)===0){
            $this->seedCatalog();
        }
    }

    public function defaults(): array
    {
        return [
            'enabled'=>'1',
            'visual_style'=>'classic',
            'show_inventory'=>'1',
            'show_warehouse'=>'1',
            'show_stats'=>'1',
            'show_guild'=>'1',
            'show_online'=>'1',
            'show_serial'=>'0',
            'show_map'=>'1',
            'show_pk'=>'0',
            'item_bytes'=>'16',
            'inventory_slots'=>'120',
            'warehouse_slots'=>'120',
            'grid_columns'=>'8',
            'cell_size'=>'32',
            'allow_online_view'=>'0',
            'title'=>'Inventario y Baúl',
            'subtitle'=>'Consulta de forma segura los personajes, equipo, inventario y baúl de tu cuenta.',
            'accent_color'=>'#d4af37',
            'background_image'=>'',
            'security_notice'=>'Este módulo es únicamente de lectura. No permite mover, vender ni eliminar objetos.',
        ];
    }

    public function settings(): array
    {
        if($this->settingsCache) return $this->settingsCache;
        $out=$this->defaults();
        foreach($this->db->fetchAll(
            "SELECT SettingKey,SettingValue FROM ".self::SETTINGS
        ) as $row){
            $out[(string)$row['SettingKey']]=(string)($row['SettingValue']??'');
        }
        return $this->settingsCache=$out;
    }

    public function saveSettings(array $p): void
    {
        $style=(string)($p['visual_style']??'classic');
        if(!in_array($style,['classic','modern'],true)) $style='classic';

        $data=[
            'enabled'=>isset($p['enabled'])?'1':'0',
            'visual_style'=>$style,
            'show_inventory'=>isset($p['show_inventory'])?'1':'0',
            'show_warehouse'=>isset($p['show_warehouse'])?'1':'0',
            'show_stats'=>isset($p['show_stats'])?'1':'0',
            'show_guild'=>isset($p['show_guild'])?'1':'0',
            'show_online'=>isset($p['show_online'])?'1':'0',
            'show_serial'=>isset($p['show_serial'])?'1':'0',
            'show_map'=>isset($p['show_map'])?'1':'0',
            'show_pk'=>isset($p['show_pk'])?'1':'0',
            'allow_online_view'=>isset($p['allow_online_view'])?'1':'0',
            /*
             * 0.97k canónico: 16 bytes, 120 bloques.
             * Permitimos editar sólo dentro de límites razonables para diagnóstico.
             */
            'item_bytes'=>(string)max(16,min(32,(int)($p['item_bytes']??16))),
            'inventory_slots'=>(string)max(64,min(240,(int)($p['inventory_slots']??120))),
            'warehouse_slots'=>(string)max(60,min(240,(int)($p['warehouse_slots']??120))),
            'grid_columns'=>(string)max(4,min(12,(int)($p['grid_columns']??8))),
            'cell_size'=>(string)max(24,min(64,(int)($p['cell_size']??32))),
            'title'=>$this->text((string)($p['title']??''),150),
            'subtitle'=>$this->text((string)($p['subtitle']??''),500),
            'accent_color'=>$this->color((string)($p['accent_color']??'#d4af37'),'#d4af37'),
            'background_image'=>$this->httpsUrl((string)($p['background_image']??'')),
            'security_notice'=>$this->text((string)($p['security_notice']??''),800),
        ];

        foreach($data as $k=>$v){
            $this->db->execute(
                "UPDATE ".self::SETTINGS."
                 SET SettingValue=:v,UpdatedAt=SYSUTCDATETIME()
                 WHERE SettingKey=:k",
                ['v'=>$v,'k'=>$k]
            );
        }

        $this->settingsCache=[];
    }

    public function seedCatalog(): int
    {
        $file=__DIR__.'/catalog.json';
        if(!is_file($file)) throw new \RuntimeException('No se encontró catalog.json.');

        $rows=json_decode((string)file_get_contents($file),true);
        if(!is_array($rows)) throw new \RuntimeException('catalog.json no es válido.');

        /*
         * Sólo rellenamos pares type/id inexistentes. No destruimos un catálogo
         * MUCore existente ni sus posibles personalizaciones.
         */
        $inserted=0;
        $this->db->transaction(function(Database $db) use($rows,&$inserted): void {
            foreach($rows as $r){
                $type=(int)($r['i_type']??0);
                $id=(int)($r['i_id']??0);
                $level=(int)($r['i_stick_level']??0);

                $exists=$db->fetchOne(
                    "SELECT TOP 1 id FROM ".self::CATALOG."
                     WHERE i_type=:t AND i_id=:i AND ISNULL(i_stick_level,0)=:l",
                    ['t'=>$type,'i'=>$id,'l'=>$level]
                );
                if($exists) continue;

                $db->execute(
                    "INSERT INTO ".self::CATALOG."
                     (name,i_type,i_id,size_x,size_y,i_socket_option,i_harmony_option,i_stick_level)
                     VALUES(:name,:type,:id,:x,:y,:socket,:harmony,:level)",
                    [
                        'name'=>(string)($r['name']??('Item '.$type.':'.$id)),
                        'type'=>$type,'id'=>$id,
                        'x'=>max(1,(int)($r['size_x']??1)),
                        'y'=>max(1,(int)($r['size_y']??1)),
                        'socket'=>(int)($r['i_socket_option']??0),
                        'harmony'=>(int)($r['i_harmony_option']??0),
                        'level'=>$level
                    ]
                );
                $inserted++;
            }
        });

        return $inserted;
    }

    public function characters(string $account): array
    {
        $res=$this->firstColumn('Character',['ResetCount','Resets','Reset']);
        $mr=$this->firstColumn('Character',['GrandResetCount','GrandResets','GrandReset','MasterResets','MasterReset']);
        $ml=$this->firstColumn('Character',['MasterLevel','MLevel']);
        $lead=$this->firstColumn('Character',['Leadership']);

        $sql="SELECT
                LTRIM(RTRIM(Name)) Name,
                Class,cLevel,LevelUpPoint,Strength,Dexterity,Vitality,Energy,
                ".($lead ? "[{$lead}] Leadership" : "0 Leadership").",
                Money,PkLevel,MapNumber,MapPosX,MapPosY,
                ".($res ? "[{$res}] Resets" : "0 Resets").",
                ".($mr ? "[{$mr}] MasterResets" : "0 MasterResets").",
                ".($ml ? "[{$ml}] MasterLevel" : "0 MasterLevel")."
              FROM Character
              WHERE LTRIM(RTRIM(AccountID))=:a
              ORDER BY Name";

        return $this->db->fetchAll($sql,['a'=>$account]);
    }

    public function character(string $account,string $name): array
    {
        $base=null;
        foreach($this->characters($account) as $c){
            if(strcasecmp((string)$c['Name'],$name)===0){
                $base=$c; break;
            }
        }
        if(!$base) throw new \RuntimeException('Personaje inválido.');

        $row=$this->db->fetchOne(
            "SELECT Inventory
             FROM Character
             WHERE LTRIM(RTRIM(AccountID))=:a
               AND LTRIM(RTRIM(Name))=:n",
            ['a'=>$account,'n'=>$name]
        );
        $base['Inventory']=$row['Inventory']??null;

        try{
            $g=$this->db->fetchOne(
                "SELECT TOP 1 gm.G_Name,g.G_Master,g.G_Score
                 FROM GuildMember gm
                 LEFT JOIN Guild g ON g.G_Name=gm.G_Name
                 WHERE LTRIM(RTRIM(gm.Name))=:n",
                ['n'=>$name]
            );
            if($g) $base=array_merge($base,$g);
        }catch(\Throwable){}

        try{
            $o=$this->db->fetchOne(
                "SELECT TOP 1 ms.ConnectStat
                 FROM Character c
                 LEFT JOIN MEMB_STAT ms ON ms.memb___id=c.AccountID
                 WHERE LTRIM(RTRIM(c.Name))=:n
                   AND LTRIM(RTRIM(c.AccountID))=:a",
                ['n'=>$name,'a'=>$account]
            );
            $base['IsOnline']=(int)($o['ConnectStat']??0);
        }catch(\Throwable){
            $base['IsOnline']=0;
        }

        return $base;
    }

    public function warehouse(string $account): array
    {
        $r=$this->db->fetchOne(
            "SELECT TOP 1 Items,Money
             FROM warehouse
             WHERE LTRIM(RTRIM(AccountID))=:a",
            ['a'=>$account]
        );

        return $r ?: ['Items'=>null,'Money'=>0];
    }

    public function decodeBlob(mixed $blob,int $slots,int $bytes): array
    {
        if($blob===null) return [];
        if(is_resource($blob)) $blob=stream_get_contents($blob);
        if(!is_string($blob)) return [];

        $items=[];
        for($i=0;$i<$slots;$i++){
            $raw=substr($blob,$i*$bytes,$bytes);
            if(strlen($raw)<$bytes) break;

            $hex=strtoupper(bin2hex($raw));
            if($hex===str_repeat('F',$bytes*2) || $hex===str_repeat('0',$bytes*2)) continue;

            $decoded=$this->decodeItem($hex);
            if($decoded){
                $decoded['slot']=$i;
                $items[]=$decoded;
            }
        }
        return $items;
    }

    /**
     * API compatible con la idea del Inventory Viewer WebEngine.
     * Market Manager podrá reutilizar type/id/name/x/y/flags.
     */
    public function decodeItem(string $hex): ?array
    {
        $hex=strtoupper(preg_replace('/[^A-F0-9]/i','',$hex)??'');
        if(strlen($hex)<32) return null;
        $hex=substr($hex,0,32);

        $id=hexdec(substr($hex,0,2));
        $b=hexdec(substr($hex,2,2));
        $dur=hexdec(substr($hex,4,2));
        $serial=substr($hex,6,8);
        $exc=hexdec(substr($hex,14,2));
        $anc=hexdec(substr($hex,16,2));
        $type=hexdec(substr($hex,18,1));
        $ref=hexdec(substr($hex,19,1));
        $harm=hexdec(substr($hex,20,1));
        $hval=hexdec(substr($hex,21,1));

        $skill=0;$luck=0;
        if($b>127){$skill=1;$b-=128;}
        $level=(int)floor($b/8);$b-=$level*8;
        if($b>3){$luck=1;$b-=4;}
        if($exc>=64){$b+=4;$exc-=64;}

        $excellent=$exc;
        if($anc===5) $anc=1;
        elseif($anc===9) $anc=2;

        $cat=$this->db->fetchOne(
            "SELECT TOP 1 name,size_x,size_y,i_socket_option,i_harmony_option
             FROM ".self::CATALOG."
             WHERE i_id=:id AND i_type=:type
             ORDER BY CASE WHEN ISNULL(i_stick_level,0)=:level THEN 0 ELSE 1 END,id",
            ['id'=>$id,'type'=>$type,'level'=>$level]
        ) ?: [
            'name'=>'Objeto '.$type.':'.$id,
            'size_x'=>1,'size_y'=>1,'i_socket_option'=>0,'i_harmony_option'=>0
        ];

        $image=$this->imageName($id,$type,$anc>0?2:($excellent>0?1:0),$level);

        return [
            'id'=>$id,
            'index'=>$id,
            'type'=>$type,
            'name'=>(string)$cat['name'],
            'x'=>max(1,(int)$cat['size_x']),
            'y'=>max(1,(int)$cat['size_y']),
            'level'=>$level,
            'option'=>$b,
            'durability'=>$dur,
            'serial'=>$serial,
            'skill'=>$skill,
            'luck'=>$luck,
            'excellent'=>$excellent,
            'ancient'=>$anc,
            'refinery'=>$ref,
            'harmony_type'=>$harm,
            'harmony_value'=>$hval,
            'socket_capable'=>(int)($cat['i_socket_option']??0)>0 ? 1 : 0,
            'harmony_capable'=>(int)($cat['i_harmony_option']??0)>0 ? 1 : 0,
            'hex'=>$hex,
            'image'=>$image,
            'image_url'=>$image!=='' ? '/module-assets/inventory-vault-viewer/assets/items/'.rawurlencode($image) : '',
            'decoder_source'=>'omega_inventory_viewer'
        ];
    }

    public function diagnostics(): array
    {
        $out=[];

        foreach([['Character','Inventory'],['warehouse','Items']] as [$table,$column]){
            try{
                $row=$this->db->fetchOne(
                    "SELECT TOP 1
                        TYPE_NAME(c.user_type_id) DataType,
                        c.max_length MaxLength
                     FROM sys.columns c
                     WHERE c.object_id=OBJECT_ID('dbo.".$table."')
                       AND c.name=:column",
                    ['column'=>$column]
                );
                $out[$table.'.'.$column]=$row ?: null;
            }catch(\Throwable){
                $out[$table.'.'.$column]=null;
            }
        }

        try{
            $out['inventory_samples']=$this->db->fetchAll(
                "SELECT TOP 5 LTRIM(RTRIM(Name)) Name,DATALENGTH(Inventory) Bytes
                 FROM Character WHERE Inventory IS NOT NULL
                 ORDER BY DATALENGTH(Inventory) DESC"
            );
        }catch(\Throwable){$out['inventory_samples']=[];}

        try{
            $out['warehouse_samples']=$this->db->fetchAll(
                "SELECT TOP 5 LTRIM(RTRIM(AccountID)) AccountID,DATALENGTH(Items) Bytes
                 FROM warehouse WHERE Items IS NOT NULL
                 ORDER BY DATALENGTH(Items) DESC"
            );
        }catch(\Throwable){$out['warehouse_samples']=[];}

        $c=$this->db->fetchOne("SELECT COUNT(*) Total FROM ".self::CATALOG);
        $out['catalog_items']=(int)($c['Total']??0);
        $out['provided_images']=$this->providedImageCount();

        return $out;
    }

    public function providedImageCount(): int
    {
        $files=glob(__DIR__.'/assets/items/*.{gif,GIF,jpg,JPG,jpeg,JPEG,png,PNG}',GLOB_BRACE);
        return is_array($files)?count($files):0;
    }

    public function assetUrl(string $path): string
    {
        $path=trim(str_replace('\\','/',$path));
        if($path==='') return '';
        if(preg_match('#^https://#i',$path)) return $path;
        if(str_starts_with($path,'assets/')){
            if(str_contains('/'.$path.'/','/../')) return '';
            return '/module-assets/inventory-vault-viewer/'.
                implode('/',array_map('rawurlencode',explode('/',$path)));
        }
        return '';
    }

    private function imageName(int $id,int $type,int $special,int $level): string
    {
        $dir=__DIR__.'/assets/items/';
        $prefix=$special===1?'10':($special===2?'01':'00');
        $itype=$type*16;

        if($id>63){
            $nxt=strtoupper(dechex($id));
        }elseif($id>31){
            $nxt='F9';
            $id-=32;
        }else{
            $nxt='00';
        }

        if($itype<128){
            $tipaj='00';
        }else{
            $tipaj='80';
            $itype-=128;
        }

        $id+=$itype;
        $itype+=$id;
        $code=sprintf('%02X',$itype);

        $specialBase=$prefix.$code.$tipaj.$nxt;
        $normalBase='00'.$code.$tipaj.$nxt;
        $candidates=[];

        for($i=max(0,$level);$i>=0;$i--){
            $suffix=sprintf('%02X',$i);
            $candidates[]=$specialBase.$suffix.'.gif';
            $candidates[]=$normalBase.$suffix.'.gif';
        }

        $candidates[]=$specialBase.'.gif';
        $candidates[]=$normalBase.'.gif';

        if(is_dir($dir)){
            $scan=scandir($dir) ?: [];
            $map=[];
            foreach($scan as $file){
                $map[strtoupper($file)]=$file;
            }
            foreach(array_unique($candidates) as $candidate){
                $key=strtoupper($candidate);
                if(isset($map[$key])) return $map[$key];
            }

            $needle=strtoupper($code.$tipaj.$nxt);
            foreach($scan as $file){
                if(str_ends_with(strtoupper($file),'.GIF') &&
                   str_contains(strtoupper($file),$needle)){
                    return $file;
                }
            }
        }

        return '';
    }

    private function firstColumn(string $table,array $candidates): ?string
    {
        foreach($candidates as $column){
            $r=$this->db->fetchOne(
                "SELECT COUNT(*) Total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME=:t AND COLUMN_NAME=:c",
                ['t'=>$table,'c'=>$column]
            );
            if((int)($r['Total']??0)>0) return $column;
        }
        return null;
    }

    private function color(string $v,string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/',trim($v)) ? strtolower(trim($v)) : $fallback;
    }

    private function httpsUrl(string $v): string
    {
        $v=trim($v);
        if($v==='') return '';
        return filter_var($v,FILTER_VALIDATE_URL) && preg_match('#^https://#i',$v)
            ? mb_substr($v,0,600)
            : '';
    }

    private function text(string $v,int $max): string
    {
        return mb_substr(trim(str_replace(["\0","\r"],'',$v)),0,$max);
    }

    public static function className(int $c): string
    {
        return match($c){
            0=>'Dark Wizard',1=>'Soul Master',
            16=>'Dark Knight',17=>'Blade Knight',
            32=>'Fairy Elf',33=>'Muse Elf',
            48=>'Magic Gladiator',
            default=>'Clase '.$c,
        };
    }
}
