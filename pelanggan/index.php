<?php
$pageTitle = "Dashboard Pelanggan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['pelanggan']);

$db = Database::getConnection();
$user = current_user();

// Fetch customer ID
$stmtPlg = $db->prepare("SELECT * FROM pelanggan WHERE user_id = ?");
$stmtPlg->execute([$user['id']]);
$pelanggan = $stmtPlg->fetch();

$pelangganId = $pelanggan['id'] ?? 0;

// Metrics
$stmtTotalOrders = $db->prepare("SELECT COUNT(*) FROM pesanan WHERE pelanggan_id = ?");
$stmtTotalOrders->execute([$pelangganId]);
$totalOrders = (int)$stmtTotalOrders->fetchColumn();

$stmtActiveOrders = $db->prepare("SELECT COUNT(*) FROM pesanan WHERE pelanggan_id = ? AND status_pesanan NOT IN ('selesai', 'dibatalkan')");
$stmtActiveOrders->execute([$pelangganId]);
$activeOrders = (int)$stmtActiveOrders->fetchColumn();

$stmtTotalSpent = $db->prepare("SELECT COALESCE(SUM(total_bayar), 0) FROM pesanan WHERE pelanggan_id = ? AND status_pesanan = 'selesai'");
$stmtTotalSpent->execute([$pelangganId]);
$totalSpent = (float)$stmtTotalSpent->fetchColumn();

// Recent Orders
$stmtRecent = $db->prepare("
    SELECT p.*, 
           (SELECT COUNT(*) FROM detail_pesanan WHERE pesanan_id = p.id) as total_item 
    FROM pesanan p 
    WHERE p.pelanggan_id = ? 
    ORDER BY p.id DESC 
    LIMIT 5
");
$stmtRecent->execute([$pelangganId]);
$recentOrders = $stmtRecent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <!-- Header -->
    <div class="glass-card p-4 p-md-5 mb-4" data-aos="fade-down" style="background: linear-gradient(135deg, rgba(25, 118, 210, 0.08) 0%, rgba(100, 181, 246, 0.08) 100%);">
        <div class="row align-items-center">
            <div class="col-md-8">
                <span class="badge bg-primary rounded-pill px-3 py-2 mb-2">Member Pelanggan #<?= htmlspecialchars($pelanggan['kode_pelanggan'] ?? 'PLG-001') ?></span>
                <h2 class="fw-bold mb-1">Halo, <?= htmlspecialchars($user['nama_lengkap']) ?>! 👋</h2>
                <p class="text-muted mb-0">Selamat datang di akun pelanggan AquaDepot. Pantau pesanan air galon Anda dan lakukan pemesanan ulang dengan cepat.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= url('views/produk.php') ?>" class="btn btn-primary rounded-pill px-4 py-2 shadow">
                    <i class="bi bi-cart3 me-1"></i> Pesan Air Lagi
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-bag-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-title">Total Pesanan</div>
                    <div class="stat-value"><?= $totalOrders ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-truck"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-title">Pesanan Sedang Berjalan</div>
                    <div class="stat-value"><?= $activeOrders ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-title">Total Belanja Selesai</div>
                    <div class="stat-value fs-5"><?= format_rupiah($totalSpent) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="glass-card p-4" data-aos="fade-up">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Pesanan Terakhir Anda</h5>
            <a href="<?= url('pelanggan/riwayat.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                Lihat Semua Riwayat <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (!empty($recentOrders)): ?>
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th>Total Bayar</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ord): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($ord['kode_pesanan']) ?></td>
                                <td><?= tanggal_indo($ord['tanggal_pesanan'], true) ?></td>
                                <td class="text-capitalize"><?= str_replace('_', ' ', $ord['metode_pengambilan']) ?></td>
                                <td class="fw-bold"><?= format_rupiah($ord['total_bayar']) ?></td>
                                <td><?= status_badge($ord['status_pesanan']) ?></td>
                                <td class="text-center">
                                    <a href="<?= url('views/cetak_invoice.php?pesanan_id=' . $ord['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Cetak Invoice">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-bag-x fs-1 text-muted mb-2 d-block"></i>
                <p class="text-muted">Anda belum memiliki riwayat pesanan.</p>
                <a href="<?= url('views/produk.php') ?>" class="btn btn-primary rounded-pill px-4">Pesan Galon Sekarang</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

