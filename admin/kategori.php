<?php
$pageTitle = "Kategori Produk - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nama)));

    if ($action === 'create') {
        try {
            $stmt = $db->prepare("INSERT INTO kategori (nama_kategori, slug, deskripsi) VALUES (?, ?, ?)");
            $stmt->execute([$nama, $slug, $deskripsi]);
            set_flash('success', 'Kategori baru berhasil ditambahkan!');
        } catch (Exception $e) {
            set_flash('error', 'Gagal menambahkan kategori: ' . $e->getMessage());
        }
    } elseif ($action === 'edit') {
        try {
            $stmt = $db->prepare("UPDATE kategori SET nama_kategori = ?, slug = ?, deskripsi = ? WHERE id = ?");
            $stmt->execute([$nama, $slug, $deskripsi, $id]);
            set_flash('success', 'Kategori berhasil diperbarui!');
        } catch (Exception $e) {
            set_flash('error', 'Gagal memperbarui kategori: ' . $e->getMessage());
        }
    }
    redirect('admin/kategori.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        // Check if products exist in category
        $check = $db->prepare("SELECT COUNT(*) FROM produk WHERE kategori_id = ?");
        $check->execute([$delId]);
        if ($check->fetchColumn() > 0) {
            set_flash('warning', 'Tidak dapat menghapus kategori karena masih memiliki produk.');
        } else {
            $stmtDel = $db->prepare("DELETE FROM kategori WHERE id = ?");
            $stmtDel->execute([$delId]);
            set_flash('success', 'Kategori berhasil dihapus!');
        }
    } catch (Exception $e) {
        set_flash('error', 'Gagal menghapus kategori: ' . $e->getMessage());
    }
    redirect('admin/kategori.php');
}

// Fetch categories with product counts
$categories = $db->query("
    SELECT k.*, COUNT(p.id) as total_produk 
    FROM kategori k 
    LEFT JOIN produk p ON k.id = p.kategori_id 
    GROUP BY k.id 
    ORDER BY k.id ASC
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
                    <h3 class="fw-bold mb-1">Manajemen Kategori Produk</h3>
                    <p class="text-muted small mb-0">Kelompokkan produk air galon, kemasan karton, dan aksesoris.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
                </button>
            </div>

            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>Nama Kategori</th>
                                <th>Slug URL</th>
                                <th>Deskripsi</th>
                                <th>Jumlah Produk</th>
                                <th class="text-center" style="width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($cat['nama_kategori']) ?></td>
                                    <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                                    <td class="small text-muted"><?= htmlspecialchars($cat['deskripsi'] ?: '-') ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary"><?= $cat['total_produk'] ?> Produk</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick='openEditModal(<?= json_encode($cat) ?>)' title="Edit Kategori">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete('<?= url('admin/kategori.php?delete=' . $cat['id']) ?>', 'kategori <?= htmlspecialchars(addslashes($cat['nama_kategori'])) ?>')" title="Hapus Kategori">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Category -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="catModalTitle">Tambah Kategori</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="catAction" value="create">
                <input type="hidden" name="id" id="catId" value="0">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Kategori</label>
                        <input type="text" class="form-control" name="nama_kategori" id="catNama" placeholder="cth: Air Kemasan Gelas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deskripsi Singkat</label>
                        <textarea class="form-control" name="deskripsi" id="catDeskripsi" rows="3" placeholder="Keterangan kategori..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Kategori</button>
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
    document.getElementById('catAction').value = 'create';
    document.getElementById('catId').value = '0';
    document.getElementById('catModalTitle').innerText = 'Tambah Kategori Baru';
    document.getElementById('catNama').value = '';
    document.getElementById('catDeskripsi').value = '';
}

function openEditModal(cat) {
    document.getElementById('catAction').value = 'edit';
    document.getElementById('catId').value = cat.id;
    document.getElementById('catModalTitle').innerText = 'Edit Kategori';
    document.getElementById('catNama').value = cat.nama_kategori;
    document.getElementById('catDeskripsi').value = cat.deskripsi;
    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}
</script>
</body>
</html>

