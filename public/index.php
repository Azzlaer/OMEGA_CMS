<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root.'/storage/installed.lock')) {
    header('Location: ../install/');
    exit;
}

spl_autoload_register(function(string $class) use($root){
    $prefix='Omega\\';
    if(!str_starts_with($class,$prefix)) return;
    $rel=str_replace('\\','/',substr($class,strlen($prefix)));
    $file=$root.'/app/'.$rel.'.php';
    if(is_file($file)) require $file;
});

use Omega\Core\Env;
use Omega\Core\Database;
use Omega\Core\Session;
use Omega\Core\Security;
use Omega\Core\ModuleManager;
use Omega\Core\View;
use Omega\GameServer\Drivers\MuEmu097kKayito\Driver;
use Omega\Repositories\NewsRepository;
use Omega\Services\AccountAuth;
use Omega\Services\AdminAccess;
use Omega\Services\AdminManager;
use Omega\Services\ModulePackageManager;
use Omega\Services\ModuleRuntime;
use Omega\Services\VisualAssetManager;
use Omega\Services\ChangelogManager;
use Omega\Services\CharacterNameRenderer;
use Omega\Services\RankingExclusionManager;
use Omega\Services\DatabaseFeatureCenter;
use Omega\Services\MyJewelBank;
use Omega\Services\SocialProfileManager;
use Omega\Services\DesignStudio;
use Omega\Services\GuildRankingProfessional;
use Omega\Services\GuideManager;
use Omega\Services\MonetizationCenter;
use Omega\Services\HCaptchaService;
use Omega\Core\Csrf;
use Omega\Core\RateLimiter;

Env::load($root.'/.env');
date_default_timezone_set((string)Env::get('APP_TIMEZONE','America/Santiago'));
Session::start();

$dbConfig = require $root.'/config/database.php';
$appConfig = require $root.'/config/app.php';
$muDb = new Database($dbConfig['mu']);
$cmsDb = new Database($dbConfig['cms']);
$driver = new Driver($muDb);
$newsRepo = new NewsRepository($cmsDb);
$rateLimiter = new RateLimiter($root.'/storage/cache/rate-limit');
$moduleRuntime = new ModuleRuntime($root,$cmsDb,$muDb);
$nameRenderer = new CharacterNameRenderer($moduleRuntime,$muDb);
$auth = new AccountAuth($muDb,$cmsDb,$rateLimiter,$moduleRuntime);
$adminAccess = new AdminAccess($cmsDb,$muDb);
$adminAccess->ensureSchema();
$adminManager = new AdminManager($cmsDb,$muDb);
$adminManager->ensureSchema();
$modulePackages = new ModulePackageManager($root.'/modules',$root.'/storage',$cmsDb);
$modulePackages->ensureSchema();
$visualAssets = new VisualAssetManager($cmsDb,$root);
$visualAssets->ensureSchema();
$changelog = new ChangelogManager($cmsDb);
$changelog->ensureSchema();
$rankingExclusions = new RankingExclusionManager($cmsDb,$muDb);
$rankingExclusions->ensureSchema();
$databaseFeatures = new DatabaseFeatureCenter($muDb);
$myJewelBank = new MyJewelBank($muDb);
$socialProfiles = new SocialProfileManager($cmsDb,$muDb,$root);
$socialProfiles->ensureSchema();
$designStudio = new DesignStudio($cmsDb,$root);
$designStudio->ensureSchema();
$guildRankingProfessional = new GuildRankingProfessional($muDb);
$guideManager = new GuideManager($cmsDb,$root);
$guideManager->ensureSchema();
$monetization = new MonetizationCenter($cmsDb);
$monetization->ensureSchema();
$hcaptcha = new HCaptchaService();
Security::headers($monetization->securityGoogleEnabled(),$hcaptcha->enabled());
$appName = (string)($appConfig['name'] ?? 'Omega MU');
$modules = (new ModuleManager($root.'/modules'))->discover();

function renderPage(string $root, string $appName, Driver $driver, AccountAuth $auth, AdminAccess $adminAccess, string $template, array $data=[]): string {
    global $moduleRuntime,$designStudio,$monetization;

    try { $stats = $driver->statistics(); }
    catch (Throwable $e) { $stats = ['online'=>0,'accounts'=>0,'characters'=>0,'guilds'=>0]; }

    $moduleHeadHtml='';
    try {
        $moduleHeadHtml=$moduleRuntime->invokeAllCollect('site.head',[
            'template'=>$template
        ]);
    } catch (Throwable) {}

    $body = View::render($template, $data + ['stats'=>$stats]);
    $loggedAccount=$auth->currentId();
    $isAdmin=$adminAccess->isAdmin($loggedAccount);
    $requestPath=parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $monetizationContext=$monetization->publicContext($loggedAccount,$isAdmin,$requestPath);

    return View::render('layout', [
        'title'=>$data['title'] ?? 'Inicio',
        'active'=>$data['active'] ?? 'home',
        'body'=>$body,
        'stats'=>$stats,
        'appName'=>$appName,
        'serverVersion'=>'MuEmu 0.97k Kayito',
        'loggedAccount'=>$loggedAccount,
        'isAdmin'=>$isAdmin,
        'moduleHeadHtml'=>$moduleHeadHtml,
        'monetization'=>$monetization,
        'monetizationContext'=>$monetizationContext,
        'design'=>$designStudio->publicContext(
            $template,
            (string)($data['active'] ?? 'home'),
            (string)($_SERVER['REQUEST_URI'] ?? '/')
        ),
    ]);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$flashSuccess = $_SESSION['_flash_success'] ?? null;
$flashError = $_SESSION['_flash_error'] ?? null;
unset($_SESSION['_flash_success'], $_SESSION['_flash_error']);

$redirect = static function(string $to): never {
    header('Location: '.$to, true, 303);
    exit;
};
$requireCsrf = static function(): void {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        throw new RuntimeException('La solicitud de seguridad expiró. Recarga la página e inténtalo nuevamente.');
    }
};

