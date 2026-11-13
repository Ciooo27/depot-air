<?php
$pengaturan = get_pengaturan();
$waNumber = preg_replace('/[^0-9]/', '', $pengaturan['no_whatsapp'] ?? '6281234567890');
$waMessage = urlencode("Halo " . ($pengaturan['nama_depot'] ?? 'AquaDepot') . ", saya ingin pesan air mineral galon.");
?>
<!-- Footer -->
<footer class="mt-5 border-top py-5" style="background: var(--card-bg); border-color: var(--card-border) !important;">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 38px; height: 38px; background: linear-gradient(135deg, #1976D2, #64B5F6);">
                        <i class="bi bi-droplet-half fs-5"></i>
                    </div>
                    <span class="fw-bold fs-4"><?= htmlspecialchars($pengaturan['nama_depot']) ?></span>
                </div>
                <p class="text-muted small mb-3">
                    <?= htmlspecialchars($pengaturan['slogan']) ?>. Kami melayani isi ulang air minum RO berkualitas tinggi, galon bermerek original, dan air kemasan untuk kebutuhan rumah tangga maupun bisnis.
                </p>
                <div class="d-flex gap-2">
                    <a href="https://wa.me/<?= $waNumber ?>?text=<?= $waMessage ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <a href="tel:<?= htmlspecialchars($pengaturan['no_telepon']) ?>" class="btn btn-sm btn-outline-primary rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-telephone-fill"></i>
                    </a>
                    <a href="mailto:<?= htmlspecialchars($pengaturan['email']) ?>" class="btn btn-sm btn-outline-info rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-envelope-fill"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <h6 class="fw-bold mb-3">Navigasi</h6>
                <ul class="list-unstyled text-muted small d-flex flex-column gap-2">
                    <li><a href="<?= url('index.php') ?>" class="text-reset">Beranda</a></li>
                    <li><a href="<?= url('views/produk.php') ?>" class="text-reset">Katalog Produk</a></li>
                    <li><a href="<?= url('views/keranjang.php') ?>" class="text-reset">Keranjang Belanja</a></li>
                    <li><a href="<?= url('auth/login.php') ?>" class="text-reset">Masuk Akun</a></li>
                    <li><a href="<?= url('auth/register.php') ?>" class="text-reset">Daftar Pelanggan</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">Jam & Layanan</h6>
                <p class="text-muted small mb-2">
                    <i class="bi bi-clock-fill text-primary me-2"></i><strong>Operasional:</strong><br>
                    <?= htmlspecialchars($pengaturan['jam_operasional']) ?>
                </p>
                <p class="text-muted small mb-2">
                    <i class="bi bi-truck text-primary me-2"></i><strong>Ongkir Wilayah Sekitar:</strong><br>
                    Mulai <?= format_rupiah($pengaturan['ongkos_kirim_default'] ?? 3000) ?> / Pengiriman
                </p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-shield-check text-success me-2"></i>Teruji Higienis & Standar Dinkes
                </p>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3">Lokasi & Kontak</h6>
                <p class="text-muted small mb-2">
                    <i class="bi bi-geo-alt-fill text-danger me-2"></i><?= nl2br(htmlspecialchars($pengaturan['alamat'])) ?>
                </p>
                <p class="text-muted small mb-2">
                    <i class="bi bi-whatsapp text-success me-2"></i><?= htmlspecialchars($pengaturan['no_whatsapp']) ?>
                </p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-envelope-fill text-primary me-2"></i><?= htmlspecialchars($pengaturan['email']) ?>
                </p>
            </div>
        </div>

        <hr class="my-4" style="border-color: var(--card-border);">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start small text-muted">
                &copy; <?= date('Y') ?> <strong><?= htmlspecialchars($pengaturan['nama_depot']) ?></strong>. All rights reserved.
            </div>
            <div class="col-md-6 text-center text-md-end small text-muted mt-2 mt-md-0">
                Sistem Informasi Manajemen Depot Air Mineral Modern
            </div>
        </div>
    </div>
</footer>

<!-- Floating WhatsApp Widget -->
<a href="https://wa.me/<?= $waNumber ?>?text=<?= $waMessage ?>" target="_blank" class="position-fixed shadow-lg d-flex align-items-center justify-content-center text-white" 
   style="bottom: 24px; right: 24px; width: 56px; height: 56px; border-radius: 50%; background: #25D366; z-index: 1050; font-size: 1.8rem; transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);" 
   title="Hubungi Kami via WhatsApp"
   onmouseover="this.style.transform='scale(1.1)'"
   onmouseout="this.style.transform='scale(1)'">
    <i class="bi bi-whatsapp"></i>
</a>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script src="<?= asset('js/cart.js') ?>"></script>

<script>
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
            easing: 'ease-out-cubic'
        });
    }
</script>
</body>
</html>

