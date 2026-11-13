<?php
$pageTitle = "Data Pelanggan - Karyawan AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['karyawan', 'admin']);

$db = Database::getConnection();

// Handle Add Customer by Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $noTelepon = trim($_POST['no_telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tipe = $_POST['tipe_pelanggan'] ?? 'umum';

    if (empty($nama) || empty($noTelepon)) {
        set_flash('error', 'Nama dan nomor telepon wajib diisi.');
    } else {
        try {
            $kodePelanggan = 'PLG-' . strtoupper(substr(uniqid(), -6));
            $stmt = $db->prepare("
                INSERT INTO pelanggan (kode_pelanggan, nama, no_telepon, alamat, tipe_pelanggan)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$kodePelanggan, $nama, $noTelepon, $alamat ?: 'Pelanggan Langsung', $tipe]);
            set_flash('success', 'Pelanggan baru berhasil ditambahkan!');
        } catch (Exception $e) {
            set_flash('error', 'Gagal menambahkan pelanggan: ' . $e->getMessage());
        }
    }
    redirect('karyawan/pelanggan.php');
}

$search = trim($_GET['search'] ?? '');
$query = "SELECT * FROM pelanggan WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (nama LIKE ? OR no_telepon LIKE ? OR kode_pelanggan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY id DESC";
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
    <?php require_once __DIR__ . '/../includes/sidebar_karyawan.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="app-content">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Daftar Pelanggan Depot</h3>
                    <p class="text-muted small mb-0">Cari nomor kontak WhatsApp dan catat data pelanggan langganan baru.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                    <i class="bi bi-person-plus me-1"></i> Input Pelanggan Baru
                </button>
            </div>

            <!-- Search -->
            <div class="glass-card p-3 mb-4">
                <form method="GET" action="" class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Cari nama atau nomor WhatsApp..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">Cari</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern small">
                        <thead>
                            <tr>
                                <th>Kode Pelanggan</th>
                                <th>Nama Lengkap</th>
                                <th>Nomor WhatsApp</th>
                                <th>Alamat Pengiriman</th>
                                <th>Tipe</th>
                                <th class="text-center">Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($c['kode_pelanggan']) ?></code></td>
                                    <td class="fw-bold"><?= htmlspecialchars($c['nama']) ?></td>
                                    <td>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['no_telepon']) ?>" target="_blank" class="text-success text-decoration-none fw-semibold">
                                            <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($c['no_telepon']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($c['alamat']) ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary text-uppercase"><?= $c['tipe_pelanggan'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['no_telepon']) ?>" target="_blank" class="btn btn-xs btn-outline-success rounded-pill px-3">
                                            <i class="bi bi-chat-dots me-1"></i> Chat
                                        </a>
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

<!-- Modal Input Pelanggan Baru -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold">Tambah Data Pelanggan Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Pelanggan</label>
                        <input type="text" class="form-control" name="nama" placeholder="cth: Pak Budi / Bu Rina" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nomor WhatsApp</label>
                        <input type="text" class="form-control" name="no_telepon" placeholder="cth: 081234567890" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tipe Pelanggan</label>
                        <select name="tipe_pelanggan" class="form-select">
                            <option value="umum">Umum</option>
                            <option value="langganan">Langganan Tetap</option>
                            <option value="bisnis">Bisnis (Warung/Kantor)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Pengiriman</label>
                        <textarea class="form-control" name="alamat" rows="3" placeholder="Alamat lengkap pengiriman..."></textarea>
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
</body>
</html>

