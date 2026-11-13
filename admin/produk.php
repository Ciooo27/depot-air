<?php
$pageTitle = "Manajemen Produk - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Form Submissions (Create, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $kategoriId = (int)$_POST['kategori_id'];
        $kodeProduk = trim($_POST['kode_produk']);
        $namaProduk = trim($_POST['nama_produk']);
        $deskripsi = trim($_POST['deskripsi']);
        $hargaModal = (float)$_POST['harga_modal'];
        $hargaJual = (float)$_POST['harga_jual'];
        $satuan = trim($_POST['satuan']);
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $status = $_POST['status'] ?? 'tersedia';
        $stokAwal = (int)($_POST['stok_awal'] ?? 0);
        $stokMinimum = (int)($_POST['stok_minimum'] ?? 5);

        // Image upload handling
        $gambar = $_POST['gambar_lama'] ?? 'default_product.png';
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
            if (in_array($ext, $allowed)) {
                $newFilename = 'prod_' . uniqid() . '.' . $ext;
                $dest = __DIR__ . '/../assets/uploads/products/' . $newFilename;
                if (move_uploaded_file($_FILES['gambar']['tmp_name'], $dest)) {
                    $gambar = $newFilename;
                }
            }
        }

        if ($action === 'create') {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO produk (kategori_id, kode_produk, nama_produk, deskripsi, harga_modal, harga_jual, satuan, gambar, is_featured, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$kategoriId, $kodeProduk, $namaProduk, $deskripsi, $hargaModal, $hargaJual, $satuan, $gambar, $isFeatured, $status]);
                $newProductId = $db->lastInsertId();

                // Insert into stock
                $stmtStock = $db->prepare("INSERT INTO stok (produk_id, stok_tersedia, stok_minimum) VALUES (?, ?, ?)");
                $stmtStock->execute([$newProductId, $stokAwal, $stokMinimum]);

                // Log initial stock if > 0
                if ($stokAwal > 0) {
                    $stmtLog = $db->prepare("
                        INSERT INTO riwayat_stok (produk_id, user_id, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, created_at)
                        VALUES (?, ?, 'masuk', ?, 0, ?, 'Stok awal produk baru', NOW())
                    ");
                    $stmtLog->execute([$newProductId, $_SESSION['user']['id'], $stokAwal, $stokAwal]);
                }

                $db->commit();
                set_flash('success', 'Produk baru berhasil ditambahkan!');
            } catch (Exception $e) {
                $db->rollBack();
                set_flash('error', 'Gagal menambahkan produk: ' . $e->getMessage());
            }
        } else {
            // Edit
            try {
                $stmt = $db->prepare("
                    UPDATE produk 
                    SET kategori_id = ?, kode_produk = ?, nama_produk = ?, deskripsi = ?, harga_modal = ?, harga_jual = ?, satuan = ?, gambar = ?, is_featured = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$kategoriId, $kodeProduk, $namaProduk, $deskripsi, $hargaModal, $hargaJual, $satuan, $gambar, $isFeatured, $status, $id]);

                // Update minimum stock setting
                $stmtUpMin = $db->prepare("UPDATE stok SET stok_minimum = ? WHERE produk_id = ?");
                $stmtUpMin->execute([$stokMinimum, $id]);

                set_flash('success', 'Data produk berhasil diperbarui!');
            } catch (Exception $e) {
                set_flash('error', 'Gagal memperbarui produk: ' . $e->getMessage());
            }
        }
        redirect('admin/produk.php');
    }
}

// Handle Delete via GET
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $stmtDel = $db->prepare("DELETE FROM produk WHERE id = ?");
        $stmtDel->execute([$delId]);
        set_flash('success', 'Produk berhasil dihapus!');
    } catch (Exception $e) {
        set_flash('error', 'Tidak dapat menghapus produk karena masih terikat dengan transaksi/pesanan.');
    }
    redirect('admin/produk.php');
}

// Fetch categories for modal select
$categories = $db->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// Search and filter
$search = trim($_GET['search'] ?? '');
$katFilter = (int)($_GET['kategori'] ?? 0);

$query = "
    SELECT p.*, s.stok_tersedia, s.stok_minimum, k.nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.kategori_id = k.id 
    LEFT JOIN stok s ON p.id = s.produk_id 
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.nama_produk LIKE ? OR p.kode_produk LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($katFilter > 0) {
    $query .= " AND p.kategori_id = ?";
    $params[] = $katFilter;
}

