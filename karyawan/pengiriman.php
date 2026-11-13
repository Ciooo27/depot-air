<?php
$pageTitle = "Kelola Pengiriman - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['karyawan', 'admin']);

$db = Database::getConnection();

// Handle Status & Notes Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pengirimanId = (int)$_POST['pengiriman_id'];
    $statusPengiriman = $_POST['status_pengiriman'];
    $catatanKurir = trim($_POST['catatan_kurir'] ?? '');

    try {
        $db->beginTransaction();

        $stmtUp = $db->prepare("
            UPDATE pengiriman 
            SET status_pengiriman = ?, catatan_kurir = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmtUp->execute([$statusPengiriman, $catatanKurir, $pengirimanId]);

        // Sync with pesanan
        $stmtSync = $db->prepare("
            UPDATE pesanan 
            SET status_pesanan = ?, updated_at = NOW() 
            WHERE id = (SELECT pesanan_id FROM pengiriman WHERE id = ?)
        ");
        $stmtSync->execute([$statusPengiriman, $pengirimanId]);

        $db->commit();
        set_flash('success', 'Status pengiriman berhasil diperbarui!');
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('error', 'Gagal memperbarui status: ' . $e->getMessage());
    }
    redirect('karyawan/pengiriman.php');
}

// Fetch deliveries
$deliveries = $db->query("
    SELECT pg.*, p.kode_pesanan, p.total_bayar, p.metode_pembayaran, p.catatan as catatan_pesanan,
           plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan,
           (SELECT SUM(dp.jumlah) FROM detail_pesanan dp WHERE dp.pesanan_id = p.id) as total_qty
    FROM pengiriman pg
    JOIN pesanan p ON pg.pesanan_id = p.id
    JOIN pelanggan plg ON p.pelanggan_id = plg.id
    ORDER BY (pg.status_pengiriman IN ('siap_dikirim', 'sedang_dikirim')) DESC, pg.id DESC
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
                    <h3 class="fw-bold mb-1">Armada & Operasional Pengantaran</h3>
                    <p class="text-muted small mb-0">Perbarui status pengantaran galon kepada pelanggan di lapangan secara langsung.</p>
                </div>
            </div>

            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern small">
                        <thead>
                            <tr>
                                <th>Kode Kirim</th>
                                <th>Penerima</th>
                                <th>Alamat Tujuan</th>
                                <th>Muatan & Tagihan</th>
                                <th>Status Antar</th>
                                <th>Catatan Lapangan</th>
                                <th class="text-center">Aksi Kurir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($deliveries)): ?>
                                <?php foreach ($deliveries as $del): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($del['kode_pengiriman']) ?></div>
                                            <small class="text-muted">Ref: <?= htmlspecialchars($del['kode_pesanan']) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($del['nama_pelanggan']) ?></div>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $del['wa_pelanggan']) ?>?text=<?= urlencode("Halo Bapak/Ibu " . $del['nama_pelanggan'] . ", kurir AquaDepot sedang menuju ke lokasi Anda untuk mengantar pesanan " . $del['kode_pesanan']) ?>" target="_blank" class="small text-success text-decoration-none">
                                                <i class="bi bi-whatsapp"></i> Hubungi WA
                                            </a>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold" style="max-width: 200px;"><?= htmlspecialchars($del['alamat_tujuan']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= (int)$del['total_qty'] ?> Galon/Item</div>
                                            <small class="text-primary fw-bold"><?= format_rupiah($del['total_bayar']) ?> (<?= strtoupper($del['metode_pembayaran']) ?>)</small>
                                        </td>
                                        <td><?= status_badge($del['status_pengiriman']) ?></td>
                                        <td class="text-muted fst-italic">
                                            <?= htmlspecialchars($del['catatan_kurir'] ?: '-') ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick='openKurirModal(<?= json_encode($del) ?>)'>
                                                <i class="bi bi-truck me-1"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada daftar pengiriman.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Update Kurir -->
<div class="modal fade" id="kurirModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold">Update Status Antaran Kurir</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="pengiriman_id" id="modalPengId">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Kode Pengiriman:</label>
                        <div class="fw-bold fs-5 text-primary" id="modalKode">-</div>
                        <small class="text-muted" id="modalTujuan">-</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Status Antaran</label>
                        <select name="status_pengiriman" id="modalStatus" class="form-select" required>
                            <option value="siap_dikirim">Siap Dikirim (Sudah di Kendaraan Motor/Mobil)</option>
                            <option value="sedang_dikirim">Sedang Dikirim (Kurir OTW ke Lokasi)</option>
                            <option value="selesai">Pesanan Selesai (Galon Tiba & Diterima)</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Catatan Kurir (cth: Diterima Ibu / Taruh Teras)</label>
                        <textarea name="catatan_kurir" id="modalCatatan" class="form-control" rows="2" placeholder="Tuliskan keterangan serah terima..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script>
function openKurirModal(del) {
    document.getElementById('modalPengId').value = del.id;
    document.getElementById('modalKode').innerText = del.kode_pengiriman;
    document.getElementById('modalTujuan').innerText = 'Penerima: ' + del.nama_pelanggan + ' • Alamat: ' + del.alamat_tujuan;
    document.getElementById('modalStatus').value = del.status_pengiriman;
    document.getElementById('modalCatatan').value = del.catatan_kurir || '';
    const modal = new bootstrap.Modal(document.getElementById('kurirModal'));
    modal.show();
}
</script>
</body>
</html>

