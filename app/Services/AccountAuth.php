<?php
declare(strict_types=1);
namespace Omega\Services;

use Omega\Core\Database;
use Omega\Core\Env;
use Omega\Core\RateLimiter;
use RuntimeException;

final class AccountAuth
{
    public function __construct(
        private readonly Database $muDb,
        private readonly Database $cmsDb,
        private readonly RateLimiter $limiter,
        private readonly ?ModuleRuntime $modules = null
    ) {}

    public function currentId(): ?string
    {
        $id = $_SESSION['mu_account_id'] ?? null;
        return is_string($id) && $id !== '' ? $id : null;
    }

    public function check(): bool
    {
        return $this->currentId() !== null;
    }

    public function requireLogin(): string
    {
        $id = $this->currentId();
        if ($id === null) {
            throw new RuntimeException('AUTH_REQUIRED');
        }
        return $id;
    }

    public function login(string $username, string $password, string $ip): array
    {
        $username = trim($username);
        $this->validateUsername($username);

        $rateKey = 'login|' . strtolower($username) . '|' . $ip;
        if (!$this->limiter->hit($rateKey, 6, 300)) {
            throw new RuntimeException('Demasiados intentos. Espera unos minutos antes de volver a intentar.');
        }

        $row = $this->muDb->fetchOne(
            'SELECT memb___id,memb__pwd,memb_name,mail_addr,AccountLevel,AccountExpireDate,bloc_code,Bloc_Expire
             FROM MEMB_INFO WHERE memb___id = :id',
            ['id' => $username]
        );

        $valid = false;
        if ($row) {
            $valid = $this->verifyPassword((string)$row['memb__pwd'], $password);
        }

        $this->recordAttempt($username, $ip, $valid);

        if (!$row || !$valid) {
            usleep(250000);
            throw new RuntimeException('Usuario o contraseña incorrectos.');
        }

        if ((string)($row['bloc_code'] ?? '0') !== '0') {
            throw new RuntimeException('Esta cuenta se encuentra bloqueada.');
        }

        session_regenerate_id(true);
        $_SESSION['mu_account_id'] = (string)$row['memb___id'];
        $_SESSION['mu_login_at'] = time();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));

        return $row;
    }

    public function logout(): void
    {
        unset($_SESSION['mu_account_id'], $_SESSION['mu_login_at']);
        session_regenerate_id(true);
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public function register(array $data, string $ip): string
    {
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $password2 = (string)($data['password_confirmation'] ?? '');
        $email = trim((string)($data['email'] ?? ''));
        $personalId = trim((string)($data['personal_id'] ?? ''));
        $terms = !empty($data['terms']);

        $this->validateUsername($username);
        $this->validateGamePassword($password);

        if (!hash_equals($password, $password2)) {
            throw new RuntimeException('Las contraseñas no coinciden.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
            throw new RuntimeException('El correo electrónico no es válido.');
        }
        if (!preg_match('/^\d{7,18}$/', $personalId)) {
            throw new RuntimeException('El ID personal debe contener entre 7 y 18 números.');
        }
        if (!$terms) {
            throw new RuntimeException('Debes aceptar las reglas del servidor.');
        }
        if (trim((string)($data['website'] ?? '')) !== '') {
            throw new RuntimeException('No fue posible completar el registro.');
        }
        if (!$this->limiter->hit('register|' . $ip, 4, 3600)) {
            throw new RuntimeException('Se alcanzó el límite de registros desde esta conexión. Intenta más tarde.');
        }

        if ($this->muDb->fetchOne(
            'SELECT memb___id FROM MEMB_INFO WHERE memb___id=:id',
            ['id'=>$username]
        )) {
            throw new RuntimeException('Ese nombre de cuenta ya está registrado.');
        }

        if ($this->muDb->fetchOne(
            'SELECT memb___id FROM MEMB_INFO WHERE mail_addr=:email',
            ['email'=>$email]
        )) {
            throw new RuntimeException('Ese correo electrónico ya está registrado.');
        }

        $encodedPassword = $this->encodePassword($password);
        $sno = str_pad(substr($personalId, -18), 18, '0', STR_PAD_LEFT);

        try {
            $this->muDb->transaction(function(Database $db) use($username,$encodedPassword,$email,$sno): void {
                // MEMB_INFO is the only authoritative account identity.
                $db->execute(
                    "INSERT INTO MEMB_INFO
                        (memb___id,memb__pwd,memb_name,mail_addr,sno__numb,AccountLevel,AccountExpireDate,bloc_code,Bloc_Expire)
                     VALUES
                        (:id,:pwd,:name,:email,:sno,0,GETDATE(),'0',GETDATE())",
                    [
                        'id'=>$username,
                        'pwd'=>$encodedPassword,
                        'name'=>$username,
                        'email'=>$email,
                        'sno'=>$sno,
                    ]
                );

                // Some MuEmu databases create these companion rows elsewhere.
                // We create them only when missing, making registration safe on both layouts.
                if (!$db->fetchOne(
                    'SELECT Id FROM AccountCharacter WHERE Id=:id',
                    ['id'=>$username]
                )) {
                    $db->execute(
                        "INSERT INTO AccountCharacter
                            (Id,GameID1,GameID2,GameID3,GameID4,GameID5,GameIDC)
                         VALUES (:id,NULL,NULL,NULL,NULL,NULL,NULL)",
                        ['id'=>$username]
                    );
                }

                if (!$db->fetchOne(
                    'SELECT memb___id FROM MEMB_STAT WHERE memb___id=:id',
                    ['id'=>$username]
                )) {
                    $db->execute(
                        "INSERT INTO MEMB_STAT
                            (memb___id,ConnectStat,ServerName,IP,ConnectTM,DisConnectTM,OnlineHours)
                         VALUES (:id,0,NULL,NULL,NULL,NULL,0)",
                        ['id'=>$username]
                    );
                }
            });
        } catch (\Throwable $e) {
            $this->logRegistrationFailure($username, $e);
            throw new RuntimeException(
                'SQL Server rechazó la creación de la cuenta. Omega guardó el detalle técnico en storage/logs/account-register.log.',
                0,
                $e
            );
        }

        $this->audit(
            'account.register',
            $username,
            $ip,
            ['email_hash'=>hash('sha256', strtolower($email))]
        );

        // Module hooks run only AFTER the SQL transaction has succeeded.
        // A module failure must never roll back or break a valid account registration.
        if ($this->modules) {
            try {
                $this->modules->dispatch('account.registered',[
                    'username'=>$username,
                    'email'=>$email,
                    'ip'=>$ip,
                    'referral_code'=>trim((string)($data['referral_code'] ?? '')),
                    'date'=>date('d/m/Y H:i:s'),
                ]);
            } catch (\Throwable) {}
        }

        return $username;
    }

    private function logRegistrationFailure(string $username, \Throwable $e): void
    {
        try {
            $root = dirname(__DIR__, 2);
            $dir = $root . '/storage/logs';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $line = sprintf(
                "[%s] account=%s error=%s\n",
                gmdate('c'),
                preg_replace('/[^A-Za-z0-9]/', '', $username),
                str_replace(["\r","\n"], ' ', $e->getMessage())
            );
            @file_put_contents($dir.'/account-register.log', $line, FILE_APPEND|LOCK_EX);
        } catch (\Throwable) {}
    }

    public function changePassword(string $accountId, string $current, string $new, string $confirm, string $ip): void
    {
        $this->validateGamePassword($new);
        if (!hash_equals($new, $confirm)) {
            throw new RuntimeException('Las nuevas contraseñas no coinciden.');
        }

        $row = $this->muDb->fetchOne(
            'SELECT memb__pwd FROM MEMB_INFO WHERE memb___id=:id',
            ['id'=>$accountId]
        );
        if (!$row || !$this->verifyPassword((string)$row['memb__pwd'], $current)) {
            throw new RuntimeException('La contraseña actual es incorrecta.');
        }

        $stat = $this->muDb->fetchOne('SELECT ConnectStat FROM MEMB_STAT WHERE memb___id=:id', ['id'=>$accountId]);
        if ((int)($stat['ConnectStat'] ?? 0) === 1) {
            throw new RuntimeException('Debes salir del juego antes de cambiar la contraseña.');
        }

        $this->muDb->execute(
            'UPDATE MEMB_INFO SET memb__pwd=:pwd WHERE memb___id=:id',
            ['pwd'=>$this->encodePassword($new),'id'=>$accountId]
        );

        $this->audit('account.password.change', $accountId, $ip);
    }

    private function validateUsername(string $username): void
    {
        if (!preg_match('/^[A-Za-z0-9]{4,10}$/', $username)) {
            throw new RuntimeException('La cuenta debe tener entre 4 y 10 caracteres y usar solo letras y números.');
        }
    }

    private function validateGamePassword(string $password): void
    {
        $mode = strtolower((string)Env::get('MU_PASSWORD_MODE', 'plain'));
        $max = $mode === 'md5' ? 20 : 10;
        if (strlen($password) < 6 || strlen($password) > $max) {
            throw new RuntimeException("La contraseña debe contener entre 6 y {$max} caracteres.");
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $password)) {
            throw new RuntimeException('La contraseña contiene caracteres no permitidos.');
        }
    }

    private function verifyPassword(string $stored, string $input): bool
    {
        $mode = strtolower((string)Env::get('MU_PASSWORD_MODE', 'plain'));
        if ($mode === 'md5') {
            return hash_equals(strtolower($stored), md5($input));
        }
        return hash_equals($stored, $input);
    }

    private function encodePassword(string $password): string
    {
        $mode = strtolower((string)Env::get('MU_PASSWORD_MODE', 'plain'));
        return $mode === 'md5' ? md5($password) : $password;
    }

    private function recordAttempt(string $username, string $ip, bool $ok): void
    {
        try {
            $key = (string)Env::get('APP_KEY', 'omega');
            $this->cmsDb->execute(
                'INSERT INTO cms_login_attempts (username,ip_hash,successful) VALUES (:u,:ip,:ok)',
                [
                    'u'=>substr($username,0,50),
                    'ip'=>hash_hmac('sha256',$ip,$key),
                    'ok'=>$ok ? 1 : 0,
                ]
            );
        } catch (\Throwable) {}
    }

    private function audit(string $action, string $target, string $ip, array $context=[]): void
    {
        try {
            $this->cmsDb->execute(
                'INSERT INTO cms_audit_log (actor,action,target,ip,context) VALUES (:actor,:action,:target,:ip,:context)',
                [
                    'actor'=>$target,
                    'action'=>$action,
                    'target'=>$target,
                    'ip'=>substr($ip,0,45),
                    'context'=>$context ? json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
                ]
            );
        } catch (\Throwable) {}
    }
}
