<?php
require_once __DIR__ . '/../config/config.php';

$pesananId = (int)($_GET['pesanan_id'] ?? 0);
$transaksiId = (int)($_GET['transaksi_id'] ?? 0);

$db = Database::getConnection();
$pengaturan = get_pengaturan();

$title = 'INVOICE';
$invoiceNumber = '';
$tanggal = '';
$customerName = '';
$customerPhone = '';
$customerAddress = '';
$metodeBayar = '';
$statusBayar = '';
$subtotal = 0;
$diskon = 0;
$ongkir = 0;
$total = 0;
$bayar = 0;
$kembalian = 0;
$kasirName = '-';
$items = [];

if ($pesananId > 0) {
    $stmt = $db->prepare("
        SELECT p.*, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan, plg.alamat as alamat_pelanggan 
        FROM pesanan p 
        JOIN pelanggan plg ON p.pelanggan_id = plg.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$pesananId]);
    $order = $stmt->fetch();

    if (!$order) {
        die("Pesanan tidak ditemukan.");
    }

    $title = 'INVOICE PESANAN';
    $invoiceNumber = $order['kode_pesanan'];
    $tanggal = $order['tanggal_pesanan'];
    $customerName = $order['nama_pelanggan'];
    $customerPhone = $order['wa_pelanggan'];
    $customerAddress = $order['alamat_pengiriman'] ?: $order['alamat_pelanggan'];
    $metodeBayar = strtoupper($order['metode_pembayaran']);
    $statusBayar = strtoupper($order['status_pesanan']);
    $subtotal = $order['total_harga'];
    $ongkir = $order['biaya_pengiriman'];
    $total = $order['total_bayar'];

    // Items
    $stmtItems = $db->prepare("
        SELECT dp.*, prd.nama_produk, prd.satuan 
        FROM detail_pesanan dp 
        JOIN produk prd ON dp.produk_id = prd.id 
        WHERE dp.pesanan_id = ?
    ");
    $stmtItems->execute([$pesananId]);
    $items = $stmtItems->fetchAll();

} elseif ($transaksiId > 0) {
    $stmt = $db->prepare("
        SELECT t.*, u.nama_lengkap as nama_kasir, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan, plg.alamat as alamat_pelanggan 
        FROM transaksi t 
        LEFT JOIN users u ON t.kasir_user_id = u.id 
        LEFT JOIN pelanggan plg ON t.pelanggan_id = plg.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$transaksiId]);
    $trx = $stmt->fetch();

    if (!$trx) {
        die("Transaksi tidak ditemukan.");
    }

    $title = 'STRUK TRANSAKSI';
    $invoiceNumber = $trx['kode_transaksi'];
    $tanggal = $trx['tanggal_transaksi'];
    $customerName = $trx['nama_pelanggan'] ?: 'Pelanggan Walk-In';
    $customerPhone = $trx['wa_pelanggan'] ?: '-';
    $customerAddress = $trx['alamat_pelanggan'] ?: '-';
    $metodeBayar = strtoupper($trx['metode_pembayaran']);
    $statusBayar = strtoupper($trx['status_pembayaran']);
    $subtotal = $trx['subtotal'];
    $diskon = $trx['diskon'];
    $ongkir = $trx['biaya_pengiriman'];
    $total = $trx['total_akhir'];
    $bayar = $trx['jumlah_bayar'];
    $kembalian = $trx['kembalian'];
    $kasirName = $trx['nama_kasir'] ?: 'Admin/Kasir';

    // Items
    $stmtItems = $db->prepare("SELECT * FROM detail_transaksi WHERE transaksi_id = ?");
    $stmtItems->execute([$transaksiId]);
    $items = $stmtItems->fetchAll();

} else {
    die("Parameter invoice tidak valid.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($invoiceNumber) ?> - AquaDepot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            padding: 30px;
        }
        .invoice-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }
        .brand-title {
            color: #1976D2;
            font-weight: 800;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .invoice-card {
                box-shadow: none;
                border: none;
                padding: 10px;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="invoice-card">
    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print pb-3 border-bottom">
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="bi bi-printer-fill me-1"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Header -->
    <div class="row align-items-start mb-4">
        <div class="col-sm-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-droplet-half fs-2 text-primary"></i>
                <h3 class="brand-title mb-0"><?= htmlspecialchars($pengaturan['nama_depot']) ?></h3>
            </div>
            <p class="small text-muted mb-0">
                <?= nl2br(htmlspecialchars($pengaturan['alamat'])) ?><br>
                Telp/WA: <?= htmlspecialchars($pengaturan['no_whatsapp']) ?> | Email: <?= htmlspecialchars($pengaturan['email']) ?>
            </p>
        </div>
        <div class="col-sm-5 text-sm-end mt-3 mt-sm-0">
            <h4 class="fw-bold mb-1 text-uppercase text-secondary"><?= $title ?></h4>
            <div class="fw-bold text-primary fs-5"><?= htmlspecialchars($invoiceNumber) ?></div>
            <small class="text-muted d-block">Tanggal: <?= tanggal_indo($tanggal, true) ?></small>
            <span class="badge bg-success-subtle text-success border border-success mt-1"><?= $statusBayar ?></span>
        </div>
    </div>

    <hr>

    <!-- Customer & Transaction Info -->
    <div class="row mb-4 small">
        <div class="col-sm-6">
            <h6 class="fw-bold text-primary mb-2">Ditujukan Kepada:</h6>
            <div class="fw-bold fs-6"><?= htmlspecialchars($customerName) ?></div>
            <div>No. Telp/WA: <?= htmlspecialchars($customerPhone) ?></div>
            <div class="text-muted">Alamat: <?= nl2br(htmlspecialchars($customerAddress)) ?></div>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <h6 class="fw-bold text-primary mb-2">Rincian Pembayaran:</h6>
            <div>Metode: <strong><?= $metodeBayar ?></strong></div>
            <?php if ($kasirName !== '-'): ?>
                <div>Petugas Kasir: <strong><?= htmlspecialchars($kasirName) ?></strong></div>
            <?php endif; ?>
            <div class="text-muted">Waktu Cetak: <?= date('d/m/Y H:i:s') ?> WIB</div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-striped small">
            <thead class="table-primary">
                <tr>
                    <th style="width: 5%;">No</th>
                    <th>Deskripsi Produk / Item</th>
                    <th class="text-center" style="width: 12%;">Qty</th>
                    <th class="text-end" style="width: 20%;">Harga Satuan</th>
                    <th class="text-end" style="width: 22%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['nama_produk']) ?></strong>
                            <?php if (!empty($item['satuan'])): ?>
                                <small class="text-muted d-block">(<?= htmlspecialchars($item['satuan']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold"><?= $item['jumlah'] ?></td>
                        <td class="text-end"><?= format_rupiah($item['harga_satuan']) ?></td>
                        <td class="text-end fw-bold"><?= format_rupiah($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="text-end">Subtotal:</th>
                    <th class="text-end"><?= format_rupiah($subtotal) ?></th>
                </tr>
                <?php if ($diskon > 0): ?>
                    <tr>
                        <th colspan="4" class="text-end text-danger">Diskon / Potongan:</th>
                        <th class="text-end text-danger">- <?= format_rupiah($diskon) ?></th>
                    </tr>
                <?php endif; ?>
                <?php if ($ongkir > 0): ?>
                    <tr>
                        <th colspan="4" class="text-end">Biaya Pengiriman:</th>
                        <th class="text-end"><?= format_rupiah($ongkir) ?></th>
                    </tr>
                <?php endif; ?>
                <tr class="table-light fs-6">
                    <th colspan="4" class="text-end text-primary">TOTAL AKHIR:</th>
                    <th class="text-end text-primary fw-bold"><?= format_rupiah($total) ?></th>
                </tr>
                <?php if ($bayar > 0): ?>
                    <tr>
                        <th colspan="4" class="text-end">Jumlah Bayar:</th>
                        <th class="text-end"><?= format_rupiah($bayar) ?></th>
                    </tr>
                    <tr>
                        <th colspan="4" class="text-end">Kembalian:</th>
                        <th class="text-end"><?= format_rupiah($kembalian) ?></th>
                    </tr>
                <?php endif; ?>
            </tfoot>
        </table>
    </div>

    <!-- Notes & Signatures -->
    <div class="row align-items-end mt-4 pt-3 border-top small text-muted">
        <div class="col-8">
            <h6 class="fw-bold mb-1">Catatan:</h6>
            <ul class="ps-3 mb-0">
                <li>Simpan struk / invoice ini sebagai bukti transaksi resmi.</li>
                <li>Terima kasih telah mempercayakan kebutuhan air minum bersih Anda pada <?= htmlspecialchars($pengaturan['nama_depot']) ?>.</li>
                <li>Layanan isi ulang air higienis teruji klinis dan disterilkan secara berkala.</li>
            </ul>
        </div>
        <div class="col-4 text-center">
            <p class="mb-4">Hormat Kami,</p>
            <div class="fw-bold text-dark mt-4 border-top pt-2 d-inline-block px-4">
                <?= htmlspecialchars($kasirName !== '-' ? $kasirName : $pengaturan['nama_depot']) ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>

