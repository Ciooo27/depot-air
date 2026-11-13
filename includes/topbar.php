<?php
require_once __DIR__ . '/../config/config.php';
$user = current_user();
?>
<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-light rounded-circle shadow-sm" id="sidebarToggleBtn" title="Menu Sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="d-none d-md-block">
            <span class="text-muted small">Selamat Datang,</span>
            <div class="fw-bold fs-6 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($user['nama_lengkap'] ?? 'User') ?></div>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <!-- Quick Link to Public Website -->
        <a href="<?= url('index.php') ?>" target="_blank" class="btn btn-outline-primary rounded-pill px-3 py-1 btn-sm d-none d-sm-inline-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-up-right"></i>
            <span>Lihat Toko</span>
        </a>

        <!-- Dark Mode Toggle -->
        <button type="button" class="btn btn-light rounded-circle theme-toggle-btn shadow-sm" style="width: 40px; height: 40px;" title="Ganti Tema">
            <i class="bi bi-moon-stars-fill theme-toggle-icon"></i>
        </button>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light rounded-pill px-3 py-1 dropdown-toggle shadow-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem;">
                    <?= strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="text-start d-none d-lg-block" style="line-height: 1.2;">
                    <div class="fw-bold small"><?= htmlspecialchars($user['username'] ?? '') ?></div>
                    <small class="text-muted text-uppercase" style="font-size: 0.68rem;"><?= htmlspecialchars($user['role_name'] ?? '') ?></small>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 mt-2">
                <li class="px-3 py-2 border-bottom mb-2">
                    <div class="fw-bold"><?= htmlspecialchars($user['nama_lengkap'] ?? '') ?></div>
                    <small class="text-muted"><?= htmlspecialchars($user['email'] ?? '') ?></small>
                </li>
                <?php if ($user['role_name'] === 'admin'): ?>
                    <li><a class="dropdown-item rounded-3 py-2" href="<?= url('admin/pengaturan.php') ?>"><i class="bi bi-gear me-2 text-primary"></i>Pengaturan Sistem</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item rounded-3 py-2" href="<?= url('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Keluar</a></li>
            </ul>
        </div>
    </div>
</header>

