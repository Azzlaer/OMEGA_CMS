<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;

final class MyJewelBank
{
    private const BALANCE_TABLE = 'CustomJewelBank';
    private const LEDGER_TABLE  = 'CustomJewelBankLedger';

    public function __construct(private readonly Database $db) {}

    public function available(): bool
    {
        return $this->tableExists(self::BALANCE_TABLE);
    }

    public function ledgerAvailable(): bool
    {
        return $this->tableExists(self::LEDGER_TABLE);
    }

    public function balance(string $accountId): array
    {
        if(!$this->available()) return [];

        $cols=$this->columnMap(self::BALANCE_TABLE);
        $accountCol=$this->findColumn($cols,['AccountID','AccountId','account_id','memb___id']);
        if(!$accountCol) return [];

        $row=$this->db->fetchOne(
            "SELECT TOP 1 * FROM dbo.[".self::BALANCE_TABLE."] WHERE [{$accountCol}]=:account",
            ['account'=>$accountId]
        );

        if(!$row){
            return [
                'Bless'=>0,
                'Soul'=>0,
                'Life'=>0,
                'Creation'=>0,
                'Chaos'=>0,
                'Revision'=>0,
                'LastUpdate'=>null
            ];
        }

        return [
            'Bless'      => $this->intFrom($row,$cols,['Bless']),
            'Soul'       => $this->intFrom($row,$cols,['Soul']),
            'Life'       => $this->intFrom($row,$cols,['Life']),
            'Creation'   => $this->intFrom($row,$cols,['Creation']),
            'Chaos'      => $this->intFrom($row,$cols,['Chaos']),
            'Revision'   => $this->intFrom($row,$cols,['Revision']),
            'LastUpdate' => $this->valueFrom($row,$cols,['LastUpdate','UpdatedAt','updated_at']),
        ];
    }

    public function history(string $accountId,int $limit=50): array
    {
        if(!$this->ledgerAvailable()) return [];

        $limit=max(1,min(200,$limit));
        $cols=$this->columnMap(self::LEDGER_TABLE);
        $accountCol=$this->findColumn($cols,['AccountID','AccountId','account_id','memb___id']);
        if(!$accountCol) return [];

        $orderCol=$this->findColumn($cols,['CreatedAt','ActionDate','LedgerID','ID','Id']);

        $sql="SELECT TOP {$limit} * FROM dbo.[".self::LEDGER_TABLE."] WHERE [{$accountCol}]=:account";
        if($orderCol){
            $sql.=" ORDER BY [{$orderCol}] DESC";
        }

        $rows=$this->db->fetchAll($sql,['account'=>$accountId]);

        $out=[];
        foreach($rows as $row){
            $out[]=[
                'id'=>$this->valueFrom($row,$cols,['LedgerID','ID','Id']),
                'request'=>$this->valueFrom($row,$cols,['RequestID','RequestId','request_id']),
                'action'=>$this->valueFrom($row,$cols,['ActionType','Action','action']),
                'jewel'=>$this->valueFrom($row,$cols,['JewelType','Jewel','jewel']),
                'amount'=>$this->valueFrom($row,$cols,['Amount','amount']),
                'created_at'=>$this->valueFrom($row,$cols,['CreatedAt','ActionDate','created_at']),
            ];
        }

        return $out;
    }

    public function totals(string $accountId): array
    {
        $balance=$this->balance($accountId);
        $sum=0;
        foreach(['Bless','Soul','Life','Creation','Chaos'] as $jewel){
            $sum+=(int)($balance[$jewel]??0);
        }

        return [
            'total_jewels'=>$sum,
            'types_with_balance'=>count(array_filter([
                (int)($balance['Bless']??0),
                (int)($balance['Soul']??0),
                (int)($balance['Life']??0),
                (int)($balance['Creation']??0),
                (int)($balance['Chaos']??0),
            ], static fn(int $v): bool => $v>0)),
            'ledger_count'=>$this->ledgerCount($accountId),
        ];
    }

    public function recentSummary(string $accountId): array
    {
        $rows=$this->history($accountId,10);
        $in=0;
        $out=0;

        foreach($rows as $row){
            $amount=(int)($row['amount']??0);
            if($amount>0) $in+=$amount;
            elseif($amount<0) $out+=abs($amount);
        }

        return [
            'recent_in'=>$in,
            'recent_out'=>$out,
        ];
    }

    private function ledgerCount(string $accountId): int
    {
        if(!$this->ledgerAvailable()) return 0;

        $cols=$this->columnMap(self::LEDGER_TABLE);
        $accountCol=$this->findColumn($cols,['AccountID','AccountId','account_id','memb___id']);
        if(!$accountCol) return 0;

        $row=$this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM dbo.[".self::LEDGER_TABLE."] WHERE [{$accountCol}]=:account",
            ['account'=>$accountId]
        );

        return (int)($row['total']??0);
    }

    public static function jewelLabel(mixed $value): string
    {
        if($value===null || $value==='') return '—';

        $raw=(string)$value;
        if(!ctype_digit(ltrim($raw,'-'))) return $raw;

        $map=[
            0=>'Bless',
            1=>'Soul',
            2=>'Life',
            3=>'Creation',
            4=>'Chaos',
        ];

        return $map[(int)$raw] ?? ('Tipo '.$raw);
    }

    public static function actionLabel(mixed $value): string
    {
        if($value===null || $value==='') return 'Movimiento';

        $raw=(string)$value;
        if(!ctype_digit(ltrim($raw,'-'))) return $raw;

        $map=[
            0=>'Movimiento',
            1=>'Depósito',
            2=>'Retiro',
        ];

        return $map[(int)$raw] ?? ('Acción '.$raw);
    }

    private function tableExists(string $table): bool
    {
        $row=$this->db->fetchOne(
            "SELECT COUNT(*) AS total
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_TYPE='BASE TABLE'
                AND TABLE_NAME=:table",
            ['table'=>$table]
        );

        return (int)($row['total']??0)>0;
    }

    private function columnMap(string $table): array
    {
        $rows=$this->db->fetchAll(
            "SELECT COLUMN_NAME
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA='dbo'
                AND TABLE_NAME=:table
              ORDER BY ORDINAL_POSITION",
            ['table'=>$table]
        );

        $map=[];
        foreach($rows as $row){
            $name=(string)$row['COLUMN_NAME'];
            $map[strtolower($name)]=$name;
        }

        return $map;
    }

    private function findColumn(array $map,array $candidates): ?string
    {
        foreach($candidates as $candidate){
            $key=strtolower($candidate);
            if(isset($map[$key])) return $map[$key];
        }

        return null;
    }

    private function valueFrom(array $row,array $map,array $candidates): mixed
    {
        $col=$this->findColumn($map,$candidates);
        return $col!==null ? ($row[$col]??null) : null;
    }

    private function intFrom(array $row,array $map,array $candidates): int
    {
        return (int)($this->valueFrom($row,$map,$candidates)??0);
    }
}
