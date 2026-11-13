<?php
$pageTitle = "Pengaturan Depot - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$db = Database::getConnection();

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $namaDepot = trim($_POST['nama_depot'] ?? 'AquaDepot');
    $slogan = trim($_POST['slogan'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $noTelepon = trim($_POST['no_telepon'] ?? '');
    $noWhatsapp = trim($_POST['no_whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $jamOperasional = trim($_POST['jam_operasional'] ?? '');
    $ongkir = (float)($_POST['ongkos_kirim_default'] ?? 3000);
    $rekening = trim($_POST['rekening_bank'] ?? '');
    $mapsEmbed = trim($_POST['maps_embed'] ?? '');

    try {
        $stmt = $db->prepare("
            UPDATE pengaturan 
            SET nama_depot = ?, slogan = ?, alamat = ?, no_telepon = ?, no_whatsapp = ?, email = ?, 
                jam_operasional = ?, ongkos_kirim_default = ?, rekening_bank = ?, maps_embed = ?, updated_at = NOW()
            WHERE id = 1
        ");
        $stmt->execute([
            $namaDepot, $slogan, $alamat, $noTelepon, $noWhatsapp, $email,
            $jamOperasional, $ongkir, $rekening, $mapsEmbed
        ]);

        set_flash('success', 'Pengaturan depot berhasil disimpan!');
    } catch (Exception $e) {
        set_flash('error', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
    }
    redirect('admin/pengaturan.php');
}

$pengaturan = get_pengaturan();
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
                    <h3 class="fw-bold mb-1">Pengaturan & Konfigurasi Depot</h3>
                    <p class="text-muted small mb-0">Kelola identitas usaha, kontak WhatsApp, tarif standar ongkir, dan rekening pembayaran.</p>
                </div>
            </div>

            <div class="glass-card p-4 p-md-5">
                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="bi bi-shop"></i> 1. Profil & Identitas Usaha Depot
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Depot Air Mineral</label>
                            <input type="text" class="form-control" name="nama_depot" value="<?= htmlspecialchars($pengaturan['nama_depot']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Slogan / Tagline</label>
                            <input type="text" class="form-control" name="slogan" value="<?= htmlspecialchars($pengaturan['slogan']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Alamat Fisik Depot</label>
                            <textarea class="form-control" name="alamat" rows="2" required><?= htmlspecialchars($pengaturan['alamat']) ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jam Operasional Pelayanan</label>
                            <input type="text" class="form-control" name="jam_operasional" value="<?= htmlspecialchars($pengaturan['jam_operasional']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Ongkos Kirim Default (Rp)</label>
                            <input type="number" class="form-control" name="ongkos_kirim_default" min="0" value="<?= (float)$pengaturan['ongkos_kirim_default'] ?>" required>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2 border-top pt-4" style="border-color: var(--card-border) !important;">
                        <i class="bi bi-chat-dots"></i> 2. Kontak & Komunikasi Pelanggan
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Nomor WhatsApp Depot (Gunakan kode 62)</label>
                            <input type="text" class="form-control" name="no_whatsapp" value="<?= htmlspecialchars($pengaturan['no_whatsapp']) ?>" placeholder="cth: 6281234567890" required>
                            <small class="text-muted">Nomor ini terhubung dengan tombol pemesanan WhatsApp otomatis.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Nomor Telepon Kantor</label>
                            <input type="text" class="form-control" name="no_telepon" value="<?= htmlspecialchars($pengaturan['no_telepon']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Alamat Email Resmi</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($pengaturan['email']) ?>" required>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2 border-top pt-4" style="border-color: var(--card-border) !important;">
                        <i class="bi bi-credit-card"></i> 3. Rekening Bank & Peta Lokasi
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Daftar Rekening Bank Resmi (Untuk Transfer)</label>
                            <textarea class="form-control" name="rekening_bank" rows="4" placeholder="Nama Bank: No Rekening a/n Nama Pemilik"><?= htmlspecialchars($pengaturan['rekening_bank']) ?></textarea>
                            <small class="text-muted">Informasi ini akan muncul di halaman checkout saat pelanggan memilih Transfer Bank.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kode Embed Google Maps (Iframe)</label>
                            <textarea class="form-control" name="maps_embed" rows="4"><?= htmlspecialchars($pengaturan['maps_embed'] ?? '') ?></textarea>
                            <small class="text-muted">Copy kode iframe dari Google Maps &raquo; Share &raquo; Embed a map.</small>
                        </div>
                    </div>

                    <div class="pt-3 border-top" style="border-color: var(--card-border) !important;">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-semibold shadow">
                            <i class="bi bi-save me-2"></i>Simpan Seluruh Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
</body>
</html>

