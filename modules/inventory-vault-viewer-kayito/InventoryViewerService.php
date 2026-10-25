<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaInventoryViewerService
{
    private const SETTINGS='cms_inventory_viewer_kayito_settings';
    private const CATALOG='MUCore_Shop_Items';

    private array $settingsCache=[];
    private ?array $itemTxtCatalogCache=null;
    private ?array $itemTxtMetaCache=null;
    private ?array $customItemCatalogCache=null;

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

        $this->db->execute("
            IF OBJECT_ID('dbo.cms_inventory_viewer_kayito_custom_items','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_inventory_viewer_kayito_custom_items(
                    ItemType INT NOT NULL,
                    ItemIndex INT NOT NULL,
                    ItemName NVARCHAR(120) NOT NULL,
                    Width INT NOT NULL DEFAULT 1,
                    Height INT NOT NULL DEFAULT 1,
                    ImagePath NVARCHAR(500) NULL,
                    Notes NVARCHAR(500) NULL,
                    UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                    CONSTRAINT PK_cms_inventory_viewer_kayito_custom_items PRIMARY KEY(ItemType,ItemIndex)
                )
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
            'show_ext_warehouse'=>'1',
            'show_stats'=>'1',
            'show_guild'=>'1',
            'show_online'=>'1',
            'show_serial'=>'0',
            'show_raw_debug'=>'0',
            'strict_catalog'=>'1',
            'strict_images'=>'1',
            'show_unknown_items'=>'0',
            'catalog_source'=>'item_txt',
            'show_map'=>'1',
            'show_pk'=>'0',
            'item_bytes'=>'10',
            'inventory_slots'=>'76',
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
            'show_ext_warehouse'=>isset($p['show_ext_warehouse'])?'1':'0',
            'show_stats'=>isset($p['show_stats'])?'1':'0',
            'show_guild'=>isset($p['show_guild'])?'1':'0',
            'show_online'=>isset($p['show_online'])?'1':'0',
            'show_serial'=>isset($p['show_serial'])?'1':'0',
            'show_raw_debug'=>isset($p['show_raw_debug'])?'1':'0',
            'strict_catalog'=>isset($p['strict_catalog'])?'1':'0',
            'strict_images'=>isset($p['strict_images'])?'1':'0',
            'show_unknown_items'=>isset($p['show_unknown_items'])?'1':'0',
            'catalog_source'=>'item_txt',
            'show_map'=>isset($p['show_map'])?'1':'0',
            'show_pk'=>isset($p['show_pk'])?'1':'0',
            'allow_online_view'=>isset($p['allow_online_view'])?'1':'0',
            /*
             * 0.97k canónico: 16 bytes, 120 bloques.
             * Permitimos editar sólo dentro de límites razonables para diagnóstico.
             */
            'item_bytes'=>(string)max(10,min(32,(int)($p['item_bytes']??10))),
            'inventory_slots'=>(string)max(76,min(240,(int)($p['inventory_slots']??76))),
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

    public function customItems(): array
    {
        if($this->customItemCatalogCache!==null){
            return $this->customItemCatalogCache;
        }

        $rows=[];
        try{
            $rows=$this->db->fetchAll(
                "SELECT ItemType,ItemIndex,ItemName,Width,Height,ImagePath,Notes,UpdatedAt
                 FROM dbo.cms_inventory_viewer_kayito_custom_items
                 ORDER BY ItemType,ItemIndex"
            );
        }catch(\Throwable){
            $rows=[];
        }

        $map=[];
        foreach($rows as $row){
            $key=(int)$row['ItemType'].':'.(int)$row['ItemIndex'];
            $map[$key]=[
                'type'=>(int)$row['ItemType'],
                'index'=>(int)$row['ItemIndex'],
                'name'=>(string)$row['ItemName'],
                'width'=>max(1,(int)$row['Width']),
                'height'=>max(1,(int)$row['Height']),
                'image_path'=>trim((string)($row['ImagePath']??'')),
                'notes'=>trim((string)($row['Notes']??'')),
                'updated_at'=>(string)($row['UpdatedAt']??''),
            ];
        }

        return $this->customItemCatalogCache=$map;
    }

    public function customItemLookup(int $type,int $index): ?array
    {
        $map=$this->customItems();
        $key=$type.':'.$index;
        return isset($map[$key]) && is_array($map[$key]) ? $map[$key] : null;
    }

    public function saveCustomItem(array $data): void
    {
        $type=max(0,(int)($data['type']??$data['custom_type']??0));
        $index=max(0,(int)($data['id']??$data['index']??$data['custom_index']??0));
        $name=$this->text((string)($data['name']??$data['custom_name']??''),120);
        $width=max(1,min(8,(int)($data['width']??$data['custom_width']??1)));
        $height=max(1,min(8,(int)($data['height']??$data['custom_height']??1)));
        $image=$this->customImagePath((string)($data['image']??$data['custom_image']??''));
        $notes=$this->text((string)($data['notes']??$data['custom_notes']??''),500);

        if($name===''){
            throw new \RuntimeException('Debes indicar un nombre para el item personalizado.');
        }

        $exists=$this->db->fetchOne(
            "SELECT TOP 1 ItemType FROM dbo.cms_inventory_viewer_kayito_custom_items WHERE ItemType=:t AND ItemIndex=:i",
            ['t'=>$type,'i'=>$index]
        );

        if($exists){
            $this->db->execute(
                "UPDATE dbo.cms_inventory_viewer_kayito_custom_items
                 SET ItemName=:n,Width=:w,Height=:h,ImagePath=:p,Notes=:notes,UpdatedAt=SYSUTCDATETIME()
                 WHERE ItemType=:t AND ItemIndex=:i",
                ['n'=>$name,'w'=>$width,'h'=>$height,'p'=>$image,'notes'=>$notes,'t'=>$type,'i'=>$index]
            );
        }else{
            $this->db->execute(
                "INSERT INTO dbo.cms_inventory_viewer_kayito_custom_items(ItemType,ItemIndex,ItemName,Width,Height,ImagePath,Notes)
                 VALUES(:t,:i,:n,:w,:h,:p,:notes)",
                ['t'=>$type,'i'=>$index,'n'=>$name,'w'=>$width,'h'=>$height,'p'=>$image,'notes'=>$notes]
            );
        }

        $this->customItemCatalogCache=null;
    }

    public function deleteCustomItem(int $type,int $index): void
    {
        $this->db->execute(
            "DELETE FROM dbo.cms_inventory_viewer_kayito_custom_items WHERE ItemType=:t AND ItemIndex=:i",
            ['t'=>$type,'i'=>$index]
        );
        $this->customItemCatalogCache=null;
    }

    public function scanAllItemsSummary(int $limit=1000): array
    {
        $summary=[];

        $append=function(array &$summary,array $item,string $storage,string $owner,string $account): void {
            $key=(int)$item['type'].':'.(int)$item['id'];

            if(!isset($summary[$key])){
                $summary[$key]=[
                    'type'=>(int)$item['type'],
                    'id'=>(int)$item['id'],
                    'absolute_index'=>(int)($item['absolute_index']??0),
                    'name'=>(string)($item['name']??''),
                    'catalog_source'=>(string)($item['catalog_source']??'unknown'),
                    'catalog_found'=>!empty($item['catalog_found'])?1:0,
                    'x'=>(int)($item['x']??1),
                    'y'=>(int)($item['y']??1),
                    'count'=>0,
                    'from_character'=>0,
                    'from_warehouse'=>0,
                    'from_extwarehouse'=>0,
                    'sample_hex'=>(string)($item['hex']??''),
                    'sample_level'=>(int)($item['level']??0),
                    'sample_owner'=>$owner,
                    'sample_account'=>$account,
                    'image_url'=>(string)($item['image_url']??''),
                ];
            }

            $summary[$key]['count']++;
            if($storage==='character') $summary[$key]['from_character']++;
            if($storage==='warehouse') $summary[$key]['from_warehouse']++;
            if($storage==='extwarehouse') $summary[$key]['from_extwarehouse']++;
        };

        try{
            $rows=$this->db->fetchAll(
                "SELECT LTRIM(RTRIM(ISNULL(AccountID,''))) AccountID,
                        LTRIM(RTRIM(ISNULL(Name,''))) Name,
                        Inventory
                 FROM Character
                 WHERE Inventory IS NOT NULL"
            );

            foreach($rows as $row){
                foreach($this->decodeBlob($row['Inventory']??null,76,10) as $item){
                    $append($summary,$item,'character',(string)($row['Name']??''),(string)($row['AccountID']??''));
                }
            }
        }catch(\Throwable){}

        try{
            $rows=$this->db->fetchAll(
                "SELECT LTRIM(RTRIM(ISNULL(AccountID,''))) AccountID,Items
                 FROM warehouse
                 WHERE Items IS NOT NULL"
            );

            foreach($rows as $row){
                foreach($this->decodeBlob($row['Items']??null,120,10) as $item){
                    $append($summary,$item,'warehouse','warehouse',(string)($row['AccountID']??''));
                }
            }
        }catch(\Throwable){}

        try{
            $rows=$this->db->fetchAll(
                "SELECT LTRIM(RTRIM(ISNULL(AccountID,''))) AccountID,
                        ISNULL(Number,0) Number,
                        Items
                 FROM ExtWarehouse
                 WHERE Items IS NOT NULL"
            );

            foreach($rows as $row){
                foreach($this->decodeBlob($row['Items']??null,120,10) as $item){
                    $append(
                        $summary,
                        $item,
                        'extwarehouse',
                        'Ext #'.(int)($row['Number']??0),
                        (string)($row['AccountID']??'')
                    );
                }
            }
        }catch(\Throwable){}

        $rows=array_values($summary);

        usort($rows,static function(array $a,array $b): int {
            $cmp=((int)$a['type'])<=>((int)$b['type']);
            if($cmp!==0) return $cmp;
            return ((int)$a['id'])<=>((int)$b['id']);
        });

        return array_slice($rows,0,max(1,$limit));
    }

    public function scanTotals(): array
    {
        $rows=$this->scanAllItemsSummary(5000);
        $occurrences=0;
        $knownOccurrences=0;
        $unknownOccurrences=0;
        $knownTypes=0;
        $unknownTypes=0;

        foreach($rows as $row){
            $count=(int)$row['count'];
            $occurrences+=$count;

            if(!empty($row['catalog_found'])){
                $knownTypes++;
                $knownOccurrences+=$count;
            }else{
                $unknownTypes++;
                $unknownOccurrences+=$count;
            }
        }

        return [
            'unique_types'=>count($rows),
            'occurrences'=>$occurrences,
            'known_types'=>$knownTypes,
            'unknown_types'=>$unknownTypes,
            'known_occurrences'=>$knownOccurrences,
            'unknown_occurrences'=>$unknownOccurrences,
            'coverage_percent'=>$occurrences>0
                ? round(($knownOccurrences/$occurrences)*100,2)
                : 100.0,
        ];
    }

    public function unknownItemSummary(int $limit=200): array
    {
        $summary=[];
        $append=static function(array &$summary,array $item,string $storage,string $owner,string $account): void {
            $key=(int)$item['type'].':'.(int)$item['id'];
            if(!isset($summary[$key])){
                $summary[$key]=[
                    'type'=>(int)$item['type'],
                    'id'=>(int)$item['id'],
                    'count'=>0,
                    'from_character'=>0,
                    'from_warehouse'=>0,
                    'from_extwarehouse'=>0,
                    'sample_hex'=>(string)($item['hex']??''),
                    'sample_owner'=>$owner,
                    'sample_account'=>$account,
                    'sample_level'=>(int)($item['level']??0),
                ];
            }
            $summary[$key]['count']++;
            if($storage==='character') $summary[$key]['from_character']++;
            if($storage==='warehouse') $summary[$key]['from_warehouse']++;
            if($storage==='extwarehouse') $summary[$key]['from_extwarehouse']++;
        };

        try{
            $rows=$this->db->fetchAll("SELECT LTRIM(RTRIM(ISNULL(AccountID,''))) AccountID, LTRIM(RTRIM(ISNULL(Name,''))) Name, Inventory FROM Character WHERE Inventory IS NOT NULL");
            foreach($rows as $row){
                foreach($this->decodeBlob($row['Inventory']??null,76,10) as $item){
                    if(!empty($item['catalog_found'])) continue;
                    $append($summary,$item,'character',(string)($row['Name']??''),(string)($row['AccountID']??''));
                }
            }
        }catch(\Throwable){}

        try{
            $rows=$this->db->fetchAll("SELECT LTRIM(RTRIM(ISNULL(AccountID,''))) AccountID, Items FROM warehouse WHERE Items IS NOT NULL");
            foreach($rows as $row){
                foreach($this->decodeBlob($row['Items']??null,120,10) as $item){
                    if(!empty($item['catalog_found'])) continue;
                    $append($summary,$item,'warehouse','warehouse',(string)($row['AccountID']??''));
                }
            }
        }catch(\Throwable){}

        try{
            $rows=$this->db->fetchAll("SELECT LTRIM(RTRIM(ISNULL(AccountID,''))) AccountID, ISNULL(Number,0) Number, Items FROM ExtWarehouse WHERE Items IS NOT NULL");
            foreach($rows as $row){
                foreach($this->decodeBlob($row['Items']??null,120,10) as $item){
                    if(!empty($item['catalog_found'])) continue;
                    $append($summary,$item,'extwarehouse','ext #'.(int)($row['Number']??0),(string)($row['AccountID']??''));
                }
            }
        }catch(\Throwable){}

        $rows=array_values($summary);
        usort($rows,static function(array $a,array $b): int {
            $cmp=((int)$b['count'])<=>((int)$a['count']);
            if($cmp!==0) return $cmp;
            $cmp=((int)$a['type'])<=>((int)$b['type']);
            if($cmp!==0) return $cmp;
            return ((int)$a['id'])<=>((int)$b['id']);
        });

        return array_slice($rows,0,max(1,$limit));
    }


    public function exportCustomItemsJson(): string
    {
        $items=array_values($this->customItems());

        $payload=[
            'format'=>'omega_inventory_custom_items',
            'version'=>1,
            'generated_at'=>gmdate('c'),
            'items'=>array_map(static function(array $row): array {
                return [
                    'type'=>(int)$row['type'],
                    'index'=>(int)$row['index'],
                    'name'=>(string)$row['name'],
                    'width'=>(int)$row['width'],
                    'height'=>(int)$row['height'],
                    'image'=>(string)$row['image_path'],
                    'notes'=>(string)$row['notes'],
                ];
            },$items),
        ];

        return (string)json_encode(
            $payload,
            JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        );
    }

    public function importCustomItemsJson(string $json): array
    {
        $json=trim($json);
        if($json===''){
            throw new \RuntimeException('El JSON de importación está vacío.');
        }

        $decoded=json_decode($json,true);

        if(!is_array($decoded)){
            throw new \RuntimeException('El JSON de importación no es válido.');
        }

        $items=$decoded['items']??$decoded;

        if(!is_array($items)){
            throw new \RuntimeException('No se encontró una lista de items en el JSON.');
        }

        $saved=0;
        $errors=[];

        foreach($items as $n=>$row){
            if(!is_array($row)){
                $errors[]='Entrada '.($n+1).': formato inválido.';
                continue;
            }

            try{
                $this->saveCustomItem([
                    'type'=>$row['type']??0,
                    'id'=>$row['index']??($row['id']??0),
                    'name'=>$row['name']??'',
                    'width'=>$row['width']??1,
                    'height'=>$row['height']??1,
                    'image'=>$row['image']??($row['image_path']??''),
                    'notes'=>$row['notes']??'',
                ]);
                $saved++;
            }catch(\Throwable $ex){
                $errors[]='Entrada '.($n+1).': '.$ex->getMessage();
            }
        }

        return ['saved'=>$saved,'errors'=>$errors];
    }

    public function imageCandidates(int $limit=500): array
    {
        $out=[];

        foreach($this->imageDirectories() as $dir){
            if(!is_dir($dir)) continue;

            foreach(scandir($dir) ?: [] as $file){
                if($file==='.' || $file==='..') continue;
                if(!preg_match('/\.(gif|png|jpe?g|webp)$/i',$file)) continue;

                $url=$this->publicImageUrl($file);
                if($url==='') continue;

                $out[strtoupper($file)]=[
                    'file'=>$file,
                    'url'=>$url
                ];

                if(count($out)>=$limit) break 2;
            }
        }

        ksort($out,SORT_NATURAL|SORT_FLAG_CASE);
        return array_values($out);
    }

    public function itemTxtCatalog(): array
    {
        if($this->itemTxtCatalogCache!==null){
            return $this->itemTxtCatalogCache;
        }

        $file=__DIR__.'/kayito_item_catalog.json';

        if(!is_file($file)){
            throw new \RuntimeException('No se encontró kayito_item_catalog.json.');
        }

        $decoded=json_decode((string)file_get_contents($file),true);

        if(!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])){
            throw new \RuntimeException('El catálogo Item.txt integrado no es válido.');
        }

        $map=[];

        foreach($decoded['items'] as $row){
            $type=(int)($row['type']??-1);
            $index=(int)($row['index']??-1);

            if($type<0 || $index<0){
                continue;
            }

            $map[$type.':'.$index]=$row;
        }

        $this->itemTxtMetaCache=[
            'source'=>(string)($decoded['source']??'Item.txt'),
            'sha256'=>(string)($decoded['sha256']??''),
            'count'=>count($map),
            'sections'=>(array)($decoded['sections']??[]),
            'max_item_type'=>(int)($decoded['max_item_type']??32),
            'overflow_count'=>(int)($decoded['overflow_count']??0)
        ];

        return $this->itemTxtCatalogCache=$map;
    }

    public function itemTxtMeta(): array
    {
        if($this->itemTxtMetaCache===null){
            $this->itemTxtCatalog();
        }

        return $this->itemTxtMetaCache ?? [];
    }

    public function itemTxtLookup(int $type,int $index): ?array
    {
        $catalog=$this->itemTxtCatalog();
        $key=$type.':'.$index;

        return isset($catalog[$key]) && is_array($catalog[$key])
            ? $catalog[$key]
            : null;
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

    public function extWarehouses(string $account): array
    {
        try{
            return $this->db->fetchAll(
                "SELECT
                    LTRIM(RTRIM(AccountID)) AccountID,
                    ISNULL(Number,0) Number,
                    Items,
                    ISNULL(Money,0) Money,
                    DATALENGTH(Items) ItemBytes
                 FROM ExtWarehouse
                 WHERE LTRIM(RTRIM(AccountID))=:a
                 ORDER BY ISNULL(Number,0)",
                ['a'=>$account]
            );
        }catch(\Throwable){
            return [];
        }
    }

    public function decodeBlob(mixed $blob,int $slots,int $bytes): array
    {
        if($blob===null) return [];
        if(is_resource($blob)) $blob=stream_get_contents($blob);
        if(!is_string($blob)) return [];

        $blobLength=strlen($blob);

        /*
         * MU 0.97k DB verificada por warehouse.sql:
         * 1200 bytes = 120 slots x 10 bytes.
         *
         * También dejamos auto-detección para no romper una base distinta.
         */
        if($blobLength===1200){
            $bytes=10;
            $slots=120;
        }elseif($blobLength===760){
            $bytes=10;
            $slots=76;
        }elseif($blobLength===1920){
            /*
             * La columna ExtWarehouse permite 1920 bytes, pero Kayito persiste
             * items DB en bloques de 10 bytes. Si aparecen 1920 bytes reales,
             * no se interpretan como 16-byte automáticamente.
             */
            $bytes=10;
            $slots=(int)floor($blobLength/10);
        }elseif($bytes>0 && $blobLength>0 && ($blobLength % $bytes)===0){
            $slots=(int)($blobLength/$bytes);
        }

        $items=[];
        for($i=0;$i<$slots;$i++){
            $raw=substr($blob,$i*$bytes,$bytes);
            if(strlen($raw)<$bytes) break;

            $hex=strtoupper(bin2hex($raw));
            if($hex===str_repeat('F',$bytes*2) || $hex===str_repeat('0',$bytes*2)) continue;

            $decoded=$this->decodeItem($hex);
            if($decoded){
                $decoded['slot']=$i;
                $decoded['storage_bytes']=$bytes;
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
        if(strlen($hex)<20) return null;

        /*
         * MU 0.97k Kayito - DBItemByteConvert / ConvertItemByte.
         * La estructura persistida en Character.Inventory, warehouse.Items y
         * los registros reales de ExtWarehouse es de 10 bytes.
         */
        $hex=substr($hex,0,20);
        $b=[];

        for($n=0;$n<10;$n++){
            $b[$n]=hexdec(substr($hex,$n*2,2));
        }

        if(
            $b[0]===0xFF &&
            (($b[7]&0x80)===0x80) &&
            (($b[9]&0xF0)===0xF0)
        ){
            return null;
        }

        /*
         * Fórmula exacta del GameServer:
         *
         * m_Index = lpMsg[0]
         *         | ((lpMsg[9] & 0xF0) * 32)
         *         | ((lpMsg[7] & 0x80) * 2);
         *
         * GET_ITEM(section,index) = section*512 + index.
         */
        $absoluteIndex=
            $b[0] |
            (($b[9] & 0xF0) * 32) |
            (($b[7] & 0x80) * 2);

        /*
         * Kayito 0.97k:
         * MAX_ITEM_TYPE = 32
         * GET_ITEM(section,index) = section*32 + index
         */
        $type=(int)floor($absoluteIndex/32);
        $id=$absoluteIndex%32;

        $skill=($b[1]>>7)&1;
        $luck=($b[1]>>2)&1;
        $level=($b[1]>>3)&15;
        $addOption=($b[1]&3) + (($b[7]&0x40)>>4);
        $durability=$b[2];
        $excellent=$b[7]&0x3F;

        $serial=sprintf(
            '%02X%02X%02X%02X',
            $b[3],$b[4],$b[5],$b[6]
        );

        $serialValid=!in_array(
            $serial,
            ['00000000','FFFFFFFF'],
            true
        );

        /*
         * Prioridad v2:
         * 1) Item.txt real del GameServer (autoridad)
         * 2) mapping manual SOLO si Item.txt no conoce ese Type:Index
         * 3) desconocido
         *
         * Esto evita que mappings viejos o erróneos oculten un item real.
         */
        $cat=$this->itemTxtLookup($type,$id);

        if(is_array($cat)){
            $catalogFound=true;
            $catalogSource='item_txt';
        }else{
            $custom=$this->customItemLookup($type,$id);

            if(is_array($custom)){
                $cat=[
                    'name'=>(string)$custom['name'],
                    'width'=>(int)$custom['width'],
                    'height'=>(int)$custom['height'],
                    'skill_id'=>0,
                    'have_serial'=>1,
                    'have_option'=>1,
                    'drop_item'=>0,
                    'source_line'=>0,
                    'image_path'=>(string)($custom['image_path']??''),
                    'notes'=>(string)($custom['notes']??'')
                ];
                $catalogFound=true;
                $catalogSource='custom';
            }else{
                $cat=[
                    'name'=>'Item desconocido '.$type.':'.$id,
                    'width'=>1,
                    'height'=>1,
                    'skill_id'=>0,
                    'have_serial'=>1,
                    'have_option'=>1,
                    'drop_item'=>0,
                    'source_line'=>0,
                    'image_path'=>'',
                    'notes'=>''
                ];
                $catalogFound=false;
                $catalogSource='unknown';
            }
        }

        $customImage=(string)($cat['image_path']??'');
        if($customImage!==''){
            $image='';
            $imageUrl=$this->assetUrl($customImage);
            if($imageUrl===''){
                $imageUrl=str_starts_with($customImage,'/module-assets/') ? $customImage : '';
            }
        }else{
            /* No adivinar imágenes de un Type:Index ausente del catálogo. */
            $image=$catalogFound
                ? $this->imageName($id,$type,$excellent>0?1:0,$level)
                : '';
            $imageUrl=$image!==''?$this->publicImageUrl($image):'';
        }

        return [
            'absolute_index'=>$absoluteIndex,
            'id'=>$id,
            'index'=>$id,
            'type'=>$type,
            'name'=>(string)$cat['name'],
            'x'=>max(1,(int)($cat['width']??1)),
            'y'=>max(1,(int)($cat['height']??1)),
            'level'=>$level,
            'option'=>$addOption,
            'durability'=>$durability,
            'serial'=>$serialValid?$serial:'',
            'skill'=>$skill,
            'luck'=>$luck,
            'excellent'=>$excellent,
            'ancient'=>0,
            'refinery'=>0,
            'harmony_type'=>0,
            'harmony_value'=>0,
            'socket_capable'=>0,
            'harmony_capable'=>0,
            'catalog_found'=>$catalogFound?1:0,
            'catalog_source'=>$catalogSource,
            'catalog_line'=>(int)($cat['source_line']??0),
            'item_skill_id'=>(int)($cat['skill_id']??0),
            'have_serial'=>(int)($cat['have_serial']??1),
            'have_option'=>(int)($cat['have_option']??1),
            'drop_item'=>(int)($cat['drop_item']??0),
            'hex'=>$hex,
            'storage_bytes'=>10,
            'byte7'=>sprintf('%02X',$b[7]),
            'byte9'=>sprintf('%02X',$b[9]),
            'object_key'=>$serialValid
                ? 'SERIAL:'.$serial
                : 'HEX:'.strtoupper(hash('sha256',$hex)),
            'image'=>$image,
            'image_url'=>$imageUrl,
            'custom_notes'=>(string)($cat['notes']??''),
            'decoder_source'=>'kayito_gameserver_dbitembyteconvert'
        ];
    }

    public function analyzeLayout(array $items,int $columns,int $rows,int $offset=0): array
    {
        $cells=array_fill(0,$columns*$rows,null);
        $warnings=[];
        $used=0;

        foreach($items as $idx=>$item){
            $slot=(int)($item['slot']??-1);
            if($slot<$offset) continue;

            $visual=$slot-$offset;
            if($visual<0 || $visual>=($columns*$rows)) continue;

            $x=$visual%$columns;
            $y=(int)floor($visual/$columns);
            $w=max(1,(int)($item['x']??1));
            $h=max(1,(int)($item['y']??1));

            if(($x+$w)>$columns || ($y+$h)>$rows){
                $warnings[]='Slot '.$slot.' ('.$item['type'].':'.$item['id'].
                    ') sale de los límites '.$w.'x'.$h.'.';
                continue;
            }

            for($dy=0;$dy<$h;$dy++){
                for($dx=0;$dx<$w;$dx++){
                    $cell=(($y+$dy)*$columns)+($x+$dx);

                    if($cells[$cell]!==null){
                        $warnings[]='Superposición: slot '.$slot.
                            ' comparte celda con slot '.$cells[$cell].'.';
                    }else{
                        $cells[$cell]=$slot;
                        $used++;
                    }
                }
            }
        }

        return [
            'used_cells'=>$used,
            'free_cells'=>max(0,($columns*$rows)-$used),
            'warnings'=>array_values(array_unique($warnings)),
            'valid'=>count($warnings)===0
        ];
    }

    public function diagnostics(): array
    {
        $out=[];

        foreach([['Character','Inventory'],['warehouse','Items'],['ExtWarehouse','Items']] as [$table,$column]){
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

        try{
            $out['extwarehouse_samples']=$this->db->fetchAll(
                "SELECT TOP 10
                    LTRIM(RTRIM(AccountID)) AccountID,
                    ISNULL(Number,0) Number,
                    DATALENGTH(Items) Bytes
                 FROM ExtWarehouse
                 WHERE Items IS NOT NULL
                 ORDER BY AccountID,Number"
            );
        }catch(\Throwable){$out['extwarehouse_samples']=[];}

        $c=$this->db->fetchOne("SELECT COUNT(*) Total FROM ".self::CATALOG);
        $out['legacy_mucore_catalog_items']=(int)($c['Total']??0);

        $itemTxt=$this->itemTxtMeta();
        $out['catalog_items']=(int)($itemTxt['count']??0);
        $out['catalog_source']='Item.txt';
        $out['catalog_sha256']=(string)($itemTxt['sha256']??'');
        $out['catalog_sections']=(array)($itemTxt['sections']??[]);
        $out['catalog_max_item_type']=(int)($itemTxt['max_item_type']??32);
        $out['catalog_overflow_definitions']=(int)($itemTxt['overflow_count']??0);

        $out['provided_images']=$this->providedImageCount();
        $out['image_pack']=$this->imagePackInfo();
        $out['custom_items_total']=count($this->customItems());
        $out['unknown_items_total']=count($this->unknownItemSummary(5000));
        $out['scan_totals']=$this->scanTotals();
        $out['image_candidates_total']=count($this->imageCandidates(2000));

        $out['format_detection']=[
            'Character.Inventory'=>'varbinary(760) / 76 x 10 bytes',
            'warehouse.Items'=>'varbinary(1200) / 120 x 10 bytes',
            'ExtWarehouse.Items'=>'columna varbinary(1920), registros reales analizados: 1200 bytes / 120 x 10',
            'decoder'=>'GameServer ConvertItemByte + GET_ITEM(section,index)=section*32+index',
            'source'=>'Character + warehouse + ExtWarehouse DB real + source Kayito'
        ];

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
            return '/module-assets/inventory-vault-viewer-kayito/'.
                implode('/',array_map('rawurlencode',explode('/',$path)));
        }
        return '';
    }

    private function customImagePath(string $path): string
    {
        $path=trim(str_replace('\\','/',$path));
        if($path==='') return '';
        if(preg_match('#^https://#i',$path)) return $path;
        if(str_starts_with($path,'/module-assets/')) return $path;
        if(str_starts_with($path,'assets/')){
            if(str_contains('/'.$path.'/','/../')) return '';
            return $path;
        }
        if(preg_match('/^[A-Za-z0-9._-]+\.(gif|png|jpe?g|webp|svg)$/i',$path)){
            return 'assets/custom-items/'.$path;
        }
        return '';
    }

    private function imageName(int $id,int $type,int $special,int $level): string
    {
        $dirs=$this->imageDirectories();
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
        $suffix=sprintf('%02X',max(0,$level));
        $candidates=[
            $specialBase.$suffix.'.gif',
            $normalBase.$suffix.'.gif',
            $specialBase.'.gif',
            $normalBase.'.gif'
        ];

        foreach($dirs as $dir){
            if(!is_dir($dir)) continue;

            $scan=scandir($dir) ?: [];
            $map=[];
            foreach($scan as $file){
                $map[strtoupper($file)]=$file;
            }

            foreach(array_unique($candidates) as $candidate){
                $key=strtoupper($candidate);
                if(isset($map[$key])) return $map[$key];
            }

        }

        return '';
    }

    /**
     * Prefer companion image pack when installed as sibling module.
     * Fall back to the images bundled with Inventory Viewer itself.
     *
     * @return string[]
     */
    private function imageDirectories(): array
    {
        $moduleRoot=dirname(__DIR__);

        return [
            $moduleRoot.'/inventory-item-images-1/assets/items/',
            $moduleRoot.'/inventory-item-images-2/assets/items/',
            // Compatibilidad con el pack único anterior si alguien amplía el límite.
            $moduleRoot.'/inventory-item-images/assets/items/',
            __DIR__.'/assets/items/',
        ];
    }

    private function publicImageUrl(string $image): string
    {
        $moduleRoot=dirname(__DIR__);

        foreach([
            'inventory-item-images-1',
            'inventory-item-images-2',
            'inventory-item-images'
        ] as $slug){
            $pack=$moduleRoot.'/'.$slug.'/assets/items/'.$image;
            if(is_file($pack)){
                return '/module-assets/'.$slug.'/assets/items/'.rawurlencode($image);
            }
        }

        $local=__DIR__.'/assets/items/'.$image;
        if(is_file($local)){
            return '/module-assets/inventory-vault-viewer-kayito/assets/items/'.rawurlencode($image);
        }

        return '';
    }

    public function imagePackInfo(): array
    {
        $moduleRoot=dirname(__DIR__);
        $parts=[];

        foreach([
            'inventory-item-images-1',
            'inventory-item-images-2',
            'inventory-item-images'
        ] as $slug){
            $dir=$moduleRoot.'/'.$slug.'/assets/items/';
            $files=is_dir($dir)
                ? glob($dir.'*.{gif,GIF}',GLOB_BRACE)
                : [];

            $parts[$slug]=[
                'installed'=>is_dir($dir),
                'images'=>is_array($files)?count($files):0,
            ];
        }

        $localFiles=glob(__DIR__.'/assets/items/*.{gif,GIF}',GLOB_BRACE);

        return [
            'parts'=>$parts,
            'split_complete'=>
                !empty($parts['inventory-item-images-1']['installed']) &&
                !empty($parts['inventory-item-images-2']['installed']),
            'split_images'=>
                (int)$parts['inventory-item-images-1']['images'] +
                (int)$parts['inventory-item-images-2']['images'],
            'legacy_installed'=>!empty($parts['inventory-item-images']['installed']),
            'legacy_images'=>(int)$parts['inventory-item-images']['images'],
            'local_images'=>is_array($localFiles)?count($localFiles):0,
        ];
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
