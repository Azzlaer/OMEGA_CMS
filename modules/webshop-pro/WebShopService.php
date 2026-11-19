<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaWebShopService
{
    private const SETTINGS='cms_webshop_settings';
    private const CATEGORIES='cms_webshop_categories';
    private const PRODUCTS='cms_webshop_products';
    private const WALLETS='cms_webshop_wallets';
    private const LEDGER='cms_webshop_ledger';
    private const ORDERS='cms_webshop_orders';
    private const DELIVERIES='cms_webshop_deliveries';
    private const AUDIT='cms_webshop_audit';

    public function __construct(private readonly Database $db)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.".self::SETTINGS."','U') IS NULL
            CREATE TABLE dbo.".self::SETTINGS."(
                SettingKey VARCHAR(120) NOT NULL PRIMARY KEY,
                SettingValue NVARCHAR(MAX) NULL,
                UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::CATEGORIES."','U') IS NULL
            CREATE TABLE dbo.".self::CATEGORIES."(
                CategoryId INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                Name NVARCHAR(100) NOT NULL,
                Slug VARCHAR(100) NOT NULL UNIQUE,
                Icon NVARCHAR(30) NULL,
                Description NVARCHAR(300) NULL,
                SortOrder INT NOT NULL DEFAULT 0,
                IsActive BIT NOT NULL DEFAULT 1,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                UpdatedAt DATETIME2 NULL
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::PRODUCTS."','U') IS NULL
            CREATE TABLE dbo.".self::PRODUCTS."(
                ProductId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                CategoryId INT NULL,
                Name NVARCHAR(160) NOT NULL,
                Slug VARCHAR(160) NOT NULL UNIQUE,
                Description NVARCHAR(1000) NULL,
                ProductType VARCHAR(30) NOT NULL,
                PriceCredits BIGINT NOT NULL DEFAULT 0,
                Stock INT NULL,
                IsActive BIT NOT NULL DEFAULT 1,
                IsFeatured BIT NOT NULL DEFAULT 0,
                SortOrder INT NOT NULL DEFAULT 0,
                ImageUrl NVARCHAR(700) NULL,
                PayloadJson NVARCHAR(MAX) NULL,
                LimitPerAccount INT NULL,
                RequiresOffline BIT NOT NULL DEFAULT 1,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                UpdatedAt DATETIME2 NULL
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::WALLETS."','U') IS NULL
            CREATE TABLE dbo.".self::WALLETS."(
                AccountId VARCHAR(10) NOT NULL PRIMARY KEY,
                Credits BIGINT NOT NULL DEFAULT 0,
                UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::LEDGER."','U') IS NULL
            CREATE TABLE dbo.".self::LEDGER."(
                LedgerId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                AccountId VARCHAR(10) NOT NULL,
                Amount BIGINT NOT NULL,
                BalanceAfter BIGINT NOT NULL,
                EntryType VARCHAR(30) NOT NULL,
                ReferenceType VARCHAR(40) NULL,
                ReferenceId BIGINT NULL,
                Note NVARCHAR(500) NULL,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::ORDERS."','U') IS NULL
            CREATE TABLE dbo.".self::ORDERS."(
                OrderId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                AccountId VARCHAR(10) NOT NULL,
                ProductId BIGINT NOT NULL,
                ProductName NVARCHAR(160) NOT NULL,
                ProductType VARCHAR(30) NOT NULL,
                Quantity INT NOT NULL DEFAULT 1,
                UnitPrice BIGINT NOT NULL,
                TotalPrice BIGINT NOT NULL,
                Status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
                PayloadJson NVARCHAR(MAX) NULL,
                ClientIpHash CHAR(64) NULL,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                CompletedAt DATETIME2 NULL,
                ErrorMessage NVARCHAR(1000) NULL
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::DELIVERIES."','U') IS NULL
            CREATE TABLE dbo.".self::DELIVERIES."(
                DeliveryId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                OrderId BIGINT NOT NULL,
                AccountId VARCHAR(10) NOT NULL,
                DeliveryType VARCHAR(30) NOT NULL,
                PayloadJson NVARCHAR(MAX) NULL,
                Status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                ClaimedAt DATETIME2 NULL,
                Notes NVARCHAR(1000) NULL
            )
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.".self::AUDIT."','U') IS NULL
            CREATE TABLE dbo.".self::AUDIT."(
                AuditId BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                AccountId VARCHAR(10) NULL,
                ActionCode VARCHAR(60) NOT NULL,
                ObjectType VARCHAR(40) NULL,
                ObjectId BIGINT NULL,
                Details NVARCHAR(1000) NULL,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        foreach($this->defaults() as $k=>$v){
            $exists=$this->db->fetchOne(
                "SELECT TOP 1 SettingKey FROM dbo.".self::SETTINGS." WHERE SettingKey=:k",
                ['k'=>$k]
            );
            if(!$exists){
                $this->db->execute(
                    "INSERT INTO dbo.".self::SETTINGS."(SettingKey,SettingValue) VALUES(:k,:v)",
                    ['k'=>$k,'v'=>$v]
                );
            }
        }

        $category=$this->db->fetchOne("SELECT TOP 1 CategoryId FROM dbo.".self::CATEGORIES);
        if(!$category){
            $this->db->execute("
                INSERT INTO dbo.".self::CATEGORIES."(Name,Slug,Icon,Description,SortOrder,IsActive)
                VALUES
                (N'Objetos','items',N'⚔️',N'Objetos y equipamiento del servidor.',10,1),
                (N'Servicios','services',N'⚙️',N'Resets, Zen, desbloqueos y otros servicios.',20,1),
                (N'VIP','vip',N'👑',N'Membresías y beneficios VIP.',30,1)
            ");
        }
    }

    public function defaults(): array
    {
        return [
            'enabled'=>'0',
            'maintenance_message'=>'La tienda web se encuentra temporalmente deshabilitada.',
            'require_offline'=>'1',
            'max_quantity'=>'10',
            'show_balance'=>'1',
            'allow_item_orders'=>'0',
            'allow_service_orders'=>'1',
            'credits_name'=>'Credits',
        ];
    }

    public function settings(): array
    {
        $out=$this->defaults();
        foreach($this->db->fetchAll("SELECT SettingKey,SettingValue FROM dbo.".self::SETTINGS) as $r){
            $out[(string)$r['SettingKey']]=(string)($r['SettingValue']??'');
        }
        return $out;
    }

    public function saveSettings(array $p): void
    {
        $values=[
            'enabled'=>!empty($p['enabled'])?'1':'0',
            'maintenance_message'=>$this->text((string)($p['maintenance_message']??''),500),
            'require_offline'=>!empty($p['require_offline'])?'1':'0',
            'max_quantity'=>(string)max(1,min(100,(int)($p['max_quantity']??10))),
            'show_balance'=>!empty($p['show_balance'])?'1':'0',
            'allow_item_orders'=>!empty($p['allow_item_orders'])?'1':'0',
            'allow_service_orders'=>!empty($p['allow_service_orders'])?'1':'0',
            'credits_name'=>$this->text((string)($p['credits_name']??'Credits'),40),
        ];

        foreach($values as $k=>$v){
            $this->db->execute("
                UPDATE dbo.".self::SETTINGS."
                   SET SettingValue=:v,UpdatedAt=SYSUTCDATETIME()
                 WHERE SettingKey=:k
            ",['v'=>$v,'k'=>$k]);
        }
    }

    public function categories(bool $onlyActive=false): array
    {
        $sql="SELECT * FROM dbo.".self::CATEGORIES;
        if($onlyActive) $sql.=" WHERE IsActive=1";
        $sql.=" ORDER BY SortOrder,Name";
        return $this->db->fetchAll($sql);
    }

    public function products(string $category='',string $search='',bool $onlyActive=true): array
    {
        $where=[];
        $params=[];
        if($onlyActive) $where[]='p.IsActive=1';
        if($category!==''){
            $where[]='c.Slug=:category';
            $params['category']=$category;
        }
        if($search!==''){
            $where[]='(p.Name LIKE :q OR p.Description LIKE :q2)';
            $params['q']='%'.$search.'%';
            $params['q2']='%'.$search.'%';
        }

        $sql="SELECT p.*,c.Name CategoryName,c.Slug CategorySlug,c.Icon CategoryIcon
                FROM dbo.".self::PRODUCTS." p
                LEFT JOIN dbo.".self::CATEGORIES." c ON c.CategoryId=p.CategoryId";
        if($where) $sql.=' WHERE '.implode(' AND ',$where);
        $sql.=" ORDER BY p.IsFeatured DESC,p.SortOrder,p.Name";
        return $this->db->fetchAll($sql,$params);
    }

    public function product(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*,c.Name CategoryName,c.Slug CategorySlug
               FROM dbo.".self::PRODUCTS." p
               LEFT JOIN dbo.".self::CATEGORIES." c ON c.CategoryId=p.CategoryId
              WHERE p.ProductId=:id",
            ['id'=>$id]
        );
    }

    public function saveCategory(array $p): int
    {
        $id=max(0,(int)($p['CategoryId']??0));
        $name=$this->text((string)($p['Name']??''),100);
        $slug=$this->slug((string)($p['Slug']??$name));
        if($name==='' || $slug==='') throw new RuntimeException('Nombre y slug son obligatorios.');

        if($id>0){
            $this->db->execute("
                UPDATE dbo.".self::CATEGORIES."
                   SET Name=:n,Slug=:s,Icon=:i,Description=:d,SortOrder=:o,
                       IsActive=:a,UpdatedAt=SYSUTCDATETIME()
                 WHERE CategoryId=:id
            ",[
                'n'=>$name,'s'=>$slug,'i'=>$this->text((string)($p['Icon']??''),30),
                'd'=>$this->text((string)($p['Description']??''),300),
                'o'=>(int)($p['SortOrder']??0),'a'=>!empty($p['IsActive'])?1:0,'id'=>$id
            ]);
            return $id;
        }

        $this->db->execute("
            INSERT INTO dbo.".self::CATEGORIES."
                (Name,Slug,Icon,Description,SortOrder,IsActive)
            VALUES(:n,:s,:i,:d,:o,:a)
        ",[
            'n'=>$name,'s'=>$slug,'i'=>$this->text((string)($p['Icon']??''),30),
            'd'=>$this->text((string)($p['Description']??''),300),
            'o'=>(int)($p['SortOrder']??0),'a'=>!empty($p['IsActive'])?1:0
        ]);

        return (int)($this->db->fetchOne("SELECT CAST(SCOPE_IDENTITY() AS INT) id")['id']??0);
    }

    public function saveProduct(array $p): int
    {
        $id=max(0,(int)($p['ProductId']??0));
        $type=strtoupper(trim((string)($p['ProductType']??'ITEM')));
        if(!in_array($type,['ITEM','VIP','RESET','ZEN','UNBAN','CREDITS'],true)){
            throw new RuntimeException('Tipo de producto no soportado.');
        }

        $name=$this->text((string)($p['Name']??''),160);
        $slug=$this->slug((string)($p['Slug']??$name));
        if($name==='' || $slug==='') throw new RuntimeException('Nombre y slug son obligatorios.');

        $payload=$this->payloadFromInput($type,$p);
        $payloadJson=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        $params=[
            'cat'=>($p['CategoryId']??'')===''?null:(int)$p['CategoryId'],
            'n'=>$name,'s'=>$slug,'d'=>$this->text((string)($p['Description']??''),1000),
            't'=>$type,'price'=>max(0,(int)($p['PriceCredits']??0)),
            'stock'=>($p['Stock']??'')===''?null:max(0,(int)$p['Stock']),
            'active'=>!empty($p['IsActive'])?1:0,'featured'=>!empty($p['IsFeatured'])?1:0,
            'sort'=>(int)($p['SortOrder']??0),
            'img'=>$this->text((string)($p['ImageUrl']??''),700),
            'payload'=>$payloadJson,
            'limit'=>($p['LimitPerAccount']??'')===''?null:max(1,(int)$p['LimitPerAccount']),
            'offline'=>!empty($p['RequiresOffline'])?1:0,
        ];

        if($id>0){
            $params['id']=$id;
            $this->db->execute("
                UPDATE dbo.".self::PRODUCTS."
                   SET CategoryId=:cat,Name=:n,Slug=:s,Description=:d,ProductType=:t,
                       PriceCredits=:price,Stock=:stock,IsActive=:active,IsFeatured=:featured,
                       SortOrder=:sort,ImageUrl=:img,PayloadJson=:payload,
                       LimitPerAccount=:limit,RequiresOffline=:offline,UpdatedAt=SYSUTCDATETIME()
                 WHERE ProductId=:id
            ",$params);
            return $id;
        }

        $this->db->execute("
            INSERT INTO dbo.".self::PRODUCTS."
                (CategoryId,Name,Slug,Description,ProductType,PriceCredits,Stock,IsActive,
                 IsFeatured,SortOrder,ImageUrl,PayloadJson,LimitPerAccount,RequiresOffline)
            VALUES(:cat,:n,:s,:d,:t,:price,:stock,:active,:featured,:sort,:img,:payload,:limit,:offline)
        ",$params);

        return (int)($this->db->fetchOne("SELECT CAST(SCOPE_IDENTITY() AS INT) id")['id']??0);
    }

    public function balance(string $account): int
    {
        $this->ensureWallet($account);
        return (int)($this->db->fetchOne(
            "SELECT Credits FROM dbo.".self::WALLETS." WHERE AccountId=:a",
            ['a'=>$account]
        )['Credits']??0);
    }

    public function adjustCredits(string $account,int $amount,string $note,string $entryType='ADMIN'): int
    {
        if($amount===0) return $this->balance($account);
        $this->ensureWallet($account);

        return $this->db->transaction(function(Database $db) use($account,$amount,$note,$entryType): int {
            $row=$db->fetchOne(
                "SELECT Credits FROM dbo.".self::WALLETS." WITH(UPDLOCK,HOLDLOCK) WHERE AccountId=:a",
                ['a'=>$account]
            );
            $current=(int)($row['Credits']??0);
            $next=$current+$amount;
            if($next<0) throw new RuntimeException('Saldo insuficiente.');

            $db->execute(
                "UPDATE dbo.".self::WALLETS." SET Credits=:c,UpdatedAt=SYSUTCDATETIME() WHERE AccountId=:a",
                ['c'=>$next,'a'=>$account]
            );
            $db->execute("
                INSERT INTO dbo.".self::LEDGER."
                    (AccountId,Amount,BalanceAfter,EntryType,Note)
                VALUES(:a,:amt,:bal,:typ,:note)
            ",['a'=>$account,'amt'=>$amount,'bal'=>$next,'typ'=>$entryType,'note'=>$this->text($note,500)]);
            return $next;
        });
    }

    public function buy(string $account,int $productId,int $quantity,string $clientIp=''): int
    {
        $s=$this->settings();
        if(($s['enabled']??'0')!=='1') throw new RuntimeException((string)$s['maintenance_message']);

        $product=$this->product($productId);
        if(!$product || !(int)$product['IsActive']) throw new RuntimeException('Producto no disponible.');

        $quantity=max(1,min((int)$s['max_quantity'],$quantity));
        if($product['ProductType']!=='ITEM' && $quantity>1){
            throw new RuntimeException('Los servicios se compran de a una unidad.');
        }

        if(($product['ProductType']==='ITEM' && ($s['allow_item_orders']??'0')!=='1') ||
           ($product['ProductType']!=='ITEM' && ($s['allow_service_orders']??'1')!=='1')){
            throw new RuntimeException('Este tipo de compra está deshabilitado.');
        }

        if(((int)$product['RequiresOffline']===1 || ($s['require_offline']??'1')==='1') && $this->accountOnline($account)){
            throw new RuntimeException('Debes desconectarte del juego antes de comprar.');
        }

        $stock=$product['Stock'];
        if($stock!==null && (int)$stock<$quantity) throw new RuntimeException('Stock insuficiente.');

        $limit=$product['LimitPerAccount'];
        if($limit!==null){
            $count=(int)($this->db->fetchOne(
                "SELECT COALESCE(SUM(Quantity),0) n FROM dbo.".self::ORDERS."
                 WHERE AccountId=:a AND ProductId=:p AND Status IN('PENDING','COMPLETED')",
                ['a'=>$account,'p'=>$productId]
            )['n']??0);
            if($count+$quantity>(int)$limit) throw new RuntimeException('Has alcanzado el límite de compra para este producto.');
        }

        $total=(int)$product['PriceCredits']*$quantity;
        $payload=(string)($product['PayloadJson']??'{}');
        $ipHash=$clientIp!==''?hash('sha256',$clientIp):null;

        return $this->db->transaction(function(Database $db) use($account,$product,$quantity,$total,$payload,$ipHash): int {
            $this->ensureWallet($account);
            $wallet=$db->fetchOne(
                "SELECT Credits FROM dbo.".self::WALLETS." WITH(UPDLOCK,HOLDLOCK) WHERE AccountId=:a",
                ['a'=>$account]
            );
            $credits=(int)($wallet['Credits']??0);
            if($credits<$total) throw new RuntimeException('No tienes créditos suficientes.');

            if($product['Stock']!==null){
                $stock=$db->fetchOne(
                    "SELECT Stock FROM dbo.".self::PRODUCTS." WITH(UPDLOCK,HOLDLOCK) WHERE ProductId=:p",
                    ['p'=>$product['ProductId']]
                );
                if((int)($stock['Stock']??0)<$quantity) throw new RuntimeException('Stock insuficiente.');
            }

            $newBalance=$credits-$total;
            $db->execute(
                "UPDATE dbo.".self::WALLETS." SET Credits=:c,UpdatedAt=SYSUTCDATETIME() WHERE AccountId=:a",
                ['c'=>$newBalance,'a'=>$account]
            );

            if($product['Stock']!==null){
                $db->execute(
                    "UPDATE dbo.".self::PRODUCTS." SET Stock=Stock-:q,UpdatedAt=SYSUTCDATETIME() WHERE ProductId=:p",
                    ['q'=>$quantity,'p'=>$product['ProductId']]
                );
            }

            $db->execute("
                INSERT INTO dbo.".self::ORDERS."
                    (AccountId,ProductId,ProductName,ProductType,Quantity,UnitPrice,TotalPrice,Status,PayloadJson,ClientIpHash)
                VALUES(:a,:pid,:pn,:pt,:q,:up,:tp,'PENDING',:payload,:iph)
            ",[
                'a'=>$account,'pid'=>$product['ProductId'],'pn'=>$product['Name'],
                'pt'=>$product['ProductType'],'q'=>$quantity,'up'=>$product['PriceCredits'],
                'tp'=>$total,'payload'=>$payload,'iph'=>$ipHash
            ]);

            $orderId=(int)($db->fetchOne("SELECT CAST(SCOPE_IDENTITY() AS BIGINT) id")['id']??0);

            $db->execute("
                INSERT INTO dbo.".self::LEDGER."
                    (AccountId,Amount,BalanceAfter,EntryType,ReferenceType,ReferenceId,Note)
                VALUES(:a,:amt,:bal,'PURCHASE','ORDER',:rid,:note)
            ",[
                'a'=>$account,'amt'=>-$total,'bal'=>$newBalance,'rid'=>$orderId,
                'note'=>'Compra WebShop: '.$this->text((string)$product['Name'],160)
            ]);

            $db->execute("
                INSERT INTO dbo.".self::DELIVERIES."
                    (OrderId,AccountId,DeliveryType,PayloadJson,Status)
                VALUES(:oid,:a,:dt,:payload,'PENDING')
            ",[
                'oid'=>$orderId,'a'=>$account,'dt'=>$product['ProductType'],'payload'=>$payload
            ]);

            $db->execute("
                INSERT INTO dbo.".self::AUDIT."
                    (AccountId,ActionCode,ObjectType,ObjectId,Details)
                VALUES(:a,'PURCHASE','ORDER',:oid,:details)
            ",[
                'a'=>$account,'oid'=>$orderId,
                'details'=>'Producto '.$this->text((string)$product['Name'],160).' x'.$quantity
            ]);

            return $orderId;
        });
    }

    public function orders(string $account,int $limit=50): array
    {
        $limit=max(1,min(200,$limit));
        return $this->db->fetchAll(
            "SELECT TOP {$limit} * FROM dbo.".self::ORDERS." WHERE AccountId=:a ORDER BY OrderId DESC",
            ['a'=>$account]
        );
    }

    public function ledger(string $account,int $limit=50): array
    {
        $limit=max(1,min(200,$limit));
        return $this->db->fetchAll(
            "SELECT TOP {$limit} * FROM dbo.".self::LEDGER." WHERE AccountId=:a ORDER BY LedgerId DESC",
            ['a'=>$account]
        );
    }

    public function deliveries(string $account,int $limit=50): array
    {
        $limit=max(1,min(200,$limit));
        return $this->db->fetchAll(
            "SELECT TOP {$limit} * FROM dbo.".self::DELIVERIES." WHERE AccountId=:a ORDER BY DeliveryId DESC",
            ['a'=>$account]
        );
    }

    public function adminStats(): array
    {
        return [
            'products'=>(int)($this->db->fetchOne("SELECT COUNT(*) n FROM dbo.".self::PRODUCTS)['n']??0),
            'orders'=>(int)($this->db->fetchOne("SELECT COUNT(*) n FROM dbo.".self::ORDERS)['n']??0),
            'pending'=>(int)($this->db->fetchOne("SELECT COUNT(*) n FROM dbo.".self::DELIVERIES." WHERE Status='PENDING'")['n']??0),
            'credits'=>(int)($this->db->fetchOne("SELECT COALESCE(SUM(Credits),0) n FROM dbo.".self::WALLETS)['n']??0),
        ];
    }

    public function recentOrders(int $limit=30): array
    {
        $limit=max(1,min(100,$limit));
        return $this->db->fetchAll(
            "SELECT TOP {$limit} * FROM dbo.".self::ORDERS." ORDER BY OrderId DESC"
        );
    }

    public function pendingDeliveries(int $limit=50): array
    {
        $limit=max(1,min(200,$limit));
        return $this->db->fetchAll(
            "SELECT TOP {$limit} * FROM dbo.".self::DELIVERIES." WHERE Status='PENDING' ORDER BY DeliveryId"
        );
    }

    public function diagnostic(): array
    {
        $s=$this->settings();
        return [
            'enabled'=>($s['enabled']??'0')==='1',
            'products'=>count($this->products('','',false)),
            'categories'=>count($this->categories(false)),
            'pending_deliveries'=>count($this->pendingDeliveries(200)),
            'wallets'=>(int)($this->db->fetchOne("SELECT COUNT(*) n FROM dbo.".self::WALLETS)['n']??0),
        ];
    }

    private function ensureWallet(string $account): void
    {
        $account=trim($account);
        if($account==='') throw new RuntimeException('Cuenta inválida.');
        $this->db->execute("
            IF NOT EXISTS(SELECT 1 FROM dbo.".self::WALLETS." WHERE AccountId=:a)
            INSERT INTO dbo.".self::WALLETS."(AccountId,Credits) VALUES(:a2,0)
        ",['a'=>$account,'a2'=>$account]);
    }

    private function accountOnline(string $account): bool
    {
        $row=$this->db->fetchOne(
            "SELECT TOP 1 ConnectStat FROM dbo.MEMB_STAT WHERE memb___id=:a",
            ['a'=>$account]
        );
        return (int)($row['ConnectStat']??0)>0;
    }

    private function payloadFromInput(string $type,array $p): array
    {
        return match($type){
            'ITEM'=>[
                'item_hex'=>strtoupper(preg_replace('/[^A-F0-9]/i','',(string)($p['ItemHex']??''))??''),
                'item_name'=>$this->text((string)($p['ItemName']??$p['Name']??''),160),
                'item_type'=>(int)($p['ItemType']??0),
                'item_index'=>(int)($p['ItemIndex']??0),
                'item_level'=>(int)($p['ItemLevel']??0),
            ],
            'VIP'=>['account_level'=>max(0,(int)($p['VipLevel']??1)),'days'=>max(1,(int)($p['VipDays']??30))],
            'RESET'=>['amount'=>max(1,(int)($p['ResetAmount']??1)),'character'=>$this->text((string)($p['CharacterName']??''),10)],
            'ZEN'=>['amount'=>max(1,(int)($p['ZenAmount']??1))],
            'UNBAN'=>['clear_bloc_code'=>true],
            'CREDITS'=>['amount'=>max(1,(int)($p['CreditAmount']??1))],
            default=>[],
        };
    }

    private function text(string $v,int $max): string
    {
        $v=trim(strip_tags($v));
        return strlen($v)>$max?substr($v,0,$max):$v;
    }

    private function slug(string $v): string
    {
        $v=strtolower(trim($v));
        $v=preg_replace('/[^a-z0-9]+/','-',$v)??'';
        return trim(substr($v,0,160),'-');
    }
}
