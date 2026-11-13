<?php
$pageTitle = "Riwayat Pesanan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['pelanggan']);

$db = Database::getConnection();
$user = current_user();

$stmtPlg = $db->prepare("SELECT id FROM pelanggan WHERE user_id = ?");
$stmtPlg->execute([$user['id']]);
$pelangganId = $stmtPlg->fetchColumn();

// Fetch orders with details
$stmtOrders = $db->prepare("
    SELECT p.*, pg.status_pengiriman, pg.kode_pengiriman, pg.catatan_kurir,
           u_kurir.nama_lengkap as nama_kurir, u_kurir.no_telepon as wa_kurir
    FROM pesanan p
    LEFT JOIN pengiriman pg ON p.id = pg.pesanan_id
    LEFT JOIN users u_kurir ON pg.kurir_user_id = u_kurir.id
    WHERE p.pelanggan_id = ?
    ORDER BY p.id DESC
");
$stmtOrders->execute([$pelangganId]);
$orders = $stmtOrders->fetchAll();

// Fetch details grouped by order id
$stmtDetails = $db->prepare("
    SELECT dp.*, prd.nama_produk, prd.satuan 
    FROM detail_pesanan dp
    JOIN produk prd ON dp.produk_id = prd.id
    JOIN pesanan p ON dp.pesanan_id = p.id
    WHERE p.pelanggan_id = ?
");
$stmtDetails->execute([$pelangganId]);
$allDetails = $stmtDetails->fetchAll();

$itemsByOrder = [];
foreach ($allDetails as $d) {
    $itemsByOrder[$d['pesanan_id']][] = $d;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="mb-4" data-aos="fade-down">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= url('pelanggan/index.php') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Riwayat Pesanan</li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1">Riwayat & Status Pesanan Air</h2>
                <p class="text-muted mb-0">Lacak pengantaran galon dan periksa rincian pesanan Anda.</p>
            </div>
            <a href="<?= url('views/produk.php') ?>" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-plus-circle me-1"></i> Pesan Baru
            </a>
        </div>
    </div>

    <div class="glass-card p-4" data-aos="fade-up">
        <?php if (!empty($orders)): ?>
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Pesanan</th>
                            <th>Tanggal</th>
                            <th>Item Pesanan</th>
                            <th>Total Bayar</th>
                            <th>Status Pesanan</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($orders as $ord): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <span class="fw-bold text-primary"><?= htmlspecialchars($ord['kode_pesanan']) ?></span>
                                    <div class="small text-muted text-capitalize"><?= str_replace('_', ' ', $ord['metode_pengambilan']) ?> • <?= strtoupper($ord['metode_pembayaran']) ?></div>
                                </td>
                                <td><?= tanggal_indo($ord['tanggal_pesanan'], true) ?></td>
                                <td>
                                    <?php 
                                    $orderItems = $itemsByOrder[$ord['id']] ?? [];
                                    foreach ($orderItems as $it): 
                                    ?>
                                        <div class="small">
                                            • <strong><?= $it['jumlah'] ?>x</strong> <?= htmlspecialchars($it['nama_produk']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="fw-bold"><?= format_rupiah($ord['total_bayar']) ?></td>
                                <td><?= status_badge($ord['status_pesanan']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#detailModal<?= $ord['id'] ?>">
                                            <i class="bi bi-eye me-1"></i> Detail
                                        </button>
                                        <a href="<?= url('views/cetak_invoice.php?pesanan_id=' . $ord['id']) ?>" target="_blank" class="btn btn-outline-secondary rounded-pill px-2 ms-1" title="Cetak Struk">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </div>

                                    <!-- Detail Modal -->
                                    <div class="modal fade" id="detailModal<?= $ord['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header bg-primary text-white rounded-top-4">
                                                    <h6 class="modal-title fw-bold">Detail Pesanan #<?= htmlspecialchars($ord['kode_pesanan']) ?></h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body text-start p-4">
                                                    <div class="mb-3 pb-3 border-bottom">
                                                        <div class="row g-2 small">
                                                            <div class="col-6">
                                                                <span class="text-muted">Status:</span>
                                                                <div><?= status_badge($ord['status_pesanan']) ?></div>
                                                            </div>
                                                            <div class="col-6">
                                                                <span class="text-muted">Waktu Pesan:</span>
                                                                <div class="fw-bold"><?= tanggal_indo($ord['tanggal_pesanan'], true) ?></div>
                                                            </div>
                                                            <div class="col-12 mt-2">
                                                                <span class="text-muted">Alamat Pengiriman:</span>
                                                                <div class="fw-semibold"><?= htmlspecialchars($ord['alamat_pengiriman'] ?: 'Ambil Sendiri di Depot') ?></div>
                                                            </div>
                                                            <?php if (!empty($ord['nama_kurir'])): ?>
                                                                <div class="col-12 mt-2 p-2 bg-light rounded-3">
                                                                    <span class="text-muted">Kurir Pengantar:</span>
                                                                    <div class="fw-bold text-primary"><?= htmlspecialchars($ord['nama_kurir']) ?> (<?= htmlspecialchars($ord['wa_kurir']) ?>)</div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <h6 class="fw-bold small mb-2">Rincian Barang:</h6>
                                                    <ul class="list-group list-group-flush small mb-3">
                                                        <?php foreach ($orderItems as $it): ?>
                                                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <span class="fw-bold"><?= htmlspecialchars($it['nama_produk']) ?></span>
                                                                    <div class="text-muted"><?= $it['jumlah'] ?> <?= htmlspecialchars($it['satuan']) ?> x <?= format_rupiah($it['harga_satuan']) ?></div>
                                                                </div>
                                                                <span class="fw-bold"><?= format_rupiah($it['subtotal']) ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>

                                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                                        <span>Ongkos Kirim</span>
                                                        <span><?= format_rupiah($ord['biaya_pengiriman']) ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between fw-bold text-primary fs-6 border-top pt-2">
                                                        <span>Total Tagihan</span>
                                                        <span><?= format_rupiah($ord['total_bayar']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 p-3">
                                                    <a href="<?= url('views/cetak_invoice.php?pesanan_id=' . $ord['id']) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                        <i class="bi bi-printer me-1"></i> Cetak Invoice
                                                    </a>
                                                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-journal-x fs-1 text-muted mb-2 d-block"></i>
                <h5 class="fw-bold">Belum Ada Riwayat Pesanan</h5>
                <p class="text-muted small">Pesanan yang Anda buat akan tercatat di sini lengkap dengan status pengiriman kurir.</p>
                <a href="<?= url('views/produk.php') ?>" class="btn btn-primary rounded-pill px-4">Pesan Air Minum</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

