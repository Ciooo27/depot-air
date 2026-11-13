<?php
$pageTitle = "Perbarui Stok - Karyawan AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['karyawan', 'admin']);

$db = Database::getConnection();

// Handle Restock Input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $produkId = (int)$_POST['produk_id'];
    $jumlah = (int)$_POST['jumlah'];
    $keterangan = trim($_POST['keterangan'] ?? 'Restock pasokan depot oleh operator');

    if ($jumlah <= 0) {
        set_flash('error', 'Jumlah stok masuk harus lebih dari 0.');
    } else {
        try {
            $db->beginTransaction();

            $stmtCur = $db->prepare("SELECT stok_tersedia FROM stok WHERE produk_id = ? FOR UPDATE");
            $stmtCur->execute([$produkId]);
            $currentStock = (int)$stmtCur->fetchColumn();

            $newStock = $currentStock + $jumlah;
            $db->prepare("UPDATE stok SET stok_tersedia = ? WHERE produk_id = ?")->execute([$newStock, $produkId]);

            $stmtLog = $db->prepare("
                INSERT INTO riwayat_stok (produk_id, user_id, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, created_at)
                VALUES (?, ?, 'masuk', ?, ?, ?, ?, NOW())
            ");
            $stmtLog->execute([$produkId, $_SESSION['user']['id'], $jumlah, $currentStock, $newStock, $keterangan]);

            $db->commit();
            set_flash('success', "Berhasil menambahkan $jumlah unit stok ke gudang!");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Gagal memperbarui stok: ' . $e->getMessage());
        }
    }
    redirect('karyawan/stok.php');
}

// Fetch products with stock
$stocks = $db->query("
    SELECT p.id, p.kode_produk, p.nama_produk, p.satuan, s.stok_tersedia, s.stok_minimum, k.nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.kategori_id = k.id 
    JOIN stok s ON p.id = s.produk_id 
    ORDER BY (s.stok_tersedia <= s.stok_minimum) DESC, p.nama_produk ASC
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
                    <h3 class="fw-bold mb-1">Stok & Pasokan Air Minum</h3>
                    <p class="text-muted small mb-0">Periksa ketersediaan stok galon dan catat penambahan stok pasokan air masuk.</p>
                </div>
            </div>

            <div class="row g-4">
                <!-- Stock List -->
                <div class="col-lg-8">
                    <div class="glass-card p-4">
                        <h5 class="fw-bold mb-3">Status Ketersediaan Produk</h5>
                        <div class="table-responsive">
                            <table class="table-modern small">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Kategori</th>
                                        <th class="text-center">Sisa Stok</th>
                                        <th>Kondisi</th>
                                        <th class="text-center">Aksi Cepat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stocks as $st): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($st['nama_produk']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($st['satuan']) ?> • Min: <?= $st['stok_minimum'] ?></small>
                                            </td>
                                            <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($st['nama_kategori']) ?></span></td>
                                            <td class="text-center fw-bold fs-6 <?= ($st['stok_tersedia'] <= $st['stok_minimum']) ? 'text-danger' : 'text-success' ?>">
                                                <?= $st['stok_tersedia'] ?>
                                            </td>
                                            <td>
                                                <?php if ($st['stok_tersedia'] <= 0): ?>
                                                    <span class="badge bg-danger">Habis</span>
                                                <?php elseif ($st['stok_tersedia'] <= $st['stok_minimum']): ?>
                                                    <span class="badge bg-warning text-dark">Menipis</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Aman</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="selectRestock(<?= $st['id'] ?>)">
                                                    <i class="bi bi-plus-lg me-1"></i> Tambah
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Input Restock Form -->
                <div class="col-lg-4">
                    <div class="glass-card p-4 position-sticky" style="top: 90px;">
                        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bi bi-box-arrow-in-down text-success"></i> Input Pasokan Masuk
                        </h5>

                        <form method="POST" action="">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Pilih Produk</label>
                                <select name="produk_id" id="restockProdSelect" class="form-select" required>
                                    <?php foreach ($stocks as $st): ?>
                                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['nama_produk']) ?> (Sisa: <?= $st['stok_tersedia'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Jumlah Unit Tambahan</label>
                                <input type="number" name="jumlah" class="form-control" min="1" value="50" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Catatan Pasokan</label>
                                <input type="text" name="keterangan" class="form-control" placeholder="cth: Pasokan tangki air sumber utama">
                            </div>

                            <button type="submit" class="btn btn-success w-100 py-2 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-plus-circle me-1"></i> Simpan Stok Masuk
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script>
function selectRestock(prodId) {
    document.getElementById('restockProdSelect').value = prodId;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>

