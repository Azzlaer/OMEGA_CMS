<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/install/lib/Installer.php';

use Omega\Install\Installer;

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode','1');
    ini_set('session.cookie_httponly','1');
    ini_set('session.cookie_samesite','Lax');
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

$installer = new Installer($root);
$ds = [
    'pdo_sqlsrv' => false,
    'pdo_dblib' => false,
    'fallback' => false,
    'recommended' => null,
    'platform' => PHP_OS_FAMILY,
];
try {
    $detectedDriverStatus = $installer->driverStatus();
    if (is_array($detectedDriverStatus)) {
        $ds = array_merge($ds, $detectedDriverStatus);
    }
} catch (\Throwable $e) {
    // Requirements page will show the missing driver; installer UI must remain renderable.
}

$locked = $installer->isInstalled();
$step = max(1, min(6, (int)($_GET['step'] ?? 1)));
$error = null;
$notice = null;

if (empty($_SESSION['omega_install_csrf'])) $_SESSION['omega_install_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['omega_install_csrf'];

function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function post(string $k, mixed $d=''): mixed { return $_POST[$k] ?? $d; }
function installer_url(int $step): string { return './?step='.$step; }

if ($locked) {
    http_response_code(403);
    $lock = @json_decode((string)@file_get_contents($root.'/storage/installed.lock'), true) ?: [];
    renderHeader(6, 'Instalador bloqueado');
    echo '<div class="card success"><h2>Omega MU CMS ya está instalado</h2><p>Por seguridad el instalador quedó bloqueado.</p><div class="summary"><b>Sitio:</b> '.h($lock['app_url'] ?? '').'<br><b>Base MU:</b> '.h($lock['mu_database'] ?? '').'<br><b>Base CMS:</b> '.h($lock['cms_database'] ?? '').'</div><p class="muted">Para reinstalar de forma deliberada, elimina manualmente <code>storage/installed.lock</code> desde el servidor. El instalador nunca lo desbloquea desde la web.</p><a class="btn primary" href="../">Ir al sitio</a></div>';
    renderFooter(); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)post('_csrf'))) $error = 'La sesión del instalador expiró. Recarga la página.';
    else try {
        $action = (string)post('action');
        if ($action === 'requirements') {
            if (!$installer->ready()) throw new RuntimeException('Corrige los requisitos marcados antes de continuar.');
            header('Location: '.installer_url(2)); exit;
        }
        if ($action === 'sql') {
            $sql = [
                'host'=>trim((string)post('host','152.230.104.97')),
                'port'=>(int)post('port',1433),
                'username'=>trim((string)post('username','muserver')),
                'password'=>(string)post('password'),
                'trust_cert'=>post('trust_cert') === '1',
                'encrypt'=>post('encrypt') === '1',
                'driver'=>in_array((string)post('sql_driver','auto'),['auto','dblib','sqlsrv'],true)
                    ? (string)post('sql_driver','auto')
                    : 'auto'
            ];
            $muDatabase = trim((string)post('mu_database','MuOnline97'));

            // Preserve every non-sensitive field before attempting the connection.
            // If PDO throws, step 2 can render the exact values the user submitted.
            // The SQL password is deliberately NEVER stored in session or echoed back.
            $_SESSION['omega_install_sql_form'] = [
                'host' => $sql['host'],
                'port' => $sql['port'],
                'username' => $sql['username'],
                'trust_cert' => $sql['trust_cert'],
                'encrypt' => $sql['encrypt'],
                'driver' => $sql['driver'],
                'mu_database' => $muDatabase,
            ];
            // cPanel/shared-hosting friendly: validate directly against MuOnline97.
            // The SQL login does NOT need access to master just to install the CMS.
            $muPdo = $installer->connect($sql, $muDatabase);
            $selectedDriver = $installer->lastDriver();
            if ($selectedDriver) $sql['driver'] = $selectedDriver;
            $sql['dblib_mode'] = $installer->lastConnectionMode() ?? 'unknown';
            $schema = $installer->validateMuSchema($muPdo);
            $_SESSION['omega_install_sql'] = $sql;
            $_SESSION['omega_install_mu_database'] = $muDatabase;
            $_SESSION['omega_install_schema'] = $schema;
            header('Location: '.installer_url(3)); exit;
        }
        if ($action === 'schema') {
            $schema = $_SESSION['omega_install_schema'] ?? [];
            if (!$schema || !$installer->schemaIsCompatible($schema)) throw new RuntimeException('El esquema MuOnline97 no es compatible. No se modificarán las tablas del servidor automáticamente.');
            header('Location: '.installer_url(4)); exit;
        }
        if ($action === 'cms') {
            $sql = $_SESSION['omega_install_sql'] ?? null;
            if (!$sql) throw new RuntimeException('Debes verificar primero la conexión SQL.');
            $cmsLocation = (string)post('cms_location','same');
            $mode = (string)post('table_mode','repair');
            if ($cmsLocation === 'same') {
                $cmsDatabase = (string)($_SESSION['omega_install_mu_database'] ?? 'MuOnline97');
                $cms = $installer->connect($sql, $cmsDatabase);
            } else {
                $cmsDatabase = trim((string)post('cms_database','OmegaCMS'));
                try {
                    $server = $installer->connect($sql, 'master');
                    $installer->createCmsDatabase($server, $cmsDatabase);
                    $cms = $installer->connect($sql, $cmsDatabase);
                } catch (Throwable $e) {
                    throw new RuntimeException('No se pudo crear/usar una base CMS separada. Tu usuario SQL probablemente no tiene acceso a master o permiso CREATE DATABASE. Usa “Misma base MuOnline97” o concede esos permisos. Detalle: '.$e->getMessage(), 0, $e);
                }
            }
            $installer->installCmsSchema($cms, $mode === 'rebuild');
            $_SESSION['omega_install_cms_database'] = $cmsDatabase;
            $_SESSION['omega_install_cms_location'] = $cmsLocation;
            $_SESSION['omega_install_table_mode'] = $mode;
            header('Location: '.installer_url(5)); exit;
        }
        if ($action === 'admin') {
            $sql = $_SESSION['omega_install_sql'] ?? null;
            $cmsDatabase = $_SESSION['omega_install_cms_database'] ?? null;
            if (!$sql || !$cmsDatabase) throw new RuntimeException('La base CMS aún no está preparada.');
            if ((string)post('password') !== (string)post('password_confirmation')) throw new RuntimeException('Las contraseñas del administrador no coinciden.');
            $admin = ['username'=>post('admin_username'),'email'=>post('admin_email'),'password'=>post('password')];
            $cms = $installer->connect($sql, $cmsDatabase);
            $mu = $installer->connect($sql, (string)($_SESSION['omega_install_mu_database'] ?? 'MuOnline97'));
            $installer->createAdmin($cms, $mu, $admin);
            $_SESSION['omega_install_admin'] = ['username'=>$admin['username'],'email'=>$admin['email']];
            header('Location: '.installer_url(6)); exit;
        }
        if ($action === 'finish') {
            $sql = $_SESSION['omega_install_sql'] ?? null;
            $muDatabase = $_SESSION['omega_install_mu_database'] ?? null;
            $cmsDatabase = $_SESSION['omega_install_cms_database'] ?? null;
            if (!$sql || !$muDatabase || !$cmsDatabase) throw new RuntimeException('Faltan datos de pasos anteriores.');
            $appName = trim((string)post('app_name','Omega MU'));
            $appUrl = rtrim(trim((string)post('app_url')), '/');
            $timezone = trim((string)post('timezone','America/Santiago'));
            if ($appName === '') throw new RuntimeException('Indica el nombre del servidor/web.');
            if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl)) throw new RuntimeException('El dominio/URL debe ser una URL válida, por ejemplo https://mu.example.com');
            if (!in_array($timezone, timezone_identifiers_list(), true)) throw new RuntimeException('Zona horaria no válida.');
            $cms = $installer->connect($sql, $cmsDatabase);
            $settings = ['site_name'=>$appName,'site_url'=>$appUrl,'timezone'=>$timezone,'gameserver_driver'=>'muemu097k_kayito','mu_database'=>$muDatabase];
            $installer->saveSettings($cms, $settings);
            $installer->writeEnv(['app_name'=>$appName,'app_url'=>$appUrl,'timezone'=>$timezone,'sql'=>$sql,'mu_database'=>$muDatabase,'cms_database'=>$cmsDatabase]);
            $installer->writeLock(['app_name'=>$appName,'app_url'=>$appUrl,'mu_database'=>$muDatabase,'cms_database'=>$cmsDatabase,'driver'=>'MuEmu 0.97k Kayito','admin'=>$_SESSION['omega_install_admin']['username'] ?? '']);
            $_SESSION = [];
            if (session_id() !== '') session_destroy();
            renderHeader(6, 'Instalación completada');
            echo '<div class="card success"><div class="bigcheck">✓</div><h2>Omega MU CMS 2.0 instalado</h2><p>La conexión, las tablas CMS y la cuenta administradora quedaron configuradas.</p><div class="warning"><b>Importante:</b> el instalador quedó bloqueado automáticamente.</div><a class="btn primary" href="../">Abrir Omega MU CMS</a></div>';
            renderFooter(); exit;
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$titles = [1=>'Requisitos del servidor',2=>'Conexión SQL Server',3=>'Verificar MuEmu 0.97k',4=>'Base de datos del CMS',5=>'Cuenta administradora',6=>'Dominio y sitio'];
renderHeader($step, $titles[$step]);
echo '<div class="info"><b>BUILD ACTIVA:</b> v0.14.3 MULTI-DRIVER &nbsp;·&nbsp; <b>SQL Server:</b> PDO_SQLSRV / PDO_DBLIB<br><b>Plataforma:</b> '.h(PHP_OS_FAMILY).' · PHP '.h(PHP_VERSION).'</div>';
if ($error) echo '<div class="alert error">'.h($error).'</div>';
if ($notice) echo '<div class="alert notice">'.h($notice).'</div>';

