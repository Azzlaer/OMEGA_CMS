<?php
declare(strict_types=1);

namespace Omega\GameServer\Drivers\MuEmu097kKayito;

final class CharacterClass
{
    public static function name(int $class): string
    {
        return match($class) {
            0  => 'Dark Wizard',
            1  => 'Soul Master',
            16 => 'Dark Knight',
            17 => 'Blade Knight',
            32 => 'Fairy Elf',
            33 => 'Muse Elf',
            48 => 'Magic Gladiator',
            default => 'Unknown'
        };
    }

    public static function shortName(int $class): string
    {
        return match($class) {
            0  => 'DW',
            1  => 'SM',
            16 => 'DK',
            17 => 'BK',
            32 => 'FE',
            33 => 'ME',
            48 => 'MG',
            default => '?'
        };
    }

    public static function isEvolved(int $class): bool
    {
        return in_array($class, [1,17,33], true);
    }

    public static function evolutionLabel(int $class): string
    {
        if ($class === 48) return 'Especial';
        return self::isEvolved($class) ? 'Evolucionado' : 'Base';
    }

    public static function supported(): array
    {
        return [
            0  => ['slug'=>'dark-wizard','name'=>'Dark Wizard','short'=>'DW','evolved'=>false],
            1  => ['slug'=>'soul-master','name'=>'Soul Master','short'=>'SM','evolved'=>true],
            16 => ['slug'=>'dark-knight','name'=>'Dark Knight','short'=>'DK','evolved'=>false],
            17 => ['slug'=>'blade-knight','name'=>'Blade Knight','short'=>'BK','evolved'=>true],
            32 => ['slug'=>'fairy-elf','name'=>'Fairy Elf','short'=>'FE','evolved'=>false],
            33 => ['slug'=>'muse-elf','name'=>'Muse Elf','short'=>'ME','evolved'=>true],
            48 => ['slug'=>'magic-gladiator','name'=>'Magic Gladiator','short'=>'MG','evolved'=>null],
        ];
    }

    public static function classFromSlug(string $slug): ?int
    {
        foreach (self::supported() as $id=>$info) {
            if ($info['slug'] === $slug) return $id;
        }
        return null;
    }
}
