<?php
declare(strict_types=1);
namespace Omega\Core;

final class Security
{
    public static function headers(bool $googleServices=false,bool $hcaptcha=false): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        $script="'self'";
        $connect="'self'";
        $frame="https://www.youtube.com https://youtube.com https://www.youtube-nocookie.com https://youtube-nocookie.com";

        if ($googleServices) {
            $script.=" https://www.googletagmanager.com https://pagead2.googlesyndication.com https://www.googleadservices.com https://googleads.g.doubleclick.net";
            $connect.=" https://www.google-analytics.com https://region1.google-analytics.com https://analytics.google.com https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net";
            $frame.=" https://googleads.g.doubleclick.net https://tpc.googlesyndication.com";
        }

        $style="'self' 'unsafe-inline'";
        if ($hcaptcha) {
            $script.=" https://hcaptcha.com https://*.hcaptcha.com";
            $connect.=" https://hcaptcha.com https://*.hcaptcha.com";
            $frame.=" https://hcaptcha.com https://*.hcaptcha.com";
            $style.=" https://hcaptcha.com https://*.hcaptcha.com";
        }

        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src {$style}; script-src {$script}; connect-src {$connect}; frame-src {$frame}; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        header('X-Frame-Options: DENY');
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
