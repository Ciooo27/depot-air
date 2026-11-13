<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/CartController.php';

$pengaturan = get_pengaturan();
$cartCount = CartController::getCount();
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $pengaturan['nama_depot']) ?> - Depot Air Mineral Berkualitas</title>
    <meta name="description" content="<?= htmlspecialchars($pengaturan['slogan'] ?? 'Pesan air mineral galon isi ulang higienis dengan layanan antar cepat') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- AOS (Animate on Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body data-base-url="<?= base_url() ?>">

<?php if ($flash = get_flash()): ?>
    <div id="flash-message-data" data-type="<?= $flash['type'] ?>" data-message="<?= htmlspecialchars($flash['message']) ?>"></div>
<?php endif; ?>

<!-- Public Navbar -->
<nav class="navbar navbar-expand-lg sticky-top glass-card border-0 rounded-0" style="background: var(--glass-bg); backdrop-filter: blur(14px); z-index: 1040; border-bottom: 1px solid var(--card-border) !important;">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('index.php') ?>">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 42px; height: 42px; background: linear-gradient(135deg, #1976D2, #64B5F6); box-shadow: 0 4px 10px rgba(25, 118, 210, 0.3);">
                <i class="bi bi-droplet-half fs-4"></i>
            </div>
            <div>
                <span class="fw-bold fs-4 text-gradient"><?= htmlspecialchars($pengaturan['nama_depot']) ?></span>
                <small class="d-none d-sm-block text-muted" style="font-size: 0.72rem; line-height: 1;"><?= htmlspecialchars($pengaturan['slogan']) ?></small>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
            <i class="bi bi-list fs-2 text-primary"></i>
        </button>

        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-semibold">
                <li class="nav-item">
                    <a class="nav-link px-3 <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'text-primary' : '' ?>" href="<?= url('index.php') ?>">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 <?= (strpos($_SERVER['PHP_SELF'], 'produk') !== false) ? 'text-primary' : '' ?>" href="<?= url('views/produk.php') ?>">Katalog Produk</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="<?= url('index.php#keunggulan') ?>">Keunggulan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="<?= url('index.php#cara-pesan') ?>">Cara Pesan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="<?= url('index.php#kontak') ?>">Kontak & Lokasi</a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <!-- Dark Mode Toggle -->
                <button type="button" class="btn btn-light rounded-circle theme-toggle-btn shadow-sm" style="width: 42px; height: 42px;" title="Ganti Tema">
                    <i class="bi bi-moon-stars-fill theme-toggle-icon"></i>
                </button>

                <!-- Keranjang Belanja -->
                <a href="<?= url('views/keranjang.php') ?>" class="btn btn-light rounded-pill position-relative px-3 shadow-sm d-flex align-items-center gap-2">
                    <i class="bi bi-cart3 text-primary fs-5"></i>
                    <span class="d-none d-sm-inline fw-semibold">Keranjang</span>
                    <span class="badge rounded-pill bg-danger cart-badge <?= ($cartCount == 0) ? 'd-none' : '' ?>">
                        <?= $cartCount ?>
                    </span>
                </a>

                <?php if ($currentUser): ?>
                    <!-- Dropdown User Login -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary rounded-pill px-3 dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle fs-5"></i>
                            <span class="d-none d-md-inline"><?= htmlspecialchars(explode(' ', $currentUser['nama_lengkap'])[0]) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 mt-2">
                            <li class="px-3 py-2 border-bottom mb-2">
                                <div class="fw-bold"><?= htmlspecialchars($currentUser['nama_lengkap']) ?></div>
                                <small class="badge bg-primary-subtle text-primary text-uppercase"><?= $currentUser['role_name'] ?></small>
                            </li>
                            <?php if ($currentUser['role_name'] === 'admin'): ?>
                                <li><a class="dropdown-item rounded-3 py-2" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2 text-primary"></i>Panel Admin</a></li>
                            <?php elseif ($currentUser['role_name'] === 'karyawan'): ?>
                                <li><a class="dropdown-item rounded-3 py-2" href="<?= url('karyawan/index.php') ?>"><i class="bi bi-pc-display-horizontal me-2 text-primary"></i>Panel Karyawan</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item rounded-3 py-2" href="<?= url('pelanggan/index.php') ?>"><i class="bi bi-grid me-2 text-primary"></i>Dashboard Saya</a></li>
                                <li><a class="dropdown-item rounded-3 py-2" href="<?= url('pelanggan/riwayat.php') ?>"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Pesanan</a></li>
                                <li><a class="dropdown-item rounded-3 py-2" href="<?= url('pelanggan/profil.php') ?>"><i class="bi bi-person-gear me-2 text-primary"></i>Profil Saya</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item rounded-3 py-2 text-danger" href="<?= url('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= url('auth/login.php') ?>" class="btn btn-outline-primary rounded-pill px-3">Masuk</a>
                    <a href="<?= url('auth/register.php') ?>" class="btn btn-primary rounded-pill px-3">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

