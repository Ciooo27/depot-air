<?php
/**
 * Database Connection using PDO
 * AquaDepot - bisa dipakai lokal (XAMPP) dan online (Render + Aiven)
 */

class Database {
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn !== null) {
            return self::$conn;
        }

        $host   = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_NAME') ?: 'aquadepot_db';
        $user   = getenv('DB_USER') ?: 'root';
        $pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
        $port   = getenv('DB_PORT') ?: '3306';

        // Dukung format host:port
        if (strpos($host, ':') !== false) {
            [$host, $port] = explode(':', $host, 2);
        }

        $isLocal = ($host === 'localhost' || $host === '127.0.0.1');

        $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // SSL hanya untuk database online (Aiven)
        if (!$isLocal) {
            $ca = __DIR__ . '/ca.pem';
            if (file_exists($ca)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            }
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            self::$conn = new PDO($dsn, $user, $pass, $options);
            return self::$conn;
        } catch (PDOException $e) {
            // Lokal: buat database otomatis kalau belum ada
            if ($isLocal && ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false)) {
                try {
                    $tempPdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $tempPdo->exec("USE `$dbName`");

                    $sqlFile = __DIR__ . '/../database/aquadepot_db.sql';
                    if (file_exists($sqlFile)) {
                        $tempPdo->exec(file_get_contents($sqlFile));
                        self::$conn = new PDO($dsn, $user, $pass, $options);
                        return self::$conn;
                    }
                } catch (Exception $autoEx) {
                    // lanjut ke pesan error di bawah
                }
            }

            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die("<h3 style='font-family:Arial'>Koneksi database gagal.</h3>"
              . "<p style='font-family:Arial'>" . htmlspecialchars($e->getMessage()) . "</p>"
              . "<p style='font-family:Arial'>Jika di komputer lokal, buka <a href='install.php'>install.php</a>.</p>");
        }
    }
}