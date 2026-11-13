<?php
$pageTitle = "Data Pelanggan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Form Submissions (Add, Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $noTelepon = trim($_POST['no_telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tipe = $_POST['tipe_pelanggan'] ?? 'umum';

    if ($action === 'create') {
        try {
            $kodePelanggan = 'PLG-' . strtoupper(substr(uniqid(), -6));
            $stmt = $db->prepare("
                INSERT INTO pelanggan (kode_pelanggan, nama, no_telepon, alamat, tipe_pelanggan)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$kodePelanggan, $nama, $noTelepon, $alamat, $tipe]);
            set_flash('success', 'Data pelanggan baru berhasil ditambahkan!');
        } catch (Exception $e) {
            set_flash('error', 'Gagal menambahkan pelanggan: ' . $e->getMessage());
        }
    } elseif ($action === 'edit') {
        try {
            $stmt = $db->prepare("
                UPDATE pelanggan 
                SET nama = ?, no_telepon = ?, alamat = ?, tipe_pelanggan = ?
                WHERE id = ?
            ");
            $stmt->execute([$nama, $noTelepon, $alamat, $tipe, $id]);
            set_flash('success', 'Data pelanggan berhasil diperbarui!');
        } catch (Exception $e) {
            set_flash('error', 'Gagal memperbarui pelanggan: ' . $e->getMessage());
        }
    }
    redirect('admin/pelanggan.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $stmtDel = $db->prepare("DELETE FROM pelanggan WHERE id = ?");
        $stmtDel->execute([$delId]);
        set_flash('success', 'Data pelanggan berhasil dihapus!');
    } catch (Exception $e) {
        set_flash('error', 'Gagal menghapus pelanggan karena memiliki data riwayat pesanan/transaksi.');
    }
    redirect('admin/pelanggan.php');
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$tipeFilter = $_GET['tipe'] ?? '';

$query = "
    SELECT p.*, u.username, u.email,
           (SELECT COUNT(*) FROM transaksi WHERE pelanggan_id = p.id) as total_transaksi,
           (SELECT COALESCE(SUM(total_akhir), 0) FROM transaksi WHERE pelanggan_id = p.id AND status_pembayaran = 'lunas') as total_belanja
    FROM pelanggan p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.nama LIKE ? OR p.no_telepon LIKE ? OR p.kode_pelanggan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($tipeFilter)) {
    $query .= " AND p.tipe_pelanggan = ?";
    $params[] = $tipeFilter;
}

$query .= " ORDER BY p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();
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
                    <h3 class="fw-bold mb-1">Manajemen Pelanggan</h3>
                    <p class="text-muted small mb-0">Kelola basis data pelanggan rumah tangga, langganan tetap, dan warung/kantor.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openCreateModal()">
                    <i class="bi bi-person-plus me-1"></i> Tambah Pelanggan
                </button>
            </div>

            <!-- Filter Card -->
            <div class="glass-card p-3 mb-4">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari nama pelanggan, no telepon/WA, atau kode..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="tipe" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Semua Tipe</option>
                            <option value="umum" <?= ($tipeFilter === 'umum') ? 'selected' : '' ?>>Umum</option>
                            <option value="langganan" <?= ($tipeFilter === 'langganan') ? 'selected' : '' ?>>Langganan Tetap</option>
                            <option value="bisnis" <?= ($tipeFilter === 'bisnis') ? 'selected' : '' ?>>Bisnis / Kantor / Warung</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1">Filter</button>
                        <a href="<?= url('admin/pelanggan.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Kode & Pelanggan</th>
                                <th>WhatsApp</th>
                                <th>Alamat Pengiriman</th>
                                <th>Tipe Pelanggan</th>
                                <th>Transaksi</th>
                                <th>Total Belanja</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($customers)): ?>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($c['nama']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($c['kode_pelanggan']) ?> <?= ($c['username'] ? '• User: ' . htmlspecialchars($c['username']) : '') ?></small>
                                        </td>
                                        <td>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['no_telepon']) ?>" target="_blank" class="text-success text-decoration-none small fw-semibold">
                                                <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($c['no_telepon']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <small class="text-muted d-block" style="max-width: 220px;"><?= nl2br(htmlspecialchars($c['alamat'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($c['tipe_pelanggan'] === 'bisnis'): ?>
                                                <span class="badge bg-primary">Bisnis / Usaha</span>
                                            <?php elseif ($c['tipe_pelanggan'] === 'langganan'): ?>
                                                <span class="badge bg-success">Langganan</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Umum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-center"><?= $c['total_transaksi'] ?>x</td>
                                        <td class="fw-bold text-success"><?= format_rupiah($c['total_belanja']) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick='openEditModal(<?= json_encode($c) ?>)' title="Edit Pelanggan">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete('<?= url('admin/pelanggan.php?delete=' . $c['id']) ?>', 'pelanggan <?= htmlspecialchars(addslashes($c['nama'])) ?>')" title="Hapus Pelanggan">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data pelanggan yang cocok.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pelanggan -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="customerModalTitle">Tambah Pelanggan Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="custAction" value="create">
                <input type="hidden" name="id" id="custId" value="0">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama" id="custNama" placeholder="cth: Ibu Dewi / Warung Makan Sejahtera" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nomor WhatsApp / Telepon</label>
                        <input type="text" class="form-control" name="no_telepon" id="custTelepon" placeholder="cth: 081234567890" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tipe Pelanggan</label>
                        <select name="tipe_pelanggan" id="custTipe" class="form-select" required>
                            <option value="umum">Umum (Beli Lepas)</option>
                            <option value="langganan">Langganan Tetap (Jadwal Antar Rutin)</option>
                            <option value="bisnis">Bisnis (Kantor / Warung / Restoran)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Pengiriman</label>
                        <textarea class="form-control" name="alamat" id="custAlamat" rows="3" placeholder="Nama Jalan, Blok, No Rumah, RT/RW, Patokan..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Pelanggan</button>
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
    document.getElementById('custAction').value = 'create';
    document.getElementById('custId').value = '0';
    document.getElementById('customerModalTitle').innerText = 'Tambah Pelanggan Baru';
    document.getElementById('custNama').value = '';
    document.getElementById('custTelepon').value = '';
    document.getElementById('custTipe').value = 'umum';
    document.getElementById('custAlamat').value = '';
}

function openEditModal(c) {
    document.getElementById('custAction').value = 'edit';
    document.getElementById('custId').value = c.id;
    document.getElementById('customerModalTitle').innerText = 'Edit Pelanggan: ' + c.nama;
    document.getElementById('custNama').value = c.nama;
    document.getElementById('custTelepon').value = c.no_telepon;
    document.getElementById('custTipe').value = c.tipe_pelanggan;
    document.getElementById('custAlamat').value = c.alamat;
    const modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
}
</script>
</body>
</html>

