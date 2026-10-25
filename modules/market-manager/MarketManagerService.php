<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaMarketManagerService
{
    private const SETTINGS='cms_market_settings';
    private const CURRENCIES='cms_market_currencies';
    private const LISTINGS='cms_market_listings';
    private const TRANSACTIONS='cms_market_transactions';
    private const DELIVERIES='cms_market_delivery_vault';
    private const AUDIT='cms_market_audit_log';

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

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::CURRENCIES."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::CURRENCIES."(
                    CurrencyCode VARCHAR(30) NOT NULL PRIMARY KEY,
                    DisplayName NVARCHAR(80) NOT NULL,
                    CurrencyIcon NVARCHAR(20) NULL,
                    IsEnabled BIT NOT NULL DEFAULT 0,
                    MinimumPrice BIGINT NOT NULL DEFAULT 1,
                    MaximumPrice BIGINT NOT NULL DEFAULT 2000000000,
                    FeePercent DECIMAL(9,4) NOT NULL DEFAULT 0,
                    SourceTable VARCHAR(128) NULL,
                    AccountColumn VARCHAR(128) NULL,
                    BalanceColumn VARCHAR(128) NULL,
                    MappingVerified BIT NOT NULL DEFAULT 0,
                    SortOrder INT NOT NULL DEFAULT 0,
                    Description NVARCHAR(255) NULL,
                    UpdatedAt DATETIME2 NULL
                )
            END
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::LISTINGS."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::LISTINGS."(
                    ListingId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    SellerAccount VARCHAR(10) NOT NULL,
                    BuyerAccount VARCHAR(10) NULL,
                    ItemHex CHAR(32) NOT NULL,
                    ItemSerial CHAR(8) NULL,
                    ObjectKey VARCHAR(80) NOT NULL,
                    ItemType INT NULL,
                    ItemIndex INT NULL,
                    ItemLevel INT NULL,
                    ItemName NVARCHAR(160) NULL,
                    Price BIGINT NOT NULL,
                    CurrencyCode VARCHAR(30) NOT NULL,
                    Status VARCHAR(24) NOT NULL DEFAULT('ACTIVE'),
                    SellerIp VARCHAR(64) NULL,
                    BuyerIp VARCHAR(64) NULL,
                    SourceSlot INT NULL,
                    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                    SoldAt DATETIME2 NULL,
                    CancelledAt DATETIME2 NULL,
                    ExpiresAt DATETIME2 NULL
                )
            END
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::TRANSACTIONS."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::TRANSACTIONS."(
                    TransactionId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    ListingId BIGINT NOT NULL,
                    SellerAccount VARCHAR(10) NOT NULL,
                    BuyerAccount VARCHAR(10) NOT NULL,
                    ItemHex CHAR(32) NOT NULL,
                    ItemSerial CHAR(8) NULL,
                    GrossPrice BIGINT NOT NULL,
                    FeeAmount BIGINT NOT NULL,
                    NetPrice BIGINT NOT NULL,
                    CurrencyCode VARCHAR(30) NOT NULL,
                    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
                )
            END
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::DELIVERIES."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::DELIVERIES."(
                    DeliveryId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    ListingId BIGINT NULL,
                    BuyerAccount VARCHAR(10) NOT NULL,
                    SellerAccount VARCHAR(10) NULL,
                    ItemHex CHAR(32) NOT NULL,
                    ItemSerial CHAR(8) NULL,
                    ObjectKey VARCHAR(80) NOT NULL,
                    ItemName NVARCHAR(160) NULL,
                    CurrencyCode VARCHAR(30) NULL,
                    GrossPrice BIGINT NOT NULL DEFAULT 0,
                    FeeAmount BIGINT NOT NULL DEFAULT 0,
                    NetPrice BIGINT NOT NULL DEFAULT 0,
                    Status VARCHAR(20) NOT NULL DEFAULT('PENDING'),
                    DeliveryType VARCHAR(20) NOT NULL DEFAULT('PURCHASE'),
                    PurchasedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                    ClaimedAt DATETIME2 NULL,
                    Notes NVARCHAR(1000) NULL
                )
            END
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::AUDIT."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::AUDIT."(
                    AuditId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    AccountId VARCHAR(10) NULL,
                    ActionCode VARCHAR(50) NOT NULL,
                    ListingId BIGINT NULL,
                    Details NVARCHAR(1000) NULL,
                    IpAddress VARCHAR(64) NULL,
                    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
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
                    "INSERT INTO ".self::SETTINGS."(SettingKey,SettingValue) VALUES(:k,:v)",
                    ['k'=>$key,'v'=>$value]
                );
            }
        }

        $zen=$this->db->fetchOne(
            "SELECT TOP 1 CurrencyCode FROM ".self::CURRENCIES." WHERE CurrencyCode='ZEN'"
        );
        if(!$zen){
            $verified=$this->tableColumnExists('warehouse','AccountID') &&
                      $this->tableColumnExists('warehouse','Money');
            $this->db->execute(
                "INSERT INTO ".self::CURRENCIES."
                (CurrencyCode,DisplayName,CurrencyIcon,IsEnabled,MinimumPrice,MaximumPrice,
                 FeePercent,SourceTable,AccountColumn,BalanceColumn,MappingVerified,SortOrder,Description)
                 VALUES('ZEN',N'Zen',N'🪙',:enabled,1,2000000000,5.0,
                        'warehouse','AccountID','Money',:verified,1,N'Zen almacenado en el baúl')",
                ['enabled'=>$verified?1:0,'verified'=>$verified?1:0]
            );
        }
    }

    public function defaults(): array
    {
        return [
            'enabled'=>'0',
            'allow_listing'=>'0',
            'allow_purchase'=>'0',
            'require_offline'=>'1',
            'maintenance_message'=>'El mercado se encuentra en configuración.',
            'listing_limit'=>'10',
            'listing_duration_days'=>'30',
            'warehouse_table'=>'warehouse',
            'warehouse_account_column'=>'AccountID',
            'warehouse_items_column'=>'Items',
            'warehouse_bytes'=>'1920',
            'item_bytes'=>'16',
            'warehouse_slots'=>'120',
            'safe_claim_1x1_only'=>'1',
            'debug_mode'=>'0',
        ];
    }

    public function settings(): array
    {
        if($this->settingsCache) return $this->settingsCache;
        $out=$this->defaults();
        foreach($this->db->fetchAll("SELECT SettingKey,SettingValue FROM ".self::SETTINGS) as $r){
            $out[(string)$r['SettingKey']]=(string)($r['SettingValue']??'');
        }
        return $this->settingsCache=$out;
    }

    public function saveSettings(array $p): void
    {
        $data=[
            'enabled'=>isset($p['enabled'])?'1':'0',
            'allow_listing'=>isset($p['allow_listing'])?'1':'0',
            'allow_purchase'=>isset($p['allow_purchase'])?'1':'0',
            'require_offline'=>isset($p['require_offline'])?'1':'0',
            'maintenance_message'=>$this->text((string)($p['maintenance_message']??''),500),
            'listing_limit'=>(string)max(1,min(100,(int)($p['listing_limit']??10))),
            'listing_duration_days'=>(string)max(1,min(365,(int)($p['listing_duration_days']??30))),
            'safe_claim_1x1_only'=>isset($p['safe_claim_1x1_only'])?'1':'0',
            'debug_mode'=>isset($p['debug_mode'])?'1':'0',
        ];
        foreach($data as $k=>$v){
            $this->db->execute(
                "UPDATE ".self::SETTINGS." SET SettingValue=:v,UpdatedAt=SYSUTCDATETIME() WHERE SettingKey=:k",
                ['v'=>$v,'k'=>$k]
            );
        }
        $this->settingsCache=[];
    }

    public function enabled(): bool
    {
        return ($this->settings()['enabled']??'0')==='1';
    }

    public function currencies(bool $enabledOnly=false): array
    {
        $sql="SELECT * FROM ".self::CURRENCIES;
        if($enabledOnly) $sql.=" WHERE IsEnabled=1 AND MappingVerified=1";
        $sql.=" ORDER BY SortOrder,DisplayName";
        return $this->db->fetchAll($sql);
    }

    public function saveCurrency(array $p): void
    {
        $code=strtoupper(trim((string)($p['CurrencyCode']??'')));
        if(!preg_match('/^[A-Z0-9_]{1,30}$/',$code)) throw new \RuntimeException('Código de moneda inválido.');

        $table=$this->identifier((string)($p['SourceTable']??''));
        $account=$this->identifier((string)($p['AccountColumn']??''));
        $balance=$this->identifier((string)($p['BalanceColumn']??''));

        if(!$this->tableColumnExists($table,$account) || !$this->tableColumnExists($table,$balance)){
            throw new \RuntimeException('El mapeo de moneda no existe en la base MuOnline.');
        }

        $min=max(1,(int)($p['MinimumPrice']??1));
        $max=max($min,(int)($p['MaximumPrice']??2000000000));
        $fee=max(0,min(100,(float)($p['FeePercent']??0)));
        $enabled=isset($p['IsEnabled'])?1:0;

        $exists=$this->db->fetchOne(
            "SELECT TOP 1 CurrencyCode FROM ".self::CURRENCIES." WHERE CurrencyCode=:c",
            ['c'=>$code]
        );

        $params=[
            'c'=>$code,
            'n'=>$this->text((string)($p['DisplayName']??$code),80),
            'i'=>$this->text((string)($p['CurrencyIcon']??'💰'),20),
            'e'=>$enabled,
            'min'=>$min,
            'max'=>$max,
            'fee'=>$fee,
            't'=>$table,
            'a'=>$account,
            'b'=>$balance,
            'd'=>$this->text((string)($p['Description']??''),255)
        ];

        if($exists){
            $this->db->execute(
                "UPDATE ".self::CURRENCIES." SET
                 DisplayName=:n,CurrencyIcon=:i,IsEnabled=:e,
                 MinimumPrice=:min,MaximumPrice=:max,FeePercent=:fee,
                 SourceTable=:t,AccountColumn=:a,BalanceColumn=:b,
                 MappingVerified=1,Description=:d,UpdatedAt=SYSUTCDATETIME()
                 WHERE CurrencyCode=:c",
                $params
            );
        }else{
            $this->db->execute(
                "INSERT INTO ".self::CURRENCIES."
                 (CurrencyCode,DisplayName,CurrencyIcon,IsEnabled,MinimumPrice,MaximumPrice,
                  FeePercent,SourceTable,AccountColumn,BalanceColumn,MappingVerified,SortOrder,Description)
                 VALUES(:c,:n,:i,:e,:min,:max,:fee,:t,:a,:b,1,999,:d)",
                $params
            );
        }
    }

    public function listings(string $search='',string $currency='',int $page=1,int $perPage=24): array
    {
        $page=max(1,$page);
        $perPage=max(6,min(60,$perPage));
        $start=(($page-1)*$perPage)+1;
        $end=$page*$perPage;

        $where="Status='ACTIVE' AND (ExpiresAt IS NULL OR ExpiresAt>SYSUTCDATETIME())";
        $params=[];

        if($search!==''){
            $where.=" AND (ItemName LIKE :q OR SellerAccount LIKE :q OR ItemSerial LIKE :q)";
            $params['q']='%'.$search.'%';
        }
        if($currency!==''){
            $where.=" AND CurrencyCode=:cur";
            $params['cur']=$currency;
        }

        $sql="SELECT ListingId,SellerAccount,ItemHex,ItemSerial,ObjectKey,
                    ItemType,ItemIndex,ItemLevel,ItemName,Price,CurrencyCode,CreatedAt,ExpiresAt
              FROM (
                SELECT ListingId,SellerAccount,ItemHex,ItemSerial,ObjectKey,
                       ItemType,ItemIndex,ItemLevel,ItemName,Price,CurrencyCode,CreatedAt,ExpiresAt,
                       ROW_NUMBER() OVER(ORDER BY CreatedAt DESC,ListingId DESC) RowNum
                FROM ".self::LISTINGS."
                WHERE {$where}
              ) x
              WHERE RowNum BETWEEN {$start} AND {$end}
              ORDER BY RowNum";

        $rows=$this->db->fetchAll($sql,$params);
        foreach($rows as &$row){
            $decoded=$this->decodeBasic((string)$row['ItemHex']);
            $row['DecodedItem']=$decoded;
            if(empty($row['ItemName'])) $row['ItemName']=$decoded['name'];
        }
        unset($row);
        return $rows;
    }

    public function myListings(string $account): array
    {
        return $this->db->fetchAll(
            "SELECT TOP 100 * FROM ".self::LISTINGS."
             WHERE SellerAccount=:a ORDER BY CreatedAt DESC",
            ['a'=>$account]
        );
    }

    public function warehouseSlots(string $account): array
    {
        $s=$this->settings();
        $table=$this->q($s['warehouse_table']);
        $acct=$this->q($s['warehouse_account_column']);
        $items=$this->q($s['warehouse_items_column']);

        $row=$this->db->fetchOne(
            "SELECT master.dbo.fn_varbintohexstr({$items}) WarehouseHex,
                    DATALENGTH({$items}) WarehouseBytes
             FROM {$table}
             WHERE LTRIM(RTRIM({$acct}))=:a",
            ['a'=>$account]
        );

        if(!$row) return [];
        if((int)($row['WarehouseBytes']??0)!==(int)$s['warehouse_bytes']){
            throw new \RuntimeException('Warehouse.Items no contiene 1920 bytes.');
        }

        $hex=strtoupper(preg_replace('/^0X/i','',(string)$row['WarehouseHex']));
        $bytes=(int)$s['item_bytes'];
        $slots=(int)$s['warehouse_slots'];
        $hexPer=$bytes*2;
        $out=[];

        for($i=0;$i<$slots;$i++){
            $item=substr($hex,$i*$hexPer,$hexPer);
            if(strlen($item)!==$hexPer) break;
            if($item===str_repeat('F',$hexPer) || $item===str_repeat('0',$hexPer)) continue;
            $out[]=$this->decodeBasic($item,$i);
        }

        return $out;
    }

    public function decodeBasic(string $hex,?int $slot=null): array
    {
        $hex=strtoupper(preg_replace('/[^A-F0-9]/i','',$hex)??'');
        if(strlen($hex)<32){
            return ['slot'=>$slot,'hex'=>$hex,'serial'=>'','type'=>0,'id'=>0,'index'=>0,
                    'level'=>0,'option'=>0,'skill'=>0,'luck'=>0,'excellent'=>0,
                    'name'=>'Objeto desconocido','object_key'=>'INVALID'];
        }

        $hex=substr($hex,0,32);
        $id=hexdec(substr($hex,0,2));
        $optionByte=hexdec(substr($hex,2,2));
        $serial=substr($hex,6,8);
        $excellentByte=hexdec(substr($hex,14,2));
        $type=hexdec(substr($hex,18,1));

        $skill=0;$luck=0;
        if($optionByte>127){$skill=1;$optionByte-=128;}
        $level=(int)floor($optionByte/8);
        $optionByte-=$level*8;
        if($optionByte>3){$luck=1;$optionByte-=4;}
        if($excellentByte>=64){$optionByte+=4;$excellentByte-=64;}

        $validSerial=!in_array($serial,['','00000000','FFFFFFFF'],true);
        /*
         * v0.4.4 philosophy:
         * serial is preferred, but a serial-less object still gets a deterministic
         * object identity derived from the exact 16-byte item block.
         */
        $objectKey=$validSerial ? 'SERIAL:'.$serial : 'HEX:'.strtoupper(hash('sha256',$hex));

        return [
            'slot'=>$slot,'hex'=>$hex,'serial'=>$validSerial?$serial:'',
            'type'=>$type,'id'=>$id,'index'=>$id,'level'=>$level,'option'=>$optionByte,
            'skill'=>$skill,'luck'=>$luck,'excellent'=>$excellentByte,
            'name'=>'Item '.$type.':'.$id,'object_key'=>$objectKey,
            'x'=>1,'y'=>1,'decoder_source'=>'omega-basic'
        ];
    }

    public function createListing(string $account,int $slot,int $price,string $currency,string $ip): void
    {
        $s=$this->settings();
        if(($s['enabled']??'0')!=='1' || ($s['allow_listing']??'0')!=='1'){
            throw new \RuntimeException('El mercado no permite nuevas publicaciones.');
        }
        if(($s['require_offline']??'1')==='1' && $this->accountConnected($account)){
            throw new \RuntimeException('Debes desconectarte del juego antes de publicar.');
        }
        if($slot<0 || $slot>=(int)$s['warehouse_slots']) throw new \RuntimeException('Slot inválido.');

        $cur=$this->currency($currency);
        if(!$cur || !(int)$cur['IsEnabled'] || !(int)$cur['MappingVerified']){
            throw new \RuntimeException('Moneda inválida o no verificada.');
        }
        if($price<(int)$cur['MinimumPrice'] || $price>(int)$cur['MaximumPrice']){
            throw new \RuntimeException('Precio fuera del rango permitido.');
        }

        $active=$this->db->fetchOne(
            "SELECT COUNT(*) Total FROM ".self::LISTINGS."
             WHERE SellerAccount=:a AND Status='ACTIVE'",
            ['a'=>$account]
        );
        if((int)($active['Total']??0)>=(int)$s['listing_limit']){
            throw new \RuntimeException('Has alcanzado el límite de publicaciones.');
        }

        $table=$this->q($s['warehouse_table']);
        $acct=$this->q($s['warehouse_account_column']);
        $itemsCol=$this->q($s['warehouse_items_column']);
        $itemBytes=(int)$s['item_bytes'];
        $offset=($slot*$itemBytes)+1;
        $duration=(int)$s['listing_duration_days'];

        $this->db->transaction(function(Database $db) use(
            $account,$slot,$price,$currency,$ip,$table,$acct,$itemsCol,$itemBytes,$offset,$duration
        ): void {
            $row=$db->fetchOne(
                "SELECT {$itemsCol} Items,DATALENGTH({$itemsCol}) WarehouseBytes
                 FROM {$table} WITH(UPDLOCK,HOLDLOCK,ROWLOCK)
                 WHERE LTRIM(RTRIM({$acct}))=:a",
                ['a'=>$account]
            );
            if(!$row) throw new \RuntimeException('No se encontró el Warehouse.');
            if((int)$row['WarehouseBytes']!==1920) throw new \RuntimeException('Warehouse.Items no tiene 1920 bytes.');

            $binary=$row['Items'];
            if(!is_string($binary)) throw new \RuntimeException('No fue posible leer Warehouse.Items.');

            $item=substr($binary,$offset-1,$itemBytes);
            if(strlen($item)!==$itemBytes) throw new \RuntimeException('Bloque de objeto incompleto.');

            $hex=strtoupper(bin2hex($item));
            if($hex===str_repeat('F',$itemBytes*2) || $hex===str_repeat('0',$itemBytes*2)){
                throw new \RuntimeException('El slot seleccionado está vacío.');
            }

            $decoded=$this->decodeBasic($hex,$slot);
            $serial=$decoded['serial']!==''?$decoded['serial']:null;
            $objectKey=$decoded['object_key'];

            if($serial!==null){
                $dup=$db->fetchOne(
                    "SELECT TOP 1 ListingId FROM ".self::LISTINGS."
                     WHERE ItemSerial=:s AND Status='ACTIVE'",
                    ['s'=>$serial]
                );
                if($dup) throw new \RuntimeException('El objeto ya se encuentra publicado.');
            }

            $empty=str_repeat("\xFF",$itemBytes);
            $new=substr($binary,0,$offset-1).$empty.substr($binary,($offset-1)+$itemBytes);
            if(strlen($new)!==1920) throw new \RuntimeException('Reconstrucción inválida del Warehouse.');

            $changed=$db->execute(
                "UPDATE {$table} SET {$itemsCol}=:items WHERE LTRIM(RTRIM({$acct}))=:a",
                ['items'=>$new,'a'=>$account]
            );
            if($changed!==1) throw new \RuntimeException('No fue posible retirar el objeto del Warehouse.');

            $db->execute(
                "INSERT INTO ".self::LISTINGS."
                 (SellerAccount,ItemHex,ItemSerial,ObjectKey,ItemType,ItemIndex,ItemLevel,
                  ItemName,Price,CurrencyCode,SellerIp,SourceSlot,ExpiresAt)
                 VALUES(:seller,:hex,:serial,:obj,:type,:idx,:lvl,:name,:price,:cur,:ip,:slot,
                        DATEADD(DAY,CAST(:days AS INT),SYSUTCDATETIME()))",
                [
                    'seller'=>$account,'hex'=>$hex,'serial'=>$serial,'obj'=>$objectKey,
                    'type'=>$decoded['type'],'idx'=>$decoded['id'],'lvl'=>$decoded['level'],
                    'name'=>$decoded['name'],'price'=>$price,'cur'=>$currency,'ip'=>$ip,
                    'slot'=>$slot,'days'=>$duration
                ]
            );

            $id=$db->fetchOne("SELECT CAST(SCOPE_IDENTITY() AS BIGINT) ListingId");
            $this->audit($account,'LISTING_CREATED',(int)($id['ListingId']??0),
                'Slot='.$slot.'; ObjectKey='.$objectKey.'; Price='.$price.'; Currency='.$currency,$ip);
        });
    }

    public function cancelListing(string $account,int $listingId,string $ip): void
    {
        $this->db->transaction(function(Database $db) use($account,$listingId,$ip): void {
            $row=$db->fetchOne(
                "SELECT TOP 1 * FROM ".self::LISTINGS." WITH(UPDLOCK,HOLDLOCK)
                 WHERE ListingId=:id AND SellerAccount=:a AND Status='ACTIVE'",
                ['id'=>$listingId,'a'=>$account]
            );
            if(!$row) throw new \RuntimeException('La publicación ya no está disponible.');

            $db->execute(
                "UPDATE ".self::LISTINGS."
                 SET Status='CANCELLED',CancelledAt=SYSUTCDATETIME()
                 WHERE ListingId=:id",
                ['id'=>$listingId]
            );

            $db->execute(
                "INSERT INTO ".self::DELIVERIES."
                 (ListingId,BuyerAccount,SellerAccount,ItemHex,ItemSerial,ObjectKey,ItemName,
                  CurrencyCode,GrossPrice,FeeAmount,NetPrice,Status,DeliveryType,Notes)
                 VALUES(:listing,:buyer,:seller,:hex,:serial,:obj,:name,:cur,0,0,0,
                        'PENDING','RETURN',N'Publicación cancelada por el vendedor')",
                [
                    'listing'=>$listingId,'buyer'=>$account,'seller'=>$account,
                    'hex'=>$row['ItemHex'],'serial'=>$row['ItemSerial'],'obj'=>$row['ObjectKey'],
                    'name'=>$row['ItemName'],'cur'=>$row['CurrencyCode']
                ]
            );

            $this->audit($account,'LISTING_CANCELLED',$listingId,'Objeto protegido en Bóveda de Entregas.',$ip);
        });
    }

    public function buy(string $buyer,int $listingId,string $ip): void
    {
        $s=$this->settings();
        if(($s['enabled']??'0')!=='1' || ($s['allow_purchase']??'0')!=='1'){
            throw new \RuntimeException('El mercado no permite compras.');
        }
        if(($s['require_offline']??'1')==='1' && $this->accountConnected($buyer)){
            throw new \RuntimeException('Debes desconectarte del juego antes de comprar.');
        }

        $this->db->transaction(function(Database $db) use($buyer,$listingId,$ip): void {
            $listing=$db->fetchOne(
                "SELECT TOP 1 * FROM ".self::LISTINGS." WITH(UPDLOCK,HOLDLOCK,ROWLOCK)
                 WHERE ListingId=:id AND Status='ACTIVE'
                   AND (ExpiresAt IS NULL OR ExpiresAt>SYSUTCDATETIME())",
                ['id'=>$listingId]
            );
            if(!$listing) throw new \RuntimeException('La publicación ya no está disponible.');

            $seller=(string)$listing['SellerAccount'];
            if($seller===$buyer) throw new \RuntimeException('No puedes comprar tu propia publicación.');

            $cur=$this->currency((string)$listing['CurrencyCode']);
            if(!$cur || !(int)$cur['IsEnabled'] || !(int)$cur['MappingVerified']){
                throw new \RuntimeException('La moneda de la publicación no está habilitada.');
            }

            $price=(int)$listing['Price'];
            $fee=(int)floor($price*((float)$cur['FeePercent']/100));
            $net=$price-$fee;

            $table=$this->q((string)$cur['SourceTable']);
            $acct=$this->q((string)$cur['AccountColumn']);
            $bal=$this->q((string)$cur['BalanceColumn']);

            $buyerRow=$db->fetchOne(
                "SELECT TOP 1 {$bal} Balance FROM {$table} WITH(UPDLOCK,HOLDLOCK)
                 WHERE LTRIM(RTRIM({$acct}))=:a",
                ['a'=>$buyer]
            );
            if(!$buyerRow || (int)$buyerRow['Balance']<$price){
                throw new \RuntimeException('Saldo insuficiente.');
            }

            $sellerRow=$db->fetchOne(
                "SELECT TOP 1 {$bal} Balance FROM {$table} WITH(UPDLOCK,HOLDLOCK)
                 WHERE LTRIM(RTRIM({$acct}))=:a",
                ['a'=>$seller]
            );
            if(!$sellerRow) throw new \RuntimeException('No existe saldo de moneda para el vendedor.');

            $changed=$db->execute(
                "UPDATE {$table} SET {$bal}={$bal}-:price
                 WHERE LTRIM(RTRIM({$acct}))=:a AND {$bal}>=:price",
                ['price'=>$price,'a'=>$buyer]
            );
            if($changed!==1) throw new \RuntimeException('No fue posible descontar el saldo.');

            $changed=$db->execute(
                "UPDATE {$table} SET {$bal}=ISNULL({$bal},0)+:net
                 WHERE LTRIM(RTRIM({$acct}))=:a",
                ['net'=>$net,'a'=>$seller]
            );
            if($changed!==1) throw new \RuntimeException('No fue posible acreditar al vendedor.');

            $db->execute(
                "UPDATE ".self::LISTINGS."
                 SET Status='SOLD',BuyerAccount=:buyer,BuyerIp=:ip,SoldAt=SYSUTCDATETIME()
                 WHERE ListingId=:id",
                ['buyer'=>$buyer,'ip'=>$ip,'id'=>$listingId]
            );

            $db->execute(
                "INSERT INTO ".self::TRANSACTIONS."
                 (ListingId,SellerAccount,BuyerAccount,ItemHex,ItemSerial,GrossPrice,FeeAmount,NetPrice,CurrencyCode)
                 VALUES(:id,:seller,:buyer,:hex,:serial,:gross,:fee,:net,:cur)",
                [
                    'id'=>$listingId,'seller'=>$seller,'buyer'=>$buyer,
                    'hex'=>$listing['ItemHex'],'serial'=>$listing['ItemSerial'],
                    'gross'=>$price,'fee'=>$fee,'net'=>$net,'cur'=>$listing['CurrencyCode']
                ]
            );

            $db->execute(
                "INSERT INTO ".self::DELIVERIES."
                 (ListingId,BuyerAccount,SellerAccount,ItemHex,ItemSerial,ObjectKey,ItemName,
                  CurrencyCode,GrossPrice,FeeAmount,NetPrice,Status,DeliveryType,Notes)
                 VALUES(:id,:buyer,:seller,:hex,:serial,:obj,:name,:cur,:gross,:fee,:net,
                        'PENDING','PURCHASE',N'Compra protegida; retirar desde Bóveda de Entregas')",
                [
                    'id'=>$listingId,'buyer'=>$buyer,'seller'=>$seller,
                    'hex'=>$listing['ItemHex'],'serial'=>$listing['ItemSerial'],
                    'obj'=>$listing['ObjectKey'],'name'=>$listing['ItemName'],
                    'cur'=>$listing['CurrencyCode'],'gross'=>$price,'fee'=>$fee,'net'=>$net
                ]
            );

            $this->audit($buyer,'LISTING_PURCHASED',$listingId,
                'Seller='.$seller.'; Gross='.$price.'; Fee='.$fee.'; Net='.$net,$ip);
        });
    }

    public function deliveries(string $account,string $status=''): array
    {
        $where="BuyerAccount=:a";
        $params=['a'=>$account];
        if(in_array($status,['PENDING','CLAIMED','CANCELLED'],true)){
            $where.=" AND Status=:s"; $params['s']=$status;
        }
        $rows=$this->db->fetchAll(
            "SELECT TOP 200 * FROM ".self::DELIVERIES."
             WHERE {$where} ORDER BY PurchasedAt DESC,DeliveryId DESC",
            $params
        );
        foreach($rows as &$r) $r['DecodedItem']=$this->decodeBasic((string)$r['ItemHex']);
        unset($r);
        return $rows;
    }

    public function pendingDeliveryCount(string $account): int
    {
        $r=$this->db->fetchOne(
            "SELECT COUNT(*) Total FROM ".self::DELIVERIES."
             WHERE BuyerAccount=:a AND Status='PENDING'",
            ['a'=>$account]
        );
        return (int)($r['Total']??0);
    }

    public function claimDelivery(string $account,int $deliveryId,string $ip): void
    {
        $s=$this->settings();
        if(($s['require_offline']??'1')==='1' && $this->accountConnected($account)){
            throw new \RuntimeException('Debes desconectarte del juego antes de retirar.');
        }

        /*
         * Without a full MU item-size catalog, automatic placement of multi-cell
         * items is unsafe. This v1.0.0 only performs direct claims for blocks that
         * the basic decoder can safely treat as 1x1.
         */
        if(($s['safe_claim_1x1_only']??'1')!=='1'){
            throw new \RuntimeException('El modo de retiro automático ampliado aún no está habilitado.');
        }

        $table=$this->q($s['warehouse_table']);
        $acct=$this->q($s['warehouse_account_column']);
        $itemsCol=$this->q($s['warehouse_items_column']);

        $this->db->transaction(function(Database $db) use(
            $account,$deliveryId,$ip,$table,$acct,$itemsCol
        ): void {
            $delivery=$db->fetchOne(
                "SELECT TOP 1 * FROM ".self::DELIVERIES." WITH(UPDLOCK,HOLDLOCK)
                 WHERE DeliveryId=:id AND BuyerAccount=:a AND Status='PENDING'",
                ['id'=>$deliveryId,'a'=>$account]
            );
            if(!$delivery) throw new \RuntimeException('La entrega no existe o ya fue retirada.');

            $row=$db->fetchOne(
                "SELECT {$itemsCol} Items,DATALENGTH({$itemsCol}) WarehouseBytes
                 FROM {$table} WITH(UPDLOCK,HOLDLOCK,ROWLOCK)
                 WHERE LTRIM(RTRIM({$acct}))=:a",
                ['a'=>$account]
            );
            if(!$row || (int)($row['WarehouseBytes']??0)!==1920){
                throw new \RuntimeException('Warehouse incompatible.');
            }

            $binary=$row['Items'];
            if(!is_string($binary)) throw new \RuntimeException('No fue posible leer Warehouse.Items.');

            $item=hex2bin((string)$delivery['ItemHex']);
            if($item===false || strlen($item)!==16) throw new \RuntimeException('Objeto de entrega inválido.');

            $target=null;
            for($i=0;$i<120;$i++){
                $block=substr($binary,$i*16,16);
                if($block===str_repeat("\xFF",16)){
                    $target=$i;
                    break;
                }
            }
            if($target===null) throw new \RuntimeException('No hay ningún slot 1x1 libre en el Warehouse.');

            $new=substr($binary,0,$target*16).$item.substr($binary,($target+1)*16);
            if(strlen($new)!==1920) throw new \RuntimeException('Reconstrucción inválida.');

            $changed=$db->execute(
                "UPDATE {$table} SET {$itemsCol}=:items WHERE LTRIM(RTRIM({$acct}))=:a",
                ['items'=>$new,'a'=>$account]
            );
            if($changed!==1) throw new \RuntimeException('No fue posible insertar el objeto.');

            $db->execute(
                "UPDATE ".self::DELIVERIES."
                 SET Status='CLAIMED',ClaimedAt=SYSUTCDATETIME(),
                     Notes=ISNULL(Notes,N'')+N'; Slot='+CAST(:slot AS NVARCHAR(10))
                 WHERE DeliveryId=:id",
                ['slot'=>$target,'id'=>$deliveryId]
            );

            $this->audit($account,'DELIVERY_CLAIMED',(int)($delivery['ListingId']??0),
                'DeliveryId='.$deliveryId.'; Slot='.$target,$ip);
        });
    }

    public function diagnostics(): array
    {
        $s=$this->settings();
        $out=[];

        /*
         * No usar TOP 1 sobre warehouse: una cuenta sin baúl inicializado puede
         * tener Items=NULL y provocar un falso negativo.
         */
        try{
            $table=$this->identifier((string)$s['warehouse_table']);
            $items=$this->identifier((string)$s['warehouse_items_column']);

            $schema=$this->db->fetchOne(
                "SELECT TOP 1
                    DATA_TYPE DataType,
                    CHARACTER_MAXIMUM_LENGTH MaxLength
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME=:table
                   AND COLUMN_NAME=:column",
                ['table'=>$table,'column'=>$items]
            );

            $rows=$this->db->fetchOne(
                "SELECT
                    COUNT(*) TotalRows,
                    SUM(CASE WHEN [{$items}] IS NULL THEN 1 ELSE 0 END) NullRows,
                    SUM(CASE WHEN [{$items}] IS NOT NULL THEN 1 ELSE 0 END) InitializedRows,
                    MIN(CASE WHEN [{$items}] IS NOT NULL THEN DATALENGTH([{$items}]) END) MinBytes,
                    MAX(CASE WHEN [{$items}] IS NOT NULL THEN DATALENGTH([{$items}]) END) MaxBytes,
                    SUM(CASE WHEN DATALENGTH([{$items}])=1920 THEN 1 ELSE 0 END) Rows1920
                 FROM [{$table}]"
            ) ?? [];

            $schemaLength=(int)($schema['MaxLength']??0);
            $initialized=(int)($rows['InitializedRows']??0);
            $min=(int)($rows['MinBytes']??0);
            $max=(int)($rows['MaxBytes']??0);
            $rows1920=(int)($rows['Rows1920']??0);

            $compatibleSchema=$schemaLength===1920 || $schemaLength===-1;
            $compatibleData=
                $initialized===0
                    ? $compatibleSchema
                    : ($min===1920 && $max===1920 && $rows1920===$initialized);

            $out['Warehouse 1920 bytes']=[
                'status'=>($compatibleSchema && $compatibleData)?'ok':'review',
                'detail'=>'Schema='.
                    ($schemaLength===-1?'MAX':(string)$schemaLength).
                    ' bytes; inicializados='.$initialized.
                    '; min='.$min.
                    '; max='.$max.
                    '; filas1920='.$rows1920
            ];
        }catch(\Throwable $e){
            $out['Warehouse 1920 bytes']=[
                'status'=>'review',
                'detail'=>'No fue posible comprobar warehouse.Items: '.$e->getMessage()
            ];
        }

        $out['MEMB_STAT']=[
            'status'=>$this->tableColumnExists('MEMB_STAT','ConnectStat')?'ok':'review',
            'detail'=>'MEMB_STAT.ConnectStat'
        ];

        foreach($this->currencies(false) as $c){
            $mapped=
                (int)$c['MappingVerified']===1 &&
                $this->tableColumnExists((string)$c['SourceTable'],(string)$c['AccountColumn']) &&
                $this->tableColumnExists((string)$c['SourceTable'],(string)$c['BalanceColumn']);

            $out['Moneda '.$c['CurrencyCode']]=[
                'status'=>$mapped?'ok':'review',
                'detail'=>(string)($c['SourceTable']??'').'.'.
                    (string)($c['BalanceColumn']??'')
            ];
        }

        /*
         * Estas dos entradas son ESTADO, no errores.
         * OFF = modo seguro.
         * ON  = función activa.
         */
        $out['Publicaciones']=[
            'status'=>(($s['allow_listing']??'0')==='1')?'active':'safe',
            'detail'=>(($s['allow_listing']??'0')==='1')
                ? 'Habilitadas'
                : 'Deshabilitadas / modo seguro'
        ];

        $out['Compras']=[
            'status'=>(($s['allow_purchase']??'0')==='1')?'active':'safe',
            'detail'=>(($s['allow_purchase']??'0')==='1')
                ? 'Habilitadas'
                : 'Deshabilitadas / modo seguro'
        ];

        return $out;
    }

    private function currency(string $code): ?array
    {
        return $this->db->fetchOne(
            "SELECT TOP 1 * FROM ".self::CURRENCIES." WHERE CurrencyCode=:c",
            ['c'=>strtoupper(trim($code))]
        );
    }

    private function accountConnected(string $account): bool
    {
        try{
            $r=$this->db->fetchOne(
                "SELECT TOP 1 ConnectStat FROM MEMB_STAT
                 WHERE LTRIM(RTRIM(memb___id))=:a",
                ['a'=>$account]
            );
            return (int)($r['ConnectStat']??0)>0;
        }catch(\Throwable){
            return false;
        }
    }

    private function audit(?string $account,string $action,?int $listing,string $details,string $ip): void
    {
        $this->db->execute(
            "INSERT INTO ".self::AUDIT."
             (AccountId,ActionCode,ListingId,Details,IpAddress)
             VALUES(:a,:c,:l,:d,:ip)",
            ['a'=>$account,'c'=>$action,'l'=>$listing,'d'=>$details,'ip'=>mb_substr($ip,0,64)]
        );
    }

    private function tableColumnExists(string $table,string $column): bool
    {
        if(!$this->validIdentifier($table) || !$this->validIdentifier($column)) return false;
        $r=$this->db->fetchOne(
            "SELECT COUNT(*) Total FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME=:t AND COLUMN_NAME=:c",
            ['t'=>$table,'c'=>$column]
        );
        return (int)($r['Total']??0)>0;
    }

    private function q(string $v): string
    {
        return '['.$this->identifier($v).']';
    }

    private function identifier(string $v): string
    {
        $v=trim($v);
        if(!$this->validIdentifier($v)) throw new \RuntimeException('Identificador SQL inválido.');
        return $v;
    }

    private function validIdentifier(string $v): bool
    {
        return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/',$v);
    }

    private function text(string $v,int $max): string
    {
        return mb_substr(trim(str_replace(["\0","\r"],'',$v)),0,$max);
    }
}
