<?php
declare(strict_types=1);
namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class AdminAccess
{
    private bool $schemaReady = false;

    public function __construct(
        private readonly Database $cmsDb,
        private readonly Database $muDb
    ) {}

    public function ensureSchema(): void
    {
        if ($this->schemaReady) return;

        // Safe, additive migration for already-installed v0.4 sites.
        $this->cmsDb->execute("
            IF COL_LENGTH('dbo.cms_admin_users','account_id') IS NULL
                ALTER TABLE dbo.cms_admin_users ADD account_id VARCHAR(10) NULL
        ");

        // Link legacy installer admins by matching their old username with MEMB_INFO.
        $this->cmsDb->execute("
            UPDATE a
               SET a.account_id = a.username
              FROM dbo.cms_admin_users a
             WHERE a.account_id IS NULL
               AND LEN(a.username) <= 10
               AND EXISTS (
                    SELECT 1 FROM dbo.MEMB_INFO m
                     WHERE m.memb___id = a.username
               )
        ");

        $this->schemaReady = true;
    }

    public function isAdmin(?string $accountId): bool
    {
        if (!$accountId) return false;
        $this->ensureSchema();

        $row = $this->cmsDb->fetchOne(
            "SELECT TOP 1 id,role,is_active
               FROM dbo.cms_admin_users
              WHERE account_id=:id AND is_active=1",
            ['id'=>$accountId]
        );
        return $row !== null;
    }

    public function adminRecord(string $accountId): ?array
    {
        $this->ensureSchema();
        return $this->cmsDb->fetchOne(
            "SELECT TOP 1 id,account_id,username,email,role,is_active,last_login_at,created_at
               FROM dbo.cms_admin_users
              WHERE account_id=:id AND is_active=1",
            ['id'=>$accountId]
        );
    }

    public function requireAdmin(?string $accountId): array
    {
        if (!$accountId) throw new RuntimeException('ADMIN_AUTH_REQUIRED');
        $row = $this->adminRecord($accountId);
        if (!$row) throw new RuntimeException('ADMIN_FORBIDDEN');
        return $row;
    }

    public function touchLogin(string $accountId): void
    {
        $this->ensureSchema();
        $this->cmsDb->execute(
            "UPDATE dbo.cms_admin_users
                SET last_login_at=SYSUTCDATETIME(),updated_at=SYSUTCDATETIME()
              WHERE account_id=:id AND is_active=1",
            ['id'=>$accountId]
        );
    }


    public function isSuperAdmin(?string $accountId): bool
    {
        if (!$accountId) return false;
        $this->ensureSchema();

        $row = $this->cmsDb->fetchOne(
            "SELECT TOP 1 role
               FROM dbo.cms_admin_users
              WHERE account_id=:id AND is_active=1",
            ['id'=>$accountId]
        );

        return strtolower((string)($row['role'] ?? '')) === 'superadmin';
    }

    public function whitelist(): array
    {
        $this->ensureSchema();

        return $this->cmsDb->fetchAll(
            "SELECT a.id,a.account_id,a.username,a.email,a.role,a.is_active,
                    a.last_login_at,a.created_at,a.updated_at,
                    CASE WHEN m.memb___id IS NULL THEN 0 ELSE 1 END AS game_account_exists
               FROM dbo.cms_admin_users a
               LEFT JOIN dbo.MEMB_INFO m ON m.memb___id=a.account_id
              ORDER BY
                    CASE WHEN LOWER(a.role)='superadmin' THEN 0 ELSE 1 END,
                    a.is_active DESC,
                    a.account_id"
        );
    }

    public function addToWhitelist(string $actorAccountId, string $accountId, string $role='admin'): void
    {
        $this->requireSuperAdmin($actorAccountId);
        $this->ensureSchema();

        $accountId=trim($accountId);
        if ($accountId==='' || strlen($accountId)>10 || !preg_match('/^[A-Za-z0-9]+$/',$accountId)) {
            throw new RuntimeException('ACCOUNT_ID_INVALID');
        }

        $role=strtolower(trim($role));
        if (!in_array($role,['admin','superadmin'],true)) {
            throw new RuntimeException('ADMIN_ROLE_INVALID');
        }

        $game=$this->muDb->fetchOne(
            "SELECT TOP 1 memb___id,mail_addr
               FROM dbo.MEMB_INFO
              WHERE memb___id=:id",
            ['id'=>$accountId]
        );

        if (!$game) {
            throw new RuntimeException('MU_ACCOUNT_NOT_FOUND');
        }

        $existing=$this->cmsDb->fetchOne(
            "SELECT TOP 1 id
               FROM dbo.cms_admin_users
              WHERE account_id=:id OR username=:id",
            ['id'=>$accountId]
        );

        if ($existing) {
            $this->cmsDb->execute(
                "UPDATE dbo.cms_admin_users
                    SET account_id=:id,username=:id,role=:role,is_active=1,
                        updated_at=SYSUTCDATETIME()
                  WHERE id=:rowid",
                ['id'=>$accountId,'role'=>$role,'rowid'=>(int)$existing['id']]
            );

            $this->audit($actorAccountId,'admin_whitelist_reactivate',$accountId,[
                'role'=>$role
            ]);
            return;
        }

        $email=trim((string)($game['mail_addr'] ?? ''));
        if ($email==='' || $this->cmsDb->fetchOne(
            "SELECT TOP 1 id FROM dbo.cms_admin_users WHERE email=:email",
            ['email'=>$email]
        )) {
            $email=strtolower($accountId).'@omega-admin.local';
        }

        $hash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);

        $this->cmsDb->execute(
            "INSERT INTO dbo.cms_admin_users
                (username,account_id,email,password_hash,role,is_active)
             VALUES(:username,:account_id,:email,:password_hash,:role,1)",
            [
                'username'=>$accountId,
                'account_id'=>$accountId,
                'email'=>$email,
                'password_hash'=>$hash,
                'role'=>$role
            ]
        );

        $this->audit($actorAccountId,'admin_whitelist_add',$accountId,[
            'role'=>$role
        ]);
    }

    public function updateWhitelistEntry(
        string $actorAccountId,
        string $targetAccountId,
        string $role,
        bool $active
    ): void {
        $this->requireSuperAdmin($actorAccountId);
        $this->ensureSchema();

        $targetAccountId=trim($targetAccountId);
        $role=strtolower(trim($role));

        if (!in_array($role,['admin','superadmin'],true)) {
            throw new RuntimeException('ADMIN_ROLE_INVALID');
        }

        $target=$this->cmsDb->fetchOne(
            "SELECT TOP 1 id,account_id,role,is_active
               FROM dbo.cms_admin_users
              WHERE account_id=:id",
            ['id'=>$targetAccountId]
        );

        if (!$target) {
            throw new RuntimeException('ADMIN_NOT_FOUND');
        }

        if (strcasecmp($actorAccountId,$targetAccountId)===0) {
            if (!$active || $role!=='superadmin') {
                throw new RuntimeException('CANNOT_REMOVE_OWN_SUPERADMIN_ACCESS');
            }
        }

        $targetIsActiveSuper=
            ((int)$target['is_active']===1)
            && strtolower((string)$target['role'])==='superadmin';

        if ($targetIsActiveSuper && (!$active || $role!=='superadmin')) {
            $count=$this->cmsDb->fetchOne(
                "SELECT COUNT(*) AS total
                   FROM dbo.cms_admin_users
                  WHERE is_active=1 AND LOWER(role)='superadmin'"
            );
            if ((int)($count['total'] ?? 0) <= 1) {
                throw new RuntimeException('LAST_SUPERADMIN_PROTECTED');
            }
        }

        $this->cmsDb->execute(
            "UPDATE dbo.cms_admin_users
                SET role=:role,is_active=:active,updated_at=SYSUTCDATETIME()
              WHERE account_id=:id",
            [
                'role'=>$role,
                'active'=>$active ? 1 : 0,
                'id'=>$targetAccountId
            ]
        );

        $this->audit($actorAccountId,'admin_whitelist_update',$targetAccountId,[
            'role'=>$role,
            'active'=>$active
        ]);
    }

    public function removeFromWhitelist(string $actorAccountId,string $targetAccountId): void
    {
        $this->requireSuperAdmin($actorAccountId);
        $this->ensureSchema();

        $targetAccountId=trim($targetAccountId);

        if (strcasecmp($actorAccountId,$targetAccountId)===0) {
            throw new RuntimeException('CANNOT_DELETE_SELF');
        }

        $target=$this->cmsDb->fetchOne(
            "SELECT TOP 1 id,role,is_active
               FROM dbo.cms_admin_users
              WHERE account_id=:id",
            ['id'=>$targetAccountId]
        );

        if (!$target) {
            throw new RuntimeException('ADMIN_NOT_FOUND');
        }

        if ((int)$target['is_active']===1 && strtolower((string)$target['role'])==='superadmin') {
            $count=$this->cmsDb->fetchOne(
                "SELECT COUNT(*) AS total
                   FROM dbo.cms_admin_users
                  WHERE is_active=1 AND LOWER(role)='superadmin'"
            );
            if ((int)($count['total'] ?? 0) <= 1) {
                throw new RuntimeException('LAST_SUPERADMIN_PROTECTED');
            }
        }

        $this->cmsDb->execute(
            "DELETE FROM dbo.cms_admin_users WHERE account_id=:id",
            ['id'=>$targetAccountId]
        );

        $this->audit($actorAccountId,'admin_whitelist_remove',$targetAccountId,[]);
    }

    public function requireSuperAdmin(?string $accountId): array
    {
        $row=$this->requireAdmin($accountId);

        if (strtolower((string)($row['role'] ?? ''))!=='superadmin') {
            throw new RuntimeException('SUPERADMIN_REQUIRED');
        }

        return $row;
    }

    private function audit(string $actor,string $action,string $target,array $context): void
    {
        try {
            $this->cmsDb->execute(
                "INSERT INTO dbo.cms_audit_log(actor,action,target,ip,context)
                 VALUES(:actor,:action,:target,:ip,:context)",
                [
                    'actor'=>$actor,
                    'action'=>$action,
                    'target'=>$target,
                    'ip'=>(string)($_SERVER['REMOTE_ADDR'] ?? ''),
                    'context'=>json_encode(
                        $context,
                        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
                    )
                ]
            );
        } catch (\Throwable) {
            // El control de acceso no debe romperse porque falle el log.
        }
    }


    public function linkStatus(): array
    {
        $this->ensureSchema();
        $rows = $this->cmsDb->fetchAll(
            "SELECT a.id,a.username,a.account_id,a.email,a.role,a.is_active,
                    CASE WHEN m.memb___id IS NULL THEN 0 ELSE 1 END AS game_account_exists
               FROM dbo.cms_admin_users a
               LEFT JOIN dbo.MEMB_INFO m ON m.memb___id = COALESCE(a.account_id,a.username)
              ORDER BY a.id"
        );
        return $rows;
    }
}
