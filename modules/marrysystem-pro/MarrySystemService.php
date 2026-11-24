<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaMarrySystemService
{
    public function __construct(private readonly Database $db){}

    public function diagnostics(): array
    {
        $required=[
            'MarriageCouples'=>['MarriageID','Account1','Character1','Account2','Character2','MarriedDate','ApprovedBy','IsActive','DivorcedDate'],
            'MarriageRequests'=>['RequestID','RequesterAccount','RequesterName','TargetAccount','TargetName','RequesterAccepted','TargetAccepted','AdminApproved','ApprovedBy','Status','CreatedDate','ApprovedDate','ExpiresDate','ClosedDate'],
            'MarriageHistory'=>['HistoryID','ActionType','Character1','Character2','AdminName','Reason','Value','ActionDate'],
            'MarriageAudit'=>['AuditID','AccountID','CharacterName','PartnerName','ActionType','Reason','MapNumber','MapX','MapY','ZenBefore','ZenAfter','ActionDate'],
        ];

        $out=[];
        foreach($required as $table=>$columns){
            $exists=$this->tableExists($table);
            $present=[];

            if($exists){
                $rows=$this->db->fetchAll(
                    "SELECT COLUMN_NAME
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=:t",
                    ['t'=>$table]
                );

                foreach($rows as $row){
                    $present[strtolower((string)$row['COLUMN_NAME'])]=true;
                }
            }

            $missing=[];
            foreach($columns as $column){
                if(!isset($present[strtolower($column)])) $missing[]=$column;
            }

            $count=0;
            if($exists){
                $row=$this->db->fetchOne("SELECT COUNT(*) total FROM dbo.[{$table}]");
                $count=(int)($row['total']??0);
            }

            $out[$table]=[
                'exists'=>$exists,
                'missing'=>$missing,
                'count'=>$count,
            ];
        }

        return $out;
    }

    public function ready(): bool
    {
        foreach($this->diagnostics() as $row){
            if(!$row['exists'] || $row['missing']) return false;
        }
        return true;
    }

    public function summary(): array
    {
        $active=(int)($this->db->fetchOne(
            "SELECT COUNT(*) total FROM dbo.MarriageCouples WHERE IsActive=1"
        )['total']??0);

        $total=(int)($this->db->fetchOne(
            "SELECT COUNT(*) total FROM dbo.MarriageCouples"
        )['total']??0);

        $divorced=(int)($this->db->fetchOne(
            "SELECT COUNT(*) total
             FROM dbo.MarriageCouples
             WHERE IsActive=0 OR DivorcedDate IS NOT NULL"
        )['total']??0);

        $pending=(int)($this->db->fetchOne(
            "SELECT COUNT(*) total
             FROM dbo.MarriageRequests
             WHERE UPPER(Status) IN ('PENDING','WAITING','REQUESTED','OPEN')
                OR (ClosedDate IS NULL AND AdminApproved=0)"
        )['total']??0);

        $approvedRequests=(int)($this->db->fetchOne(
            "SELECT COUNT(*) total
             FROM dbo.MarriageRequests
             WHERE AdminApproved=1"
        )['total']??0);

        $auditActions=(int)($this->db->fetchOne(
            "SELECT COUNT(*) total FROM dbo.MarriageAudit"
        )['total']??0);

        return [
            'active_couples'=>$active,
            'total_marriages'=>$total,
            'divorced'=>$divorced,
            'pending_requests'=>$pending,
            'approved_requests'=>$approvedRequests,
            'audit_actions'=>$auditActions,
        ];
    }

    public function activeCouples(int $limit=50): array
    {
        $limit=max(10,min(200,$limit));

        return $this->db->fetchAll(
            "SELECT TOP {$limit}
                MarriageID,
                Character1,
                Character2,
                MarriedDate,
                ApprovedBy
             FROM dbo.MarriageCouples
             WHERE IsActive=1
             ORDER BY MarriedDate DESC,MarriageID DESC"
        );
    }

    public function recentHistory(int $limit=30): array
    {
        $limit=max(10,min(200,$limit));

        return $this->db->fetchAll(
            "SELECT TOP {$limit}
                HistoryID,
                ActionType,
                Character1,
                Character2,
                AdminName,
                Reason,
                Value,
                ActionDate
             FROM dbo.MarriageHistory
             ORDER BY ActionDate DESC,HistoryID DESC"
        );
    }

    public function personalStatus(string $accountId): array
    {
        $accountId=trim($accountId);
        if($accountId===''){
            return ['couples'=>[],'requests'=>[],'history'=>[]];
        }

        $couples=$this->db->fetchAll(
            "SELECT TOP 20
                MarriageID,
                Character1,
                Character2,
                MarriedDate,
                IsActive,
                DivorcedDate
             FROM dbo.MarriageCouples
             WHERE Account1=:a OR Account2=:a2
             ORDER BY MarriedDate DESC,MarriageID DESC",
            ['a'=>$accountId,'a2'=>$accountId]
        );

        $requests=$this->db->fetchAll(
            "SELECT TOP 20
                RequestID,
                RequesterName,
                TargetName,
                RequesterAccepted,
                TargetAccepted,
                AdminApproved,
                Status,
                CreatedDate,
                ApprovedDate,
                ExpiresDate,
                ClosedDate
             FROM dbo.MarriageRequests
             WHERE RequesterAccount=:a OR TargetAccount=:a2
             ORDER BY CreatedDate DESC,RequestID DESC",
            ['a'=>$accountId,'a2'=>$accountId]
        );

        $history=$this->db->fetchAll(
            "SELECT TOP 20
                HistoryID,
                ActionType,
                Character1,
                Character2,
                Reason,
                Value,
                ActionDate
             FROM dbo.MarriageHistory
             WHERE Character1 IN (SELECT Name FROM dbo.Character WHERE AccountID=:a)
                OR Character2 IN (SELECT Name FROM dbo.Character WHERE AccountID=:a2)
             ORDER BY ActionDate DESC,HistoryID DESC",
            ['a'=>$accountId,'a2'=>$accountId]
        );

        return [
            'couples'=>$couples,
            'requests'=>$requests,
            'history'=>$history,
        ];
    }

    private function tableExists(string $table): bool
    {
        $row=$this->db->fetchOne(
            "SELECT COUNT(*) total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA='dbo'
               AND TABLE_TYPE='BASE TABLE'
               AND TABLE_NAME=:t",
            ['t'=>$table]
        );

        return (int)($row['total']??0)>0;
    }
}
