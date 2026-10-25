<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;

final class GuildRankingProfessional
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function load(array $query,array $settings): array
    {
        if(!$this->tableExists('Guild')
            || !$this->tableExists('GuildMember')
            || !$this->tableExists('Character')){
            return [
                'available'=>false,
                'guilds'=>[],
                'stats'=>[],
                'members'=>[],
                'search'=>'',
                'sort'=>'resets',
                'limit'=>50,
                'error'=>'Se requieren las tablas Guild, GuildMember y Character.'
            ];
        }

        $gcols=$this->columnMap('Guild');
        $mcols=$this->columnMap('GuildMember');
        $ccols=$this->columnMap('Character');

        $gName=$this->findCol($gcols,['G_Name','GuildName']);
        $gMark=$this->findCol($gcols,['G_Mark','GuildMark']);
        $gMaster=$this->findCol($gcols,['G_Master','GuildMaster','Master']);
        $gNotice=$this->findCol($gcols,['G_Notice','Notice']);
        $gScore=$this->findCol($gcols,['G_Score','Score']);

        $mName=$this->findCol($mcols,['Name','CharacterName']);
        $mGuild=$this->findCol($mcols,['G_Name','GuildName']);

        $cName=$this->findCol($ccols,['Name','CharacterName']);
        $cReset=$this->findCol($ccols,['ResetCount','Resets','Reset']);

        if(!$gName || !$mName || !$mGuild || !$cName || !$cReset){
            return [
                'available'=>false,
                'guilds'=>[],
                'stats'=>[],
                'members'=>[],
                'search'=>'',
                'sort'=>'resets',
                'limit'=>50,
                'error'=>'La estructura de Guild/GuildMember/Character no contiene las columnas mínimas.'
            ];
        }

        $search=trim((string)($query['q']??''));
        $sort=trim((string)($query['sort']??($settings['guild_professional_default_sort']??'resets')));

        if(!in_array($sort,['resets','members','score','name'],true)){
            $sort='resets';
        }

        $limit=max(10,min(200,(int)($settings['guild_professional_page_limit']??100)));

        $params=[];
        $where='1=1';

        if($search!==''){
            $needle='%'.$search.'%';
            $parts=[];

            $parts[]="g.[{$gName}] LIKE :q_name";
            $params['q_name']=$needle;

            if($gMaster){
                $parts[]="g.[{$gMaster}] LIKE :q_master";
                $params['q_master']=$needle;
            }

            if($gNotice){
                $parts[]="g.[{$gNotice}] LIKE :q_notice";
                $params['q_notice']=$needle;
            }

            $where.=' AND ('.implode(' OR ',$parts).')';
        }

        $order=match($sort){
            'members'=>'MemberCount DESC, TotalResets DESC, GuildName ASC',
            'score'=>'GuildScore DESC, TotalResets DESC, GuildName ASC',
            'name'=>'GuildName ASC',
            default=>'TotalResets DESC, MemberCount DESC, GuildName ASC',
        };

        $scoreSelect=$gScore ? "ISNULL(g.[{$gScore}],0)" : "0";
        $markSelect=$gMark ? "g.[{$gMark}]" : "NULL";
        $masterSelect=$gMaster ? "g.[{$gMaster}]" : "''";
        $noticeSelect=$gNotice ? "g.[{$gNotice}]" : "''";

        /*
         * Los agregados se calculan en una subconsulta agrupada únicamente
         * por GuildMember.G_Name. Evitamos GROUP BY sobre G_Mark/G_Notice
         * para mantener compatibilidad con schemas antiguos de SQL Server.
         */
        $sql="
            SELECT TOP {$limit}
                g.[{$gName}] AS GuildName,
                {$markSelect} AS GuildMark,
                {$masterSelect} AS GuildMaster,
                {$noticeSelect} AS GuildNotice,
                {$scoreSelect} AS GuildScore,
                ISNULL(a.MemberCount,0) AS MemberCount,
                ISNULL(a.TotalResets,0) AS TotalResets
            FROM dbo.Guild g
            LEFT JOIN (
                SELECT
                    gm.[{$mGuild}] AS GuildName,
                    COUNT(gm.[{$mName}]) AS MemberCount,
                    ISNULL(SUM(ISNULL(c.[{$cReset}],0)),0) AS TotalResets
                FROM dbo.GuildMember gm
                LEFT JOIN dbo.Character c
                    ON c.[{$cName}] = gm.[{$mName}]
                GROUP BY gm.[{$mGuild}]
            ) a
                ON a.GuildName = g.[{$gName}]
            WHERE {$where}
            ORDER BY {$order}
        ";

        $guilds=$this->db->fetchAll($sql,$params);

        $stats=$this->db->fetchOne("
            SELECT
                COUNT(DISTINCT g.[{$gName}]) AS GuildCount,
                COUNT(gm.[{$mName}]) AS MemberCount,
                ISNULL(SUM(ISNULL(c.[{$cReset}],0)),0) AS TotalResets
            FROM dbo.Guild g
            LEFT JOIN dbo.GuildMember gm
                ON gm.[{$mGuild}] = g.[{$gName}]
            LEFT JOIN dbo.Character c
                ON c.[{$cName}] = gm.[{$mName}]
        ") ?? [];

        $memberPreview=[];

        if($this->settingBool($settings,'guild_professional_show_member_preview',true)){
            foreach($guilds as $guild){
                $guildName=(string)($guild['GuildName']??'');
                if($guildName==='') continue;

                $memberPreview[$guildName]=$this->db->fetchAll("
                    SELECT TOP 6
                        gm.[{$mName}] AS Name,
                        ISNULL(c.[{$cReset}],0) AS ResetCount
                    FROM dbo.GuildMember gm
                    LEFT JOIN dbo.Character c
                        ON c.[{$cName}] = gm.[{$mName}]
                    WHERE gm.[{$mGuild}]=:guild
                    ORDER BY
                        ISNULL(c.[{$cReset}],0) DESC,
                        gm.[{$mName}] ASC
                ",['guild'=>$guildName]);
            }
        }

        return [
            'available'=>true,
            'guilds'=>$guilds,
            'stats'=>$stats,
            'members'=>$memberPreview,
            'search'=>$search,
            'sort'=>$sort,
            'limit'=>$limit,
            'show_score'=>$this->settingBool($settings,'guild_professional_show_score',true),
            'show_notice'=>$this->settingBool($settings,'guild_professional_show_notice',true),
            'show_member_preview'=>$this->settingBool($settings,'guild_professional_show_member_preview',true),
            'top_reset'=>(int)($guilds[0]['TotalResets']??0),
            'error'=>null,
        ];
    }

    private function tableExists(string $table): bool
    {
        $row=$this->db->fetchOne(
            "SELECT COUNT(*) AS total
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_TYPE='BASE TABLE'
                AND TABLE_NAME=:table",
            ['table'=>$table]
        );

        return (int)($row['total']??0)>0;
    }

    private function columnMap(string $table): array
    {
        $rows=$this->db->fetchAll(
            "SELECT COLUMN_NAME
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_NAME=:table
              ORDER BY ORDINAL_POSITION",
            ['table'=>$table]
        );

        $map=[];

        foreach($rows as $row){
            $name=(string)$row['COLUMN_NAME'];
            $map[strtolower($name)]=$name;
        }

        return $map;
    }

    private function findCol(array $map,array $candidates): ?string
    {
        foreach($candidates as $candidate){
            $key=strtolower($candidate);
            if(isset($map[$key])) return $map[$key];
        }

        return null;
    }

    private function settingBool(array $settings,string $key,bool $default): bool
    {
        if(!array_key_exists($key,$settings)) return $default;

        $value=$settings[$key];

        if(is_bool($value)) return $value;

        return in_array(
            strtolower((string)$value),
            ['1','true','yes','on'],
            true
        );
    }
}
