<?php
declare(strict_types=1);
namespace Omega\Core;

final class ModuleManager
{
    public function __construct(private readonly string $modulesPath) {}
    public function discover(): array
    {
        $out=[];
        foreach (glob($this->modulesPath.'/*/module.json') ?: [] as $manifest) {
            $data=json_decode((string)file_get_contents($manifest),true);
            if(is_array($data) && isset($data['name'],$data['slug'],$data['version'])) $out[]=$data;
        }
        return $out;
    }
}
