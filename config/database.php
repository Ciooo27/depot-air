<?php
/**
 * Database Connection using PDO
 * Sistem Informasi Manajemen Depot Air Mineral (AquaDepot)
 */

class Database {
    private static $host = "localhost";
    private static $db_name = "aquadepot_db";
    private static $username = "root";
    private static $password = "";
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            $host = getenv('DB_HOST') ?: self::$host;
            $dbName = getenv('DB_NAME') ?: self::$db_name;
            $user = getenv('DB_USER') ?: self::$username;
            $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : self::$password;
            $port = getenv('DB_PORT') ?: '3306';

            // Support host:port format (common in Aiven / Railway)
            if (strpos($host, ':') !== false) {
                $parts = explode(':', $host, 2);
                $host = $parts[0];
                $port = $parts[1];
            }

            $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $dbName . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$conn = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // If database doesn't exist (Error 1049), attempt auto-creation
                if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                    try {
                        $tempPdo = new PDO("mysql:host=" . $host . ";charset=utf8mb4", $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                        ]);
                        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $tempPdo->exec("USE `$dbName`");

                        $sqlFile = __DIR__ . '/../database/aquadepot_db.sql';
                        if (file_exists($sqlFile)) {
                            $sql = file_get_contents($sqlFile);
                            $tempPdo->exec($sql);

                            // Reconnect now that database and tables are created!
                            self::$conn = new PDO($dsn, $user, $pass, $options);
                            return self::$conn;
                        }
                    } catch (Exception $autoEx) {
                        // If auto-create fails, show friendly UI below
                    }
                }

                // Friendly HTML Error with direct 1-click installer link
                $installUrl = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/../install.php';
                if (file_exists(__DIR__ . '/../install.php')) {
                    $installUrl = 'install.php';
                }

                die("
                <!DOCTYPE html>
                <html lang='id'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Database Belum Dibuat - AquaDepot</title>
                    <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap' rel='stylesheet'>
                    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
                    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'>
                    <style>
                        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #F4F8FC; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
                        .card-box { background: #FFFFFF; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); max-width: 620px; padding: 35px; border: 1px solid rgba(25, 118, 210, 0.1); }
                    </style>
                </head>
                <body>
                    <div class='card-box text-center'>
                        <div class='rounded-circle d-inline-flex align-items-center justify-content-center text-warning bg-warning-subtle mb-3' style='width: 70px; height: 70px; font-size: 2rem;'>
                            <i class='bi bi-database-exclamation'></i>
                        </div>
                        <h3 class='fw-bold mb-1'>Database 'aquadepot_db' Belum Dibuat</h3>
                        <p class='text-muted small mb-4'>Koneksi ke MySQL di komputer Anda sudah berhasil, tetapi basis data <strong>aquadepot_db</strong> belum ada di phpMyAdmin.</p>
                        
                        <div class='p-3 bg-primary-subtle text-start rounded-4 mb-4 border border-primary-subtle'>
                            <h6 class='fw-bold text-primary mb-2'><i class='bi bi-magic me-1'></i> Solusi 1-Detik (Paling Mudah):</h6>
                            <p class='small text-muted mb-3'>Cukup klik tombol biru di bawah ini untuk membuat database dan mengimpor seluruh tabel otomatis:</p>
                            <a href='install.php' class='btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm'>
                                <i class='bi bi-play-circle-fill me-1'></i> Pasang Database Otomatis Sekarang
                            </a>
                        </div>

                        <div class='text-start border-top pt-3'>
                            <h6 class='fw-bold small mb-2 text-muted'>Atau Impor Manual via phpMyAdmin:</h6>
                            <ol class='small text-muted ps-3 mb-0'>
                                <li>Buka <code>http://localhost/phpmyadmin</code> di tab baru browser Anda.</li>
                                <li>Klik menu <strong>Databases</strong> &raquo; buat database bernama: <code>aquadepot_db</code> &raquo; klik <strong>Create</strong>.</li>
                                <li>Pilih tab <strong>Import</strong> &raquo; upload file <code>database/aquadepot_db.sql</code> &raquo; klik <strong>Import</strong>.</li>
                                <li>Setelah itu, <strong>Refresh (Muat Ulang)</strong> halaman ini!</li>
                            </ol>
                        </div>
                    </div>
                </body>
                </html>
                ");
            }
        }
        return self::$conn;
    }
}
