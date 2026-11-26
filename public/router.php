<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
try {
    $db=new PDO('mysql:host='.(getenv('DB_HOST')?:'db').';dbname='.(getenv('DB_NAME')?:'laporkota_local').';charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    (new LaporKota\App($db))->run();
} catch(LaporKota\HttpError $error) {LaporKota\Response::error($error->status,$error->getMessage());
} catch(Throwable $error) {LaporKota\Response::error(503,'Layanan sementara tidak tersedia. Silakan coba lagi.');}
