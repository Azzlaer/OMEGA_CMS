<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class DesignStudio
{
    private const MAX_IMAGE = 12_582_912;   // 12 MB
    private const MAX_VIDEO = 62_914_560;   // 60 MB

    private array $sectionLabels = [
        'global'     => '🌌 Fondo Global',
        'home'       => '🏠 Inicio',
        'news'       => '📰 Noticias',
        'changelog'  => '🧾 Changelog',
        'rankings'   => '🏆 Rankings',
        'online'     => '🟢 Online',
        'downloads'  => '⬇ Descargas',
        'info'       => '📖 Información',
        'account'    => '👤 Mi Cuenta',
        'login'      => '🔐 Login',
        'register'   => '📝 Registro',
        'character'  => '⚔ Perfil de Personaje',
        'modules'    => '🧩 Páginas de Módulos',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly string $root
    ) {}

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.cms_design_settings','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_design_settings(
                    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
                    setting_value NVARCHAR(MAX) NULL,
                    updated_at DATETIME2 NOT NULL
                        CONSTRAINT DF_design_settings_updated DEFAULT SYSUTCDATETIME()
                );
            END
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.cms_design_assets','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_design_assets(
                    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    label NVARCHAR(120) NOT NULL,
                    file_name NVARCHAR(180) NOT NULL,
                    public_path NVARCHAR(255) NOT NULL,
                    media_type VARCHAR(12) NOT NULL,
                    mime_type VARCHAR(80) NOT NULL,
                    file_size BIGINT NOT NULL,
                    width INT NULL,
                    height INT NULL,
                    created_by VARCHAR(10) NULL,
                    created_at DATETIME2 NOT NULL
                        CONSTRAINT DF_design_assets_created DEFAULT SYSUTCDATETIME()
                );
            END
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.cms_design_sections','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_design_sections(
                    section_key VARCHAR(60) NOT NULL PRIMARY KEY,
                    enabled BIT NOT NULL CONSTRAINT DF_design_section_enabled DEFAULT 1,
                    background_mode VARCHAR(20) NOT NULL
                        CONSTRAINT DF_design_section_mode DEFAULT 'inherit',
                    asset_id INT NULL,
                    random_asset_ids NVARCHAR(MAX) NULL,
                    rotation_seconds INT NOT NULL
                        CONSTRAINT DF_design_section_rotation DEFAULT 0,
                    overlay_opacity INT NOT NULL
                        CONSTRAINT DF_design_section_overlay DEFAULT 55,
                    background_position VARCHAR(30) NOT NULL
                        CONSTRAINT DF_design_section_position DEFAULT 'center center',
                    updated_at DATETIME2 NOT NULL
                        CONSTRAINT DF_design_section_updated DEFAULT SYSUTCDATETIME()
                );
            END
        ");

        $this->db->execute("
            IF OBJECT_ID('dbo.cms_design_presets','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_design_presets(
                    id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    preset_name NVARCHAR(100) NOT NULL,
                    settings_json NVARCHAR(MAX) NOT NULL,
                    sections_json NVARCHAR(MAX) NOT NULL,
                    created_by VARCHAR(10) NULL,
                    created_at DATETIME2 NOT NULL
                        CONSTRAINT DF_design_presets_created DEFAULT SYSUTCDATETIME()
                );
            END
        ");
    }

    public function sectionLabels(): array
    {
        return $this->sectionLabels;
    }

    public function defaults(): array
    {
        return [
            'theme_bg'          => '#090b0f',
            'theme_panel'       => '#141820',
            'theme_text'        => '#f4f0e8',
            'theme_border'      => '#282f3b',
            'theme_accent'      => '#d5a33d',
            'theme_accent2'     => '#f2c565',
            'theme_radius'      => '10',

            'hero_enabled'      => '0',
            'hero_mode'         => 'none',
            'hero_asset_id'     => '0',
            'hero_overlay'      => '52',
            'hero_height'       => '300',
            'hero_position'     => 'center center',
            'hero_blur'         => '0',

            'page_overlay'      => '46',
            'background_blur'   => '0',
            'background_fixed'  => '1',
        ];
    }

    public function settings(): array
    {
        $this->ensureSchema();
        $settings=$this->defaults();

        $rows=$this->db->fetchAll(
            "SELECT setting_key,setting_value
               FROM dbo.cms_design_settings"
        );

        foreach($rows as $row){
            $settings[(string)$row['setting_key']] =
                (string)($row['setting_value'] ?? '');
        }

        return $settings;
    }

    public function saveSettings(array $input): void
    {
        $allowed=array_keys($this->defaults());
        $data=[];

        foreach($allowed as $key){
            $data[$key]=(string)($input[$key] ?? $this->defaults()[$key]);
        }

        foreach(['theme_bg','theme_panel','theme_text','theme_border','theme_accent','theme_accent2'] as $key){
            if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$data[$key])){
                throw new RuntimeException('COLOR_INVALID_'.$key);
            }
        }

        $data['theme_radius']=(string)$this->clampInt($data['theme_radius'],0,30);
        $data['hero_enabled']=$this->truthy($input['hero_enabled']??'0')?'1':'0';

        if(!in_array($data['hero_mode'],['none','image','video'],true)){
            $data['hero_mode']='none';
        }

        $data['hero_asset_id']=(string)$this->clampInt($data['hero_asset_id'],0,999999999);
        $data['hero_overlay']=(string)$this->clampInt($data['hero_overlay'],0,90);
        $data['hero_height']=(string)$this->clampInt($data['hero_height'],180,760);
        $data['hero_blur']=(string)$this->clampInt($data['hero_blur'],0,12);
        $data['page_overlay']=(string)$this->clampInt($data['page_overlay'],0,90);
        $data['background_blur']=(string)$this->clampInt($data['background_blur'],0,12);
        $data['background_fixed']=$this->truthy($input['background_fixed']??'0')?'1':'0';

        $positions=['center center','center top','center bottom','left center','right center'];
        if(!in_array($data['hero_position'],$positions,true)){
            $data['hero_position']='center center';
        }

        if((int)$data['hero_asset_id']>0){
            $asset=$this->asset((int)$data['hero_asset_id']);
            if(!$asset) throw new RuntimeException('HERO_ASSET_NOT_FOUND');

            if($data['hero_mode']==='image' && $asset['media_type']!=='image'){
                throw new RuntimeException('HERO_REQUIRES_IMAGE');
            }
            if($data['hero_mode']==='video' && $asset['media_type']!=='video'){
                throw new RuntimeException('HERO_REQUIRES_VIDEO');
            }
        }

        $this->db->transaction(function(Database $tx) use($data){
            foreach($data as $key=>$value){
                $exists=$tx->fetchOne(
                    "SELECT setting_key
                       FROM dbo.cms_design_settings
                      WHERE setting_key=:key",
                    ['key'=>$key]
                );

                if($exists){
                    $tx->execute(
                        "UPDATE dbo.cms_design_settings
                            SET setting_value=:value,
                                updated_at=SYSUTCDATETIME()
                          WHERE setting_key=:key",
                        ['value'=>$value,'key'=>$key]
                    );
                }else{
                    $tx->execute(
                        "INSERT INTO dbo.cms_design_settings
                            (setting_key,setting_value,updated_at)
                         VALUES(:key,:value,SYSUTCDATETIME())",
                        ['key'=>$key,'value'=>$value]
                    );
                }
            }
        });
    }

    public function sections(): array
    {
        $this->ensureSchema();

        $stored=[];
        foreach($this->db->fetchAll(
            "SELECT * FROM dbo.cms_design_sections ORDER BY section_key"
        ) as $row){
            $stored[(string)$row['section_key']]=$row;
        }

        $out=[];
        foreach($this->sectionLabels as $key=>$label){
            $row=$stored[$key] ?? [];
            $ids=$this->decodeIds((string)($row['random_asset_ids']??''));

            $out[$key]=[
                'section_key'=>$key,
                'label'=>$label,
                'enabled'=>array_key_exists('enabled',$row)?(int)$row['enabled']:1,
                'background_mode'=>(string)($row['background_mode']??($key==='global'?'default':'inherit')),
                'asset_id'=>(int)($row['asset_id']??0),
                'random_asset_ids'=>$ids,
                'rotation_seconds'=>(int)($row['rotation_seconds']??0),
                'overlay_opacity'=>(int)($row['overlay_opacity']??55),
                'background_position'=>(string)($row['background_position']??'center center'),
            ];
        }

        return $out;
    }

    public function saveSection(array $input): void
    {
        $key=(string)($input['section_key']??'');
        if(!isset($this->sectionLabels[$key])){
            throw new RuntimeException('DESIGN_SECTION_INVALID');
        }

        $mode=(string)($input['background_mode']??'inherit');
        $allowed=$key==='global'
            ? ['default','solid','image','random','video']
            : ['inherit','default','solid','image','random','video'];

        if(!in_array($mode,$allowed,true)){
            throw new RuntimeException('BACKGROUND_MODE_INVALID');
        }

        $assetId=$this->clampInt($input['asset_id']??0,0,999999999);

        $randomRaw=$input['random_asset_ids']??[];
        if(!is_array($randomRaw)) $randomRaw=[];
        $randomIds=[];
        foreach($randomRaw as $id){
            $id=(int)$id;
            if($id>0 && $this->asset($id) && !in_array($id,$randomIds,true)){
                $randomIds[]=$id;
            }
        }

        /*
         * v0.25.2:
         * Si el usuario quita la imagen/video desde AdminCP, hacemos fallback
         * automático en vez de lanzar SECTION_ASSET_REQUIRED.
         */
        if(in_array($mode,['image','video'],true)){
            $asset=$assetId>0 ? $this->asset($assetId) : null;

            if(!$asset){
                $mode=$key==='global' ? 'default' : 'inherit';
                $assetId=0;
            }else{
                if($mode==='image' && $asset['media_type']!=='image'){
                    throw new RuntimeException('SECTION_REQUIRES_IMAGE');
                }

                if($mode==='video' && $asset['media_type']!=='video'){
                    throw new RuntimeException('SECTION_REQUIRES_VIDEO');
                }
            }
        }

        if($mode==='random'){
            $randomIds=array_values(array_filter(
                $randomIds,
                fn(int $id): bool => (($this->asset($id)['media_type']??'')==='image')
            ));

            if(!$randomIds){
                $mode=$key==='global' ? 'default' : 'inherit';
            }
        }

        if(!in_array($mode,['image','video'],true)){
            $assetId=0;
        }

        if($mode!=='random'){
            $randomIds=[];
        }

        $position=(string)($input['background_position']??'center center');
        $positions=['center center','center top','center bottom','left center','right center'];
        if(!in_array($position,$positions,true)) $position='center center';

        $params=[
            'key'=>$key,
            'enabled'=>$this->truthy($input['enabled']??'0')?1:0,
            'mode'=>$mode,
            'asset_id'=>$assetId>0?$assetId:null,
            'random_asset_ids'=>json_encode($randomIds),
            'rotation'=>$this->clampInt($input['rotation_seconds']??0,0,3600),
            'overlay'=>$this->clampInt($input['overlay_opacity']??55,0,90),
            'position'=>$position,
        ];

        $exists=$this->db->fetchOne(
            "SELECT section_key FROM dbo.cms_design_sections WHERE section_key=:key",
            ['key'=>$key]
        );

        if($exists){
            $this->db->execute(
                "UPDATE dbo.cms_design_sections SET
                    enabled=:enabled,
                    background_mode=:mode,
                    asset_id=:asset_id,
                    random_asset_ids=:random_asset_ids,
                    rotation_seconds=:rotation,
                    overlay_opacity=:overlay,
                    background_position=:position,
                    updated_at=SYSUTCDATETIME()
                 WHERE section_key=:key",
                $params
            );
        }else{
            $this->db->execute(
                "INSERT INTO dbo.cms_design_sections(
                    section_key,enabled,background_mode,asset_id,
                    random_asset_ids,rotation_seconds,overlay_opacity,
                    background_position,updated_at
                 ) VALUES(
                    :key,:enabled,:mode,:asset_id,
                    :random_asset_ids,:rotation,:overlay,
                    :position,SYSUTCDATETIME()
                 )",
                $params
            );
        }
    }

    public function assets(): array
    {
        $this->ensureSchema();
        return $this->db->fetchAll(
            "SELECT *
               FROM dbo.cms_design_assets
              ORDER BY id DESC"
        );
    }

    public function asset(int $id): ?array
    {
        if($id<=0) return null;

        return $this->db->fetchOne(
            "SELECT TOP 1 *
               FROM dbo.cms_design_assets
              WHERE id=:id",
            ['id'=>$id]
        );
    }

    public function upload(array $file,string $label,string $actor): void
    {
        $this->ensureSchema();

        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK){
            throw new RuntimeException('MEDIA_UPLOAD_FAILED_'.$error);
        }

        $tmp=(string)($file['tmp_name']??'');
        if($tmp==='' || !is_uploaded_file($tmp)){
            throw new RuntimeException('MEDIA_UPLOAD_INVALID');
        }

        $size=(int)($file['size']??0);
        if($size<=0) throw new RuntimeException('MEDIA_EMPTY');

        $finfo=new \finfo(FILEINFO_MIME_TYPE);
        $mime=(string)$finfo->file($tmp);

        $allowed=[
            'image/jpeg'=>['image','jpg',self::MAX_IMAGE],
            'image/png'=>['image','png',self::MAX_IMAGE],
            'image/webp'=>['image','webp',self::MAX_IMAGE],
            'image/gif'=>['image','gif',self::MAX_IMAGE],
            'video/mp4'=>['video','mp4',self::MAX_VIDEO],
            'video/webm'=>['video','webm',self::MAX_VIDEO],
        ];

        if(!isset($allowed[$mime])){
            throw new RuntimeException('MEDIA_TYPE_NOT_ALLOWED');
        }

        [$type,$ext,$max]=$allowed[$mime];
        if($size>$max){
            throw new RuntimeException($type==='video'?'VIDEO_TOO_LARGE':'IMAGE_TOO_LARGE');
        }

        $width=null;$height=null;
        if($type==='image'){
            $info=@getimagesize($tmp);
            if(!$info) throw new RuntimeException('IMAGE_INVALID');
            $width=(int)$info[0];
            $height=(int)$info[1];
            if($width<320 || $height<120 || $width>7680 || $height>4320){
                throw new RuntimeException('IMAGE_DIMENSIONS_INVALID');
            }
        }

        $dir=$this->root.'/public/uploads/design';
        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)){
            throw new RuntimeException('DESIGN_UPLOAD_DIR_FAILED');
        }

        $name=date('YmdHis').'-'.bin2hex(random_bytes(8)).'.'.$ext;
        $dest=$dir.'/'.$name;

        if(!@move_uploaded_file($tmp,$dest)){
            throw new RuntimeException('MEDIA_SAVE_FAILED');
        }

        $label=trim(strip_tags($label));
        if($label==='') $label=(string)($file['name']??'Media');
        if(mb_strlen($label)>120) $label=mb_substr($label,0,120);

        $this->db->execute(
            "INSERT INTO dbo.cms_design_assets(
                label,file_name,public_path,media_type,mime_type,file_size,
                width,height,created_by,created_at
             ) VALUES(
                :label,:file_name,:public_path,:media_type,:mime_type,:file_size,
                :width,:height,:created_by,SYSUTCDATETIME()
             )",
            [
                'label'=>$label,
                'file_name'=>$name,
                'public_path'=>'/public/uploads/design/'.$name,
                'media_type'=>$type,
                'mime_type'=>$mime,
                'file_size'=>$size,
                'width'=>$width,
                'height'=>$height,
                'created_by'=>$actor,
            ]
        );
    }

    public function deleteAsset(int $id): void
    {
        $this->ensureSchema();

        if($id<=0){
            throw new RuntimeException('ASSET_ID_INVALID');
        }

        $asset=$this->asset($id);
        if(!$asset){
            throw new RuntimeException('ASSET_NOT_FOUND');
        }

        $settings=$this->settings();

        if((int)($settings['hero_asset_id']??0)===$id){
            throw new RuntimeException(
                'No se puede eliminar: este archivo está siendo usado por el Header / Hero.'
            );
        }

        foreach($this->sections() as $section){
            if((int)($section['asset_id']??0)===$id){
                throw new RuntimeException(
                    'No se puede eliminar: este archivo está asignado a la sección '.
                    ($section['label']??$section['section_key']).'.'
                );
            }

            if(in_array($id,$section['random_asset_ids']??[],true)){
                throw new RuntimeException(
                    'No se puede eliminar: este archivo forma parte de la colección aleatoria de '.
                    ($section['label']??$section['section_key']).'.'
                );
            }
        }

        $publicPath=(string)($asset['public_path']??'');

        /*
         * En v0.24.0 los assets se guardaron como:
         *   /public/uploads/design/archivo.ext
         *
         * El filesystem real es:
         *   {root}/public/uploads/design/archivo.ext
         *
         * Normalizamos el path y jamás usamos la ruta completa proveniente de DB
         * para evitar traversal.
         */
        $fileName=basename((string)($asset['file_name']??basename($publicPath)));

        if(!preg_match('/^[A-Za-z0-9._-]+$/',$fileName)){
            throw new RuntimeException('ASSET_FILENAME_INVALID');
        }

        $filePath=$this->root.'/public/uploads/design/'.$fileName;

        /*
         * Primero se elimina el archivo físico. Si existe y no puede borrarse,
         * NO eliminamos el registro SQL para no dejar un archivo huérfano.
         */
        if(is_file($filePath)){
            if(!@unlink($filePath)){
                throw new RuntimeException(
                    'No fue posible eliminar el archivo físico. Revisa permisos de public/uploads/design.'
                );
            }
        }

        $affected=$this->db->execute(
            "DELETE FROM dbo.cms_design_assets WHERE id=:id",
            ['id'=>$id]
        );

        if($affected<1){
            throw new RuntimeException('No fue posible eliminar el registro de la Biblioteca Multimedia.');
        }
    }

    public function presets(): array
    {
        return $this->db->fetchAll(
            "SELECT id,preset_name,created_by,created_at
               FROM dbo.cms_design_presets
              ORDER BY id DESC"
        );
    }

    public function savePreset(string $name,string $actor): void
    {
        $name=trim(strip_tags($name));
        if($name==='' || mb_strlen($name)>100){
            throw new RuntimeException('PRESET_NAME_INVALID');
        }

        $this->db->execute(
            "INSERT INTO dbo.cms_design_presets(
                preset_name,settings_json,sections_json,created_by,created_at
             ) VALUES(
                :name,:settings,:sections,:actor,SYSUTCDATETIME()
             )",
            [
                'name'=>$name,
                'settings'=>json_encode($this->settings(),JSON_UNESCAPED_UNICODE),
                'sections'=>json_encode($this->sections(),JSON_UNESCAPED_UNICODE),
                'actor'=>$actor,
            ]
        );
    }

    public function applyPreset(int $id): void
    {
        $row=$this->db->fetchOne(
            "SELECT TOP 1 settings_json,sections_json
               FROM dbo.cms_design_presets
              WHERE id=:id",
            ['id'=>$id]
        );
        if(!$row) throw new RuntimeException('PRESET_NOT_FOUND');

        $settings=json_decode((string)$row['settings_json'],true);
        $sections=json_decode((string)$row['sections_json'],true);

        if(!is_array($settings) || !is_array($sections)){
            throw new RuntimeException('PRESET_CORRUPT');
        }

        $this->saveSettings($settings);

        foreach($sections as $key=>$section){
            if(!is_array($section)) continue;
            $section['section_key']=(string)$key;
            $section['enabled']=!empty($section['enabled'])?'1':'0';
            $section['random_asset_ids']=$section['random_asset_ids']??[];
            $this->saveSection($section);
        }
    }

    public function publicContext(string $template,string $active,string $uri): array
    {
        $settings=$this->settings();
        $sections=$this->sections();
        $assetsById=[];

        foreach($this->assets() as $asset){
            $assetsById[(int)$asset['id']]=$asset;
        }

        $sectionKey=$this->resolveSectionKey($template,$active,$uri);
        $section=$sections[$sectionKey] ?? $sections['global'];
        $global=$sections['global'];

        if(!$section['enabled'] || $section['background_mode']==='inherit'){
            $section=$global;
        }

        $pageType='default';
        $pageUrls=[];
        $position=(string)($section['background_position']??'center center');
        $overlay=(int)($section['overlay_opacity']??$settings['page_overlay']);
        $rotation=(int)($section['rotation_seconds']??0);

        if($section['background_mode']==='solid'){
            $pageType='solid';
        }elseif($section['background_mode']==='image'){
            $asset=$assetsById[(int)$section['asset_id']]??null;
            if($asset && $asset['media_type']==='image'){
                $pageType='image';
                $pageUrls[]=(string)$asset['public_path'];
            }
        }elseif($section['background_mode']==='video'){
            $asset=$assetsById[(int)$section['asset_id']]??null;
            if($asset && $asset['media_type']==='video'){
                $pageType='video';
                $pageUrls[]=(string)$asset['public_path'];
            }
        }elseif($section['background_mode']==='random'){
            foreach($section['random_asset_ids'] as $id){
                $asset=$assetsById[(int)$id]??null;
                if($asset && $asset['media_type']==='image'){
                    $pageUrls[]=(string)$asset['public_path'];
                }
            }
            if($pageUrls){
                shuffle($pageUrls);
                $pageType='random';
            }
        }

        $hero=[
            'enabled'=>$settings['hero_enabled']==='1',
            'mode'=>$settings['hero_mode'],
            'url'=>'',
            'overlay'=>(int)$settings['hero_overlay'],
            'height'=>(int)$settings['hero_height'],
            'position'=>(string)$settings['hero_position'],
            'blur'=>(int)$settings['hero_blur'],
        ];

        $heroAsset=$assetsById[(int)$settings['hero_asset_id']]??null;
        if($hero['enabled'] && $heroAsset){
            if($hero['mode']==='image' && $heroAsset['media_type']==='image'){
                $hero['url']=(string)$heroAsset['public_path'];
            }elseif($hero['mode']==='video' && $heroAsset['media_type']==='video'){
                $hero['url']=(string)$heroAsset['public_path'];
            }
        }

        return [
            'settings'=>$settings,
            'section_key'=>$sectionKey,
            'page'=>[
                'type'=>$pageType,
                'urls'=>$pageUrls,
                'position'=>$position,
                'overlay'=>$overlay,
                'rotation'=>$rotation,
                'blur'=>(int)$settings['background_blur'],
                'fixed'=>$settings['background_fixed']==='1',
            ],
            'hero'=>$hero,
        ];
    }

    private function resolveSectionKey(string $template,string $active,string $uri): string
    {
        if($template==='home') return 'home';
        if($template==='public_character_profile') return 'character';
        if($template==='module_user_page') return 'modules';

        if(str_starts_with($template,'account_') || in_array($template,['account','character','password'],true)){
            return 'account';
        }

        if(isset($this->sectionLabels[$active]) && $active!=='global'){
            return $active;
        }

        if(str_starts_with($uri,'/login')) return 'login';
        if(str_starts_with($uri,'/register')) return 'register';
        if(str_starts_with($uri,'/account/modules/')) return 'modules';

        return 'global';
    }

    private function decodeIds(string $json): array
    {
        if($json==='') return [];
        $ids=json_decode($json,true);
        if(!is_array($ids)) return [];
        return array_values(array_unique(array_filter(
            array_map('intval',$ids),
            static fn(int $id): bool => $id>0
        )));
    }

    private function clampInt(mixed $value,int $min,int $max): int
    {
        return max($min,min($max,(int)$value));
    }

    private function truthy(mixed $value): bool
    {
        if(is_bool($value)) return $value;
        return in_array(strtolower((string)$value),['1','true','yes','on'],true);
    }
}
