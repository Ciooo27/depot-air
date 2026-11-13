<?php
$pageTitle = "Manajemen Pengiriman - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Assign Courier or Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pengirimanId = (int)$_POST['pengiriman_id'];
    $kurirId = !empty($_POST['kurir_user_id']) ? (int)$_POST['kurir_user_id'] : null;
    $statusPengiriman = $_POST['status_pengiriman'];
    $catatanKurir = trim($_POST['catatan_kurir'] ?? '');

    try {
        $db->beginTransaction();

        // Update pengiriman
        $stmtUp = $db->prepare("
            UPDATE pengiriman 
            SET kurir_user_id = ?, status_pengiriman = ?, catatan_kurir = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmtUp->execute([$kurirId, $statusPengiriman, $catatanKurir, $pengirimanId]);

        // Sync with pesanan
        $stmtSync = $db->prepare("
            UPDATE pesanan 
            SET status_pesanan = ?, updated_at = NOW() 
            WHERE id = (SELECT pesanan_id FROM pengiriman WHERE id = ?)
        ");
        $stmtSync->execute([$statusPengiriman, $pengirimanId]);

        $db->commit();
        set_flash('success', 'Status pengiriman dan kurir berhasil diperbarui!');
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('error', 'Gagal memperbarui pengiriman: ' . $e->getMessage());
    }
    redirect('admin/pengiriman.php');
}

// Fetch couriers / employees
$couriers = $db->query("
    SELECT u.id, u.nama_lengkap, u.no_telepon 
    FROM users u 
    WHERE u.role_id = 2 AND u.status_aktif = 'aktif'
")->fetchAll();

// Filter
$statusFilter = $_GET['status'] ?? '';

$query = "
    SELECT pg.*, p.kode_pesanan, p.total_bayar, p.metode_pembayaran, p.catatan as catatan_pesanan,
           plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan,
           u_kurir.nama_lengkap as nama_kurir, u_kurir.no_telepon as wa_kurir,
           (SELECT SUM(dp.jumlah) FROM detail_pesanan dp WHERE dp.pesanan_id = p.id) as total_qty
    FROM pengiriman pg
    JOIN pesanan p ON pg.pesanan_id = p.id
    JOIN pelanggan plg ON p.pelanggan_id = plg.id
    LEFT JOIN users u_kurir ON pg.kurir_user_id = u_kurir.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $query .= " AND pg.status_pengiriman = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY pg.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$deliveries = $stmt->fetchAll();
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
    <?php require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="app-content">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Manajemen Armada & Pengiriman Galon</h3>
                    <p class="text-muted small mb-0">Pantau penugasan kurir depot, tujuan alamat, dan progres 6 status pengiriman.</p>
                </div>
            </div>

            <!-- Filter Status -->
            <div class="glass-card p-3 mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Status Pengiriman (6 Status)</option>
                            <option value="menunggu_konfirmasi" <?= ($statusFilter === 'menunggu_konfirmasi') ? 'selected' : '' ?>>1. Menunggu Konfirmasi</option>
                            <option value="diproses" <?= ($statusFilter === 'diproses') ? 'selected' : '' ?>>2. Pesanan Diproses</option>
                            <option value="siap_dikirim" <?= ($statusFilter === 'siap_dikirim') ? 'selected' : '' ?>>3. Siap Dikirim</option>
                            <option value="sedang_dikirim" <?= ($statusFilter === 'sedang_dikirim') ? 'selected' : '' ?>>4. Sedang Dikirim</option>
                            <option value="selesai" <?= ($statusFilter === 'selesai') ? 'selected' : '' ?>>5. Pesanan Selesai</option>
                            <option value="dibatalkan" <?= ($statusFilter === 'dibatalkan') ? 'selected' : '' ?>>6. Pesanan Dibatalkan</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="<?= url('admin/pengiriman.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Tampilkan Semua</a>
                    </div>
                </form>
            </div>

            <!-- Deliveries Table -->
            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Kode Kirim</th>
                                <th>Penerima & WhatsApp</th>
                                <th>Alamat Pengantaran</th>
                                <th>Volume & Tagihan</th>
                                <th>Kurir Armada</th>
                                <th>Status Pengiriman</th>
                                <th class="text-center">Aksi</th>
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
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $del['wa_pelanggan']) ?>?text=<?= urlencode("Halo Bapak/Ibu " . $del['nama_pelanggan'] . ", kami dari kurir AquaDepot ingin mengonfirmasi pengantaran galon " . $del['kode_pesanan']) ?>" target="_blank" class="small text-success text-decoration-none">
                                                <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($del['wa_pelanggan']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($del['alamat_tujuan']) ?>">
                                                <?= htmlspecialchars($del['alamat_tujuan']) ?>
                                            </div>
                                            <?php if ($del['catatan_pesanan']): ?>
                                                <small class="text-muted d-block fst-italic">Note: <?= htmlspecialchars($del['catatan_pesanan']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= (int)$del['total_qty'] ?> Galon/Item</div>
                                            <small class="text-primary fw-bold"><?= format_rupiah($del['total_bayar']) ?> (<?= strtoupper($del['metode_pembayaran']) ?>)</small>
                                        </td>
                                        <td>
                                            <?php if ($del['nama_kurir']): ?>
                                                <span class="badge bg-primary-subtle text-primary">
                                                    <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($del['nama_kurir']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Belum Ditugaskan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= status_badge($del['status_pengiriman']) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick='openDeliveryModal(<?= json_encode($del) ?>)'>
                                                <i class="bi bi-pencil me-1"></i> Kelola
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data pengiriman.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Delivery Assign & Status -->
<div class="modal fade" id="deliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold">Kelola Kurir & Status Kirim</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="pengiriman_id" id="modalDelivId">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Kode Pengiriman:</label>
                        <div class="fw-bold fs-5 text-primary" id="modalKodeDeliv">-</div>
                        <small class="text-muted" id="modalTujuanDeliv">-</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tugaskan Kurir</label>
                        <select name="kurir_user_id" id="modalKurirSelect" class="form-select">
                            <option value="">-- Pilih Petugas Kurir Depot --</option>
                            <?php foreach ($couriers as $cr): ?>
                                <option value="<?= $cr['id'] ?>"><?= htmlspecialchars($cr['nama_lengkap']) ?> (<?= htmlspecialchars($cr['no_telepon']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Status Pengiriman (6 Status Standar)</label>
                        <select name="status_pengiriman" id="modalStatusDelivSelect" class="form-select" required>
                            <option value="menunggu_konfirmasi">1. Menunggu Konfirmasi</option>
                            <option value="diproses">2. Pesanan Diproses (Isi Galon/Packing)</option>
                            <option value="siap_dikirim">3. Siap Dikirim (Sudah Muat ke Kendaraan)</option>
                            <option value="sedang_dikirim">4. Sedang Dikirim (Dalam Perjalanan ke Pelanggan)</option>
                            <option value="selesai">5. Pesanan Selesai (Diterima dengan Baik)</option>
                            <option value="dibatalkan">6. Pesanan Dibatalkan</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Catatan Kurir / Laporan Lapangan</label>
                        <textarea name="catatan_kurir" id="modalCatatanKurir" class="form-control" rows="2" placeholder="cth: Diterima oleh pemilik rumah Ibu Siti di teras depan."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Pengiriman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script>
function openDeliveryModal(del) {
    document.getElementById('modalDelivId').value = del.id;
    document.getElementById('modalKodeDeliv').innerText = del.kode_pengiriman;
    document.getElementById('modalTujuanDeliv').innerText = 'Penerima: ' + del.nama_pelanggan + ' • Alamat: ' + del.alamat_tujuan;
    document.getElementById('modalKurirSelect').value = del.kurir_user_id || '';
    document.getElementById('modalStatusDelivSelect').value = del.status_pengiriman;
    document.getElementById('modalCatatanKurir').value = del.catatan_kurir || '';

    const modal = new bootstrap.Modal(document.getElementById('deliveryModal'));
    modal.show();
}
</script>
</body>
</html>

