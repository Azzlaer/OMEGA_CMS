<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(SESSION_NAME);
    session_start();
}
@mkdir(DATA_DIR,0700,true); @mkdir(JOB_DIR,0700,true); @mkdir(STATE_DIR,0700,true);

function h(string $s): string { return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function csrf_token(): string {
    if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $a=(string)($_POST['csrf']??'');$b=(string)($_SESSION['csrf']??'');
    if(!$a||!$b||!hash_equals($b,$a)){http_response_code(403);exit('CSRF invalido');}
}
function is_windows(): bool { return PHP_OS_FAMILY==='Windows'; }
function new_job(string $label): string {
    $id=bin2hex(random_bytes(16));
    file_put_contents(JOB_DIR.DIRECTORY_SEPARATOR.$id.'.status.json',json_encode([
        'state'=>'queued','progress'=>0,'step'=>'Preparando '.$label,'updated'=>date(DATE_ATOM)
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX);
    return $id;
}
function quote_arg(string $s): string { return '"' . str_replace('"','\"',$s) . '"'; }
function start_job(string $script,string $job,array $extra=[]): void {
    if(!ALLOW_EXECUTION) throw new RuntimeException('Ejecucion deshabilitada.');
    if(!is_windows()) throw new RuntimeException('Debe ejecutarse en Windows.');
    $file=SCRIPT_DIR.DIRECTORY_SEPARATOR.$script;
    if(!is_file($file)) throw new RuntimeException('Script faltante: '.$script);
    $args=['-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',$file,'-JobId',$job,'-DataDir',JOB_DIR];
    foreach($extra as $k=>$v){$args[]='-'.$k;$args[]=(string)$v;}
    $startupLog = JOB_DIR . DIRECTORY_SEPARATOR . $job . '.startup.log.txt';
    $cmd='cmd.exe /d /s /c start "" /b powershell.exe '.implode(' ',array_map('quote_arg',$args)).' >>'.quote_arg($startupLog).' 2>&1';
    $handle=@popen($cmd,'r');
    if($handle===false) throw new RuntimeException('PHP no pudo iniciar PowerShell. Revisa popen() y disable_functions.');
    @pclose($handle);
}
function valid_username(string $s): string {
    $s=trim($s);
    if($s===''||strlen($s)>64||!preg_match('/^[A-Za-z0-9._ @\-]+$/',$s)) throw new RuntimeException('Usuario invalido.');
    return $s;
}
function valid_port($v): int {
    $n=filter_var($v,FILTER_VALIDATE_INT);
    if($n===false||$n<1||$n>65535) throw new RuntimeException('Puerto invalido.');
    return $n;
}
function valid_int($v,int $min,int $max,string $name): int {
    $n=filter_var($v,FILTER_VALIDATE_INT);
    if($n===false||$n<$min||$n>$max) throw new RuntimeException($name.' invalido.');
    return $n;
}
function valid_ip_csv(string $raw): string {
    $items=preg_split('/[\s,;]+/',trim($raw))?:[];
    $out=[];
    foreach($items as $x){
      if($x==='')continue;
      if(strpos($x,'/')!==false){
        [$ip,$p]=array_pad(explode('/',$x,2),2,'');
        if(!filter_var($ip,FILTER_VALIDATE_IP)||!ctype_digit($p)) throw new RuntimeException('IP/CIDR invalida: '.$x);
        $max=str_contains($ip,':')?128:32;if((int)$p<0||(int)$p>$max)throw new RuntimeException('CIDR invalido: '.$x);
      }elseif(!filter_var($x,FILTER_VALIDATE_IP)) throw new RuntimeException('IP invalida: '.$x);
      $out[]=$x;
    }
    if(!$out) throw new RuntimeException('Debes indicar al menos una IP.');
    return implode(',',array_values(array_unique($out)));
}

$configUnsafe=(APP_PASSWORD==='CHANGE_ME_NOW'||strlen(APP_PASSWORD)<10);
if(isset($_GET['logout'])){$_SESSION=[];session_destroy();header('Location: ./');exit;}
if(empty($_SESSION['auth'])){
  $err='';
  if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['login'])){
    if($configUnsafe)$err='Cambia APP_PASSWORD en config.php.';
    elseif(hash_equals(APP_PASSWORD,(string)($_POST['password']??''))){
      session_regenerate_id(true);$_SESSION['auth']=true;csrf_token();header('Location: ./');exit;
    } else {sleep(1);$err='Clave incorrecta.';}
  }
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RDP Guard Web v3.2</title>
<style>body{font-family:system-ui;background:#08111f;color:#e5e7eb;margin:0;min-height:100vh;display:grid;place-items:center}.login{width:min(440px,92vw);padding:26px;background:#111827;border:1px solid #334155;border-radius:18px}input{width:100%;box-sizing:border-box;padding:12px;margin:8px 0 14px;background:#0b1220;color:#fff;border:1px solid #475569;border-radius:9px}button{border:0;border-radius:9px;padding:11px 16px;background:#2563eb;color:#fff;font-weight:700}.err{padding:10px;background:#451a1a;border-radius:8px}</style></head><body><div class="login"><h1>RDP Guard Web v3.2</h1><p>Recuperacion y proteccion de Escritorio remoto.</p><?php if($configUnsafe):?><p class="err">Cambia APP_PASSWORD en config.php.</p><?php endif;?><?php if($err):?><p class="err"><?=h($err)?></p><?php endif;?><form method="post"><label>Clave</label><input type="password" name="password" required><button name="login" value="1">Entrar</button></form></div></body></html><?php exit;
}

$error='';$job='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){
 verify_csrf();
 try{
   $action=(string)$_POST['action'];
   if($action==='diagnostic'){
     $job=new_job('diagnostico');start_job('diagnostic.ps1',$job);
   }elseif($action==='events'){
     $id=(int)($_POST['event_id']??4625);if(!in_array($id,[4624,4625,4740],true))throw new RuntimeException('Evento no permitido.');
     $count=valid_int($_POST['event_count']??DEFAULT_EVENT_COUNT,1,MAX_EVENT_COUNT,'Cantidad');
     $job=new_job('eventos');start_job('events.ps1',$job,['EventId'=>$id,'MaxEvents'=>$count]);
   }elseif($action==='recover'){
     $user=valid_username((string)($_POST['username']??'Administrator'));
     $port=valid_port($_POST['rdp_port']??3389);
     $th=valid_int($_POST['lock_threshold']??DEFAULT_LOCKOUT_THRESHOLD,1,999,'Umbral');
     $du=valid_int($_POST['lock_duration']??DEFAULT_LOCKOUT_DURATION,1,9999,'Duracion');
     $wi=valid_int($_POST['lock_window']??DEFAULT_LOCKOUT_WINDOW,1,9999,'Ventana');
     $job=new_job('recuperacion');
     start_job('recover.ps1',$job,[
       'Username'=>$user,'Port'=>$port,'LockThreshold'=>$th,'LockDuration'=>$du,'LockWindow'=>$wi,
       'EnableNla'=>isset($_POST['nla'])?1:0,'AddRdpGroup'=>isset($_POST['add_rdp_group'])?1:0
     ]);
   }elseif($action==='firewall'){
     $port=valid_port($_POST['fw_port']??3389);$csv=valid_ip_csv((string)($_POST['allowed_ips']??''));
     $job=new_job('firewall');
     start_job('firewall.ps1',$job,['Port'=>$port,'AllowedCsv'=>$csv,'DisableGeneral'=>isset($_POST['disable_general'])?1:0]);
   }elseif($action==='antibrute_install'){
     $th=valid_int($_POST['brute_threshold']??DEFAULT_BRUTE_THRESHOLD,2,1000,'Umbral brute');
     $win=valid_int($_POST['brute_window']??DEFAULT_BRUTE_WINDOW_MIN,1,1440,'Ventana brute');
     $blk=valid_int($_POST['brute_block']??DEFAULT_BRUTE_BLOCK_MIN,5,10080,'Bloqueo brute');
     $csv=valid_ip_csv((string)($_POST['brute_whitelist']??'127.0.0.1,::1'));
     $job=new_job('anti-bruteforce');
     start_job('antibrute_install.ps1',$job,[
       'Threshold'=>$th,'WindowMinutes'=>$win,'BlockMinutes'=>$blk,'WhitelistCsv'=>$csv,
       'WorkerSource'=>SCRIPT_DIR.DIRECTORY_SEPARATOR.'antibrute_worker.ps1'
     ]);
   }elseif($action==='antibrute_remove'){
     $job=new_job('eliminar anti-bruteforce');start_job('antibrute_remove.ps1',$job);
   }
 }catch(Throwable $e){$error=$e->getMessage();}
}
$hostDefault=DEFAULT_RDP_HOST;
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>RDP Guard Web v3.2</title>
<style>
:root{color-scheme:dark;--bg:#08111f;--panel:#101827;--panel2:#0c1422;--line:#2b3a50;--muted:#94a3b8;--blue:#3b82f6;--green:#22c55e;--red:#ef4444;--amber:#f59e0b}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,Segoe UI,Arial;background:var(--bg);color:#e5e7eb}
header{position:sticky;top:0;z-index:10;padding:14px 20px;background:#0b1220e8;backdrop-filter:blur(10px);border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center}
header h1{font-size:20px;margin:0}a{color:#93c5fd;text-decoration:none}.wrap{max-width:1380px;margin:auto;padding:18px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.tabbtn{background:#172033}.tabbtn.active{background:#2563eb}.tab{display:none}.tab.active{display:block}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:17px;min-width:0}.full{grid-column:1/-1}.muted{color:var(--muted)}h2,h3{margin-top:0}
input,select,textarea{width:100%;padding:10px;border-radius:8px;background:#0b1220;color:#fff;border:1px solid #475569}textarea{min-height:80px}.row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.check{display:flex;gap:8px;align-items:center;margin:9px 0}.check input{width:auto}
button{border:0;border-radius:8px;padding:10px 14px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}.danger{background:#b91c1c}.good{background:#15803d}.secondary{background:#475569}
.warn,.err,.ok{padding:11px;border-radius:9px;margin:10px 0}.warn{background:#422006;border:1px solid #92400e}.err{background:#451a1a;border:1px solid #7f1d1d}.ok{background:#052e16;border:1px solid #166534}
.progress{height:16px;background:#020617;border:1px solid #334155;border-radius:999px;overflow:hidden}.bar{height:100%;width:0;background:#2563eb}.statusline{display:flex;justify-content:space-between;gap:10px}.hidden{display:none}
pre{white-space:pre-wrap;word-break:break-word;background:#020617;border:1px solid #334155;border-radius:9px;padding:12px;max-height:420px;overflow:auto}
.tablewrap{overflow:auto;max-height:520px;border:1px solid #334155;border-radius:10px}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:8px;border-bottom:1px solid #243244;text-align:left;vertical-align:top}th{position:sticky;top:0;background:#172033}.pill{display:inline-block;padding:2px 7px;margin:2px;border-radius:999px;background:#1e293b}.stepok{color:#86efac}.stepbad{color:#fca5a5}
@media(max-width:900px){.grid,.row{grid-template-columns:1fr}.full{grid-column:auto}}
</style></head><body>
<header><h1>🛡️ RDP Guard Web v3.2</h1><a href="?logout=1">Cerrar sesion</a></header>
<div class="wrap">
<?php if($error):?><div class="err"><?=h($error)?></div><?php endif;?>
<div class="warn"><b>Antes de modificar puerto o firewall:</b> mantén abierta la consola VNC/SPICE/KVM del proveedor. La regla "Recovery" abre el puerto RDP temporalmente a cualquier IP para ayudarte a recuperar acceso; después usa la pestaña Firewall para restringirlo.</div>

<div class="tabs">
<button class="tabbtn active" data-tab="dashboard">Estado</button>
<button class="tabbtn" data-tab="recovery">Recuperar RDP</button>
<button class="tabbtn" data-tab="events">Eventos</button>
<button class="tabbtn" data-tab="firewall">Firewall</button>
<button class="tabbtn" data-tab="antibrute">Anti-Bruteforce</button>
<button class="tabbtn" data-tab="connect">Conectar</button>
</div>

<section class="tab active" id="dashboard">
<div class="grid">
<div class="card"><h2>Diagnostico completo</h2><p class="muted">Cuenta, politica, RDP, NLA, puerto, listener, TermService, grupo RDP, firewall y bloqueos.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><button name="action" value="diagnostic">Ejecutar diagnostico</button></form></div>
<div class="card"><h2>Objetivo</h2><p>Recuperar el acceso sin desactivar permanentemente las protecciones de Windows. Después de entrar, restringe RDP a tu IP o usa VPN.</p></div>
<div class="card full" id="jobCard"><h2>Trabajo actual</h2><div class="statusline"><span id="step">Sin trabajo activo.</span><strong id="pct">0%</strong></div><div class="progress"><div class="bar" id="bar"></div></div><p class="muted" id="jobId"></p></div>
<div class="card full hidden" id="resultCard"><h2>Resultado</h2><div id="resultContent"></div></div>
<div class="card full"><details><summary>Log tecnico</summary><pre id="techLog">Sin datos.</pre></details></div>
</div>
</section>

<section class="tab" id="recovery">
<div class="grid">
<div class="card full"><h2>Recuperacion de cuenta y RDP</h2><p class="muted">Habilita la cuenta, intenta limpiar el bloqueo, configura una politica razonable, activa RDP/NLA, inicia TermService, verifica el listener y crea una regla de recuperacion.</p>
<form method="post" class="recoverForm"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<div class="row">
<div><label>Usuario local de Windows</label><input name="username" value="Administrator" required></div>
<div><label>Puerto RDP</label><input type="number" name="rdp_port" value="3389" min="1" max="65535"></div>
<div><label>Intentos antes de bloquear</label><input type="number" name="lock_threshold" value="<?=DEFAULT_LOCKOUT_THRESHOLD?>" min="1" max="999"></div>
</div>
<div class="row">
<div><label>Duracion bloqueo (min)</label><input type="number" name="lock_duration" value="<?=DEFAULT_LOCKOUT_DURATION?>" min="1"></div>
<div><label>Ventana observacion (min)</label><input type="number" name="lock_window" value="<?=DEFAULT_LOCKOUT_WINDOW?>" min="1"></div>
<div></div>
</div>
<label class="check"><input type="checkbox" name="nla" checked> Activar NLA</label>
<label class="check"><input type="checkbox" name="add_rdp_group" checked> Agregar/verificar usuario en Remote Desktop Users</label>
<button class="danger" name="action" value="recover" onclick="return confirm('Se modificara RDP, la cuenta, TermService y firewall. ¿Tienes abierta la consola VNC/SPICE/KVM?')">Recuperar acceso RDP</button>
</form></div>
</div>
</section>

<section class="tab" id="events">
<div class="grid">
<div class="card"><h2>Visor de seguridad</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<label>Evento</label><select name="event_id"><option value="4625">4625 - Login fallido</option><option value="4740">4740 - Cuenta bloqueada</option><option value="4624">4624 - Login correcto</option></select>
<label>Cantidad</label><input type="number" name="event_count" value="<?=DEFAULT_EVENT_COUNT?>" min="1" max="<?=MAX_EVENT_COUNT?>">
<button name="action" value="events">Consultar</button></form></div>
<div class="card"><h2>Que buscar</h2><p class="muted">4625 repetidos desde una misma IP indican fuerza bruta. 4740 confirma qué cuenta se bloqueó. 4624 permite revisar accesos exitosos.</p></div>
</div>
</section>

<section class="tab" id="firewall">
<div class="grid"><div class="card full"><h2>Restringir RDP por IP</h2>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<div class="row"><div><label>Puerto RDP</label><input type="number" name="fw_port" value="3389"></div><div style="grid-column:span 2"><label>IPs/CIDR permitidas</label><input name="allowed_ips" placeholder="1.2.3.4/32, 5.6.7.0/24"></div></div>
<label class="check"><input type="checkbox" name="disable_general"> Deshabilitar reglas generales de Remote Desktop y la regla Recovery</label>
<p class="muted">Activa esa opcion solo cuando hayas probado que tu IP permitida es correcta.</p>
<button class="danger" name="action" value="firewall" onclick="return confirm('Una allowlist incorrecta puede dejarte sin RDP. ¿Continuar?')">Aplicar firewall restringido</button>
</form></div></div>
</section>

<section class="tab" id="antibrute">
<div class="grid">
<div class="card full"><h2>Anti-Bruteforce automatico</h2><p class="muted">Instala una tarea SYSTEM que cada 5 minutos revisa eventos 4625. Si una IP supera el umbral dentro de la ventana, crea una regla temporal de bloqueo y la elimina cuando vence.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<div class="row"><div><label>Fallos para bloquear</label><input type="number" name="brute_threshold" value="<?=DEFAULT_BRUTE_THRESHOLD?>" min="2"></div>
<div><label>Ventana (min)</label><input type="number" name="brute_window" value="<?=DEFAULT_BRUTE_WINDOW_MIN?>" min="1"></div>
<div><label>Bloquear por (min)</label><input type="number" name="brute_block" value="<?=DEFAULT_BRUTE_BLOCK_MIN?>" min="5"></div></div>
<label>Whitelist (tu IP + localhost)</label><input name="brute_whitelist" value="127.0.0.1, ::1" placeholder="127.0.0.1, ::1, TU.IP/32">
<div style="margin-top:10px"><button class="good" name="action" value="antibrute_install">Instalar / actualizar</button> <button class="secondary" name="action" value="antibrute_remove" onclick="return confirm('¿Eliminar anti-bruteforce y sus bloqueos temporales?')">Desinstalar</button></div>
</form></div>
</div>
</section>

<section class="tab" id="connect">
<div class="grid"><div class="card full"><h2>Crear conexion .RDP</h2><p class="muted">Genera un archivo de Escritorio remoto con NLA y solicitud de credenciales.</p>
<div class="row"><div><label>IP o dominio VPS</label><input id="rdpHost" value="<?=h($hostDefault)?>" placeholder="152.230.104.97"></div><div><label>Puerto</label><input id="rdpPort" type="number" value="3389"></div><div><label>Usuario</label><input id="rdpUser" value="Administrator"></div></div>
<div style="margin-top:12px"><button type="button" id="downloadRdp">Descargar archivo .RDP</button></div>
<p class="muted">Este archivo no contiene la contraseña. Windows te la pedirá al abrirlo.</p>
</div></div>
</section>

</div>
<script>
(()=>{
const initialJob=<?=json_encode($job,JSON_UNESCAPED_SLASHES)?>;
let timer=null,currentJob=initialJob;
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

document.querySelectorAll('.tabbtn').forEach(b=>b.addEventListener('click',()=>{
 document.querySelectorAll('.tabbtn').forEach(x=>x.classList.remove('active'));
 document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
 b.classList.add('active');document.getElementById(b.dataset.tab).classList.add('active');
}));

function card(title,obj){
 let body=typeof obj==='string'?obj:JSON.stringify(obj??{},null,2);
 return '<div class="card"><h3>'+esc(title)+'</h3><pre>'+esc(body)+'</pre></div>';
}
function renderResult(r){
 $('resultCard').classList.remove('hidden');
 let html='';
 if(r.type==='diagnostic'){
   const s=r.sections||{};html='<div class="grid">'+
   card('Privilegios',s.privileges)+card('Politica de cuentas',s.accountsPolicy)+
   card('Usuarios locales',s.localUsers)+card('RDP / NLA / Puerto',s.rdp)+
   card('TermService',s.termService)+card('Listener RDP',s.listener)+
   card('Remote Desktop Users',s.remoteDesktopUsers)+card('Firewall',s.firewall)+
   card('Bloqueos recientes',s.lockouts)+'</div>';
 }else if(r.type==='recovery'){
   html='<h3>Recuperacion de '+esc(r.username||'')+'</h3>';
   (r.steps||[]).forEach(x=>{html+='<p class="'+(x.ok?'stepok':'stepbad')+'">'+(x.ok?'✓ ':'✗ ')+esc(x.name)+' — '+esc(x.detail)+'</p>';});
   if(r.listenerOk) html+='<div class="ok">El puerto RDP responde localmente. Ya puedes probar la conexion desde tu PC.</div>';
   if(r.error) html+='<div class="err">'+esc(r.error)+'</div>';
 }else if(r.type==='events'){
   html='<h3>Evento '+esc(r.eventId)+' — '+esc(r.count)+' registros</h3>';
   if(Array.isArray(r.summary)&&r.summary.length){html+='<p>';r.summary.slice(0,25).forEach(x=>html+='<span class="pill">'+esc(x.ip)+' · '+esc(x.Count)+'</span>');html+='</p>';}
   html+='<div class="tablewrap"><table><thead><tr><th>Fecha</th><th>ID</th><th>Usuario</th><th>IP/Equipo</th><th>Tipo</th><th>Estado</th><th>Mensaje</th></tr></thead><tbody>';
   (r.rows||[]).forEach(x=>{html+='<tr><td>'+esc(x.time)+'</td><td>'+esc(x.id)+'</td><td>'+esc(x.user)+'</td><td>'+esc(x.ip||x.callerComputer||x.workstation)+'</td><td>'+esc(x.logonType)+'</td><td>'+esc((x.status||'')+' '+(x.subStatus||''))+'</td><td style="max-width:520px;white-space:pre-wrap">'+esc(x.message)+'</td></tr>';});
   html+='</tbody></table></div>';
 }else{
   html='<pre>'+esc(JSON.stringify(r,null,2))+'</pre>';
 }
 $('resultContent').innerHTML=html;
 document.querySelector('[data-tab="dashboard"]').click();
}

async function poll(job){
 try{
   const res=await fetch('api.php?job='+encodeURIComponent(job),{cache:'no-store'});
   const d=await res.json();if(!d.ok)throw new Error(d.error||'API');
   const s=d.status||{},pct=Math.max(0,Math.min(100,Number(s.progress)||0));
   $('bar').style.width=pct+'%';$('pct').textContent=pct+'%';$('step').textContent=s.step||s.state||'Procesando';$('jobId').textContent='Job: '+job+' · '+(s.state||'');$('techLog').textContent=d.log||'Sin log.';
   if(d.result)renderResult(d.result);
   if(s.state==='done'||s.state==='error'){clearInterval(timer);timer=null;}
 }catch(e){$('step').textContent='Error consultando estado: '+e.message;}
}
if(initialJob){poll(initialJob);timer=setInterval(()=>poll(initialJob),1500);}

$('downloadRdp').addEventListener('click',()=>{
 const host=$('rdpHost').value.trim(),port=$('rdpPort').value.trim(),user=$('rdpUser').value.trim();
 if(!host){alert('Escribe la IP o dominio del VPS.');return;}
 location.href='download_rdp.php?host='+encodeURIComponent(host)+'&port='+encodeURIComponent(port)+'&user='+encodeURIComponent(user);
});
})();
</script>
</body></html>
