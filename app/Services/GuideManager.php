<?php
declare(strict_types=1);
namespace Omega\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Omega\Core\Database;
use RuntimeException;

final class GuideManager
{
    private string $uploadDir;
    private string $publicUploadPrefix = '/public/uploads/guides/';

    public function __construct(
        private readonly Database $cmsDb,
        private readonly string $root
    ) {
        $this->uploadDir = rtrim($root, '/\\').'/public/uploads/guides';
    }

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_guides','U') IS NULL
            CREATE TABLE dbo.cms_guides(
                id INT IDENTITY(1,1) PRIMARY KEY,
                title NVARCHAR(180) NOT NULL,
                slug VARCHAR(160) NOT NULL,
                summary NVARCHAR(700) NULL,
                content_html NVARCHAR(MAX) NOT NULL,
                category NVARCHAR(60) NULL,
                author VARCHAR(64) NOT NULL,
                logo_url NVARCHAR(700) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                featured BIT NOT NULL DEFAULT 0,
                views INT NOT NULL DEFAULT 0,
                published_at DATETIME2 NULL,
                created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");
        $this->cmsDb->execute("
            IF NOT EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name='UX_cms_guides_slug' AND object_id=OBJECT_ID('dbo.cms_guides')
            )
            CREATE UNIQUE INDEX UX_cms_guides_slug ON dbo.cms_guides(slug)
        ");
        $this->cmsDb->execute("
            IF NOT EXISTS (
                SELECT 1 FROM sys.indexes
                WHERE name='IX_cms_guides_status_date' AND object_id=OBJECT_ID('dbo.cms_guides')
            )
            CREATE INDEX IX_cms_guides_status_date ON dbo.cms_guides(status,published_at,created_at)
        ");
    }

    public function adminList(): array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchAll("
            SELECT id,title,slug,category,author,logo_url,status,featured,views,published_at,created_at,updated_at
            FROM cms_guides
            ORDER BY featured DESC,id DESC
        ");
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchOne("SELECT * FROM cms_guides WHERE id=:id", ['id'=>$id]);
    }

    public function publicList(string $query='', string $category=''): array
    {
        $this->ensureSchema();
        $query = trim($query);
        $category = trim($category);
        $where = ["status='published'"];
        $params = [];
        if ($query !== '') {
            $where[] = '(title LIKE :q1 OR summary LIKE :q2 OR category LIKE :q3 OR author LIKE :q4)';
            $needle = '%'.$query.'%';
            $params['q1']=$needle; $params['q2']=$needle; $params['q3']=$needle; $params['q4']=$needle;
        }
        if ($category !== '') {
            $where[] = 'category=:category';
            $params['category'] = $category;
        }
        return $this->cmsDb->fetchAll("
            SELECT id,title,slug,summary,category,author,logo_url,featured,views,published_at,created_at
            FROM cms_guides
            WHERE ".implode(' AND ',$where)."
            ORDER BY featured DESC,ISNULL(published_at,created_at) DESC,id DESC
        ", $params);
    }

    public function categories(): array
    {
        $this->ensureSchema();
        $rows = $this->cmsDb->fetchAll("
            SELECT category,COUNT(*) AS total
            FROM cms_guides
            WHERE status='published' AND category IS NOT NULL AND LTRIM(RTRIM(category))<>''
            GROUP BY category
            ORDER BY category
        ");
        return $rows;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $this->ensureSchema();
        $row = $this->cmsDb->fetchOne("
            SELECT * FROM cms_guides WHERE slug=:slug AND status='published'
        ", ['slug'=>$slug]);
        if (!$row) return null;
        $this->cmsDb->execute("UPDATE cms_guides SET views=views+1 WHERE id=:id", ['id'=>(int)$row['id']]);
        $row['views'] = (int)($row['views'] ?? 0) + 1;
        return $row;
    }

    public function save(array $data, array $files, string $authorAccount): int
    {
        $this->ensureSchema();
        $id = (int)($data['id'] ?? 0);
        $existing = $id > 0 ? $this->find($id) : null;
        if ($id > 0 && !$existing) throw new RuntimeException('La guía que intentas editar ya no existe.');

        $title = trim((string)($data['title'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $summary = trim((string)($data['summary'] ?? ''));
        $content = trim((string)($data['content_html'] ?? ''));
        $category = trim((string)($data['category'] ?? 'General'));
        $status = in_array(($data['status'] ?? 'draft'), ['draft','published'], true) ? (string)$data['status'] : 'draft';
        $featured = !empty($data['featured']) ? 1 : 0;

        if ($title === '' || $content === '') throw new RuntimeException('El título y el contenido HTML son obligatorios.');
        if (strlen($title) > 180) throw new RuntimeException('El título es demasiado largo.');
        if (strlen($summary) > 700) throw new RuntimeException('El resumen es demasiado largo.');
        if (strlen($category) > 60) throw new RuntimeException('La categoría es demasiado larga.');

        if ($slug === '') $slug = $this->slugify($title);
        $slug = strtolower($slug);
        if (!preg_match('/^[a-z0-9_-]{3,160}$/', $slug)) {
            throw new RuntimeException('El slug solo puede contener letras minúsculas, números, guion y guion bajo.');
        }
        $dup = $this->cmsDb->fetchOne("SELECT id FROM cms_guides WHERE slug=:slug AND id<>:id", ['slug'=>$slug,'id'=>$id]);
        if ($dup) throw new RuntimeException('Ya existe otra guía con ese slug.');

        $content = self::sanitizeHtml($content);
        if (trim(strip_tags($content)) === '' && !str_contains($content,'<img') && !str_contains($content,'<iframe')) {
            throw new RuntimeException('El contenido HTML quedó vacío después de aplicar el filtro de seguridad.');
        }

        $logo = (string)($existing['logo_url'] ?? '');
        if (!empty($data['remove_logo'])) {
            $this->deleteLocalLogo($logo);
            $logo = '';
        }

        $remoteLogo = trim((string)($data['logo_remote'] ?? ''));
        if ($remoteLogo !== '') {
            $validated = $this->validateRemoteImageUrl($remoteLogo);
            if ($logo !== $validated) $this->deleteLocalLogo($logo);
            $logo = $validated;
        }

        if (isset($files['logo_file']) && is_array($files['logo_file']) && (int)($files['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newLogo = $this->storeLogoUpload($files['logo_file']);
            $this->deleteLocalLogo($logo);
            $logo = $newLogo;
        }

        $author = trim((string)($existing['author'] ?? $authorAccount));
        if ($author === '') $author = 'Administrador';

        if ($id > 0) {
            $this->cmsDb->execute("
                UPDATE cms_guides SET
                    title=:title,slug=:slug,summary=:summary,content_html=:content,category=:category,
                    logo_url=:logo,status=:status,featured=:featured,
                    published_at=CASE WHEN :status2='published' AND published_at IS NULL THEN SYSUTCDATETIME() ELSE published_at END,
                    updated_at=SYSUTCDATETIME()
                WHERE id=:id
            ", [
                'title'=>$title,'slug'=>$slug,'summary'=>$summary,'content'=>$content,'category'=>$category,
                'logo'=>$logo !== '' ? $logo : null,'status'=>$status,'featured'=>$featured,'status2'=>$status,'id'=>$id
            ]);
            return $id;
        }

        $this->cmsDb->execute("
            INSERT INTO cms_guides(title,slug,summary,content_html,category,author,logo_url,status,featured,published_at)
            VALUES(:title,:slug,:summary,:content,:category,:author,:logo,:status,:featured,
                   CASE WHEN :status2='published' THEN SYSUTCDATETIME() ELSE NULL END)
        ", [
            'title'=>$title,'slug'=>$slug,'summary'=>$summary,'content'=>$content,'category'=>$category,'author'=>$author,
            'logo'=>$logo !== '' ? $logo : null,'status'=>$status,'featured'=>$featured,'status2'=>$status
        ]);
        $row = $this->cmsDb->fetchOne("SELECT TOP 1 id FROM cms_guides WHERE slug=:slug ORDER BY id DESC", ['slug'=>$slug]);
        return (int)($row['id'] ?? 0);
    }

    public function delete(int $id): void
    {
        $row = $this->find($id);
        if (!$row) return;
        $this->cmsDb->execute("DELETE FROM cms_guides WHERE id=:id", ['id'=>$id]);
        $this->deleteLocalLogo((string)($row['logo_url'] ?? ''));
    }

    private function slugify(string $value): string
    {
        $ascii = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
        if (!is_string($ascii) || $ascii === '') $ascii = $value;
        $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/','-',$ascii),'-'));
        if (strlen($slug) < 3) $slug = 'guia-'.date('YmdHis');
        return substr($slug,0,160);
    }

    private function validateRemoteImageUrl(string $url): string
    {
        if (strlen($url) > 700 || !filter_var($url,FILTER_VALIDATE_URL)) {
            throw new RuntimeException('La URL remota del logo no es válida.');
        }
        $scheme = strtolower((string)parse_url($url,PHP_URL_SCHEME));
        if ($scheme !== 'https') {
            throw new RuntimeException('El logo remoto debe usar HTTPS.');
        }
        return $url;
    }

    private function storeLogoUpload(array $file): string
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No fue posible subir el logo (código '.$error.').');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 4 * 1024 * 1024) throw new RuntimeException('El logo debe pesar menos de 4 MB.');
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('El archivo subido no es válido.');

        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mime = (string)finfo_file($fi,$tmp); finfo_close($fi); }
        }
        if ($mime === '' && function_exists('mime_content_type')) $mime = (string)mime_content_type($tmp);
        $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (!isset($map[$mime])) throw new RuntimeException('Formato de logo no permitido. Usa JPG, PNG, GIF o WEBP.');
        if (@getimagesize($tmp) === false) throw new RuntimeException('El archivo no contiene una imagen válida.');

        if (!is_dir($this->uploadDir) && !@mkdir($this->uploadDir,0755,true) && !is_dir($this->uploadDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de logos de guías.');
        }
        $filename = 'guide_'.date('Ymd_His').'_'.bin2hex(random_bytes(6)).'.'.$map[$mime];
        $dest = $this->uploadDir.'/'.$filename;
        if (!move_uploaded_file($tmp,$dest)) throw new RuntimeException('No se pudo guardar el logo en el servidor.');
        return $this->publicUploadPrefix.$filename;
    }

    private function deleteLocalLogo(string $logo): void
    {
        if ($logo === '' || !str_starts_with($logo,$this->publicUploadPrefix)) return;
        $name = basename($logo);
        $path = $this->uploadDir.'/'.$name;
        if (is_file($path)) @unlink($path);
    }

    public static function sanitizeHtml(string $html): string
    {
        // Drop dangerous containers including their contents before DOM normalization.
        $html = preg_replace('#<(script|style|object|embed|form|svg|math)\b[^>]*>.*?</\1\s*>#is','',$html) ?? $html;
        $allowed = ['p','br','h2','h3','h4','strong','b','em','i','u','s','blockquote','ul','ol','li','a','img','figure','figcaption','table','thead','tbody','tr','th','td','hr','code','pre','span','div','iframe'];
        $dropWhole = ['script','style','object','embed','form','input','button','textarea','select','option','meta','link','base','svg','math'];

        if (!class_exists(DOMDocument::class)) {
            $clean = strip_tags($html,'<'.implode('><',$allowed).'>');
            $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$clean) ?? $clean;
            $clean = preg_replace('/\s+(style|srcdoc)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$clean) ?? $clean;
            $clean = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|data|vbscript):.*?\2/i','$1="#"',$clean) ?? $clean;
            return $clean;
        }

        $dom = new DOMDocument('1.0','UTF-8');
        $prev = libxml_use_internal_errors(true);
        $wrapped = '<!doctype html><html><body><div id="omega-guide-root">'.$html.'</div></body></html>';
        $dom->loadHTML('<?xml encoding="UTF-8">'.$wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $dom->getElementById('omega-guide-root');
        if (!$root) return '';

        $walk = function(DOMNode $node) use (&$walk,$allowed,$dropWhole): void {
            $children = [];
            foreach ($node->childNodes as $child) $children[] = $child;
            foreach ($children as $child) {
                if ($child instanceof DOMElement) {
                    $tag = strtolower($child->tagName);
                    if (in_array($tag,$dropWhole,true)) {
                        $child->parentNode?->removeChild($child);
                        continue;
                    }
                    if (!in_array($tag,$allowed,true)) {
                        $parent = $child->parentNode;
                        if ($parent) {
                            while ($child->firstChild) $parent->insertBefore($child->firstChild,$child);
                            $parent->removeChild($child);
                        }
                        continue;
                    }
                    self::cleanElementAttributes($child,$tag);
                    $walk($child);
                }
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $child) $out .= $dom->saveHTML($child);
        return trim($out);
    }

    private static function cleanElementAttributes(DOMElement $el, string $tag): void
    {
        $global = ['class','title'];
        $specific = [
            'a'=>['href','target','rel'],
            'img'=>['src','alt','width','height','loading'],
            'iframe'=>['src','width','height','title','allow','allowfullscreen','loading'],
            'th'=>['colspan','rowspan'], 'td'=>['colspan','rowspan'],
            'ol'=>['start'],
        ];
        $allowed = array_merge($global,$specific[$tag] ?? []);
        $remove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->name);
            if (str_starts_with($name,'on') || !in_array($name,$allowed,true)) $remove[] = $attr->name;
        }
        foreach ($remove as $name) $el->removeAttribute($name);

        if ($el->hasAttribute('class')) {
            $class = preg_replace('/[^A-Za-z0-9 _-]/','',(string)$el->getAttribute('class')) ?? '';
            if ($class === '') $el->removeAttribute('class'); else $el->setAttribute('class',substr($class,0,180));
        }
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $href = trim($el->getAttribute('href'));
            if (!self::safeUrl($href,true)) $el->setAttribute('href','#');
            if (strtolower($el->getAttribute('target')) === '_blank') $el->setAttribute('rel','noopener noreferrer');
            else $el->removeAttribute('target');
        }
        if ($tag === 'img') {
            $src = trim($el->getAttribute('src'));
            if (!self::safeUrl($src,false)) { $el->parentNode?->removeChild($el); return; }
            $el->setAttribute('loading','lazy');
        }
        if ($tag === 'iframe') {
            $src = trim($el->getAttribute('src'));
            if (!self::safeYoutubeEmbed($src)) { $el->parentNode?->removeChild($el); return; }
            $el->setAttribute('loading','lazy');
            $el->setAttribute('allowfullscreen','allowfullscreen');
            $el->setAttribute('referrerpolicy','strict-origin-when-cross-origin');
        }
        foreach (['width','height','colspan','rowspan','start'] as $n) {
            if ($el->hasAttribute($n) && !preg_match('/^\d{1,4}$/',$el->getAttribute($n))) $el->removeAttribute($n);
        }
    }

    private static function safeUrl(string $url, bool $allowRelative): bool
    {
        if ($url === '') return false;
        if ($allowRelative && (str_starts_with($url,'/') || str_starts_with($url,'#'))) return true;
        if (!filter_var($url,FILTER_VALIDATE_URL)) return false;
        return in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true);
    }

    private static function safeYoutubeEmbed(string $url): bool
    {
        if (!filter_var($url,FILTER_VALIDATE_URL)) return false;
        if (strtolower((string)parse_url($url,PHP_URL_SCHEME)) !== 'https') return false;
        $host = strtolower((string)parse_url($url,PHP_URL_HOST));
        $path = (string)parse_url($url,PHP_URL_PATH);
        return in_array($host,['www.youtube.com','youtube.com','www.youtube-nocookie.com','youtube-nocookie.com'],true)
            && str_starts_with($path,'/embed/');
    }
}