// Public module assets are served through the front controller, never directly from /modules.
if (preg_match('#^/module-assets/([a-z0-9_-]{3,80})/(.+)$#',$path,$am)) {
    $slug=$am[1];
    $rel=rawurldecode($am[2]);
    $rel=str_replace('\\','/',$rel);
    if (!$moduleRuntime->isEnabled($slug) || str_starts_with($rel,'/') || str_contains('/'.$rel.'/','/../')) {
        http_response_code(404); exit;
    }
    $ext=strtolower(pathinfo($rel,PATHINFO_EXTENSION));
    if (!in_array($ext,['css','js','gif','png','jpg','jpeg','webp'],true)) { http_response_code(403); exit; }
    $base=realpath($root.'/modules/'.$slug);
    $file=realpath($root.'/modules/'.$slug.'/'.$rel);
    if(!$base || !$file || !str_starts_with($file,$base.DIRECTORY_SEPARATOR) || !is_file($file)){
        http_response_code(404); exit;
    }
    $mimeMap=[
        'css'=>'text/css; charset=utf-8',
        'js'=>'application/javascript; charset=utf-8',
        'gif'=>'image/gif',
        'png'=>'image/png',
        'jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg',
        'webp'=>'image/webp',
    ];
    header('Content-Type: '.$mimeMap[$ext]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=300');
    readfile($file); exit;
}

// cPanel puede servir /index.php directamente.
if ($path === '/index.php') $path = '/';

if ($path === '/ads.txt') {
    $adsTxt=$monetization->adsTxt();
    if ($adsTxt===null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "ads.txt no está publicado.\n";
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $adsTxt;
    }
    exit;
}

try {
    // APIs permanecen JSON, pero la portada ya no.
    if (str_starts_with($path, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');

        if ($path === '/api/status') {
            echo json_encode([
                'name'=>$appName,
                'version'=>\Omega\Core\Version::current(),
                'driver'=>'MuEmu 0.97k Kayito',
                'stats'=>$driver->statistics(),
                'modules'=>$modules,
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($path === '/api/rankings/level') {
            echo json_encode(['data'=>$driver->ranking('level',50)],JSON_UNESCAPED_UNICODE); exit;
        }
        if ($path === '/api/rankings/reset') {
            echo json_encode(['data'=>$driver->ranking('reset',50)],JSON_UNESCAPED_UNICODE); exit;
        }
        if ($path === '/api/rankings/grandreset') {
            echo json_encode(['data'=>$driver->ranking('grandreset',50)],JSON_UNESCAPED_UNICODE); exit;
        }
        if (preg_match('#^/api/rankings/(duel|skyevent|monsterpoints|minigames|goldenarcher)$#',$path,$rm)) {
            echo json_encode(['data'=>$driver->advancedRanking($rm[1],50)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if (preg_match('#^/api/rankings/class/([a-z0-9-]+)$#',$path,$cm)) {
            $classId=\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::classFromSlug($cm[1]);
            if($classId===null){http_response_code(404);echo json_encode(['error'=>'Clase no soportada']);exit;}
            echo json_encode(['data'=>$driver->classRanking($classId,50)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($path === '/api/online') {
            echo json_encode([
                'count'=>$driver->onlineCharacterCount(),
                'data'=>$driver->onlineCharacters(100)
            ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        http_response_code(404);
        echo json_encode(['error'=>'API endpoint no encontrado'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($path === '/diagnostic') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($driver->diagnostic(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($path === '/') {
        try { $news = $newsRepo->latest(3); } catch(Throwable $e) { $news=[]; }
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'home',[
            'title'=>'Inicio','active'=>'home','news'=>$news,
            'topLevel'=>$driver->ranking('level',5),
            'topReset'=>$driver->ranking('reset',5),
            'nameRenderer'=>$nameRenderer,
        ]);
        exit;
    }

    if ($path === '/news') {
        try { $news = $newsRepo->latest(20); } catch(Throwable $e) { $news=[]; }
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'news',['title'=>'Noticias','active'=>'news','news'=>$news]);
        exit;
    }

    if (preg_match('#^/news/([A-Za-z0-9_-]+)$#',$path,$m)) {
        try { $article = $newsRepo->findBySlug($m[1]); } catch(Throwable $e) { $article=null; }
        if (!$article) { http_response_code(404); }
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,$article?'article':'simple', $article ? [
            'title'=>$article['title'],'active'=>'news','article'=>$article
        ] : [
            'title'=>'Noticia no encontrada','active'=>'news','heading'=>'Noticia no encontrada',
            'lead'=>'La publicación solicitada no existe o todavía no está publicada.',
            'content'=>'<p><a class="btn btn-primary" href="/news">Volver a noticias</a></p>'
        ]);
        exit;
    }


    if ($path === '/guides') {
        $q = trim((string)($_GET['q'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        try {
            $guides = $guideManager->publicList($q,$category);
            $categories = $guideManager->categories();
        } catch(Throwable $e) {
            $guides=[]; $categories=[];
        }
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'guides',[
            'title'=>'Guías','active'=>'guides','guides'=>$guides,'categories'=>$categories,
            'q'=>$q,'selectedCategory'=>$category
        ]);
        exit;
    }

    if (preg_match('#^/guides/([a-z0-9_-]{3,160})$#',$path,$gm)) {
        try { $guide = $guideManager->findPublishedBySlug($gm[1]); }
        catch(Throwable $e) { $guide=null; }
        if (!$guide) {
            http_response_code(404);
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
                'title'=>'Guía no encontrada','active'=>'guides','heading'=>'Guía no encontrada',
                'lead'=>'La guía no existe, fue retirada o todavía no está publicada.',
                'content'=>'<p><a class="btn btn-primary" href="/guides">Volver a Guías</a></p>'
            ]);
            exit;
        }
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'guide_article',[
            'title'=>$guide['title'],'active'=>'guides','guide'=>$guide
        ]);
        exit;
    }

    if ($path === '/changelog') {
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'changelog',[
            'title'=>'Changelog',
            'active'=>'changelog',
            'rows'=>$changelog->latest(50)
        ]);
        exit;
    }

    if (preg_match('#^/changelog/([a-z0-9_-]{3,180})$#',$path,$cm)) {
        $article=$changelog->findBySlug($cm[1]);
        if(!$article){
            http_response_code(404);
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
                'title'=>'Actualización no encontrada','active'=>'changelog','eyebrow'=>'CHANGELOG',
                'heading'=>'Actualización no encontrada','lead'=>'La publicación solicitada no existe.'
            ]);
            exit;
        }
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'changelog_article',[
            'title'=>$article['title'],
            'active'=>'changelog',
            'article'=>$article
        ]);
        exit;
    }

    if ($path === '/guilds') {
        $redirect('/rankings?type=guilds');
    }

    if ($path === '/rankings') {
        $type = strtolower((string)($_GET['type'] ?? 'level'));
        $allowed = [
            'level','reset','grandreset','guilds',
            'bloodcastle','devilsquare',
            'duel','skyevent','monsterpoints','minigames','goldenarcher',
            'dark-wizard','soul-master','dark-knight','blade-knight',
            'fairy-elf','muse-elf','magic-gladiator'
        ];

        if (!in_array($type,$allowed,true)) $type='level';

        $availability = $driver->rankingAvailability();

        if (array_key_exists($type,$availability) && !$availability[$type]) {
            $type='level';
        }

        $guildProfessional=null;
        $eventStats=null;

        if($type==='guilds'){
            $guildProfessional=$guildRankingProfessional->load(
                $_GET,
                $adminManager->settings()
            );
            $rows=[];
        }elseif(in_array($type,['bloodcastle','devilsquare'],true)){
            $rows=$driver->eventRanking($type,50);
            $eventStats=$driver->eventRankingSummary($type);
        }elseif(in_array($type,['duel','skyevent','monsterpoints','minigames','goldenarcher'],true)){
            $rows=$driver->advancedRanking($type,50);
        }elseif(($classId=\Omega\GameServer\Drivers\MuEmu097kKayito\CharacterClass::classFromSlug($type))!==null){
            $rows=$driver->classRanking($classId,50);
        }else{
            $rows=$driver->ranking($type,50);
        }

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'rankings',[
            'title'=>'Rankings',
            'active'=>'rankings',
            'type'=>$type,
            'rows'=>$rows,
            'availability'=>$availability,
            'visualAssets'=>$visualAssets->map(),
            'nameRenderer'=>$nameRenderer,
            'guildProfessional'=>$guildProfessional,
            'eventStats'=>$eventStats
        ]);
        exit;
    }

    if ($path === '/online') {
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'online',[
            'title'=>'Usuarios Online',
            'active'=>'online',
            'rows'=>$driver->onlineCharacters(100),
            'onlineCount'=>$driver->onlineCharacterCount(),
            'visualAssets'=>$visualAssets->map(),
            'nameRenderer'=>$nameRenderer
        ]);
        exit;
    }

    if ($path === '/downloads') {
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'downloads',[
            'title'=>'Descargas','active'=>'downloads',
            'downloads'=>array_values(array_filter($adminManager->downloadsList(), fn($d)=>(int)$d['is_active']===1))
        ]);
        exit;
    }

    if ($path === '/info') {
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
            'title'=>'Información','active'=>'info','eyebrow'=>'SERVIDOR','heading'=>'Información del Reino',
            'lead'=>'Omega MU está conectado directamente a los files MuEmu 0.97k Kayito.',
            'content'=>'<div class="info-grid">
                <div><span>Versión</span><strong>0.97k</strong></div>
                <div><span>Engine</span><strong>MuEmu Kayito</strong></div>
                <div><span>Database</span><strong>MuOnline97</strong></div>
                <div><span>CMS</span><strong>Omega MU 2.0</strong></div>
            </div><p class="muted-copy">Las rates, resets, comandos y reglas serán administrables mediante módulos del CMS para evitar valores hardcodeados.</p>'
        ]);
        exit;
    }

    if ($path === '/login') {
        if ($auth->check()) $redirect('/account');

        $error = null;
        $old = [];
        if ($method === 'POST') {
            $requireCsrf();
            $old['username'] = trim((string)($_POST['username'] ?? ''));
            try {
                if($hcaptcha->protects('login')){
                    $verified=$hcaptcha->verify(
                        (string)($_POST['h-captcha-response']??''),
                        $clientIp,
                        'login'
                    );
                    if(!$verified){
                        throw new RuntimeException('HCAPTCHA_FAILED');
                    }
                }

                $auth->login(
                    (string)($_POST['username'] ?? ''),
                    (string)($_POST['password'] ?? ''),
                    $clientIp
                );
                if ($adminAccess->isAdmin($auth->currentId())) {
                    $adminAccess->touchLogin((string)$auth->currentId());
                }
                $_SESSION['_flash_success'] = 'Sesión iniciada correctamente.';
                $redirect('/account');
            } catch (Throwable $e) {
                $code=$e->getMessage();
                if(str_starts_with($code,'HCAPTCHA_')){
                    $error=$hcaptcha->friendlyError($code,'login');
                }else{
                    $error=$code;
                }
            }
        }

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'login',[
            'title'=>'Mi Cuenta','active'=>'account','error'=>$error,'old'=>$old,
            'success'=>$flashSuccess,
            'hcaptchaEnabled'=>$hcaptcha->protects('login'),
            'hcaptchaSiteKey'=>$hcaptcha->siteKey()
        ]);
        exit;
    }

    if ($path === '/register') {
        if ($auth->check()) $redirect('/account');

        $error = null;
        $old = [];
        if ($method === 'POST') {
            $requireCsrf();
            $old = [
                'username'=>trim((string)($_POST['username'] ?? '')),
                'email'=>trim((string)($_POST['email'] ?? '')),
                'personal_id'=>trim((string)($_POST['personal_id'] ?? '')),
            ];
            try {
                if($hcaptcha->protects('register')){
                    $verified=$hcaptcha->verify(
                        (string)($_POST['h-captcha-response']??''),
                        $clientIp,
                        'register'
                    );
                    if(!$verified){
                        throw new RuntimeException('HCAPTCHA_FAILED');
                    }
                }

                $created = $auth->register($_POST, $clientIp);
                $_SESSION['_flash_success'] = 'Cuenta '.$created.' creada correctamente. Ya puedes iniciar sesión.';
                $redirect('/login');
            } catch (Throwable $e) {
                $code=$e->getMessage();
                if(str_starts_with($code,'HCAPTCHA_')){
                    $error=$hcaptcha->friendlyError($code,'register');
                }else{
                    $error=$code;
                }
            }
        }

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'register',[
            'title'=>'Crear Cuenta','active'=>'account','error'=>$error,'old'=>$old,'appName'=>$appName,
            'moduleAssets'=>$moduleRuntime->assetsFor('register'),
            'hcaptchaEnabled'=>$hcaptcha->protects('register'),
            'hcaptchaSiteKey'=>$hcaptcha->siteKey()
        ]);
        exit;
    }

    if ($path === '/logout' && $method === 'POST') {
        $requireCsrf();
        $auth->logout();
        $_SESSION['_flash_success'] = 'Sesión cerrada correctamente.';
        $redirect('/login');
    }

    if ($path === '/account') {
        if (!$auth->check()) $redirect('/login');
        $accountId = $auth->requireLogin();
        $account = $driver->account($accountId);
        if (!$account) {
            $auth->logout();
            $redirect('/login');
        }

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'account',[
            'title'=>'Mi Cuenta','active'=>'account',
            'account'=>$account,
            'characters'=>$driver->characters($accountId),
            'status'=>$driver->accountStatus($accountId),
            'success'=>$flashSuccess,'error'=>$flashError,
            'visualAssets'=>$visualAssets->map(),
            'moduleUserMenu'=>$moduleRuntime->userMenu(),
            'nameRenderer'=>$nameRenderer,
            'jewelBankAvailable'=>$myJewelBank->available()
        ]);
        exit;
    }

    if ($path === '/account/jewel-bank') {
        if(!$auth->check()) $redirect('/login');
        $accountId=$auth->requireLogin();

        if(!$myJewelBank->available()){
            http_response_code(404);
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
                'title'=>'Mi Jewel Bank','active'=>'account','eyebrow'=>'JEWEL BANK',
                'heading'=>'Jewel Bank no disponible',
                'lead'=>'La tabla CustomJewelBank no está instalada en esta base de datos.',
                'content'=>'<p><a class="btn btn-primary" href="/account">Volver a Mi Cuenta</a></p>'
            ]);
            exit;
        }

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'account_jewel_bank',[
            'title'=>'Mi Jewel Bank',
            'active'=>'account',
            'accountId'=>$accountId,
            'balance'=>$myJewelBank->balance($accountId),
            'history'=>$myJewelBank->history($accountId,50),
            'totals'=>$myJewelBank->totals($accountId),
            'recent'=>$myJewelBank->recentSummary($accountId),
            'ledgerAvailable'=>$myJewelBank->ledgerAvailable()
        ]);
        exit;
    }


    if ($path === '/account/social-profile') {
        if(!$auth->check()) $redirect('/login');
        $accountId=$auth->requireLogin();

        $error=$flashError;
        $success=$flashSuccess;

        if($method==='POST'){
            $requireCsrf();

            try{
                $socialProfiles->save($accountId,$_POST,$_FILES);
                $_SESSION['_flash_success']='Perfil social actualizado correctamente.';
                $redirect('/account/social-profile');
            }catch(Throwable $e){
                $error=$e->getMessage();
            }
        }

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'account_social_profile',[
            'title'=>'Mi Perfil Social',
            'active'=>'account',
            'profile'=>$socialProfiles->profile($accountId),
            'countries'=>$socialProfiles->countries(),
            'consentVersion'=>SocialProfileManager::CONSENT_VERSION,
            'success'=>$success,
            'error'=>$error
        ]);
        exit;
    }

    if (preg_match('#^/character/([A-Za-z0-9_-]{1,10})$#',$path,$pc)) {
        $char=$socialProfiles->publicCharacter($pc[1]);

        if(!$char){
            http_response_code(404);
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
                'title'=>'Personaje no encontrado',
                'active'=>'rankings',
                'eyebrow'=>'CHARACTER PROFILE',
                'heading'=>'Personaje no encontrado',
                'lead'=>'No existe un personaje público con ese nombre.',
                'content'=>'<p><a class="btn btn-primary" href="/rankings">Volver a rankings</a></p>'
            ]);
            exit;
        }

        $ownerProfile=$socialProfiles->publicOwnerProfileByAccount(
            (string)$char['AccountID']
        );

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'public_character_profile',[
            'title'=>(string)$char['Name'],
            'active'=>'rankings',
            'char'=>$char,
            'ownerProfile'=>$ownerProfile,
            'visualAssets'=>$visualAssets->map(),
            'nameRenderer'=>$nameRenderer
        ]);
        exit;
    }

    if (preg_match('#^/account/modules/([a-z0-9_-]{3,80})$#',$path,$um)) {
        if(!$auth->check()) $redirect('/login');
        $accountId=$auth->requireLogin();

        try {
            if($method==='POST') $requireCsrf();
            $result=$moduleRuntime->invoke($um[1],'user.page',[
                'account_id'=>$accountId,
                'method'=>$method,
                'query'=>$_GET,
                'post'=>$_POST,
                'csrf_token'=>Csrf::token(),
                'base_url'=>(string)($appConfig['url']??''),
            ]);
            if(!is_array($result) || empty($result['html'])){
                throw new RuntimeException('El módulo no ofrece una página de usuario.');
            }
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'module_user_page',[
                'title'=>(string)($result['title']??'Módulo'),
                'active'=>'account',
                'moduleHtml'=>(string)$result['html']
            ]);
        } catch(Throwable $e) {
            http_response_code(500);
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
                'title'=>'Módulo','active'=>'account','eyebrow'=>'MODULE',
                'heading'=>'No fue posible cargar el módulo',
                'lead'=>$e->getMessage(),
                'content'=>'<p><a class="btn btn-primary" href="/account">Volver a mi cuenta</a></p>'
            ]);
        }
        exit;
    }

    if ($path === '/account/password') {
        if (!$auth->check()) $redirect('/login');
        $accountId = $auth->requireLogin();
        $error = null;
        $success = $flashSuccess;

        if ($method === 'POST') {
            $requireCsrf();
            try {
                $auth->changePassword(
                    $accountId,
                    (string)($_POST['current_password'] ?? ''),
                    (string)($_POST['new_password'] ?? ''),
                    (string)($_POST['new_password_confirmation'] ?? ''),
                    $clientIp
                );
                $success = 'Contraseña actualizada correctamente.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'password',[
            'title'=>'Cambiar contraseña','active'=>'account','error'=>$error,'success'=>$success
        ]);
        exit;
    }

    if (preg_match('#^/account/character/([A-Za-z0-9_-]{1,10})$#',$path,$m)) {
        if (!$auth->check()) $redirect('/login');
        $accountId = $auth->requireLogin();
        $char = $driver->character($accountId,$m[1]);
        if (!$char) {
            http_response_code(404);
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
                'title'=>'Personaje no encontrado','active'=>'account','heading'=>'Personaje no encontrado',
                'lead'=>'Ese personaje no pertenece a tu cuenta.',
                'content'=>'<p><a class="btn btn-primary" href="/account">Volver</a></p>'
            ]);
            exit;
        }
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'character',[
            'title'=>$char['Name'],'active'=>'account','char'=>$char,
            'visualAssets'=>$visualAssets->map(),
            'nameRenderer'=>$nameRenderer
        ]);
        exit;
    }

    if (preg_match('#^/account/character/([A-Za-z0-9_-]{1,10})/(clearpk|unstuck|stats)$#',$path,$m) && $method === 'POST') {
        if (!$auth->check()) $redirect('/login');
        $requireCsrf();
        $accountId = $auth->requireLogin();
        $name = $m[1];
        $action = $m[2];

        if (!$driver->character($accountId,$name)) {
            $_SESSION['_flash_error'] = 'Personaje no encontrado.';
            $redirect('/account');
        }

        try {
            if ($action === 'clearpk') {
                $driver->clearPk($accountId,$name);
                $_SESSION['_flash_success'] = 'PK limpiado correctamente en '.$name.'.';
            } elseif ($action === 'unstuck') {
                $driver->unstuck($accountId,$name);
                $_SESSION['_flash_success'] = $name.' fue movido a Lorencia (125,125).';
            } else {
                $result = $driver->addStats($accountId,$name,$_POST);
                $_SESSION['_flash_success'] = 'Se distribuyeron '.$result['spent'].' puntos. Restantes: '.$result['remaining'].'.';
            }
        } catch (Throwable $e) {
            $_SESSION['_flash_error'] = $e->getMessage();
        }
        $redirect('/account');
    }

    if ($path === '/account/recovery') {
        echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
            'title'=>'Recuperar contraseña','active'=>'account','eyebrow'=>'RECUPERACIÓN',
            'heading'=>'Recuperar contraseña',
            'lead'=>'El sistema de recuperación por correo se habilitará junto al módulo SMTP.',
            'content'=>'<div class="notice-box">Por ahora, contacta al administrador si perdiste el acceso. No se muestran contraseñas almacenadas desde la web.</div>'
        ]);
        exit;
    }

    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        if (!$auth->check()) $redirect('/login');
        $accountId = $auth->requireLogin();
        try {
            $admin = $adminAccess->requireAdmin($accountId);
        } catch (Throwable $e) {
            http_response_code(403);
            echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
                'title'=>'Acceso denegado','active'=>'account','eyebrow'=>'ADMINCP',
                'heading'=>'Acceso denegado','lead'=>'Tu cuenta MU no está autorizada como administrador de Omega.',
                'content'=>'<div class="notice-box danger">Solo el ID administrador vinculado puede acceder.</div>'
            ]);
            exit;
        }

        $renderAdmin = function(string $title,string $active,string $template,array $data=[]) use($admin): void {
            $body = View::render($template,$data);
            echo View::render('admin_layout',[
                'admin'=>$admin,'adminTitle'=>$title,'activeAdmin'=>$active,'adminBody'=>$body
            ]);
        };

        if ($path === '/admin/name-effects-renderer-test') {
            $character=trim((string)($_GET['character']??''));
            $diag=$character!=='' ? $nameRenderer->diagnose($character) : null;

            $e=[\Omega\Core\Security::class,'e'];
            ob_start();
            ?>
            <div class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <span class="eyebrow">DIAGNÓSTICO</span>
                        <h2>Name Effects Renderer</h2>
                    </div>
                </div>

                <form method="get" action="/admin/name-effects-renderer-test" class="omega-form">
                    <label>Personaje
                        <input name="character" maxlength="10" required value="<?= $e($character) ?>" placeholder="Azzlaer">
                    </label>
                    <button class="btn btn-primary" type="submit">Probar renderer</button>
                </form>

                <?php if(is_array($diag)): ?>
                    <div style="margin-top:18px">
                        <p><strong>Módulo activo:</strong>
                            <?= !empty($diag['module_enabled'])?'SÍ':'NO' ?>
                        </p>

                        <p><strong>Asignación encontrada:</strong>
                            <?= !empty($diag['assignment'])?'SÍ':'NO' ?>
                        </p>

                        <?php if(!empty($diag['assignment'])): ?>
                            <pre style="white-space:pre-wrap"><?= $e(json_encode($diag['assignment'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre>
                        <?php endif; ?>

                        <p><strong>Hook devolvió efecto:</strong>
                            <?= !empty($diag['hook_changed_name'])?'SÍ':'NO' ?>
                        </p>

                        <p><strong>Resultado hook:</strong><br>
                            <?= is_string($diag['hook_result'])?$diag['hook_result']:'NULL' ?>
                        </p>

                        <p><strong>Resultado bridge directo:</strong><br>
                            <?= is_string($diag['direct_result'])?$diag['direct_result']:'NULL' ?>
                        </p>

                        <?php if(!empty($diag['errors'])): ?>
                            <div class="notice-box danger">
                                <?= $e(implode(' | ',$diag['errors'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $body=ob_get_clean();

            echo View::render('admin_layout',[
                'admin'=>$admin,
                'adminTitle'=>'Name Effects Renderer',
                'activeAdmin'=>'modules',
                'adminBody'=>$body
            ]);
            exit;
        }

        if ($path === '/admin') {
            $newsRow = $cmsDb->fetchOne("SELECT COUNT(*) AS total FROM cms_news WHERE status='published'");
            $downloadsRow = $cmsDb->fetchOne("SELECT COUNT(*) AS total FROM cms_downloads WHERE is_active=1");
            $renderAdmin('Dashboard','dashboard','admin_dashboard',[
                'stats'=>$driver->statistics(),
                'newsCount'=>(int)($newsRow['total']??0),
                'downloadsCount'=>(int)($downloadsRow['total']??0),
            ]);
            exit;
        }

        if ($path === '/admin/news') {
            $renderAdmin('Noticias','news','admin_news',['rows'=>$adminManager->newsList()]);
            exit;
        }
        if ($path === '/admin/news/new') {
            $renderAdmin('Nueva noticia','news','admin_news_form',['item'=>[]]);
            exit;
        }
        if ($path === '/admin/news/edit') {
            $item = $adminManager->newsFind((int)($_GET['id']??0));
            if(!$item) { http_response_code(404); $redirect('/admin/news'); }
            $renderAdmin('Editar noticia','news','admin_news_form',['item'=>$item]);
            exit;
        }
        if ($path === '/admin/news/save' && $method === 'POST') {
            $requireCsrf();
            try { $adminManager->newsSave($_POST); $_SESSION['_flash_success']='Noticia guardada.'; }
            catch(Throwable $e){ $_SESSION['_flash_error']=$e->getMessage(); }
            $redirect('/admin/news');
        }
        if ($path === '/admin/news/delete' && $method === 'POST') {
            $requireCsrf(); $adminManager->newsDelete((int)($_POST['id']??0)); $redirect('/admin/news');
        }


        if ($path === '/admin/guides') {
            $renderAdmin('Guías','guides','admin_guides',['rows'=>$guideManager->adminList()]);
            exit;
        }
        if ($path === '/admin/guides/new') {
            $renderAdmin('Nueva guía','guides','admin_guide_form',['item'=>[]]);
            exit;
        }
        if ($path === '/admin/guides/edit') {
            $item=$guideManager->find((int)($_GET['id']??0));
            if(!$item) $redirect('/admin/guides');
            $renderAdmin('Editar guía','guides','admin_guide_form',['item'=>$item]);
            exit;
        }
        if ($path === '/admin/guides/save' && $method === 'POST') {
            $requireCsrf();
            try {
                $guideManager->save($_POST,$_FILES,$accountId);
                $_SESSION['_flash_success']='Guía guardada correctamente.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/guides');
        }
        if ($path === '/admin/guides/delete' && $method === 'POST') {
            $requireCsrf();
            try {
                $guideManager->delete((int)($_POST['id']??0));
                $_SESSION['_flash_success']='Guía eliminada.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/guides');
        }

        if ($path === '/admin/monetization') {
            try { $adminAccess->requireSuperAdmin($accountId); }
            catch(Throwable $e) {
                http_response_code(403);
                $renderAdmin('Acceso denegado','dashboard','simple',[
                    'heading'=>'Solo SuperAdmin',
                    'lead'=>'Google & Monetization Center PRO administra scripts, publicidad y privacidad global.'
                ]);
                exit;
            }
            $editId=(int)($_GET['edit']??0);
            $renderAdmin('Google & Monetization','monetization','admin_monetization',[
                'settings'=>$monetization->settings(),
                'rows'=>$monetization->placements(),
                'item'=>$editId>0 ? ($monetization->findPlacement($editId)??[]) : [],
                'success'=>$flashSuccess,
                'error'=>$flashError
            ]);
            exit;
        }

        if ($path === '/admin/monetization/settings' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try {
                $monetization->saveSettings($_POST);
                $_SESSION['_flash_success']='Configuración de Google y monetización actualizada.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/monetization');
        }

        if ($path === '/admin/monetization/placement/save' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try {
                $monetization->savePlacement($_POST);
                $_SESSION['_flash_success']='Bloque publicitario guardado.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/monetization');
        }

        if ($path === '/admin/monetization/placement/delete' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try {
                $monetization->deletePlacement((int)($_POST['id']??0));
                $_SESSION['_flash_success']='Bloque publicitario eliminado.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/monetization');
        }

        if ($path === '/admin/changelog') {
            $renderAdmin('Changelog','changelog','admin_changelog',[
                'rows'=>$changelog->listAdmin()
            ]);
            exit;
        }

        if ($path === '/admin/changelog/new') {
            $renderAdmin('Nueva actualización','changelog','admin_changelog_form',[
                'item'=>[]
            ]);
            exit;
        }

        if ($path === '/admin/changelog/edit') {
            $item=$changelog->find((int)($_GET['id']??0));
            if(!$item) $redirect('/admin/changelog');
            $renderAdmin('Editar actualización','changelog','admin_changelog_form',[
                'item'=>$item
            ]);
            exit;
        }

        if ($path === '/admin/changelog/save' && $method === 'POST') {
            $requireCsrf();
            try {
                $changelog->save($_POST);
                $_SESSION['_flash_success']='Actualización guardada.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/changelog');
        }

        if ($path === '/admin/changelog/delete' && $method === 'POST') {
            $requireCsrf();
            $changelog->delete((int)($_POST['id']??0));
            $redirect('/admin/changelog');
        }

        if ($path === '/admin/downloads') {
            $renderAdmin('Descargas','downloads','admin_downloads',['rows'=>$adminManager->downloadsList()]);
            exit;
        }
        if ($path === '/admin/downloads/new') {
            $renderAdmin('Nueva descarga','downloads','admin_download_form',['item'=>[]]);
            exit;
        }
        if ($path === '/admin/downloads/edit') {
            $item=$adminManager->downloadFind((int)($_GET['id']??0));
            if(!$item) $redirect('/admin/downloads');
            $renderAdmin('Editar descarga','downloads','admin_download_form',['item'=>$item]);
            exit;
        }
        if ($path === '/admin/downloads/save' && $method === 'POST') {
            $requireCsrf();
            try { $adminManager->downloadSave($_POST); } catch(Throwable $e){ $_SESSION['_flash_error']=$e->getMessage(); }
            $redirect('/admin/downloads');
        }
        if ($path === '/admin/downloads/delete' && $method === 'POST') {
            $requireCsrf(); $adminManager->downloadDelete((int)($_POST['id']??0)); $redirect('/admin/downloads');
        }

        if ($path === '/admin/accounts') {
            $q=(string)($_GET['q']??'');
            $renderAdmin('Cuentas','accounts','admin_accounts',['rows'=>$adminManager->accounts($q),'q'=>$q]);
            exit;
        }

        if ($path === '/admin/ranking-exclusions') {
            $q=(string)($_GET['q']??'');
            $renderAdmin('Excluir de Rankings','ranking-exclusions','admin_ranking_exclusions',[
                'rows'=>$rankingExclusions->all($q),
                'q'=>$q,
                'activeCount'=>$rankingExclusions->activeCount(),
                'success'=>$flashSuccess,
                'error'=>$flashError
            ]);
            exit;
        }

        if ($path === '/admin/ranking-exclusions/add' && $method === 'POST') {
            $requireCsrf();

            try {
                $result=$rankingExclusions->addMany(
                    (string)($_POST['characters']??''),
                    (string)($_POST['reason']??''),
                    $accountId
                );

                $parts=[];
                if($result['added']) $parts[]='Agregados: '.implode(', ',$result['added']);
                if($result['updated']) $parts[]='Reactivados: '.implode(', ',$result['updated']);
                if($result['notFound']) $parts[]='No encontrados: '.implode(', ',$result['notFound']);
                if($result['invalid']) $parts[]='Inválidos: '.implode(', ',$result['invalid']);

                $_SESSION['_flash_success']=$parts
                    ? implode(' · ',$parts)
                    : 'No hubo cambios.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }

            $redirect('/admin/ranking-exclusions');
        }

        if ($path === '/admin/ranking-exclusions/update' && $method === 'POST') {
            $requireCsrf();

            try {
                $rankingExclusions->update(
                    (int)($_POST['id']??0),
                    (string)($_POST['active']??'0')==='1',
                    (string)($_POST['reason']??'')
                );
                $_SESSION['_flash_success']='Exclusión actualizada.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }

            $redirect('/admin/ranking-exclusions');
        }

        if ($path === '/admin/ranking-exclusions/delete' && $method === 'POST') {
            $requireCsrf();

            try {
                $rankingExclusions->delete((int)($_POST['id']??0));
                $_SESSION['_flash_success']='Personaje eliminado de la lista de exclusión.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }

            $redirect('/admin/ranking-exclusions');
        }



        if ($path === '/admin/database-features') {
            $renderAdmin('Database Feature Center','database-features','admin_database_features',[
                'features'=>$databaseFeatures->featureSummary(),
                'stats'=>$databaseFeatures->statistics()
            ]);
            exit;
        }

        if (preg_match('#^/admin/database-features/([a-z0-9-]+)$#',$path,$m)) {
            $slug=(string)$m[1];
            $feature=$databaseFeatures->feature($slug);

            if(!$feature){
                http_response_code(404);
                $renderAdmin('Feature no encontrado','database-features','simple',[
                    'heading'=>'Feature no encontrado',
                    'lead'=>'El sistema solicitado no está registrado en Database Feature Center.'
                ]);
                exit;
            }

            $renderAdmin($feature['title'],'database-features','admin_database_feature_detail',[
                'feature'=>$feature,
                'tables'=>$databaseFeatures->featureTables($slug)
            ]);
            exit;
        }

        if (preg_match('#^/admin/database-features/([a-z0-9-]+)/table/([A-Za-z0-9_]+)$#',$path,$m)) {
            $slug=(string)$m[1];
            $table=(string)$m[2];
            $feature=$databaseFeatures->feature($slug);

            if(!$feature || !in_array($table,$feature['found'],true)){
                http_response_code(404);
                $renderAdmin('Tabla no encontrada','database-features','simple',[
                    'heading'=>'Tabla no disponible',
                    'lead'=>'La tabla solicitada no pertenece al feature seleccionado o no está instalada.'
                ]);
                exit;
            }

            try{
                $tableInfo=$databaseFeatures->tableInfo($table);
                $preview=$databaseFeatures->preview($table,25);

                $renderAdmin($table,'database-features','admin_database_table',[
                    'feature'=>$feature,
                    'tableInfo'=>$tableInfo,
                    'preview'=>$preview
                ]);
            }catch(Throwable $e){
                $renderAdmin('Error de lectura','database-features','simple',[
                    'heading'=>'No se pudo inspeccionar la tabla',
                    'lead'=>$e->getMessage()
                ]);
            }
            exit;
        }

        if ($path === '/admin/access') {
            try {
                $adminAccess->requireSuperAdmin($accountId);
            } catch (Throwable) {
                http_response_code(403);
                $renderAdmin('Acceso Admin','access','simple',[
                    'heading'=>'Acceso restringido',
                    'lead'=>'Solo un Super Administrador puede administrar la lista blanca.'
                ]);
                exit;
            }

            $renderAdmin('Acceso Admin','access','admin_access',[
                'rows'=>$adminAccess->whitelist(),
                'currentAccount'=>$accountId,
                'success'=>$flashSuccess,
                'error'=>$flashError
            ]);
            exit;
        }

        if ($path === '/admin/access/add' && $method === 'POST') {
            $requireCsrf();
            try {
                $adminAccess->addToWhitelist(
                    $accountId,
                    (string)($_POST['account_id']??''),
                    (string)($_POST['role']??'admin')
                );
                $_SESSION['_flash_success']='Cuenta agregada/autorizada correctamente.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/access');
        }

        if ($path === '/admin/access/update' && $method === 'POST') {
            $requireCsrf();
            try {
                $adminAccess->updateWhitelistEntry(
                    $accountId,
                    (string)($_POST['account_id']??''),
                    (string)($_POST['role']??'admin'),
                    (string)($_POST['active']??'0')==='1'
                );
                $_SESSION['_flash_success']='Permisos actualizados correctamente.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/access');
        }

        if ($path === '/admin/access/remove' && $method === 'POST') {
            $requireCsrf();
            try {
                $adminAccess->removeFromWhitelist(
                    $accountId,
                    (string)($_POST['account_id']??'')
                );
                $_SESSION['_flash_success']='Acceso administrativo eliminado.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/access');
        }

        if ($path === '/admin/accounts/block' && $method === 'POST') {
            $requireCsrf();
            $adminManager->setAccountBlock((string)($_POST['account_id']??''),(string)($_POST['blocked']??'0')==='1');
            $redirect('/admin/accounts');
        }

        if ($path === '/admin/modules') {
            $renderAdmin('Módulos','modules','admin_modules',[
                'rows'=>$modulePackages->installedModules($modules),
                'error'=>$flashError,'success'=>$flashSuccess
            ]);
            exit;
        }
        if ($path === '/admin/modules/install' && $method === 'POST') {
            $requireCsrf();
            try {
                $result=$modulePackages->installUploaded($_FILES['module_zip']??[]);
                $_SESSION['_flash_success']='Módulo '.$result['manifest']['name'].' v'.$result['manifest']['version'].' instalado correctamente.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/modules');
        }
        if ($path === '/admin/modules/toggle' && $method === 'POST') {
            $requireCsrf();
            $adminManager->setModuleEnabled(
                (string)($_POST['slug']??''),
                (string)($_POST['name']??''),
                (string)($_POST['version']??''),
                (string)($_POST['enabled']??'0')==='1'
            );
            $redirect('/admin/modules');
        }
        if ($path === '/admin/modules/uninstall' && $method === 'POST') {
            $requireCsrf();
            try {
                $modulePackages->uninstall((string)($_POST['slug']??''));
                $_SESSION['_flash_success']='Módulo desinstalado.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/modules');
        }
        if ($path === '/admin/modules/action' && $method === 'POST') {
            $requireCsrf();
            $slug=(string)($_POST['slug']??'');
            $action=(string)($_POST['module_action']??'');
            try {
                $result=$moduleRuntime->invoke($slug,'admin.action',[
                    'action'=>$action,
                    'admin_account'=>$accountId,
                    'data'=>$_POST,
                    'csrf_token'=>Csrf::token()
                ]);
                $_SESSION['_flash_success']=is_string($result)&&$result!==''?$result:'Acción del módulo ejecutada correctamente.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/modules/'.rawurlencode($slug));
        }
        if ($path === '/admin/modules/config/save' && $method === 'POST') {
            $requireCsrf();
            $slug=(string)($_POST['slug']??'');
            try {
                $modulePackages->saveConfig($slug,$_POST);
                $_SESSION['_flash_success']='Configuración guardada.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/modules/'.rawurlencode($slug));
        }
        if (preg_match('#^/admin/modules/([a-z0-9_-]{3,80})$#',$path,$mm)) {
            $slug=$mm[1];
            $rows=$modulePackages->installedModules($modules);
            $module=null;
            foreach($rows as $row){ if($row['slug']===$slug){$module=$row;break;} }
            if(!$module){ $redirect('/admin/modules'); }
            $customAdmin='';
            try {
                $adminResult=$moduleRuntime->invoke($slug,'admin.panel',[
                    'admin_account'=>$accountId,
                    'csrf_token'=>Csrf::token()
                ]);
                if(is_string($adminResult)) $customAdmin=$adminResult;
            } catch(Throwable) {}
            $renderAdmin($module['name'],'modules','admin_module_detail',[
                'module'=>$module,
                'config'=>$modulePackages->config($slug),
                'files'=>$modulePackages->files($slug),
                'customAdminHtml'=>$customAdmin,
            ]);
            exit;
        }

        if ($path === '/admin/design-studio') {
            try{
                $adminAccess->requireSuperAdmin($accountId);
            }catch(Throwable $e){
                http_response_code(403);
                $renderAdmin('Acceso denegado','dashboard','simple',[
                    'heading'=>'Solo SuperAdmin',
                    'lead'=>'Visual Design Studio modifica la apariencia pública global.',
                    'content'=>'<div class="notice-box danger">Se requiere rol superadmin.</div>'
                ]);
                exit;
            }

            $renderAdmin('Visual Design Studio','design-studio','admin_design_studio',[
                'settings'=>$designStudio->settings(),
                'sections'=>$designStudio->sections(),
                'assets'=>$designStudio->assets(),
                'presets'=>$designStudio->presets(),
                'error'=>$flashError,
                'success'=>$flashSuccess
            ]);
            exit;
        }

        if ($path === '/admin/design-studio/settings' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try{
                $designStudio->saveSettings($_POST);
                $_SESSION['_flash_success']='Apariencia global actualizada.';
            }catch(Throwable $e){
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/design-studio#appearance');
        }

        if ($path === '/admin/design-studio/section' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try{
                $designStudio->saveSection($_POST);
                $_SESSION['_flash_success']='Diseño de sección actualizado.';
            }catch(Throwable $e){
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/design-studio#sections');
        }

        if ($path === '/admin/design-studio/upload' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try{
                $assetId=$designStudio->upload(
                    $_FILES['media']??[],
                    (string)($_POST['label']??''),
                    $accountId
                );

                if(!empty($_POST['use_as_global_background']) && $assetId>0){
                    $designStudio->useAsGlobalBackground($assetId);
                    $_SESSION['_flash_success']='Archivo subido y aplicado como nuevo fondo global.';
                }else{
                    $_SESSION['_flash_success']='Archivo agregado a la Biblioteca Multimedia.';
                }
            }catch(Throwable $e){
                $message=$e->getMessage();
                $friendly=[
                    'MEDIA_UPLOAD_FAILED_1'=>'El archivo supera el límite upload_max_filesize configurado en PHP.',
                    'MEDIA_UPLOAD_FAILED_2'=>'El archivo supera el tamaño máximo permitido por el formulario.',
                    'MEDIA_UPLOAD_FAILED_3'=>'La subida quedó incompleta. Inténtalo nuevamente.',
                    'MEDIA_UPLOAD_FAILED_4'=>'No se recibió ningún archivo.',
                    'MEDIA_UPLOAD_FAILED_6'=>'PHP no encuentra su carpeta temporal de uploads.',
                    'MEDIA_UPLOAD_FAILED_7'=>'PHP no pudo escribir el archivo en disco.',
                    'MEDIA_UPLOAD_FAILED_8'=>'Una extensión de PHP detuvo la subida.',
                    'MEDIA_TYPE_NOT_ALLOWED'=>'Formato no permitido. Usa JPG, PNG, WEBP, GIF, MP4 o WEBM.',
                    'IMAGE_TOO_LARGE'=>'La imagen supera 12 MB.',
                    'VIDEO_TOO_LARGE'=>'El video supera 60 MB.',
                    'IMAGE_DIMENSIONS_INVALID'=>'La imagen debe medir entre 320×120 y 7680×4320 píxeles.',
                ];
                $_SESSION['_flash_error']=$friendly[$message]??$message;
            }
            $redirect('/admin/design-studio#library');
        }

        if ($path === '/admin/design-studio/asset/use-global' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try{
                $designStudio->useAsGlobalBackground((int)($_POST['asset_id']??0));
                $_SESSION['_flash_success']='Fondo global actualizado correctamente.';
            }catch(Throwable $e){
                $_SESSION['_flash_error']='No se pudo aplicar la imagen: '.$e->getMessage();
            }
            $redirect('/admin/design-studio#library');
        }

        if ($path === '/admin/design-studio/asset/delete' && $method === 'POST') {
            try{
                $adminAccess->requireSuperAdmin($accountId);
                $requireCsrf();

                $designStudio->deleteAsset((int)($_POST['asset_id']??0));
                $_SESSION['_flash_success']='🗑 Archivo eliminado correctamente de la Biblioteca Multimedia.';
            }catch(Throwable $e){
                $_SESSION['_flash_error']='No se pudo eliminar el archivo: '.$e->getMessage();
            }

            $redirect('/admin/design-studio#library');
        }

        if ($path === '/admin/design-studio/preset/save' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try{
                $designStudio->savePreset(
                    (string)($_POST['preset_name']??''),
                    $accountId
                );
                $_SESSION['_flash_success']='Preset guardado correctamente.';
            }catch(Throwable $e){
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/design-studio#presets');
        }

        if ($path === '/admin/design-studio/preset/apply' && $method === 'POST') {
            $adminAccess->requireSuperAdmin($accountId);
            $requireCsrf();
            try{
                $designStudio->applyPreset((int)($_POST['preset_id']??0));
                $_SESSION['_flash_success']='Preset aplicado al sitio.';
            }catch(Throwable $e){
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/design-studio#presets');
        }

        if ($path === '/admin/visual-assets') {
            $renderAdmin('Iconos & Variables','visuals','admin_visual_assets',[
                'rows'=>$visualAssets->all(),
                'error'=>$flashError,
                'success'=>$flashSuccess
            ]);
            exit;
        }

        if ($path === '/admin/visual-assets/upload' && $method === 'POST') {
            $requireCsrf();
            try {
                $visualAssets->upload(
                    (string)($_POST['asset_key']??''),
                    $_FILES['image']??[]
                );
                $_SESSION['_flash_success']='Imagen actualizada correctamente.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/visual-assets');
        }

        if ($path === '/admin/visual-assets/url' && $method === 'POST') {
            $requireCsrf();
            try {
                $visualAssets->saveUrl(
                    (string)($_POST['asset_key']??''),
                    (string)($_POST['image_url']??'')
                );
                $_SESSION['_flash_success']='Asociación guardada.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/visual-assets');
        }

        if ($path === '/admin/visual-assets/reset' && $method === 'POST') {
            $requireCsrf();
            try {
                $visualAssets->reset((string)($_POST['asset_key']??''));
                $_SESSION['_flash_success']='Icono restaurado.';
            } catch(Throwable $e) {
                $_SESSION['_flash_error']=$e->getMessage();
            }
            $redirect('/admin/visual-assets');
        }

        if ($path === '/admin/settings') {
            if ($method === 'POST') {
                $requireCsrf();
                $_POST['maintenance_mode'] = !empty($_POST['maintenance_mode']) ? '1' : '0';
                $adminManager->saveSettings($_POST);
                $redirect('/admin/settings');
            }
            $renderAdmin('Configuración','settings','admin_settings',[
                'settings'=>$adminManager->settings(),
                'hcaptchaDiagnostic'=>$hcaptcha->diagnostic()
            ]);
            exit;
        }

        http_response_code(404);
        $renderAdmin('No encontrado','dashboard','simple',[
            'heading'=>'Módulo no encontrado','lead'=>'La sección solicitada no existe.','content'=>''
        ]);
        exit;
    }

    http_response_code(404);
    echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
        'title'=>'404','active'=>'','eyebrow'=>'404','heading'=>'Página no encontrada',
        'lead'=>'La ruta solicitada no existe.',
        'content'=>'<p><a class="btn btn-primary" href="/">Volver al inicio</a></p>'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $debug = Env::bool('APP_DEBUG', false);
    $detail = $debug ? '<pre>'.Security::e($e->getMessage()).'</pre>' : '';
    echo renderPage($root,$appName,$driver,$auth,$adminAccess,'simple',[
        'title'=>'Error','active'=>'','eyebrow'=>'OMEGA CMS','heading'=>'No fue posible cargar esta sección',
        'lead'=>'El CMS encontró un problema al consultar los datos.',
        'content'=>'<div class="notice-box danger">Revisa la configuración del CMS y la conexión a la base de datos.</div>'.$detail
    ]);
}
