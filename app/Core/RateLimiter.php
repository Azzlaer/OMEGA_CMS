<?php
declare(strict_types=1);
namespace Omega\Core;

final class RateLimiter
{
    public function __construct(private readonly string $dir) {}
    public function hit(string $key, int $max, int $window): bool
    {
        if (!is_dir($this->dir)) @mkdir($this->dir, 0770, true);
        $file = $this->dir.'/'.hash('sha256',$key).'.json';
        $now = time(); $data=['start'=>$now,'count'=>0];
        if (is_file($file)) $data = json_decode((string)file_get_contents($file), true) ?: $data;
        if ($now - (int)$data['start'] >= $window) $data=['start'=>$now,'count'=>0];
        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return (int)$data['count'] <= $max;
    }
}
