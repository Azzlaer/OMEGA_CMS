<?php
declare(strict_types=1);

use Omega\Core\Database;

final class OmegaTvTModuleSettings
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
            IF OBJECT_ID('dbo.cms_tvt_module_settings','U') IS NULL
            BEGIN
                CREATE TABLE dbo.cms_tvt_module_settings(
                    Id INT NOT NULL CONSTRAINT PK_cms_tvt_module_settings PRIMARY KEY,
                    HeaderMode VARCHAR(16) NOT NULL CONSTRAINT DF_tvt_header_mode DEFAULT 'design',
                    DesignEyebrow NVARCHAR(120) NULL,
                    DesignTitle NVARCHAR(160) NULL,
                    DesignSubtitle NVARCHAR(500) NULL,
                    BannerPath NVARCHAR(500) NULL,
                    BannerAlt NVARCHAR(180) NULL,
                    ShowAccountCharacters BIT NOT NULL CONSTRAINT DF_tvt_show_chars DEFAULT 1,
                    RecentMatches INT NOT NULL CONSTRAINT DF_tvt_recent DEFAULT 20,
                    RankingLimit INT NOT NULL CONSTRAINT DF_tvt_ranking DEFAULT 30,
                    TimelineLimit INT NOT NULL CONSTRAINT DF_tvt_timeline DEFAULT 100,
                    ShowRewards BIT NOT NULL CONSTRAINT DF_tvt_rewards DEFAULT 1,
                    ShowObjectives BIT NOT NULL CONSTRAINT DF_tvt_objectives DEFAULT 1,
                    UpdatedAt DATETIME2 NOT NULL CONSTRAINT DF_tvt_updated DEFAULT SYSUTCDATETIME()
                );
            END
        ");

        $this->db->execute("
            IF NOT EXISTS(SELECT 1 FROM dbo.cms_tvt_module_settings WHERE Id=1)
            INSERT INTO dbo.cms_tvt_module_settings(
                Id,HeaderMode,DesignEyebrow,DesignTitle,DesignSubtitle,BannerAlt,
                ShowAccountCharacters,RecentMatches,RankingLimit,TimelineLimit,
                ShowRewards,ShowObjectives
            ) VALUES(
                1,'design',N'EVENT CENTER · TEAM VS TEAM',N'BLUE ⚔ RED · TvT',
                N'Historial, ranking y estadísticas reales del evento registrados por el GameServer.',
                N'Evento TvT',1,20,30,100,1,1
            )
        ");
    }

    public function get(): array
    {
        $row=$this->db->fetchOne("SELECT TOP 1 * FROM dbo.cms_tvt_module_settings WHERE Id=1") ?? [];
        return [
            'header_mode'=>(string)($row['HeaderMode']??'design'),
            'design_eyebrow'=>(string)($row['DesignEyebrow']??'EVENT CENTER · TEAM VS TEAM'),
            'design_title'=>(string)($row['DesignTitle']??'BLUE ⚔ RED · TvT'),
            'design_subtitle'=>(string)($row['DesignSubtitle']??'Historial, ranking y estadísticas reales del evento registrados por el GameServer.'),
            'banner_path'=>(string)($row['BannerPath']??''),
            'banner_alt'=>(string)($row['BannerAlt']??'Evento TvT'),
            'show_account_characters'=>(bool)($row['ShowAccountCharacters']??1),
            'recent_matches'=>(int)($row['RecentMatches']??20),
            'ranking_limit'=>(int)($row['RankingLimit']??30),
            'timeline_limit'=>(int)($row['TimelineLimit']??100),
            'show_rewards'=>(bool)($row['ShowRewards']??1),
            'show_objectives'=>(bool)($row['ShowObjectives']??1),
        ];
    }

    public function save(array $data): void
    {
        $mode=strtolower(trim((string)($data['header_mode']??'design')));
        if(!in_array($mode,['design','banner','none'],true)) $mode='design';
        if($mode==='banner' && (string)($this->get()['banner_path']??'')===''){
            throw new RuntimeException('Primero sube una imagen antes de activar Banner personalizado.');
        }

        $eyebrow=$this->clip((string)($data['design_eyebrow']??''),120);
        $title=$this->clip((string)($data['design_title']??''),160);
        $subtitle=$this->clip((string)($data['design_subtitle']??''),500);
        $alt=$this->clip((string)($data['banner_alt']??''),180);

        $recent=max(5,min(100,(int)($data['recent_matches']??20)));
        $ranking=max(10,min(100,(int)($data['ranking_limit']??30)));
        $timeline=max(20,min(300,(int)($data['timeline_limit']??100)));

        $this->db->execute(
            "UPDATE dbo.cms_tvt_module_settings SET
                HeaderMode=:mode,
                DesignEyebrow=:eyebrow,
                DesignTitle=:title,
                DesignSubtitle=:subtitle,
                BannerAlt=:alt,
                ShowAccountCharacters=:chars,
                RecentMatches=:recent,
                RankingLimit=:ranking,
                TimelineLimit=:timeline,
                ShowRewards=:rewards,
                ShowObjectives=:objectives,
                UpdatedAt=SYSUTCDATETIME()
             WHERE Id=1",
            [
                'mode'=>$mode,
                'eyebrow'=>$eyebrow,
                'title'=>$title,
                'subtitle'=>$subtitle,
                'alt'=>$alt,
                'chars'=>!empty($data['show_account_characters'])?1:0,
                'recent'=>$recent,
                'ranking'=>$ranking,
                'timeline'=>$timeline,
                'rewards'=>!empty($data['show_rewards'])?1:0,
                'objectives'=>!empty($data['show_objectives'])?1:0,
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
        if(!is_file($tmp)) throw new RuntimeException('Archivo temporal no disponible.');
        if($size<1 || $size>8*1024*1024) throw new RuntimeException('La imagen debe pesar menos de 8 MB.');

        $info=@getimagesize($tmp);
        if(!is_array($info)) throw new RuntimeException('El archivo no es una imagen válida.');

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

        $dir=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'event-tvt-pro';
        if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)){
            throw new RuntimeException('No fue posible crear la carpeta de banners TvT.');
        }

        $old=$this->get()['banner_path']??'';
        $name='banner-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.'.$ext;
        $dest=$dir.DIRECTORY_SEPARATOR.$name;

        if(!@move_uploaded_file($tmp,$dest)){
            if(!@rename($tmp,$dest)){
                throw new RuntimeException('No fue posible guardar la imagen.');
            }
        }

        $publicPath='/public/uploads/event-tvt-pro/'.$name;
        $this->db->execute(
            "UPDATE dbo.cms_tvt_module_settings
             SET BannerPath=:p,HeaderMode='banner',UpdatedAt=SYSUTCDATETIME()
             WHERE Id=1",
            ['p'=>$publicPath]
        );

        $this->deletePhysicalBanner((string)$old,$publicPath);
        return $publicPath;
    }

    public function deleteBanner(): void
    {
        $old=(string)($this->get()['banner_path']??'');
        $this->db->execute(
            "UPDATE dbo.cms_tvt_module_settings
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
        if(!str_starts_with($publicPath,'/public/uploads/event-tvt-pro/')) return;

        $name=basename($publicPath);
        if($name==='' || $name==='.' || $name==='..') return;

        $full=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'event-tvt-pro'.DIRECTORY_SEPARATOR.$name;
        if(is_file($full)) @unlink($full);
    }

    private function clip(string $value,int $max): string
    {
        $value=trim($value);
        if(function_exists('mb_substr')) return mb_substr($value,0,$max,'UTF-8');
        return substr($value,0,$max);
    }
}
