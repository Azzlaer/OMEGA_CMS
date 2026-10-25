<?php
declare(strict_types=1);

return static function(string $hook,array $payload,array $config,array $context): mixed {
    if($hook==='admin.action'){
        if((string)($payload['action']??'')==='runtime_check'){
            return 'Inventory Viewer - Item Images Pack 2/2 activo. GIF incluidos: 473.';
        }
        return null;
    }

    if($hook==='admin.panel'){
        $files=glob(__DIR__.'/assets/items/*.{gif,GIF}',GLOB_BRACE);
        $count=is_array($files)?count($files):0;
        ob_start(); ?>
        <div style="background:#11171e;border:1px solid #293440;border-radius:10px;padding:18px">
            <h2 style="margin-top:0;color:#e2bd5c">🖼 Inventory Viewer - Item Images Pack 2/2</h2>
            <p><strong>GIF instalados:</strong> <?= (int)$count ?></p>
            <p><strong>Ruta pública:</strong> <code>/module-assets/inventory-item-images-2/assets/items/</code></p>
            <p>Instala también la otra mitad del pack para disponer de la colección completa.</p>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    return null;
};
