<?php
declare(strict_types=1);
namespace Omega\Repositories;

use Omega\Core\Database;

final class NewsRepository
{
    public function __construct(private readonly Database $db) {}

    public function latest(int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        return $this->db->fetchAll("
            SELECT TOP {$limit}
                id,title,slug,summary,content,image,category,featured,published_at,created_at
            FROM cms_news
            WHERE status = 'published'
              AND (published_at IS NULL OR published_at <= SYSUTCDATETIME())
            ORDER BY featured DESC, COALESCE(published_at, created_at) DESC, id DESC
        ");
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne("
            SELECT TOP 1 id,title,slug,summary,content,image,category,featured,published_at,created_at
            FROM cms_news
            WHERE slug = :slug AND status = 'published'
        ", ['slug'=>$slug]);
    }
}
