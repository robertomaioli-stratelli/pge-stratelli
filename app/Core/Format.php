<?php
namespace App\Core;

final class Format
{
    public static function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function dateTime(?string $value): string
    {
        if (!$value) return '—';
        $ts=strtotime($value);
        return $ts?date('d/m/Y H:i:s',$ts):(string)$value;
    }

    public static function date(?string $value): string
    {
        if (!$value) return '—';
        $ts=strtotime($value);
        return $ts?date('d/m/Y',$ts):(string)$value;
    }

    public static function fileSize(int $bytes): string
    {
        if ($bytes<=0) return '';
        if ($bytes>=1048576) return number_format($bytes/1048576,1,',','.').' MB';
        if ($bytes>=1024) return number_format($bytes/1024,0,',','.').' KB';
        return $bytes.' B';
    }

    public static function slug(string $value): string
    {
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;
        $ascii=strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',$ascii));
        return trim($ascii,'-')?:'arquivo';
    }
}