if ($step === 1) {
    $checks=$installer->requirements();
    echo '<div class="card"><h2>Comprobación del servidor</h2><p class="muted">Omega revisará PHP y los permisos necesarios antes de tocar SQL Server.</p><div class="checks">';
    foreach($checks as $label=>$ok) echo '<div class="check '.($ok?'ok':'bad').'"><span>'.($ok?'✓':'×').'</span><b>'.h($label).'</b><em>'.($ok?'OK':'Revisar').'</em></div>';
    $ds = array_merge(
        [
            'pdo_sqlsrv'=>false,
            'pdo_dblib'=>false,
            'fallback'=>false,
            'recommended'=>null,
            'platform'=>PHP_OS_FAMILY,
        ],
        (array)$installer->driverStatus()
    );
    $recommendedLabel = ($ds['recommended']??null)==='sqlsrv'
        ? 'PDO_SQLSRV · Windows/XAMPP recomendado'
        : (($ds['recommended']??null)==='dblib'
            ? 'PDO_DBLIB / FreeTDS · Linux/cPanel recomendado'
            : 'Ninguno');
    echo '<div class="info"><b>Drivers SQL detectados</b><br>'
        .'PDO_SQLSRV: '.(($ds['pdo_sqlsrv'] ?? false)?'✓ disponible':'× no disponible').'<br>'
        .'PDO_DBLIB / FreeTDS: '.(($ds['pdo_dblib'] ?? false)?'✓ disponible':'× no disponible').'<br>'
        .'<b>Plataforma:</b> '.h($ds['platform']??PHP_OS_FAMILY).'<br>'
        .'<b>Recomendado:</b> '.h($recommendedLabel)
        .(($ds['fallback'] ?? false)?'<br><small>En modo Automático Omega usará el recomendado y conserva el otro como fallback.</small>':'')
        .'</div>';
    echo '</div><form method="post"><input type="hidden" name="_csrf" value="'.h($csrf).'"><input type="hidden" name="action" value="requirements"><button class="btn primary" '.($installer->ready()?'':'disabled').'>Continuar</button></form></div>';
}
elseif ($step === 2) {
    // Successful connection values take precedence; otherwise use the last submitted
    // non-sensitive draft. POST is used as a final fallback for the current request.
    $saved = $_SESSION['omega_install_sql'] ?? [];
    $draft = $_SESSION['omega_install_sql_form'] ?? [];
    $s = array_merge($draft, $saved);

    $hostValue = (string)post('host', $s['host'] ?? '152.230.104.97');
    $portValue = (int)post('port', $s['port'] ?? 1433);
    $userValue = (string)post('username', $s['username'] ?? 'muserver');
    $dbValue = (string)post('mu_database', $s['mu_database'] ?? ($_SESSION['omega_install_mu_database'] ?? 'MuOnline97'));
    $driverValue = (string)(post('sql_driver') ?: ($s['driver'] ?? 'auto'));
    if(!in_array($driverValue,['auto','dblib','sqlsrv'],true)) $driverValue='auto';

    $trustValue = $_SERVER['REQUEST_METHOD']==='POST'
        ? post('trust_cert') === '1'
        : (array_key_exists('trust_cert',$s)
            ? (bool)$s['trust_cert']
            : PHP_OS_FAMILY === 'Windows');

    $encryptValue = $_SERVER['REQUEST_METHOD']==='POST'
        ? post('encrypt') === '1'
        : (bool)($s['encrypt'] ?? false);

    $driverOptions =
        '<option value="auto" '.($driverValue==='auto'?'selected':'').'>Automático (recomendado)</option>'.
        '<option value="sqlsrv" '.($driverValue==='sqlsrv'?'selected':'').' '.(!($ds['pdo_sqlsrv'] ?? false)?'disabled':'').'>PDO_SQLSRV · Windows/XAMPP recomendado'.(!($ds['pdo_sqlsrv'] ?? false)?' (no disponible)':'').'</option>'.
        '<option value="dblib" '.($driverValue==='dblib'?'selected':'').' '.(!($ds['pdo_dblib'] ?? false)?'disabled':'').'>PDO_DBLIB / FreeTDS · Linux/cPanel recomendado'.(!($ds['pdo_dblib'] ?? false)?' (no disponible)':'').'</option>';

    echo '<div class="card"><h2>SQL Server / MuOnline97</h2>'
        .'<p class="muted">Omega detecta ambos controladores. En Windows/XAMPP se recomienda <b>PDO_SQLSRV</b>; en Linux/cPanel se recomienda <b>PDO_DBLIB / FreeTDS</b>. Las credenciales se validan conectando realmente a SQL Server.</p>'
        .'<form method="post" class="formgrid">'
        .'<input type="hidden" name="_csrf" value="'.h($csrf).'"><input type="hidden" name="action" value="sql">'
        .'<label>Servidor SQL<input name="host" required value="'.h($hostValue).'"></label>'
        .'<label>Puerto<input name="port" type="number" min="1" max="65535" required value="'.h($portValue).'"></label>'
        .'<label>Usuario SQL<input name="username" autocomplete="username" required value="'.h($userValue).'"></label>'
        .'<label>Contraseña SQL<input name="password" type="password" autocomplete="current-password" required placeholder="Vuelve a escribirla si la prueba falla"></label>'
        .'<label>Base MuEmu<input name="mu_database" required value="'.h($dbValue).'"></label>'
        .'<label>Driver SQL<select name="sql_driver">'.$driverOptions.'</select></label>'
        .'<label class="toggle"><input type="checkbox" name="trust_cert" value="1" '.($trustValue?'checked':'').'> Confiar en certificado SQL</label>'
        .'<label class="toggle"><input type="checkbox" name="encrypt" value="1" '.($encryptValue?'checked':'').'> Cifrar conexión SQLSRV</label>'
        .'<div class="full successline"><b>Omega Multi-Driver:</b><br>'
        .'✓ PDO_SQLSRV — Windows / XAMPP recomendado<br>'
        .'✓ PDO_DBLIB / FreeTDS — Linux / cPanel recomendado<br>'
        .'El modo Automático elige según el sistema operativo y las extensiones cargadas.</div>'
        .'<div class="full info"><b>Drivers disponibles:</b> '.h($installer->driverName()).'<br>'
        .'PDO_SQLSRV requiere Microsoft ODBC Driver for SQL Server en Windows. '
        .'Para servidores SQL antiguos/locales puede convenir dejar <b>Cifrar conexión</b> desmarcado y <b>Confiar en certificado</b> marcado.<br>'
        .'<b>Seguridad:</b> Omega no guarda la contraseña mientras una prueba falle.</div>'
        .'<div class="full actions"><a class="btn" href="'.installer_url(1).'">Atrás</a><button class="btn primary">Probar conexión</button></div>'
        .'</form></div>';
}
elseif ($step === 3) {
    $schema=$_SESSION['omega_install_schema']??[]; $compatible=$schema&&$installer->schemaIsCompatible($schema);
    echo '<div class="card"><h2>Detector MuEmu 0.97k Kayito</h2><p>Base detectada: <b>'.h($_SESSION['omega_install_mu_database']??'').'</b></p><div class="checks">';
    foreach($schema as $table=>$r){$detail=$r['ok']?'Compatible':'Faltan: '.implode(', ',$r['missing']); echo '<div class="check '.($r['ok']?'ok':'bad').'"><span>'.($r['ok']?'✓':'×').'</span><b>'.h($table).'</b><em>'.h($detail).'</em></div>';}
    echo '</div>';
    if(!$compatible) echo '<div class="warning"><b>No se continuará automáticamente.</b> Omega jamás recreará ni borrará tablas del GameServer. Corrige/restaura la base MuOnline97 y vuelve a verificar.</div>';
    else echo '<div class="successline">✓ La estructura coincide con MuEmu-0.97k-kayito.</div>';
    echo '<form method="post"><input type="hidden" name="_csrf" value="'.h($csrf).'"><input type="hidden" name="action" value="schema"><div class="actions"><a class="btn" href="'.installer_url(2).'">Volver a SQL</a><button class="btn primary" '.($compatible?'':'disabled').'>Continuar</button></div></form></div>';
}
elseif ($step === 4) {
    $muDb = $_SESSION['omega_install_mu_database'] ?? 'MuOnline97';
    echo '<div class="card"><h2>Base de datos del CMS</h2><p class="muted">En hosting/cPanel se recomienda guardar las tablas web <code>cms_*</code> dentro de la misma base MuOnline97. Esto no modifica ni elimina las tablas del GameServer.</p><form method="post"><input type="hidden" name="_csrf" value="'.h($csrf).'"><input type="hidden" name="action" value="cms"><div class="radioCards"><label class="radioCard"><input type="radio" name="cms_location" value="same" checked><b>Misma base MuOnline97 (recomendado)</b><span>Usará <code>'.h($muDb).'</code> y creará solamente tablas <code>cms_*</code>. No necesita acceso a master ni CREATE DATABASE.</span></label><label class="radioCard"><input type="radio" name="cms_location" value="separate"><b>Base OmegaCMS separada</b><span>Requiere acceso a <code>master</code> y permiso <code>CREATE DATABASE</code>.</span></label></div><label>Nombre de base separada (solo modo avanzado)<input name="cms_database" value="OmegaCMS"></label><div class="radioCards"><label class="radioCard"><input type="radio" name="table_mode" value="repair" checked><b>Instalación segura / reparar</b><span>Crea las tablas CMS faltantes y conserva los datos existentes.</span></label><label class="radioCard danger"><input type="radio" name="table_mode" value="rebuild"><b>Recrear tablas CMS</b><span>Elimina y recrea exclusivamente <code>cms_*</code>. Nunca toca Character, MEMB_INFO, warehouse, Guild, etc.</span></label></div><div class="actions"><a class="btn" href="'.installer_url(3).'">Atrás</a><button class="btn primary">Preparar OmegaCMS</button></div></form></div>';
}
elseif ($step === 5) {
    $a=$_SESSION['omega_install_admin']??[];
    echo '<div class="card"><h2>Administrador vinculado a MU</h2><p class="muted">Este ID será una cuenta real de <code>MEMB_INFO</code> y, al mismo tiempo, el único Super Administrador inicial del CMS.</p><form method="post" class="formgrid"><input type="hidden" name="_csrf" value="'.h($csrf).'"><input type="hidden" name="action" value="admin"><label>ID / Cuenta MU administradora<input name="admin_username" minlength="4" maxlength="10" pattern="[A-Za-z0-9]+" required value="'.h($a['username']??'Azzlaer').'"></label><label>Email<input name="admin_email" type="email" maxlength="50" required value="'.h($a['email']??'').'"></label><label>Contraseña MU<input name="password" type="password" minlength="6" maxlength="10" autocomplete="new-password" required></label><label>Repetir contraseña<input name="password_confirmation" type="password" minlength="6" maxlength="10" autocomplete="new-password" required></label><div class="full info"><b>Una sola identidad:</b> Omega verificará si el ID existe en <code>MEMB_INFO</code>. Si no existe, lo creará y lo vinculará a <code>cms_admin_users.account_id</code>. Después entrarás a <b>/login</b> con esta cuenta MU y tendrás acceso adicional a <b>/admin</b>.</div><div class="full actions"><a class="btn" href="'.installer_url(4).'">Atrás</a><button class="btn primary">Crear/vincular administrador</button></div></form></div>';
}
elseif ($step === 6) {
    $guess = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost');
    echo '<div class="card"><h2>Identidad del sitio</h2><p>Último paso. Omega guardará la configuración y bloqueará <code>/install</code>.</p><form method="post" class="formgrid"><input type="hidden" name="_csrf" value="'.h($csrf).'"><input type="hidden" name="action" value="finish"><label>Nombre del servidor / web<input name="app_name" required value="Omega MU"></label><label>Dominio / URL<input name="app_url" type="url" required value="'.h($guess).'"></label><label>Zona horaria<input name="timezone" required value="America/Santiago"></label><div class="full summary"><b>Driver:</b> MuEmu 0.97k Kayito<br><b>Base MU:</b> '.h($_SESSION['omega_install_mu_database']??'').'<br><b>Base CMS:</b> '.h($_SESSION['omega_install_cms_database']??'').'<br><b>Administrador:</b> '.h($_SESSION['omega_install_admin']['username']??'').'</div><div class="full actions"><a class="btn" href="'.installer_url(5).'">Atrás</a><button class="btn primary">Finalizar instalación</button></div></form></div>';
}

