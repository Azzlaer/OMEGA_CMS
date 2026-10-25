<?php
use Omega\Core\Env;
return [
 'name'=>Env::get('APP_NAME','Omega MU CMS'),
 'env'=>Env::get('APP_ENV','production'),
 'debug'=>Env::bool('APP_DEBUG',false),
 'game_driver'=>Env::get('GAME_DRIVER','muemu097k_kayito'),
];
