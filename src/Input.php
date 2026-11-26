<?php
declare(strict_types=1);
namespace LaporKota;
final class Input
{
    public static function text(array $data,string $key,int $min,int $max,string $default=''):string
    {
        $value=$data[$key]??$default;
        if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen($value,'UTF-8')<$min||mb_strlen($value,'UTF-8')>$max)throw new HttpError(400,'Periksa isian formulir.');
        return $value;
    }
    public static function id(array $data,string $key):int
    {
        $value=$data[$key]??null;
        if(is_int($value))$value=(string)$value;
        if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,14}$/D',$value))throw new HttpError(400,'Identitas data tidak valid.');
        return (int)$value;
    }
}
