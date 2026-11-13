<?php
$pageTitle = "Dashboard Admin - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// 1. KPI Statistics
$today = date('Y-m-d');

// Pendapatan Hari Ini
$stmtRevToday = $db->prepare("SELECT COALESCE(SUM(total_akhir), 0) FROM transaksi WHERE DATE(tanggal_transaksi) = ? AND status_pembayaran = 'lunas'");
$stmtRevToday->execute([$today]);
$revenueToday = (float)$stmtRevToday->fetchColumn();

// Transaksi Hari Ini
$stmtTrxToday = $db->prepare("SELECT COUNT(*) FROM transaksi WHERE DATE(tanggal_transaksi) = ?");
$stmtTrxToday->execute([$today]);
$trxTodayCount = (int)$stmtTrxToday->fetchColumn();

// Pesanan Masuk (Menunggu Konfirmasi)
$stmtOrdersIn = $db->query("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = 'menunggu_konfirmasi'");
$ordersIncomingCount = (int)$stmtOrdersIn->fetchColumn();

// Total Pelanggan
$totalPelanggan = (int)$db->query("SELECT COUNT(*) FROM pelanggan")->fetchColumn();

// Total Produk
$totalProduk = (int)$db->query("SELECT COUNT(*) FROM produk WHERE status != 'nonaktif'")->fetchColumn();

// Stok Menipis
$stmtLowStock = $db->query("
    SELECT COUNT(*) 
    FROM stok s 
    JOIN produk p ON s.produk_id = p.id 
    WHERE s.stok_tersedia <= s.stok_minimum AND p.status != 'nonaktif'
");
$lowStockCount = (int)$stmtLowStock->fetchColumn();

// 2. Chart Data: Penjualan 7 Hari Terakhir
$dailyLabels = [];
$dailySales = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyLabels[] = date('d M', strtotime($date));
    
    $stmtDaily = $db->prepare("SELECT COALESCE(SUM(total_akhir), 0) FROM transaksi WHERE DATE(tanggal_transaksi) = ? AND status_pembayaran = 'lunas'");
    $stmtDaily->execute([$date]);
    $dailySales[] = (float)$stmtDaily->fetchColumn();
}

// 3. Chart Data: Bulanan (6 Bulan Terakhir)
$monthlyLabels = [];
$monthlySales = [];
for ($m = 5; $m >= 0; $m--) {
    $ym = date('Y-m', strtotime("-$m months"));
    $monthlyLabels[] = date('M Y', strtotime($ym . '-01'));

    $stmtMonthly = $db->prepare("SELECT COALESCE(SUM(total_akhir), 0) FROM transaksi WHERE DATE_FORMAT(tanggal_transaksi, '%Y-%m') = ? AND status_pembayaran = 'lunas'");
    $stmtMonthly->execute([$ym]);
    $monthlySales[] = (float)$stmtMonthly->fetchColumn();
}

