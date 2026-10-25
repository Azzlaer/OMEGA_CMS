<?php
declare(strict_types=1);

namespace Omega\Services;

final class BbcodeRenderer
{
    public static function render(string $text): string
    {
        $safe=htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $safe=str_replace(["\r\n","\r"],"\n",$safe);

        /*
         * SMF-style media autoembed:
         * If a supported media URL is alone on its own line, render it.
         * This deliberately does NOT transform URLs embedded in normal prose.
         */
        $safe=preg_replace_callback(
            '#(?m)^[ \t]*(https?://(?:www\.)?(?:youtube\.com|youtu\.be)/[^\s<]+)[ \t]*$#i',
            static function(array $m): string {
                $url=html_entity_decode(trim((string)$m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8');
                $id=self::youtubeId($url);
                return $id!==null ? self::youtubeEmbed($id) : $m[0];
            },
            $safe
        )??$safe;

        $safe=preg_replace_callback(
            '#(?m)^[ \t]*(https://[^\s<]+\.(?:png|jpe?g|gif|webp|avif)(?:\?[^\s<]*)?)[ \t]*$#i',
            static fn(array $m): string =>
                '<img class="bbcode-image" src="'.$m[1].'" alt="" loading="lazy" referrerpolicy="no-referrer">',
            $safe
        )??$safe;

        // YouTube BBCode.
        $safe=preg_replace_callback(
            '/\[youtube\](.*?)\[\/youtube\]/is',
            static function(array $m): string {
                $id=self::youtubeId(html_entity_decode(trim((string)$m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
                if($id===null){
                    return '<span class="bbcode-error">[YouTube inválido]</span>';
                }
                return self::youtubeEmbed($id);
            },
            $safe
        )??$safe;

        $replacements=[
            '/\[b\](.*?)\[\/b\]/is'=>'<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/is'=>'<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/is'=>'<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/is'=>'<s>$1</s>',
            '/\[center\](.*?)\[\/center\]/is'=>'<div class="bbcode-align bbcode-center">$1</div>',
            '/\[left\](.*?)\[\/left\]/is'=>'<div class="bbcode-align bbcode-left">$1</div>',
            '/\[right\](.*?)\[\/right\]/is'=>'<div class="bbcode-align bbcode-right">$1</div>',
            '/\[h2\](.*?)\[\/h2\]/is'=>'<h2 class="bbcode-heading">$1</h2>',
            '/\[h3\](.*?)\[\/h3\]/is'=>'<h3 class="bbcode-heading">$1</h3>',
            '/\[code\](.*?)\[\/code\]/is'=>'<pre class="bbcode-code"><code>$1</code></pre>',
            '/\[quote\](.*?)\[\/quote\]/is'=>'<blockquote class="bbcode-quote">$1</blockquote>',
            '/\[hr\]/i'=>'<hr class="bbcode-hr">',
        ];
        foreach($replacements as $pattern=>$replace){
            $safe=preg_replace($pattern,$replace,$safe)??$safe;
        }

        $safe=preg_replace_callback(
            '/\[color=(#[0-9A-Fa-f]{6})\](.*?)\[\/color\]/is',
            static fn(array $m): string => '<span style="color:'.$m[1].'">'.$m[2].'</span>',
            $safe
        )??$safe;

        $safe=preg_replace_callback(
            '/\[size=(\d{1,2})\](.*?)\[\/size\]/is',
            static function(array $m): string {
                $size=max(10,min(40,(int)$m[1]));
                return '<span style="font-size:'.$size.'px">'.$m[2].'</span>';
            },
            $safe
        )??$safe;

        // [url=https://...]texto[/url]
        $safe=preg_replace_callback(
            '/\[url=(https?:\/\/[^\]\s]+)\](.*?)\[\/url\]/is',
            static fn(array $m): string =>
                '<a href="'.$m[1].'" target="_blank" rel="noopener noreferrer nofollow">'.$m[2].'</a>',
            $safe
        )??$safe;

        // [url]https://...[/url]
        $safe=preg_replace_callback(
            '/\[url\](https?:\/\/[^\[\s]+)\[\/url\]/is',
            static fn(array $m): string =>
                '<a href="'.$m[1].'" target="_blank" rel="noopener noreferrer nofollow">'.$m[1].'</a>',
            $safe
        )??$safe;

        // HTTPS images only. Browser handles supported image formats.
        $safe=preg_replace_callback(
            '/\[img\](https:\/\/[^\[\s]+)\[\/img\]/is',
            static fn(array $m): string =>
                '<img class="bbcode-image" src="'.$m[1].'" alt="" loading="lazy" referrerpolicy="no-referrer">',
            $safe
        )??$safe;

        // [list][*] ... [*] ... [/list]
        $safe=preg_replace_callback(
            '/\[list\](.*?)\[\/list\]/is',
            static function(array $m): string {
                $inner=(string)$m[1];
                $items=preg_split('/\[\*\]/',$inner);
                $html='';
                foreach($items as $item){
                    $item=trim($item);
                    if($item!=='') $html.='<li>'.$item.'</li>';
                }
                return $html!==''?'<ul class="bbcode-list">'.$html.'</ul>':'';
            },
            $safe
        )??$safe;

        // Backward compatible legacy [*] lines without [list].
        $safe=preg_replace('/^\[\*\]\s*(.+)$/m','<li>$1</li>',$safe)??$safe;
        $safe=preg_replace('/((?:<li>.*?<\/li>\s*)+)/is','<ul class="bbcode-list">$1</ul>',$safe)??$safe;

        return nl2br($safe,false);
    }

    private static function youtubeEmbed(string $id): string
    {
        $src='https://www.youtube-nocookie.com/embed/'.$id;
        return '<div class="bbcode-video"><iframe src="'.$src.'" title="YouTube video player" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';
    }

    private static function youtubeId(string $value): ?string
    {
        $value=trim($value);

        if(preg_match('/^[A-Za-z0-9_-]{11}$/',$value)){
            return $value;
        }

        if(!preg_match('#^https?://#i',$value)){
            return null;
        }

        $parts=parse_url($value);
        if(!is_array($parts)) return null;

        $host=strtolower((string)($parts['host']??''));
        $host=preg_replace('/^www\./','',$host)??$host;
        $path=(string)($parts['path']??'');

        $id=null;

        if($host==='youtu.be'){
            $id=trim(explode('/',$path)[1]??'');
        } elseif(in_array($host,['youtube.com','m.youtube.com','music.youtube.com'],true)){
            if($path==='/watch'){
                parse_str((string)($parts['query']??''),$query);
                $id=(string)($query['v']??'');
            } elseif(preg_match('#^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})(?:/|$)#',$path,$m)){
                $id=$m[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/',$id)?$id:null;
    }
}
