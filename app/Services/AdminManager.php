<?php
declare(strict_types=1);
namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class AdminManager
{
    public function __construct(
        private readonly Database $cmsDb,
        private readonly Database $muDb
    ) {}

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_downloads','U') IS NULL
            CREATE TABLE dbo.cms_downloads(
                id INT IDENTITY(1,1) PRIMARY KEY,
                title VARCHAR(120) NOT NULL,
                description VARCHAR(500) NULL,
                url VARCHAR(500) NOT NULL,
                button_label VARCHAR(50) NOT NULL DEFAULT 'Descargar',
                category VARCHAR(50) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active BIT NOT NULL DEFAULT 1,
                created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_modules','U') IS NULL
            CREATE TABLE dbo.cms_modules(
                slug VARCHAR(80) PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                version VARCHAR(30) NULL,
                is_enabled BIT NOT NULL DEFAULT 1,
                config_json VARCHAR(MAX) NULL,
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
    }

    public function newsList(): array
    {
        return $this->cmsDb->fetchAll("
            SELECT id,title,slug,category,status,featured,published_at,created_at,updated_at
            FROM cms_news
            ORDER BY id DESC
        ");
    }

    public function newsFind(int $id): ?array
    {
        return $this->cmsDb->fetchOne("SELECT * FROM cms_news WHERE id=:id", ['id'=>$id]);
    }

    public function newsSave(array $data): int
    {
        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $summary = trim((string)($data['summary'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        $category = trim((string)($data['category'] ?? 'Noticias'));
        $status = in_array(($data['status'] ?? 'draft'), ['draft','published'], true) ? $data['status'] : 'draft';
        $featured = !empty($data['featured']) ? 1 : 0;

        if ($title === '' || $content === '') throw new RuntimeException('Título y contenido son obligatorios.');
        if ($slug === '') {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $title), '-'));
        }
        if (!preg_match('/^[a-z0-9_-]{3,160}$/', $slug)) {
            throw new RuntimeException('El slug contiene caracteres no permitidos.');
        }

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $this->cmsDb->execute("
                UPDATE cms_news SET
                    title=:title,slug=:slug,summary=:summary,content=:content,category=:category,
                    status=:status,featured=:featured,
                    published_at=CASE WHEN :status2='published' AND published_at IS NULL THEN SYSUTCDATETIME() ELSE published_at END,
                    updated_at=SYSUTCDATETIME()
                WHERE id=:id
            ", [
                'title'=>$title,'slug'=>$slug,'summary'=>$summary,'content'=>$content,'category'=>$category,
                'status'=>$status,'featured'=>$featured,'status2'=>$status,'id'=>$id
            ]);
            return $id;
        }

        $this->cmsDb->execute("
            INSERT INTO cms_news(title,slug,summary,content,category,status,featured,published_at)
            VALUES(:title,:slug,:summary,:content,:category,:status,:featured,
                   CASE WHEN :status2='published' THEN SYSUTCDATETIME() ELSE NULL END)
        ", [
            'title'=>$title,'slug'=>$slug,'summary'=>$summary,'content'=>$content,'category'=>$category,
            'status'=>$status,'featured'=>$featured,'status2'=>$status
        ]);
        $row = $this->cmsDb->fetchOne("SELECT TOP 1 id FROM cms_news WHERE slug=:slug ORDER BY id DESC", ['slug'=>$slug]);
        return (int)($row['id'] ?? 0);
    }

    public function newsDelete(int $id): void
    {
        $this->cmsDb->execute("DELETE FROM cms_news WHERE id=:id", ['id'=>$id]);
    }

    public function downloadsList(): array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchAll("SELECT * FROM cms_downloads ORDER BY sort_order ASC,id ASC");
    }

    public function downloadFind(int $id): ?array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchOne("SELECT * FROM cms_downloads WHERE id=:id", ['id'=>$id]);
    }

    public function downloadSave(array $data): int
    {
        $this->ensureSchema();
        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $url = trim((string)($data['url'] ?? ''));
        $label = trim((string)($data['button_label'] ?? 'Descargar'));
        $category = trim((string)($data['category'] ?? 'Cliente'));
        $sort = (int)($data['sort_order'] ?? 0);
        $active = !empty($data['is_active']) ? 1 : 0;

        if ($title === '' || $url === '') throw new RuntimeException('Título y URL son obligatorios.');
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('La URL de descarga no es válida.');

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $this->cmsDb->execute("
                UPDATE cms_downloads SET title=:title,description=:description,url=:url,button_label=:label,
                category=:category,sort_order=:sort,is_active=:active,updated_at=SYSUTCDATETIME()
                WHERE id=:id
            ", compact('title','description','url','label','category','sort','active','id'));
            return $id;
        }

        $this->cmsDb->execute("
            INSERT INTO cms_downloads(title,description,url,button_label,category,sort_order,is_active)
            VALUES(:title,:description,:url,:label,:category,:sort,:active)
        ", compact('title','description','url','label','category','sort','active'));
        $row = $this->cmsDb->fetchOne("SELECT TOP 1 id FROM cms_downloads ORDER BY id DESC");
        return (int)($row['id'] ?? 0);
    }

    public function downloadDelete(int $id): void
    {
        $this->cmsDb->execute("DELETE FROM cms_downloads WHERE id=:id", ['id'=>$id]);
    }

    public function accounts(string $q = ''): array
    {
        $q = trim($q);
        if ($q === '') {
            return $this->muDb->fetchAll("
                SELECT TOP 100 m.memb___id,m.mail_addr,m.AccountLevel,m.bloc_code,
                       ISNULL(s.ConnectStat,0) AS ConnectStat,ISNULL(s.OnlineHours,0) AS OnlineHours
                FROM MEMB_INFO m
                LEFT JOIN MEMB_STAT s ON s.memb___id=m.memb___id
                ORDER BY m.memb___id
            ");
        }

        return $this->muDb->fetchAll("
            SELECT TOP 100 m.memb___id,m.mail_addr,m.AccountLevel,m.bloc_code,
                   ISNULL(s.ConnectStat,0) AS ConnectStat,ISNULL(s.OnlineHours,0) AS OnlineHours
            FROM MEMB_INFO m
            LEFT JOIN MEMB_STAT s ON s.memb___id=m.memb___id
            WHERE m.memb___id LIKE :q OR m.mail_addr LIKE :q
            ORDER BY m.memb___id
        ", ['q'=>'%'.$q.'%']);
    }

    public function setAccountBlock(string $accountId, bool $blocked): void
    {
        $this->muDb->execute(
            "UPDATE MEMB_INFO SET bloc_code=:b WHERE memb___id=:id",
            ['b'=>$blocked ? '1' : '0','id'=>$accountId]
        );
    }

    public function settings(): array
    {
        $rows = $this->cmsDb->fetchAll("SELECT setting_key,setting_value FROM cms_settings");
        $out = [];
        foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
        return $out;
    }

    public function saveSettings(array $data): void
    {
        $allowed = [
            'site_name','site_url','server_description','discord_url',
            'downloads_note','maintenance_mode',
            'guild_professional_page_limit',
            'guild_professional_default_sort',
            'guild_professional_show_score',
            'guild_professional_show_notice',
            'guild_professional_show_member_preview'
        ];

        if(isset($data['guild_professional_page_limit'])){
            $data['guild_professional_page_limit']=(string)max(
                10,
                min(200,(int)$data['guild_professional_page_limit'])
            );
        }

        if(isset($data['guild_professional_default_sort'])
            && !in_array(
                (string)$data['guild_professional_default_sort'],
                ['resets','members','score','name'],
                true
            )){
            $data['guild_professional_default_sort']='resets';
        }

        foreach([
            'guild_professional_show_score',
            'guild_professional_show_notice',
            'guild_professional_show_member_preview'
        ] as $toggle){
            $data[$toggle]=!empty($data[$toggle])?'1':'0';
        }

        foreach ($allowed as $key) {
            if (!array_key_exists($key,$data)) continue;
            $value = trim((string)$data[$key]);
            $this->cmsDb->execute("
                IF EXISTS(SELECT 1 FROM cms_settings WHERE setting_key=:k)
                    UPDATE cms_settings SET setting_value=:v,updated_at=SYSUTCDATETIME() WHERE setting_key=:k2
                ELSE
                    INSERT INTO cms_settings(setting_key,setting_value) VALUES(:k3,:v2)
            ", ['k'=>$key,'v'=>$value,'k2'=>$key,'k3'=>$key,'v2'=>$value]);
        }
    }

    public function modules(array $discovered): array
    {
        $this->ensureSchema();
        $stored = $this->cmsDb->fetchAll("SELECT * FROM cms_modules");
        $bySlug = [];
        foreach ($stored as $r) $bySlug[$r['slug']] = $r;

        $out = [];
        foreach ($discovered as $m) {
            $slug = (string)($m['slug'] ?? '');
            $out[] = $m + [
                'is_enabled' => isset($bySlug[$slug]) ? (bool)$bySlug[$slug]['is_enabled'] : (bool)($m['enabled_by_default'] ?? true),
            ];
        }
        return $out;
    }

    public function setModuleEnabled(string $slug, string $name, string $version, bool $enabled): void
    {
        $this->ensureSchema();
        $this->cmsDb->execute("
            IF EXISTS(SELECT 1 FROM cms_modules WHERE slug=:slug)
                UPDATE cms_modules SET name=:name,version=:version,is_enabled=:enabled,updated_at=SYSUTCDATETIME() WHERE slug=:slug2
            ELSE
                INSERT INTO cms_modules(slug,name,version,is_enabled) VALUES(:slug3,:name2,:version2,:enabled2)
        ", [
            'slug'=>$slug,'name'=>$name,'version'=>$version,'enabled'=>$enabled?1:0,
            'slug2'=>$slug,'slug3'=>$slug,'name2'=>$name,'version2'=>$version,'enabled2'=>$enabled?1:0
        ]);
    }
}
