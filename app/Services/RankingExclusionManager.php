<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class RankingExclusionManager
{
    public function __construct(
        private readonly Database $cmsDb,
        private readonly Database $muDb
    ) {}

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_ranking_exclusions','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_ranking_exclusions(
                    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    character_name VARCHAR(10) NOT NULL,
                    reason NVARCHAR(250) NULL,
                    is_active BIT NOT NULL CONSTRAINT DF_cms_ranking_exclusions_active DEFAULT 1,
                    created_by VARCHAR(10) NULL,
                    created_at DATETIME2 NOT NULL CONSTRAINT DF_cms_ranking_exclusions_created DEFAULT SYSUTCDATETIME(),
                    updated_at DATETIME2 NOT NULL CONSTRAINT DF_cms_ranking_exclusions_updated DEFAULT SYSUTCDATETIME()
                );

                CREATE UNIQUE INDEX UX_cms_ranking_exclusions_character
                    ON dbo.cms_ranking_exclusions(character_name);
            END
        ");
    }

    public function all(string $q=''): array
    {
        $this->ensureSchema();
        $q=trim($q);

        if($q===''){
            return $this->cmsDb->fetchAll("
                SELECT id,character_name,reason,is_active,created_by,created_at,updated_at
                FROM dbo.cms_ranking_exclusions
                ORDER BY is_active DESC, character_name ASC
            ");
        }

        return $this->cmsDb->fetchAll("
            SELECT id,character_name,reason,is_active,created_by,created_at,updated_at
            FROM dbo.cms_ranking_exclusions
            WHERE character_name LIKE :q OR reason LIKE :q
            ORDER BY is_active DESC, character_name ASC
        ", ['q'=>'%'.$q.'%']);
    }

    public function characterExists(string $name): bool
    {
        $name=trim($name);
        if($name==='') return false;

        $row=$this->muDb->fetchOne(
            "SELECT TOP 1 Name FROM Character WHERE Name=:name",
            ['name'=>$name]
        );

        return is_array($row);
    }

    public function addMany(string $names,string $reason,string $actor): array
    {
        $this->ensureSchema();

        $reason=trim($reason);
        if(mb_strlen($reason)>250){
            $reason=mb_substr($reason,0,250);
        }

        $parts=preg_split('/[\r\n,;]+/u',$names) ?: [];
        $parts=array_values(array_unique(array_filter(array_map(
            static fn(string $v): string => trim($v),
            $parts
        ))));

        if(!$parts){
            throw new RuntimeException('Debes indicar al menos un personaje.');
        }

        $added=[];
        $updated=[];
        $notFound=[];
        $invalid=[];

        foreach($parts as $name){
            if(!preg_match('/^[A-Za-z0-9_-]{1,10}$/',$name)){
                $invalid[]=$name;
                continue;
            }

            $game=$this->muDb->fetchOne(
                "SELECT TOP 1 Name FROM Character WHERE Name=:name",
                ['name'=>$name]
            );

            if(!$game){
                $notFound[]=$name;
                continue;
            }

            $realName=(string)$game['Name'];

            $existing=$this->cmsDb->fetchOne(
                "SELECT TOP 1 id FROM dbo.cms_ranking_exclusions WHERE character_name=:name",
                ['name'=>$realName]
            );

            if($existing){
                $this->cmsDb->execute("
                    UPDATE dbo.cms_ranking_exclusions
                    SET reason=:reason,is_active=1,updated_at=SYSUTCDATETIME()
                    WHERE id=:id
                ",[
                    'reason'=>$reason,
                    'id'=>(int)$existing['id']
                ]);
                $updated[]=$realName;
            }else{
                $this->cmsDb->execute("
                    INSERT INTO dbo.cms_ranking_exclusions
                        (character_name,reason,is_active,created_by)
                    VALUES(:name,:reason,1,:actor)
                ",[
                    'name'=>$realName,
                    'reason'=>$reason,
                    'actor'=>$actor
                ]);
                $added[]=$realName;
            }
        }

        return compact('added','updated','notFound','invalid');
    }

    public function update(int $id,bool $active,string $reason): void
    {
        $this->ensureSchema();

        $reason=trim($reason);
        if(mb_strlen($reason)>250){
            $reason=mb_substr($reason,0,250);
        }

        $row=$this->cmsDb->fetchOne(
            "SELECT TOP 1 id FROM dbo.cms_ranking_exclusions WHERE id=:id",
            ['id'=>$id]
        );

        if(!$row){
            throw new RuntimeException('Exclusión no encontrada.');
        }

        $this->cmsDb->execute("
            UPDATE dbo.cms_ranking_exclusions
            SET is_active=:active,reason=:reason,updated_at=SYSUTCDATETIME()
            WHERE id=:id
        ",[
            'active'=>$active?1:0,
            'reason'=>$reason,
            'id'=>$id
        ]);
    }

    public function delete(int $id): void
    {
        $this->ensureSchema();

        $this->cmsDb->execute(
            "DELETE FROM dbo.cms_ranking_exclusions WHERE id=:id",
            ['id'=>$id]
        );
    }

    public function activeCount(): int
    {
        $this->ensureSchema();

        $row=$this->cmsDb->fetchOne(
            "SELECT COUNT(*) AS total FROM dbo.cms_ranking_exclusions WHERE is_active=1"
        );

        return (int)($row['total']??0);
    }
}
