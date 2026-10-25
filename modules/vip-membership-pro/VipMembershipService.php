<?php
declare(strict_types=1);

use Omega\Core\Database;
final class OmegaVipMembershipService
{
    private const SETTINGS='cms_vip_pro_settings';
    private const PLANS='cms_vip_pro_plans';
    private const LOG='cms_vip_pro_log';

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
                    SettingKey VARCHAR(120) NOT NULL PRIMARY KEY,
                    SettingValue NVARCHAR(MAX) NULL,
                    UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
                )
            END
        ");

        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.".self::PLANS."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::PLANS."(
                    PlanID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    VipType INT NOT NULL,
                    DisplayName NVARCHAR(100) NOT NULL,
                    ButtonLabel NVARCHAR(80) NULL,
                    DaysAmount INT NOT NULL,
                    BaseCost INT NOT NULL,
                    DiscountPercent INT NOT NULL DEFAULT 0,
                    IsEnabled BIT NOT NULL DEFAULT 1,
                    SortOrder INT NOT NULL DEFAULT 0,
                    Description NVARCHAR(300) NULL,
                    InfoContent NVARCHAR(MAX) NULL,
                    CardIcon NVARCHAR(20) NULL,
                    AccentClass VARCHAR(30) NULL
                )
            END
        ");

        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.".self::LOG."','U') IS NULL
            BEGIN
                CREATE TABLE dbo.".self::LOG."(
                    LogID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    AccountID VARCHAR(20) NOT NULL,
                    PlanID INT NULL,
                    VipType INT NOT NULL,
                    VipDays INT NOT NULL,
                    CreditsCharged INT NOT NULL,
                    BalanceBefore INT NOT NULL,
                    Status VARCHAR(20) NOT NULL,
                    ErrorMessage NVARCHAR(1000) NULL,
                    ClientIP VARCHAR(64) NULL,
                    CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
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

        $count=$this->cmsDb->fetchOne(
            "SELECT COUNT(*) Total FROM ".self::PLANS
        );

        if((int)($count['Total']??0)===0){
            $defaultInfo="[center][size=20][b]Beneficios VIP[/b][/size][/center]\n\n[list]\n[*]Membresía premium por tiempo limitado\n[*]Beneficios configurados por la administración\n[*]Acreditación directa en tu cuenta\n[/list]";

            $this->insertPlan(1,'Bronce','Comprar VIP',7,500,0,1,10,'Una semana de beneficios VIP para comenzar.',$defaultInfo,'🥉','bronze');
            $this->insertPlan(1,'Bronce Mensual','Comprar VIP',30,1800,10,1,20,'Treinta días de membresía VIP Bronce.',$defaultInfo,'🥉','bronze');
            $this->insertPlan(2,'Plata Mensual','Comprar VIP',30,3000,10,1,30,'Beneficios superiores durante treinta días.',$defaultInfo,'🥈','silver');
            $this->insertPlan(3,'Oro Mensual','Comprar VIP',30,5000,15,1,40,'El plan premium para jugadores exigentes.',$defaultInfo,'🥇','gold');
        }
    }

    public function defaults(): array
    {
        return [
            'enabled'=>'1',
            'allow_type_change'=>'0',
            'require_offline'=>'1',
            'currency_name'=>'Créditos',
            'title'=>'Membresías VIP',
            'description'=>'Elige un plan y activa beneficios premium para toda tu cuenta.',

            'vip_table'=>'MEMB_INFO',
            'vip_user_column'=>'memb___id',
            'vip_type_column'=>'AccountLevel',
            'vip_expire_column'=>'AccountExpireDate',

            'credit_backend'=>'table',
            'credits_table'=>'MEMB_CREDITS',
            'credits_user_column'=>'memb___id',
            'credits_value_column'=>'credits',

            'wcoin_table'=>'CashShopData',
            'wcoin_user_column'=>'AccountID',
            'wcoin_value_column'=>'WCoinC',

            'status_table'=>'MEMB_STAT',
            'status_user_column'=>'memb___id',
            'status_value_column'=>'ConnectStat',
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
        $backend=(string)($post['credit_backend']??'table');
        if(!in_array($backend,['table','wcoinc'],true)) $backend='table';

        $data=[
            'enabled'=>isset($post['enabled'])?'1':'0',
            'allow_type_change'=>isset($post['allow_type_change'])?'1':'0',
            'require_offline'=>isset($post['require_offline'])?'1':'0',
            'currency_name'=>$this->text((string)($post['currency_name']??'Créditos'),80),
            'title'=>$this->text((string)($post['title']??'Membresías VIP'),120),
            'description'=>$this->text((string)($post['description']??''),500),

            'vip_table'=>$this->identifier((string)($post['vip_table']??'MEMB_INFO')),
            'vip_user_column'=>$this->identifier((string)($post['vip_user_column']??'memb___id')),
            'vip_type_column'=>$this->identifier((string)($post['vip_type_column']??'AccountLevel')),
            'vip_expire_column'=>$this->identifier((string)($post['vip_expire_column']??'AccountExpireDate')),

            'credit_backend'=>$backend,
            'credits_table'=>$this->identifier((string)($post['credits_table']??'MEMB_CREDITS')),
            'credits_user_column'=>$this->identifier((string)($post['credits_user_column']??'memb___id')),
            'credits_value_column'=>$this->identifier((string)($post['credits_value_column']??'credits')),

            'wcoin_table'=>$this->identifier((string)($post['wcoin_table']??'CashShopData')),
            'wcoin_user_column'=>$this->identifier((string)($post['wcoin_user_column']??'AccountID')),
            'wcoin_value_column'=>$this->identifier((string)($post['wcoin_value_column']??'WCoinC')),

            'status_table'=>$this->identifier((string)($post['status_table']??'MEMB_STAT')),
            'status_user_column'=>$this->identifier((string)($post['status_user_column']??'memb___id')),
            'status_value_column'=>$this->identifier((string)($post['status_value_column']??'ConnectStat')),
        ];

        $this->cmsDb->transaction(function(Database $db) use($data): void {
            foreach($data as $key=>$value){
                $changed=$db->execute(
                    "UPDATE ".self::SETTINGS."
                     SET SettingValue=:value,UpdatedAt=SYSUTCDATETIME()
                     WHERE SettingKey=:key",
                    ['value'=>$value,'key'=>$key]
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

    public function plans(bool $enabledOnly=true): array
    {
        $sql="SELECT * FROM ".self::PLANS;
        if($enabledOnly) $sql.=" WHERE IsEnabled=1";
        $sql.=" ORDER BY SortOrder,PlanID";
        return $this->cmsDb->fetchAll($sql);
    }

    public function plan(int $planId): ?array
    {
        return $this->cmsDb->fetchOne(
            "SELECT TOP 1 * FROM ".self::PLANS." WHERE PlanID=:id",
            ['id'=>$planId]
        );
    }

    public function planCost(array $plan): int
    {
        $base=max(0,(int)($plan['BaseCost']??0));
        $discount=max(0,min(100,(int)($plan['DiscountPercent']??0)));
        return (int)round($base*((100-$discount)/100));
    }

    public function addPlan(array $data): void
    {
        $name=$this->text((string)($data['DisplayName']??''),100);
        if($name==='') throw new \RuntimeException('El plan necesita un nombre.');

        $this->insertPlan(
            max(1,min(10,(int)($data['VipType']??1))),
            $name,
            $this->text((string)($data['ButtonLabel']??'Comprar VIP'),80),
            max(1,min(3650,(int)($data['DaysAmount']??30))),
            max(0,(int)($data['BaseCost']??0)),
            max(0,min(100,(int)($data['DiscountPercent']??0))),
            1,
            max(0,min(9999,(int)($data['SortOrder']??100))),
            $this->text((string)($data['Description']??''),300),
            trim((string)($data['InfoContent']??'')),
            $this->text((string)($data['CardIcon']??'👑'),20),
            $this->accent((string)($data['AccentClass']??'gold'))
        );
    }

    public function savePlan(array $data): void
    {
        $id=(int)($data['PlanID']??0);
        if($id<=0) throw new \RuntimeException('Plan inválido.');

        $name=$this->text((string)($data['DisplayName']??''),100);
        if($name==='') throw new \RuntimeException('El plan necesita un nombre.');

        $this->cmsDb->execute(
            "UPDATE ".self::PLANS."
             SET VipType=:type,
                 DisplayName=:name,
                 ButtonLabel=:button,
                 DaysAmount=:days,
                 BaseCost=:cost,
                 DiscountPercent=:discount,
                 IsEnabled=:enabled,
                 SortOrder=:sort,
                 Description=:description,
                 InfoContent=:info,
                 CardIcon=:icon,
                 AccentClass=:accent
             WHERE PlanID=:id",
            [
                'type'=>max(1,min(10,(int)($data['VipType']??1))),
                'name'=>$name,
                'button'=>$this->text((string)($data['ButtonLabel']??'Comprar VIP'),80),
                'days'=>max(1,min(3650,(int)($data['DaysAmount']??1))),
                'cost'=>max(0,(int)($data['BaseCost']??0)),
                'discount'=>max(0,min(100,(int)($data['DiscountPercent']??0))),
                'enabled'=>isset($data['IsEnabled'])?1:0,
                'sort'=>max(0,min(9999,(int)($data['SortOrder']??0))),
                'description'=>$this->text((string)($data['Description']??''),300),
                'info'=>trim((string)($data['InfoContent']??'')),
                'icon'=>$this->text((string)($data['CardIcon']??'👑'),20),
                'accent'=>$this->accent((string)($data['AccentClass']??'gold')),
                'id'=>$id,
            ]
        );
    }

    public function deletePlan(int $id): void
    {
        if($id<=0) throw new \RuntimeException('Plan inválido.');

        $used=$this->cmsDb->fetchOne(
            "SELECT COUNT(*) Total FROM ".self::LOG." WHERE PlanID=:id",
            ['id'=>$id]
        );

        if((int)($used['Total']??0)>0){
            $this->cmsDb->execute(
                "UPDATE ".self::PLANS." SET IsEnabled=0 WHERE PlanID=:id",
                ['id'=>$id]
            );
            return;
        }

        $this->cmsDb->execute(
            "DELETE FROM ".self::PLANS." WHERE PlanID=:id",
            ['id'=>$id]
        );
    }

    public function currentVip(string $account): array
    {
        $s=$this->settings();
        $table=$this->q($s['vip_table']);
        $user=$this->q($s['vip_user_column']);
        $type=$this->q($s['vip_type_column']);
        $expire=$this->q($s['vip_expire_column']);

        return $this->muDb->fetchOne(
            "SELECT TOP 1 {$type} VipType,{$expire} VipExpire
             FROM {$table}
             WHERE LTRIM(RTRIM({$user}))=:account",
            ['account'=>trim($account)]
        ) ?? ['VipType'=>0,'VipExpire'=>null];
    }

    public function balance(string $account): int
    {
        [$table,$user,$value]=$this->creditSource();

        $row=$this->muDb->fetchOne(
            "SELECT TOP 1 {$value} Balance
             FROM {$table}
             WHERE LTRIM(RTRIM({$user}))=:account",
            ['account'=>trim($account)]
        );

        return max(0,(int)($row['Balance']??0));
    }

    public function buy(string $account,int $planId,string $ip=''): void
    {
        $s=$this->settings();
        if(($s['enabled']??'0')!=='1'){
            throw new \RuntimeException('El servicio VIP está deshabilitado.');
        }

        $account=trim($account);
        if($account==='' || strlen($account)>20){
            throw new \RuntimeException('Cuenta inválida.');
        }

        if(($s['require_offline']??'1')==='1' && $this->accountConnected($account)){
            throw new \RuntimeException('Debes desconectar completamente la cuenta del juego.');
        }

        $plan=$this->cmsDb->fetchOne(
            "SELECT TOP 1 * FROM ".self::PLANS."
             WHERE PlanID=:id AND IsEnabled=1",
            ['id'=>$planId]
        );

        if(!$plan) throw new \RuntimeException('El plan VIP ya no está disponible.');
        if(!$this->accountExists($account)) throw new \RuntimeException('La cuenta no existe.');

        $current=$this->currentVip($account);
        $currentType=(int)($current['VipType']??0);
        $currentExpire=(string)($current['VipExpire']??'');
        $isActive=$currentType>0 && $currentExpire!=='' && strtotime($currentExpire)>time();

        if(
            $isActive &&
            $currentType!==(int)$plan['VipType'] &&
            ($s['allow_type_change']??'0')!=='1'
        ){
            throw new \RuntimeException('Tu membresía actual debe vencer antes de cambiar de tipo.');
        }

        $cost=$this->planCost($plan);
        $before=$this->balance($account);

        if($before<$cost){
            throw new \RuntimeException(
                'Saldo insuficiente. Necesitas '.number_format($cost).' '.
                ($s['currency_name']??'Créditos').'.'
            );
        }

        [$creditTable,$creditUser,$creditValue]=$this->creditSource();

        $vipTable=$this->q($s['vip_table']);
        $vipUser=$this->q($s['vip_user_column']);
        $vipType=$this->q($s['vip_type_column']);
        $vipExpire=$this->q($s['vip_expire_column']);

        $days=max(1,(int)$plan['DaysAmount']);
        $newType=max(1,(int)$plan['VipType']);

        try{
            $this->muDb->transaction(function(Database $db) use(
                $account,$cost,$creditTable,$creditUser,$creditValue,
                $vipTable,$vipUser,$vipType,$vipExpire,$days,$newType
            ): void {
                $row=$db->fetchOne(
                    "SELECT TOP 1 {$creditValue} Balance
                     FROM {$creditTable} WITH (UPDLOCK,HOLDLOCK)
                     WHERE LTRIM(RTRIM({$creditUser}))=:account",
                    ['account'=>$account]
                );

                if(!$row || (int)$row['Balance']<$cost){
                    throw new \RuntimeException('Saldo insuficiente.');
                }

                if($cost>0){
                    $changed=$db->execute(
                        "UPDATE {$creditTable}
                         SET {$creditValue}={$creditValue}-:cost
                         WHERE LTRIM(RTRIM({$creditUser}))=:account
                           AND {$creditValue}>=:cost",
                        ['cost'=>$cost,'account'=>$account]
                    );

                    if($changed!==1){
                        throw new \RuntimeException('No fue posible descontar el saldo.');
                    }
                }

                $changed=$db->execute(
                    "UPDATE {$vipTable}
                     SET {$vipType}=:newType,
                         {$vipExpire}=DATEADD(
                            DAY,CAST(:days AS INT),
                            CASE
                                WHEN {$vipType}=:sameType
                                 AND {$vipExpire}>GETDATE()
                                THEN {$vipExpire}
                                ELSE GETDATE()
                            END
                         )
                     WHERE LTRIM(RTRIM({$vipUser}))=:account",
                    [
                        'newType'=>$newType,
                        'days'=>$days,
                        'sameType'=>$newType,
                        'account'=>$account
                    ]
                );

                if($changed!==1){
                    throw new \RuntimeException('No fue posible acreditar la membresía VIP.');
                }
            });

            $this->writeLog($account,$plan,$cost,$before,'success',null,$ip);
        }catch(\Throwable $e){
            try{
                $this->writeLog($account,$plan,$cost,$before,'failed',$e->getMessage(),$ip);
            }catch(\Throwable){}
            throw $e;
        }
    }

    public function logs(int $limit=100,?string $account=null): array
    {
        $limit=max(1,min(500,$limit));

        $sql="SELECT TOP {$limit}
                l.LogID,l.AccountID,l.PlanID,l.VipType,l.VipDays,
                l.CreditsCharged,l.BalanceBefore,l.Status,
                l.ErrorMessage,l.ClientIP,l.CreatedAt,
                p.DisplayName
              FROM ".self::LOG." l
              LEFT JOIN ".self::PLANS." p ON p.PlanID=l.PlanID";

        $args=[];
        if($account!==null){
            $sql.=" WHERE LTRIM(RTRIM(l.AccountID))=:account";
            $args['account']=trim($account);
        }

        $sql.=" ORDER BY l.LogID DESC";
        return $this->cmsDb->fetchAll($sql,$args);
    }

    public function diagnostics(): array
    {
        $s=$this->settings();

        $checks=[
            'Tabla VIP'=>$s['vip_table'],
            'Tabla estado'=>$s['status_table'],
        ];

        if(($s['credit_backend']??'table')==='wcoinc'){
            $checks['Tabla WCoinC']=$s['wcoin_table'];
        }else{
            $checks['Tabla créditos']=$s['credits_table'];
        }

        $out=[];
        foreach($checks as $label=>$table){
            try{
                $safe=$this->identifier($table);
                $row=$this->muDb->fetchOne(
                    "SELECT CASE WHEN OBJECT_ID('dbo.{$safe}','U') IS NULL
                     THEN 0 ELSE 1 END Available"
                );
                $out[$label]=(int)($row['Available']??0)===1;
            }catch(\Throwable){
                $out[$label]=false;
            }
        }

        $out['Planes Omega']=true;
        $out['Historial Omega']=true;
        return $out;
    }

    public function parseBbcode(string $text): string
    {
        $text=htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

        $patterns=[
            '/\[b\](.*?)\[\/b\]/is',
            '/\[i\](.*?)\[\/i\]/is',
            '/\[u\](.*?)\[\/u\]/is',
            '/\[center\](.*?)\[\/center\]/is',
            '/\[br\]/i',
            '/\[color=(#[a-f0-9]{3,6}|[a-z]+)\](.*?)\[\/color\]/is',
            '/\[size=(1[0-9]|2[0-8])\](.*?)\[\/size\]/is',
            '/\[url=(https?:\/\/[^\]\s]+)\](.*?)\[\/url\]/is',
            '/\[list\](.*?)\[\/list\]/is',
            '/\[\*\](.*?)(?=\[\*\]|$)/is',
        ];

        $replace=[
            '<strong>$1</strong>',
            '<em>$1</em>',
            '<u>$1</u>',
            '<div class="vippro-bb-center">$1</div>',
            '<br>',
            '<span style="color:$1">$2</span>',
            '<span style="font-size:$1px">$2</span>',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$2</a>',
            '<ul class="vippro-bb-list">$1</ul>',
            '<li>$1</li>',
        ];

        return nl2br((string)preg_replace($patterns,$replace,$text));
    }

    private function writeLog(
        string $account,array $plan,int $cost,int $before,
        string $status,?string $error,string $ip
    ): void {
        $this->cmsDb->execute(
            "INSERT INTO ".self::LOG."
             (AccountID,PlanID,VipType,VipDays,CreditsCharged,
              BalanceBefore,Status,ErrorMessage,ClientIP)
             VALUES(:account,:plan,:type,:days,:cost,:before,:status,:error,:ip)",
            [
                'account'=>$account,
                'plan'=>(int)$plan['PlanID'],
                'type'=>(int)$plan['VipType'],
                'days'=>(int)$plan['DaysAmount'],
                'cost'=>$cost,
                'before'=>$before,
                'status'=>$status,
                'error'=>$error,
                'ip'=>mb_substr($ip,0,64),
            ]
        );
    }

    private function creditSource(): array
    {
        $s=$this->settings();

        if(($s['credit_backend']??'table')==='wcoinc'){
            return [
                $this->q($s['wcoin_table']),
                $this->q($s['wcoin_user_column']),
                $this->q($s['wcoin_value_column']),
            ];
        }

        return [
            $this->q($s['credits_table']),
            $this->q($s['credits_user_column']),
            $this->q($s['credits_value_column']),
        ];
    }

    private function accountExists(string $account): bool
    {
        $s=$this->settings();
        $table=$this->q($s['vip_table']);
        $user=$this->q($s['vip_user_column']);

        return (bool)$this->muDb->fetchOne(
            "SELECT TOP 1 {$user} AccountID
             FROM {$table}
             WHERE LTRIM(RTRIM({$user}))=:account",
            ['account'=>$account]
        );
    }

    private function accountConnected(string $account): bool
    {
        $s=$this->settings();
        $table=$this->q($s['status_table']);
        $user=$this->q($s['status_user_column']);
        $value=$this->q($s['status_value_column']);

        $row=$this->muDb->fetchOne(
            "SELECT TOP 1 {$value} ConnectStat
             FROM {$table}
             WHERE LTRIM(RTRIM({$user}))=:account",
            ['account'=>$account]
        );

        return (int)($row['ConnectStat']??0)>0;
    }

    private function insertPlan(
        int $type,string $name,string $button,int $days,int $cost,int $discount,
        int $enabled,int $sort,string $description,string $info,string $icon,string $accent
    ): void {
        $this->cmsDb->execute(
            "INSERT INTO ".self::PLANS."
             (VipType,DisplayName,ButtonLabel,DaysAmount,BaseCost,
              DiscountPercent,IsEnabled,SortOrder,Description,
              InfoContent,CardIcon,AccentClass)
             VALUES(:type,:name,:button,:days,:cost,:discount,:enabled,
                    :sort,:description,:info,:icon,:accent)",
            compact(
                'type','name','button','days','cost','discount',
                'enabled','sort','description','info','icon','accent'
            )
        );
    }

    private function q(string $value): string
    {
        return '['.$this->identifier($value).']';
    }

    private function identifier(string $value): string
    {
        $value=trim($value);
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/',$value)){
            throw new \RuntimeException('Identificador SQL inválido: '.$value);
        }
        return $value;
    }

    private function accent(string $value): string
    {
        $value=preg_replace('/[^a-z0-9_-]/i','',trim($value))??'';
        return $value!=='' ? mb_substr($value,0,30) : 'gold';
    }

    private function text(string $value,int $max): string
    {
        return mb_substr(trim(str_replace(["\0","\r"],'',$value)),0,$max);
    }
}
