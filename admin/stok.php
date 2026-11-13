<?php
$pageTitle = "Manajemen Stok - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Stock Adjustment / In / Out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $produkId = (int)$_POST['produk_id'];
    $jenis = $_POST['jenis']; // 'masuk', 'keluar', 'penyesuaian'
    $jumlah = (int)$_POST['jumlah'];
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($jumlah <= 0 && $jenis !== 'penyesuaian') {
        set_flash('error', 'Jumlah stok harus lebih dari 0.');
    } else {
        try {
            $db->beginTransaction();

            // Lock current stock
            $stmtCur = $db->prepare("SELECT stok_tersedia FROM stok WHERE produk_id = ? FOR UPDATE");
            $stmtCur->execute([$produkId]);
            $currentStock = (int)$stmtCur->fetchColumn();

            $newStock = $currentStock;
            if ($jenis === 'masuk') {
                $newStock = $currentStock + $jumlah;
            } elseif ($jenis === 'keluar') {
                if ($jumlah > $currentStock) {
                    throw new Exception("Jumlah stok keluar melebihi stok yang tersedia saat ini ($currentStock).");
                }
                $newStock = $currentStock - $jumlah;
            } elseif ($jenis === 'penyesuaian') {
                $newStock = $jumlah;
            }

            // Update stok
            $stmtUp = $db->prepare("UPDATE stok SET stok_tersedia = ? WHERE produk_id = ?");
            $stmtUp->execute([$newStock, $produkId]);

            // Log riwayat
            $stmtLog = $db->prepare("
                INSERT INTO riwayat_stok (produk_id, user_id, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtLog->execute([
                $produkId,
                $_SESSION['user']['id'],
                $jenis,
                ($jenis === 'penyesuaian' ? abs($newStock - $currentStock) : $jumlah),
                $currentStock,
                $newStock,
                $keterangan ?: "Perubahan stok manual oleh " . $_SESSION['user']['nama_lengkap']
            ]);

            $db->commit();
            set_flash('success', 'Stok produk berhasil diperbarui!');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Gagal memperbarui stok: ' . $e->getMessage());
        }
    }
    redirect('admin/stok.php');
}

// Fetch all products with stock
$stocks = $db->query("
    SELECT p.id, p.kode_produk, p.nama_produk, p.satuan, s.stok_tersedia, s.stok_minimum, k.nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.kategori_id = k.id 
    JOIN stok s ON p.id = s.produk_id 
    ORDER BY (s.stok_tersedia <= s.stok_minimum) DESC, p.nama_produk ASC
")->fetchAll();

// Fetch stock logs
$logs = $db->query("
    SELECT r.*, p.nama_produk, p.satuan, u.nama_lengkap as nama_user 
    FROM riwayat_stok r 
    JOIN produk p ON r.produk_id = p.id 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.id DESC 
    LIMIT 20
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
    <?php require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="app-content">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Manajemen & Log Stok</h3>
                    <p class="text-muted small mb-0">Catat pasokan air masuk, pengeluaran barang, dan pantau log audit riwayat stok.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#stokModal">
                    <i class="bi bi-plus-slash-minus me-1"></i> Penyesuaian / Tambah Stok
                </button>
            </div>

            <!-- Current Stock Table -->
            <div class="glass-card p-4 mb-4">
                <h5 class="fw-bold mb-3">Ketersediaan Stok Saat Ini</h5>
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th class="text-center">Stok Minimum</th>
                                <th class="text-center">Stok Tersedia</th>
                                <th>Status Level</th>
                                <th class="text-center">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stocks as $st): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($st['nama_produk']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($st['kode_produk']) ?> • <?= htmlspecialchars($st['satuan']) ?></small>
                                    </td>
                                    <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($st['nama_kategori']) ?></span></td>
                                    <td class="text-center text-muted fw-semibold"><?= $st['stok_minimum'] ?></td>
                                    <td class="text-center">
                                        <span class="fs-6 fw-bold <?= ($st['stok_tersedia'] <= $st['stok_minimum']) ? 'text-danger' : 'text-success' ?>">
                                            <?= $st['stok_tersedia'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($st['stok_tersedia'] <= 0): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Habis</span>
                                        <?php elseif ($st['stok_tersedia'] <= $st['stok_minimum']): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Menipis (Perlu Restock)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Aman</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="quickRestock(<?= $st['id'] ?>, '<?= htmlspecialchars(addslashes($st['nama_produk'])) ?>')">
                                            <i class="bi bi-plus-lg me-1"></i> Restock
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Stock History Logs -->
            <div class="glass-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Riwayat Mutasi & Perubahan Stok (20 Terakhir)</h5>
                    <small class="text-muted">Tercatat Otomatis dari Penjualan & Restock</small>
                </div>

                <div class="table-responsive">
                    <table class="table-modern small">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Produk</th>
                                <th>Jenis</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-center">Sebelum</th>
                                <th class="text-center">Sesudah</th>
                                <th>Keterangan</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><?= tanggal_indo($l['created_at'], true) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($l['nama_produk']) ?></td>
                                        <td>
                                            <?php if ($l['jenis'] === 'masuk'): ?>
                                                <span class="badge bg-success-subtle text-success">Masuk</span>
                                            <?php elseif ($l['jenis'] === 'keluar'): ?>
                                                <span class="badge bg-danger-subtle text-danger">Keluar</span>
                                            <?php else: ?>
                                                <span class="badge bg-info-subtle text-info">Penyesuaian</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold">
                                            <?= ($l['jenis'] === 'masuk') ? '+' : (($l['jenis'] === 'keluar') ? '-' : '') ?><?= $l['jumlah'] ?>
                                        </td>
                                        <td class="text-center text-muted"><?= $l['stok_sebelum'] ?></td>
                                        <td class="text-center fw-bold"><?= $l['stok_sesudah'] ?></td>
                                        <td><?= htmlspecialchars($l['keterangan']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($l['nama_user']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-3">Belum ada riwayat mutasi stok.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Adjustment -->
<div class="modal fade" id="stokModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold">Input Perubahan Stok</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Produk</label>
                        <select name="produk_id" id="modalProdukId" class="form-select" required>
                            <?php foreach ($stocks as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['nama_produk']) ?> (Stok saat ini: <?= $st['stok_tersedia'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Perubahan</label>
                        <select name="jenis" id="modalJenis" class="form-select" required>
                            <option value="masuk">Stok Masuk (Restock / Pembelian Pasokan Air)</option>
                            <option value="keluar">Stok Keluar (Galon Rusak / Kebocoran / Sampel)</option>
                            <option value="penyesuaian">Penyesuaian Manual (Opname Fisik)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jumlah</label>
                        <input type="number" name="jumlah" id="modalJumlah" class="form-control" min="1" value="20" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Keterangan / Catatan</label>
                        <input type="text" name="keterangan" class="form-control" placeholder="cth: Pasokan tangki air sumber / Penggantian galon pecah">
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script>
function quickRestock(prodId, prodName) {
    document.getElementById('modalProdukId').value = prodId;
    document.getElementById('modalJenis').value = 'masuk';
    document.getElementById('modalJumlah').value = '50';
    const modal = new bootstrap.Modal(document.getElementById('stokModal'));
    modal.show();
}
</script>
</body>
</html>

