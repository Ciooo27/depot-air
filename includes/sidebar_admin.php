<?php
require_once __DIR__ . '/../config/config.php';
$pengaturan = get_pengaturan();

// Count pending orders for badge
try {
    $db = Database::getConnection();
    $stmtPending = $db->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'menunggu_konfirmasi'");
    $pendingOrdersCount = (int)$stmtPending->fetchColumn();
    
    // Check low stock count
    $stmtLowStock = $db->query("SELECT COUNT(*) FROM stok s WHERE s.stok_tersedia <= s.stok_minimum");
    $lowStockCount = (int)$stmtLowStock->fetchColumn();
} catch (Exception $e) {
    $pendingOrdersCount = 0;
    $lowStockCount = 0;
}

$currentFile = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-droplet-half"></i>
        </div>
        <div class="overflow-hidden">
            <h6 class="mb-0 fw-bold text-truncate"><?= htmlspecialchars($pengaturan['nama_depot']) ?></h6>
            <span class="badge bg-primary text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">Panel Admin</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="menu-title">Utama</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'index.php') ? 'active' : '' ?>" href="<?= url('admin/index.php') ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="menu-title">Master Data</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'produk.php') ? 'active' : '' ?>" href="<?= url('admin/produk.php') ?>">
                    <i class="bi bi-box-seam"></i>
                    <span>Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'kategori.php') ? 'active' : '' ?>" href="<?= url('admin/kategori.php') ?>">
                    <i class="bi bi-tags"></i>
                    <span>Kategori Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'stok.php') ? 'active' : '' ?>" href="<?= url('admin/stok.php') ?>">
                    <i class="bi bi-stack"></i>
                    <span>Manajemen Stok</span>
                    <?php if ($lowStockCount > 0): ?>
                        <span class="badge rounded-pill bg-danger ms-auto"><?= $lowStockCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <div class="menu-title">Operasional & Transaksi</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'pesanan.php') ? 'active' : '' ?>" href="<?= url('admin/pesanan.php') ?>">
                    <i class="bi bi-cart-check"></i>
                    <span>Pesanan Masuk</span>
                    <?php if ($pendingOrdersCount > 0): ?>
                        <span class="badge rounded-pill bg-warning text-dark ms-auto"><?= $pendingOrdersCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'transaksi.php') ? 'active' : '' ?>" href="<?= url('admin/transaksi.php') ?>">
                    <i class="bi bi-receipt"></i>
                    <span>Riwayat Transaksi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'pengiriman.php') ? 'active' : '' ?>" href="<?= url('admin/pengiriman.php') ?>">
                    <i class="bi bi-truck"></i>
                    <span>Pengiriman</span>
                </a>
            </li>
        </ul>

        <div class="menu-title">Pengguna & Akun</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'pelanggan.php') ? 'active' : '' ?>" href="<?= url('admin/pelanggan.php') ?>">
                    <i class="bi bi-people"></i>
                    <span>Data Pelanggan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'karyawan.php') ? 'active' : '' ?>" href="<?= url('admin/karyawan.php') ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Data Karyawan</span>
                </a>
            </li>
        </ul>

        <div class="menu-title">Laporan & Pengaturan</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'laporan.php') ? 'active' : '' ?>" href="<?= url('admin/laporan.php') ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Laporan & Rekap</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentFile == 'pengaturan.php') ? 'active' : '' ?>" href="<?= url('admin/pengaturan.php') ?>">
                    <i class="bi bi-gear"></i>
                    <span>Pengaturan Depot</span>
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

