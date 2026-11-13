<?php
require_once __DIR__ . '/../config/config.php';
require_role(['karyawan', 'admin']);

$transaksiId = (int)($_GET['id'] ?? 0);
if ($transaksiId <= 0) {
    die("ID transaksi tidak valid.");
}

$db = Database::getConnection();
$pengaturan = get_pengaturan();

$stmt = $db->prepare("
    SELECT t.*, u.nama_lengkap as nama_kasir, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan 
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

$stmtItems = $db->prepare("SELECT * FROM detail_transaksi WHERE transaksi_id = ?");
$stmtItems->execute([$transaksiId]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk #<?= htmlspecialchars($trx['kode_transaksi']) ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 78mm;
            margin: 0 auto;
            padding: 10px 5px;
            font-size: 12px;
            color: #000;
            background: #fff;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .border-top { border-top: 1px dashed #000; padding-top: 5px; margin-top: 5px; }
        .border-bottom { border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        td, th { padding: 2px 0; }
        .btn-print {
            display: block;
            width: 100%;
            padding: 8px;
            background: #1976D2;
            color: #fff;
            text-align: center;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 15px;
            font-family: Arial, sans-serif;
        }
        @media print {
            .no-print { display: none !important; }
            body { width: 100%; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="no-print" style="margin-bottom: 15px;">
    <button class="btn-print" onclick="window.print()">CETAK STRUK SEKARANG</button>
</div>

<div class="text-center">
    <div class="fw-bold" style="font-size: 15px;"><?= strtoupper(htmlspecialchars($pengaturan['nama_depot'])) ?></div>
    <div><?= htmlspecialchars($pengaturan['slogan']) ?></div>
    <div style="font-size: 10px;"><?= htmlspecialchars($pengaturan['alamat']) ?></div>
    <div style="font-size: 10px;">Telp/WA: <?= htmlspecialchars($pengaturan['no_whatsapp']) ?></div>
</div>

<div class="border-top border-bottom" style="margin-top: 8px;">
    <div>No : <?= htmlspecialchars($trx['kode_transaksi']) ?></div>
    <div>Tgl: <?= date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])) ?> WIB</div>
    <div>Ksr: <?= htmlspecialchars($trx['nama_kasir'] ?: 'Operator') ?></div>
    <div>Plg: <?= htmlspecialchars($trx['nama_pelanggan'] ?: 'Umum (Walk-in)') ?></div>
</div>

<table>
    <thead>
        <tr>
            <th>Item</th>
            <th class="text-center">Qty</th>
            <th class="text-end">Harga</th>
            <th class="text-end">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td colspan="4" class="fw-bold"><?= htmlspecialchars($item['nama_produk']) ?></td>
            </tr>
            <tr>
                <td></td>
                <td class="text-center"><?= $item['jumlah'] ?></td>
                <td class="text-end"><?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="border-top" style="margin-top: 6px;">
    <table>
        <tr>
            <td>Subtotal:</td>
            <td class="text-end">Rp <?= number_format($trx['subtotal'], 0, ',', '.') ?></td>
        </tr>
        <?php if ($trx['diskon'] > 0): ?>
            <tr>
                <td>Diskon:</td>
                <td class="text-end">-Rp <?= number_format($trx['diskon'], 0, ',', '.') ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($trx['biaya_pengiriman'] > 0): ?>
            <tr>
                <td>Ongkir:</td>
                <td class="text-end">Rp <?= number_format($trx['biaya_pengiriman'], 0, ',', '.') ?></td>
            </tr>
        <?php endif; ?>
        <tr class="fw-bold" style="font-size: 13px;">
            <td>TOTAL:</td>
            <td class="text-end">Rp <?= number_format($trx['total_akhir'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>Bayar (<?= strtoupper($trx['metode_pembayaran']) ?>):</td>
            <td class="text-end">Rp <?= number_format($trx['jumlah_bayar'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>Kembali:</td>
            <td class="text-end">Rp <?= number_format($trx['kembalian'], 0, ',', '.') ?></td>
        </tr>
    </table>
</div>

<div class="text-center border-top" style="margin-top: 10px; padding-top: 8px;">
    <div class="fw-bold">TERIMA KASIH</div>
    <div style="font-size: 10px;">Air Bersih & Segar untuk Keluarga Sehat</div>
    <div style="font-size: 9px; margin-top: 4px;">AquaDepot Management System</div>
</div>

</body>
</html>

