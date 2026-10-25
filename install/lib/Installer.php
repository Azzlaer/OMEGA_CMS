<?php
declare(strict_types=1);

namespace Omega\Install;

use PDO;
use PDOException;
use RuntimeException;

final class Installer
{
    private ?string $lastDriver = null;
    private ?string $lastConnectionMode = null;

    public function __construct(private readonly string $root) {}

    public function requirements(): array
    {
        $storage = $this->root . '/storage';
        $env = $this->root . '/.env';
        return [
            'PHP 8.2 o superior' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'Extensión PDO' => extension_loaded('pdo'),
            'Driver SQL Server PDO (PDO_DBLIB o PDO_SQLSRV)' =>
                extension_loaded('pdo_dblib') || extension_loaded('pdo_sqlsrv'),
            'OpenSSL' => extension_loaded('openssl'),
            'JSON' => extension_loaded('json'),
            'Sessions' => extension_loaded('session'),
            'Directorio storage escribible' => is_dir($storage) && is_writable($storage),
            '.env creable/escribible' => (!file_exists($env) && is_writable($this->root)) || (is_file($env) && is_writable($env)),
        ];
    }

    public function ready(): bool
    {
        return !in_array(false, $this->requirements(), true);
    }

    public function connect(array $sql, ?string $database = null): PDO
    {
        $host = trim((string)($sql['host'] ?? '127.0.0.1'));
        $port = max(1, (int)($sql['port'] ?? 1433));
        $user = trim((string)($sql['username'] ?? ''));
        $pass = (string)($sql['password'] ?? '');
        $configured = strtolower(trim((string)($sql['driver'] ?? 'auto')));
        $trust = !empty($sql['trust_cert']);
        $encrypt = !empty($sql['encrypt']);

        if ($database === null || trim($database) === '') {
            throw new RuntimeException('Debes indicar una base de datos.');
        }
        $database = trim($database);

        $available = [
            'dblib' => extension_loaded('pdo_dblib'),
            'sqlsrv' => extension_loaded('pdo_sqlsrv'),
        ];

        if (!$available['dblib'] && !$available['sqlsrv']) {
            throw new RuntimeException(
                'No hay ningún driver PDO para SQL Server. ' .
                'Linux/cPanel: instala PDO_DBLIB/FreeTDS. ' .
                'Windows/XAMPP: instala PDO_SQLSRV + Microsoft ODBC Driver.'
            );
        }

        if (in_array($configured, ['dblib','sqlsrv'], true)) {
            if (!$available[$configured]) {
                throw new RuntimeException(
                    strtoupper($configured).' fue seleccionado pero la extensión correspondiente no está cargada.'
                );
            }
            $candidates = [$configured];
        } else {
            // Auto: Windows prefers PDO_SQLSRV; Linux prefers PDO_DBLIB.
            $preferred = PHP_OS_FAMILY === 'Windows'
                ? ['sqlsrv','dblib']
                : ['dblib','sqlsrv'];
            $candidates = array_values(array_filter(
                $preferred,
                static fn(string $driver): bool => $available[$driver]
            ));
        }

        $errors = [];

        foreach ($candidates as $driver) {
            if ($driver === 'sqlsrv') {
                $dsn = "sqlsrv:Server={$host},{$port};Database={$database};"
                     . "TrustServerCertificate=" . ($trust ? '1' : '0') . ";"
                     . "Encrypt=" . ($encrypt ? '1' : '0') . ";"
                     . "LoginTimeout=8";

                for ($try = 1; $try <= 2; $try++) {
                    try {
                        $pdo = new PDO($dsn, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        ]);

                        $this->probeConnection($pdo, $database);
                        $this->lastDriver = 'sqlsrv';
                        $this->lastConnectionMode = $encrypt ? 'sqlsrv-encrypted' : 'sqlsrv-standard';
                        return $pdo;
                    } catch (PDOException|RuntimeException $e) {
                        $errors[] = "PDO_SQLSRV intento {$try}: " . trim($e->getMessage());
                        if ($try === 1) usleep(250000);
                    }
                }
            }

            if ($driver === 'dblib') {
                $attempts = [
                    'dblib-basic' => "dblib:host={$host}:{$port};dbname={$database}",
                    'dblib-utf8'  => "dblib:host={$host}:{$port};dbname={$database};charset=UTF-8",
                ];

                foreach ($attempts as $mode => $dsn) {
                    for ($try = 1; $try <= 2; $try++) {
                        try {
                            $pdo = new PDO($dsn, $user, $pass, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                PDO::ATTR_TIMEOUT => 8,
                                PDO::ATTR_PERSISTENT => false,
                            ]);

                            $this->probeConnection($pdo, $database);
                            $this->lastDriver = 'dblib';
                            $this->lastConnectionMode = $mode;
                            return $pdo;
                        } catch (PDOException|RuntimeException $e) {
                            $errors[] = strtoupper($mode)." intento {$try}: ".trim($e->getMessage());
                            if ($try === 1) usleep(250000);
                        }
                    }
                }
            }
        }

        $target = $host . ':' . $port . ' / ' . $database;
        throw new RuntimeException(
            'No fue posible conectar con SQL Server. Destino: ' . $target . '. ' .
            'Drivers probados: ' . implode(', ', array_map('strtoupper', $candidates)) . '. ' .
            'Detalle: ' . implode(' | ', $errors)
        );
    }

    private function probeConnection(PDO $pdo, string $database): void
    {
        $probe = $pdo->query("
            SELECT
                CAST(SERVERPROPERTY('ProductVersion') AS VARCHAR(128)) AS ProductVersion,
                DB_NAME() AS CurrentDatabase,
                SUSER_SNAME() AS LoginName
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$probe || strcasecmp((string)($probe['CurrentDatabase'] ?? ''), $database) !== 0) {
            throw new RuntimeException(
                'La conexión abrió, pero SQL Server no confirmó la base solicitada.'
            );
        }
    }

    public function lastConnectionMode(): ?string
    {
        return $this->lastConnectionMode;
    }

    public function lastDriver(): ?string
    {
        return $this->lastDriver;
    }

    public function driverName(): string
    {
        $drivers = [];
        if (extension_loaded('pdo_dblib')) $drivers[] = 'PDO_DBLIB / FreeTDS';
        if (extension_loaded('pdo_sqlsrv')) $drivers[] = 'PDO_SQLSRV / Microsoft';
        return $drivers ? implode(' + ', $drivers) : 'No disponible';
    }

    public function driverStatus(): array
    {
        $sqlsrv = extension_loaded('pdo_sqlsrv');
        $dblib = extension_loaded('pdo_dblib');

        $recommended = null;
        if (PHP_OS_FAMILY === 'Windows') {
            $recommended = $sqlsrv ? 'sqlsrv' : ($dblib ? 'dblib' : null);
        } else {
            $recommended = $dblib ? 'dblib' : ($sqlsrv ? 'sqlsrv' : null);
        }

        return [
            'pdo_sqlsrv' => $sqlsrv,
            'pdo_dblib' => $dblib,
            'fallback' => $sqlsrv && $dblib,
            'recommended' => $recommended,
            'platform' => PHP_OS_FAMILY,
        ];
    }

    public function databaseExists(PDO $pdo, string $name): bool
    {
        $st = $pdo->prepare('SELECT DB_ID(:name) AS dbid');
        $st->execute(['name' => $name]);
        return $st->fetchColumn() !== null;
    }

    public function validateMuSchema(PDO $pdo): array
    {
        $required = [
            'AccountCharacter' => ['Id','GameID1','GameID2','GameID3','GameID4','GameID5'],
            'Character' => ['AccountID','Name','ResetCount','GrandResetCount','cLevel','LevelUpPoint','Class','Strength','Dexterity','Vitality','Energy','Inventory','MapNumber','PkLevel'],
            'MEMB_INFO' => ['memb___id','memb__pwd','memb_name','mail_addr','AccountLevel','AccountExpireDate','bloc_code'],
            'MEMB_STAT' => ['memb___id','ConnectStat','ServerName','IP','ConnectTM','DisConnectTM','OnlineHours'],
            'Guild' => ['G_Name','G_Mark','G_Score','G_Master'],
            'GuildMember' => ['Name','G_Name','G_Status'],
            'warehouse' => ['AccountID','Items','Money','pw'],
            'ResetInfo' => ['Name','ResetDay','ResetWek','ResetMon','GrandResetDay','GrandResetWek','GrandResetMon'],
            'RankingBloodCastle' => ['Name','Score'],
            'RankingDevilSquare' => ['Name','Score'],
        ];
        $results = [];
        foreach ($required as $table => $columns) {
            $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=:t");
            $st->execute(['t' => $table]);
            $found = array_map(static fn(array $r) => strtolower((string)$r['COLUMN_NAME']), $st->fetchAll());
            $missing = [];
            foreach ($columns as $column) {
                if (!in_array(strtolower($column), $found, true)) $missing[] = $column;
            }
            $results[$table] = ['ok' => count($missing) === 0 && count($found) > 0, 'missing' => $missing];
        }
        return $results;
    }

    public function schemaIsCompatible(array $results): bool
    {
        foreach ($results as $result) if (empty($result['ok'])) return false;
        return true;
    }

    public function createCmsDatabase(PDO $serverPdo, string $database): void
    {
        $this->assertIdentifier($database);
        if (!$this->databaseExists($serverPdo, $database)) {
            $serverPdo->exec('CREATE DATABASE [' . $database . ']');
        }
    }

    public function installCmsSchema(PDO $pdo, bool $rebuild = false): void
    {
        if ($rebuild) {
            foreach (['cms_guides','cms_login_attempts','cms_sessions','cms_audit_log','cms_news','cms_admin_users','cms_settings'] as $table) {
                $pdo->exec("IF OBJECT_ID('dbo.{$table}','U') IS NOT NULL DROP TABLE dbo.{$table}");
            }
        }
        $sql = <<<'SQL'
IF OBJECT_ID('dbo.cms_settings','U') IS NULL
CREATE TABLE dbo.cms_settings(
 setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
 setting_value NVARCHAR(MAX) NULL,
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);

IF OBJECT_ID('dbo.cms_admin_users','U') IS NULL
CREATE TABLE dbo.cms_admin_users(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 username VARCHAR(50) NOT NULL UNIQUE,
 account_id VARCHAR(10) NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role VARCHAR(40) NOT NULL DEFAULT 'superadmin',
 is_active BIT NOT NULL DEFAULT 1,
 last_login_at DATETIME2 NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);

IF OBJECT_ID('dbo.cms_news','U') IS NULL
CREATE TABLE dbo.cms_news(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 title NVARCHAR(180) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 summary NVARCHAR(500) NULL,
 content NVARCHAR(MAX) NOT NULL,
 image NVARCHAR(255) NULL,
 category NVARCHAR(80) NULL,
 author_id BIGINT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 featured BIT NOT NULL DEFAULT 0,
 views BIGINT NOT NULL DEFAULT 0,
 published_at DATETIME2 NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);


IF OBJECT_ID('dbo.cms_guides','U') IS NULL
CREATE TABLE dbo.cms_guides(
 id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 title NVARCHAR(180) NOT NULL,
 slug VARCHAR(160) NOT NULL,
 summary NVARCHAR(700) NULL,
 content_html NVARCHAR(MAX) NOT NULL,
 category NVARCHAR(60) NULL,
 author VARCHAR(64) NOT NULL,
 logo_url NVARCHAR(700) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 featured BIT NOT NULL DEFAULT 0,
 views INT NOT NULL DEFAULT 0,
 published_at DATETIME2 NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='UX_cms_guides_slug' AND object_id=OBJECT_ID('dbo.cms_guides'))
CREATE UNIQUE INDEX UX_cms_guides_slug ON dbo.cms_guides(slug);
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_cms_guides_status_date' AND object_id=OBJECT_ID('dbo.cms_guides'))
CREATE INDEX IX_cms_guides_status_date ON dbo.cms_guides(status,published_at,created_at);

IF OBJECT_ID('dbo.cms_audit_log','U') IS NULL
CREATE TABLE dbo.cms_audit_log(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 actor NVARCHAR(100) NULL,
 action VARCHAR(100) NOT NULL,
 target NVARCHAR(190) NULL,
 ip VARCHAR(45) NULL,
 context NVARCHAR(MAX) NULL,
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);

IF OBJECT_ID('dbo.cms_sessions','U') IS NULL
CREATE TABLE dbo.cms_sessions(
 id VARCHAR(128) NOT NULL PRIMARY KEY,
 admin_user_id BIGINT NULL,
 ip_hash CHAR(64) NULL,
 user_agent_hash CHAR(64) NULL,
 last_activity_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
 created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);

IF OBJECT_ID('dbo.cms_login_attempts','U') IS NULL
CREATE TABLE dbo.cms_login_attempts(
 id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
 username VARCHAR(50) NULL,
 ip_hash CHAR(64) NOT NULL,
 successful BIT NOT NULL DEFAULT 0,
 attempted_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
SQL;
        $pdo->exec($sql);
        // Additive migration for older installations.
        $pdo->exec("IF COL_LENGTH('dbo.cms_admin_users','account_id') IS NULL ALTER TABLE dbo.cms_admin_users ADD account_id VARCHAR(10) NULL");
    }

    public function createAdmin(PDO $cmsPdo, PDO $muPdo, array $admin): void
    {
        $username = trim((string)($admin['username'] ?? ''));
        $email = trim((string)($admin['email'] ?? ''));
        $password = (string)($admin['password'] ?? '');

        // The administrator ID IS a real MU account ID.
        if (!preg_match('/^[A-Za-z0-9]{4,10}$/', $username)) {
            throw new RuntimeException('El ID administrador debe tener 4-10 letras/números porque será también una cuenta MEMB_INFO.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 50) {
            throw new RuntimeException('El email del administrador no es válido.');
        }
        if (strlen($password) < 6 || strlen($password) > 10) {
            throw new RuntimeException('La contraseña debe tener 6-10 caracteres para ser compatible con MuEmu 0.97k.');
        }

        // Create or verify the game account.
        $st = $muPdo->prepare("SELECT memb___id,mail_addr FROM dbo.MEMB_INFO WHERE memb___id=:id");
        $st->execute(['id'=>$username]);
        $game = $st->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            $muPdo->beginTransaction();
            try {
                $q = $muPdo->prepare("
                    INSERT INTO dbo.MEMB_INFO
                        (memb___id,memb__pwd,memb_name,mail_addr,sno__numb,AccountLevel,AccountExpireDate,bloc_code,Bloc_Expire)
                    VALUES
                        (:id,:pwd,:name,:email,:sno,0,GETDATE(),'0',GETDATE())
                ");
                $q->execute([
                    'id'=>$username,
                    'pwd'=>$password,
                    'name'=>$username,
                    'email'=>$email,
                    'sno'=>'000000000000000000',
                ]);

                $q = $muPdo->prepare("
                    IF NOT EXISTS(SELECT 1 FROM dbo.AccountCharacter WHERE Id=:id)
                    INSERT INTO dbo.AccountCharacter(Id,GameID1,GameID2,GameID3,GameID4,GameID5,GameIDC)
                    VALUES(:id2,NULL,NULL,NULL,NULL,NULL,NULL)
                ");
                $q->execute(['id'=>$username,'id2'=>$username]);

                $q = $muPdo->prepare("
                    IF NOT EXISTS(SELECT 1 FROM dbo.MEMB_STAT WHERE memb___id=:id)
                    INSERT INTO dbo.MEMB_STAT(memb___id,ConnectStat,OnlineHours)
                    VALUES(:id2,0,0)
                ");
                $q->execute(['id'=>$username,'id2'=>$username]);

                $muPdo->commit();
            } catch (\Throwable $e) {
                if ($muPdo->inTransaction()) $muPdo->rollBack();
                throw $e;
            }
        }

        // Keep password_hash for backward schema compatibility only.
        // Admin authorization after installation is based on MEMB_INFO session + account_id.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $st = $cmsPdo->prepare("
            SELECT id FROM dbo.cms_admin_users
             WHERE account_id=:a OR username=:u OR email=:e
        ");
        $st->execute(['a'=>$username,'u'=>$username,'e'=>$email]);
        $existing = $st->fetchColumn();

        if ($existing) {
            $up = $cmsPdo->prepare("
                UPDATE dbo.cms_admin_users
                   SET username=:u,account_id=:a,email=:e,password_hash=:p,
                       role='superadmin',is_active=1,updated_at=SYSUTCDATETIME()
                 WHERE id=:id
            ");
            $up->execute([
                'u'=>$username,'a'=>$username,'e'=>$email,'p'=>$hash,'id'=>$existing
            ]);
        } else {
            $in = $cmsPdo->prepare("
                INSERT INTO dbo.cms_admin_users
                    (username,account_id,email,password_hash,role,is_active)
                VALUES(:u,:a,:e,:p,'superadmin',1)
            ");
            $in->execute([
                'u'=>$username,'a'=>$username,'e'=>$email,'p'=>$hash
            ]);
        }
    }

    public function saveSettings(PDO $pdo, array $settings): void
    {
        $sql = "MERGE dbo.cms_settings AS target USING (SELECT :k AS setting_key, :v AS setting_value) AS source ON target.setting_key=source.setting_key WHEN MATCHED THEN UPDATE SET setting_value=source.setting_value,updated_at=SYSUTCDATETIME() WHEN NOT MATCHED THEN INSERT(setting_key,setting_value) VALUES(source.setting_key,source.setting_value);";
        $st = $pdo->prepare($sql);
        foreach ($settings as $key=>$value) {
            $st->execute(['k'=>(string)$key,'v'=>(string)$value]);
        }
    }

    public function writeEnv(array $data): void
    {
        $lines = [
            'APP_NAME=' . $this->quoteEnv($data['app_name']),
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_URL=' . $this->quoteEnv($data['app_url']),
            'APP_TIMEZONE=' . $this->quoteEnv($data['timezone'] ?? 'America/Santiago'),
            'APP_KEY=' . $this->quoteEnv('base64:' . base64_encode(random_bytes(32))),
            '',
            'MU_DB_DRIVER=' . $this->envDriver($data['sql']),
            'MU_DB_HOST=' . $this->quoteEnv($data['sql']['host']),
            'MU_DB_PORT=' . (int)$data['sql']['port'],
            'MU_DB_DATABASE=' . $this->quoteEnv($data['mu_database']),
            'MU_DB_USERNAME=' . $this->quoteEnv($data['sql']['username']),
            'MU_DB_PASSWORD=' . $this->quoteEnv($data['sql']['password']),
            'MU_DB_TRUST_CERT=' . (!empty($data['sql']['trust_cert']) ? 'true' : 'false'),
            'MU_DB_ENCRYPT=' . (!empty($data['sql']['encrypt']) ? 'true' : 'false'),
            '',
            'CMS_DB_DRIVER=' . $this->envDriver($data['sql']),
            'CMS_DB_HOST=' . $this->quoteEnv($data['sql']['host']),
            'CMS_DB_PORT=' . (int)$data['sql']['port'],
            'CMS_DB_DATABASE=' . $this->quoteEnv($data['cms_database']),
            'CMS_DB_USERNAME=' . $this->quoteEnv($data['sql']['username']),
            'CMS_DB_PASSWORD=' . $this->quoteEnv($data['sql']['password']),
            'CMS_DB_TRUST_CERT=' . (!empty($data['sql']['trust_cert']) ? 'true' : 'false'),
            'CMS_DB_ENCRYPT=' . (!empty($data['sql']['encrypt']) ? 'true' : 'false'),
            '',
        ];
        $content = implode(PHP_EOL, $lines);
        if (file_put_contents($this->root.'/.env', $content, LOCK_EX) === false) throw new RuntimeException('No se pudo escribir el archivo .env.');
    }

    public function writeLock(array $metadata): void
    {
        $payload = json_encode($metadata + ['installed_at'=>gmdate('c')], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if (file_put_contents($this->root.'/storage/installed.lock', $payload, LOCK_EX) === false) throw new RuntimeException('No se pudo bloquear el instalador.');
    }

    public function isInstalled(): bool
    {
        return is_file($this->root.'/storage/installed.lock');
    }


    private function envDriver(array $sql): string
    {
        $driver = strtolower((string)($sql['driver'] ?? $this->lastDriver ?? 'auto'));
        return in_array($driver, ['sqlsrv','dblib'], true) ? $driver : 'auto';
    }

    private function quoteEnv(mixed $value): string
    {
        $v = str_replace(["\\", '"', "\r", "\n"], ['\\\\','\\"','',''], (string)$value);
        return '"'.$v.'"';
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $identifier)) throw new RuntimeException('Nombre de base de datos no válido.');
    }
}
