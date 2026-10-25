<?php
declare(strict_types=1);
namespace Omega\Core;

final class Version
{
    public const VERSION = '0.18.0';

    public static function current(): string
    {
        return self::VERSION;
    }

    public static function satisfies(string $minimum): bool
    {
        return version_compare(self::VERSION, $minimum, '>=');
    }
}
