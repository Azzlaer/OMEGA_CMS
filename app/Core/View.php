<?php
declare(strict_types=1);
namespace Omega\Core;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $root = dirname(__DIR__, 2);
        $file = $root . '/templates/' . ltrim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template no encontrado: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}
