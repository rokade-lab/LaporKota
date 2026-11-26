<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use LaporKota\Foundation;
try {
    $foundation = new Foundation([
        'env'=>getenv('APP_ENV') ?: '', 'host'=>getenv('DB_HOST') ?: '',
        'database'=>getenv('DB_NAME') ?: '', 'user'=>getenv('DB_USER') ?: '',
        'password'=>getenv('DB_PASSWORD') ?: '', 'project'=>getenv('LOCAL_PROJECT') ?: '',
        'teams'=>getenv('FIXTURE_TEAMS') === false ? '2' : getenv('FIXTURE_TEAMS'),
    ]);
    $result = match ($argv[1] ?? '') {
        'setup'=>$foundation->setup(),
        'runtime'=>$foundation->runtime(),
        'manifest'=>$foundation->manifest(),
        'reset-local'=>$foundation->resetLocal(),
        default=>throw new RuntimeException('command unavailable'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR setup: ".($exception instanceof RuntimeException && !$exception instanceof PDOException ? $exception->getMessage() : 'dependency unavailable')."\n");
    exit(1);
}