$query .= " ORDER BY p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();
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
                    <h3 class="fw-bold mb-1">Manajemen Produk & Harga</h3>
                    <p class="text-muted small mb-0">Kelola katalog air galon, kemasan botol/gelas, dan penetapan harga jual.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Produk Baru
                </button>
            </div>

            <!-- Filter Card -->
            <div class="glass-card p-3 mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari nama atau kode produk..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="kategori" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="0">Semua Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($katFilter === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nama_kategori']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">Filter</button>
                        <a href="<?= url('admin/produk.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Products Table -->
            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th>Harga Modal</th>
                                <th>Harga Jual</th>
                                <th>Stok</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-3 bg-light p-2 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border: 1px solid var(--card-border);">
                                                    <img src="<?= product_image($p['gambar']) ?>" alt="" style="max-height: 38px;">
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($p['kode_produk']) ?> • <?= htmlspecialchars($p['satuan']) ?></small>
                                                    <?php if ($p['is_featured']): ?>
                                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">Unggulan</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($p['nama_kategori']) ?></span></td>
                                        <td class="text-muted"><?= format_rupiah($p['harga_modal']) ?></td>
                                        <td class="fw-bold text-primary"><?= format_rupiah($p['harga_jual']) ?></td>
                                        <td>
                                            <span class="badge <?= ($p['stok_tersedia'] <= $p['stok_minimum']) ? 'bg-danger' : 'bg-success' ?>">
                                                <?= (int)$p['stok_tersedia'] ?>
                                            </span>
                                        </td>
                                        <td><?= status_badge($p['status']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick='openEditModal(<?= json_encode($p) ?>)' title="Edit Produk">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete('<?= url('admin/produk.php?delete=' . $p['id']) ?>', '<?= htmlspecialchars(addslashes($p['nama_produk'])) ?>')" title="Hapus Produk">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Tidak ada data produk yang sesuai filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Create / Edit Product -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="productModalTitle">Tambah Produk Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="productId" value="0">
                <input type="hidden" name="gambar_lama" id="gambarLama" value="default_product.png">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kode Produk</label>
                            <input type="text" class="form-control" name="kode_produk" id="mKode" placeholder="cth: PRD-GLN-05" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kategori Produk</label>
                            <select name="kategori_id" id="mKategori" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Nama Produk</label>
                            <input type="text" class="form-control" name="nama_produk" id="mNama" placeholder="cth: Air Galon RO Plus Oksigen" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Deskripsi Produk</label>
                            <textarea class="form-control" name="deskripsi" id="mDeskripsi" rows="2" placeholder="Keterangan singkat keunggulan produk..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Harga Modal (Rp)</label>
                            <input type="number" class="form-control" name="harga_modal" id="mModal" min="0" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Harga Jual (Rp)</label>
                            <input type="number" class="form-control" name="harga_jual" id="mJual" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Satuan</label>
                            <input type="text" class="form-control" name="satuan" id="mSatuan" value="Galon" placeholder="cth: Galon, Dus, Botol" required>
                        </div>

                        <div class="col-md-6" id="stokAwalCol">
                            <label class="form-label small fw-bold">Stok Awal</label>
                            <input type="number" class="form-control" name="stok_awal" id="mStokAwal" min="0" value="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Batas Stok Minimum</label>
                            <input type="number" class="form-control" name="stok_minimum" id="mStokMin" min="1" value="5" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status Ketersediaan</label>
                            <select name="status" id="mStatus" class="form-select">
                                <option value="tersedia">Tersedia</option>
                                <option value="habis">Stok Habis</option>
                                <option value="nonaktif">Nonaktifkan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Foto Produk (Opsional)</label>
                            <input type="file" class="form-control" name="gambar" accept="image/*">
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="mFeatured" name="is_featured">
                                <label class="form-check-label small" for="mFeatured">Tampilkan sebagai Produk Unggulan di Halaman Utama</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script>
function openCreateModal() {
    document.getElementById('formAction').value = 'create';
    document.getElementById('productId').value = '0';
    document.getElementById('productModalTitle').innerText = 'Tambah Produk Baru';
    document.getElementById('mKode').value = 'PRD-' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('mNama').value = '';
    document.getElementById('mDeskripsi').value = '';
    document.getElementById('mModal').value = '0';
    document.getElementById('mJual').value = '';
    document.getElementById('mSatuan').value = 'Galon';
    document.getElementById('stokAwalCol').style.display = 'block';
    document.getElementById('mStokAwal').value = '50';
    document.getElementById('mStokMin').value = '5';
    document.getElementById('mStatus').value = 'tersedia';
    document.getElementById('mFeatured').checked = false;
}

function openEditModal(prod) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('productId').value = prod.id;
    document.getElementById('productModalTitle').innerText = 'Edit Produk: ' + prod.nama_produk;
    document.getElementById('mKode').value = prod.kode_produk;
    document.getElementById('mKategori').value = prod.kategori_id;
    document.getElementById('mNama').value = prod.nama_produk;
    document.getElementById('mDeskripsi').value = prod.deskripsi;
    document.getElementById('mModal').value = prod.harga_modal;
    document.getElementById('mJual').value = prod.harga_jual;
    document.getElementById('mSatuan').value = prod.satuan;
    document.getElementById('stokAwalCol').style.display = 'none'; // Stok managed in stok menu
    document.getElementById('mStokMin').value = prod.stok_minimum;
    document.getElementById('mStatus').value = prod.status;
    document.getElementById('mFeatured').checked = (prod.is_featured == 1);
    document.getElementById('gambarLama').value = prod.gambar;

    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}
</script>
</body>
</html>

