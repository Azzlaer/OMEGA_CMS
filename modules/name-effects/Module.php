<?php
declare(strict_types=1);

/*
 * v1.1.4 cache-safe loader.
 * The implementation filename is versioned intentionally so LiteSpeed/OPcache
 * cannot reuse bytecode from Name Effects v1.0-v1.1.3.
 */
return require __DIR__.'/Module_v114.php';
