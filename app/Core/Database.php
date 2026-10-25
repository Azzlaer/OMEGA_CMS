<?php
declare(strict_types=1);
namespace Omega\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $pdo = null;
    private ?string $activeDriver = null;

    public function __construct(private readonly array $config) {}

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) return $this->pdo;

        $configured = strtolower((string)($this->config['driver'] ?? 'auto'));
        $host = (string)($this->config['host'] ?? '127.0.0.1');
        $port = (int)($this->config['port'] ?? 1433);
        $db = (string)($this->config['database'] ?? '');
        $user = (string)($this->config['username'] ?? '');
        $pass = (string)($this->config['password'] ?? '');
        $trust = !empty($this->config['trust_cert']);
        $encrypt = !empty($this->config['encrypt']);

        $candidates = [];
        if (in_array($configured, ['sqlsrv','dblib'], true)) {
            $candidates[] = $configured;
        } else {
            // Windows/XAMPP: Microsoft PDO_SQLSRV is the natural first choice.
            // Linux/cPanel: FreeTDS/PDO_DBLIB remains the preferred first choice.
            $windows = PHP_OS_FAMILY === 'Windows';
            $preferred = $windows ? ['sqlsrv','dblib'] : ['dblib','sqlsrv'];
            foreach ($preferred as $candidate) {
                if (extension_loaded('pdo_'.$candidate)) $candidates[] = $candidate;
            }
        }
        if (!$candidates) throw new RuntimeException('No existe un driver PDO compatible con SQL Server.');

        $errors = [];
        foreach ($candidates as $driver) {
            try {
                $this->pdo = $this->connectWithDriver($driver, $host, $port, $db, $user, $pass, $trust, $encrypt);
                $this->activeDriver = $driver;
                return $this->pdo;
            } catch (PDOException|RuntimeException $e) {
                $errors[$driver] = $e->getMessage();
            }
        }

        $parts = [];
        foreach ($errors as $driver => $message) {
            $parts[] = strtoupper($driver) . ': ' . $message;
        }
        throw new RuntimeException('No fue posible conectar con SQL Server. ' . implode(' | ', $parts));
    }

    private function connectWithDriver(string $driver, string $host, int $port, string $db, string $user, string $pass, bool $trust, bool $encrypt): PDO
    {
        if ($driver === 'sqlsrv') {
            if (!extension_loaded('pdo_sqlsrv')) throw new RuntimeException('pdo_sqlsrv no está disponible.');
            $dsn = "sqlsrv:Server={$host},{$port};Database={$db};"
                 . "TrustServerCertificate=" . ($trust ? '1' : '0') . ";"
                 . "Encrypt=" . ($encrypt ? '1' : '0') . ";"
                 . "LoginTimeout=8";
        } elseif ($driver === 'dblib') {
            if (!extension_loaded('pdo_dblib')) throw new RuntimeException('pdo_dblib no está disponible.');
            $dsn = "dblib:host={$host}:{$port};dbname={$db};charset=UTF-8";
        } else {
            throw new RuntimeException('Driver SQL no soportado: ' . $driver);
        }

        if ($driver === 'sqlsrv') {
            // Keep PDO_SQLSRV constructor options minimal.
            // Login timeout belongs to the SQLSRV DSN (LoginTimeout=8).
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
        } else {
            // PDO_DBLIB supports the generic timeout option used by the Linux build.
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 8,
            ];
        }

        return new PDO($dsn, $user, $pass, $options);
    }

    public function activeDriver(): ?string { return $this->activeDriver; }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $st = $this->pdo()->prepare($sql); $st->execute($params);
        $row = $st->fetch(); return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $st = $this->pdo()->prepare($sql); $st->execute($params); return $st->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $st = $this->pdo()->prepare($sql); $st->execute($params); return $st->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try { $result = $callback($this); $pdo->commit(); return $result; }
        catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
