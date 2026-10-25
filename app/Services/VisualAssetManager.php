<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class VisualAssetManager
{
    private const MAX_UPLOAD = 2_000_000;

    private const DEFINITIONS = [
        'class.0'  => ['label'=>'Dark Wizard',      'group'=>'Clases', 'default'=>'/public/assets/img/classes/dark-wizard.jpg'],
        'class.1'  => ['label'=>'Soul Master',      'group'=>'Clases', 'default'=>'/public/assets/img/classes/soul-master.jpg'],
        'class.16' => ['label'=>'Dark Knight',      'group'=>'Clases', 'default'=>'/public/assets/img/classes/dark-knight.jpg'],
        'class.17' => ['label'=>'Blade Knight',     'group'=>'Clases', 'default'=>'/public/assets/img/classes/blade-knight.jpg'],
        'class.32' => ['label'=>'Fairy Elf',        'group'=>'Clases', 'default'=>'/public/assets/img/classes/fairy-elf.jpg'],
        'class.33' => ['label'=>'Muse Elf',         'group'=>'Clases', 'default'=>'/public/assets/img/classes/muse-elf.jpg'],
        'class.48' => ['label'=>'Magic Gladiator',  'group'=>'Clases', 'default'=>'/public/assets/img/classes/magic-gladiator.jpg'],

        'ranking.level'        => ['label'=>'Ranking Nivel',          'group'=>'Rankings', 'default'=>null],
        'ranking.reset'        => ['label'=>'Ranking Reset',          'group'=>'Rankings', 'default'=>null],
        'ranking.grandreset'   => ['label'=>'Ranking Grand Reset',    'group'=>'Rankings', 'default'=>null],
        'ranking.guilds'       => ['label'=>'Ranking Guilds',         'group'=>'Rankings', 'default'=>null],
        'ranking.duel'         => ['label'=>'Ranking Duelos',         'group'=>'Rankings', 'default'=>null],
        'ranking.skyevent'     => ['label'=>'Ranking Sky Event',      'group'=>'Rankings', 'default'=>null],
        'ranking.minigames'    => ['label'=>'Ranking MiniGames',      'group'=>'Rankings', 'default'=>null],
        'ranking.goldenarcher' => ['label'=>'Ranking Golden Archer',  'group'=>'Rankings', 'default'=>null],

        'online.default'       => ['label'=>'Usuarios Online',        'group'=>'Sistema',  'default'=>null],
    ];

    public function __construct(
        private readonly Database $cmsDb,
        private readonly string $root
    ) {}

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_visual_assets','U') IS NULL
            CREATE TABLE dbo.cms_visual_assets(
                asset_key VARCHAR(120) NOT NULL PRIMARY KEY,
                label VARCHAR(160) NOT NULL,
                asset_group VARCHAR(80) NOT NULL,
                image_url VARCHAR(500) NULL,
                updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
            )
        ");

        foreach (self::DEFINITIONS as $key=>$def) {
            $this->cmsDb->execute("
                IF NOT EXISTS(SELECT 1 FROM dbo.cms_visual_assets WHERE asset_key=:k)
                INSERT INTO dbo.cms_visual_assets(asset_key,label,asset_group,image_url)
                VALUES(:k2,:l,:g,:u)
            ", [
                'k'=>$key,'k2'=>$key,
                'l'=>$def['label'],
                'g'=>$def['group'],
                'u'=>$def['default']
            ]);
        }
    }

    public function all(): array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchAll("
            SELECT asset_key,label,asset_group,image_url,updated_at
            FROM cms_visual_assets
            ORDER BY
                CASE asset_group WHEN 'Clases' THEN 1 WHEN 'Rankings' THEN 2 ELSE 3 END,
                label
        ");
    }

    public function map(): array
    {
        $rows=$this->all();
        $map=[];
        foreach($rows as $r) $map[(string)$r['asset_key']] = (string)($r['image_url'] ?? '');
        return $map;
    }

    public function url(string $key): ?string
    {
        $this->ensureSchema();
        $row=$this->cmsDb->fetchOne(
            "SELECT image_url FROM cms_visual_assets WHERE asset_key=:k",
            ['k'=>$key]
        );
        $url=trim((string)($row['image_url'] ?? ''));
        return $url!==''?$url:null;
    }

    public function saveUrl(string $key,string $url): void
    {
        $this->assertKnownKey($key);
        $url=trim($url);
        if($url!=='' && !str_starts_with($url,'/public/') && !filter_var($url,FILTER_VALIDATE_URL)){
            throw new RuntimeException('La URL debe ser absoluta o comenzar con /public/.');
        }
        $this->cmsDb->execute(
            "UPDATE cms_visual_assets SET image_url=:u,updated_at=SYSUTCDATETIME() WHERE asset_key=:k",
            ['u'=>$url!==''?$url:null,'k'=>$key]
        );
    }

    public function upload(string $key,array $file): string
    {
        $this->assertKnownKey($key);

        if(($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
            throw new RuntimeException('No se recibió una imagen válida.');
        }
        $size=(int)($file['size'] ?? 0);
        if($size<1 || $size>self::MAX_UPLOAD){
            throw new RuntimeException('La imagen debe pesar menos de 2 MB.');
        }

        $tmp=(string)($file['tmp_name'] ?? '');
        if(!is_file($tmp)) throw new RuntimeException('Archivo temporal no disponible.');

        $info=@getimagesize($tmp);
        if(!$info) throw new RuntimeException('El archivo no es una imagen válida.');

        $mime=(string)($info['mime'] ?? '');
        $ext=match($mime){
            'image/jpeg'=>'jpg',
            'image/png'=>'png',
            'image/webp'=>'webp',
            default=>throw new RuntimeException('Solo se permiten JPG, PNG o WEBP.')
        };

        if((int)$info[0] > 2048 || (int)$info[1] > 2048){
            throw new RuntimeException('La imagen no puede superar 2048x2048.');
        }

        $dir=$this->root.'/public/uploads/icons';
        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)){
            throw new RuntimeException('No se pudo crear public/uploads/icons.');
        }

        $safe=preg_replace('/[^a-z0-9_-]+/i','-',str_replace('.','-',$key));
        $name=$safe.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3)).'.'.$ext;
        $dest=$dir.'/'.$name;

        if(!@move_uploaded_file($tmp,$dest)){
            if(!@copy($tmp,$dest)) throw new RuntimeException('No se pudo guardar la imagen.');
        }

        $url='/public/uploads/icons/'.$name;
        $this->saveUrl($key,$url);
        return $url;
    }

    public function reset(string $key): void
    {
        $this->assertKnownKey($key);
        $default=self::DEFINITIONS[$key]['default'] ?? null;
        $this->cmsDb->execute(
            "UPDATE cms_visual_assets SET image_url=:u,updated_at=SYSUTCDATETIME() WHERE asset_key=:k",
            ['u'=>$default,'k'=>$key]
        );
    }

    private function assertKnownKey(string $key): void
    {
        if(!array_key_exists($key,self::DEFINITIONS)){
            throw new RuntimeException('Variable visual no reconocida.');
        }
    }
}
