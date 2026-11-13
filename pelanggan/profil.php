<?php
$pageTitle = "Profil Saya - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['pelanggan']);

$db = Database::getConnection();
$user = current_user();

// Fetch latest user and customer data
$stmt = $db->prepare("
    SELECT u.*, p.id as pelanggan_id, p.kode_pelanggan, p.alamat, p.tipe_pelanggan 
    FROM users u 
    LEFT JOIN pelanggan p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $no_telepon = trim($_POST['no_telepon'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if (empty($nama) || empty($email) || empty($no_telepon)) {
            $error = 'Nama lengkap, email, dan nomor WhatsApp tidak boleh kosong.';
        } else {
            // Update users table
            $stmtUpUser = $db->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_telepon = ? WHERE id = ?");
            $stmtUpUser->execute([$nama, $email, $no_telepon, $user['id']]);

            // Update pelanggan table
            $stmtUpPlg = $db->prepare("UPDATE pelanggan SET nama = ?, no_telepon = ?, alamat = ? WHERE user_id = ?");
            $stmtUpPlg->execute([$nama, $no_telepon, $alamat, $user['id']]);

            // Update session data
            $_SESSION['user']['nama_lengkap'] = $nama;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['no_telepon'] = $no_telepon;
            $_SESSION['user']['alamat'] = $alamat;

            $success = 'Profil Anda berhasil diperbarui!';
            $userData['nama_lengkap'] = $nama;
            $userData['email'] = $email;
            $userData['no_telepon'] = $no_telepon;
            $userData['alamat'] = $alamat;
        }
    } elseif ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($oldPass) || empty($newPass)) {
            $error = 'Silakan isi password lama dan password baru.';
        } elseif (!password_verify($oldPass, $userData['password'])) {
            $error = 'Password saat ini yang Anda masukkan salah.';
        } elseif (strlen($newPass) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Konfirmasi password baru tidak cocok.';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmtUpPass = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtUpPass->execute([$hash, $user['id']]);
            $success = 'Password berhasil diubah! Gunakan password baru Anda pada login berikutnya.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="mb-4" data-aos="fade-down">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= url('pelanggan/index.php') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Profil Saya</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">Pengaturan Profil & Akun</h2>
        <p class="text-muted">Perbarui data diri dan alamat utama pengantaran galon air minum Anda.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 py-2 px-3 small d-flex align-items-center mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success rounded-4 py-2 px-3 small d-flex align-items-center mb-3">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Edit Profile Form -->
        <div class="col-lg-7" data-aos="fade-right">
            <div class="glass-card p-4">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom" style="border-color: var(--card-border) !important;">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 60px; height: 60px;">
                        <?= strtoupper(substr($userData['nama_lengkap'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($userData['nama_lengkap']) ?></h5>
                        <small class="text-muted">ID Pelanggan: <strong><?= htmlspecialchars($userData['kode_pelanggan'] ?? '-') ?></strong></small>
                    </div>
                </div>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($userData['nama_lengkap']) ?>" required>
                                <label for="nama_lengkap">Nama Lengkap</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($userData['username']) ?>" disabled>
                                <label for="username">Username (Permanen)</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
                                <label for="email">Alamat Email</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="no_telepon" name="no_telepon" value="<?= htmlspecialchars($userData['no_telepon']) ?>" required>
                                <label for="no_telepon">Nomor WhatsApp Aktif</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-floating">
                                <textarea class="form-control" id="alamat" name="alamat" style="height: 110px;" required><?= htmlspecialchars($userData['alamat']) ?></textarea>
                                <label for="alamat">Alamat Lengkap Pengiriman</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 mt-4">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan Profil
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-lg-5" data-aos="fade-left">
            <div class="glass-card p-4">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock text-primary"></i> Ganti Password
                </h5>
                <p class="text-muted small mb-4">Gunakan password yang kuat untuk menjaga keamanan akun pemesanan Anda.</p>

                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="old_password" name="old_password" placeholder="Password Saat Ini" required>
                        <label for="old_password">Password Saat Ini</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Password Baru" minlength="6" required>
                        <label for="new_password">Password Baru (Min 6 Karakter)</label>
                    </div>

                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Ulangi Password Baru" minlength="6" required>
                        <label for="confirm_password">Ulangi Password Baru</label>
                    </div>

                    <button type="submit" class="btn btn-outline-primary w-100 rounded-pill py-2 fw-semibold">
                        <i class="bi bi-key-fill me-1"></i> Perbarui Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

