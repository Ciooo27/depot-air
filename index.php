<?php
$pageTitle = "AquaDepot - Depot Air Mineral Bersih & Higienis";
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/header.php';

$pengaturan = get_pengaturan();

// Fetch featured products from database
try {
    $db = Database::getConnection();
    $stmtFeatured = $db->query("
        SELECT p.*, s.stok_tersedia, k.nama_kategori 
        FROM produk p
        JOIN kategori k ON p.kategori_id = k.id
        LEFT JOIN stok s ON p.id = s.produk_id
        WHERE p.status = 'tersedia' AND p.is_featured = 1
        ORDER BY p.id ASC
        LIMIT 6
    ");
    $featuredProducts = $stmtFeatured->fetchAll();

    // Counts for stats counter
    $totalGalonTerjual = $db->query("SELECT COALESCE(SUM(jumlah), 0) FROM detail_transaksi")->fetchColumn();
    $totalPelanggan = $db->query("SELECT COUNT(*) FROM pelanggan")->fetchColumn();
} catch (Exception $e) {
    $featuredProducts = [];
    $totalGalonTerjual = 1250;
    $totalPelanggan = 350;
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7" data-aos="fade-right">
                <div class="hero-badge shadow-sm">
                    <i class="bi bi-patch-check-fill text-primary fs-5"></i>
                    <span>Tersertifikasi Higienis & Uji Laboratorium Dinkes</span>
                </div>
                <h1 class="hero-title mb-4">
                    Air Mineral Murni & Segar, <br>
                    <span class="text-gradient">Diantar Tepat Waktu</span> Ke Rumah Anda
                </h1>
                <p class="lead text-muted mb-4" style="font-size: 1.15rem; max-width: 600px;">
                    <?= htmlspecialchars($pengaturan['slogan']) ?>. Nikmati kemudahan memesan air isi ulang murni Reverse Osmosis (RO) dan Bio-Energy Mineral secara online dengan kurir depot ramah & sigap.
                </p>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <a href="<?= url('views/produk.php') ?>" class="btn btn-primary btn-lg rounded-pill px-4 shadow">
                        <i class="bi bi-cart3 me-2"></i>Pesan Air Sekarang
                    </a>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pengaturan['no_whatsapp']) ?>?text=<?= urlencode('Halo AquaDepot, mau tanya stok galon hari ini.') ?>" target="_blank" class="btn btn-outline-success btn-lg rounded-pill px-4 shadow-sm">
                        <i class="bi bi-whatsapp me-2"></i>Hubungi WhatsApp
                    </a>
                </div>

                <!-- Live Metrics Badge -->
                <div class="row g-4 mt-4 pt-3 border-top" style="border-color: var(--card-border) !important;">
                    <div class="col-sm-4">
                        <h4 class="fw-bold mb-0 text-primary">100%</h4>
                        <small class="text-muted">Higienis & Steril UV</small>
                    </div>
                    <div class="col-sm-4">
                        <h4 class="fw-bold mb-0 text-primary"><?= number_format($totalGalonTerjual + 1450) ?>+</h4>
                        <small class="text-muted">Galon Telah Terkirim</small>
                    </div>
                    <div class="col-sm-4">
                        <h4 class="fw-bold mb-0 text-primary">&lt; 30 Menit</h4>
                        <small class="text-muted">Estimasi Pengantaran</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 text-center" data-aos="fade-left">
                <div class="position-relative d-inline-block">
                    <!-- Glass Card Graphic Representation -->
                    <div class="glass-card p-4 rounded-5 text-center shadow-lg position-relative" style="background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.6);">
                        <img src="<?= asset('images/default_product.svg') ?>" alt="AquaDepot Galon" style="max-height: 280px; filter: drop-shadow(0 15px 25px rgba(25, 118, 210, 0.3));" class="img-fluid animate-bounce">
                        
                        <div class="p-3 bg-white rounded-4 shadow-sm mt-3 d-flex align-items-center justify-content-between border">
                            <div class="text-start">
                                <span class="badge bg-success-subtle text-success mb-1">Siap Antar</span>
                                <div class="fw-bold fs-6">Air Galon RO Murni</div>
                                <small class="text-muted">19 Liter • Mulai Rp 6.000</small>
                            </div>
                            <a href="<?= url('views/produk.php') ?>" class="btn btn-sm btn-primary rounded-circle" style="width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Keunggulan Layanan -->
<section class="py-5" id="keunggulan">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5" data-aos="fade-up">
            <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Kenapa Memilih AquaDepot?</span>
            <h2 class="fw-bold">Keunggulan Layanan & Kualitas Kami</h2>
            <p class="text-muted">Kami menjamin air minum yang Anda konsumsi senantiasa aman, segar, dan menyehatkan bagi seluruh keluarga.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card p-4 h-100 text-center">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center text-primary mb-3 p-3 bg-primary-subtle fs-2">
                        <i class="bi bi-funnel-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Filtrasi 8 Tahap + RO</h5>
                    <p class="text-muted small mb-0">Disaring menggunakan membran Reverse Osmosis dengan pori 0.0001 mikron yang menyaring bakteri, virus, dan logam berbahaya.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card p-4 h-100 text-center">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center text-success mb-3 p-3 bg-success-subtle fs-2">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Sterilisasi UV & Ozon</h5>
                    <p class="text-muted small mb-0">Penyinaran ultraviolet ganda serta ozonisasi aktif menjaga air tetap steril dan higienis hingga tetes terakhir.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card p-4 h-100 text-center">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center text-info mb-3 p-3 bg-info-subtle fs-2">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Layanan Antar Kilat</h5>
                    <p class="text-muted small mb-0">Kurir depot kami sigap mengantar galon langsung sampai ke dapur atau dispenser rumah Anda tanpa repot angkat berat.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="glass-card p-4 h-100 text-center">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center text-warning mb-3 p-3 bg-warning-subtle fs-2">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Harga Sangat Hemat</h5>
                    <p class="text-muted small mb-0">Harga terjangkau mulai dari Rp 6.000 per galon dengan metode pembayaran fleksibel: Tunai, QRIS, Transfer, atau COD.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Products Catalog -->
<section class="py-5" style="background: var(--card-bg);" id="produk-unggulan">
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4" data-aos="fade-up">
            <div>
                <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">2 Produk Pilihan Utama</span>
                <h2 class="fw-bold mb-1">Daftar Produk Air Minum</h2>
                <p class="text-muted mb-0">Tersedia 2 varian air galon isi ulang murni & berkualitas tinggi: Air RO dan Air BIO.</p>
            </div>
            <a href="<?= url('views/produk.php') ?>" class="btn btn-outline-primary rounded-pill px-4 mt-3 mt-md-0">
                Lihat Semua Produk <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="col-md-6 col-lg-6" data-aos="fade-up">
                        <div class="product-card">
                            <div class="product-img-wrapper" style="height: 240px;">
                                <img src="<?= product_image($product['gambar']) ?>" alt="<?= htmlspecialchars($product['nama_produk']) ?>" class="img-fluid p-3" style="max-height: 210px;">
                                <span class="position-absolute top-0 start-0 m-3 badge bg-primary rounded-pill px-3 py-2">
                                    <?= htmlspecialchars($product['nama_kategori']) ?>
                                </span>
                            </div>
                            <div class="product-body">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted"><?= htmlspecialchars($product['satuan']) ?></small>
                                    <small class="badge bg-success-subtle text-success">Stok: <?= (int)$product['stok_tersedia'] ?></small>
                                </div>
                                <h5 class="fw-bold mb-2 text-truncate" title="<?= htmlspecialchars($product['nama_produk']) ?>">
                                    <?= htmlspecialchars($product['nama_produk']) ?>
                                </h5>
                                <p class="text-muted small mb-3 flex-grow-1" style="min-height: 40px;">
                                    <?= htmlspecialchars(mb_strimwidth($product['deskripsi'], 0, 85, '...')) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-color: var(--card-border) !important;">
                                    <div>
                                        <div class="text-muted small" style="font-size: 0.75rem;">Harga</div>
                                        <div class="product-price"><?= format_rupiah($product['harga_jual']) ?></div>
                                    </div>
                                    <button type="button" class="btn btn-primary rounded-pill px-3 btn-sm" onclick="addToCart(<?= $product['id'] ?>, 1)">
                                        <i class="bi bi-cart-plus me-1"></i> Pesan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">Belum ada produk unggulan yang ditampilkan.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Cara Pemesanan -->
<section class="py-5" id="cara-pesan">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5" data-aos="fade-up">
            <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Mudah & Cepat</span>
            <h2 class="fw-bold">4 Langkah Mudah Pesan Air di AquaDepot</h2>
            <p class="text-muted">Tidak perlu repot keluar rumah dan membawa beban berat, biar kami yang antarkan!</p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card p-4 h-100">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold mb-3 shadow" style="width: 54px; height: 54px; font-size: 1.4rem;">
                        1
                    </div>
                    <h5 class="fw-bold mb-2">Pilih Produk</h5>
                    <p class="text-muted small mb-0">Pilih varian air minum yang Anda inginkan (Air RO atau Air BIO) dan tentukan jumlah galon yang dibutuhkan.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card p-4 h-100">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold mb-3 shadow" style="width: 54px; height: 54px; font-size: 1.4rem;">
                        2
                    </div>
                    <h5 class="fw-bold mb-2">Isi Data & Alamat</h5>
                    <p class="text-muted small mb-0">Tentukan alamat pengiriman rumah/kantor Anda dan pilih metode apakah ingin diantar kurir atau ambil sendiri.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card p-4 h-100">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold mb-3 shadow" style="width: 54px; height: 54px; font-size: 1.4rem;">
                        3
                    </div>
                    <h5 class="fw-bold mb-2">Pilih Pembayaran</h5>
                    <p class="text-muted small mb-0">Bayar fleksibel dengan opsi COD (Bayar saat galon tiba di tempat), transfer bank otomatis, atau QRIS.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="glass-card p-4 h-100">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center fw-bold mb-3 shadow" style="width: 54px; height: 54px; font-size: 1.4rem;">
                        4
                    </div>
                    <h5 class="fw-bold mb-2">Galon Tiba di Rumah</h5>
                    <p class="text-muted small mb-0">Kurir depot mengantarkan pesanan dengan aman, bersih, dan cepat. Siap dipasang di dispenser Anda!</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimoni Pelanggan -->
<section class="py-5" style="background: var(--card-bg);" id="testimoni">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5" data-aos="fade-up">
            <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Kepuasan Pelanggan</span>
            <h2 class="fw-bold">Apa Kata Pelanggan AquaDepot?</h2>
            <p class="text-muted">Kepercayaan pelanggan adalah kebanggaan kami dalam memberikan pelayanan air minum terbaik.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card p-4 h-100 d-flex flex-column">
                    <div class="text-warning mb-3">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="text-muted small fst-italic flex-grow-1">
                        "Air RO dari AquaDepot rasanya segar sekali dan tidak berbau sama sekali. Pengantaran kurirnya cepat, pesan lewat website 15 menit kemudian sudah sampai depan rumah!"
                    </p>
                    <div class="d-flex align-items-center gap-3 pt-3 border-top" style="border-color: var(--card-border) !important;">
                        <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            SR
                        </div>
                        <div>
                            <div class="fw-bold small">Siti Rahmawati</div>
                            <small class="text-muted">Ibu Rumah Tangga - Sidorejo Lor, Salatiga</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card p-4 h-100 d-flex flex-column">
                    <div class="text-warning mb-3">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="text-muted small fst-italic flex-grow-1">
                        "Depot langganan terbaik untuk kantor kami di Salatiga. Setiap minggu kami order air RO dan BIO untuk karyawan, invoice dan struknya rapi. Sangat recommended!"
                    </p>
                    <div class="d-flex align-items-center gap-3 pt-3 border-top" style="border-color: var(--card-border) !important;">
                        <div class="rounded-circle bg-success-subtle text-success fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            AF
                        </div>
                        <div>
                            <div class="fw-bold small">Ahmad Fauzi</div>
                            <small class="text-muted">Office Manager - Salatiga</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card p-4 h-100 d-flex flex-column">
                    <div class="text-warning mb-3">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="text-muted small fst-italic flex-grow-1">
                        "Air BIO nya segar dan enak banget, badan terasa lebih bugar. Galon selalu dibersihkan dan dilap rapi sebelum diserahkan. Mantap pelayanannya!"
                    </p>
                    <div class="d-flex align-items-center gap-3 pt-3 border-top" style="border-color: var(--card-border) !important;">
                        <div class="rounded-circle bg-info-subtle text-info fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            BJ
                        </div>
                        <div>
                            <div class="fw-bold small">Bu Joko</div>
                            <small class="text-muted">Pemilik Warung Makan - Sidorejo</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Lokasi Depot & Google Maps Embed -->
<section class="py-5" id="kontak">
    <div class="container py-4">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5" data-aos="fade-right">
                <div class="glass-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">Lokasi & Kontak</span>
                        <h3 class="fw-bold mb-3">Kunjungi Depot Kami</h3>
                        <p class="text-muted small mb-4">
                            Anda juga dapat datang langsung ke outlet depot kami untuk melakukan isi ulang galon mandiri dengan harga spesial walk-in.
                        </p>

                        <a href="<?= google_maps_url($pengaturan['alamat'] ?? null, 'search') ?>" target="_blank" class="d-flex align-items-start gap-3 mb-3 text-decoration-none text-reset p-2 rounded-3" style="transition: background 0.2s;" onmouseover="this.style.background='var(--card-border)'" onmouseout="this.style.background='transparent'" title="Klik untuk membuka lokasi di Google Maps">
                            <div class="rounded-circle bg-danger-subtle text-danger p-2 fs-5 flex-shrink-0">
                                <i class="bi bi-geo-alt-fill"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h6 class="fw-bold mb-0">Alamat Outlet</h6>
                                    <span class="badge bg-danger-subtle text-danger small"><i class="bi bi-box-arrow-up-right me-1"></i>Buka Maps</span>
                                </div>
                                <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($pengaturan['alamat'])) ?></p>
                            </div>
                        </a>

                        <div class="d-flex align-items-start gap-3 mb-3 px-2">
                            <div class="rounded-circle bg-primary-subtle text-primary p-2 fs-5 flex-shrink-0">
                                <i class="bi bi-clock-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Jam Operasional</h6>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($pengaturan['jam_operasional']) ?></p>
                            </div>
                        </div>

                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pengaturan['no_whatsapp']) ?>" target="_blank" class="d-flex align-items-start gap-3 mb-3 text-decoration-none text-reset p-2 rounded-3" style="transition: background 0.2s;" onmouseover="this.style.background='var(--card-border)'" onmouseout="this.style.background='transparent'">
                            <div class="rounded-circle bg-success-subtle text-success p-2 fs-5 flex-shrink-0">
                                <i class="bi bi-whatsapp"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">WhatsApp Pemesanan</h6>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($pengaturan['no_whatsapp']) ?></p>
                            </div>
                        </a>
                    </div>

                    <div class="pt-3 border-top d-flex flex-column gap-2" style="border-color: var(--card-border) !important;">
                        <a href="<?= google_maps_url($pengaturan['alamat'] ?? null, 'search') ?>" target="_blank" class="btn btn-primary w-100 rounded-pill py-2 shadow-sm">
                            <i class="bi bi-geo-alt-fill me-2"></i>Buka Lokasi di Google Maps
                        </a>
                        <a href="<?= google_maps_url($pengaturan['alamat'] ?? null, 'dir') ?>" target="_blank" class="btn btn-outline-primary w-100 rounded-pill py-2">
                            <i class="bi bi-compass me-2"></i>Petunjuk Arah (Navigasi Rute)
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-7" data-aos="fade-left">
                <div class="glass-card p-3 h-100 overflow-hidden rounded-4 position-relative">
                    <!-- Tombol langsung ke Google Maps di pojok peta -->
                    <a href="<?= google_maps_url($pengaturan['alamat'] ?? null, 'search') ?>" target="_blank" class="btn btn-sm btn-light position-absolute top-0 end-0 m-4 shadow rounded-pill px-3 py-2 fw-semibold border d-inline-flex align-items-center gap-1 z-3" style="background: rgba(255,255,255,0.95);">
                        <i class="bi bi-box-arrow-up-right text-primary"></i>
                        <span>Buka di Google Maps</span>
                    </a>
                    <div class="w-100 h-100 rounded-3 overflow-hidden" style="min-height: 380px;">
                        <?= $pengaturan['maps_embed'] ?? '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

