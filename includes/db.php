<?php
/**
 * Database Connection Manager (PDO)
 */

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('get_db_connection')) {
    function get_db_connection(): PDO
    {
        static $instance = null;

        if ($instance !== null) {
            return $instance;
        }

        $driver   = env('DB_CONNECTION', 'mysql');
        $charset  = env('DB_CHARSET', 'utf8mb4');

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $dbPath = env('DB_DATABASE', dirname(__DIR__) . '/database/database.sqlite');
                $dsn = "sqlite:{$dbPath}";
                $instance = new PDO($dsn, null, null, $options);
            } else {
                $host     = env('DB_HOST', '127.0.0.1');
                $port     = (int)env('DB_PORT', 3306);
                $database = env('DB_DATABASE', 'pabpla_demo');
                $username = env('DB_USERNAME', 'root');
                $password = (string)env('DB_PASSWORD', '');

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset}";

                $instance = new PDO($dsn, $username, $password, $options);
            }

            return $instance;
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());

            if (is_production()) {
                http_response_code(500);
                die('<div style="text-align:center; margin-top:50px; font-family:sans-serif; color:#721c24; background:#f8d7da; padding:20px; border-radius:8px; max-width:600px; margin-left:auto; margin-right:auto;">' .
                    '<h3>ขออภัย ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้</h3>' .
                    '<p>กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบ</p>' .
                    '</div>');
            }

            throw new PDOException("Database connection failed: " . $e->getMessage(), (int)$e->getCode());
        }
    }
}

if (!function_exists('get_db')) {
    function get_db(): PDO
    {
        return get_db_connection();
    }
}

// Global $conn instance for backward compatibility with existing legacy scripts
$conn = get_db_connection();
