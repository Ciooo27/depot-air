<?php
$pageTitle = "Pesanan Berhasil - AquaDepot";
require_once __DIR__ . '/../config/config.php';

$pesananId = (int)($_GET['id'] ?? 0);
if ($pesananId <= 0) {
    redirect('index.php');
}

$db = Database::getConnection();
$stmt = $db->prepare("
    SELECT p.*, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan 
    FROM pesanan p 
    JOIN pelanggan plg ON p.pelanggan_id = plg.id 
    WHERE p.id = ?
");
$stmt->execute([$pesananId]);
$pesanan = $stmt->fetch();

if (!$pesanan) {
    redirect('index.php');
}

// Fetch items
$stmtItems = $db->prepare("
    SELECT dp.*, prd.nama_produk, prd.satuan 
    FROM detail_pesanan dp 
    JOIN produk prd ON dp.produk_id = prd.id 
    WHERE dp.pesanan_id = ?
");
$stmtItems->execute([$pesananId]);
$items = $stmtItems->fetchAll();

$pengaturan = get_pengaturan();

// Format WhatsApp confirmation text
$waPhone = preg_replace('/[^0-9]/', '', $pengaturan['no_whatsapp'] ?? '6281234567890');
$pesanWa = "Halo *" . ($pengaturan['nama_depot'] ?? 'AquaDepot') . "*,\n\nSaya telah membuat pesanan baru melalui website:\n"
         . "📋 *Kode Pesanan:* " . $pesanan['kode_pesanan'] . "\n"
         . "👤 *Nama:* " . $pesanan['nama_pelanggan'] . "\n"
         . "🚚 *Metode:* " . ucfirst($pesanan['metode_pengambilan']) . "\n"
         . "💰 *Total Pembayaran:* " . format_rupiah($pesanan['total_bayar']) . " (" . strtoupper($pesanan['metode_pembayaran']) . ")\n"
         . "📍 *Alamat:* " . ($pesanan['alamat_pengiriman'] ?: '-') . "\n\n"
         . "Mohon segera diproses dan dikonfirmasi. Terima kasih!";
$waUrl = "https://wa.me/" . $waPhone . "?text=" . urlencode($pesanWa);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8" data-aos="zoom-in">
            <div class="glass-card p-4 p-md-5 text-center mb-4">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #2EAD68, #64B5F6); box-shadow: 0 10px 24px rgba(46, 173, 104, 0.4);">
                    <i class="bi bi-check-lg fs-1"></i>
                </div>
                <h3 class="fw-bold mb-1">Pesanan Anda Berhasil Dibuat!</h3>
                <p class="text-muted">Terima kasih atas pesanan Anda. Kami telah menerima rincian pemesanan air minum Anda.</p>
                <div class="badge bg-primary fs-6 px-3 py-2 rounded-pill mb-4">
                    Kode Pesanan: <?= htmlspecialchars($pesanan['kode_pesanan']) ?>
                </div>

                <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success rounded-pill px-4 py-2 shadow-sm d-flex align-items-center gap-2">
                        <i class="bi bi-whatsapp fs-5"></i>
                        <span>Konfirmasi via WhatsApp Depot</span>
                    </a>
                    <a href="<?= url('views/cetak_invoice.php?pesanan_id=' . $pesanan['id']) ?>" target="_blank" class="btn btn-outline-primary rounded-pill px-4 py-2 d-flex align-items-center gap-2">
                        <i class="bi bi-printer fs-5"></i>
                        <span>Cetak Struk / Invoice</span>
                    </a>
                    <?php if (is_logged_in() && current_user()['role_name'] === 'pelanggan'): ?>
                        <a href="<?= url('pelanggan/riwayat.php') ?>" class="btn btn-outline-secondary rounded-pill px-4 py-2">
                            <i class="bi bi-clock-history me-1"></i> Riwayat Pesanan
                        </a>
                    <?php endif; ?>
                </div>

                <div class="card bg-body border rounded-4 text-start p-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Rincian Pemesanan</h6>
                    <div class="row g-3 mb-3 small">
                        <div class="col-sm-6">
                            <span class="text-muted">Penerima:</span>
                            <div class="fw-bold"><?= htmlspecialchars($pesanan['nama_pelanggan']) ?> (<?= htmlspecialchars($pesanan['wa_pelanggan']) ?>)</div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Metode Pengambilan:</span>
                            <div class="fw-bold text-uppercase"><?= str_replace('_', ' ', $pesanan['metode_pengambilan']) ?></div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted">Alamat Pengiriman:</span>
                            <div class="fw-bold"><?= htmlspecialchars($pesanan['alamat_pengiriman'] ?: 'Ambil Sendiri di Depot') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Metode Pembayaran:</span>
                            <div class="fw-bold text-uppercase"><?= htmlspecialchars($pesanan['metode_pembayaran']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted">Status Pesanan:</span>
                            <div><?= status_badge($pesanan['status_pesanan']) ?></div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">Item Produk:</h6>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($items as $item): ?>
                            <li class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-center small">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($item['nama_produk']) ?></div>
                                    <span class="text-muted"><?= $item['jumlah'] ?> <?= htmlspecialchars($item['satuan']) ?> x <?= format_rupiah($item['harga_satuan']) ?></span>
                                </div>
                                <span class="fw-bold"><?= format_rupiah($item['subtotal']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Biaya Pengiriman</span>
                        <span><?= format_rupiah($pesanan['biaya_pengiriman']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold fs-6 text-primary border-top pt-2">
                        <span>Total Pembayaran</span>
                        <span><?= format_rupiah($pesanan['total_bayar']) ?></span>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="<?= url('index.php') ?>" class="btn btn-link text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Utama
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

