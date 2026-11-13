<?php
$pageTitle = "Keranjang Pemesanan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/CartController.php';

$cartData = CartController::getCart();
$cartItems = $cartData['items'];
$totalPrice = $cartData['total'];
$pengaturan = get_pengaturan();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <div class="mb-4" data-aos="fade-down">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= url('views/produk.php') ?>">Katalog Produk</a></li>
                <li class="breadcrumb-item active" aria-current="page">Keranjang Belanja</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">Keranjang Pemesanan Air</h2>
        <p class="text-muted">Periksa kembali jumlah galon dan barang sebelum melanjutkan ke pengisian alamat dan pembayaran.</p>
    </div>

    <?php if (!empty($cartItems)): ?>
        <div class="row g-4">
            <!-- Items Table -->
            <div class="col-lg-8" data-aos="fade-right">
                <div class="glass-card p-4">
                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Harga</th>
                                    <th style="width: 140px;">Jumlah</th>
                                    <th>Subtotal</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-3 bg-light p-2 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; border: 1px solid var(--card-border);">
                                                    <img src="<?= product_image($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama_produk']) ?>" style="max-height: 40px;">
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($item['nama_produk']) ?></h6>
                                                    <small class="text-muted"><?= htmlspecialchars($item['satuan']) ?> • Sisa Stok: <?= (int)$item['stok_tersedia'] ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="fw-semibold">
                                            <?= format_rupiah($item['harga']) ?>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="width: 110px;">
                                                <button class="btn btn-outline-secondary" type="button" onclick="updateCartQty(<?= $item['id'] ?>, <?= $item['jumlah'] - 1 ?>)">
                                                    <i class="bi bi-dash"></i>
                                                </button>
                                                <input type="number" class="form-control text-center px-1" value="<?= $item['jumlah'] ?>" min="1" max="<?= $item['stok_tersedia'] ?>" onchange="updateCartQty(<?= $item['id'] ?>, this.value)">
                                                <button class="btn btn-outline-secondary" type="button" onclick="updateCartQty(<?= $item['id'] ?>, <?= $item['jumlah'] + 1 ?>)" <?= ($item['jumlah'] >= $item['stok_tersedia']) ? 'disabled' : '' ?>>
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-primary">
                                            <?= format_rupiah($item['subtotal']) ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle" style="width: 32px; height: 32px;" onclick="removeFromCart(<?= $item['id'] ?>)" title="Hapus Item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top" style="border-color: var(--card-border) !important;">
                        <a href="<?= url('views/produk.php') ?>" class="btn btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i> Tambah Produk Lain
                        </a>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-3" onclick="clearCart()">
                            <i class="bi bi-trash3 me-1"></i> Kosongkan Keranjang
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Card -->
            <div class="col-lg-4" data-aos="fade-left">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-3">Ringkasan Belanja</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Pembelian</span>
                        <span class="fw-bold"><?= format_rupiah($totalPrice) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Estimasi Pengiriman</span>
                        <span class="text-success fw-semibold">Dihitung di checkout</span>
                    </div>

                    <hr style="border-color: var(--card-border);">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fw-bold fs-6">Subtotal Sementara</span>
                        <span class="fw-bold fs-5 text-primary"><?= format_rupiah($totalPrice) ?></span>
                    </div>

                    <a href="<?= url('views/checkout.php') ?>" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-6 mb-3 shadow">
                        <i class="bi bi-shield-check me-2"></i>Lanjut ke Checkout
                    </a>

                    <div class="p-3 bg-light rounded-3 small text-muted border">
                        <div class="d-flex align-items-center gap-2 mb-1 fw-bold text-dark">
                            <i class="bi bi-truck text-primary"></i> Layanan Pengiriman Depot
                        </div>
                        Pesan sebelum jam 17:00 WIB untuk jaminan pengantaran di hari yang sama!
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5" data-aos="zoom-in">
            <div class="glass-card p-5 max-w-500 mx-auto">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-primary bg-primary-subtle mb-3" style="width: 80px; height: 80px; font-size: 2.5rem;">
                    <i class="bi bi-cart-x"></i>
                </div>
                <h4 class="fw-bold">Keranjang Belanja Masih Kosong</h4>
                <p class="text-muted small mb-4">Anda belum menambahkan air galon atau produk lainnya ke dalam keranjang.</p>
                <a href="<?= url('views/produk.php') ?>" class="btn btn-primary rounded-pill px-4 py-2">
                    <i class="bi bi-droplet-half me-2"></i>Mulai Belanja Sekarang
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function clearCart() {
    if (confirm('Apakah Anda yakin ingin mengosongkan seluruh keranjang belanja?')) {
        const baseUrl = document.body.getAttribute('data-base-url') || '';
        fetch(`${baseUrl}/controllers/CartController.php?action=remove&product_id=0`)
            .then(() => location.reload());
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

