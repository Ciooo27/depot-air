<?php
$pageTitle = "Laporan & Rekapitulasi - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

$tab = $_GET['tab'] ?? 'penjualan'; // 'penjualan', 'keuntungan', 'terlaris', 'stok'
$periode = $_GET['periode'] ?? 'bulan_ini'; // 'hari_ini', 'minggu_ini', 'bulan_ini', 'tahun_ini', 'custom'
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Determine date range based on period
if ($periode === 'hari_ini') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($periode === 'minggu_ini') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d');
} elseif ($periode === 'bulan_ini') {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
} elseif ($periode === 'tahun_ini') {
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
}

// 1. Data Penjualan & Keuntungan
$stmtReport = $db->prepare("
    SELECT t.id, t.kode_transaksi, t.tanggal_transaksi, t.metode_pembayaran, t.subtotal, t.diskon, t.biaya_pengiriman, t.total_akhir,
           plg.nama as nama_pelanggan,
           dt.nama_produk, dt.jumlah, dt.harga_satuan, dt.subtotal as subtotal_item,
           p.harga_modal, (p.harga_modal * dt.jumlah) as total_modal,
           (dt.subtotal - (p.harga_modal * dt.jumlah)) as keuntungan_item
    FROM transaksi t
    JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    JOIN produk p ON dt.produk_id = p.id
    LEFT JOIN pelanggan plg ON t.pelanggan_id = plg.id
    WHERE DATE(t.tanggal_transaksi) BETWEEN ? AND ? AND t.status_pembayaran = 'lunas'
    ORDER BY t.tanggal_transaksi DESC
");
$stmtReport->execute([$startDate, $endDate]);
$reportRows = $stmtReport->fetchAll();

// Aggregations
$grandTotalOmset = 0;
$grandTotalModal = 0;
$grandTotalQty = 0;

foreach ($reportRows as $r) {
    $grandTotalOmset += $r['subtotal_item'];
    $grandTotalModal += $r['total_modal'];
    $grandTotalQty += $r['jumlah'];
}
$grandTotalLaba = $grandTotalOmset - $grandTotalModal;

// 2. Data Produk Terlaris
$stmtBest = $db->prepare("
    SELECT dt.nama_produk, SUM(dt.jumlah) as total_terjual, SUM(dt.subtotal) as total_omset
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    WHERE DATE(t.tanggal_transaksi) BETWEEN ? AND ? AND t.status_pembayaran = 'lunas'
    GROUP BY dt.nama_produk
    ORDER BY total_terjual DESC
");
$stmtBest->execute([$startDate, $endDate]);
$bestSellers = $stmtBest->fetchAll();

// 3. Data Stok Opname
$stmtStockReport = $db->query("
    SELECT p.kode_produk, p.nama_produk, p.satuan, p.harga_modal, p.harga_jual, s.stok_tersedia, s.stok_minimum, k.nama_kategori,
           (p.harga_modal * s.stok_tersedia) as aset_modal,
           (p.harga_jual * s.stok_tersedia) as potensi_omset
    FROM produk p
    JOIN kategori k ON p.kategori_id = k.id
    JOIN stok s ON p.id = s.produk_id
    ORDER BY k.nama_kategori ASC, p.nama_produk ASC
");
$stockReport = $stmtStockReport->fetchAll();
$totalAsetStok = array_sum(array_column($stockReport, 'aset_modal'));

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_AquaDepot_' . $startDate . '_sd_' . $endDate . '.csv');
    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($tab === 'stok') {
        fputcsv($output, ['Kode Produk', 'Nama Produk', 'Kategori', 'Satuan', 'Stok Tersedia', 'Stok Minimum', 'Harga Modal', 'Harga Jual', 'Aset Modal']);
        foreach ($stockReport as $s) {
            fputcsv($output, [
                $s['kode_produk'], $s['nama_produk'], $s['nama_kategori'], $s['satuan'],
                $s['stok_tersedia'], $s['stok_minimum'], $s['harga_modal'], $s['harga_jual'], $s['aset_modal']
            ]);
        }
    } else {
        fputcsv($output, ['No. Transaksi', 'Tanggal', 'Pelanggan', 'Produk', 'Jumlah', 'Harga Satuan', 'Total Penjualan', 'Harga Modal', 'Total Modal', 'Laba Bersih']);
        foreach ($reportRows as $r) {
            fputcsv($output, [
                $r['kode_transaksi'], $r['tanggal_transaksi'], $r['nama_pelanggan'] ?: 'Walk-in',
                $r['nama_produk'], $r['jumlah'], $r['harga_satuan'], $r['subtotal_item'],
                $r['harga_modal'], $r['total_modal'], $r['keuntungan_item']
            ]);
        }
    }
    fclose($output);
    exit;
}
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

<div class="app-wrapper">
    <?php require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="app-content">
            <!-- Header & Export Action -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">
                <div>
                    <h3 class="fw-bold mb-1">Laporan & Analisis Penjualan</h3>
                    <p class="text-muted small mb-0">Rekapitulasi omset pendapatan, laba kotor keuntungan depot, dan audit stok opname.</p>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-3 shadow-sm">
                        <i class="bi bi-printer me-1"></i> Cetak Laporan (PDF)
                    </button>
                    <a href="<?= url('admin/laporan.php?export=csv&tab=' . $tab . '&start_date=' . $startDate . '&end_date=' . $endDate) ?>" class="btn btn-success rounded-pill px-3 shadow-sm">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export ke Excel / CSV
                    </a>
                </div>
            </div>

            <!-- Periode Filter Bar -->
            <div class="glass-card p-3 mb-4 no-print">
                <form method="GET" action="" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Pilihan Cepat Periode</label>
                        <select name="periode" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="hari_ini" <?= ($periode === 'hari_ini') ? 'selected' : '' ?>>Hari Ini (<?= date('d M Y') ?>)</option>
                            <option value="minggu_ini" <?= ($periode === 'minggu_ini') ? 'selected' : '' ?>>Minggu Ini</option>
                            <option value="bulan_ini" <?= ($periode === 'bulan_ini') ? 'selected' : '' ?>>Bulan Ini (<?= date('F Y') ?>)</option>
                            <option value="tahun_ini" <?= ($periode === 'tahun_ini') ? 'selected' : '' ?>>Tahun Ini (<?= date('Y') ?>)</option>
                            <option value="custom" <?= ($periode === 'custom') ? 'selected' : '' ?>>Rentang Tanggal Kustom</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                    </div>

                    <div class="col-md-3 text-md-end">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">Tampilkan Laporan</button>
                    </div>
                </form>
            </div>

            <!-- Tab Pills -->
            <ul class="nav nav-pills mb-4 no-print gap-2">
                <li class="nav-item">
                    <a class="nav-link <?= ($tab === 'penjualan') ? 'active' : 'bg-white' ?>" href="<?= url('admin/laporan.php?tab=penjualan&start_date=' . $startDate . '&end_date=' . $endDate) ?>">
                        <i class="bi bi-cart-check me-1"></i> Laporan Omset & Keuntungan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($tab === 'terlaris') ? 'active' : 'bg-white' ?>" href="<?= url('admin/laporan.php?tab=terlaris&start_date=' . $startDate . '&end_date=' . $endDate) ?>">
                        <i class="bi bi-star me-1"></i> Produk Terlaris
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($tab === 'stok') ? 'active' : 'bg-white' ?>" href="<?= url('admin/laporan.php?tab=stok') ?>">
                        <i class="bi bi-box-seam me-1"></i> Laporan Aset & Stok Barang
                    </a>
                </li>
            </ul>

            <!-- Summary KPI Cards -->
            <?php if ($tab !== 'stok'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon blue"><i class="bi bi-droplet"></i></div>
                            <div class="stat-info">
                                <div class="stat-title">Total Galon / Item Terjual</div>
                                <div class="stat-value"><?= number_format($grandTotalQty) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon cyan"><i class="bi bi-cash-stack"></i></div>
                            <div class="stat-info">
                                <div class="stat-title">Total Omset Pendapatan</div>
                                <div class="stat-value fs-5 text-primary"><?= format_rupiah($grandTotalOmset) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon orange"><i class="bi bi-wallet2"></i></div>
                            <div class="stat-info">
                                <div class="stat-title">Total Modal Pokok (HPP)</div>
                                <div class="stat-value fs-5 text-muted"><?= format_rupiah($grandTotalModal) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon green"><i class="bi bi-graph-up-arrow"></i></div>
                            <div class="stat-info">
                                <div class="stat-title">Total Keuntungan Bersih</div>
                                <div class="stat-value fs-5 text-success"><?= format_rupiah($grandTotalLaba) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon blue"><i class="bi bi-boxes"></i></div>
                            <div class="stat-info">
                                <div class="stat-title">Total Jenis Produk</div>
                                <div class="stat-value"><?= count($stockReport) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon green"><i class="bi bi-safe"></i></div>
                            <div class="stat-info">
                                <div class="stat-title">Total Nilai Aset Modal Stok</div>
                                <div class="stat-value text-success"><?= format_rupiah($totalAsetStok) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Table Section -->
            <div class="glass-card p-4">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <?php if ($tab === 'penjualan'): ?>
                            Rincian Penjualan & Margin Keuntungan (<?= tanggal_indo($startDate) ?> s/d <?= tanggal_indo($endDate) ?>)
                        <?php elseif ($tab === 'terlaris'): ?>
                            Peringkat Produk Paling Laris (<?= tanggal_indo($startDate) ?> s/d <?= tanggal_indo($endDate) ?>)
                        <?php else: ?>
                            Audit Fisik Stok & Valuasi Aset Gudang
                        <?php endif; ?>
                    </h5>
                    <small class="text-muted">Status: Lunas</small>
                </div>

                <div class="table-responsive">
                    <?php if ($tab === 'penjualan'): ?>
                        <table class="table-modern small">
                            <thead>
                                <tr>
                                    <th>No. Transaksi</th>
                                    <th>Tanggal</th>
                                    <th>Pelanggan</th>
                                    <th>Produk</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Harga Jual</th>
                                    <th class="text-end">Total Omset</th>
                                    <th class="text-end">Modal</th>
                                    <th class="text-end">Keuntungan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($reportRows)): ?>
                                    <?php foreach ($reportRows as $row): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($row['kode_transaksi']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($row['tanggal_transaksi'])) ?></td>
                                            <td><?= htmlspecialchars($row['nama_pelanggan'] ?: 'Walk-In') ?></td>
                                            <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                                            <td class="text-center fw-bold"><?= $row['jumlah'] ?></td>
                                            <td class="text-end"><?= format_rupiah($row['harga_satuan']) ?></td>
                                            <td class="text-end fw-bold"><?= format_rupiah($row['subtotal_item']) ?></td>
                                            <td class="text-end text-muted"><?= format_rupiah($row['total_modal']) ?></td>
                                            <td class="text-end fw-bold text-success"><?= format_rupiah($row['keuntungan_item']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data transaksi pada rentang periode ini.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    <?php elseif ($tab === 'terlaris'): ?>
                        <table class="table-modern small">
                            <thead>
                                <tr>
                                    <th style="width: 8%;">Peringkat</th>
                                    <th>Nama Produk</th>
                                    <th class="text-center">Total Unit Terjual</th>
                                    <th class="text-end">Total Omset Penjualan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($bestSellers as $bs): ?>
                                    <tr>
                                        <td class="fw-bold">
                                            <?php if ($rank === 1): ?><span class="badge bg-warning text-dark"><i class="bi bi-trophy-fill me-1"></i>#1</span>
                                            <?php elseif ($rank === 2): ?><span class="badge bg-secondary">#2</span>
                                            <?php elseif ($rank === 3): ?><span class="badge bg-danger-subtle text-danger">#3</span>
                                            <?php else: ?>#<?= $rank ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold"><?= htmlspecialchars($bs['nama_produk']) ?></td>
                                        <td class="text-center fw-bold fs-6 text-primary"><?= number_format($bs['total_terjual']) ?></td>
                                        <td class="text-end fw-bold"><?= format_rupiah($bs['total_omset']) ?></td>
                                    </tr>
                                <?php $rank++; endforeach; ?>
                            </tbody>
                        </table>

                    <?php else: ?>
                        <table class="table-modern small">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th class="text-center">Stok Fisik</th>
                                    <th class="text-end">Harga Modal</th>
                                    <th class="text-end">Harga Jual</th>
                                    <th class="text-end">Nilai Aset Modal</th>
                                    <th class="text-end">Potensi Omset</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockReport as $sr): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($sr['kode_produk']) ?></code></td>
                                        <td class="fw-bold"><?= htmlspecialchars($sr['nama_produk']) ?></td>
                                        <td><?= htmlspecialchars($sr['nama_kategori']) ?></td>
                                        <td class="text-center fw-bold <?= ($sr['stok_tersedia'] <= $sr['stok_minimum']) ? 'text-danger' : 'text-success' ?>">
                                            <?= $sr['stok_tersedia'] ?> <?= htmlspecialchars($sr['satuan']) ?>
                                        </td>
                                        <td class="text-end"><?= format_rupiah($sr['harga_modal']) ?></td>
                                        <td class="text-end"><?= format_rupiah($sr['harga_jual']) ?></td>
                                        <td class="text-end fw-bold"><?= format_rupiah($sr['aset_modal']) ?></td>
                                        <td class="text-end fw-bold text-primary"><?= format_rupiah($sr['potensi_omset']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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

