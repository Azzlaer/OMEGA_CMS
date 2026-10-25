<?php
declare(strict_types=1);

require_once __DIR__.'/StaffProfessionalService.php';

return static function(string $hook,array $payload,array $config,array $context): mixed {
    /** @var \Omega\Core\Database $cmsDb */
    $cmsDb=$context['cms_db'];
    /** @var \Omega\Core\Database $muDb */
    $muDb=$context['mu_db'];

    $service=new OmegaStaffProfessionalService($cmsDb,$muDb);
    $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');

    if($hook==='site.head'){
        return
            '<link rel="stylesheet" href="/module-assets/staff-profesional/assets/css/staff.css?v=200">'.
            '<script defer src="/module-assets/staff-profesional/assets/js/staff.js?v=200"></script>';
    }

    if($hook==='admin.action'){
        $action=(string)($payload['action']??'');
        $data=(array)($payload['data']??[]);

        if($action==='save_settings'){
            $service->saveSettings($data);
            return 'Configuración del Staff guardada.';
        }
        if($action==='member_add'){
            $service->add($data);
            return 'Miembro del Staff agregado.';
        }
        if($action==='member_save'){
            $service->update((int)($data['StaffId']??0),$data);
            return 'Miembro del Staff actualizado.';
        }
        if($action==='member_toggle'){
            $service->toggle((int)($data['StaffId']??0));
            return 'Estado del miembro actualizado.';
        }
        if($action==='member_delete'){
            $service->delete((int)($data['StaffId']??0));
            return 'Miembro eliminado del Staff.';
        }
        if($action==='runtime_check'){
            return 'Runtime Staff Profesional v2.0.0 para OmegaCMS activo.';
        }
        if($action==='diagnostics'){
            $d=$service->diagnostics();
            $parts=[];
            foreach($d as $label=>$ok){
                $parts[]=$label.': '.($ok?'OK':'FALTA');
            }
            return implode(' | ',$parts);
        }

        return null;
    }

    if($hook==='admin.panel'){
        $s=$service->settings();
        $members=$service->members(false);
        $characters=$service->characters();
        $token=$e((string)($payload['csrf_token']??''));

        $checked=static fn(string $key,bool $default=false):string =>
            (($s[$key]??($default?'1':'0'))==='1')?' checked':'';

        $memberForm=static function(array $m,bool $new=false) use($token,$e,$characters): string {
            ob_start(); ?>
<form method="post" action="/admin/modules/action" class="staffadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="staff-profesional">
<input type="hidden" name="module_action" value="<?= $new?'member_add':'member_save' ?>">
<?php if(!$new): ?><input type="hidden" name="StaffId" value="<?= (int)$m['StaffId'] ?>"><?php endif; ?>

<div class="staffadm-two">
<label>Personaje
<select name="CharacterName" required>
<option value="">Seleccionar personaje</option>
<?php foreach($characters as $ch): ?>
<option value="<?= $e((string)$ch['Name']) ?>" <?= (($m['CharacterName']??'')===$ch['Name']?'selected':'') ?>>
<?= $e((string)$ch['Name']) ?> · <?= $e(OmegaStaffProfessionalService::className((int)$ch['Class'])) ?> · Lv. <?= (int)$ch['cLevel'] ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Rango<input name="RankName" maxlength="80" value="<?= $e((string)($m['RankName']??'Game Master')) ?>"></label>
<label>Color rango<input type="color" name="RankColor" value="<?= $e((string)($m['RankColor']??'#d4af37')) ?>"></label>
<label>Departamento<input name="Department" maxlength="100" value="<?= $e((string)($m['Department']??'')) ?>" placeholder="Game Masters"></label>
</div>

<label>Descripción corta<input name="ShortDescription" maxlength="255" value="<?= $e((string)($m['ShortDescription']??'')) ?>"></label>
<label>Biografía<textarea name="Biography" rows="4"><?= $e((string)($m['Biography']??'')) ?></textarea></label>
<label>Avatar URL / asset<input name="AvatarUrl" maxlength="500" value="<?= $e((string)($m['AvatarUrl']??'')) ?>" placeholder="https://... o assets/images/..."></label>

<div class="staffadm-three">
<label>País<input name="Country" value="<?= $e((string)($m['Country']??'')) ?>"></label>
<label>Idiomas<input name="Languages" value="<?= $e((string)($m['Languages']??'')) ?>"></label>
<label>Zona horaria<input name="Timezone" value="<?= $e((string)($m['Timezone']??'')) ?>"></label>
<label>Horario<input name="Schedule" value="<?= $e((string)($m['Schedule']??'')) ?>"></label>
<label>Estado público
<select name="PublicStatus">
<?php foreach(['available'=>'Disponible','busy'=>'Ocupado','away'=>'Ausente','offline'=>'Fuera de servicio'] as $v=>$label): ?>
<option value="<?= $v ?>" <?= (($m['PublicStatus']??'available')===$v?'selected':'') ?>><?= $label ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Orden<input type="number" min="0" max="9999" name="SortOrder" value="<?= (int)($m['SortOrder']??0) ?>"></label>
</div>

<h4>Contacto</h4>
<div class="staffadm-two">
<label>Teléfono<input name="ContactPhone" value="<?= $e((string)($m['ContactPhone']??'')) ?>"></label>
<label>WhatsApp<input name="ContactWhatsapp" value="<?= $e((string)($m['ContactWhatsapp']??'')) ?>"></label>
<label>Discord<input name="ContactDiscord" value="<?= $e((string)($m['ContactDiscord']??'')) ?>"></label>
<label>Email<input type="email" name="ContactEmail" value="<?= $e((string)($m['ContactEmail']??'')) ?>"></label>
<label>Web / Perfil<input name="ContactWebsite" value="<?= $e((string)($m['ContactWebsite']??'')) ?>"></label>
</div>

<div class="staffadm-checks">
<?php foreach([
'ShowPhone'=>'Mostrar teléfono',
'ShowWhatsapp'=>'Mostrar WhatsApp',
'ShowDiscord'=>'Mostrar Discord',
'ShowEmail'=>'Mostrar email',
'ShowStats'=>'Mostrar estadísticas',
'ShowGuild'=>'Mostrar clan',
'ShowOnline'=>'Mostrar estado online',
'Featured'=>'Destacado',
'Active'=>'Activo',
] as $key=>$label): ?>
<label><input type="checkbox" name="<?= $key ?>" value="1" <?= !empty($m[$key])||($new&&in_array($key,['ShowDiscord','ShowStats','ShowGuild','ShowOnline','Active'],true))?'checked':'' ?>> <?= $label ?></label>
<?php endforeach; ?>
</div>

<button class="btn btn-primary" type="submit"><?= $new?'Agregar miembro':'Guardar cambios' ?></button>
</form>
            <?php return (string)ob_get_clean();
        };

        ob_start(); ?>
<style>
.staffadm-layout{display:grid;grid-template-columns:390px 1fr;gap:14px}
.staffadm-box{background:#11171e;border:1px solid #293440;border-radius:10px;padding:15px}
.staffadm-box h3{font:20px Georgia,serif;color:#e1bc59;margin:0 0 12px}
.staffadm-form{display:grid;gap:9px}
.staffadm-form label{font-size:10px;color:#aab5c0}
.staffadm-form input,.staffadm-form select,.staffadm-form textarea{width:100%;margin-top:4px;padding:8px;background:#0a1016;color:#e7edf3;border:1px solid #344153;border-radius:6px}
.staffadm-two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.staffadm-three{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.staffadm-checks{display:grid;grid-template-columns:repeat(3,1fr);gap:5px}
.staffadm-checks label{display:flex;gap:6px;align-items:center}
.staffadm-checks input{width:auto}
.staffadm-member{padding:12px;border:1px solid #2d3945;border-radius:8px;background:#0d1319;margin-bottom:10px}
.staffadm-member summary{cursor:pointer;color:#e7c267;font-weight:700}
.staffadm-actions{display:flex;gap:6px;margin-top:8px}
.staffadm-actions form{display:inline}
.staffadm-help{font-size:10px;color:#81909e;line-height:1.5}
@media(max-width:950px){.staffadm-layout{grid-template-columns:1fr}.staffadm-two,.staffadm-three,.staffadm-checks{grid-template-columns:1fr}}
</style>

<div class="staffadm-layout">
<section class="staffadm-box">
<h3>🛡️ Staff Profesional</h3>

<form method="post" action="/admin/modules/action" class="staffadm-form">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="staff-profesional">
<input type="hidden" name="module_action" value="save_settings">

<label><input type="checkbox" name="enabled" value="1" <?= $checked('enabled',true) ?>> Módulo habilitado</label>
<label>Título<input name="title" value="<?= $e((string)$s['title']) ?>"></label>
<label>Subtítulo<textarea name="subtitle" rows="3"><?= $e((string)$s['subtitle']) ?></textarea></label>

<label><input type="checkbox" name="show_warning" value="1" <?= $checked('show_warning',true) ?>> Mostrar advertencia</label>
<label>Título advertencia<input name="warning_title" value="<?= $e((string)$s['warning_title']) ?>"></label>
<label>Texto advertencia<textarea name="warning_text" rows="4"><?= $e((string)$s['warning_text']) ?></textarea></label>

<label>Color principal<input type="color" name="accent_color" value="<?= $e((string)$s['accent_color']) ?>"></label>
<label>Imagen fondo<input name="background_image" value="<?= $e((string)$s['background_image']) ?>"></label>
<label>Imagen cabecera<input name="header_image" value="<?= $e((string)$s['header_image']) ?>"></label>
<label>Avatar por defecto<input name="default_avatar" value="<?= $e((string)$s['default_avatar']) ?>"></label>

<label>Columnas<input type="number" min="1" max="4" name="card_columns" value="<?= (int)$s['card_columns'] ?>"></label>

<div class="staffadm-checks">
<?php foreach([
'show_search'=>'Buscar',
'show_rank_filter'=>'Filtro rango',
'show_department_filter'=>'Filtro área',
'show_character_class'=>'Clase',
'show_level'=>'Nivel',
'show_resets'=>'Resets',
'show_master_resets'=>'Grand Resets',
'show_pk'=>'PK',
'show_map'=>'Mapa',
] as $key=>$label): ?>
<label><input type="checkbox" name="<?= $key ?>" value="1" <?= $checked($key) ?>> <?= $label ?></label>
<?php endforeach; ?>
</div>

<label><input type="checkbox" name="show_empty_slots" value="1" <?= $checked('show_empty_slots') ?>> Mostrar cargos vacantes</label>
<label>Cantidad vacantes<input type="number" min="0" max="12" name="empty_slots_count" value="<?= (int)$s['empty_slots_count'] ?>"></label>
<label>Título vacante<input name="empty_slot_title" value="<?= $e((string)$s['empty_slot_title']) ?>"></label>
<label>Texto vacante<textarea name="empty_slot_text" rows="3"><?= $e((string)$s['empty_slot_text']) ?></textarea></label>

<label>Aviso de contacto<textarea name="contact_notice" rows="3"><?= $e((string)$s['contact_notice']) ?></textarea></label>
<label>Texto footer<textarea name="footer_text" rows="3"><?= $e((string)$s['footer_text']) ?></textarea></label>

<button class="btn btn-primary" type="submit">Guardar configuración</button>
</form>
</section>

<section class="staffadm-box">
<h3>Agregar miembro</h3>
<?= $memberForm([],true) ?>

<h3 style="margin-top:18px">Miembros registrados</h3>
<?php foreach($members as $m): ?>
<details class="staffadm-member">
<summary><?= $e((string)$m['CharacterName']) ?> · <?= $e((string)$m['RankName']) ?> <?= (int)$m['Active']?'':'(inactivo)' ?></summary>
<div style="margin-top:10px"><?= $memberForm($m,false) ?></div>
<div class="staffadm-actions">
<form method="post" action="/admin/modules/action">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="staff-profesional">
<input type="hidden" name="module_action" value="member_toggle">
<input type="hidden" name="StaffId" value="<?= (int)$m['StaffId'] ?>">
<button class="btn btn-ghost">Activar / desactivar</button>
</form>
<form method="post" action="/admin/modules/action" onsubmit="return confirm('¿Eliminar definitivamente este miembro del Staff?')">
<input type="hidden" name="_csrf" value="<?= $token ?>">
<input type="hidden" name="slug" value="staff-profesional">
<input type="hidden" name="module_action" value="member_delete">
<input type="hidden" name="StaffId" value="<?= (int)$m['StaffId'] ?>">
<button class="btn btn-danger">Eliminar</button>
</form>
</div>
</details>
<?php endforeach; ?>

<p class="staffadm-help">Los datos de nivel, clase, Reset, Grand Reset, mapa, clan y estado Online se obtienen en tiempo real desde MuOnline97.</p>
</section>
</div>
        <?php
        return (string)ob_get_clean();
    }

    if($hook==='user.page'){
        $s=$service->settings();

        if(($s['enabled']??'1')!=='1'){
            return [
                'title'=>'Staff Oficial',
                'html'=>'<div class="notice-box">La página del Staff está temporalmente desactivada.</div>'
            ];
        }

        $members=$service->members(true);
        $accent=(string)($s['accent_color']??'#d4af37');
        $background=$service->assetUrl((string)($s['background_image']??''));
        $header=$service->assetUrl((string)($s['header_image']??''));
        $defaultAvatar=$service->assetUrl((string)($s['default_avatar']??'assets/images/default-avatar.png'));

        $ranks=[];
        $departments=[];
        foreach($members as $m){
            $rank=trim((string)($m['RankName']??''));
            $dep=trim((string)($m['Department']??''));
            if($rank!=='') $ranks[$rank]=true;
            if($dep!=='') $departments[$dep]=true;
        }

        $style='--staff-accent:'.$e($accent).';';
        if($background!==''){
            $style.="background-image:linear-gradient(rgba(7,7,10,.88),rgba(7,7,10,.88)),url('".$e($background)."');";
        }

        ob_start(); ?>
<div id="staff-pro" class="staff-pro" style="<?= $style ?>">
<header class="staff-hero">
<?php if($header!==''): ?><img class="staff-hero-image" src="<?= $e($header) ?>" alt="Staff"><?php endif; ?>
<div class="staff-hero-copy">
<span class="staff-eyebrow">EQUIPO OFICIAL</span>
<h1><?= $e((string)($s['title']??'Nuestro Equipo')) ?></h1>
<p><?= $e((string)($s['subtitle']??'')) ?></p>
</div>
</header>

<?php if(($s['show_warning']??'1')==='1'): ?>
<section class="staff-warning">
<div class="staff-warning-icon">🛡️</div>
<div>
<h3><?= $e((string)$s['warning_title']) ?></h3>
<p><?= nl2br($e((string)$s['warning_text'])) ?></p>
</div>
</section>
<?php endif; ?>

<?php if(($s['show_search']??'1')==='1'||($s['show_rank_filter']??'1')==='1'||($s['show_department_filter']??'1')==='1'): ?>
<div class="staff-toolbar">
<?php if(($s['show_search']??'1')==='1'): ?><input id="staff-search" type="search" placeholder="Buscar miembro, personaje, rango o clan…"><?php endif; ?>
<?php if(($s['show_rank_filter']??'1')==='1'): ?>
<select id="staff-rank-filter"><option value="">Todos los rangos</option>
<?php foreach(array_keys($ranks) as $r): ?><option value="<?= $e(strtolower($r)) ?>"><?= $e($r) ?></option><?php endforeach; ?>
</select>
<?php endif; ?>
<?php if(($s['show_department_filter']??'1')==='1'): ?>
<select id="staff-department-filter"><option value="">Todas las áreas</option>
<?php foreach(array_keys($departments) as $d): ?><option value="<?= $e(strtolower($d)) ?>"><?= $e($d) ?></option><?php endforeach; ?>
</select>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="staff-grid" style="--staff-columns:<?= max(1,min(4,(int)($s['card_columns']??3))) ?>">
<?php foreach($members as $m):
$avatar=$service->assetUrl((string)($m['AvatarUrl']??''));
if($avatar==='') $avatar=$defaultAvatar;
$status=(string)($m['PublicStatus']??'available');
$search=strtolower(
    (string)($m['CharacterName']??'').' '.
    (string)($m['RankName']??'').' '.
    (string)($m['Department']??'').' '.
    (string)($m['GuildName']??'')
);
?>
<article class="staff-card<?= !empty($m['Featured'])?' featured':'' ?>"
 data-search="<?= $e($search) ?>"
 data-rank="<?= $e(strtolower((string)($m['RankName']??''))) ?>"
 data-department="<?= $e(strtolower((string)($m['Department']??''))) ?>">

<div class="staff-card-top">
<?php if(!empty($m['Featured'])): ?><span class="staff-featured">⭐ DESTACADO</span><?php endif; ?>
<?php if($avatar!==''): ?><img class="staff-avatar" src="<?= $e($avatar) ?>" alt="<?= $e((string)$m['CharacterName']) ?>"><?php endif; ?>
<span class="staff-status status-<?= $e($status) ?>">
<?= $status==='available'?'Disponible':($status==='busy'?'Ocupado':($status==='away'?'Ausente':'Fuera de servicio')) ?>
</span>
</div>

<div class="staff-card-body">
<span class="staff-rank" style="background:<?= $e((string)$m['RankColor']) ?>"><?= $e((string)$m['RankName']) ?></span>
<h2><?= $e((string)$m['CharacterName']) ?></h2>

<?php if(!empty($m['Department'])): ?><div class="staff-department">⚙️ <?= $e((string)$m['Department']) ?></div><?php endif; ?>
<?php if(($s['show_character_class']??'1')==='1' && $m['Class']!==null): ?><div class="staff-class">🧙 <?= $e(OmegaStaffProfessionalService::className((int)$m['Class'])) ?></div><?php endif; ?>
<?php if(!empty($m['ShortDescription'])): ?><p class="staff-summary"><?= $e((string)$m['ShortDescription']) ?></p><?php endif; ?>

<?php if(!empty($m['ShowOnline'])): ?>
<div class="staff-online <?= !empty($m['IsOnline'])?'online':'offline' ?>"><i></i><?= !empty($m['IsOnline'])?'Conectado':'Desconectado' ?></div>
<?php endif; ?>

<?php if(!empty($m['ShowStats'])): ?>
<div class="staff-stats">
<?php if(($s['show_level']??'1')==='1'): ?><div><span>Nivel</span><b><?= (int)($m['cLevel']??0) ?></b></div><?php endif; ?>
<?php if(($s['show_resets']??'1')==='1'): ?><div><span>Resets</span><b><?= (int)($m['Resets']??0) ?></b></div><?php endif; ?>
<?php if(($s['show_master_resets']??'1')==='1'): ?><div><span>G. Reset</span><b><?= (int)($m['MasterResets']??0) ?></b></div><?php endif; ?>
<?php if(($s['show_pk']??'0')==='1'): ?><div><span>PK</span><b><?= (int)($m['PkLevel']??0) ?></b></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if(($s['show_map']??'1')==='1' && $m['MapNumber']!==null): ?>
<div class="staff-location">📍 <?= $e(OmegaStaffProfessionalService::mapName((int)$m['MapNumber'])) ?> (<?= (int)$m['MapPosX'] ?>, <?= (int)$m['MapPosY'] ?>)</div>
<?php endif; ?>

<?php if(!empty($m['ShowGuild']) && !empty($m['GuildName'])): ?>
<section class="staff-guild">
<div class="staff-guild-icon">🛡️</div>
<div><small>Clan</small><strong><?= $e((string)$m['GuildName']) ?></strong>
<span>Maestro: <?= $e((string)($m['GuildMaster']?:'Sin información')) ?> · Puntos: <?= number_format((int)$m['GuildScore']) ?></span></div>
</section>
<?php endif; ?>

<div class="staff-meta">
<?php if(!empty($m['Country'])): ?><span>🌎 <?= $e((string)$m['Country']) ?></span><?php endif; ?>
<?php if(!empty($m['Languages'])): ?><span>🗣️ <?= $e((string)$m['Languages']) ?></span><?php endif; ?>
<?php if(!empty($m['Schedule'])): ?><span>🕒 <?= $e((string)$m['Schedule']) ?></span><?php endif; ?>
<?php if(!empty($m['Timezone'])): ?><span>🌐 <?= $e((string)$m['Timezone']) ?></span><?php endif; ?>
</div>

<?php if(!empty($m['Biography'])): ?><details class="staff-bio"><summary>Ver perfil</summary><p><?= nl2br($e((string)$m['Biography'])) ?></p></details><?php endif; ?>

<div class="staff-contact">
<?php if(!empty($m['ShowWhatsapp'])&&!empty($m['ContactWhatsapp'])): ?><a href="https://wa.me/<?= preg_replace('/\D+/','',(string)$m['ContactWhatsapp']) ?>" target="_blank" rel="noopener noreferrer">💬 WhatsApp</a><?php endif; ?>
<?php if(!empty($m['ShowPhone'])&&!empty($m['ContactPhone'])): ?><a href="tel:<?= $e((string)$m['ContactPhone']) ?>">📞 Teléfono</a><?php endif; ?>
<?php if(!empty($m['ShowDiscord'])&&!empty($m['ContactDiscord'])): ?><button type="button" class="staff-copy" data-copy="<?= $e((string)$m['ContactDiscord']) ?>">🎧 <?= $e((string)$m['ContactDiscord']) ?></button><?php endif; ?>
<?php if(!empty($m['ShowEmail'])&&!empty($m['ContactEmail'])): ?><a href="mailto:<?= $e((string)$m['ContactEmail']) ?>">✉️ Correo</a><?php endif; ?>
<?php if(!empty($m['ContactWebsite'])): ?><a href="<?= $e((string)$m['ContactWebsite']) ?>" target="_blank" rel="noopener noreferrer">🔗 Perfil</a><?php endif; ?>
</div>
</div>
</article>
<?php endforeach; ?>

<?php if(($s['show_empty_slots']??'0')==='1'): ?>
<?php for($i=0;$i<(int)($s['empty_slots_count']??0);$i++): ?>
<article class="staff-card staff-empty"><div class="staff-empty-icon">➕</div><h2><?= $e((string)$s['empty_slot_title']) ?></h2><p><?= $e((string)$s['empty_slot_text']) ?></p></article>
<?php endfor; ?>
<?php endif; ?>
</div>

<div id="staff-no-results" class="staff-no-results" hidden>No se encontraron miembros con esos filtros.</div>
<?php if(!empty($s['contact_notice'])): ?><p class="staff-contact-notice">🔐 <?= $e((string)$s['contact_notice']) ?></p><?php endif; ?>
<?php if(!empty($s['footer_text'])): ?><footer class="staff-footer"><?= $e((string)$s['footer_text']) ?></footer><?php endif; ?>
</div>
        <?php
        return [
            'title'=>(string)($s['title']??'Staff Oficial'),
            'html'=>(string)ob_get_clean()
        ];
    }

    return null;
};
