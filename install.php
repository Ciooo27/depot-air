<?php
/**
 * Auto-Installer for AquaDepot
 * One-click database creator and migrator for XAMPP / Localhost
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'aquadepot_db';

$message = '';
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['host'] ?? 'localhost');
    $dbUser = trim($_POST['user'] ?? 'root');
    $dbPass = $_POST['pass'] ?? '';

    try {
        // Connect to MySQL server without selecting database
        $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create Database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");

        // Read SQL file
        $sqlFile = __DIR__ . '/database/aquadepot_db.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("File database/aquadepot_db.sql tidak ditemukan.");
        }

        $sql = file_get_contents($sqlFile);

        // Execute queries
        $pdo->exec($sql);

        $status = 'success';
        $message = "Basis data <strong>$dbName</strong> dan seluruh 15 tabel berhasil dibuat dan diisi data pengujian!";

    } catch (Exception $e) {
        $status = 'danger';
        $message = "Gagal mengimpor database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Otomatis - AquaDepot</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #F4F8FC;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-card {
            background: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(25, 118, 210, 0.1);
            max-width: 520px;
            width: 100%;
            padding: 40px;
            border: 1px solid rgba(25, 118, 210, 0.1);
        }
    </style>
</head>
<body>

<div class="install-card text-center">
    <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white mb-3" style="width: 68px; height: 68px; background: linear-gradient(135deg, #1976D2, #64B5F6); box-shadow: 0 4px 14px rgba(25, 118, 210, 0.4);">
        <i class="bi bi-droplet-half fs-1"></i>
    </div>
    <h3 class="fw-bold mb-1">Installer Otomatis AquaDepot</h3>
    <p class="text-muted small mb-4">Inisialisasi Database MySQL & Akun Pengujian Sekali Klik</p>

    <?php if ($status === 'success'): ?>
        <div class="alert alert-success text-start rounded-4 p-3 mb-4">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                <h6 class="fw-bold mb-0">Instalasi Berhasil!</h6>
            </div>
            <p class="small mb-3"><?= $message ?></p>
            <div class="p-2 bg-white rounded-3 border small mb-3">
                <strong>Akun Pengujian Siap Pakai:</strong><br>
                • Admin: <code>admin</code> / <code>admin123</code><br>
                • Karyawan: <code>karyawan</code> / <code>karyawan123</code><br>
                • Pelanggan: <code>pelanggan</code> / <code>pelanggan123</code>
            </div>
            <a href="index.php" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">
                Buka Website AquaDepot <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    <?php else: ?>
        <?php if ($status === 'danger'): ?>
            <div class="alert alert-danger text-start rounded-4 p-3 mb-4 small">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="text-start">
            <div class="mb-3">
                <label class="form-label small fw-bold">Database Host</label>
                <input type="text" class="form-control rounded-3" name="host" value="localhost" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Database User</label>
                <input type="text" class="form-control rounded-3" name="user" value="root" required>
                <small class="text-muted">Default pengguna XAMPP adalah <code>root</code></small>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">Database Password</label>
                <input type="password" class="form-control rounded-3" name="pass" placeholder="Kosongkan jika default XAMPP">
            </div>

            <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow">
                <i class="bi bi-database-fill-gear me-2"></i>Pasang & Buat Database Sekarang
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-muted small">
            Atau Anda dapat mengimpor berkas <code>database/aquadepot_db.sql</code> secara manual melalui phpMyAdmin.
        </div>
    <?php endif; ?>
</div>

</body>
</html>

