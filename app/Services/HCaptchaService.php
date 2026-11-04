<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Env;
use RuntimeException;

final class HCaptchaService
{
    private const VERIFY_URL='https://api.hcaptcha.com/siteverify';

    public function enabled(): bool
    {
        return Env::bool('HCAPTCHA_ENABLED',false);
    }

    public function protects(string $context): bool
    {
        if(!$this->enabled()) return false;

        $context=strtolower(trim($context));
        $map=[
            'login'=>'HCAPTCHA_LOGIN',
            'register'=>'HCAPTCHA_REGISTER',
            'recovery'=>'HCAPTCHA_RECOVERY',
        ];

        if(!isset($map[$context])) return false;

        /*
         * Compatibilidad hacia atrás:
         * si la variable específica no existe, login y registro quedan
         * protegidos automáticamente cuando HCAPTCHA_ENABLED=true.
         * Recovery queda preparado para cuando el flujo SMTP esté activo.
         */
        $default=in_array($context,['login','register'],true);
        return Env::bool($map[$context],$default);
    }

    public function siteKey(): string
    {
        return trim((string)Env::get('HCAPTCHA_SITEKEY',''));
    }

    private function secret(): string
    {
        return trim((string)Env::get('HCAPTCHA_SECRET',''));
    }

    public function configured(): bool
    {
        return $this->siteKey()!=='' && $this->secret()!=='';
    }

    public function verify(string $token,string $remoteIp='',string $context='login'): bool
    {
        if(!$this->protects($context)) return true;

        if(!$this->configured()){
            throw new RuntimeException('HCAPTCHA_NOT_CONFIGURED');
        }

        $token=trim($token);
        if($token===''){
            throw new RuntimeException('HCAPTCHA_REQUIRED');
        }

        if(!function_exists('curl_init')){
            throw new RuntimeException('HCAPTCHA_CURL_REQUIRED');
        }

        $payload=[
            'secret'=>$this->secret(),
            'response'=>$token,
            'sitekey'=>$this->siteKey(),
        ];

        $remoteIp=trim($remoteIp);
        if($remoteIp!=='' && filter_var($remoteIp,FILTER_VALIDATE_IP)){
            $payload['remoteip']=$remoteIp;
        }

        $ch=curl_init(self::VERIFY_URL);
        if($ch===false){
            throw new RuntimeException('HCAPTCHA_CONNECTION_FAILED');
        }

        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>http_build_query($payload,'','&',PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_TIMEOUT=>10,
            CURLOPT_HTTPHEADER=>[
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_USERAGENT=>'OMEGA-CMS/HCaptcha',
        ]);

        $body=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $curlError=curl_errno($ch)!==0;
        curl_close($ch);

        if($curlError || !is_string($body) || $body==='' || $status<200 || $status>=300){
            throw new RuntimeException('HCAPTCHA_SERVICE_UNAVAILABLE');
        }

        $json=json_decode($body,true);
        if(!is_array($json)){
            throw new RuntimeException('HCAPTCHA_INVALID_RESPONSE');
        }

        return ($json['success']??false)===true;
    }

    public function diagnostic(): array
    {
        $curl=function_exists('curl_init');
        return [
            'enabled'=>$this->enabled(),
            'configured'=>$this->configured(),
            'sitekey_present'=>$this->siteKey()!=='',
            'secret_present'=>$this->secret()!=='',
            'curl_available'=>$curl,
            'login'=>$this->protects('login'),
            'register'=>$this->protects('register'),
            'recovery'=>$this->protects('recovery'),
            'ready'=>$this->enabled() && $this->configured() && $curl,
        ];
    }

    public function friendlyError(string $code,string $context='login'): string
    {
        $action=$context==='register'?'crear la cuenta':($context==='recovery'?'continuar con la recuperación':'iniciar sesión');

        return match($code){
            'HCAPTCHA_NOT_CONFIGURED'=>'hCaptcha está activado pero faltan HCAPTCHA_SITEKEY o HCAPTCHA_SECRET en el archivo .env.',
            'HCAPTCHA_REQUIRED'=>'Completa la verificación hCaptcha antes de '.$action.'.',
            'HCAPTCHA_CURL_REQUIRED'=>'El servidor necesita la extensión PHP cURL para verificar hCaptcha.',
            'HCAPTCHA_CONNECTION_FAILED',
            'HCAPTCHA_SERVICE_UNAVAILABLE',
            'HCAPTCHA_INVALID_RESPONSE'=>'No fue posible validar hCaptcha en este momento. Inténtalo nuevamente.',
            default=>'La verificación hCaptcha no fue válida. Completa un nuevo desafío e inténtalo otra vez.',
        };
    }
}
