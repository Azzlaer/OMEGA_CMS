<?php
declare(strict_types=1);

$root = __DIR__;

// cPanel-friendly entry point: public_html may be the project root.
if (!is_file($root . '/storage/installed.lock')) {
    header('Location: install/');
    exit;
}

require $root . '/public/index.php';
