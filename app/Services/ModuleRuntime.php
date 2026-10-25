<?php
declare(strict_types=1);
namespace Omega\Services;

use Omega\Core\Database;
use Omega\Core\ModuleSecrets;

final class ModuleRuntime
{
    public function __construct(
        private readonly string $root,
        private readonly Database $cmsDb,
        private readonly Database $muDb
    ) {}

    public function isEnabled(string $slug): bool
    {
        try {
            $row = $this->cmsDb->fetchOne(
                "SELECT is_enabled FROM cms_modules WHERE slug=:s",
                ['s'=>$slug]
            );
            return $row !== null && (int)$row['is_enabled'] === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public function enabledManifests(): array
    {
        try {
            $rows = $this->cmsDb->fetchAll(
                "SELECT slug,manifest_json,config_json FROM cms_modules WHERE is_enabled=1 ORDER BY slug"
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $manifest = json_decode((string)($row['manifest_json'] ?? ''), true);
            if (!is_array($manifest)) continue;
            $config = json_decode((string)($row['config_json'] ?? ''), true);
            if (!is_array($config)) $config = [];
            $config = ModuleSecrets::decodeConfig($config, (array)($manifest['config_schema'] ?? []));
            $out[] = ['slug'=>(string)$row['slug'],'manifest'=>$manifest,'config'=>$config];
        }
        return $out;
    }

    public function userMenu(): array
    {
        $items=[];
        foreach($this->enabledManifests() as $m){
            $menu=$m['manifest']['user_menu']??null;
            if(!is_array($menu) || empty($menu['label'])) continue;
            $items[]=[
                'slug'=>$m['slug'],
                'label'=>(string)$menu['label'],
                'icon'=>(string)($menu['icon']??'⬡'),
                'url'=>'/account/modules/'.rawurlencode($m['slug']),
                'order'=>(int)($menu['order']??100),
            ];
        }
        usort($items,fn($a,$b)=>$a['order']<=>$b['order']);
        return $items;
    }

    public function assetsFor(string $area): array
    {
        $assets = [];
        foreach ($this->enabledManifests() as $m) {
            $list = $m['manifest']['assets'][$area] ?? [];
            if (!is_array($list)) continue;
            foreach ($list as $asset) {
                $asset = ltrim(str_replace('\\','/',(string)$asset), '/');
                if ($asset === '' || str_contains('/'.$asset.'/', '/../')) continue;
                $ext = strtolower(pathinfo($asset, PATHINFO_EXTENSION));
                if (!in_array($ext, ['css','js'], true)) continue;
                $assets[] = [
                    'slug'=>$m['slug'],
                    'path'=>$asset,
                    'type'=>$ext,
                    'url'=>'/module-assets/'.rawurlencode($m['slug']).'/'.implode('/', array_map('rawurlencode', explode('/',$asset))),
                ];
            }
        }
        return $assets;
    }

    public function invokeAllFirst(string $hook,array $payload=[]): mixed
    {
        foreach($this->enabledManifests() as $module){
            try{
                $result=$this->invokeLoaded($module,$hook,$payload);
                if($result!==null && $result!=='') return $result;
            }catch(\Throwable $e){
                $this->log($module['slug'],'ERROR',$hook.': '.$e->getMessage());
            }
        }
        return null;
    }

    public function invokeAllCollect(string $hook,array $payload=[]): string
    {
        $html='';
        foreach($this->enabledManifests() as $module){
            try{
                $result=$this->invokeLoaded($module,$hook,$payload);
                if(is_string($result) && $result!=='')$html.=$result."\n";
            }catch(\Throwable $e){
                $this->log($module['slug'],'ERROR',$hook.': '.$e->getMessage());
            }
        }
        return $html;
    }

    public function dispatch(string $hook, array $payload): void
    {
        foreach ($this->enabledManifests() as $module) {
            try {
                $this->invokeLoaded($module, $hook, $payload);
            } catch (\Throwable $e) {
                $this->log($module['slug'], 'ERROR', $hook.': '.$e->getMessage());
            }
        }
    }

    public function invoke(string $slug, string $hook, array $payload=[]): mixed
    {
        foreach ($this->enabledManifests() as $module) {
            if ($module['slug'] === $slug) {
                return $this->invokeLoaded($module, $hook, $payload);
            }
        }
        throw new \RuntimeException('El módulo no está instalado/activo.');
    }

    private function invokeLoaded(array $module, string $hook, array $payload): mixed
    {
        $slug = (string)$module['slug'];
        $entry = $this->root.'/modules/'.$slug.'/Module.php';
        if (!is_file($entry)) return null;

        $handler = require $entry;
        if (!is_callable($handler)) {
            throw new \RuntimeException('Module.php debe retornar un callable.');
        }

        $context = [
            'root'=>$this->root,
            'module_dir'=>dirname($entry),
            'cms_db'=>$this->cmsDb,
            'mu_db'=>$this->muDb,
            'log'=>function(string $level,string $message) use($slug): void {
                $this->log($slug,$level,$message);
            },
        ];

        return $handler($hook,$payload,$module['config'],$context);
    }

    private function log(string $slug,string $level,string $message): void
    {
        $dir=$this->root.'/storage/logs';
        if(!is_dir($dir)) @mkdir($dir,0775,true);
        $slug=preg_replace('/[^a-z0-9_-]/i','',$slug);
        $line=sprintf("[%s] [%s] %s\n",gmdate('c'),strtoupper($level),str_replace(["\r","\n"],' ',$message));
        @file_put_contents($dir.'/module-'.$slug.'.log',$line,FILE_APPEND|LOCK_EX);
    }
}
