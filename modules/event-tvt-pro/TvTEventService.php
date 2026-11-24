<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaTvTEventService
{
    public function __construct(private readonly Database $db){}

    public function diagnostics(): array
    {
        $required=[
            'TvTMatch'=>['SessionKey','EventName','StartedAt','EndedAt','Status','WinnerTeam','BlueScore','RedScore'],
            'TvTResult'=>['ResultId','SessionKey','Account','CharacterName','Result','CreatedAt'],
            'TvTAction'=>['ActionId','SessionKey','Action','Account','CharacterName','Detail','Value','OccurredAt'],
        ];

        $out=[];
        foreach($required as $table=>$columns){
            $exists=$this->tableExists($table);
            $present=[];
            if($exists){
                $rows=$this->db->fetchAll(
                    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=:t",
                    ['t'=>$table]
                );
                foreach($rows as $row){
                    $present[strtolower((string)$row['COLUMN_NAME'])]=true;
                }
            }

            $missing=[];
            foreach($columns as $column){
                if(!isset($present[strtolower($column)])) $missing[]=$column;
            }

            $count=0;
            if($exists){
                $row=$this->db->fetchOne("SELECT COUNT(*) total FROM dbo.[{$table}]");
                $count=(int)($row['total']??0);
            }

            $out[$table]=[
                'exists'=>$exists,
                'missing'=>$missing,
                'count'=>$count,
            ];
        }
        return $out;
    }

    public function ready(): bool
    {
        $d=$this->diagnostics();
        foreach($d as $item){
            if(!$item['exists'] || $item['missing']) return false;
        }
        return true;
    }

    public function overview(): array
    {
        return [
            'matches'=>(int)($this->db->fetchOne("SELECT COUNT(*) total FROM dbo.TvTMatch")['total']??0),
            'results'=>(int)($this->db->fetchOne("SELECT COUNT(*) total FROM dbo.TvTResult")['total']??0),
            'players'=>(int)($this->db->fetchOne(
                "SELECT COUNT(*) total FROM (
                    SELECT Account,CharacterName FROM dbo.TvTResult GROUP BY Account,CharacterName
                 ) q"
            )['total']??0),
            'wins'=>(int)($this->db->fetchOne(
                "SELECT COUNT(*) total FROM dbo.TvTResult WHERE Result='WIN'"
            )['total']??0),
            'kills'=>(int)($this->db->fetchOne(
                "SELECT ISNULL(SUM(Value),0) total FROM dbo.TvTAction WHERE Action='KILLS'"
            )['total']??0),
            'objectives'=>(int)($this->db->fetchOne(
                "SELECT COUNT(*) total FROM dbo.TvTAction WHERE Action='OBJECTIVE_DESTROYED'"
            )['total']??0),
            'zen'=>(int)($this->db->fetchOne(
                "SELECT ISNULL(SUM(Value),0) total FROM dbo.TvTAction WHERE Action='REWARD_ZEN'"
            )['total']??0),
        ];
    }

    public function ranking(int $limit=30): array
    {
        $limit=max(10,min(100,$limit));

        return $this->db->fetchAll(
            "SELECT TOP {$limit}
                r.Account,
                r.CharacterName,
                COUNT(*) Matches,
                SUM(CASE WHEN r.Result='WIN' THEN 1 ELSE 0 END) Wins,
                SUM(CASE WHEN r.Result='LOSE' THEN 1 ELSE 0 END) Losses,
                (
                    SELECT ISNULL(SUM(a.Value),0)
                    FROM dbo.TvTAction a
                    WHERE a.Action='KILLS'
                      AND a.Account=r.Account
                      AND a.CharacterName=r.CharacterName
                ) Kills,
                (
                    SELECT ISNULL(SUM(a.Value),0)
                    FROM dbo.TvTAction a
                    WHERE a.Action='DEATHS'
                      AND a.Account=r.Account
                      AND a.CharacterName=r.CharacterName
                ) Deaths,
                (
                    SELECT COUNT(*)
                    FROM dbo.TvTAction a
                    WHERE a.Action='OBJECTIVE_DESTROYED'
                      AND a.CharacterName=r.CharacterName
                ) Objectives,
                (
                    SELECT ISNULL(SUM(a.Value),0)
                    FROM dbo.TvTAction a
                    WHERE a.Action='REWARD_ZEN'
                      AND a.Account=r.Account
                      AND a.CharacterName=r.CharacterName
                ) RewardZen,
                MAX(r.CreatedAt) LastResultAt
             FROM dbo.TvTResult r
             GROUP BY r.Account,r.CharacterName
             ORDER BY
                SUM(CASE WHEN r.Result='WIN' THEN 1 ELSE 0 END) DESC,
                (
                    SELECT ISNULL(SUM(a.Value),0)
                    FROM dbo.TvTAction a
                    WHERE a.Action='KILLS'
                      AND a.Account=r.Account
                      AND a.CharacterName=r.CharacterName
                ) DESC,
                COUNT(*) DESC,
                r.CharacterName ASC"
        );
    }

    public function recentMatches(int $limit=20): array
    {
        $limit=max(5,min(100,$limit));
        return $this->db->fetchAll(
            "SELECT TOP {$limit}
                m.SessionKey,m.EventName,m.StartedAt,m.EndedAt,m.Status,m.WinnerTeam,m.BlueScore,m.RedScore,
                (SELECT COUNT(*) FROM dbo.TvTResult r WHERE r.SessionKey=m.SessionKey) ResultCount,
                (SELECT COUNT(*) FROM dbo.TvTAction a WHERE a.SessionKey=m.SessionKey AND a.Action='ENTRY') EntryCount,
                (SELECT COUNT(*) FROM dbo.TvTAction a WHERE a.SessionKey=m.SessionKey AND a.Action='OBJECTIVE_DESTROYED') ObjectiveCount,
                (SELECT ISNULL(SUM(a.Value),0) FROM dbo.TvTAction a WHERE a.SessionKey=m.SessionKey AND a.Action='KILLS') KillCount,
                (SELECT ISNULL(SUM(a.Value),0) FROM dbo.TvTAction a WHERE a.SessionKey=m.SessionKey AND a.Action='REWARD_ZEN') RewardZen,
                (SELECT TOP 1 r.CharacterName FROM dbo.TvTResult r WHERE r.SessionKey=m.SessionKey AND r.Result='WIN' ORDER BY r.ResultId ASC) WinnerCharacter,
                (SELECT TOP 1 r.Account FROM dbo.TvTResult r WHERE r.SessionKey=m.SessionKey AND r.Result='WIN' ORDER BY r.ResultId ASC) WinnerAccount
             FROM dbo.TvTMatch m
             ORDER BY m.StartedAt DESC"
        );
    }

    public function session(string $sessionKey,int $timelineLimit=100): ?array
    {
        $sessionKey=trim($sessionKey);
        if($sessionKey==='' || strlen($sessionKey)>64) return null;

        $match=$this->db->fetchOne(
            "SELECT TOP 1 * FROM dbo.TvTMatch WHERE SessionKey=:s",
            ['s'=>$sessionKey]
        );
        if(!$match) return null;

        $results=$this->db->fetchAll(
            "SELECT r.*,
                (SELECT TOP 1 a.Detail FROM dbo.TvTAction a
                 WHERE a.SessionKey=r.SessionKey
                   AND a.Action='ENTRY'
                   AND a.Account=r.Account
                   AND a.CharacterName=r.CharacterName
                 ORDER BY a.ActionId ASC) Team,
                (SELECT ISNULL(SUM(a.Value),0) FROM dbo.TvTAction a
                 WHERE a.SessionKey=r.SessionKey AND a.Action='KILLS'
                   AND a.Account=r.Account AND a.CharacterName=r.CharacterName) Kills,
                (SELECT ISNULL(SUM(a.Value),0) FROM dbo.TvTAction a
                 WHERE a.SessionKey=r.SessionKey AND a.Action='DEATHS'
                   AND a.Account=r.Account AND a.CharacterName=r.CharacterName) Deaths,
                (SELECT COUNT(*) FROM dbo.TvTAction a
                 WHERE a.SessionKey=r.SessionKey AND a.Action='OBJECTIVE_DESTROYED'
                   AND a.CharacterName=r.CharacterName) Objectives,
                (SELECT ISNULL(SUM(a.Value),0) FROM dbo.TvTAction a
                 WHERE a.SessionKey=r.SessionKey AND a.Action='REWARD_ZEN'
                   AND a.Account=r.Account AND a.CharacterName=r.CharacterName) RewardZen
             FROM dbo.TvTResult r
             WHERE r.SessionKey=:s
             ORDER BY CASE WHEN r.Result='WIN' THEN 0 ELSE 1 END,r.ResultId ASC",
            ['s'=>$sessionKey]
        );

        $timelineLimit=max(20,min(300,$timelineLimit));
        $timeline=$this->db->fetchAll(
            "SELECT TOP {$timelineLimit}
                ActionId,Action,Account,CharacterName,Detail,Value,OccurredAt
             FROM dbo.TvTAction
             WHERE SessionKey=:s
             ORDER BY OccurredAt ASC,ActionId ASC",
            ['s'=>$sessionKey]
        );

        $entries=$this->db->fetchAll(
            "SELECT Account,CharacterName,Detail Team,Value TeamValue,OccurredAt
             FROM dbo.TvTAction
             WHERE SessionKey=:s AND Action='ENTRY'
             ORDER BY ActionId ASC",
            ['s'=>$sessionKey]
        );

        return [
            'match'=>$match,
            'results'=>$results,
            'timeline'=>$timeline,
            'entries'=>$entries,
        ];
    }

    public function player(string $character): ?array
    {
        $character=trim($character);
        if($character==='' || strlen($character)>11) return null;

        $row=$this->db->fetchOne(
            "SELECT TOP 1
                r.Account,r.CharacterName,
                COUNT(*) Matches,
                SUM(CASE WHEN r.Result='WIN' THEN 1 ELSE 0 END) Wins,
                SUM(CASE WHEN r.Result='LOSE' THEN 1 ELSE 0 END) Losses,
                MAX(r.CreatedAt) LastResultAt
             FROM dbo.TvTResult r
             WHERE r.CharacterName=:n
             GROUP BY r.Account,r.CharacterName",
            ['n'=>$character]
        );
        if(!$row) return null;

        $row['Kills']=(int)($this->db->fetchOne(
            "SELECT ISNULL(SUM(Value),0) total FROM dbo.TvTAction
             WHERE Action='KILLS' AND CharacterName=:n",
            ['n'=>$character]
        )['total']??0);
        $row['Deaths']=(int)($this->db->fetchOne(
            "SELECT ISNULL(SUM(Value),0) total FROM dbo.TvTAction
             WHERE Action='DEATHS' AND CharacterName=:n",
            ['n'=>$character]
        )['total']??0);
        $row['Objectives']=(int)($this->db->fetchOne(
            "SELECT COUNT(*) total FROM dbo.TvTAction
             WHERE Action='OBJECTIVE_DESTROYED' AND CharacterName=:n",
            ['n'=>$character]
        )['total']??0);
        $row['RewardZen']=(int)($this->db->fetchOne(
            "SELECT ISNULL(SUM(Value),0) total FROM dbo.TvTAction
             WHERE Action='REWARD_ZEN' AND CharacterName=:n",
            ['n'=>$character]
        )['total']??0);

        $row['Recent']=$this->db->fetchAll(
            "SELECT TOP 20 r.*,m.EventName,m.StartedAt,m.Status,m.WinnerTeam,m.BlueScore,m.RedScore
             FROM dbo.TvTResult r
             LEFT JOIN dbo.TvTMatch m ON m.SessionKey=r.SessionKey
             WHERE r.CharacterName=:n
             ORDER BY r.ResultId DESC",
            ['n'=>$character]
        );

        return $row;
    }

    private function tableExists(string $table): bool
    {
        $row=$this->db->fetchOne(
            "SELECT COUNT(*) total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA='dbo' AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME=:t",
            ['t'=>$table]
        );
        return (int)($row['total']??0)>0;
    }
}
