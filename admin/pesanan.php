<?php
$pageTitle = "Kelola Pesanan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    verify_csrf();
    $pesananId = (int)$_POST['pesanan_id'];
    $newStatus = $_POST['status_pesanan'];
    $catatan = trim($_POST['catatan'] ?? '');

    try {
        $db->beginTransaction();

        // Get current order status
        $stmtCur = $db->prepare("SELECT status_pesanan FROM pesanan WHERE id = ? FOR UPDATE");
        $stmtCur->execute([$pesananId]);
        $oldStatus = $stmtCur->fetchColumn();

        if ($oldStatus !== $newStatus) {
            // Update pesanan
            $stmtUp = $db->prepare("UPDATE pesanan SET status_pesanan = ?, updated_at = NOW() WHERE id = ?");
            $stmtUp->execute([$newStatus, $pesananId]);

            // Sync with pengiriman if exists
            $stmtPeng = $db->prepare("UPDATE pengiriman SET status_pengiriman = ?, updated_at = NOW() WHERE pesanan_id = ?");
            $stmtPeng->execute([$newStatus, $pesananId]);

            // If cancelled from an active status, rollback stock!
            if ($newStatus === 'dibatalkan' && $oldStatus !== 'dibatalkan') {
                $stmtItems = $db->prepare("SELECT produk_id, jumlah FROM detail_pesanan WHERE pesanan_id = ?");
                $stmtItems->execute([$pesananId]);
                $items = $stmtItems->fetchAll();

                $stmtRollbackStock = $db->prepare("UPDATE stok SET stok_tersedia = stok_tersedia + ? WHERE produk_id = ?");
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
                        "Pengembalian stok pesanan #$pesananId dibatalkan",
                        $it['produk_id']
                    ]);
                    $stmtRollbackStock->execute([$it['jumlah'], $it['produk_id']]);
                }
            }

            // If completed, create transaction record if not yet created
            if ($newStatus === 'selesai') {
                $stmtCheckTrx = $db->prepare("SELECT id FROM transaksi WHERE pesanan_id = ?");
                $stmtCheckTrx->execute([$pesananId]);
                if (!$stmtCheckTrx->fetchColumn()) {
                    $stmtOrderData = $db->prepare("SELECT * FROM pesanan WHERE id = ?");
                    $stmtOrderData->execute([$pesananId]);
                    $ord = $stmtOrderData->fetch();

                    $kodeTrx = 'TRX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                    $stmtInsTrx = $db->prepare("
                        INSERT INTO transaksi (kode_transaksi, pesanan_id, pelanggan_id, kasir_user_id, tanggal_transaksi, total_item, subtotal, diskon, biaya_pengiriman, total_akhir, metode_pembayaran, jumlah_bayar, kembalian, status_pembayaran, catatan)
                        VALUES (?, ?, ?, ?, NOW(), 0, ?, 0, ?, ?, ?, ?, 0, 'lunas', ?)
                    ");
                    $stmtInsTrx->execute([
                        $kodeTrx,
                        $pesananId,
                        $ord['pelanggan_id'],
                        $_SESSION['user']['id'],
                        $ord['total_harga'],
                        $ord['biaya_pengiriman'],
                        $ord['total_bayar'],
                        $ord['metode_pembayaran'],
                        $ord['total_bayar'],
                        "Transaksi otomatis pesanan selesai " . $ord['kode_pesanan']
                    ]);
                    $newTrxId = $db->lastInsertId();

                    // Insert detail_transaksi snapshot
                    $stmtDetails = $db->prepare("
                        SELECT dp.*, p.nama_produk 
                        FROM detail_pesanan dp 
                        JOIN produk p ON dp.produk_id = p.id 
                        WHERE dp.pesanan_id = ?
                    ");
                    $stmtDetails->execute([$pesananId]);
                    $dItems = $stmtDetails->fetchAll();

                    $stmtInsDT = $db->prepare("
                        INSERT INTO detail_transaksi (transaksi_id, produk_id, nama_produk, harga_satuan, jumlah, subtotal)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $totalItemsCount = 0;
                    foreach ($dItems as $di) {
                        $stmtInsDT->execute([$newTrxId, $di['produk_id'], $di['nama_produk'], $di['harga_satuan'], $di['jumlah'], $di['subtotal']]);
                        $totalItemsCount += $di['jumlah'];
                    }

                    $db->prepare("UPDATE transaksi SET total_item = ? WHERE id = ?")->execute([$totalItemsCount, $newTrxId]);
                }
            }
        }

        $db->commit();
        set_flash('success', 'Status pesanan berhasil diperbarui!');
    } catch (Exception $e) {
        $db->rollBack();
        set_flash('error', 'Gagal memperbarui status: ' . $e->getMessage());
    }
    redirect('admin/pesanan.php');
}