// 4. Chart Data: Top 5 Produk Terlaris
$stmtTopProd = $db->query("
    SELECT dt.nama_produk, SUM(dt.jumlah) as total_terjual
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    WHERE t.status_pembayaran = 'lunas'
    GROUP BY dt.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 5
");
$topProducts = $stmtTopProd->fetchAll();
$topLabels = [];
$topData = [];
foreach ($topProducts as $tp) {
    $topLabels[] = $tp['nama_produk'];
    $topData[] = (int)$tp['total_terjual'];
}

// 5. Recent Transactions
$stmtRecentTrx = $db->query("
    SELECT t.*, u.nama_lengkap as kasir_nama, plg.nama as pelanggan_nama 
    FROM transaksi t 
    LEFT JOIN users u ON t.kasir_user_id = u.id 
    LEFT JOIN pelanggan plg ON t.pelanggan_id = plg.id 
    ORDER BY t.id DESC 
    LIMIT 5
");
$recentTransactions = $stmtRecentTrx->fetchAll();

// 6. Recent Orders
$stmtRecentOrders = $db->query("
    SELECT p.*, plg.nama as pelanggan_nama, plg.no_telepon 
    FROM pesanan p 
    JOIN pelanggan plg ON p.pelanggan_id = plg.id 
    ORDER BY p.id DESC 
    LIMIT 5
");
$recentOrders = $stmtRecentOrders->fetchAll();

// 7. Low Stock Alerts List
$stmtAlertStock = $db->query("
    SELECT p.nama_produk, p.satuan, s.stok_tersedia, s.stok_minimum, k.nama_kategori 
    FROM stok s 
    JOIN produk p ON s.produk_id = p.id 
    JOIN kategori k ON p.kategori_id = k.id 
    WHERE s.stok_tersedia <= s.stok_minimum AND p.status != 'nonaktif' 
    ORDER BY s.stok_tersedia ASC 
    LIMIT 5
");
$alertStockItems = $stmtAlertStock->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Google Fonts & Bootstrap -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-base-url="<?= base_url() ?>">

<?php if ($flash = get_flash()): ?>
    <div id="flash-message-data" data-type="<?= $flash['type'] ?>" data-message="<?= htmlspecialchars($flash['message']) ?>"></div>
<?php endif; ?>

<div class="app-wrapper">
    <!-- Admin Sidebar -->
    <?php require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Topbar -->
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="app-content">
            <!-- Page Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Dashboard Ikhtisar Manajemen</h3>
                    <p class="text-muted small mb-0">Pantau performa penjualan, pesanan masuk, dan ketersediaan stok depot secara real-time.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('admin/pesanan.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="bi bi-bell me-1"></i> Pesanan Masuk (<?= $ordersIncomingCount ?>)
                    </a>
                    <a href="<?= url('admin/transaksi.php') ?>" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> Transaksi Kasir
                    </a>
                </div>
            </div>

            <!-- Notification Banner if orders waiting -->
            <?php if ($ordersIncomingCount > 0): ?>
                <div class="alert alert-warning border-0 rounded-4 shadow-sm p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-warning text-dark p-2 fs-5">
                            <i class="bi bi-bell-fill"></i>
                        </div>
                        <div>
                            <strong class="d-block">Pemberitahuan Pesanan Baru Masuk!</strong>
                            <small class="text-muted">Ada <strong><?= $ordersIncomingCount ?> pesanan</strong> pelanggan online yang menunggu konfirmasi dan proses pengiriman.</small>
                        </div>
                    </div>
                    <a href="<?= url('admin/pesanan.php') ?>" class="btn btn-dark btn-sm rounded-pill px-3">Proses Sekarang</a>
                </div>
            <?php endif; ?>

            <!-- 6 KPI Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Pendapatan Hari Ini</div>
                            <div class="stat-value" style="font-size: 1.15rem;"><?= format_rupiah($revenueToday) ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Transaksi Hari Ini</div>
                            <div class="stat-value"><?= $trxTodayCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="bi bi-cart-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Pesanan Masuk</div>
                            <div class="stat-value"><?= $ordersIncomingCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon cyan">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Total Pelanggan</div>
                            <div class="stat-value"><?= $totalPelanggan ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Total Produk</div>
                            <div class="stat-value"><?= $totalProduk ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon <?= ($lowStockCount > 0) ? 'red' : 'green' ?>">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-title">Stok Menipis</div>
                            <div class="stat-value <?= ($lowStockCount > 0) ? 'text-danger' : '' ?>"><?= $lowStockCount ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- Daily Sales Line/Bar Chart -->
                <div class="col-lg-8">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Tren Penjualan 7 Hari Terakhir (Rp)</h6>
                            <small class="text-muted">Update Otomatis Database</small>
                        </div>
                        <div style="height: 280px;">
                            <canvas id="dailySalesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Best Sellers Doughnut Chart -->
                <div class="col-lg-4">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Produk Paling Laris</h6>
                            <small class="text-muted">Volume Galon/Item</small>
                        </div>
                        <div style="height: 280px; position: relative;">
                            <canvas id="bestSellersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Sales & Low Stock Alert Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Grafik Penjualan Bulanan (6 Bulan)</h6>
                            <a href="<?= url('admin/laporan.php') ?>" class="small text-decoration-none">Detail Laporan &raquo;</a>
                        </div>
                        <div style="height: 240px;">
                            <canvas id="monthlySalesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-bell-fill me-1"></i> Peringatan Stok Menipis</h6>
                            <a href="<?= url('admin/stok.php') ?>" class="small text-decoration-none">Kelola Stok &raquo;</a>
                        </div>
                        <?php if (!empty($alertStockItems)): ?>
                            <ul class="list-group list-group-flush small">
                                <?php foreach ($alertStockItems as $st): ?>
                                    <li class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-danger"><?= htmlspecialchars($st['nama_produk']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($st['nama_kategori']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-danger rounded-pill">Sisa: <?= $st['stok_tersedia'] ?></span>
                                            <small class="text-muted d-block">Min: <?= $st['stok_minimum'] ?></small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-center py-4 text-success">
                                <i class="bi bi-check-circle fs-1 mb-2 d-block"></i>
                                <span class="fw-semibold">Semua stok produk dalam kondisi aman!</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Orders & Transactions Tables -->
            <div class="row g-4">
                <!-- Recent Orders -->
                <div class="col-lg-6">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Pesanan Pelanggan Terbaru</h6>
                            <a href="<?= url('admin/pesanan.php') ?>" class="small text-decoration-none">Lihat Semua</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table-modern small">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Pelanggan</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentOrders)): ?>
                                        <?php foreach ($recentOrders as $ro): ?>
                                            <tr>
                                                <td class="fw-bold text-primary"><?= htmlspecialchars($ro['kode_pesanan']) ?></td>
                                                <td><?= htmlspecialchars($ro['pelanggan_nama']) ?></td>
                                                <td class="fw-bold"><?= format_rupiah($ro['total_bayar']) ?></td>
                                                <td><?= status_badge($ro['status_pesanan']) ?></td>
                                                <td>
                                                    <a href="<?= url('admin/pesanan.php') ?>" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1">
                                                        <i class="bi bi-arrow-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">Belum ada pesanan terbaru.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="col-lg-6">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Transaksi Kasir Terbaru</h6>
                            <a href="<?= url('admin/transaksi.php') ?>" class="small text-decoration-none">Lihat Semua</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table-modern small">
                                <thead>
                                    <tr>
                                        <th>Kode Transaksi</th>
                                        <th>Pelanggan</th>
                                        <th>Total Akhir</th>
                                        <th>Metode</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentTransactions)): ?>
                                        <?php foreach ($recentTransactions as $rt): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($rt['kode_transaksi']) ?></td>
                                                <td><?= htmlspecialchars($rt['pelanggan_nama'] ?: 'Walk-In') ?></td>
                                                <td class="fw-bold text-success"><?= format_rupiah($rt['total_akhir']) ?></td>
                                                <td class="text-uppercase"><?= htmlspecialchars($rt['metode_pembayaran']) ?></td>
                                                <td>
                                                    <a href="<?= url('views/cetak_invoice.php?transaksi_id=' . $rt['id']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1" title="Cetak Struk">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">Belum ada transaksi hari ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>

<script>
// Chart 1: Daily Sales
const ctxDaily = document.getElementById('dailySalesChart').getContext('2d');
new Chart(ctxDaily, {
    type: 'line',
    data: {
        labels: <?= json_encode($dailyLabels) ?>,
        datasets: [{
            label: 'Penjualan (Rp)',
            data: <?= json_encode($dailySales) ?>,
            borderColor: '#1976D2',
            backgroundColor: 'rgba(25, 118, 210, 0.1)',
            fill: true,
            tension: 0.35,
            pointBackgroundColor: '#1976D2',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + Number(value).toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});

// Chart 2: Best Sellers
const ctxBest = document.getElementById('bestSellersChart').getContext('2d');
new Chart(ctxBest, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($topLabels) ?>,
        datasets: [{
            data: <?= json_encode($topData) ?>,
            backgroundColor: [
                '#1976D2',
                '#64B5F6',
                '#2EAD68',
                '#F59E0B',
                '#8B5CF6'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12 } }
        }
    }
});

// Chart 3: Monthly Sales
const ctxMonthly = document.getElementById('monthlySalesChart').getContext('2d');
new Chart(ctxMonthly, {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthlyLabels) ?>,
        datasets: [{
            label: 'Pendapatan Bulanan',
            data: <?= json_encode($monthlySales) ?>,
            backgroundColor: '#64B5F6',
            hoverBackgroundColor: '#1976D2',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + Number(value).toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>