renderFooter();

function renderHeader(int $step, string $title): void {
    $labels=['Requisitos','SQL Server','MuEmu','OmegaCMS','Administrador','Sitio'];
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' · Omega MU CMS</title><style>'.installerCss().'</style></head><body><main class="shell"><header><div class="brand"><div class="logo">Ω</div><div><strong>OMEGA MU CMS 2.0 <span style="font-size:11px;color:#87a2c4">v0.14.3-MULTI-DRIVER</span></strong><small>Installer · MuEmu 0.97k Kayito</small></div></div><h1>'.h($title).'</h1></header><nav class="steps">';
    foreach($labels as $i=>$label){$n=$i+1;$state=$n<$step?'done':($n===$step?'active':'');echo '<div class="step '.$state.'"><span>'.($n<$step?'✓':$n).'</span><b>'.h($label).'</b></div>';}
    echo '</nav><section>';
}
function renderFooter(): void { echo '</section><footer>Omega MU CMS 2.0 · v0.14.0 Multi-Driver · MuEmu 0.97k</footer></main></body></html>'; }
function installerCss(): string { return <<<'CSS'
:root{color-scheme:dark;--bg:#090b0f;--panel:#11151c;--panel2:#171c25;--border:#293141;--text:#edf2f8;--muted:#93a0b3;--accent:#b78a36;--accent2:#e7c36d;--good:#44c780;--bad:#ee6471;--warn:#e9aa45}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top,#1a202a 0,#090b0f 46%);font:14px/1.55 Segoe UI,Arial,sans-serif;color:var(--text);min-height:100vh}.shell{max-width:1020px;margin:0 auto;padding:34px 20px}header{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:28px}.brand{display:flex;align-items:center;gap:14px}.brand strong{display:block;font-size:18px;letter-spacing:.08em}.brand small{color:var(--muted)}.logo{width:48px;height:48px;border:1px solid #6f5528;border-radius:12px;display:grid;place-items:center;font-size:28px;color:var(--accent2);background:linear-gradient(145deg,#201a11,#11151c)}h1{font-size:24px;margin:0}h2{margin:0 0 8px;font-size:21px}.steps{display:grid;grid-template-columns:repeat(6,1fr);background:#0d1117;border:1px solid var(--border);border-radius:14px;padding:13px;margin-bottom:20px}.step{display:flex;align-items:center;justify-content:center;gap:7px;color:#657184;font-size:12px}.step span{width:26px;height:26px;display:grid;place-items:center;border-radius:50%;border:1px solid #3a4352}.step.active{color:#fff}.step.active span{background:var(--accent);border-color:var(--accent2);color:#0b0b0b;font-weight:700}.step.done{color:var(--good)}.step.done span{border-color:var(--good)}.card{background:linear-gradient(180deg,var(--panel2),var(--panel));border:1px solid var(--border);border-radius:16px;padding:26px;box-shadow:0 20px 70px #0008}.card.success{text-align:center;padding:42px}.muted{color:var(--muted)}.checks{display:grid;gap:9px;margin:20px 0}.check{display:grid;grid-template-columns:32px 1fr auto;gap:10px;align-items:center;background:#0c1016;border:1px solid #252d3b;border-radius:10px;padding:11px 13px}.check span{font-size:20px}.check em{font-style:normal;color:var(--muted);font-size:12px}.check.ok span{color:var(--good)}.check.bad span{color:var(--bad)}.check.bad em{color:var(--bad)}form{margin-top:18px}.formgrid{display:grid;grid-template-columns:1fr 1fr;gap:16px}label{display:grid;gap:7px;font-weight:600}input,select{width:100%;background:#090d13;color:#fff;border:1px solid #313b4b;border-radius:8px;padding:12px 13px;outline:none}input:focus,select:focus{border-color:var(--accent)}.toggle{display:flex;align-items:center;gap:9px;padding-top:28px}.toggle input,.radioCard input{width:auto}.full{grid-column:1/-1}.actions{display:flex;justify-content:space-between;gap:12px;margin-top:20px}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #3a4453;background:#171d26;color:#fff;border-radius:9px;padding:11px 17px;font-weight:700;cursor:pointer}.btn.primary{background:linear-gradient(#c79a43,#966c27);border-color:#d1ab5f;color:#101010}.btn[disabled]{opacity:.42;cursor:not-allowed}.alert,.warning,.info,.summary,.successline{border-radius:10px;padding:13px 15px;margin:0 0 17px}.error{background:#33171d;border:1px solid #6e2c38;color:#ffbdc4}.warning{background:#2b2212;border:1px solid #705528;color:#f4d79d;margin-top:18px}.info{background:#111d29;border:1px solid #284966;color:#b9d7f3}.summary{background:#0c1016;border:1px solid #2a3341;text-align:left}.successline{background:#10271d;border:1px solid #256b48;color:#93e8b9}.radioCards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:18px 0}.radioCard{display:grid;grid-template-columns:auto 1fr;column-gap:10px;background:#0d1117;border:1px solid #303947;border-radius:11px;padding:15px}.radioCard b,.radioCard span{grid-column:2}.radioCard span{color:var(--muted);font-weight:400}.radioCard.danger{border-color:#56343a}.bigcheck{font-size:58px;color:var(--good)}code{background:#080b10;padding:2px 6px;border-radius:5px;color:#d7b96f}footer{text-align:center;color:#596477;font-size:12px;padding:20px}@media(max-width:760px){header{align-items:start;flex-direction:column}.steps{grid-template-columns:repeat(3,1fr);gap:10px}.formgrid,.radioCards{grid-template-columns:1fr}.full{grid-column:auto}.step b{display:none}}
CSS; }
