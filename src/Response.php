<?php
declare(strict_types=1);
namespace LaporKota;
final class Response
{
    public static function error(int $status,string $message):void
    {
        http_response_code($status);
        if(str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/api/')){header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);return;}
        View::page('Ada kendala','<p role="alert">'.View::e($message).'</p><a href="/laporan">Kembali ke aduan</a>');
    }
}
