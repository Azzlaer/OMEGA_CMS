<?php
declare(strict_types=1);

/**
 * Omega MU CMS
 * Secure Roulette v1.0.2
 *
 * Principios:
 * - el navegador NO decide AccountID;
 * - el navegador NO decide el premio;
 * - el premio se elige con random_int() en PHP;
 * - cooldown y entrega se validan server-side;
 * - CSRF lo valida el core de Omega antes de invocar user.page POST;
 * - bloqueo de concurrencia mediante transacción SQL + UPDLOCK/HOLDLOCK;
 * - auditoría persistente en SQL.
 */

return static function(
    string $hook,
    array $payload,
    array $config,
    array $context
): mixed {
    $db = $context['mu_db'];
    $log = $context['log'];

    $e = static fn(mixed $v): string =>
        htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $cfgBool = static function(string $key, bool $default=false) use ($config): bool {
        if(!array_key_exists($key,$config)) return $default;
        $v=$config[$key];
        if(is_bool($v)) return $v;
        return in_array(strtolower((string)$v),['1','true','yes','on'],true);
    };

    $cfgInt = static function(string $key,int $default,int $min,int $max) use($config): int {
        $v=(int)($config[$key]??$default);
        return max($min,min($max,$v));
    };

    $tableExists = static function(string $table) use($db): bool {
        $row=$db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA='dbo'
               AND TABLE_TYPE='BASE TABLE'
               AND TABLE_NAME=:table",
            ['table'=>$table]
        );
        return (int)($row['total']??0)>0;
    };

    $columnExists = static function(string $table,string $column) use($db): bool {
        $row=$db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA='dbo'
               AND TABLE_NAME=:table
               AND COLUMN_NAME=:column",
            ['table'=>$table,'column'=>$column]
        );
        return (int)($row['total']??0)>0;
    };

    $ensureSchema = static function() use($db): void {
        $db->execute("
            IF OBJECT_ID('dbo.cms_secure_roulette_state','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_secure_roulette_state(
                    account_id VARCHAR(10) NOT NULL PRIMARY KEY,
                    last_spin_at DATETIME2 NULL,
                    spin_count BIGINT NOT NULL CONSTRAINT DF_rulette_spin_count DEFAULT 0,
                    updated_at DATETIME2 NOT NULL CONSTRAINT DF_rulette_updated DEFAULT SYSUTCDATETIME()
                );
            END
        ");

        $db->execute("
            IF OBJECT_ID('dbo.cms_secure_roulette_log','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_secure_roulette_log(
                    id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    account_id VARCHAR(10) NOT NULL,
                    slot_no TINYINT NOT NULL,
                    reward_type VARCHAR(32) NOT NULL,
                    reward_amount INT NOT NULL,
                    created_at DATETIME2 NOT NULL CONSTRAINT DF_rulette_log_created DEFAULT SYSUTCDATETIME()
                );

                CREATE INDEX IX_cms_secure_roulette_log_account
                    ON dbo.cms_secure_roulette_log(account_id,created_at DESC);
            END
        ");
    };

    $rewardSlots = static function() use($config,$cfgInt): array {
        $allowed=['WCoinC','WCoinP','GoblinPoint','Credits'];
        $slots=[];

        for($i=1;$i<=8;$i++){
            $type=(string)($config['slot'.$i.'_type']??'WCoinC');
            if(!in_array($type,$allowed,true)) $type='WCoinC';

            $amount=$cfgInt('slot'.$i.'_amount',10,0,1000000);

            $slots[$i]=[
                'type'=>$type,
                'amount'=>$amount,
            ];
        }

        return $slots;
    };

    $rewardLabel = static function(string $type): string {
        return match($type){
            'WCoinC'=>'WCoinC',
            'WCoinP'=>'WCoinP',
            'GoblinPoint'=>'Goblin Point',
            'Credits'=>'Credits',
            default=>$type,
        };
    };

    $formatWait = static function(int $seconds): string {
        $seconds=max(0,$seconds);
        if($seconds>=86400){
            $d=intdiv($seconds,86400);
            $h=intdiv($seconds%86400,3600);
            return $d.'d '.$h.'h';
        }
        if($seconds>=3600){
            $h=intdiv($seconds,3600);
            $m=intdiv($seconds%3600,60);
            return $h.'h '.$m.'m';
        }
        if($seconds>=60){
            $m=intdiv($seconds,60);
            $s=$seconds%60;
            return $m.'m '.$s.'s';
        }
        return $seconds.'s';
    };

    $rewardBackendStatus = static function() use($tableExists,$columnExists): array {
        return [
            'WCoinC'=>$tableExists('CashShopData')
                && $columnExists('CashShopData','AccountID')
                && $columnExists('CashShopData','WCoinC'),
            'WCoinP'=>$tableExists('CashShopData')
                && $columnExists('CashShopData','AccountID')
                && $columnExists('CashShopData','WCoinP'),
            'GoblinPoint'=>$tableExists('CashShopData')
                && $columnExists('CashShopData','AccountID')
                && $columnExists('CashShopData','GoblinPoint'),
            'Credits'=>$tableExists('memb_credits')
                && $columnExists('memb_credits','memb___id')
                && $columnExists('memb_credits','credits'),
        ];
    };

    $creditReward = static function(
        string $accountId,
        string $type,
        int $amount
    ) use($db,$rewardBackendStatus): void {
        $status=$rewardBackendStatus();

        if(empty($status[$type])){
            throw new \RuntimeException(
                'El backend de '.$type.' no está disponible en esta base de datos.'
            );
        }

        if($amount<0 || $amount>1000000){
            throw new \RuntimeException('Cantidad de premio fuera de rango.');
        }

        if($type==='WCoinC'){
            $affected=$db->execute(
                "UPDATE dbo.CashShopData
                 SET WCoinC=ISNULL(WCoinC,0)+:amount
                 WHERE AccountID=:account",
                ['amount'=>$amount,'account'=>$accountId]
            );
            if($affected<1){
                throw new \RuntimeException('La cuenta no tiene fila en CashShopData.');
            }
            return;
        }

        if($type==='WCoinP'){
            $affected=$db->execute(
                "UPDATE dbo.CashShopData
                 SET WCoinP=ISNULL(WCoinP,0)+:amount
                 WHERE AccountID=:account",
                ['amount'=>$amount,'account'=>$accountId]
            );
            if($affected<1){
                throw new \RuntimeException('La cuenta no tiene fila en CashShopData.');
            }
            return;
        }

        if($type==='GoblinPoint'){
            $affected=$db->execute(
                "UPDATE dbo.CashShopData
                 SET GoblinPoint=ISNULL(GoblinPoint,0)+:amount
                 WHERE AccountID=:account",
                ['amount'=>$amount,'account'=>$accountId]
            );
            if($affected<1){
                throw new \RuntimeException('La cuenta no tiene fila en CashShopData.');
            }
            return;
        }

        if($type==='Credits'){
            $affected=$db->execute(
                "UPDATE dbo.memb_credits
                 SET credits=ISNULL(credits,0)+:amount
                 WHERE memb___id=:account",
                ['amount'=>$amount,'account'=>$accountId]
            );
            if($affected<1){
                throw new \RuntimeException('La cuenta no tiene fila en memb_credits.');
            }
            return;
        }

        throw new \RuntimeException('Tipo de premio no permitido.');
    };

    $remainingSeconds = static function(
        string $accountId,
        int $cooldownMinutes
    ) use($db): int {
        $row=$db->fetchOne(
            "SELECT TOP 1
                CASE
                    WHEN last_spin_at IS NULL THEN 0
                    WHEN DATEADD(MINUTE,CAST(:cooldown AS INT),last_spin_at) <= SYSUTCDATETIME() THEN 0
                    ELSE DATEDIFF(SECOND,SYSUTCDATETIME(),DATEADD(MINUTE,CAST(:cooldown2 AS INT),last_spin_at))
                END AS remaining_seconds
             FROM dbo.cms_secure_roulette_state
             WHERE account_id=:account",
            [
                'cooldown'=>$cooldownMinutes,
                'cooldown2'=>$cooldownMinutes,
                'account'=>$accountId
            ]
        );

        return max(0,(int)($row['remaining_seconds']??0));
    };

    $history = static function(string $accountId,int $limit) use($db): array {
        $limit=max(5,min(100,$limit));
        return $db->fetchAll(
            "SELECT TOP {$limit}
                slot_no,reward_type,reward_amount,created_at
             FROM dbo.cms_secure_roulette_log
             WHERE account_id=:account
             ORDER BY id DESC",
            ['account'=>$accountId]
        );
    };

    $styles = static function(): string {
        return <<<'CSS'
<style>
.srw{--bg:#0b1117;--panel:#111923;--panel2:#17212c;--border:#2a3946;--gold:#ddb65a;--red:#d65d57;--green:#72d69f;color:#e9eef2}
.srw *{box-sizing:border-box}
.srw .srw-hero{position:relative;overflow:hidden;border:1px solid #514125;border-radius:14px;padding:24px;background:radial-gradient(circle at 80% 20%,rgba(221,182,90,.18),transparent 35%),linear-gradient(135deg,#17120d,#0b1218 58%,#101a22);margin-bottom:18px}
.srw .srw-hero:after{content:"🎰";position:absolute;right:28px;top:-16px;font-size:145px;opacity:.06}
.srw .eyebrow{font-size:11px;letter-spacing:.15em;color:#91a3b1;font-weight:800}
.srw h2,.srw h3{margin:4px 0;color:#f0d27a}
.srw .muted{color:#8fa3b2}
.srw .srw-kpis{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:10px;margin:15px 0}
.srw .srw-kpi{background:linear-gradient(180deg,var(--panel2),var(--panel));border:1px solid var(--border);border-radius:10px;padding:13px}
.srw .srw-kpi span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:#8599a8}
.srw .srw-kpi strong{display:block;font-size:22px;margin-top:4px;color:#f3dfaa}
.srw .srw-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px}
.srw .srw-panel{background:linear-gradient(145deg,#121b24,#0d141b);border:1px solid var(--border);border-radius:12px;padding:16px}
.srw .srw-wheel{position:relative;width:420px;height:420px;max-width:100%;aspect-ratio:1/1;margin:0 auto;border-radius:50%;border:9px solid #8a6b30;box-shadow:0 0 0 5px #211b10,0 0 40px rgba(221,182,90,.09);overflow:hidden;background:#111}
.srw .srw-wheel-inner{position:absolute;inset:0;border-radius:50%;transition:transform 4.6s cubic-bezier(.12,.65,.11,1)}
.srw .srw-wheel-labels{position:absolute;inset:0}
.srw .srw-seg{position:absolute;left:50%;top:50%;width:45%;transform-origin:0 0;font-size:12px;font-weight:900;color:#fff;text-shadow:0 1px 3px #000;white-space:nowrap}
.srw .srw-pointer{position:absolute;left:50%;top:-2px;transform:translateX(-50%);z-index:5;width:0;height:0;border-left:18px solid transparent;border-right:18px solid transparent;border-top:0;border-bottom:34px solid #e7c86e;filter:drop-shadow(0 2px 3px #000)}
.srw .srw-center{position:absolute;inset:50% auto auto 50%;transform:translate(-50%,-50%);width:85px;height:85px;border-radius:50%;background:#1a2026;border:5px solid #a78642;z-index:4;display:flex;align-items:center;justify-content:center;font-size:34px}
.srw .srw-actions{text-align:center;margin-top:16px}
.srw .srw-btn{border:1px solid #8c6d31;background:linear-gradient(#49391f,#2d2418);color:#f3d781;border-radius:8px;padding:12px 22px;font-weight:900;font-size:16px;cursor:pointer}
.srw .srw-btn:disabled{opacity:.45;cursor:not-allowed}
.srw .srw-prize{border:1px solid #496b56;background:rgba(63,125,81,.11);color:#9ee2b7;border-radius:9px;padding:12px;margin-bottom:14px}
.srw .srw-error{border:1px solid #7c3b38;background:rgba(139,56,51,.12);color:#ffaaa4;border-radius:9px;padding:12px;margin-bottom:14px}
.srw .srw-slots{display:grid;gap:7px}
.srw .srw-slot{display:flex;justify-content:space-between;gap:8px;background:#0a1219;border:1px solid #21313c;border-radius:7px;padding:8px 9px}
.srw .srw-history{overflow:auto}
.srw table{width:100%;border-collapse:collapse;min-width:580px}
.srw th{padding:9px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#8da0af;background:#111b24}
.srw td{padding:9px;border-bottom:1px solid #1d2b36}
.srw .srw-note{margin-top:13px;border-left:3px solid #8c6d31;background:#0b131a;padding:10px 11px;color:#9fb1be}
.srw .ok{color:#78dfa7!important}.srw .bad{color:#ff8e88!important}
@media(max-width:950px){.srw .srw-grid{grid-template-columns:1fr}.srw .srw-kpis{grid-template-columns:1fr 1fr}}
</style>
CSS;
    };

    $ensureSchema();
    $slots=$rewardSlots();
    $backend=$rewardBackendStatus();

    if($hook==='admin.panel'){
        $logCount=(int)(($db->fetchOne(
            "SELECT COUNT(*) AS total FROM dbo.cms_secure_roulette_log"
        )['total']??0));

        $stateCount=(int)(($db->fetchOne(
            "SELECT COUNT(*) AS total FROM dbo.cms_secure_roulette_state"
        )['total']??0));

        $backendRows='';
        foreach($backend as $type=>$ok){
            $backendRows.='<tr><td>'.$e($rewardLabel($type)).'</td><td style="color:'.
                ($ok?'#72d69f':'#ff8e88').'">'.($ok?'✓ Disponible':'✕ No disponible').'</td></tr>';
        }

        return $styles().'
        <div class="srw">
          <div class="srw-hero">
            <span class="eyebrow">MODULE SECURITY CENTER</span>
            <h2>🎰 Secure Roulette</h2>
            <p class="muted">Ruleta server-side para Omega Web.</p>
          </div>

          <div class="srw-kpis">
            <div class="srw-kpi"><span>Estado</span><strong class="'.($cfgBool('enabled',true)?'ok':'bad').'">'.($cfgBool('enabled',true)?'ACTIVA':'DESACTIVADA').'</strong></div>
            <div class="srw-kpi"><span>Cooldown</span><strong>'.$cfgInt('cooldown_minutes',1440,0,525600).' min</strong></div>
            <div class="srw-kpi"><span>Jugadores registrados</span><strong>'.number_format($stateCount,0,',','.').'</strong></div>
            <div class="srw-kpi"><span>Giros auditados</span><strong>'.number_format($logCount,0,',','.').'</strong></div>
          </div>

          <div class="srw-panel">
            <h3>Backends de premios</h3>
            <div class="srw-history">
              <table>
                <thead><tr><th>Premio</th><th>Estado</th></tr></thead>
                <tbody>'.$backendRows.'</tbody>
              </table>
            </div>

            <div class="srw-note">
              🔒 El AccountID nunca se recibe desde el navegador y el premio nunca
              se recibe por POST. Ambos son determinados por el servidor.
            </div>
          </div>
        </div>';
    }

    if($hook!=='user.page'){
        return null;
    }

    $accountId=trim((string)($payload['account_id']??''));
    if($accountId===''){
        throw new \RuntimeException('No se recibió una cuenta autenticada.');
    }

    $enabled=$cfgBool('enabled',true);
    $cooldownMinutes=$cfgInt('cooldown_minutes',1440,0,525600);
    $historyLimit=$cfgInt('history_limit',20,5,100);

    $method=strtoupper((string)($payload['method']??'GET'));
    $post=(array)($payload['post']??[]);
    $csrfToken=(string)($payload['csrf_token']??'');

    $wonSlot=0;
    $wonType='';
    $wonAmount=0;
    $error='';

    if($method==='POST' && isset($post['spin'])){
        if(!$enabled){
            $error='La ruleta está temporalmente desactivada.';
        }else{
            try{
                $result=$db->transaction(function($tx) use(
                    $accountId,$cooldownMinutes,$slots,$creditReward,$log
                ){
                    /*
                     * Bloqueo por rango/clave para esta cuenta.
                     * HOLDLOCK = SERIALIZABLE para impedir dos inserciones/giros
                     * simultáneos en la misma cuenta.
                     */
                    $row=$tx->fetchOne(
                        "SELECT account_id,last_spin_at,spin_count
                         FROM dbo.cms_secure_roulette_state WITH (UPDLOCK,HOLDLOCK)
                         WHERE account_id=:account",
                        ['account'=>$accountId]
                    );

                    if(!$row){
                        $tx->execute(
                            "INSERT INTO dbo.cms_secure_roulette_state(
                                account_id,last_spin_at,spin_count,updated_at
                             ) VALUES(
                                :account,NULL,0,SYSUTCDATETIME()
                             )",
                            ['account'=>$accountId]
                        );

                        $row=[
                            'account_id'=>$accountId,
                            'last_spin_at'=>null,
                            'spin_count'=>0
                        ];
                    }

                    /*
                     * Si cooldown=0, dejamos 3 segundos técnicos para impedir
                     * doble-submit accidental o concurrencia inmediata.
                     */
                    $effectiveCooldownSeconds=
                        $cooldownMinutes>0 ? ($cooldownMinutes*60) : 3;

                    if(!empty($row['last_spin_at'])){
                        $waitRow=$tx->fetchOne(
                            "SELECT
                                CASE
                                  WHEN DATEADD(SECOND,CAST(:cooldown AS INT),CAST(:last_spin AS DATETIME2))
                                       <= SYSUTCDATETIME()
                                  THEN 0
                                  ELSE DATEDIFF(
                                    SECOND,
                                    SYSUTCDATETIME(),
                                    DATEADD(SECOND,CAST(:cooldown2 AS INT),CAST(:last_spin2 AS DATETIME2))
                                  )
                                END AS remaining_seconds",
                            [
                                'cooldown'=>$effectiveCooldownSeconds,
                                'last_spin'=>(string)$row['last_spin_at'],
                                'cooldown2'=>$effectiveCooldownSeconds,
                                'last_spin2'=>(string)$row['last_spin_at'],
                            ]
                        );

                        $remaining=max(0,(int)($waitRow['remaining_seconds']??0));
                        if($remaining>0){
                            throw new \RuntimeException(
                                'Debes esperar antes de volver a girar.'
                            );
                        }
                    }

                    // El navegador jamás decide esto.
                    $slot=random_int(1,8);
                    $reward=$slots[$slot];

                    $type=(string)$reward['type'];
                    $amount=(int)$reward['amount'];

                    if($amount<0 || $amount>1000000){
                        throw new \RuntimeException('Premio fuera de rango.');
                    }

                    // Acreditar dentro de la misma transacción SQL.
                    $creditReward($accountId,$type,$amount);

                    $tx->execute(
                        "UPDATE dbo.cms_secure_roulette_state
                         SET last_spin_at=SYSUTCDATETIME(),
                             spin_count=spin_count+1,
                             updated_at=SYSUTCDATETIME()
                         WHERE account_id=:account",
                        ['account'=>$accountId]
                    );

                    $tx->execute(
                        "INSERT INTO dbo.cms_secure_roulette_log(
                            account_id,slot_no,reward_type,reward_amount,created_at
                         ) VALUES(
                            :account,:slot,:type,:amount,SYSUTCDATETIME()
                         )",
                        [
                            'account'=>$accountId,
                            'slot'=>$slot,
                            'type'=>$type,
                            'amount'=>$amount,
                        ]
                    );

                    $log(
                        'INFO',
                        'Spin account='.$accountId.
                        ' slot='.$slot.
                        ' reward='.$type.
                        ' amount='.$amount
                    );

                    return [
                        'slot'=>$slot,
                        'type'=>$type,
                        'amount'=>$amount
                    ];
                });

                $wonSlot=(int)$result['slot'];
                $wonType=(string)$result['type'];
                $wonAmount=(int)$result['amount'];

            }catch(\Throwable $ex){
                $error=$ex->getMessage();
                $log('WARNING','Spin rejected account='.$accountId.' reason='.$error);
            }
        }
    }

    $remaining=$remainingSeconds($accountId,$cooldownMinutes);

    // Cuando cooldown=0, la función pública devuelve 0; el anti-submit técnico
    // se aplica dentro de la transacción.
    if($cooldownMinutes===0) $remaining=0;

    $historyRows=$history($accountId,$historyLimit);

    $slotCards='';
    foreach($slots as $no=>$reward){
        $ok=!empty($backend[$reward['type']]);
        $slotCards.='<div class="srw-slot">
            <span>#'.$no.' · '.$e($rewardLabel($reward['type'])).'</span>
            <strong class="'.($ok?'ok':'bad').'">'.$e((string)$reward['amount']).'</strong>
        </div>';
    }

    $historyHtml='';
    if(!$historyRows){
        $historyHtml='<div class="srw-note">Todavía no tienes giros registrados.</div>';
    }else{
        $historyHtml='<div class="srw-history"><table><thead><tr>
            <th>Fecha</th><th>Slot</th><th>Premio</th><th>Cantidad</th>
        </tr></thead><tbody>';

        foreach($historyRows as $row){
            $historyHtml.='<tr>
                <td>'.$e((string)$row['created_at']).'</td>
                <td>#'.(int)$row['slot_no'].'</td>
                <td>'.$e($rewardLabel((string)$row['reward_type'])).'</td>
                <td>'.number_format((int)$row['reward_amount'],0,',','.').'</td>
            </tr>';
        }

        $historyHtml.='</tbody></table></div>';
    }

    /*
     * Rueda visual. El navegador recibe el slot GANADOR únicamente después de
     * que SQL ya entregó el premio. Cambiar JS/DOM no modifica DB.
     */
    $colors=['#a87b2c','#7d2930','#2b6578','#48692f','#8b4b26','#4f3c72','#26725c','#7c652d'];
    $gradient=[];
    for($i=0;$i<8;$i++){
        $start=$i*45;
        $end=$start+45;
        $gradient[]=$colors[$i].' '.$start.'deg '.$end.'deg';
    }
    $wheelGradient='conic-gradient('.implode(',',$gradient).')';

    $labels='';
    foreach($slots as $i=>$reward){
        $angle=(($i-1)*45)+22.5;
        $labels.='<div class="srw-seg" style="transform:rotate('.$angle.'deg) translateX(38px)">
            '.$e($rewardLabel($reward['type'])).' '.$e((string)$reward['amount']).'
        </div>';
    }

    $rotation=0;
    if($wonSlot>0){
        // Center of target segment at pointer (top), plus 6 full rotations.
        $targetCenter=(($wonSlot-1)*45)+22.5;
        $rotation=2160 + (360 - $targetCenter);
    }

    $html=$styles();
    $html.='<div class="srw">
      <div class="srw-hero">
        <span class="eyebrow">MY ACCOUNT · SERVER SIDE</span>
        <h2>🎰 Ruleta Segura</h2>
        <p class="muted">El premio y tu cuenta son determinados exclusivamente por Omega Web.</p>
      </div>';

    if($wonSlot>0){
        $html.='<div class="srw-prize">
            🎉 Premio acreditado:
            <strong>'.$e($rewardLabel($wonType)).' +'.number_format($wonAmount,0,',','.').'</strong>
        </div>';
    }

    if($error!==''){
        $html.='<div class="srw-error">⚠ '.$e($error).'</div>';
    }

    $html.='<div class="srw-kpis">
        <div class="srw-kpi"><span>Estado</span><strong class="'.($enabled?'ok':'bad').'">'.($enabled?'ACTIVA':'OFFLINE').'</strong></div>
        <div class="srw-kpi"><span>Cooldown</span><strong>'.($cooldownMinutes>0?$cooldownMinutes.' min':'Libre').'</strong></div>
        <div class="srw-kpi"><span>Próximo giro</span><strong>'.($remaining>0?$e($formatWait($remaining)):'Disponible').'</strong></div>
        <div class="srw-kpi"><span>Seguridad</span><strong class="ok">SERVER SIDE</strong></div>
    </div>

    <div class="srw-grid">
      <section class="srw-panel">
        <div class="srw-wheel">
          <div class="srw-pointer"></div>
          <div class="srw-wheel-inner" id="srwWheel"
               style="background:'.$e($wheelGradient).';'.($wonSlot>0?'transform:rotate('.$rotation.'deg);':'').'">
            <div class="srw-wheel-labels">'.$labels.'</div>
          </div>
          <div class="srw-center">Ω</div>
        </div>

        <div class="srw-actions">';

    if(!$enabled){
        $html.='<button class="srw-btn" type="button" disabled>Ruleta desactivada</button>';
    }elseif($remaining>0){
        $html.='<button class="srw-btn" type="button" disabled>Disponible en '.$e($formatWait($remaining)).'</button>';
    }else{
        $html.='<form method="post" action="/account/modules/secure-roulette" id="srwForm">
            <input type="hidden" name="_csrf" value="'.$e($csrfToken).'">
            <input type="hidden" name="spin" value="1">
            <button class="srw-btn" id="srwSpinButton" type="submit">🎰 GIRAR RULETA</button>
        </form>';
    }

    $html.='</div>

        <div class="srw-note">
          🔒 Puedes modificar HTML, JavaScript o la animación con DevTools si quieres:
          eso no cambia el premio real, porque SQL ya lo acreditó en el servidor.
        </div>
      </section>

      <aside class="srw-panel">
        <span class="eyebrow">PREMIOS CONFIGURADOS</span>
        <h3>8 posiciones</h3>
        <div class="srw-slots">'.$slotCards.'</div>
      </aside>
    </div>

    <section class="srw-panel" style="margin-top:18px">
      <span class="eyebrow">AUDITORÍA PERSONAL</span>
      <h3>Últimos giros</h3>
      '.$historyHtml.'
    </section>
    </div>';

    if($wonSlot>0){
        $html.='<script>
        document.addEventListener("DOMContentLoaded",function(){
            var wheel=document.getElementById("srwWheel");
            if(!wheel) return;
            wheel.style.transform="rotate(0deg)";
            requestAnimationFrame(function(){
                requestAnimationFrame(function(){
                    wheel.style.transform="rotate('.$rotation.'deg)";
                });
            });
        });
        </script>';
    }

    $html.='<script>
    document.addEventListener("DOMContentLoaded",function(){
        var form=document.getElementById("srwForm");
        if(!form) return;
        form.addEventListener("submit",function(){
            var btn=document.getElementById("srwSpinButton");
            if(btn){
                btn.disabled=true;
                btn.textContent="Procesando premio...";
            }
        });
    });
    </script>';

    return [
        'title'=>'Ruleta Segura',
        'html'=>$html
    ];
};
