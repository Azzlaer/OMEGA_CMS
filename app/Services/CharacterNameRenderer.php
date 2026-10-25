<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;
use Omega\Core\Security;

final class CharacterNameRenderer
{
    /** @var array<string,string> */
    private array $cache=[];

    public function __construct(
        private readonly ModuleRuntime $modules,
        private readonly Database $muDb
    ) {}

    public function render(string $name,array $context=[]): string
    {
        $name=trim($name);
        if($name==='') return '';

        $key=strtolower($name);
        if(array_key_exists($key,$this->cache)){
            return $this->cache[$key];
        }

        $safe=Security::e($name);

        /*
         * Preferred path: module hook.
         */
        try {
            $result=$this->modules->invokeAllFirst('character.name.render',[
                'name'=>$name,
                'context'=>$context
            ]);

            if(is_string($result) && $result!=='' && $result!==$safe){
                return $this->cache[$key]=$result;
            }
        } catch(\Throwable) {
            // Continue to bridge fallback.
        }

        /*
         * Fallback bridge:
         * Only active when Name Effects Professional itself is enabled.
         * This keeps module ON/OFF authoritative but avoids silent hook failures
         * hiding effects from Rankings / Online / Account.
         */
        if($this->modules->isEnabled('name-effects')){
            try {
                $result=$this->renderNameEffectDirect($name);
                if($result!==null){
                    return $this->cache[$key]=$result;
                }
            } catch(\Throwable) {
                // Final fallback remains escaped plain name.
            }
        }

        return $this->cache[$key]=$safe;
    }

    /**
     * Diagnostic information for AdminCP.
     *
     * @return array<string,mixed>
     */
    public function diagnose(string $name): array
    {
        $name=trim($name);

        $out=[
            'character'=>$name,
            'module_enabled'=>$this->modules->isEnabled('name-effects'),
            'assignment'=>null,
            'hook_result'=>null,
            'direct_result'=>null,
            'errors'=>[],
        ];

        if($name==='') return $out;

        try{
            $safe=Security::e($name);
            $hook=$this->modules->invokeAllFirst('character.name.render',[
                'name'=>$name,
                'context'=>['area'=>'diagnostic']
            ]);
            $out['hook_result']=is_string($hook)?$hook:null;
            $out['hook_changed_name']=is_string($hook) && $hook!=='' && $hook!==$safe;
        }catch(\Throwable $e){
            $out['errors'][]='Hook: '.$e->getMessage();
        }

        try{
            $out['assignment']=$this->activeEffectRow($name);
        }catch(\Throwable $e){
            $out['errors'][]='DB: '.$e->getMessage();
        }

        try{
            $out['direct_result']=$this->renderNameEffectDirect($name);
        }catch(\Throwable $e){
            $out['errors'][]='Direct renderer: '.$e->getMessage();
        }

        return $out;
    }

    private function activeEffectRow(string $name): ?array
    {
        return $this->muDb->fetchOne("
            SELECT TOP 1
                a.AssignmentID,
                LTRIM(RTRIM(a.AccountID)) AS AccountID,
                LTRIM(RTRIM(a.CharacterName)) AS CharacterName,
                a.EffectID,
                a.ActivatedAt,
                a.ExpiresAt,
                a.IsActive AS AssignmentActive,
                e.EffectName,
                e.EffectKey,
                e.ImagePath,
                e.TextColor,
                e.GlowColor,
                e.GlowSize,
                e.CustomCss,
                e.IsActive AS EffectActive
            FROM OmegaNameEffectAssignments a
            INNER JOIN OmegaNameEffects e ON e.EffectID=a.EffectID
            WHERE LTRIM(RTRIM(a.CharacterName))=:name
              AND a.IsActive=1
              AND e.IsActive=1
              AND (a.ExpiresAt IS NULL OR a.ExpiresAt>SYSUTCDATETIME())
            ORDER BY a.AssignmentID DESC
        ",['name'=>$name]);
    }

    private function renderNameEffectDirect(string $name): ?string
    {
        $fx=$this->activeEffectRow($name);
        if(!$fx) return null;

        $style=[];

        $imagePath=trim((string)($fx['ImagePath']??''));
        if($imagePath!==''){
            $img=$this->assetUrl($imagePath);
            if($img!==''){
                $escapedImg=str_replace(
                    ['"',"'",')'],
                    ['%22','%27','%29'],
                    $img
                );
                $style[]='background-image:url("'.$escapedImg.'")';
                $style[]='background-repeat:repeat-x';
                $style[]='background-position:center center';
                $style[]='background-size:auto 100%';
            }
        }

        $style[]='color:'.$this->color((string)($fx['TextColor']??''),'#ffffff');

        $glowSize=max(0,min(25,(int)($fx['GlowSize']??0)));
        if($glowSize>0){
            $style[]='text-shadow:0 0 '.$glowSize.'px '.
                $this->color((string)($fx['GlowColor']??''),'#d4af37');
        }

        $custom=$this->sanitizeCss((string)($fx['CustomCss']??''));
        if($custom!=='') $style[]=$custom;

        /*
         * Important: inline display/padding here means the effect remains visible
         * even if site.head from the module was not loaded for some reason.
         */
        $style[]='display:inline-block';
        $style[]='line-height:1.25';

        return '<span class="omega-name-effect" data-effect="'.
            Security::e((string)$fx['EffectKey']).
            '" style="'.
            Security::e(implode(';',$style)).
            '">'.
            Security::e($name).
            '</span>';
    }

    private function assetUrl(string $path): string
    {
        $path=trim(str_replace('\\','/',$path));
        if($path==='') return '';

        if(preg_match('#^https?://#i',$path)){
            return $path;
        }

        if(str_starts_with($path,'/public/')){
            return $path;
        }

        if(str_starts_with($path,'assets/')){
            $parts=array_values(array_filter(
                explode('/',$path),
                static fn(string $p): bool => $p!=='' && $p!=='.'
            ));

            foreach($parts as $part){
                if($part==='..') return '';
            }

            return '/module-assets/name-effects/'.
                implode('/',array_map('rawurlencode',$parts));
        }

        return '';
    }

    private function color(string $value,string $default): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/',$value)
            ? strtolower($value)
            : $default;
    }

    private function sanitizeCss(string $css): string
    {
        $css=trim($css);
        if($css==='') return '';

        if(strlen($css)>1500){
            $css=substr($css,0,1500);
        }

        $css=preg_replace('/url\s*\([^)]*\)/i','',$css)??'';
        $css=preg_replace('/@import\b[^;]*;?/i','',$css)??'';
        $css=preg_replace('/expression\s*\([^)]*\)/i','',$css)??'';
        $css=preg_replace('/javascript\s*:/i','',$css)??'';
        $css=preg_replace('/behavior\s*:/i','',$css)??'';

        return $css;
    }
}
