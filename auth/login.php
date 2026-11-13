<?php
require_once __DIR__ . '/../config/config.php';

// If already logged in, redirect based on role
if (is_logged_in()) {
    $role = $_SESSION['user']['role_name'];
    if ($role === 'admin') redirect('admin/index.php');
    if ($role === 'karyawan') redirect('karyawan/index.php');
    redirect('pelanggan/index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Silakan isi username/email dan password.';
    } else {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.*, r.nama_role 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE (u.username = ? OR u.email = ?)
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status_aktif'] !== 'aktif') {
                $error = 'Akun Anda sedang dinonaktifkan oleh administrator. Silakan hubungi manajemen.';
            } else {
                // Set session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'role_id' => $user['role_id'],
                    'role_name' => $user['nama_role'],
                    'foto_profil' => $user['foto_profil']
                ];

                // If customer, fetch pelanggan ID
                if ($user['nama_role'] === 'pelanggan') {
                    $stmtPelanggan = $db->prepare("SELECT id, kode_pelanggan, alamat, no_telepon FROM pelanggan WHERE user_id = ?");
                    $stmtPelanggan->execute([$user['id']]);
                    $pelanggan = $stmtPelanggan->fetch();
                    if ($pelanggan) {
                        $_SESSION['user']['pelanggan_id'] = $pelanggan['id'];
                        $_SESSION['user']['kode_pelanggan'] = $pelanggan['kode_pelanggan'];
                        $_SESSION['user']['alamat'] = $pelanggan['alamat'];
                        $_SESSION['user']['no_telepon'] = $pelanggan['no_telepon'];
                    }
                }

                set_flash('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '!');

                // Redirect based on role
                if ($user['nama_role'] === 'admin') {
                    redirect('admin/index.php');
                } elseif ($user['nama_role'] === 'karyawan') {
                    redirect('karyawan/index.php');
                } else {
                    // If redirected from checkout
                    if (!empty($_SESSION['cart'])) {
                        redirect('views/checkout.php');
                    } else {
                        redirect('pelanggan/index.php');
                    }
                }
            }
        } else {
            $error = 'Username, email, atau password salah!';
        }
    }
}

$pageTitle = "Masuk ke Akun - AquaDepot";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center align-items-center" style="min-height: 75vh;">
        <div class="col-md-8 col-lg-5" data-aos="fade-up">
            <div class="glass-card p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white mb-3" style="width: 58px; height: 58px; background: linear-gradient(135deg, #1976D2, #64B5F6); box-shadow: 0 4px 14px rgba(25, 118, 210, 0.4);">
                        <i class="bi bi-droplet-half fs-2"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Masuk ke AquaDepot</h3>
                    <p class="text-muted small">Kelola operasional depot atau pesan air galon secara praktis</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center rounded-3 py-2 small" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username atau Email" value="<?= htmlspecialchars($username) ?>" required autofocus>
                        <label for="username"><i class="bi bi-person me-2 text-primary"></i>Username atau Email</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="bi bi-lock me-2 text-primary"></i>Password</label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4 small">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" checked>
                            <label class="form-check-label text-muted" for="remember">Ingat Saya</label>
                        </div>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pengaturan['no_whatsapp'] ?? '6281234567890') ?>?text=Halo%20Admin,%20saya%20lupa%20password%20akun%20AquaDepot" target="_blank" class="text-muted">Lupa Password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-6 mb-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk Sekarang
                    </button>

                    <div class="text-center small text-muted">
                        Belum punya akun? <a href="<?= url('auth/register.php') ?>" class="fw-bold text-primary">Daftar Pelanggan Baru</a>
                    </div>
                </form>

                <!-- Quick Test Login Buttons for Evaluator / Testing Convenience -->
                <div class="mt-4 pt-3 border-top text-center" style="border-color: var(--card-border) !important;">
                    <small class="text-muted d-block mb-2 fw-semibold">⚡ Akses Cepat Akun Pengujian (Klik untuk isi):</small>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill small py-1 px-3" onclick="fillLogin('admin', 'admin123')">
                            <i class="bi bi-shield-lock me-1"></i>Admin
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-info rounded-pill small py-1 px-3" onclick="fillLogin('karyawan', 'karyawan123')">
                            <i class="bi bi-person-badge me-1"></i>Karyawan
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-success rounded-pill small py-1 px-3" onclick="fillLogin('pelanggan', 'pelanggan123')">
                            <i class="bi bi-cart me-1"></i>Pelanggan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

