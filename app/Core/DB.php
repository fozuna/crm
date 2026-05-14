<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('DB_HOST', '127.0.0.1');
        $port = (int) Config::get('DB_PORT', 3306);
        $db = (string) Config::get('DB_NAME', '');
        $user = (string) Config::get('DB_USER', '');
        $pass = (string) Config::get('DB_PASS', '');
        $charset = (string) Config::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Falha ao conectar no banco.');
        }

        return self::$pdo;
    }
}

