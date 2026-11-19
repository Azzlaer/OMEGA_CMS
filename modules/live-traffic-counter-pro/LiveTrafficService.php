<?php
declare(strict_types=1);

use Omega\Core\Database;
use Omega\Core\Env;

final class OmegaLiveTrafficService
{
    private const DEFAULTS=[
        'enabled'=>'1',
        'widget_type'=>'summary',
        'placement'=>'footer',
        'online_window'=>'5',
        'refresh_seconds'=>'15',
        'show_online'=>'1',
        'show_today'=>'1',
        'show_total'=>'1',
        'show_pageviews'=>'1',
        'exclude_bots'=>'1',
        'label'=>'Visitantes',
        'accent'=>'#d5a33d',
        'floating_side'=>'right',
    ];

    public function __construct(private readonly Database $db)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.cms_traffic_settings','U') IS NULL
            CREATE TABLE dbo.cms_traffic_settings(
                setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
                setting_value NVARCHAR(MAX) NULL,
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
        $this->db->execute("
            IF OBJECT_ID('dbo.cms_traffic_visitors','U') IS NULL
            CREATE TABLE dbo.cms_traffic_visitors(
                visitor_hash CHAR(64) NOT NULL PRIMARY KEY,
                first_seen DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                last_seen DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                pageviews BIGINT NOT NULL DEFAULT 1,
                last_path NVARCHAR(500) NULL,
                device_type VARCHAR(20) NULL
            )
        ");
        $this->db->execute("
            IF OBJECT_ID('dbo.cms_traffic_daily','U') IS NULL
            CREATE TABLE dbo.cms_traffic_daily(
                visit_day DATE NOT NULL,
                visitor_hash CHAR(64) NOT NULL,
                pageviews INT NOT NULL DEFAULT 1,
                first_seen DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                last_seen DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                CONSTRAINT PK_cms_traffic_daily PRIMARY KEY(visit_day,visitor_hash)
            )
        ");
        $this->db->execute("
            IF NOT EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name='IX_cms_traffic_visitors_last_seen'
                AND object_id=OBJECT_ID('dbo.cms_traffic_visitors')
            )
            CREATE INDEX IX_cms_traffic_visitors_last_seen
            ON dbo.cms_traffic_visitors(last_seen)
        ");
        $this->db->execute("
            IF NOT EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name='IX_cms_traffic_daily_day_last'
                AND object_id=OBJECT_ID('dbo.cms_traffic_daily')
            )
            CREATE INDEX IX_cms_traffic_daily_day_last
            ON dbo.cms_traffic_daily(visit_day,last_seen)
        ");
    }

    public function settings(): array
    {
        $out=self::DEFAULTS;
        try{
            foreach($this->db->fetchAll("SELECT setting_key,setting_value FROM dbo.cms_traffic_settings") as $row){
                $key=(string)($row['setting_key']??'');
                if(array_key_exists($key,$out)) $out[$key]=(string)($row['setting_value']??'');
            }
        }catch(Throwable){}
        return $out;
    }

    public function save(array $input): void
    {
        $widget=(string)($input['widget_type']??'summary');
        if(!in_array($widget,['online','total','today','pageviews','summary','compact','bar','floating'],true)) $widget='summary';

        $placement=(string)($input['placement']??'footer');
        if(!in_array($placement,['footer','content_top','content_bottom','floating'],true)) $placement='footer';

        $side=(string)($input['floating_side']??'right');
        if(!in_array($side,['left','right'],true)) $side='right';

        $accent=trim((string)($input['accent']??'#d5a33d'));
        if(!preg_match('/^#[0-9a-f]{6}$/i',$accent)) $accent='#d5a33d';

        $label=trim(strip_tags((string)($input['label']??'Visitantes')));
        $label=mb_substr($label,0,60);

        $values=[
            'enabled'=>!empty($input['enabled'])?'1':'0',
            'widget_type'=>$widget,
            'placement'=>$placement,
            'online_window'=>(string)max(1,min(60,(int)($input['online_window']??5))),
            'refresh_seconds'=>(string)max(5,min(120,(int)($input['refresh_seconds']??15))),
            'show_online'=>!empty($input['show_online'])?'1':'0',
            'show_today'=>!empty($input['show_today'])?'1':'0',
            'show_total'=>!empty($input['show_total'])?'1':'0',
            'show_pageviews'=>!empty($input['show_pageviews'])?'1':'0',
            'exclude_bots'=>!empty($input['exclude_bots'])?'1':'0',
            'label'=>$label===''?'Visitantes':$label,
            'accent'=>$accent,
            'floating_side'=>$side,
        ];

        foreach($values as $key=>$value){
            $this->db->execute("
                MERGE dbo.cms_traffic_settings AS target
                USING (
                    SELECT CAST(:k AS VARCHAR(120)) AS setting_key,
                           CAST(:v AS NVARCHAR(MAX)) AS setting_value
                ) AS source
                ON target.setting_key=source.setting_key
                WHEN MATCHED THEN UPDATE SET
                    setting_value=source.setting_value,
                    updated_at=SYSUTCDATETIME()
                WHEN NOT MATCHED THEN
                    INSERT(setting_key,setting_value)
                    VALUES(source.setting_key,source.setting_value);
            ",['k'=>$key,'v'=>$value]);
        }
    }

    public function track(string $path): void
    {
        $s=$this->settings();
        if(($s['enabled']??'0')!=='1') return;

        $ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
        if(($s['exclude_bots']??'1')==='1' && $this->isBot($ua)) return;

        $token=$this->visitorToken();
        if($token==='') return;

        $key=(string)Env::get('APP_KEY','omega-traffic');
        $hash=hash_hmac('sha256',$token,$key);
        $path=mb_substr($path,0,500);
        $device=$this->deviceType($ua);

        $this->db->execute("
            MERGE dbo.cms_traffic_visitors AS target
            USING (
                SELECT CAST(:h AS CHAR(64)) AS visitor_hash
            ) AS source
            ON target.visitor_hash=source.visitor_hash
            WHEN MATCHED THEN UPDATE SET
                last_seen=SYSUTCDATETIME(),
                pageviews=target.pageviews+1,
                last_path=:p,
                device_type=:d
            WHEN NOT MATCHED THEN
                INSERT(visitor_hash,first_seen,last_seen,pageviews,last_path,device_type)
                VALUES(source.visitor_hash,SYSUTCDATETIME(),SYSUTCDATETIME(),1,:p2,:d2);
        ",['h'=>$hash,'p'=>$path,'d'=>$device,'p2'=>$path,'d2'=>$device]);

        $this->db->execute("
            MERGE dbo.cms_traffic_daily AS target
            USING (
                SELECT CAST(CONVERT(date,SYSDATETIME()) AS DATE) AS visit_day,
                       CAST(:h AS CHAR(64)) AS visitor_hash
            ) AS source
            ON target.visit_day=source.visit_day
               AND target.visitor_hash=source.visitor_hash
            WHEN MATCHED THEN UPDATE SET
                pageviews=target.pageviews+1,
                last_seen=SYSUTCDATETIME()
            WHEN NOT MATCHED THEN
                INSERT(visit_day,visitor_hash,pageviews,first_seen,last_seen)
                VALUES(source.visit_day,source.visitor_hash,1,SYSUTCDATETIME(),SYSUTCDATETIME());
        ",['h'=>$hash]);
    }

    public function stats(): array
    {
        $s=$this->settings();
        $window=max(1,min(60,(int)($s['online_window']??5)));

        $online=(int)($this->db->fetchOne("
            SELECT COUNT(*) AS n
            FROM dbo.cms_traffic_visitors
            WHERE last_seen>=DATEADD(MINUTE,-:m,SYSUTCDATETIME())
        ",['m'=>$window])['n']??0);

        $total=(int)($this->db->fetchOne("
            SELECT COUNT(*) AS n FROM dbo.cms_traffic_visitors
        ")['n']??0);

        $pageviews=(int)($this->db->fetchOne("
            SELECT COALESCE(SUM(pageviews),0) AS n FROM dbo.cms_traffic_visitors
        ")['n']??0);

        $todayRow=$this->db->fetchOne("
            SELECT COUNT(*) AS unique_today,COALESCE(SUM(pageviews),0) AS pageviews_today
            FROM dbo.cms_traffic_daily
            WHERE visit_day=CONVERT(date,SYSDATETIME())
        ")??[];

        $devices=['desktop'=>0,'mobile'=>0,'other'=>0];
        foreach($this->db->fetchAll("
            SELECT COALESCE(device_type,'other') AS device_type,COUNT(*) AS n
            FROM dbo.cms_traffic_visitors
            WHERE last_seen>=DATEADD(MINUTE,-:m,SYSUTCDATETIME())
            GROUP BY COALESCE(device_type,'other')
        ",['m'=>$window]) as $row){
            $k=(string)($row['device_type']??'other');
            if(!array_key_exists($k,$devices)) $k='other';
            $devices[$k]=(int)($row['n']??0);
        }

        return [
            'online'=>$online,
            'unique_total'=>$total,
            'pageviews_total'=>$pageviews,
            'unique_today'=>(int)($todayRow['unique_today']??0),
            'pageviews_today'=>(int)($todayRow['pageviews_today']??0),
            'devices'=>$devices,
            'online_window'=>$window,
            'generated_at'=>gmdate('c'),
        ];
    }

    public function reset(): void
    {
        $this->db->execute("DELETE FROM dbo.cms_traffic_daily");
        $this->db->execute("DELETE FROM dbo.cms_traffic_visitors");
    }

    private function visitorToken(): string
    {
        $name='omega_visitor';
        $existing=(string)($_COOKIE[$name]??'');
        if(preg_match('/^[a-f0-9]{32}$/',$existing)) return $existing;

        try{$token=bin2hex(random_bytes(16));}
        catch(Throwable){return '';}

        if(!headers_sent()){
            setcookie($name,$token,[
                'expires'=>time()+31536000,
                'path'=>'/',
                'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
                'httponly'=>true,
                'samesite'=>'Lax',
            ]);
        }
        $_COOKIE[$name]=$token;
        return $token;
    }

    private function isBot(string $ua): bool
    {
        if($ua==='') return true;
        return (bool)preg_match('/bot|crawler|spider|slurp|bingpreview|headless|facebookexternalhit|monitor|uptime/i',$ua);
    }

    private function deviceType(string $ua): string
    {
        if(preg_match('/mobile|android|iphone|ipad|ipod/i',$ua)) return 'mobile';
        if($ua==='') return 'other';
        return 'desktop';
    }
}
