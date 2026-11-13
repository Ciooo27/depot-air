<?php
$pageTitle = "Dashboard Karyawan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['karyawan', 'admin']);

$db = Database::getConnection();

// Operasional metrics
$today = date('Y-m-d');

// 1. Pesanan Baru (menunggu konfirmasi)
$newOrdersCount = (int)$db->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'menunggu_konfirmasi'")->fetchColumn();

// 2. Pesanan Diproses / Dikirim
$inProcessCount = (int)$db->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan IN ('diproses', 'siap_dikirim', 'sedang_dikirim')")->fetchColumn();

// 3. Pesanan Selesai Hari Ini
$stmtDoneToday = $db->prepare("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'selesai' AND DATE(updated_at) = ?");
$stmtDoneToday->execute([$today]);
$doneTodayCount = (int)$stmtDoneToday->fetchColumn();

// 4. Transaksi Hari Ini Kasir
$stmtTrxToday = $db->prepare("SELECT COUNT(*) FROM transaksi WHERE DATE(tanggal_transaksi) = ?");
$stmtTrxToday->execute([$today]);
$trxTodayCount = (int)$stmtTrxToday->fetchColumn();

// 5. Total Pendapatan Kasir Hari Ini
$stmtRevToday = $db->prepare("SELECT COALESCE(SUM(total_akhir), 0) FROM transaksi WHERE DATE(tanggal_transaksi) = ? AND status_pembayaran = 'lunas'");
$stmtRevToday->execute([$today]);
$revToday = (float)$stmtRevToday->fetchColumn();

// Quick active orders list
$activeOrders = $db->query("
    SELECT p.*, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan 
    FROM pesanan p 
    JOIN pelanggan plg ON p.pelanggan_id = plg.id 
    WHERE p.status_pesanan IN ('menunggu_konfirmasi', 'diproses', 'siap_dikirim', 'sedang_dikirim') 
    ORDER BY p.id ASC 
    LIMIT 6
")->fetchAll();

// Quick low stock alert
$lowStock = $db->query("
    SELECT p.nama_produk, p.satuan, s.stok_tersedia, s.stok_minimum 
    FROM stok s 
    JOIN produk p ON s.produk_id = p.id 
    WHERE s.stok_tersedia <= s.stok_minimum 
    ORDER BY s.stok_tersedia ASC 
    LIMIT 4
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body data-base-url="<?= base_url() ?>">

<?php if ($flash = get_flash()): ?>
    <div id="flash-message-data" data-type="<?= $flash['type'] ?>" data-message="<?= htmlspecialchars($flash['message']) ?>"></div>
<?php endif; ?>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar_karyawan.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="app-content">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Panel Operasional Karyawan</h3>
                    <p class="text-muted small mb-0">Kelola pengisian galon, kasir transaksi langsung, dan antaran pesanan pelanggan.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('karyawan/kasir.php') ?>" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-calculator me-1"></i> Buka Kasir POS
                    </a>
                </div>
            </div>

            <!-- Operational KPI Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="bi bi-bell"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Pesanan Baru Masuk</div>
                            <div class="stat-value"><?= $newOrdersCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon cyan">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Sedang Diproses/Antar</div>
                            <div class="stat-value"><?= $inProcessCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Pesanan Selesai Hari Ini</div>
                            <div class="stat-value"><?= $doneTodayCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Kasir Masuk Hari Ini</div>
                            <div class="stat-value fs-5"><?= format_rupiah($revToday) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Active Orders Needing Action -->
                <div class="col-lg-8">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Pesanan Aktif Perlu Tindakan Segera</h5>
                            <a href="<?= url('karyawan/pesanan.php') ?>" class="small text-decoration-none">Lihat Semua Pesanan &raquo;</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table-modern small">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Pelanggan & WhatsApp</th>
                                        <th>Tipe Antar</th>
                                        <th>Total</th>
                                        <th>Status Saat Ini</th>
                                        <th class="text-center">Aksi Cepat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($activeOrders)): ?>
                                        <?php foreach ($activeOrders as $ao): ?>
                                            <tr>
                                                <td class="fw-bold text-primary"><?= htmlspecialchars($ao['kode_pesanan']) ?></td>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($ao['nama_pelanggan']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($ao['wa_pelanggan']) ?></small>
                                                </td>
                                                <td class="text-capitalize"><?= str_replace('_', ' ', $ao['metode_pengambilan']) ?></td>
                                                <td class="fw-bold"><?= format_rupiah($ao['total_bayar']) ?></td>
                                                <td><?= status_badge($ao['status_pesanan']) ?></td>
                                                <td class="text-center">
                                                    <a href="<?= url('karyawan/pesanan.php') ?>" class="btn btn-xs btn-primary rounded-pill px-3 py-1">
                                                        Proses
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pesanan aktif saat ini. Semua pesanan telah selesai diproses!</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Stock Monitoring -->
                <div class="col-lg-4">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Status Stok Produk</h5>
                            <a href="<?= url('karyawan/stok.php') ?>" class="small text-decoration-none">Kelola Stok &raquo;</a>
                        </div>

                        <?php if (!empty($lowStock)): ?>
                            <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Perhatian: Stok produk di bawah ini perlu ditambah pasokannya.
                            </div>
                            <ul class="list-group list-group-flush small mb-3">
                                <?php foreach ($lowStock as $ls): ?>
                                    <li class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-danger"><?= htmlspecialchars($ls['nama_produk']) ?></div>
                                            <small class="text-muted">Min: <?= $ls['stok_minimum'] ?> <?= htmlspecialchars($ls['satuan']) ?></small>
                                        </div>
                                        <span class="badge bg-danger rounded-pill">Sisa: <?= $ls['stok_tersedia'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-center py-4 text-success">
                                <i class="bi bi-check-circle-fill fs-1 mb-2 d-block"></i>
                                <span class="fw-bold">Semua stok produk tersedia aman!</span>
                            </div>
                        <?php endif; ?>

                        <div class="pt-3 border-top" style="border-color: var(--card-border) !important;">
                            <a href="<?= url('karyawan/stok.php') ?>" class="btn btn-outline-success w-100 rounded-pill btn-sm py-2">
                                <i class="bi bi-plus-circle me-1"></i> Input Pasokan Galon Masuk
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>

