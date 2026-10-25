<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class ChangelogManager
{
    public function __construct(private readonly Database $cmsDb) {}

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_changelog','U') IS NULL
            CREATE TABLE dbo.cms_changelog(
                id INT IDENTITY(1,1) PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(180) NOT NULL UNIQUE,
                version_label VARCHAR(80) NULL,
                category VARCHAR(50) NOT NULL DEFAULT 'GameServer',
                summary VARCHAR(500) NULL,
                content VARCHAR(MAX) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                featured BIT NOT NULL DEFAULT 0,
                title_color VARCHAR(20) NULL,
                title_border_color VARCHAR(20) NULL,
                title_bg_color VARCHAR(20) NULL,
                title_shadow_color VARCHAR(20) NULL,
                title_border_width INT NOT NULL DEFAULT 0,
                title_shadow_size INT NOT NULL DEFAULT 0,
                published_at DATETIME2 NULL,
                created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
    }

    public function listAdmin(): array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchAll("
            SELECT id,title,slug,version_label,category,status,featured,
                   title_color,title_border_color,title_bg_color,title_shadow_color,
                   title_border_width,title_shadow_size,published_at,created_at,updated_at
            FROM cms_changelog
            ORDER BY id DESC
        ");
    }

    public function latest(int $limit=20): array
    {
        $this->ensureSchema();
        $limit=max(1,min(100,$limit));
        return $this->cmsDb->fetchAll("
            SELECT TOP {$limit}
                id,title,slug,version_label,category,summary,content,featured,
                title_color,title_border_color,title_bg_color,title_shadow_color,
                title_border_width,title_shadow_size,published_at,created_at
            FROM cms_changelog
            WHERE status='published'
              AND (published_at IS NULL OR published_at<=SYSUTCDATETIME())
            ORDER BY featured DESC,COALESCE(published_at,created_at) DESC,id DESC
        ");
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchOne("SELECT * FROM cms_changelog WHERE id=:id",['id'=>$id]);
    }

    public function findBySlug(string $slug): ?array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchOne("
            SELECT TOP 1 * FROM cms_changelog
            WHERE slug=:slug AND status='published'
        ",['slug'=>$slug]);
    }

    public function save(array $data): int
    {
        $this->ensureSchema();

        $title=trim((string)($data['title']??''));
        $slug=trim((string)($data['slug']??''));
        $version=trim((string)($data['version_label']??''));
        $category=trim((string)($data['category']??'GameServer'));
        $summary=trim((string)($data['summary']??''));
        $content=trim((string)($data['content']??''));
        $status=in_array(($data['status']??'draft'),['draft','published'],true)?$data['status']:'draft';
        $featured=!empty($data['featured'])?1:0;

        if($title===''||$content==='') throw new RuntimeException('Título y contenido son obligatorios.');

        if($slug===''){
            $slug=strtolower(trim(preg_replace('/[^A-Za-z0-9]+/','-',$title),'-'));
        }
        if(!preg_match('/^[a-z0-9_-]{3,180}$/',$slug)){
            throw new RuntimeException('El slug contiene caracteres no permitidos.');
        }

        $allowedCategories=['GameServer','Cliente','Launcher','Web','Seguridad','Evento','General'];
        if(!in_array($category,$allowedCategories,true)) $category='General';

        $titleColor=$this->sanitizeColor((string)($data['title_color']??'#f4f0e8'));
        $borderColor=$this->sanitizeColor((string)($data['title_border_color']??'#d5a33d'));
        $bgColor=$this->sanitizeColor((string)($data['title_bg_color']??'#121820'));
        $shadowColor=$this->sanitizeColor((string)($data['title_shadow_color']??'#000000'));
        $borderWidth=max(0,min(6,(int)($data['title_border_width']??0)));
        $shadowSize=max(0,min(20,(int)($data['title_shadow_size']??0)));

        $id=(int)($data['id']??0);

        $params=[
            'title'=>$title,'slug'=>$slug,'version'=>$version,'category'=>$category,
            'summary'=>$summary,'content'=>$content,'status'=>$status,'featured'=>$featured,
            'tc'=>$titleColor,'bc'=>$borderColor,'bg'=>$bgColor,'sc'=>$shadowColor,
            'bw'=>$borderWidth,'ss'=>$shadowSize
        ];

        if($id>0){
            $params['id']=$id;
            $this->cmsDb->execute("
                UPDATE cms_changelog SET
                    title=:title,slug=:slug,version_label=:version,category=:category,
                    summary=:summary,content=:content,status=:status,featured=:featured,
                    title_color=:tc,title_border_color=:bc,title_bg_color=:bg,title_shadow_color=:sc,
                    title_border_width=:bw,title_shadow_size=:ss,
                    published_at=CASE WHEN :status2='published' AND published_at IS NULL THEN SYSUTCDATETIME() ELSE published_at END,
                    updated_at=SYSUTCDATETIME()
                WHERE id=:id
            ",$params+['status2'=>$status]);
            return $id;
        }

        $this->cmsDb->execute("
            INSERT INTO cms_changelog(
                title,slug,version_label,category,summary,content,status,featured,
                title_color,title_border_color,title_bg_color,title_shadow_color,
                title_border_width,title_shadow_size,published_at
            )
            VALUES(
                :title,:slug,:version,:category,:summary,:content,:status,:featured,
                :tc,:bc,:bg,:sc,:bw,:ss,
                CASE WHEN :status2='published' THEN SYSUTCDATETIME() ELSE NULL END
            )
        ",$params+['status2'=>$status]);

        $row=$this->cmsDb->fetchOne("SELECT TOP 1 id FROM cms_changelog WHERE slug=:slug ORDER BY id DESC",['slug'=>$slug]);
        return (int)($row['id']??0);
    }

    public function delete(int $id): void
    {
        $this->ensureSchema();
        $this->cmsDb->execute("DELETE FROM cms_changelog WHERE id=:id",['id'=>$id]);
    }

    public static function renderBbcode(string $text): string
    {
        return BbcodeRenderer::render($text);
    }


    private function sanitizeColor(string $color): string
    {
        $color=trim($color);
        return preg_match('/^#[0-9A-Fa-f]{6}$/',$color)?strtolower($color):'#ffffff';
    }
}