// Filter and Search
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT p.*, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan,
           pg.kode_pengiriman, pg.status_pengiriman, u_kurir.nama_lengkap as nama_kurir
    FROM pesanan p
    JOIN pelanggan plg ON p.pelanggan_id = plg.id
    LEFT JOIN pengiriman pg ON p.id = pg.pesanan_id
    LEFT JOIN users u_kurir ON pg.kurir_user_id = u_kurir.id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $query .= " AND p.status_pesanan = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $query .= " AND (p.kode_pesanan LIKE ? OR plg.nama LIKE ? OR plg.no_telepon LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Fetch items for all orders
$stmtItems = $db->query("
    SELECT dp.*, prd.nama_produk, prd.satuan 
    FROM detail_pesanan dp 
    JOIN produk prd ON dp.produk_id = prd.id
");
$rawItems = $stmtItems->fetchAll();
$itemsGroup = [];
foreach ($rawItems as $ri) {
    $itemsGroup[$ri['pesanan_id']][] = $ri;
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
                    <h3 class="fw-bold mb-1">Manajemen Pesanan Masuk</h3>
                    <p class="text-muted small mb-0">Kelola konfirmasi pesanan pelanggan, proses armada pengiriman, dan lacak status pesanan.</p>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="glass-card p-3 mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari kode pesanan, nama, no HP..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Status Pesanan</option>
                            <option value="menunggu_konfirmasi" <?= ($statusFilter === 'menunggu_konfirmasi') ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                            <option value="diproses" <?= ($statusFilter === 'diproses') ? 'selected' : '' ?>>Diproses</option>
                            <option value="siap_dikirim" <?= ($statusFilter === 'siap_dikirim') ? 'selected' : '' ?>>Siap Dikirim</option>
                            <option value="sedang_dikirim" <?= ($statusFilter === 'sedang_dikirim') ? 'selected' : '' ?>>Sedang Dikirim</option>
                            <option value="selesai" <?= ($statusFilter === 'selesai') ? 'selected' : '' ?>>Selesai</option>
                            <option value="dibatalkan" <?= ($statusFilter === 'dibatalkan') ? 'selected' : '' ?>>Dibatalkan</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">Filter</button>
                        <a href="<?= url('admin/pesanan.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Kode & Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Item Pesanan</th>
                                <th>Total Bayar</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orders)): ?>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($o['kode_pesanan']) ?></div>
                                            <small class="text-muted"><?= tanggal_indo($o['tanggal_pesanan'], true) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($o['nama_pelanggan']) ?></div>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $o['wa_pelanggan']) ?>" target="_blank" class="small text-success text-decoration-none">
                                                <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($o['wa_pelanggan']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php 
                                            $orderItems = $itemsGroup[$o['id']] ?? [];
                                            foreach ($orderItems as $it): 
                                            ?>
                                                <div class="small">
                                                    <strong><?= $it['jumlah'] ?>x</strong> <?= htmlspecialchars($it['nama_produk']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td class="fw-bold text-primary">
                                            <?= format_rupiah($o['total_bayar']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border text-uppercase">
                                                <?= str_replace('_', ' ', $o['metode_pengambilan']) ?>
                                            </span>
                                            <small class="d-block text-muted text-uppercase mt-1"><?= $o['metode_pembayaran'] ?></small>
                                        </td>
                                        <td><?= status_badge($o['status_pesanan']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-primary rounded-pill px-3" onclick='openStatusModal(<?= json_encode($o) ?>)'>
                                                    <i class="bi bi-pencil-square me-1"></i> Update
                                                </button>
                                                <a href="<?= url('views/cetak_invoice.php?pesanan_id=' . $o['id']) ?>" target="_blank" class="btn btn-outline-secondary rounded-pill px-2 ms-1" title="Cetak Invoice">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data pesanan yang sesuai filter.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Update Status Pesanan -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold">Update Status Pesanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="pesanan_id" id="modalPesananId">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Kode Pesanan:</label>
                        <div class="fw-bold fs-5 text-primary" id="modalKodePesanan">-</div>
                        <small class="text-muted" id="modalPelangganInfo">-</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Status Baru:</label>
                        <select name="status_pesanan" id="modalStatusSelect" class="form-select" required>
                            <option value="menunggu_konfirmasi">Menunggu Konfirmasi</option>
                            <option value="diproses">Pesanan Diproses (Pengisian / Pengemasan Galon)</option>
                            <option value="siap_dikirim">Siap Dikirim</option>
                            <option value="sedang_dikirim">Sedang Dikirim (Kurir di Jalan)</option>
                            <option value="selesai">Pesanan Selesai (Diterima Pelanggan)</option>
                            <option value="dibatalkan">Pesanan Dibatalkan (Kembalikan Stok Otomatis)</option>
                        </select>
                    </div>

                    <div class="p-3 bg-light rounded-3 small text-muted border">
                        <i class="bi bi-info-circle me-1"></i> Jika status diubah menjadi <strong>Pesanan Selesai</strong>, sistem otomatis menerbitkan data transaksi lunas ke kasir. Jika <strong>Dibatalkan</strong>, stok galon otomatis dipulihkan ke gudang.
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
function openStatusModal(order) {
    document.getElementById('modalPesananId').value = order.id;
    document.getElementById('modalKodePesanan').innerText = order.kode_pesanan;
    document.getElementById('modalPelangganInfo').innerText = 'Pemesan: ' + order.nama_pelanggan + ' (' + order.wa_pelanggan + ')';
    document.getElementById('modalStatusSelect').value = order.status_pesanan;

    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}
</script>
</body>
</html>

