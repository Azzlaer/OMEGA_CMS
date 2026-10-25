<?php
use Omega\Core\Env;
$mk = static fn(string $p): array => [
 'driver'=>Env::get($p.'_DB_DRIVER','sqlsrv'),
 'host'=>Env::get($p.'_DB_HOST','127.0.0.1'),
 'port'=>(int)Env::get($p.'_DB_PORT',1433),
 'database'=>Env::get($p.'_DB_DATABASE'),
 'username'=>Env::get($p.'_DB_USERNAME'),
 'password'=>Env::get($p.'_DB_PASSWORD'),
 'trust_cert'=>Env::bool($p.'_DB_TRUST_CERT',true),
 'encrypt'=>Env::bool($p.'_DB_ENCRYPT',false),
];
return ['mu'=>$mk('MU'),'cms'=>$mk('CMS')];
