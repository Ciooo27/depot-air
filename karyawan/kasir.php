<?php
$pageTitle = "Kasir POS - Input Transaksi - AquaDepot";
require_once __DIR__ . '/../config/config.php';
require_role(['karyawan', 'admin']);

$db = Database::getConnection();

// Handle Direct POS Sale Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_transaction') {
    verify_csrf();

    $pelangganId = !empty($_POST['pelanggan_id']) ? (int)$_POST['pelanggan_id'] : null;
    $metodePembayaran = $_POST['metode_pembayaran'] ?? 'tunai';
    $diskon = (float)($_POST['diskon'] ?? 0);
    $ongkir = (float)($_POST['biaya_pengiriman'] ?? 0);
    $jumlahBayar = (float)($_POST['jumlah_bayar'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $items = $_POST['items'] ?? []; // array of [produk_id => qty]

    if (empty($items)) {
        set_flash('error', 'Pilih minimal satu produk untuk transaksi kasir.');
    } else {
        try {
            $db->beginTransaction();

            $subtotal = 0;
            $totalItemCount = 0;
            $orderLines = [];

            // Fetch products and lock stock
            $stmtProd = $db->prepare("SELECT p.*, s.stok_tersedia FROM produk p JOIN stok s ON p.id = s.produk_id WHERE p.id = ? FOR UPDATE");

            foreach ($items as $prodId => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;

                $stmtProd->execute([$prodId]);
                $p = $stmtProd->fetch();

                if (!$p) continue;

                if ($qty > $p['stok_tersedia']) {
                    throw new Exception("Stok untuk " . $p['nama_produk'] . " tidak cukup (tersedia: " . $p['stok_tersedia'] . ").");
                }

                $itemSubtotal = $p['harga_jual'] * $qty;
                $subtotal += $itemSubtotal;
                $totalItemCount += $qty;

                $orderLines[] = [
                    'produk_id' => $p['id'],
                    'nama_produk' => $p['nama_produk'],
                    'harga_satuan' => $p['harga_jual'],
                    'jumlah' => $qty,
                    'subtotal' => $itemSubtotal,
                    'stok_sekarang' => $p['stok_tersedia']
                ];
            }

            if (empty($orderLines)) {
                throw new Exception("Tidak ada item produk valid yang dipilih.");
            }

            $totalAkhir = max(0, $subtotal - $diskon + $ongkir);
            if ($jumlahBayar < $totalAkhir && $metodePembayaran === 'tunai') {
                throw new Exception("Uang yang dibayarkan kurang dari total transaksi.");
            }

            $kembalian = max(0, $jumlahBayar - $totalAkhir);
            $kodeTransaksi = 'TRX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // Insert into `transaksi`
            $stmtTrx = $db->prepare("
                INSERT INTO transaksi (kode_transaksi, pesanan_id, pelanggan_id, kasir_user_id, tanggal_transaksi, total_item, subtotal, diskon, biaya_pengiriman, total_akhir, metode_pembayaran, jumlah_bayar, kembalian, status_pembayaran, catatan)
                VALUES (?, NULL, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'lunas', ?)
            ");
            $stmtTrx->execute([
                $kodeTransaksi,
                $pelangganId,
                $_SESSION['user']['id'],
                $totalItemCount,
                $subtotal,
                $diskon,
                $ongkir,
                $totalAkhir,
                $metodePembayaran,
                $jumlahBayar ?: $totalAkhir,
                $kembalian,
                $catatan ?: 'Transaksi langsung di kasir depot'
            ]);
            $transaksiId = $db->lastInsertId();

            // Insert `detail_transaksi` & deduct stock
            $stmtDetail = $db->prepare("
                INSERT INTO detail_transaksi (transaksi_id, produk_id, nama_produk, harga_satuan, jumlah, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtDeductStock = $db->prepare("UPDATE stok SET stok_tersedia = stok_tersedia - ? WHERE produk_id = ?");
            $stmtLogStock = $db->prepare("
                INSERT INTO riwayat_stok (produk_id, user_id, jenis, jumlah, stok_sebelum, stok_sesudah, keterangan, created_at)
                VALUES (?, ?, 'keluar', ?, ?, ?, ?, NOW())
            ");

            foreach ($orderLines as $ol) {
                $stmtDetail->execute([
                    $transaksiId,
                    $ol['produk_id'],
                    $ol['nama_produk'],
                    $ol['harga_satuan'],
                    $ol['jumlah'],
                    $ol['subtotal']
                ]);

                $stmtDeductStock->execute([$ol['jumlah'], $ol['produk_id']]);

                $stokSesudah = $ol['stok_sekarang'] - $ol['jumlah'];
                $stmtLogStock->execute([
                    $ol['produk_id'],
                    $_SESSION['user']['id'],
                    $ol['jumlah'],
                    $ol['stok_sekarang'],
                    $stokSesudah,
                    "Transaksi kasir $kodeTransaksi"
                ]);
            }

            // Insert into `pembayaran`
            $kodeBayar = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $stmtPay = $db->prepare("
                INSERT INTO pembayaran (transaksi_id, kode_pembayaran, metode, jumlah, status, tanggal_bayar, diverifikasi_oleh)
                VALUES (?, ?, ?, ?, 'lunas', NOW(), ?)
            ");
            $stmtPay->execute([$transaksiId, $kodeBayar, $metodePembayaran, $totalAkhir, $_SESSION['user']['id']]);

            $db->commit();
            set_flash('success', 'Transaksi berhasil disimpan!');
            redirect('views/cetak_invoice.php?transaksi_id=' . $transaksiId);

        } catch (Exception $e) {
            $db->rollBack();
            set_flash('error', 'Gagal memproses transaksi kasir: ' . $e->getMessage());
            redirect('karyawan/kasir.php');
        }
    }
}

// Fetch products with stock > 0
$products = $db->query("
    SELECT p.*, s.stok_tersedia, k.nama_kategori 
    FROM produk p 
    JOIN kategori k ON p.kategori_id = k.id 
    JOIN stok s ON p.id = s.produk_id 
    WHERE p.status = 'tersedia' AND s.stok_tersedia > 0 
    ORDER BY p.is_featured DESC, p.nama_produk ASC
")->fetchAll();

// Fetch customers for selector
$customers = $db->query("SELECT id, kode_pelanggan, nama, no_telepon FROM pelanggan ORDER BY nama ASC")->fetchAll();
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
    <?php require_once __DIR__ . '/../includes/sidebar_karyawan.php'; ?>

    <div class="app-main">
        <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

        <div class="app-content">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="bi bi-calculator me-2 text-primary"></i>Kasir POS - Input Transaksi Langsung</h3>
                    <p class="text-muted small mb-0">Klik produk untuk menambahkan ke daftar transaksi, hitung total, dan cetak struk pembayaran.</p>
                </div>
            </div>

            <form method="POST" action="" id="posForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_transaction">

                <div class="row g-4">
                    <!-- Products Selection Column -->
                    <div class="col-lg-7">
                        <div class="glass-card p-4 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Katalog Produk Tersedia</h5>
                                <input type="text" id="posSearch" class="form-control form-control-sm w-50" placeholder="Cari produk cepat..." onkeyup="filterPosProducts()">
                            </div>

                            <div class="row g-3" id="posProductGrid" style="max-height: 560px; overflow-y: auto;">
                                <?php foreach ($products as $p): ?>
                                    <div class="col-sm-6 pos-product-item" data-name="<?= strtolower(htmlspecialchars($p['nama_produk'])) ?>">
                                        <div class="card h-100 p-3 rounded-4 border shadow-sm cursor-pointer hover-elevate" onclick="addPosItem(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nama_produk'])) ?>', <?= $p['harga_jual'] ?>, <?= $p['stok_tersedia'] ?>)">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-3 bg-light p-2 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border: 1px solid var(--card-border);">
                                                    <img src="<?= product_image($p['gambar']) ?>" alt="" style="max-height: 38px;">
                                                </div>
                                                <div class="overflow-hidden flex-grow-1">
                                                    <div class="fw-bold text-truncate small"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                                    <div class="text-primary fw-bold small"><?= format_rupiah($p['harga_jual']) ?></div>
                                                    <small class="badge bg-success-subtle text-success" style="font-size: 0.7rem;">Stok: <?= $p['stok_tersedia'] ?></small>
                                                </div>
                                                <button type="button" class="btn btn-primary btn-sm rounded-circle" style="width: 32px; height: 32px;">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Cart / Checkout Calculation Column -->
                    <div class="col-lg-5">
                        <div class="glass-card p-4 position-sticky" style="top: 90px;">
                            <h5 class="fw-bold mb-3 d-flex align-items-center justify-content-between">
                                <span>Tagihan Kasir</span>
                                <button type="button" class="btn btn-outline-danger btn-xs rounded-pill px-2" onclick="resetPosCart()">Reset</button>
                            </h5>

                            <!-- Customer Picker -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Pilih Pelanggan (Opsional)</label>
                                <select name="pelanggan_id" class="form-select form-select-sm">
                                    <option value="">-- Pelanggan Walk-In / Umum --</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama']) ?> (<?= htmlspecialchars($c['no_telepon']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Selected Items List -->
                            <div class="border rounded-3 p-2 mb-3 bg-white" style="min-height: 180px; max-height: 240px; overflow-y: auto;">
                                <table class="table table-sm table-borderless mb-0 small">
                                    <tbody id="posCartBody">
                                        <tr id="emptyPosRow">
                                            <td colspan="4" class="text-center text-muted py-4">Belum ada produk yang dipilih. Klik produk di samping.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Calculations -->
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Subtotal</span>
                                <span class="fw-bold" id="displaySubtotal">Rp 0</span>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-0">Diskon (Rp)</label>
                                    <input type="number" name="diskon" id="posDiskon" class="form-control form-control-sm" value="0" min="0" oninput="calculatePos()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-0">Ongkir (Rp)</label>
                                    <input type="number" name="biaya_pengiriman" id="posOngkir" class="form-control form-control-sm" value="0" min="0" oninput="calculatePos()">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-3">
                                <span class="fw-bold fs-6">Total Tagihan</span>
                                <span class="fw-bold fs-4 text-primary" id="displayTotal">Rp 0</span>
                            </div>

                            <!-- Payment Method & Pay Amount -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold mb-1">Metode Bayar</label>
                                    <select name="metode_pembayaran" id="posMetode" class="form-select form-select-sm">
                                        <option value="tunai">Tunai / Cash</option>
                                        <option value="qris">QRIS Depot</option>
                                        <option value="transfer">Transfer Bank</option>
                                        <option value="cod">COD</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold mb-1">Uang Diterima (Rp)</label>
                                    <input type="number" name="jumlah_bayar" id="posBayar" class="form-control form-control-sm" min="0" placeholder="0" oninput="calculatePos()">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded-3 small">
                                <span class="fw-bold text-muted">Uang Kembalian:</span>
                                <span class="fw-bold fs-6 text-success" id="displayKembalian">Rp 0</span>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-6 shadow">
                                <i class="bi bi-printer me-2"></i>Simpan & Cetak Struk
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= asset('js/main.js') ?>"></script>
<script>
let posCart = {};

function addPosItem(id, name, price, maxStock) {
    if (!posCart[id]) {
        posCart[id] = { id: id, name: name, price: price, qty: 1, maxStock: maxStock };
    } else {
        if (posCart[id].qty < maxStock) {
            posCart[id].qty += 1;
        } else {
            alert('Stok maksimal telah tercapai (' + maxStock + ' unit)');
        }
    }
    renderPosCart();
}

function updatePosQty(id, qty) {
    if (qty <= 0) {
        delete posCart[id];
    } else {
        if (qty <= posCart[id].maxStock) {
            posCart[id].qty = parseInt(qty);
        } else {
            posCart[id].qty = posCart[id].maxStock;
        }
    }
    renderPosCart();
}

function renderPosCart() {
    const tbody = document.getElementById('posCartBody');
    tbody.innerHTML = '';
    const keys = Object.keys(posCart);

    if (keys.length === 0) {
        tbody.innerHTML = '<tr id="emptyPosRow"><td colspan="4" class="text-center text-muted py-4">Belum ada produk yang dipilih. Klik produk di samping.</td></tr>';
    } else {
        keys.forEach(k => {
            const item = posCart[k];
            const sub = item.price * item.qty;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="fw-bold text-truncate" style="max-width: 140px;">${item.name}</div>
                    <small class="text-muted">Rp ${item.price.toLocaleString('id-ID')}</small>
                    <input type="hidden" name="items[${item.id}]" value="${item.qty}">
                </td>
                <td style="width: 80px;">
                    <input type="number" class="form-control form-control-sm px-1 text-center" min="1" max="${item.maxStock}" value="${item.qty}" onchange="updatePosQty(${item.id}, this.value)">
                </td>
                <td class="text-end fw-bold" style="width: 90px;">
                    Rp ${sub.toLocaleString('id-ID')}
                </td>
                <td class="text-end" style="width: 30px;">
                    <button type="button" class="btn btn-xs btn-link text-danger p-0" onclick="updatePosQty(${item.id}, 0)">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }
    calculatePos();
}

function calculatePos() {
    let subtotal = 0;
    Object.values(posCart).forEach(i => {
        subtotal += (i.price * i.qty);
    });

    const diskon = parseFloat(document.getElementById('posDiskon').value) || 0;
    const ongkir = parseFloat(document.getElementById('posOngkir').value) || 0;
    const bayar = parseFloat(document.getElementById('posBayar').value) || 0;

    const total = Math.max(0, subtotal - diskon + ongkir);
    const kembalian = Math.max(0, bayar - total);

    document.getElementById('displaySubtotal').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
    document.getElementById('displayTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('displayKembalian').innerText = 'Rp ' + kembalian.toLocaleString('id-ID');
}

function resetPosCart() {
    posCart = {};
    renderPosCart();
}

function filterPosProducts() {
    const q = document.getElementById('posSearch').value.toLowerCase();
    const items = document.querySelectorAll('.pos-product-item');
    items.forEach(el => {
        const name = el.getAttribute('data-name');
        if (name.includes(q)) {
            el.style.display = 'block';
        } else {
            el.style.display = 'none';
        }
    });
}
</script>
</body>
</html>

