<?php
declare(strict_types=1);

namespace Omega\Services;

use Omega\Core\Database;
use RuntimeException;

final class DatabaseFeatureCenter
{
    private const FEATURES = [
        'jewel-bank' => [
            'title' => 'Jewel Bank',
            'icon' => '💎',
            'description' => 'Banco de jewels, balances y movimientos.',
            'tables' => ['CustomJewelBank','CustomJewelBankLedger'],
            'priority' => 'high',
            'idea' => 'Saldo por cuenta, historial y futuro depósito/retiro web.'
        ],
        'duel-history' => [
            'title' => 'Duel History',
            'icon' => '⚔️',
            'description' => 'Historial PvP y estadísticas de duelos.',
            'tables' => ['DuelHistory','DuelRanking'],
            'priority' => 'high',
            'idea' => 'Perfil PvP, últimos rivales, victorias, derrotas y rachas.'
        ],
        'pk-drops' => [
            'title' => 'PK Item Drop History',
            'icon' => '☠️',
            'description' => 'Historial de objetos perdidos por PK.',
            'tables' => ['PKItemDropHistory'],
            'priority' => 'high',
            'idea' => 'Últimas muertes PK, killer, víctima e item perdido.'
        ],
        'monster-points' => [
            'title' => 'Monster Points',
            'icon' => '👹',
            'description' => 'Balance e historial de Monster Points.',
            'tables' => ['MonsterPointBalance','MonsterPointHistory'],
            'priority' => 'high',
            'idea' => 'Saldo, ranking, historial de ganancias y gastos.'
        ],
        'quest-progress' => [
            'title' => 'Latin Quest Progress',
            'icon' => '🎯',
            'description' => 'Progreso de misiones custom.',
            'tables' => ['LatinQuestProgress'],
            'priority' => 'high',
            'idea' => 'Panel de progreso de quests por personaje.'
        ],
        'golden-archer' => [
            'title' => 'Golden Archer',
            'icon' => '🏹',
            'description' => 'Sistema Golden Archer y números afortunados.',
            'tables' => ['GoldenArcherCoin','GoldenArcherLuckyNumbers'],
            'priority' => 'medium',
            'idea' => 'Renas, Stones, Lucky Numbers y ranking del evento.'
        ],
        'web-mail' => [
            'title' => 'Web Mail',
            'icon' => '✉️',
            'description' => 'Sistema de correo web.',
            'tables' => ['WE_WebMailSettings','WE_WebMailLog','WE_WebMailBlockedAccounts'],
            'priority' => 'medium',
            'idea' => 'Buzón web, historial y lista de bloqueados.'
        ],
        'change-class' => [
            'title' => 'Change Class',
            'icon' => '🔄',
            'description' => 'Cambio de clase administrable.',
            'tables' => ['WE_ChangeClassSettings','WE_ChangeClassCatalog','WE_ChangeClassLog'],
            'priority' => 'medium',
            'idea' => 'Catálogo de cambios, requisitos, precios y logs.'
        ],
        'minigames' => [
            'title' => 'Web Minigames',
            'icon' => '🎮',
            'description' => 'Colección de minijuegos WebEngine/custom.',
            'tables' => [
                'WE_MG_Settings','WE_MG_Games','WE_MG_Rewards','WE_MG_Redemptions',
                'WE_MG_GuessMonsters','WE_MG_GuessMaps','WE_MG_WhackTargets',
                'WE_MG_SecretSymbols','WE_MG_PuzzleImages','WE_MG_ChestPrizes',
                'WE_MG_HigherLowerCards'
            ],
            'priority' => 'medium',
            'idea' => 'Portal de minijuegos con premios, progreso y redenciones.'
        ],
        'countdown' => [
            'title' => 'Countdown / Events',
            'icon' => '📅',
            'description' => 'Contadores configurables para eventos.',
            'tables' => ['WE_CountdownSettings','WE_CountdownWidgets'],
            'priority' => 'medium',
            'idea' => 'Contadores de eventos, mantenimiento y temporadas.'
        ],
        'referrals' => [
            'title' => 'Referral System',
            'icon' => '🤝',
            'description' => 'Referidos, códigos y recompensas.',
            'tables' => ['WE_ReferralSettings','WE_ReferralCodes','WE_Referrals','WE_ReferralRewardLog'],
            'priority' => 'medium',
            'idea' => 'Programa de referidos con recompensas y seguimiento.'
        ],
        'binance-pay' => [
            'title' => 'Binance Pay',
            'icon' => '🪙',
            'description' => 'Órdenes y productos de Binance Pay.',
            'tables' => ['WE_BinancePaySettings','WE_BinancePayProducts','WE_BinancePayOrders','WE_BinancePayEvents'],
            'priority' => 'review',
            'idea' => 'Donaciones/pagos; requiere revisión de seguridad antes de habilitar escritura.'
        ],
        'mercadopago' => [
            'title' => 'MercadoPago',
            'icon' => '💳',
            'description' => 'Órdenes y productos MercadoPago.',
            'tables' => ['WE_MercadoPagoSettings','WE_MercadoPagoProducts','WE_MercadoPagoOrders'],
            'priority' => 'review',
            'idea' => 'Donaciones/pagos; requiere validación completa de callbacks y estados.'
        ],
    ];

