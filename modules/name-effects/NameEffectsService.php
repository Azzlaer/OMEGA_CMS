<?php
declare(strict_types=1);

use Omega\Core\Database;
use Omega\Core\Security;

final class OmegaNameEffectsService
{
    public function __construct(
        private readonly Database $db,
        private readonly array $config,
        private readonly string $moduleDir
    ){
        $this->ensureSchema();
        $this->seedOriginalEffects();
        $this->cleanupExpired();
    }

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.OmegaNameEffects','U') IS NULL
            CREATE TABLE dbo.OmegaNameEffects(
                EffectID INT IDENTITY(1,1) PRIMARY KEY,
                EffectName VARCHAR(120) NOT NULL,
                EffectKey VARCHAR(80) NOT NULL UNIQUE,
                ImagePath VARCHAR(500) NULL,
                TextColor VARCHAR(20) NULL,
                GlowColor VARCHAR(20) NULL,
                GlowSize INT NOT NULL DEFAULT 0,
                CustomCss VARCHAR(1500) NULL,
                CostType VARCHAR(30) NOT NULL DEFAULT 'ZEN',
                CostAmount INT NOT NULL DEFAULT 0,
                FreeAllowed BIT NOT NULL DEFAULT 1,
                VipAllowed BIT NOT NULL DEFAULT 1,
                DurationType VARCHAR(20) NOT NULL DEFAULT 'days',
                DurationDays INT NOT NULL DEFAULT 30,
                SortOrder INT NOT NULL DEFAULT 0,
                IsActive BIT NOT NULL DEFAULT 1,
                Notes VARCHAR(500) NULL,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                UpdatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
        $this->db->execute("
            IF OBJECT_ID('dbo.OmegaNameEffectAssignments','U') IS NULL
            CREATE TABLE dbo.OmegaNameEffectAssignments(
                AssignmentID BIGINT IDENTITY(1,1) PRIMARY KEY,
                AccountID VARCHAR(10) NOT NULL,
                CharacterName VARCHAR(10) NOT NULL,
                EffectID INT NOT NULL,
                ActivatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                ExpiresAt DATETIME2 NULL,
                IsActive BIT NOT NULL DEFAULT 1,
                LastUpdate DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
        $this->db->execute("
            IF OBJECT_ID('dbo.OmegaNameEffectsLog','U') IS NULL
            CREATE TABLE dbo.OmegaNameEffectsLog(
                LogID BIGINT IDENTITY(1,1) PRIMARY KEY,
                AccountID VARCHAR(10) NOT NULL,
                CharacterName VARCHAR(10) NOT NULL,
                EffectID INT NULL,
                EffectName VARCHAR(120) NULL,
                UserTier VARCHAR(20) NULL,
                CostType VARCHAR(30) NULL,
                CostAmount INT NOT NULL DEFAULT 0,
                Status VARCHAR(20) NOT NULL,
                Message VARCHAR(500) NULL,
                CreatedAt DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
    }

    public function cleanupExpired(): int
    {
        return $this->db->execute("
            UPDATE OmegaNameEffectAssignments
            SET IsActive=0,LastUpdate=SYSUTCDATETIME()
            WHERE IsActive=1 AND ExpiresAt IS NOT NULL AND ExpiresAt<=SYSUTCDATETIME()
        ");
    }


    private function seedOriginalEffects(): void
    {
        $presets=[
            ['effect_01_white_sparks','White Sparks','assets/effects/effect_01_white_sparks.gif',51000000,1,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','sparkles blancos clásicos'],
            ['effect_02_green_twinkles','Green Twinkles','assets/effects/effect_02_green_twinkles.gif',52000000,2,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','brillos verdes'],
            ['effect_03_white_dots','White Dots','assets/effects/effect_03_white_dots.gif',53000000,3,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','partículas blancas'],
            ['effect_04_pink_bar','Pink Bar','assets/effects/effect_04_pink_bar.gif',54000000,4,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','barra rosa'],
            ['effect_05_red_cross','Red Cross','assets/effects/effect_05_red_cross.gif',55000000,5,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','destellos rojos'],
            ['effect_06_rainbow_rain','Rainbow Rain','assets/effects/effect_06_rainbow_rain.gif',56000000,6,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','lluvia arcoíris'],
            ['effect_07_blue_gold_star','Blue Gold Star','assets/effects/effect_07_blue_gold_star.gif',57000000,7,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','estrellas azules y doradas'],
            ['effect_08_fire_aura','Fire Aura','assets/effects/effect_08_fire_aura.gif',58000000,8,'background-repeat:no-repeat;background-position:center center;background-size:contain;padding:4px 12px;border-radius:4px;','aura de fuego'],
            ['effect_09_magic_star_purple','Magic Star Purple','assets/effects/effect_09_magic_star_purple.gif',59000000,9,'background-repeat:no-repeat;background-position:center center;background-size:contain;padding:4px 12px;border-radius:4px;','estrella mágica púrpura'],
            ['effect_10_golden_fairy','Golden Fairy','assets/effects/effect_10_golden_fairy.gif',60000000,10,'background-repeat:no-repeat;background-position:center center;background-size:contain;padding:4px 12px;border-radius:4px;','hadita dorada'],
            ['effect_11_candy_ribbons','Candy Ribbons','assets/effects/effect_11_candy_ribbons.gif',61000000,11,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','cintas de colores'],
            ['effect_12_blue_snow','Blue Snow','assets/effects/effect_12_blue_snow.gif',62000000,12,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','copos azules'],
            ['effect_13_blue_sparkles','Blue Sparkles','assets/effects/effect_13_blue_sparkles.gif',63000000,13,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','destellos azules'],
            ['effect_14_gold_line','Gold Line','assets/effects/effect_14_gold_line.gif',64000000,14,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','línea dorada'],
            ['effect_15_white_twinkles','White Twinkles','assets/effects/effect_15_white_twinkles.gif',65000000,15,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','brillos blancos'],
            ['effect_16_ice_flame','Ice Flame','assets/effects/effect_16_ice_flame.gif',66000000,16,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','llama helada'],
            ['effect_17_red_orb','Red Orb','assets/effects/effect_17_red_orb.gif',67000000,17,'background-repeat:no-repeat;background-position:center center;background-size:contain;padding:4px 12px;border-radius:4px;','orbe rojo'],
            ['effect_18_snowflakes','Snowflakes','assets/effects/effect_18_snowflakes.gif',68000000,18,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','copos de nieve'],
            ['effect_19_gold_line_2','Gold Line II','assets/effects/effect_19_gold_line_2.gif',69000000,19,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','línea dorada 2'],
            ['effect_20_blue_twinkles_pair','Blue Twinkles Pair','assets/effects/effect_20_blue_twinkles_pair.gif',70000000,20,'background-repeat:repeat-x;background-position:center center;background-size:auto 100%;padding:2px 10px;border-radius:4px;','doble brillo azul'],
        ];

        foreach($presets as [$key,$name,$image,$cost,$sort,$css,$notes]){
            $exists=$this->db->fetchOne(
                "SELECT EffectID FROM OmegaNameEffects WHERE EffectKey=:k",
                ['k'=>$key]
            );
            if($exists) continue;

            $this->db->execute("
                INSERT INTO OmegaNameEffects(
                    EffectName,EffectKey,ImagePath,TextColor,GlowColor,GlowSize,CustomCss,
                    CostType,CostAmount,FreeAllowed,VipAllowed,DurationType,DurationDays,
                    SortOrder,IsActive,Notes
                )
                VALUES(
                    :n,:k,:img,'#ffffff','#d4af37',0,:css,
                    'ZEN',:cost,1,1,'days',30,:sort,1,:notes
                )
            ",[
                'n'=>$name,'k'=>$key,'img'=>$image,'css'=>$css,
                'cost'=>$cost,'sort'=>$sort,'notes'=>$notes
            ]);
        }
    }

    public function assetUrl(string $path): string
    {
        $path=trim(str_replace('\\','/',$path));
        if($path==='') return '';
        if(preg_match('#^https?://#i',$path) || str_starts_with($path,'/public/')) return $path;

        if(str_starts_with($path,'assets/')){
            $parts=array_values(array_filter(explode('/',$path),fn($p)=>$p!==''&&$p!=='.'));
            foreach($parts as $part){
                if($part==='..') return '';
            }
            return '/module-assets/name-effects/'.implode('/',array_map('rawurlencode',$parts));
        }
        return '';
    }

    public function catalog(bool $activeOnly=true): array
    {
        $sql="SELECT * FROM OmegaNameEffects";
        if($activeOnly)$sql.=" WHERE IsActive=1";
        $sql.=" ORDER BY SortOrder ASC,EffectName ASC,EffectID ASC";
        return $this->db->fetchAll($sql);
    }

    public function effect(int $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM OmegaNameEffects WHERE EffectID=:id",['id'=>$id]);
    }

    public function saveEffect(array $d): int
    {
        $id=(int)($d['EffectID']??0);
        $name=trim((string)($d['EffectName']??''));
        $key=strtolower(trim((string)($d['EffectKey']??'')));
        $img=trim((string)($d['ImagePath']??''));
        $costType=strtoupper(trim((string)($d['CostType']??'ZEN')));
        $cost=max(0,(int)($d['CostAmount']??0));
        $duration=in_array(($d['DurationType']??'days'),['days','permanent'],true)?$d['DurationType']:'days';
        $days=max(1,min(3650,(int)($d['DurationDays']??30)));
        $sort=max(-9999,min(9999,(int)($d['SortOrder']??0)));
        $active=!empty($d['IsActive'])?1:0;
        $free=!empty($d['FreeAllowed'])?1:0;
        $vip=!empty($d['VipAllowed'])?1:0;
        $text=$this->color((string)($d['TextColor']??'#ffffff'),'#ffffff');
        $glow=$this->color((string)($d['GlowColor']??'#d4af37'),'#d4af37');
        $glowSize=max(0,min(25,(int)($d['GlowSize']??0)));
        $css=$this->sanitizeCss((string)($d['CustomCss']??''));
        $notes=mb_substr(trim((string)($d['Notes']??'')),0,500);

        if($name==='')throw new RuntimeException('El efecto necesita nombre.');
        if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,78}[a-z0-9]$/',$key))
            throw new RuntimeException('Clave de efecto inválida.');

        $allowed=['FREE','ZEN','WCOINC','LEVEL','POINTS','RESETS','GRANDRESETS'];
        if(!in_array($costType,$allowed,true))throw new RuntimeException('Tipo de costo inválido.');

        if($img!=='' && !preg_match('#^(https?://|/public/|assets/)#i',$img))
            throw new RuntimeException('Ruta de imagen inválida.');

        $params=['n'=>$name,'k'=>$key,'img'=>$img?:null,'tc'=>$text,'gc'=>$glow,'gs'=>$glowSize,'css'=>$css?:null,
            'ct'=>$costType,'ca'=>$cost,'free'=>$free,'vip'=>$vip,'dt'=>$duration,'dd'=>$days,'so'=>$sort,'active'=>$active,'notes'=>$notes];

        if($id>0){
            $params['id']=$id;
            $this->db->execute("
                UPDATE OmegaNameEffects SET EffectName=:n,EffectKey=:k,ImagePath=:img,TextColor=:tc,
                GlowColor=:gc,GlowSize=:gs,CustomCss=:css,CostType=:ct,CostAmount=:ca,
                FreeAllowed=:free,VipAllowed=:vip,DurationType=:dt,DurationDays=:dd,
                SortOrder=:so,IsActive=:active,Notes=:notes,UpdatedAt=SYSUTCDATETIME()
                WHERE EffectID=:id
            ",$params);
            return $id;
        }

        $this->db->execute("
            INSERT INTO OmegaNameEffects(EffectName,EffectKey,ImagePath,TextColor,GlowColor,GlowSize,CustomCss,
                CostType,CostAmount,FreeAllowed,VipAllowed,DurationType,DurationDays,SortOrder,IsActive,Notes)
            VALUES(:n,:k,:img,:tc,:gc,:gs,:css,:ct,:ca,:free,:vip,:dt,:dd,:so,:active,:notes)
        ",$params);
        $r=$this->db->fetchOne("SELECT TOP 1 EffectID FROM OmegaNameEffects WHERE EffectKey=:k",['k'=>$key]);
        return (int)($r['EffectID']??0);
    }

    public function toggle(int $id): void
    {
        $this->db->execute("UPDATE OmegaNameEffects SET IsActive=CASE WHEN IsActive=1 THEN 0 ELSE 1 END,UpdatedAt=SYSUTCDATETIME() WHERE EffectID=:id",['id'=>$id]);
    }

    public function delete(int $id): void
    {
        if($this->db->fetchOne("SELECT TOP 1 AssignmentID FROM OmegaNameEffectAssignments WHERE EffectID=:id AND IsActive=1",['id'=>$id]))
            throw new RuntimeException('No puedes eliminar un efecto actualmente asignado.');
        $this->db->execute("DELETE FROM OmegaNameEffects WHERE EffectID=:id",['id'=>$id]);
    }

    public function activeFor(string $character): ?array
    {
        return $this->db->fetchOne("
            SELECT TOP 1 e.*,a.ExpiresAt,a.AssignmentID
            FROM OmegaNameEffectAssignments a
            INNER JOIN OmegaNameEffects e ON e.EffectID=a.EffectID
            WHERE a.CharacterName=:n AND a.IsActive=1 AND e.IsActive=1
              AND (a.ExpiresAt IS NULL OR a.ExpiresAt>SYSUTCDATETIME())
            ORDER BY a.AssignmentID DESC
        ",['n'=>$character]);
    }

    public function characters(string $account): array
    {
        return $this->db->fetchAll("
            SELECT Name,Class,cLevel,ResetCount,GrandResetCount,LevelUpPoint,Money
            FROM Character WHERE AccountID=:a AND ISNULL(CtlCode,0)<8 ORDER BY Name
        ",['a'=>$account]);
    }

    public function userTier(string $account): string
    {
        $r=$this->db->fetchOne("SELECT AccountLevel FROM MEMB_INFO WHERE memb___id=:a",['a'=>$account]);
        return (int)($r['AccountLevel']??0)>=(int)($this->config['vip_min_level']??1)?'VIP':'FREE';
    }

    public function isOnline(string $account): bool
    {
        $r=$this->db->fetchOne("SELECT ConnectStat FROM MEMB_STAT WHERE memb___id=:a",['a'=>$account]);
        return (int)($r['ConnectStat']??0)===1;
    }

    public function buy(string $account,string $character,int $effectId): void
    {
        if(!preg_match('/^[A-Za-z0-9]{1,10}$/',$character))throw new RuntimeException('Personaje inválido.');

        $char=$this->db->fetchOne("SELECT * FROM Character WHERE AccountID=:a AND Name=:n",['a'=>$account,'n'=>$character]);
        if(!$char)throw new RuntimeException('El personaje no pertenece a tu cuenta.');
        if(!empty($this->config['require_offline']) && $this->isOnline($account))
            throw new RuntimeException('Debes desconectar la cuenta del juego antes de aplicar un efecto.');

        $effect=$this->effect($effectId);
        if(!$effect || !(int)$effect['IsActive'])throw new RuntimeException('Efecto no disponible.');

        $tier=$this->userTier($account);
        if($tier==='VIP' && !(int)$effect['VipAllowed'])throw new RuntimeException('Este efecto no está disponible para VIP.');
        if($tier==='FREE' && !(int)$effect['FreeAllowed'])throw new RuntimeException('Este efecto no está disponible para usuarios normales.');
        if($tier==='VIP' && empty($this->config['vip_access']))throw new RuntimeException('Acceso VIP deshabilitado.');
        if($tier==='FREE' && empty($this->config['free_access']))throw new RuntimeException('Acceso normal deshabilitado.');

        $this->db->transaction(function(Database $db) use($account,$character,$effect,$char,$tier): void {
            $this->deduct($account,$character,$effect,$char);

            $existing=$this->activeFor($character);
            $baseExpiry=null;
            if($existing && (int)$existing['EffectID']===(int)$effect['EffectID'] && !empty($existing['ExpiresAt'])){
                $baseExpiry=(string)$existing['ExpiresAt'];
            }

            $db->execute("UPDATE OmegaNameEffectAssignments SET IsActive=0,LastUpdate=SYSUTCDATETIME() WHERE CharacterName=:n AND IsActive=1",['n'=>$character]);

            if($effect['DurationType']==='permanent'){
                $db->execute("
                    INSERT INTO OmegaNameEffectAssignments(AccountID,CharacterName,EffectID,ExpiresAt)
                    VALUES(:a,:n,:e,NULL)
                ",['a'=>$account,'n'=>$character,'e'=>(int)$effect['EffectID']]);
            }elseif($baseExpiry){
                $db->execute("
                    INSERT INTO OmegaNameEffectAssignments(AccountID,CharacterName,EffectID,ExpiresAt)
                    VALUES(:a,:n,:e,DATEADD(DAY,CAST(:d AS INT),CASE WHEN CAST(:base AS DATETIME2)>SYSUTCDATETIME() THEN CAST(:base2 AS DATETIME2) ELSE SYSUTCDATETIME() END))
                ",['a'=>$account,'n'=>$character,'e'=>(int)$effect['EffectID'],'d'=>(int)$effect['DurationDays'],'base'=>$baseExpiry,'base2'=>$baseExpiry]);
            }else{
                $db->execute("
                    INSERT INTO OmegaNameEffectAssignments(AccountID,CharacterName,EffectID,ExpiresAt)
                    VALUES(:a,:n,:e,DATEADD(DAY,CAST(:d AS INT),SYSUTCDATETIME()))
                ",['a'=>$account,'n'=>$character,'e'=>(int)$effect['EffectID'],'d'=>(int)$effect['DurationDays']]);
            }

            $db->execute("
                INSERT INTO OmegaNameEffectsLog(AccountID,CharacterName,EffectID,EffectName,UserTier,CostType,CostAmount,Status,Message)
                VALUES(:a,:n,:e,:en,:tier,:ct,:ca,'success','Efecto aplicado correctamente.')
            ",['a'=>$account,'n'=>$character,'e'=>(int)$effect['EffectID'],'en'=>$effect['EffectName'],'tier'=>$tier,'ct'=>$effect['CostType'],'ca'=>(int)$effect['CostAmount']]);
        });
    }

    public function remove(string $account,string $character): void
    {
        $this->db->execute("UPDATE OmegaNameEffectAssignments SET IsActive=0,LastUpdate=SYSUTCDATETIME() WHERE AccountID=:a AND CharacterName=:n AND IsActive=1",['a'=>$account,'n'=>$character]);
    }

    public function logs(int $limit=100): array
    {
        $limit=max(1,min(500,$limit));
        return $this->db->fetchAll("SELECT TOP {$limit} * FROM OmegaNameEffectsLog ORDER BY LogID DESC");
    }

    public function render(string $name): ?string
    {
        $fx=$this->activeFor($name);
        if(!$fx)return null;

        $style=[];
        if(!empty($fx['ImagePath'])){
            $img=$this->assetUrl((string)$fx['ImagePath']);
            if($img!==''){
                $style[]='background-image:url("'.str_replace(['"',"'",')'],['%22','%27','%29'],$img).'")';
            }
            $style[]='background-repeat:repeat-x';
            $style[]='background-position:center';
            $style[]='background-size:auto 100%';
        }
        $style[]='color:'.$this->color((string)$fx['TextColor'],'#ffffff');
        if((int)$fx['GlowSize']>0){
            $style[]='text-shadow:0 0 '.min(25,(int)$fx['GlowSize']).'px '.$this->color((string)$fx['GlowColor'],'#d4af37');
        }
        if(!empty($fx['CustomCss']))$style[]=$this->sanitizeCss((string)$fx['CustomCss']);

        return '<span class="omega-name-effect" data-effect="'.Security::e((string)$fx['EffectKey']).'" style="'.Security::e(implode(';',$style)).'">'.Security::e($name).'</span>';
    }

    private function deduct(string $account,string $character,array $effect,array $char): void
    {
        $type=(string)$effect['CostType'];$amount=max(0,(int)$effect['CostAmount']);
        if($type==='FREE'||$amount===0)return;

        if($type==='ZEN'){
            if(empty($this->config['allow_zen']))throw new RuntimeException('Costos en Zen desactivados.');
            if((int)$char['Money']<$amount)throw new RuntimeException('No tienes suficiente Zen.');
            $this->db->execute("UPDATE Character SET Money=Money-:n WHERE AccountID=:a AND Name=:c",['n'=>$amount,'a'=>$account,'c'=>$character]);
            return;
        }
        if($type==='POINTS'){
            if(empty($this->config['allow_points']))throw new RuntimeException('Costos en puntos desactivados.');
            if((int)$char['LevelUpPoint']<$amount)throw new RuntimeException('No tienes suficientes puntos.');
            $this->db->execute("UPDATE Character SET LevelUpPoint=LevelUpPoint-:n WHERE AccountID=:a AND Name=:c",['n'=>$amount,'a'=>$account,'c'=>$character]);
            return;
        }
        if($type==='LEVEL'){
            if(empty($this->config['allow_level']))throw new RuntimeException('Costos en nivel desactivados.');
            if((int)$char['cLevel']<=$amount)throw new RuntimeException('Nivel insuficiente.');
            $this->db->execute("UPDATE Character SET cLevel=cLevel-:n WHERE AccountID=:a AND Name=:c",['n'=>$amount,'a'=>$account,'c'=>$character]);
            return;
        }
        if($type==='RESETS'){
            if(empty($this->config['allow_resets']))throw new RuntimeException('Costos en Reset desactivados.');
            if((int)$char['ResetCount']<$amount)throw new RuntimeException('Resets insuficientes.');
            $this->db->execute("UPDATE Character SET ResetCount=ResetCount-:n WHERE AccountID=:a AND Name=:c",['n'=>$amount,'a'=>$account,'c'=>$character]);
            return;
        }
        if($type==='GRANDRESETS'){
            if(empty($this->config['allow_grandresets']))throw new RuntimeException('Costos en Grand Reset desactivados.');
            if((int)$char['GrandResetCount']<$amount)throw new RuntimeException('Grand Resets insuficientes.');
            $this->db->execute("UPDATE Character SET GrandResetCount=GrandResetCount-:n WHERE AccountID=:a AND Name=:c",['n'=>$amount,'a'=>$account,'c'=>$character]);
            return;
        }
        if($type==='WCOINC'){
            if(empty($this->config['allow_wcoinc']))throw new RuntimeException('Costos WCoinC desactivados.');
            $exists=$this->db->fetchOne("SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='CashShopData' AND TABLE_SCHEMA='dbo'");
            if((int)($exists['total']??0)<1)throw new RuntimeException('CashShopData no existe.');
            $coin=$this->db->fetchOne("SELECT WCoinC FROM CashShopData WHERE AccountID=:a",['a'=>$account]);
            if(!$coin||(int)$coin['WCoinC']<$amount)throw new RuntimeException('WCoinC insuficiente.');
            $this->db->execute("UPDATE CashShopData SET WCoinC=WCoinC-:n WHERE AccountID=:a",['n'=>$amount,'a'=>$account]);
            return;
        }
        throw new RuntimeException('Tipo de costo no soportado.');
    }

    private function color(string $value,string $default): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/',$value)?strtolower($value):$default;
    }

    private function sanitizeCss(string $css): string
    {
        $css=trim($css);
        if($css==='')return '';
        if(strlen($css)>1500)$css=substr($css,0,1500);
        $css=preg_replace('/url\s*\([^)]*\)/i','',$css)??'';
        $css=preg_replace('/@import\b[^;]*;?/i','',$css)??'';
        $css=preg_replace('/expression\s*\([^)]*\)/i','',$css)??'';
        $css=preg_replace('/javascript\s*:/i','',$css)??'';
        $css=preg_replace('/behavior\s*:/i','',$css)??'';
        return $css;
    }
}
