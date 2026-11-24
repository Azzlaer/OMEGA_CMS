<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaMarrySystemSettings
{
    public function __construct(
        private readonly Database $db,
        private readonly string $root
    ) {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->db->execute("
            IF OBJECT_ID('dbo.cms_marrysystem_settings','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_marrysystem_settings(
                    Id INT NOT NULL CONSTRAINT PK_cms_marrysystem_settings PRIMARY KEY,
                    HeaderMode VARCHAR(16) NOT NULL CONSTRAINT DF_marry_header_mode DEFAULT 'design',
                    DesignEyebrow NVARCHAR(120) NULL,
                    DesignTitle NVARCHAR(180) NULL,
                    DesignSubtitle NVARCHAR(500) NULL,
                    BannerPath NVARCHAR(500) NULL,
                    BannerAlt NVARCHAR(180) NULL,
                    BannerFit VARCHAR(16) NOT NULL CONSTRAINT DF_marry_banner_fit DEFAULT 'cover',
                    BannerPosition VARCHAR(16) NOT NULL CONSTRAINT DF_marry_banner_position DEFAULT 'center',
                    BannerHeight INT NOT NULL CONSTRAINT DF_marry_banner_height DEFAULT 420,
                    CouplesLimit INT NOT NULL CONSTRAINT DF_marry_couples_limit DEFAULT 50,
                    HistoryLimit INT NOT NULL CONSTRAINT DF_marry_history_limit DEFAULT 30,
                    ShowSummary BIT NOT NULL CONSTRAINT DF_marry_summary DEFAULT 1,
                    ShowHistory BIT NOT NULL CONSTRAINT DF_marry_history DEFAULT 1,
                    ShowPersonalStatus BIT NOT NULL CONSTRAINT DF_marry_personal DEFAULT 1,
                    UpdatedAt DATETIME2 NOT NULL CONSTRAINT DF_marry_updated DEFAULT SYSUTCDATETIME()
                );
            END
        ");

        $this->db->execute("
            IF NOT EXISTS(SELECT 1 FROM dbo.cms_marrysystem_settings WHERE Id=1)
            INSERT INTO dbo.cms_marrysystem_settings(
                Id,HeaderMode,DesignEyebrow,DesignTitle,DesignSubtitle,
                BannerAlt,BannerFit,BannerPosition,BannerHeight,
                CouplesLimit,HistoryLimit,ShowSummary,ShowHistory,ShowPersonalStatus
            ) VALUES(
                1,'design',N'SISTEMAS · MARRY SYSTEM',N'💍 Sistema de Casamientos',
                N'Parejas, bodas e historial del servidor en un solo lugar.',
                N'Sistema de Casamientos','cover','center',420,
                50,30,1,1,1
            )
        ");
    }

    public function get(): array
    {
        $row=$this->db->fetchOne("SELECT TOP 1 * FROM dbo.cms_marrysystem_settings WHERE Id=1") ?? [];

        return [
            'header_mode'=>(string)($row['HeaderMode']??'design'),
            'design_eyebrow'=>(string)($row['DesignEyebrow']??'SISTEMAS · MARRY SYSTEM'),
            'design_title'=>(string)($row['DesignTitle']??'💍 Sistema de Casamientos'),
            'design_subtitle'=>(string)($row['DesignSubtitle']??'Parejas, bodas e historial del servidor en un solo lugar.'),
            'banner_path'=>(string)($row['BannerPath']??''),
            'banner_alt'=>(string)($row['BannerAlt']??'Sistema de Casamientos'),
            'banner_fit'=>(string)($row['BannerFit']??'cover'),
            'banner_position'=>(string)($row['BannerPosition']??'center'),
            'banner_height'=>max(160,min(720,(int)($row['BannerHeight']??420))),
            'couples_limit'=>max(10,min(200,(int)($row['CouplesLimit']??50))),
            'history_limit'=>max(10,min(200,(int)($row['HistoryLimit']??30))),
            'show_summary'=>(bool)($row['ShowSummary']??1),
            'show_history'=>(bool)($row['ShowHistory']??1),
            'show_personal_status'=>(bool)($row['ShowPersonalStatus']??1),
        ];
    }

    public function save(array $data): void
    {
        $mode=strtolower(trim((string)($data['header_mode']??'design')));
        if(!in_array($mode,['design','banner','none'],true)) $mode='design';

        if($mode==='banner' && (string)($this->get()['banner_path']??'')===''){
            throw new RuntimeException('Primero sube una imagen antes de activar Banner personalizado.');
        }

        $fit=strtolower(trim((string)($data['banner_fit']??'cover')));
        if(!in_array($fit,['cover','contain','fill','scale-down'],true)) $fit='cover';

        $position=strtolower(trim((string)($data['banner_position']??'center')));
        if(!in_array($position,['center','top','bottom','left','right'],true)) $position='center';

        $height=max(160,min(720,(int)($data['banner_height']??420)));
        $couples=max(10,min(200,(int)($data['couples_limit']??50)));
        $history=max(10,min(200,(int)($data['history_limit']??30)));

        $this->db->execute(
            "UPDATE dbo.cms_marrysystem_settings SET
                HeaderMode=:mode,
                DesignEyebrow=:eyebrow,
                DesignTitle=:title,
                DesignSubtitle=:subtitle,
                BannerAlt=:alt,
                BannerFit=:fit,
                BannerPosition=:position,
                BannerHeight=:height,
                CouplesLimit=:couples,
                HistoryLimit=:history,
                ShowSummary=:summary,
                ShowHistory=:show_history,
                ShowPersonalStatus=:personal,
                UpdatedAt=SYSUTCDATETIME()
             WHERE Id=1",
            [
                'mode'=>$mode,
                'eyebrow'=>$this->clip((string)($data['design_eyebrow']??''),120),
                'title'=>$this->clip((string)($data['design_title']??''),180),
                'subtitle'=>$this->clip((string)($data['design_subtitle']??''),500),
                'alt'=>$this->clip((string)($data['banner_alt']??''),180),
                'fit'=>$fit,
                'position'=>$position,
                'height'=>$height,
                'couples'=>$couples,
                'history'=>$history,
                'summary'=>!empty($data['show_summary'])?1:0,
                'show_history'=>!empty($data['show_history'])?1:0,
                'personal'=>!empty($data['show_personal_status'])?1:0,
            ]
        );
    }

    public function uploadBanner(array $file): string
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK){
            throw new RuntimeException('No se recibió una imagen válida.');
        }

        $tmp=(string)($file['tmp_name']??'');
        $size=(int)($file['size']??0);

        if($tmp==='' || !is_uploaded_file($tmp)){
            throw new RuntimeException('Archivo temporal no válido.');
        }

        if($size<1 || $size>8*1024*1024){
            throw new RuntimeException('La imagen debe pesar menos de 8 MB.');
        }

        $info=@getimagesize($tmp);
        if(!is_array($info)){
            throw new RuntimeException('El archivo no es una imagen válida.');
        }

        $width=(int)($info[0]??0);
        $height=(int)($info[1]??0);

        if($width<640 || $height<160 || $width>7680 || $height>2160){
            throw new RuntimeException('Usa una imagen entre 640×160 y 7680×2160 píxeles.');
        }

        $mime=(string)($info['mime']??'');
        $ext=match($mime){
            'image/jpeg'=>'jpg',
            'image/png'=>'png',
            'image/webp'=>'webp',
            default=>throw new RuntimeException('Formato no permitido. Usa JPG, PNG o WEBP.'),
        };

        $dir=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'marrysystem-pro';

        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)){
            throw new RuntimeException('No fue posible crear la carpeta de banners MarrySystem.');
        }

        $old=(string)($this->get()['banner_path']??'');
        $name='banner-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$ext;
        $dest=$dir.DIRECTORY_SEPARATOR.$name;

        if(!@move_uploaded_file($tmp,$dest)){
            throw new RuntimeException('No fue posible guardar la imagen.');
        }

        $publicPath='/public/uploads/marrysystem-pro/'.$name;

        $this->db->execute(
            "UPDATE dbo.cms_marrysystem_settings
             SET BannerPath=:p,HeaderMode='banner',UpdatedAt=SYSUTCDATETIME()
             WHERE Id=1",
            ['p'=>$publicPath]
        );

        $this->deletePhysicalBanner($old,$publicPath);

        return $publicPath;
    }

    public function deleteBanner(): void
    {
        $old=(string)($this->get()['banner_path']??'');

        $this->db->execute(
            "UPDATE dbo.cms_marrysystem_settings
             SET BannerPath=NULL,
                 HeaderMode=CASE WHEN HeaderMode='banner' THEN 'design' ELSE HeaderMode END,
                 UpdatedAt=SYSUTCDATETIME()
             WHERE Id=1"
        );

        $this->deletePhysicalBanner($old,'');
    }

    private function deletePhysicalBanner(string $publicPath,string $except): void
    {
        if($publicPath==='' || $publicPath===$except) return;
        if(!str_starts_with($publicPath,'/public/uploads/marrysystem-pro/')) return;

        $name=basename($publicPath);
        if($name==='' || $name==='.' || $name==='..') return;

        $full=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'marrysystem-pro'.DIRECTORY_SEPARATOR.$name;
        if(is_file($full)) @unlink($full);
    }

    private function clip(string $value,int $max): string
    {
        $value=trim($value);
        if(function_exists('mb_substr')) return mb_substr($value,0,$max,'UTF-8');
        return substr($value,0,$max);
    }
}
