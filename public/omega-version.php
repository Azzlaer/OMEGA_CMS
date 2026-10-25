<?php
declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(function(string $class) use($root){
    $prefix='Omega\\';
    if(!str_starts_with($class,$prefix)) return;
    $rel=str_replace('\\','/',substr($class,strlen($prefix)));
    $file=$root.'/app/'.$rel.'.php';
    if(is_file($file)) require $file;
});

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'omega_core_version' => \Omega\Core\Version::current(),
    'supports_module_minimum_0_7_1' => \Omega\Core\Version::satisfies('0.7.1'),
    'supports_module_minimum_0_7_2' => \Omega\Core\Version::satisfies('0.7.2'),
    'php' => PHP_VERSION,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
