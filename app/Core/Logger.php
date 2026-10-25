<?php
declare(strict_types=1);
namespace Omega\Core;
final class Logger
{
    public function __construct(private readonly string $file) {}
    public function log(string $level, string $message, array $context=[]): void
    {
        $safe=$context; foreach(['password','token','csrf','secret'] as $k) if(array_key_exists($k,$safe)) $safe[$k]='[REDACTED]';
        $line=sprintf("[%s] %s %s %s\n", date('c'), strtoupper($level), $message, json_encode($safe, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        file_put_contents($this->file,$line,FILE_APPEND|LOCK_EX);
    }
}
