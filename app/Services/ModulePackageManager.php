<?php
declare(strict_types=1);
namespace Omega\Services;

use Omega\Core\Database;
use Omega\Core\ModuleSecrets;
use Omega\Core\Version;
use RuntimeException;
use ZipArchive;

final class ModulePackageManager
{
    private const MAX_ZIP_SIZE = 25_000_000;
    private const MAX_FILES = 500;
    private const MAX_TOTAL_UNCOMPRESSED = 80_000_000;

    public function __construct(
        private readonly string $modulesDir,
        private readonly string $storageDir,
        private readonly Database $cmsDb
    ) {}

    public function ensureSchema(): void
    {
        $this->cmsDb->execute("
            IF OBJECT_ID('dbo.cms_module_files','U') IS NULL
            CREATE TABLE dbo.cms_module_files(
                module_slug VARCHAR(80) NOT NULL,
                relative_path VARCHAR(500) NOT NULL,
                sha256 CHAR(64) NULL,
                installed_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
                CONSTRAINT PK_cms_module_files PRIMARY KEY(module_slug,relative_path)
            )
        ");
        $this->cmsDb->execute("
            IF COL_LENGTH('dbo.cms_modules','manifest_json') IS NULL
                ALTER TABLE dbo.cms_modules ADD manifest_json VARCHAR(MAX) NULL
        ");
        $this->cmsDb->execute("
            IF COL_LENGTH('dbo.cms_modules','author') IS NULL
                ALTER TABLE dbo.cms_modules ADD author VARCHAR(120) NULL
        ");
        $this->cmsDb->execute("
            IF COL_LENGTH('dbo.cms_modules','description') IS NULL
                ALTER TABLE dbo.cms_modules ADD description VARCHAR(500) NULL
        ");
        $this->cmsDb->execute("
            IF COL_LENGTH('dbo.cms_modules','installed_at') IS NULL
                ALTER TABLE dbo.cms_modules ADD installed_at DATETIME2 NULL
        ");
    }

    public function inspectUpload(array $file): array
    {
        $this->assertZipSupport();
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se recibió un ZIP válido.');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_ZIP_SIZE) {
            throw new RuntimeException('El ZIP supera el límite permitido de 25 MB.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if (!is_file($tmp)) throw new RuntimeException('Archivo temporal no disponible.');

        return $this->inspectZip($tmp);
    }

    public function installUploaded(array $file): array
    {
        $this->ensureSchema();
        $manifest = $this->inspectUpload($file);
        $tmp = (string)$file['tmp_name'];

        $slug = $manifest['slug'];
        $target = rtrim($this->modulesDir,'/\\') . DIRECTORY_SEPARATOR . $slug;
        $stageRoot = rtrim($this->storageDir,'/\\') . DIRECTORY_SEPARATOR . 'module-stage';
        if (!is_dir($stageRoot) && !@mkdir($stageRoot,0775,true) && !is_dir($stageRoot)) {
            throw new RuntimeException('No fue posible crear storage/module-stage.');
        }
        $stage = $stageRoot . DIRECTORY_SEPARATOR . $slug . '-' . bin2hex(random_bytes(5));
        if (!@mkdir($stage,0775,true)) throw new RuntimeException('No fue posible preparar el módulo.');

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) throw new RuntimeException('No fue posible abrir el ZIP.');

        try {
            $rootPrefix = $this->detectRootPrefix($zip);
            $files = [];
            for ($i=0; $i<$zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if ($name === '' || str_ends_with($name,'/')) continue;
                $relative = $this->stripPrefix($name,$rootPrefix);
                if ($relative === '') continue;
                $this->assertSafeRelativePath($relative);

                $dest = $stage . DIRECTORY_SEPARATOR . str_replace('/',DIRECTORY_SEPARATOR,$relative);
                $dir = dirname($dest);
                if (!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)) {
                    throw new RuntimeException('No se pudo crear directorio del módulo.');
                }
                $stream = $zip->getStream($name);
                if (!$stream) throw new RuntimeException('No se pudo leer '.$relative);
                $out = @fopen($dest,'wb');
                if (!$out) { fclose($stream); throw new RuntimeException('No se pudo escribir '.$relative); }
                stream_copy_to_stream($stream,$out);
                fclose($stream); fclose($out);
                $files[] = $relative;
            }
        } finally {
            $zip->close();
        }

        if (!is_file($stage.'/module.json')) {
            $this->rrmdir($stage);
            throw new RuntimeException('El paquete no contiene module.json en la raíz.');
        }

        $stagedManifest = $this->loadManifest($stage.'/module.json');
        if ($stagedManifest['slug'] !== $slug) {
            $this->rrmdir($stage);
            throw new RuntimeException('El slug del manifiesto cambió durante la extracción.');
        }

        $backup = null;
        if (is_dir($target)) {
            $backupRoot = rtrim($this->storageDir,'/\\').DIRECTORY_SEPARATOR.'module-backups';
            if (!is_dir($backupRoot)) @mkdir($backupRoot,0775,true);
            $backup = $backupRoot.DIRECTORY_SEPARATOR.$slug.'-'.gmdate('Ymd-His');
            if (!@rename($target,$backup)) {
                $this->rrmdir($stage);
                throw new RuntimeException('No fue posible respaldar la versión instalada.');
            }
        }

        try {
            if (!@rename($stage,$target)) {
                throw new RuntimeException('No fue posible mover el módulo a /modules/'.$slug);
            }
        } catch (\Throwable $e) {
            if ($backup && is_dir($backup) && !is_dir($target)) @rename($backup,$target);
            if (is_dir($stage)) $this->rrmdir($stage);
            throw $e;
        }

        $this->registerInstalled($stagedManifest,$target,$files);
        $this->invalidatePhpCache($target);

        return [
            'manifest'=>$stagedManifest,
            'files'=>$files,
            'backup'=>$backup ? basename($backup) : null,
        ];
    }

    public function uninstall(string $slug): void
    {
        $this->ensureSchema();
        $slug = $this->validateSlug($slug);
        $target = rtrim($this->modulesDir,'/\\').DIRECTORY_SEPARATOR.$slug;

        $known = $this->cmsDb->fetchOne("SELECT slug FROM cms_modules WHERE slug=:s",['s'=>$slug]);
        if (!$known) throw new RuntimeException('El módulo no está registrado.');

        if (is_dir($target)) $this->rrmdir($target);
        $this->cmsDb->transaction(function(Database $db) use($slug): void {
            $db->execute("DELETE FROM cms_module_files WHERE module_slug=:s",['s'=>$slug]);
            $db->execute("DELETE FROM cms_modules WHERE slug=:s",['s'=>$slug]);
        });
    }

    public function installedModules(array $discovered): array
    {
        $this->ensureSchema();
        $rows = $this->cmsDb->fetchAll("SELECT * FROM cms_modules ORDER BY name");
        $db = [];
        foreach($rows as $r) $db[$r['slug']]=$r;

        $disc = [];
        foreach($discovered as $m) $disc[(string)$m['slug']]=$m;

        $slugs = array_values(array_unique(array_merge(array_keys($db),array_keys($disc))));
        sort($slugs);
        $out=[];
        foreach($slugs as $slug){
            $row=$db[$slug]??[];
            $mf=$disc[$slug]??[];
            $manifest=[];
            if (!empty($row['manifest_json'])) {
                $tmp=json_decode((string)$row['manifest_json'],true);
                if(is_array($tmp)) $manifest=$tmp;
            } elseif ($mf) $manifest=$mf;

            $out[]=[
                'slug'=>$slug,
                'name'=>$row['name']??$manifest['name']??$slug,
                'version'=>$row['version']??$manifest['version']??'0.0.0',
                'author'=>$row['author']??$manifest['author']??'',
                'description'=>$row['description']??$manifest['description']??'',
                'is_enabled'=>isset($row['is_enabled'])?(bool)$row['is_enabled']:(bool)($manifest['enabled_by_default']??true),
                'installed'=>isset($db[$slug]),
                'manifest'=>$manifest,
                'installed_at'=>$row['installed_at']??null,
            ];
        }
        return $out;
    }

    public function manifest(string $slug): ?array
    {
        $slug=$this->validateSlug($slug);
        $file=rtrim($this->modulesDir,'/\\').DIRECTORY_SEPARATOR.$slug.DIRECTORY_SEPARATOR.'module.json';
        return is_file($file)?$this->loadManifest($file):null;
    }

    public function config(string $slug): array
    {
        $row=$this->cmsDb->fetchOne("SELECT config_json FROM cms_modules WHERE slug=:s",['s'=>$slug]);
        $cfg=[];
        if($row && !empty($row['config_json'])){
            $tmp=json_decode((string)$row['config_json'],true);
            if(is_array($tmp))$cfg=$tmp;
        }
        $manifest=$this->manifest($slug)??[];
        foreach(($manifest['config_schema']??[]) as $field){
            $key=(string)($field['key']??'');
            if($key!=='' && !array_key_exists($key,$cfg))$cfg[$key]=$field['default']??null;
        }
        return ModuleSecrets::decodeConfig($cfg,(array)($manifest['config_schema']??[]));
    }

    public function saveConfig(string $slug,array $input): array
    {
        $manifest=$this->manifest($slug);
        if(!$manifest)throw new RuntimeException('Manifiesto del módulo no encontrado.');
        $schema=$manifest['config_schema']??[];

        $row=$this->cmsDb->fetchOne("SELECT config_json FROM cms_modules WHERE slug=:s",['s'=>$slug]);
        $rawCurrent=[];
        if($row && !empty($row['config_json'])){
            $tmp=json_decode((string)$row['config_json'],true);
            if(is_array($tmp))$rawCurrent=$tmp;
        }

        $stored=[];
        $display=[];
        foreach($schema as $field){
            $key=(string)($field['key']??'');
            if($key==='')continue;
            $type=(string)($field['type']??'text');
            $value=$input[$key]??null;

            if($type==='boolean'){
                $value=!empty($input[$key]);
                $stored[$key]=$display[$key]=$value;
            } elseif($type==='integer'){
                $value=(int)$value;
                if(isset($field['min']))$value=max((int)$field['min'],$value);
                if(isset($field['max']))$value=min((int)$field['max'],$value);
                $stored[$key]=$display[$key]=$value;
            } elseif($type==='select'){
                $allowed=array_map('strval',array_keys((array)($field['options']??[])));
                $value=(string)$value;
                if(!in_array($value,$allowed,true))$value=(string)($field['default']??'');
                $stored[$key]=$display[$key]=$value;
            } elseif($type==='secret'){
                $value=trim((string)$value);
                if($value===''){
                    $stored[$key]=$rawCurrent[$key]??'';
                    $display[$key]=ModuleSecrets::decrypt((string)($stored[$key]??''));
                } else {
                    $stored[$key]=ModuleSecrets::encrypt($value);
                    $display[$key]=$value;
                }
            } else {
                $value=trim((string)$value);
                $max=(int)($field['max_length']??500);
                if(strlen($value)>$max)$value=substr($value,0,$max);
                $stored[$key]=$display[$key]=$value;
            }
        }
        $json=json_encode($stored,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $this->cmsDb->execute("UPDATE cms_modules SET config_json=:j,updated_at=SYSUTCDATETIME() WHERE slug=:s",['j'=>$json,'s'=>$slug]);
        return $display;
    }

    public function files(string $slug): array
    {
        return $this->cmsDb->fetchAll("SELECT relative_path,sha256,installed_at FROM cms_module_files WHERE module_slug=:s ORDER BY relative_path",['s'=>$slug]);
    }

    private function inspectZip(string $path): array
    {
        $zip=new ZipArchive();
        if($zip->open($path)!==true)throw new RuntimeException('El archivo no es un ZIP válido.');
        try{
            if($zip->numFiles<1 || $zip->numFiles>self::MAX_FILES)throw new RuntimeException('Cantidad de archivos no permitida.');
            $total=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $st=$zip->statIndex($i);
                $name=(string)($st['name']??'');
                $total+=(int)($st['size']??0);
                if($total>self::MAX_TOTAL_UNCOMPRESSED)throw new RuntimeException('El contenido descomprimido supera 80 MB.');
                if($name!=='')$this->assertSafeArchiveName($name);
            }
            $prefix=$this->detectRootPrefix($zip);
            $manifestName=$prefix.'module.json';
            $idx=$zip->locateName($manifestName,ZipArchive::FL_NOCASE);
            if($idx===false)throw new RuntimeException('No se encontró module.json.');
            $raw=$zip->getFromIndex($idx);
            if(!is_string($raw))throw new RuntimeException('No fue posible leer module.json.');
            $manifest=$this->validateManifest(json_decode($raw,true));
            return $manifest;
        } finally {$zip->close();}
    }

    private function registerInstalled(array $m,string $target,array $files): void
    {
        $manifestJson=json_encode($m,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $this->cmsDb->transaction(function(Database $db) use($m,$target,$files,$manifestJson):void{
            $db->execute("
                IF EXISTS(SELECT 1 FROM cms_modules WHERE slug=:s)
                    UPDATE cms_modules SET name=:n,version=:v,author=:a,description=:d,manifest_json=:mj,
                    updated_at=SYSUTCDATETIME(),installed_at=COALESCE(installed_at,SYSUTCDATETIME()) WHERE slug=:s2
                ELSE
                    INSERT INTO cms_modules(slug,name,version,author,description,is_enabled,manifest_json,installed_at)
                    VALUES(:s3,:n2,:v2,:a2,:d2,:en,:mj2,SYSUTCDATETIME())
            ",[
                's'=>$m['slug'],'n'=>$m['name'],'v'=>$m['version'],'a'=>$m['author']??'','d'=>$m['description']??'','mj'=>$manifestJson,'s2'=>$m['slug'],
                's3'=>$m['slug'],'n2'=>$m['name'],'v2'=>$m['version'],'a2'=>$m['author']??'','d2'=>$m['description']??'',
                'en'=>!empty($m['enabled_by_default'])?1:0,'mj2'=>$manifestJson
            ]);
            $db->execute("DELETE FROM cms_module_files WHERE module_slug=:s",['s'=>$m['slug']]);
            foreach($files as $rel){
                $full=$target.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel);
                $hash=is_file($full)?hash_file('sha256',$full):null;
                $db->execute("INSERT INTO cms_module_files(module_slug,relative_path,sha256) VALUES(:s,:p,:h)",['s'=>$m['slug'],'p'=>$rel,'h'=>$hash]);
            }
        });
    }

    private function loadManifest(string $file): array
    {
        $raw=@file_get_contents($file);
        if(!is_string($raw))throw new RuntimeException('No se pudo leer module.json.');
        return $this->validateManifest(json_decode($raw,true));
    }

    private function validateManifest(mixed $m): array
    {
        if(!is_array($m))throw new RuntimeException('module.json no contiene JSON válido.');
        foreach(['name','slug','version'] as $key){
            if(empty($m[$key]) || !is_string($m[$key]))throw new RuntimeException("Falta {$key} en module.json.");
        }
        $m['slug']=$this->validateSlug($m['slug']);
        if(!preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9._-]+)?$/',(string)$m['version']))throw new RuntimeException('La versión debe usar SemVer, por ejemplo 1.0.0.');
        if(strlen((string)$m['name'])>120)throw new RuntimeException('Nombre de módulo demasiado largo.');
        $min=(string)($m['minimum_core']??'0.1.0');
        if(!Version::satisfies($min)){
            throw new RuntimeException(
                "El módulo requiere Omega MU CMS {$min} o superior. ".
                "Core detectado: ".Version::current()."."
            );
        }
        if(isset($m['config_schema']) && !is_array($m['config_schema']))throw new RuntimeException('config_schema debe ser un arreglo.');
        return $m;
    }

    private function validateSlug(string $slug): string
    {
        $slug=strtolower(trim($slug));
        if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,78}[a-z0-9]$/',$slug))throw new RuntimeException('Slug de módulo inválido.');
        return $slug;
    }

    private function assertZipSupport():void
    {
        if(!class_exists(ZipArchive::class))throw new RuntimeException('PHP ZipArchive no está disponible. Activa la extensión zip en cPanel.');
    }
    private function assertSafeArchiveName(string $name):void
    {
        $name=str_replace('\\','/',$name);
        if(str_contains($name,"\0") || str_starts_with($name,'/') || preg_match('#^[A-Za-z]:/#',$name) || str_contains('/'.$name.'/','/../'))throw new RuntimeException('El ZIP contiene una ruta insegura: '.$name);
    }
    private function assertSafeRelativePath(string $path):void
    {
        $this->assertSafeArchiveName($path);
        $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
        $allowed=['php','json','md','txt','css','js','gif','png','jpg','jpeg','webp','svg','sql'];
        if($ext!=='' && !in_array($ext,$allowed,true))throw new RuntimeException('Tipo de archivo no permitido en módulo: '.$path);
        if(strtolower(basename($path))==='.htaccess')throw new RuntimeException('.htaccess no está permitido dentro de módulos importados.');
    }
    private function detectRootPrefix(ZipArchive $zip):string
    {
        if($zip->locateName('module.json',ZipArchive::FL_NOCASE)!==false)return '';
        $prefix=null;
        for($i=0;$i<$zip->numFiles;$i++){
            $name=str_replace('\\','/',(string)$zip->getNameIndex($i));
            if($name==='')continue;
            $first=explode('/',$name,2)[0];
            if($prefix===null)$prefix=$first;
            elseif($first!==$prefix)return '';
        }
        if($prefix && $zip->locateName($prefix.'/module.json',ZipArchive::FL_NOCASE)!==false)return $prefix.'/';
        return '';
    }
    private function stripPrefix(string $name,string $prefix):string
    {
        $name=str_replace('\\','/',$name);
        return $prefix!=='' && str_starts_with($name,$prefix)?substr($name,strlen($prefix)):$name;
    }
    private function invalidatePhpCache(string $moduleDir): void
    {
        if (!is_dir($moduleDir)) return;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleDir,\FilesystemIterator::SKIP_DOTS)
        );

        foreach($iterator as $file){
            if(!$file->isFile() || strtolower($file->getExtension())!=='php') continue;
            $path=$file->getPathname();

            // Clear PHP's stat cache first because module upgrades replace the
            // directory atomically via rename().
            clearstatcache(true,$path);

            // LiteSpeed/cPanel commonly enables OPcache. Invalidate the exact
            // installed PHP path so a new module version cannot execute stale
            // bytecode from the previous ZIP.
            if(function_exists('opcache_invalidate')){
                @opcache_invalidate($path,true);
            }
        }
    }

    private function rrmdir(string $dir):void
    {
        if(!is_dir($dir))return;
        $items=scandir($dir);
        if(!is_array($items))return;
        foreach($items as $item){
            if($item==='.'||$item==='..')continue;
            $p=$dir.DIRECTORY_SEPARATOR.$item;
            if(is_dir($p))$this->rrmdir($p);else @unlink($p);
        }
        @rmdir($dir);
    }
}
