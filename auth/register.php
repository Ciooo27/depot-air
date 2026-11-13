<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$nama = '';
$email = '';
$username = '';
$no_telepon = '';
$alamat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($nama) || empty($email) || empty($username) || empty($no_telepon) || empty($alamat) || empty($password)) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal harus 6 karakter.';
    } elseif ($password !== $password_confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $db = Database::getConnection();

        // Check if username or email already exists
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmtCheck->execute([$username, $email]);
        if ($stmtCheck->fetch()) {
            $error = 'Username atau Email sudah terdaftar. Silakan gunakan yang lain atau login.';
        } else {
            try {
                $db->beginTransaction();

                // 1. Insert into users
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmtUser = $db->prepare("
                    INSERT INTO users (role_id, username, email, password, nama_lengkap, no_telepon, status_aktif, foto_profil)
                    VALUES (3, ?, ?, ?, ?, ?, 'aktif', 'default.png')
                ");
                $stmtUser->execute([$username, $email, $hashedPassword, $nama, $no_telepon]);
                $userId = $db->lastInsertId();

                // 2. Generate kode pelanggan
                $kodePelanggan = 'PLG-' . str_pad($userId, 4, '0', STR_PAD_LEFT);

                // 3. Insert into pelanggan
                $stmtPelanggan = $db->prepare("
                    INSERT INTO pelanggan (user_id, kode_pelanggan, nama, no_telepon, alamat, tipe_pelanggan)
                    VALUES (?, ?, ?, ?, ?, 'umum')
                ");
                $stmtPelanggan->execute([$userId, $kodePelanggan, $nama, $no_telepon, $alamat]);
                $pelangganId = $db->lastInsertId();

                $db->commit();

                // Auto login
                $_SESSION['user'] = [
                    'id' => $userId,
                    'username' => $username,
                    'email' => $email,
                    'nama_lengkap' => $nama,
                    'role_id' => 3,
                    'role_name' => 'pelanggan',
                    'pelanggan_id' => $pelangganId,
                    'kode_pelanggan' => $kodePelanggan,
                    'alamat' => $alamat,
                    'no_telepon' => $no_telepon,
                    'foto_profil' => 'default.png'
                ];

                set_flash('success', 'Pendaftaran berhasil! Selamat datang di AquaDepot.');

                if (!empty($_SESSION['cart'])) {
                    redirect('views/checkout.php');
                } else {
                    redirect('pelanggan/index.php');
                }
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Terjadi kesalahan sistem saat mendaftar: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Daftar Akun Pelanggan - AquaDepot";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7" data-aos="fade-up">
            <div class="glass-card p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white mb-3" style="width: 58px; height: 58px; background: linear-gradient(135deg, #2EAD68, #64B5F6);">
                        <i class="bi bi-person-plus-fill fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Daftar Akun Pelanggan</h3>
                    <p class="text-muted small">Nikmati kemudahan pesan antar air minum galon langsung ke pintu rumah Anda</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center rounded-3 py-2 small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Nama Lengkap" value="<?= htmlspecialchars($nama) ?>" required>
                                <label for="nama_lengkap"><i class="bi bi-person me-2 text-primary"></i>Nama Lengkap</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($username) ?>" required>
                                <label for="username"><i class="bi bi-at me-2 text-primary"></i>Username</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" name="email" placeholder="Alamat Email" value="<?= htmlspecialchars($email) ?>" required>
                                <label for="email"><i class="bi bi-envelope me-2 text-primary"></i>Email</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="no_telepon" name="no_telepon" placeholder="Nomor WhatsApp" value="<?= htmlspecialchars($no_telepon) ?>" required>
                                <label for="no_telepon"><i class="bi bi-whatsapp me-2 text-success"></i>Nomor WhatsApp (Aktif untuk Konfirmasi Kurir)</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-floating">
                                <textarea class="form-control" placeholder="Alamat Lengkap Pengiriman" id="alamat" name="alamat" style="height: 100px" required><?= htmlspecialchars($alamat) ?></textarea>
                                <label for="alamat"><i class="bi bi-geo-alt me-2 text-danger"></i>Alamat Lengkap Pengiriman (Nama Jalan, No. Rumah, RT/RW, Patokan)</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" minlength="6" required>
                                <label for="password"><i class="bi bi-lock me-2 text-primary"></i>Password (Min 6 Karakter)</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Ulangi Password" minlength="6" required>
                                <label for="password_confirm"><i class="bi bi-shield-check me-2 text-primary"></i>Ulangi Password</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-check my-3 small">
                        <input class="form-check-input" type="checkbox" id="terms" required checked>
                        <label class="form-check-label text-muted" for="terms">
                            Saya menyetujui syarat & ketentuan pemesanan di AquaDepot.
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-6 mb-3">
                        <i class="bi bi-person-check-fill me-2"></i>Daftar Sekarang
                    </button>

                    <div class="text-center small text-muted">
                        Sudah punya akun? <a href="<?= url('auth/login.php') ?>" class="fw-bold text-primary">Masuk di sini</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

