<?php
$pageTitle = "Katalog Produk - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getConnection();

// Fetch categories for filtering
$stmtKat = $db->query("SELECT * FROM kategori ORDER BY id ASC");
$categories = $stmtKat->fetchAll();

// Filter logic
$katFilter = (int)($_GET['kategori'] ?? 0);
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT p.*, s.stok_tersedia, k.nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.kategori_id = k.id 
    LEFT JOIN stok s ON p.id = s.produk_id 
    WHERE p.status != 'nonaktif'
";
$params = [];

if ($katFilter > 0) {
    $query .= " AND p.kategori_id = ?";
    $params[] = $katFilter;
}

if (!empty($search)) {
    $query .= " AND (p.nama_produk LIKE ? OR p.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.is_featured DESC, p.nama_produk ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="container py-5">
    <!-- Breadcrumb & Header -->
    <div class="mb-4" data-aos="fade-down">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Beranda</a></li>
                <li class="breadcrumb-item active" aria-current="page">Katalog Produk</li>
            </ol>
        </nav>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="fw-bold mb-1">Katalog Produk Air Minum</h2>
                <p class="text-muted mb-0">Menyediakan 2 pilihan air minum isi ulang berkualitas tinggi: Air RO dan Air BIO.</p>
            </div>
        </div>
    </div>

    <!-- Search & Category Filters -->
    <div class="glass-card p-3 p-md-4 mb-4" data-aos="fade-up">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari Air RO atau Air BIO..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary px-4" type="submit">Cari</button>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="<?= url('views/produk.php') ?>" class="btn btn-sm rounded-pill px-3 <?= ($katFilter === 0) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Semua Kategori
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?= url('views/produk.php?kategori=' . $cat['id']) ?>" class="btn btn-sm rounded-pill px-3 <?= ($katFilter === (int)$cat['id']) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <?= htmlspecialchars($cat['nama_kategori']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Products Grid -->
    <div class="row g-4">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $prod): ?>
                <div class="col-sm-6 col-lg-6" data-aos="fade-up">
                    <div class="product-card">
                        <div class="product-img-wrapper" style="height: 250px;">
                            <img src="<?= product_image($prod['gambar']) ?>" alt="<?= htmlspecialchars($prod['nama_produk']) ?>" class="p-3" style="max-height: 220px;">
                            <span class="position-absolute top-0 start-0 m-3 badge bg-primary rounded-pill px-3 py-1 text-truncate" style="max-width: 160px;">
                                <?= htmlspecialchars($prod['nama_kategori']) ?>
                            </span>
                            <?php if ($prod['stok_tersedia'] <= 0): ?>
                                <span class="position-absolute top-0 end-0 m-3 badge bg-danger rounded-pill px-2 py-1">
                                    Stok Habis
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="product-body">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted"><?= htmlspecialchars($prod['satuan']) ?></small>
                                <small class="badge <?= ($prod['stok_tersedia'] > 5) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                                    Stok: <?= (int)$prod['stok_tersedia'] ?>
                                </small>
                            </div>
                            <h6 class="fw-bold mb-2" style="min-height: 42px;" title="<?= htmlspecialchars($prod['nama_produk']) ?>">
                                <?= htmlspecialchars($prod['nama_produk']) ?>
                            </h6>
                            <p class="text-muted small mb-3 flex-grow-1" style="min-height: 45px;">
                                <?= htmlspecialchars(mb_strimwidth($prod['deskripsi'], 0, 80, '...')) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-color: var(--card-border) !important;">
                                <div>
                                    <small class="text-muted d-block" style="font-size: 0.7rem;">Harga</small>
                                    <div class="product-price fs-6"><?= format_rupiah($prod['harga_jual']) ?></div>
                                </div>
                                <?php if ($prod['stok_tersedia'] > 0): ?>
                                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" onclick="addToCart(<?= $prod['id'] ?>, 1)">
                                        <i class="bi bi-cart-plus me-1"></i> Beli
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" disabled>
                                        Habis
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 py-5 text-center" data-aos="fade-up">
                <div class="glass-card p-5 max-w-500 mx-auto">
                    <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                    <h5 class="fw-bold">Produk Tidak Ditemukan</h5>
                    <p class="text-muted small">Coba ubah kata kunci pencarian atau pilih kategori lain.</p>
                    <a href="<?= url('views/produk.php') ?>" class="btn btn-outline-primary rounded-pill px-4">Reset Filter</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

