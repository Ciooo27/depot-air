<?php
$pageTitle = "Manajemen Transaksi - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Cancellation
if (isset($_GET['cancel'])) {
    $cancelId = (int)$_GET['cancel'];
    try {
        $db->beginTransaction();

        $stmtCur = $db->prepare("SELECT status_pembayaran FROM transaksi WHERE id = ? FOR UPDATE");
        $stmtCur->execute([$cancelId]);
        $currentStatus = $stmtCur->fetchColumn();

        if ($currentStatus !== 'batal') {
            // Update status
            $db->prepare("UPDATE transaksi SET status_pembayaran = 'batal', updated_at = NOW() WHERE id = ?")->execute([$cancelId]);

            // Restore stocks
            $stmtItems = $db->prepare("SELECT produk_id, jumlah FROM detail_transaksi WHERE transaksi_id = ?");
            $stmtItems->execute([$cancelId]);
            $items = $stmtItems->fetchAll();

            $stmtRestore = $db->prepare("UPDATE stok SET stok_tersedia = stok_tersedia + ? WHERE produk_id = ?");
            $stmtLog = $db->prepare("
                INSERT INTO riwayat_stok (produk_id, user_id, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, created_at)
                SELECT ?, ?, 'masuk', ?, s.stok_tersedia, s.stok_tersedia + ?, ?, NOW()
                FROM stok s WHERE s.produk_id = ?
            ");

            foreach ($items as $it) {
                $stmtLog->execute([
                    $it['produk_id'],
                    $_SESSION['user']['id'],
                    $it['jumlah'],
                    $it['jumlah'],
                    "Pengembalian stok pembatalan transaksi #$cancelId",
                    $it['produk_id']
                ]);
                $stmtRestore->execute([$it['jumlah'], $it['produk_id']]);
            }
        }

        $db->commit();
        set_flash('success', 'Transaksi berhasil dibatalkan dan stok produk telah dipulihkan!');
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
    }
    redirect('admin/transaksi.php');
}

// Filter variables
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$metode = $_GET['metode'] ?? '';
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT t.*, u.nama_lengkap as nama_kasir, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan 
    FROM transaksi t 
    LEFT JOIN users u ON t.kasir_user_id = u.id 
    LEFT JOIN pelanggan plg ON t.pelanggan_id = plg.id 
    WHERE 1=1
";
$params = [];

if (!empty($startDate)) {
    $query .= " AND DATE(t.tanggal_transaksi) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $query .= " AND DATE(t.tanggal_transaksi) <= ?";
    $params[] = $endDate;
}
if (!empty($metode)) {
    $query .= " AND t.metode_pembayaran = ?";
    $params[] = $metode;
}
if (!empty($status)) {
    $query .= " AND t.status_pembayaran = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $query .= " AND (t.kode_transaksi LIKE ? OR plg.nama LIKE ? OR plg.no_telepon LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY t.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Detail items mapped by transaction id
$stmtItems = $db->query("SELECT * FROM detail_transaksi");
$allItems = $stmtItems->fetchAll();
$itemsGroup = [];
foreach ($allItems as $it) {
    $itemsGroup[$it['transaksi_id']][] = $it;
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
                    <h3 class="fw-bold mb-1">Riwayat Seluruh Transaksi</h3>
                    <p class="text-muted small mb-0">Semua data transaksi kasir offline depot maupun pesanan online tersimpan terpusat di sini.</p>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="glass-card p-3 mb-4">
                <form method="GET" action="" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Cari Transaksi</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="No. Transaksi / Pelanggan..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold mb-1">Metode</label>
                        <select name="metode" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <option value="tunai" <?= ($metode === 'tunai') ? 'selected' : '' ?>>Tunai</option>
                            <option value="transfer" <?= ($metode === 'transfer') ? 'selected' : '' ?>>Transfer</option>
                            <option value="cod" <?= ($metode === 'cod') ? 'selected' : '' ?>>COD</option>
                            <option value="qris" <?= ($metode === 'qris') ? 'selected' : '' ?>>QRIS</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 me-1">Terapkan Filter</button>
                        <a href="<?= url('admin/transaksi.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Tanggal & Waktu</th>
                                <th>Pelanggan</th>
                                <th>Total Item</th>
                                <th>Total Akhir</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($transactions)): ?>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary"><?= htmlspecialchars($t['kode_transaksi']) ?></span>
                                            <small class="d-block text-muted">Kasir: <?= htmlspecialchars($t['nama_kasir'] ?: 'Admin') ?></small>
                                        </td>
                                        <td><?= tanggal_indo($t['tanggal_transaksi'], true) ?></td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($t['nama_pelanggan'] ?: 'Pelanggan Walk-In') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($t['wa_pelanggan'] ?: '-') ?></small>
                                        </td>
                                        <td class="fw-bold text-center"><?= $t['total_item'] ?></td>
                                        <td class="fw-bold text-success fs-6"><?= format_rupiah($t['total_akhir']) ?></td>
                                        <td><span class="badge bg-light text-dark border text-uppercase"><?= $t['metode_pembayaran'] ?></span></td>
                                        <td><?= status_badge($t['status_pembayaran']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTrx<?= $t['id'] ?>" title="Detail Item">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="<?= url('views/cetak_invoice.php?transaksi_id=' . $t['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Struk">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <?php if ($t['status_pembayaran'] !== 'batal'): ?>
                                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDelete('<?= url('admin/transaksi.php?cancel=' . $t['id']) ?>', 'transaksi <?= $t['kode_transaksi'] ?> (Stok akan dikembalikan ke gudang)')" title="Batalkan Transaksi">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Modal Detail Transaksi -->
                                            <div class="modal fade" id="modalTrx<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content rounded-4 border-0 shadow">
                                                        <div class="modal-header bg-primary text-white rounded-top-4">
                                                            <h6 class="modal-title fw-bold">Detail Transaksi <?= htmlspecialchars($t['kode_transaksi']) ?></h6>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start p-4 small">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Pelanggan:</span>
                                                                <span class="fw-bold"><?= htmlspecialchars($t['nama_pelanggan'] ?: 'Walk-In') ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-3 pb-2 border-bottom">
                                                                <span class="text-muted">Waktu:</span>
                                                                <span><?= tanggal_indo($t['tanggal_transaksi'], true) ?></span>
                                                            </div>

                                                            <h6 class="fw-bold mb-2">Rincian Pembelian:</h6>
                                                            <ul class="list-group list-group-flush mb-3">
                                                                <?php 
                                                                $trxItems = $itemsGroup[$t['id']] ?? [];
                                                                foreach ($trxItems as $it): 
                                                                ?>
                                                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                                        <div>
                                                                            <span class="fw-bold"><?= htmlspecialchars($it['nama_produk']) ?></span>
                                                                            <div class="text-muted"><?= $it['jumlah'] ?> x <?= format_rupiah($it['harga_satuan']) ?></div>
                                                                        </div>
                                                                        <span class="fw-bold"><?= format_rupiah($it['subtotal']) ?></span>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>

                                                            <div class="d-flex justify-content-between text-muted mb-1">
                                                                <span>Subtotal:</span>
                                                                <span><?= format_rupiah($t['subtotal']) ?></span>
                                                            </div>
                                                            <?php if ($t['diskon'] > 0): ?>
                                                                <div class="d-flex justify-content-between text-danger mb-1">
                                                                    <span>Diskon:</span>
                                                                    <span>- <?= format_rupiah($t['diskon']) ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="d-flex justify-content-between fw-bold text-primary fs-6 border-top pt-2">
                                                                <span>Total Akhir:</span>
                                                                <span><?= format_rupiah($t['total_akhir']) ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between text-muted mt-1">
                                                                <span>Jumlah Bayar:</span>
                                                                <span><?= format_rupiah($t['jumlah_bayar']) ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between text-muted">
                                                                <span>Kembalian:</span>
                                                                <span><?= format_rupiah($t['kembalian']) ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 p-3">
                                                            <a href="<?= url('views/cetak_invoice.php?transaksi_id=' . $t['id']) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                                                <i class="bi bi-printer me-1"></i> Cetak Struk
                                                            </a>
                                                            <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data transaksi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