    public function __construct(private readonly Database $db) {}

    public function featureSummary(): array
    {
        $existing = $this->existingTables();
        $lookup = array_fill_keys(array_map('strtolower',$existing), true);
        $out = [];

        foreach(self::FEATURES as $slug=>$feature){
            $found=[];
            $missing=[];

            foreach($feature['tables'] as $table){
                if(isset($lookup[strtolower($table)])) $found[]=$table;
                else $missing[]=$table;
            }

            $count=count($feature['tables']);
            $foundCount=count($found);

            $status=$foundCount===0
                ? 'missing'
                : ($foundCount===$count ? 'ready' : 'partial');

            $out[]=[
                'slug'=>$slug,
                'title'=>$feature['title'],
                'icon'=>$feature['icon'],
                'description'=>$feature['description'],
                'priority'=>$feature['priority'],
                'idea'=>$feature['idea'],
                'tables'=>$feature['tables'],
                'found'=>$found,
                'missing'=>$missing,
                'status'=>$status,
                'coverage'=>$count>0 ? (int)round(($foundCount/$count)*100) : 0,
            ];
        }

        usort($out, static function(array $a,array $b): int {
            $rank=['ready'=>0,'partial'=>1,'missing'=>2];
            $ra=$rank[$a['status']]??9;
            $rb=$rank[$b['status']]??9;
            if($ra!==$rb) return $ra<=>$rb;
            return strcmp($a['title'],$b['title']);
        });

        return $out;
    }

    public function statistics(): array
    {
        $tables=$this->existingTables();
        $features=$this->featureSummary();

        $ready=0;
        $partial=0;
        $missing=0;

        foreach($features as $f){
            if($f['status']==='ready') $ready++;
            elseif($f['status']==='partial') $partial++;
            else $missing++;
        }

        return [
            'table_count'=>count($tables),
            'feature_count'=>count($features),
            'ready'=>$ready,
            'partial'=>$partial,
            'missing'=>$missing,
        ];
    }

    public function existingTables(): array
    {
        $rows=$this->db->fetchAll("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA='dbo'
              AND TABLE_TYPE='BASE TABLE'
            ORDER BY TABLE_NAME
        ");

        return array_values(array_map(
            static fn(array $r): string => (string)$r['TABLE_NAME'],
            $rows
        ));
    }

    public function feature(string $slug): ?array
    {
        if(!isset(self::FEATURES[$slug])) return null;

        foreach($this->featureSummary() as $feature){
            if($feature['slug']===$slug) return $feature;
        }

        return null;
    }

    public function tableInfo(string $table): array
    {
        if(!$this->isAllowedTable($table)){
            throw new RuntimeException('Tabla no permitida.');
        }

        if(!$this->tableExists($table)){
            throw new RuntimeException('La tabla no existe en esta base de datos.');
        }

        $columns=$this->db->fetchAll("
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                IS_NULLABLE,
                ORDINAL_POSITION
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=:table
            ORDER BY ORDINAL_POSITION
        ",['table'=>$table]);

        $countRow=$this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM dbo.[".$table."]"
        );

        return [
            'name'=>$table,
            'columns'=>$columns,
            'row_count'=>(int)($countRow['total']??0)
        ];
    }

    public function preview(string $table,int $limit=25): array
    {
        if(!$this->isAllowedTable($table)){
            throw new RuntimeException('Tabla no permitida.');
        }

        if(!$this->tableExists($table)){
            throw new RuntimeException('La tabla no existe.');
        }

        $limit=max(1,min(100,$limit));

        return $this->db->fetchAll(
            "SELECT TOP {$limit} * FROM dbo.[".$table."]"
        );
    }

    public function featureTables(string $slug): array
    {
        $feature=$this->feature($slug);
        if(!$feature) return [];

        $result=[];
        foreach($feature['found'] as $table){
            try{
                $info=$this->tableInfo($table);
                $result[]=$info;
            }catch(\Throwable){
                // Mantener el feature center usable aunque una tabla puntual falle.
            }
        }

        return $result;
    }

    public function tableExists(string $table): bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/',$table)) return false;

        $row=$this->db->fetchOne("
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA='dbo'
              AND TABLE_TYPE='BASE TABLE'
              AND TABLE_NAME=:table
        ",['table'=>$table]);

        return (int)($row['total']??0)>0;
    }

    private function isAllowedTable(string $table): bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/',$table)) return false;

        foreach(self::FEATURES as $feature){
            foreach($feature['tables'] as $candidate){
                if(strcasecmp($candidate,$table)===0) return true;
            }
        }

        return false;
    }
}
