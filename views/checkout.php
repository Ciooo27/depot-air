<?php
$pageTitle = "Checkout Pemesanan - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../controllers/CartController.php';

$cartData = CartController::getCart();
$cartItems = $cartData['items'];
$totalPrice = $cartData['total'];
$pengaturan = get_pengaturan();

if (empty($cartItems)) {
    set_flash('warning', 'Keranjang belanja Anda masih kosong. Silakan pilih produk terlebih dahulu.');
    redirect('views/produk.php');
}

$currentUser = current_user();

// Pre-fill fields if user is logged in
$nama = $currentUser['nama_lengkap'] ?? '';
$whatsapp = $currentUser['no_telepon'] ?? '';
$alamat = $currentUser['alamat'] ?? '';
$metodePengambilan = 'antar';
$metodePembayaran = 'cod';
$catatan = '';
$error = '';

$ongkirDefault = (float)($pengaturan['ongkos_kirim_default'] ?? 3000);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $whatsapp = trim($_POST['no_whatsapp'] ?? '');
    $metodePengambilan = $_POST['metode_pengambilan'] ?? 'antar';
    $alamat = ($metodePengambilan === 'antar') ? trim($_POST['alamat'] ?? '') : 'Ambil Mandiri di Depot';
    $catatan = trim($_POST['catatan'] ?? '');
    $metodePembayaran = $_POST['metode_pembayaran'] ?? 'cod';

    if (empty($nama) || empty($whatsapp)) {
        $error = 'Nama lengkap dan nomor WhatsApp wajib diisi.';
    } elseif ($metodePengambilan === 'antar' && empty($alamat)) {
        $error = 'Alamat pengiriman wajib diisi untuk layanan antar.';
    } else {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // 1. Determine or create Pelanggan ID
            $pelangganId = null;
            if ($currentUser && isset($currentUser['pelanggan_id'])) {
                $pelangganId = $currentUser['pelanggan_id'];
            } else {
                // Check if customer with this phone already exists
                $stmtFind = $db->prepare("SELECT id FROM pelanggan WHERE no_telepon = ? LIMIT 1");
                $stmtFind->execute([$whatsapp]);
                $pelangganId = $stmtFind->fetchColumn();

                if (!$pelangganId) {
                    $kodePelanggan = 'PLG-' . strtoupper(substr(uniqid(), -6));
                    $userId = $currentUser['id'] ?? null;
                    $stmtInsPlg = $db->prepare("
                        INSERT INTO pelanggan (user_id, kode_pelanggan, nama, no_telepon, alamat, tipe_pelanggan)
                        VALUES (?, ?, ?, ?, ?, 'umum')
                    ");
                    $stmtInsPlg->execute([$userId, $kodePelanggan, $nama, $whatsapp, $alamat]);
                    $pelangganId = $db->lastInsertId();
                }
            }

            // 2. Calculate Final Costs
            $biayaPengiriman = ($metodePengambilan === 'antar') ? $ongkirDefault : 0.00;
            $totalBayar = $totalPrice + $biayaPengiriman;

            // 3. Generate Kode Pesanan (e.g. ORD-20261109-XXXX)
            $kodePesanan = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // 4. Insert into `pesanan`
            $stmtPesanan = $db->prepare("
                INSERT INTO pesanan (kode_pesanan, pelanggan_id, tanggal_pesanan, metode_pengambilan, alamat_pengiriman, catatan, metode_pembayaran, total_harga, biaya_pengiriman, total_bayar, status_pesanan)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'menunggu_konfirmasi')
            ");
            $stmtPesanan->execute([
                $kodePesanan,
                $pelangganId,
                $metodePengambilan,
                $alamat,
                $catatan,
                $metodePembayaran,
                $totalPrice,
                $biayaPengiriman,
                $totalBayar
            ]);
            $pesananId = $db->lastInsertId();

            // 5. Insert `detail_pesanan` & deduct stock
            $stmtDetail = $db->prepare("
                INSERT INTO detail_pesanan (pesanan_id, produk_id, harga_satuan, jumlah, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmtCheckStock = $db->prepare("SELECT stok_tersedia FROM stok WHERE produk_id = ? FOR UPDATE");
            $stmtUpdateStock = $db->prepare("UPDATE stok SET stok_tersedia = stok_tersedia - ? WHERE produk_id = ?");
            $stmtLogStock = $db->prepare("
                INSERT INTO riwayat_stok (produk_id, user_id, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, created_at)
                VALUES (?, ?, 'keluar', ?, ?, ?, ?, NOW())
            ");

            // System / Default actor user id for customer order
            $actorUserId = $currentUser['id'] ?? 1;

            foreach ($cartItems as $item) {
                // Stock lock & verify
                $stmtCheckStock->execute([$item['id']]);
                $currentStock = (int)$stmtCheckStock->fetchColumn();

                if ($currentStock < $item['jumlah']) {
                    throw new Exception("Maaf, stok untuk " . $item['nama_produk'] . " tidak mencukupi (sisa: $currentStock).");
                }

                // Insert detail pesanan
                $stmtDetail->execute([
                    $pesananId,
                    $item['id'],
                    $item['harga'],
                    $item['jumlah'],
                    $item['subtotal']
                ]);

                // Deduct stock
                $stmtUpdateStock->execute([$item['jumlah'], $item['id']]);

                // Log stock change
                $stokSesudah = $currentStock - $item['jumlah'];
                $stmtLogStock->execute([
                    $item['id'],
                    $actorUserId,
                    $item['jumlah'],
                    $currentStock,
                    $stokSesudah,
                    "Pesanan online $kodePesanan"
                ]);
            }

            // 6. Create `pengiriman` if 'antar'
            if ($metodePengambilan === 'antar') {
                $kodePengiriman = 'DELIV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $stmtPengiriman = $db->prepare("
                    INSERT INTO pengiriman (pesanan_id, kode_pengiriman, status_pengiriman, alamat_tujuan, catatan_kurir)
                    VALUES (?, ?, 'menunggu_konfirmasi', ?, ?)
                ");
                $stmtPengiriman->execute([$pesananId, $kodePengiriman, $alamat, $catatan]);
            }

            // 7. Create `pembayaran` record
            $kodePembayaran = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $statusBayar = ($metodePembayaran === 'transfer') ? 'menunggu_verifikasi' : 'pending';
            $stmtPay = $db->prepare("
                INSERT INTO pembayaran (pesanan_id, kode_pembayaran, metode, jumlah, status, tanggal_bayar)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmtPay->execute([$pesananId, $kodePembayaran, $metodePembayaran, $totalBayar, $statusBayar]);

            $db->commit();

            // Clear Cart
            CartController::clear();

            // Redirect to Success Page
            redirect('views/pesanan_sukses.php?id=' . $pesananId);

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Gagal memproses pesanan: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <!-- Breadcrumb -->
    <div class="mb-4" data-aos="fade-down">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= url('views/keranjang.php') ?>">Keranjang</a></li>
                <li class="breadcrumb-item active" aria-current="page">Checkout</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">Formulir Checkout Pesanan</h2>
        <p class="text-muted">Lengkapi detail pengiriman dan pilih metode pembayaran favorit Anda.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center rounded-4 p-3 mb-4" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-4"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="checkoutForm">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- Form Details -->
            <div class="col-lg-7" data-aos="fade-right">
                <div class="glass-card p-4 mb-4">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-person-lines-fill text-primary"></i> 1. Informasi Penerima
                    </h5>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" placeholder="Nama Penerima" value="<?= htmlspecialchars($nama) ?>" required>
                        <label for="nama_lengkap"><i class="bi bi-person me-2 text-primary"></i>Nama Lengkap</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="no_whatsapp" name="no_whatsapp" placeholder="Nomor WhatsApp" value="<?= htmlspecialchars($whatsapp) ?>" required>
                        <label for="no_whatsapp"><i class="bi bi-whatsapp me-2 text-success"></i>Nomor WhatsApp (Untuk Konfirmasi Kurir)</label>
                    </div>

                    <h5 class="fw-bold mb-3 mt-4 d-flex align-items-center gap-2">
                        <i class="bi bi-truck text-primary"></i> 2. Metode Pengambilan
                    </h5>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="card p-3 h-100 rounded-3 border cursor-pointer method-card" for="metodeAntar">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="metode_pengambilan" id="metodeAntar" value="antar" <?= ($metodePengambilan === 'antar') ? 'checked' : '' ?> onchange="toggleDelivery(true)">
                                    <span class="form-check-label fw-bold d-block">Layanan Antar Kurir</span>
                                    <small class="text-muted">Diantar sampai pintu rumah (+<?= format_rupiah($ongkirDefault) ?>)</small>
                                </div>
                            </label>
                        </div>
                        <div class="col-sm-6">
                            <label class="card p-3 h-100 rounded-3 border cursor-pointer method-card" for="metodeAmbil">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="metode_pengambilan" id="metodeAmbil" value="ambil_sendiri" <?= ($metodePengambilan === 'ambil_sendiri') ? 'checked' : '' ?> onchange="toggleDelivery(false)">
                                    <span class="form-check-label fw-bold d-block">Ambil Sendiri di Depot</span>
                                    <small class="text-muted">Ambil langsung di gerai (Ongkir Rp 0)</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div id="alamatContainer" class="<?= ($metodePengambilan === 'ambil_sendiri') ? 'd-none' : '' ?>">
                        <div class="form-floating mb-3">
                            <textarea class="form-control" placeholder="Alamat Pengiriman Lengkap" id="alamat" name="alamat" style="height: 100px"><?= htmlspecialchars($alamat) ?></textarea>
                            <label for="alamat"><i class="bi bi-geo-alt me-2 text-danger"></i>Alamat Lengkap (Nama Jalan, No. Rumah, RT/RW, Patokan)</label>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="catatan" name="catatan" placeholder="Catatan Tambahan (Opsional)" value="<?= htmlspecialchars($catatan) ?>">
                        <label for="catatan"><i class="bi bi-pencil-square me-2 text-muted"></i>Catatan Tambahan (cth: Titip pos satpam / Bel rumah rusak)</label>
                    </div>

                    <h5 class="fw-bold mb-3 mt-4 d-flex align-items-center gap-2">
                        <i class="bi bi-credit-card text-primary"></i> 3. Metode Pembayaran
                    </h5>

                    <div class="d-flex flex-column gap-2 mb-3">
                        <label class="card p-3 rounded-3 border cursor-pointer">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metode_pembayaran" id="payCOD" value="cod" <?= ($metodePembayaran === 'cod') ? 'checked' : '' ?> onchange="toggleBankInfo(false)">
                                <span class="form-check-label fw-bold">COD (Cash on Delivery / Bayar Tunai ke Kurir Saat Tiba)</span>
                                <small class="text-muted d-block">Bayar tunai ke kurir saat galon sampai di rumah Anda.</small>
                            </div>
                        </label>

                        <label class="card p-3 rounded-3 border cursor-pointer">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metode_pembayaran" id="payTransfer" value="transfer" <?= ($metodePembayaran === 'transfer') ? 'checked' : '' ?> onchange="toggleBankInfo(true)">
                                <span class="form-check-label fw-bold">Transfer Bank / Mobile Banking</span>
                                <small class="text-muted d-block">Transfer ke rekening resmi depot AquaDepot.</small>
                            </div>
                        </label>

                        <label class="card p-3 rounded-3 border cursor-pointer">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metode_pembayaran" id="payTunai" value="tunai" <?= ($metodePembayaran === 'tunai') ? 'checked' : '' ?> onchange="toggleBankInfo(false)">
                                <span class="form-check-label fw-bold">Bayar Tunai di Kasir Depot</span>
                                <small class="text-muted d-block">Khusus jika Anda datang mengambil galon langsung di depot.</small>
                            </div>
                        </label>
                    </div>

                    <!-- Bank Account Info Box -->
                    <div id="bankInfoBox" class="p-3 bg-primary-subtle rounded-3 mb-3 <?= ($metodePembayaran === 'transfer') ? '' : 'd-none' ?>">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-bank me-2"></i>Rekening Resmi AquaDepot:</h6>
                        <pre class="mb-0 bg-white p-3 rounded-2 border small" style="white-space: pre-wrap; font-family: inherit;"><?= htmlspecialchars($pengaturan['rekening_bank']) ?></pre>
                        <small class="text-muted d-block mt-2">Mohon simpan bukti transfer untuk ditunjukkan kepada kurir atau di-upload pada dashboard pelanggan.</small>
                    </div>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="col-lg-5" data-aos="fade-left">
                <div class="glass-card p-4 position-sticky" style="top: 90px;">
                    <h5 class="fw-bold mb-3">Ringkasan Pesanan</h5>

                    <div class="list-group list-group-flush mb-3">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="list-group-item bg-transparent px-0 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold small"><?= htmlspecialchars($item['nama_produk']) ?></div>
                                    <small class="text-muted"><?= $item['jumlah'] ?> x <?= format_rupiah($item['harga']) ?></small>
                                </div>
                                <span class="fw-bold text-dark small"><?= format_rupiah($item['subtotal']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal Produk</span>
                        <span class="fw-bold"><?= format_rupiah($totalPrice) ?></span>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Biaya Pengiriman</span>
                        <span class="fw-bold text-primary" id="ongkirDisplay"><?= format_rupiah($ongkirDefault) ?></span>
                    </div>

                    <hr style="border-color: var(--card-border);">

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="fw-bold fs-5">Total Pembayaran</span>
                        <span class="fw-bold fs-4 text-primary" id="totalBayarDisplay">
                            <?= format_rupiah($totalPrice + $ongkirDefault) ?>
                        </span>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-6 shadow">
                        <i class="bi bi-check2-circle me-2"></i>Konfirmasi & Buat Pesanan
                    </button>

                    <div class="text-center mt-3 small text-muted">
                        <i class="bi bi-shield-lock-fill text-success me-1"></i> Transaksi Aman & Terverifikasi
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const subtotalPrice = <?= (float)$totalPrice ?>;
const ongkirValue = <?= (float)$ongkirDefault ?>;

function toggleDelivery(isDelivery) {
    const alamatContainer = document.getElementById('alamatContainer');
    const ongkirDisplay = document.getElementById('ongkirDisplay');
    const totalBayarDisplay = document.getElementById('totalBayarDisplay');

    if (isDelivery) {
        alamatContainer.classList.remove('d-none');
        document.getElementById('alamat').setAttribute('required', 'required');
        ongkirDisplay.innerText = formatRupiahJs(ongkirValue);
        totalBayarDisplay.innerText = formatRupiahJs(subtotalPrice + ongkirValue);
    } else {
        alamatContainer.classList.add('d-none');
        document.getElementById('alamat').removeAttribute('required');
        ongkirDisplay.innerText = 'Rp 0 (Ambil Sendiri)';
        totalBayarDisplay.innerText = formatRupiahJs(subtotalPrice);
    }
}

function toggleBankInfo(show) {
    const box = document.getElementById('bankInfoBox');
    if (show) {
        box.classList.remove('d-none');
    } else {
        box.classList.add('d-none');
    }
}

function formatRupiahJs(angka) {
    return 'Rp ' + Number(angka).toLocaleString('id-ID');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

