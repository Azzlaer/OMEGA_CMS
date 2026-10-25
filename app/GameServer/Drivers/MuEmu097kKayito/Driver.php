<?php
declare(strict_types=1);
namespace Omega\GameServer\Drivers\MuEmu097kKayito;
use Omega\Core\Database;
use RuntimeException;

final class Driver
{
    private const REQUIRED_TABLES = ['MEMB_INFO','MEMB_STAT','Character','AccountCharacter','Guild','GuildMember','warehouse','ResetInfo','RankingBloodCastle','RankingDevilSquare'];
    public function __construct(private readonly Database $db) {}

    private function rankingExclusionAvailable(): bool
    {
        try {
            return $this->tableExists('cms_ranking_exclusions');
        } catch (\Throwable) {
            return false;
        }
    }

    private function exclusionSql(string $characterExpression): string
    {
        if(!$this->rankingExclusionAvailable()) return '';

        return " AND NOT EXISTS (
            SELECT 1
            FROM dbo.cms_ranking_exclusions rx
            WHERE rx.is_active=1
              AND LOWER(LTRIM(RTRIM(rx.character_name)))=
                  LOWER(LTRIM(RTRIM({$characterExpression})))
        ) ";
    }


    public function diagnostic(): array
    {
        $rows=$this->db->fetchAll("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE'");
        $have=array_map(fn($r)=>strtolower((string)$r['TABLE_NAME']),$rows);
        $checks=[];
        foreach(self::REQUIRED_TABLES as $t) $checks[$t]=in_array(strtolower($t),$have,true);
        return ['driver'=>'MuEmu 0.97k Kayito','database'=>'MuOnline97','tables'=>$checks,'compatible'=>!in_array(false,$checks,true)];
    }

    public function onlineCount(): int
    {
        $r=$this->db->fetchOne('SELECT COUNT(*) AS total FROM MEMB_STAT WHERE ConnectStat = :online',['online'=>1]);
        return (int)($r['total'] ?? 0);
    }

    public function account(string $accountId): ?array
    {
        return $this->db->fetchOne('SELECT memb___id,memb_name,mail_addr,AccountLevel,AccountExpireDate,bloc_code,Bloc_Expire FROM MEMB_INFO WHERE memb___id=:id',['id'=>$accountId]);
    }

    public function characters(string $accountId): array
    {
        return $this->db->fetchAll('SELECT Name,ResetCount,GrandResetCount,cLevel,LevelUpPoint,Class,Experience,Strength,Dexterity,Vitality,Energy,Money,MapNumber,MapPosX,MapPosY,PkCount,PkLevel,PkTime,CtlCode FROM Character WHERE AccountID=:id ORDER BY cLevel DESC, ResetCount DESC',['id'=>$accountId]);
    }

    public function ranking(string $type='level', int $limit=50): array
    {
        $limit=max(1,min(100,$limit));
        $order=match($type){
            'reset'=>'ResetCount DESC, cLevel DESC',
            'grandreset'=>'GrandResetCount DESC, ResetCount DESC, cLevel DESC',
            default=>'cLevel DESC, Experience DESC',
        };
        $exclude=$this->exclusionSql('c.Name');
        return $this->db->fetchAll("SELECT TOP {$limit} c.Name,c.Class,c.cLevel,c.ResetCount,c.GrandResetCount,c.Strength,c.Dexterity,c.Vitality,c.Energy,c.PkLevel FROM Character c WHERE c.CtlCode < 8 {$exclude} ORDER BY {$order}, c.Name ASC");
    }

    public function statistics(): array
    {
        $characters = $this->db->fetchOne("SELECT COUNT(*) AS total FROM Character WHERE CtlCode < 8");
        $accounts = $this->db->fetchOne("SELECT COUNT(*) AS total FROM MEMB_INFO");
        $guilds = $this->db->fetchOne("SELECT COUNT(*) AS total FROM Guild");
        return [
            'online' => $this->onlineCount(),
            'accounts' => (int)($accounts['total'] ?? 0),
            'characters' => (int)($characters['total'] ?? 0),
            'guilds' => (int)($guilds['total'] ?? 0),
        ];
    }

    public function guildRanking(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $exclude=$this->exclusionSql('g.G_Master');
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                g.G_Name,g.G_Score,g.G_Master,g.G_Notice
            FROM Guild g
            WHERE 1=1 {$exclude}
            ORDER BY g.G_Score DESC, g.G_Name ASC
        ");
    }

    public function eventRanking(string $event, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $table = match ($event) {
            'bloodcastle' => 'RankingBloodCastle',
            'devilsquare' => 'RankingDevilSquare',
            default => throw new RuntimeException('Ranking de evento no soportado.')
        };

        if (!$this->tableExists($table)) return [];

        $exclude = $this->exclusionSql('r.Name');

        /*
         * RankingBloodCastle / RankingDevilSquare only store Name + Score.
         * Character and MEMB_STAT are enrichment sources: the event score remains
         * authoritative even when the character row no longer exists.
         * No AccountID or IP address is exposed by this public query.
         */
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                r.Name,
                ISNULL(r.Score,0) AS Score,
                c.Class,
                c.cLevel,
                c.ResetCount,
                c.GrandResetCount,
                c.PkLevel,
                c.MapNumber,
                CASE WHEN c.AccountID IS NOT NULL AND EXISTS (
                    SELECT 1
                    FROM MEMB_STAT ms
                    WHERE ms.memb___id=c.AccountID
                      AND ISNULL(ms.ConnectStat,0)=1
                ) THEN 1 ELSE 0 END AS IsOnline
            FROM {$table} r
            LEFT JOIN Character c ON c.Name=r.Name
            WHERE 1=1 {$exclude}
            ORDER BY ISNULL(r.Score,0) DESC, r.Name ASC
        ");
    }

    public function eventRankingSummary(string $event): array
    {
        $table = match ($event) {
            'bloodcastle' => 'RankingBloodCastle',
            'devilsquare' => 'RankingDevilSquare',
            default => throw new RuntimeException('Ranking de evento no soportado.')
        };

        if (!$this->tableExists($table)) {
            return ['participants'=>0,'bestScore'=>0,'averageScore'=>0];
        }

        $exclude = $this->exclusionSql('r.Name');
        $row = $this->db->fetchOne("
            SELECT
                COUNT(*) AS Participants,
                ISNULL(MAX(ISNULL(r.Score,0)),0) AS BestScore,
                ISNULL(AVG(CAST(ISNULL(r.Score,0) AS FLOAT)),0) AS AverageScore
            FROM {$table} r
            WHERE 1=1 {$exclude}
        ");

        return [
            'participants' => (int)($row['Participants'] ?? 0),
            'bestScore' => (int)($row['BestScore'] ?? 0),
            'averageScore' => (float)($row['AverageScore'] ?? 0),
        ];
    }

    public function tableExists(string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=:table",
            ['table'=>$table]
        );
        return (int)($row['total'] ?? 0) > 0;
    }

    public function advancedRanking(string $type, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));

        return match ($type) {
            'duel' => $this->duelRanking($limit),
            'skyevent' => $this->skyEventRanking($limit),
            'monsterpoints' => $this->monsterPointRanking($limit),
            'minigames' => $this->miniGameRanking($limit),
            'goldenarcher' => $this->goldenArcherRanking($limit),
            default => throw new RuntimeException('Ranking avanzado no soportado.')
        };
    }

    public function duelRanking(int $limit = 50): array
    {
        if (!$this->tableExists('DuelRanking')) return [];
        $exclude=$this->exclusionSql('d.CharacterName');
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                d.CharacterName,d.AccountID,d.Class,d.Level,d.ResetCount,
                d.Duels,d.Wins,d.Losses,d.Draws,d.Kills,d.Deaths,
                d.CurrentStreak,d.BestStreak,d.Rating,d.LastDuelDate
            FROM DuelRanking d
            WHERE 1=1 {$exclude}
            ORDER BY d.Rating DESC, d.Wins DESC, d.BestStreak DESC, d.Duels DESC, d.CharacterName ASC
        ");
    }

    public function skyEventRanking(int $limit = 50): array
    {
        if (!$this->tableExists('SkyEventRankingTotal')) return [];
        $exclude=$this->exclusionSql('s.CharacterName');
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                s.CharacterName,s.EventsPlayed,s.Wins,s.TotalKills,s.TotalScore,s.BestScore,s.LastEventDate
            FROM SkyEventRankingTotal s
            WHERE 1=1 {$exclude}
            ORDER BY s.Wins DESC, s.TotalScore DESC, s.TotalKills DESC, s.BestScore DESC, s.CharacterName ASC
        ");
    }

    public function monsterPointRanking(int $limit = 50): array
    {
        if (!$this->tableExists('MonsterPointBalance')) return [];
        $exclude=$this->exclusionSql('m.CharacterName');
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                m.CharacterName,m.Points,m.TotalEarned,m.TotalSpent,m.LastUpdate
            FROM MonsterPointBalance m
            WHERE 1=1 {$exclude}
            ORDER BY m.Points DESC, m.TotalEarned DESC, m.CharacterName ASC
        ");
    }

    public function miniGameRanking(int $limit = 50): array
    {
        if (!$this->tableExists('MiniGamePlayerStats')) return [];
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                mg.CharacterName,
                SUM(mg.Participations) AS Participations,
                SUM(mg.Wins) AS Wins,
                SUM(mg.Losses) AS Losses,
                SUM(mg.Cancelled) AS Cancelled,
                SUM(mg.TotalScore) AS TotalScore,
                SUM(mg.LatinPointsEarned) AS LatinPointsEarned,
                SUM(mg.ZenEarned) AS ZenEarned,
                MAX(mg.LastPlayed) AS LastPlayed,
                MAX(mg.LastWin) AS LastWin
            FROM MiniGamePlayerStats mg
            WHERE 1=1 {$this->exclusionSql('mg.CharacterName')}
            GROUP BY CharacterName
            ORDER BY SUM(mg.Wins) DESC, SUM(mg.TotalScore) DESC, SUM(mg.LatinPointsEarned) DESC, mg.CharacterName ASC
        ");
    }

    public function goldenArcherRanking(int $limit = 50): array
    {
        if (!$this->tableExists('GoldenArcherCoin')) return [];

        // Do not expose AccountID publicly. Resolve a character from AccountCharacter when possible.
        $exclude=$this->exclusionSql('x.CharacterName');
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                x.CharacterName,
                g.Renas,
                g.Stones
            FROM GoldenArcherCoin g
            LEFT JOIN AccountCharacter ac ON ac.Id = g.AccountID
            CROSS APPLY (
                SELECT COALESCE(
                    NULLIF(ac.GameIDC,''),
                    NULLIF(ac.GameID1,''),
                    NULLIF(ac.GameID2,''),
                    NULLIF(ac.GameID3,''),
                    NULLIF(ac.GameID4,''),
                    NULLIF(ac.GameID5,''),
                    'Sin personaje'
                ) AS CharacterName
            ) x
            WHERE 1=1 {$exclude}
            ORDER BY g.Renas DESC, g.Stones DESC, x.CharacterName ASC
        ");
    }

    public function rankingAvailability(): array
    {
        return [
            'level' => $this->tableExists('Character'),
            'reset' => $this->tableExists('Character'),
            'grandreset' => $this->tableExists('Character'),
            'guilds' => $this->tableExists('Guild'),
            'bloodcastle' => $this->tableExists('RankingBloodCastle'),
            'devilsquare' => $this->tableExists('RankingDevilSquare'),
            'duel' => $this->tableExists('DuelRanking'),
            'skyevent' => $this->tableExists('SkyEventRankingTotal'),
            'monsterpoints' => $this->tableExists('MonsterPointBalance'),
            'minigames' => $this->tableExists('MiniGamePlayerStats'),
            'goldenarcher' => $this->tableExists('GoldenArcherCoin'),
        ];
    }


    public function classRanking(int $class, int $limit = 50): array
    {
        $supported = array_keys(CharacterClass::supported());
        if (!in_array($class, $supported, true)) {
            throw new RuntimeException('Clase no soportada por este cliente 0.97k.');
        }

        $limit = max(1, min(100, $limit));

        return $this->db->fetchAll("
            SELECT TOP {$limit}
                c.Name,c.Class,c.cLevel,c.ResetCount,c.GrandResetCount,c.Experience,
                c.Strength,c.Dexterity,c.Vitality,c.Energy,c.LevelUpPoint,
                c.PkLevel,c.PkCount,c.MapNumber,c.MapPosX,c.MapPosY
            FROM Character c
            WHERE c.Class=:class
              AND ISNULL(c.CtlCode,0) < 8
              {$this->exclusionSql('c.Name')}
            ORDER BY
                c.ResetCount DESC,
                c.GrandResetCount DESC,
                c.cLevel DESC,
                c.Experience DESC,
                c.Name ASC
        ", ['class'=>$class]);
    }

    public function onlineCharacters(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        /*
         * MEMB_STAT identifies the online ACCOUNT.
         * Character.AccountID maps that account to its characters.
         *
         * Old MU schemas do not reliably expose the currently selected character
         * in MEMB_STAT. We therefore choose the most likely active character:
         * highest level/reset character for the connected account.
         *
         * This never exposes IP or AccountID publicly.
         */
        return $this->db->fetchAll("
            WITH OnlineAccounts AS (
                SELECT
                    s.memb___id,
                    s.ServerName,
                    s.ConnectTM,
                    s.OnlineHours
                FROM MEMB_STAT s
                WHERE ISNULL(s.ConnectStat,0)=1
            ),
            RankedCharacters AS (
                SELECT
                    c.AccountID,
                    c.Name,
                    c.Class,
                    c.cLevel,
                    c.ResetCount,
                    c.GrandResetCount,
                    c.MapNumber,
                    c.MapPosX,
                    c.MapPosY,
                    ROW_NUMBER() OVER(
                        PARTITION BY c.AccountID
                        ORDER BY c.cLevel DESC,c.ResetCount DESC,c.Experience DESC,c.Name ASC
                    ) AS rn
                FROM Character c
                INNER JOIN OnlineAccounts oa ON oa.memb___id=c.AccountID
                WHERE ISNULL(c.CtlCode,0)<8
            )
            SELECT TOP {$limit}
                rc.Name AS CharacterName,
                rc.Class,
                rc.cLevel,
                rc.ResetCount,
                rc.GrandResetCount,
                rc.MapNumber,
                rc.MapPosX,
                rc.MapPosY,
                oa.ServerName,
                oa.ConnectTM,
                oa.OnlineHours
            FROM OnlineAccounts oa
            LEFT JOIN RankedCharacters rc
                ON rc.AccountID=oa.memb___id AND rc.rn=1
            ORDER BY
                CASE WHEN rc.Name IS NULL THEN 1 ELSE 0 END,
                rc.cLevel DESC,
                rc.ResetCount DESC,
                rc.Name ASC
        ");
    }

    public function onlineCharacterCount(): int
    {
        $row = $this->db->fetchOne("
            SELECT COUNT(*) AS total
            FROM MEMB_STAT
            WHERE ISNULL(ConnectStat,0)=1
        ");
        return (int)($row['total'] ?? 0);
    }


    public function accountStatus(string $accountId): array
    {
        $row = $this->db->fetchOne(
            'SELECT ConnectStat,ServerName,IP,ConnectTM,DisConnectTM,OnlineHours FROM MEMB_STAT WHERE memb___id=:id',
            ['id'=>$accountId]
        );
        return $row ?: [
            'ConnectStat'=>0,'ServerName'=>null,'IP'=>null,'ConnectTM'=>null,'DisConnectTM'=>null,'OnlineHours'=>0
        ];
    }

    public function character(string $accountId, string $name): ?array
    {
        return $this->db->fetchOne(
            'SELECT Name,ResetCount,GrandResetCount,cLevel,LevelUpPoint,Class,Experience,Strength,Dexterity,Vitality,Energy,Money,
                    MapNumber,MapPosX,MapPosY,PkCount,PkLevel,PkTime,CtlCode
             FROM Character WHERE AccountID=:account AND Name=:name',
            ['account'=>$accountId,'name'=>$name]
        );
    }

    public function clearPk(string $accountId, string $name): void
    {
        $this->ensureOffline($accountId);
        $this->db->execute(
            'UPDATE Character SET PkCount=0,PkLevel=3,PkTime=0 WHERE AccountID=:account AND Name=:name',
            ['account'=>$accountId,'name'=>$name]
        );
    }

    public function unstuck(string $accountId, string $name): void
    {
        $this->ensureOffline($accountId);
        // Lorencia safe zone used by the official defaults in this 0.97k DB.
        $this->db->execute(
            'UPDATE Character SET MapNumber=0,MapPosX=125,MapPosY=125 WHERE AccountID=:account AND Name=:name',
            ['account'=>$accountId,'name'=>$name]
        );
    }

    public function addStats(string $accountId, string $name, array $points): array
    {
        $this->ensureOffline($accountId);

        $str = max(0,(int)($points['strength'] ?? 0));
        $agi = max(0,(int)($points['dexterity'] ?? 0));
        $vit = max(0,(int)($points['vitality'] ?? 0));
        $ene = max(0,(int)($points['energy'] ?? 0));
        $total = $str+$agi+$vit+$ene;

        if ($total < 1) throw new RuntimeException('Debes asignar al menos un punto.');

        return $this->db->transaction(function(Database $db) use($accountId,$name,$str,$agi,$vit,$ene,$total): array {
            $char = $db->fetchOne(
                'SELECT LevelUpPoint,Strength,Dexterity,Vitality,Energy FROM Character WITH (UPDLOCK,ROWLOCK)
                 WHERE AccountID=:account AND Name=:name',
                ['account'=>$accountId,'name'=>$name]
            );
            if (!$char) throw new RuntimeException('Personaje no encontrado.');
            if ((int)$char['LevelUpPoint'] < $total) throw new RuntimeException('No tienes suficientes puntos disponibles.');

            $cap = 32767;
            if ((int)$char['Strength']+$str > $cap ||
                (int)$char['Dexterity']+$agi > $cap ||
                (int)$char['Vitality']+$vit > $cap ||
                (int)$char['Energy']+$ene > $cap) {
                throw new RuntimeException('Uno de los stats superaría el máximo permitido.');
            }

            $db->execute(
                'UPDATE Character SET
                    Strength=Strength+:str,
                    Dexterity=Dexterity+:agi,
                    Vitality=Vitality+:vit,
                    Energy=Energy+:ene,
                    LevelUpPoint=LevelUpPoint-:total
                 WHERE AccountID=:account AND Name=:name',
                [
                    'str'=>$str,'agi'=>$agi,'vit'=>$vit,'ene'=>$ene,'total'=>$total,
                    'account'=>$accountId,'name'=>$name
                ]
            );

            return ['spent'=>$total,'remaining'=>(int)$char['LevelUpPoint']-$total];
        });
    }

    public static function mapName(int $map): string
    {
        return match($map) {
            0=>'Lorencia',1=>'Dungeon',2=>'Devias',3=>'Noria',4=>'Lost Tower',
            5=>'Exile',6=>'Arena',7=>'Atlans',8=>'Tarkan',9=>'Devil Square',
            10=>'Icarus',11=>'Blood Castle I',12=>'Blood Castle II',
            13=>'Blood Castle III',14=>'Blood Castle IV',15=>'Blood Castle V',
            16=>'Blood Castle VI',17=>'Blood Castle VII',
            default=>'Mapa '.$map
        };
    }


    public function ensureOffline(string $accountId): void
    {
        $r=$this->db->fetchOne('SELECT ConnectStat FROM MEMB_STAT WHERE memb___id=:id',['id'=>$accountId]);
        if((int)($r['ConnectStat'] ?? 0) === 1) throw new RuntimeException('La cuenta debe estar desconectada del GameServer.');
    }
}
