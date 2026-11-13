<?php
$pageTitle = "Data Karyawan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $karyawanId = (int)($_POST['karyawan_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $noTelepon = trim($_POST['no_telepon'] ?? '');
    $gaji = (float)($_POST['gaji_pokok'] ?? 0);
    $tanggalMasuk = $_POST['tanggal_masuk'] ?? date('Y-m-d');
    $statusAktif = $_POST['status_aktif'] ?? 'aktif';
    $password = $_POST['password'] ?? '';

    if ($action === 'create') {
        try {
            $db->beginTransaction();

            $hashPass = password_hash($password ?: 'karyawan123', PASSWORD_BCRYPT);
            $stmtUser = $db->prepare("
                INSERT INTO users (role_id, username, email, password, nama_lengkap, no_telepon, status_aktif)
                VALUES (2, ?, ?, ?, ?, ?, ?)
            ");
            $stmtUser->execute([$username, $email, $hashPass, $nama, $noTelepon, $statusAktif]);
            $newUserId = $db->lastInsertId();

            $stmtKaryawan = $db->prepare("
                INSERT INTO karyawan (user_id, nip, jabatan, gaji_pokok, tanggal_masuk)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtKaryawan->execute([$newUserId, $nip, $jabatan, $gaji, $tanggalMasuk]);

            $db->commit();
            set_flash('success', 'Akun karyawan baru berhasil ditambahkan!');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Gagal menambahkan karyawan: ' . $e->getMessage());
        }
    } elseif ($action === 'edit') {
        try {
            $db->beginTransaction();

            // Update user
            if (!empty($password)) {
                $hashPass = password_hash($password, PASSWORD_BCRYPT);
                $stmtUser = $db->prepare("
                    UPDATE users 
                    SET nama_lengkap = ?, email = ?, no_telepon = ?, status_aktif = ?, password = ?
                    WHERE id = ?
                ");
                $stmtUser->execute([$nama, $email, $noTelepon, $statusAktif, $hashPass, $userId]);
            } else {
                $stmtUser = $db->prepare("
                    UPDATE users 
                    SET nama_lengkap = ?, email = ?, no_telepon = ?, status_aktif = ?
                    WHERE id = ?
                ");
                $stmtUser->execute([$nama, $email, $noTelepon, $statusAktif, $userId]);
            }

            // Update karyawan
            $stmtKaryawan = $db->prepare("
                UPDATE karyawan 
                SET nip = ?, jabatan = ?, gaji_pokok = ?, tanggal_masuk = ?
                WHERE id = ?
            ");
            $stmtKaryawan->execute([$nip, $jabatan, $gaji, $tanggalMasuk, $karyawanId]);

            $db->commit();
            set_flash('success', 'Data karyawan berhasil diperbarui!');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Gagal memperbarui karyawan: ' . $e->getMessage());
        }
    }
    redirect('admin/karyawan.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delUserId = (int)$_GET['delete'];
    try {
        // Prevent deleting own account
        if ($delUserId == $_SESSION['user']['id']) {
            set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } else {
            $stmtDel = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmtDel->execute([$delUserId]);
            set_flash('success', 'Akun karyawan berhasil dihapus!');
        }
    } catch (Exception $e) {
        set_flash('error', 'Gagal menghapus akun: ' . $e->getMessage());
    }
    redirect('admin/karyawan.php');
}

// Fetch all employees
$employees = $db->query("
    SELECT k.*, u.username, u.email, u.nama_lengkap, u.no_telepon, u.status_aktif 
    FROM karyawan k 
    JOIN users u ON k.user_id = u.id 
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
                    <h3 class="fw-bold mb-1">Manajemen Data Karyawan</h3>
                    <p class="text-muted small mb-0">Kelola staf operasional kasir, operator pengisian galon, dan kurir pengantar.</p>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#karyawanModal" onclick="openCreateModal()">
                    <i class="bi bi-person-plus me-1"></i> Tambah Karyawan
                </button>
            </div>

            <!-- Employees Table -->
            <div class="glass-card p-4">
                <div class="table-responsive">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>NIP & Nama</th>
                                <th>Username / Email</th>
                                <th>Jabatan</th>
                                <th>WhatsApp</th>
                                <th>Gaji Pokok</th>
                                <th>Status Akun</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-primary"><?= htmlspecialchars($emp['nama_lengkap']) ?></div>
                                        <small class="text-muted">NIP: <?= htmlspecialchars($emp['nip']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">@<?= htmlspecialchars($emp['username']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($emp['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($emp['jabatan']) ?></span>
                                    </td>
                                    <td>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $emp['no_telepon']) ?>" target="_blank" class="small text-success text-decoration-none">
                                            <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($emp['no_telepon']) ?>
                                        </a>
                                    </td>
                                    <td class="fw-bold"><?= format_rupiah($emp['gaji_pokok']) ?></td>
                                    <td><?= status_badge($emp['status_aktif']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick='openEditModal(<?= json_encode($emp) ?>)' title="Edit Karyawan">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete('<?= url('admin/karyawan.php?delete=' . $emp['user_id']) ?>', 'karyawan <?= htmlspecialchars(addslashes($emp['nama_lengkap'])) ?>')" title="Hapus Karyawan">
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

<!-- Modal Karyawan -->
<div class="modal fade" id="karyawanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalKaryawanTitle">Tambah Karyawan Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="empAction" value="create">
                <input type="hidden" name="karyawan_id" id="empId" value="0">
                <input type="hidden" name="user_id" id="empUserId" value="0">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor Induk Pegawai (NIP)</label>
                            <input type="text" class="form-control" name="nip" id="empNip" placeholder="cth: KRY-2024-003" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Lengkap</label>
                            <input type="text" class="form-control" name="nama_lengkap" id="empNama" placeholder="cth: Dedi Supriadi" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Username Login</label>
                            <input type="text" class="form-control" name="username" id="empUsername" placeholder="cth: dedi_kasir" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Alamat Email</label>
                            <input type="email" class="form-control" name="email" id="empEmail" placeholder="cth: dedi@aquadepot.id" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jabatan / Posisi</label>
                            <input type="text" class="form-control" name="jabatan" id="empJabatan" placeholder="cth: Operator & Kasir / Kurir Antar" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor WhatsApp</label>
                            <input type="text" class="form-control" name="no_telepon" id="empTelepon" placeholder="cth: 081299887766" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Gaji Pokok (Rp)</label>
                            <input type="number" class="form-control" name="gaji_pokok" id="empGaji" min="0" value="2500000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Masuk Bekerja</label>
                            <input type="date" class="form-control" name="tanggal_masuk" id="empTanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Password Login</label>
                            <input type="password" class="form-control" name="password" id="empPass" placeholder="Kosongkan jika tidak diubah">
                            <small class="text-muted" id="empPassHelp">Default password baru: <code>karyawan123</code></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status Akun</label>
                            <select name="status_aktif" id="empStatus" class="form-select">
                                <option value="aktif">Aktif (Dapat Login)</option>
                                <option value="nonaktif">Nonaktif (Dibekukan)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Karyawan</button>
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
    document.getElementById('empAction').value = 'create';
    document.getElementById('empId').value = '0';
    document.getElementById('empUserId').value = '0';
    document.getElementById('modalKaryawanTitle').innerText = 'Tambah Karyawan Baru';
    document.getElementById('empNip').value = 'KRY-' + new Date().getFullYear() + '-' + Math.floor(100 + Math.random() * 900);
    document.getElementById('empNama').value = '';
    document.getElementById('empUsername').value = '';
    document.getElementById('empUsername').removeAttribute('disabled');
    document.getElementById('empEmail').value = '';
    document.getElementById('empJabatan').value = 'Operator Kasir & Pengiriman';
    document.getElementById('empTelepon').value = '';
    document.getElementById('empGaji').value = '2500000';
    document.getElementById('empStatus').value = 'aktif';
    document.getElementById('empPass').value = 'karyawan123';
    document.getElementById('empPassHelp').style.display = 'block';
}

function openEditModal(emp) {
    document.getElementById('empAction').value = 'edit';
    document.getElementById('empId').value = emp.id;
    document.getElementById('empUserId').value = emp.user_id;
    document.getElementById('modalKaryawanTitle').innerText = 'Edit Karyawan: ' + emp.nama_lengkap;
    document.getElementById('empNip').value = emp.nip;
    document.getElementById('empNama').value = emp.nama_lengkap;
    document.getElementById('empUsername').value = emp.username;
    document.getElementById('empUsername').setAttribute('disabled', 'disabled');
    document.getElementById('empEmail').value = emp.email;
    document.getElementById('empJabatan').value = emp.jabatan;
    document.getElementById('empTelepon').value = emp.no_telepon;
    document.getElementById('empGaji').value = emp.gaji_pokok;
    document.getElementById('empTanggal').value = emp.tanggal_masuk;
    document.getElementById('empStatus').value = emp.status_aktif;
    document.getElementById('empPass').value = '';
    document.getElementById('empPassHelp').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('karyawanModal'));
    modal.show();
}
</script>
</body>
</html>

