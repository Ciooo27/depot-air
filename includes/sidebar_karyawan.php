<?php
require_once __DIR__ . '/../config/config.php';
$pengaturan = get_pengaturan();

try {
    $db = Database::getConnection();
    $stmtPending = $db->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'menunggu_konfirmasi'");
    $pendingOrdersCount = (int)$stmtPending->fetchColumn();
} catch (Exception $e) {
    $pendingOrdersCount = 0;
}

$currentFile = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon" style="background: linear-gradient(135deg, #0288D1 0%, #26C6DA 100%);">
            <i class="bi bi-droplet-half"></i>
        </div>
        <div class="overflow-hidden">
            <h6 class="mb-0 fw-bold text-truncate"><?= htmlspecialchars($pengaturan['nama_depot']) ?></h6>
            <span class="badge bg-info text-dark text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">Panel Karyawan</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="menu-title">Operasional Utama</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'index.php') ? 'active' : '' ?>" href="<?= url('karyawan/index.php') ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard Operasional</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'kasir.php') ? 'active' : '' ?>" href="<?= url('karyawan/kasir.php') ?>">
                    <i class="bi bi-calculator"></i>
                    <span>Kasir POS (Input Transaksi)</span>
                </a>
            </li>
        </ul>

        <div class="menu-title">Pesanan & Pengiriman</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'pesanan.php') ? 'active' : '' ?>" href="<?= url('karyawan/pesanan.php') ?>">
                    <i class="bi bi-bag-check"></i>
                    <span>Daftar Pesanan</span>
                    <?php if ($pendingOrdersCount > 0): ?>
                        <span class="badge rounded-pill bg-warning text-dark ms-auto"><?= $pendingOrdersCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'pengiriman.php') ? 'active' : '' ?>" href="<?= url('karyawan/pengiriman.php') ?>">
                    <i class="bi bi-truck"></i>
                    <span>Kelola Pengiriman</span>
                </a>
            </li>
        </ul>

        <div class="menu-title">Stok & Pelanggan</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'stok.php') ? 'active' : '' ?>" href="<?= url('karyawan/stok.php') ?>">
                    <i class="bi bi-stack"></i>
                    <span>Perbarui Stok</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'pelanggan.php') ? 'active' : '' ?>" href="<?= url('karyawan/pelanggan.php') ?>">
                    <i class="bi bi-people"></i>
                    <span>Data Pelanggan</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="p-3 border-top mt-auto" style="border-color: var(--card-border) !important;">
        <a href="<?= url('auth/logout.php') ?>" class="btn btn-outline-danger w-100 rounded-pill py-2 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i>
            <span>Keluar Sistem</span>
        </a>
    </div>
</aside>

