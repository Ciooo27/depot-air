<?php
/**
 * Cart Controller
 * Manages shopping cart stored in session
 */

require_once __DIR__ . '/../config/config.php';

class CartController {
    
    public static function getCart() {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $cart = [];
        $total = 0;
        $totalItems = 0;

        if (!empty($_SESSION['cart'])) {
            $db = Database::getConnection();
            $ids = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $stmt = $db->prepare("
                SELECT p.*, s.stok_tersedia, k.nama_kategori 
                FROM produk p 
                LEFT JOIN stok s ON p.id = s.produk_id 
                LEFT JOIN kategori k ON p.kategori_id = k.id 
                WHERE p.id IN ($placeholders)
            ");
            $stmt->execute($ids);
            $products = $stmt->fetchAll();

            $prodMap = [];
            foreach ($products as $p) {
                $prodMap[$p['id']] = $p;
            }

            foreach ($_SESSION['cart'] as $id => $qty) {
                if (isset($prodMap[$id])) {
                    $item = $prodMap[$id];
                    $qty = max(1, min((int)$qty, (int)$item['stok_tersedia']));
                    $subtotal = $item['harga_jual'] * $qty;
                    
                    $cart[] = [
                        'id' => $item['id'],
                        'kode_produk' => $item['kode_produk'],
                        'nama_produk' => $item['nama_produk'],
                        'harga' => $item['harga_jual'],
                        'satuan' => $item['satuan'],
                        'gambar' => $item['gambar'],
                        'stok_tersedia' => $item['stok_tersedia'],
                        'jumlah' => $qty,
                        'subtotal' => $subtotal
                    ];

                    $total += $subtotal;
                    $totalItems += $qty;
                }
            }
        }

        return [
            'items' => $cart,
            'total' => $total,
            'total_items' => $totalItems
        ];
    }

    public static function add($productId, $qty = 1) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $productId = (int)$productId;
        $qty = max(1, (int)$qty);

        // Check stock availability
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT p.id, p.nama_produk, s.stok_tersedia FROM produk p JOIN stok s ON p.id = s.produk_id WHERE p.id = ?");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();

        if (!$prod) {
            return ['status' => false, 'message' => 'Produk tidak ditemukan.'];
        }

        $currentQty = $_SESSION['cart'][$productId] ?? 0;
        $newQty = $currentQty + $qty;

        if ($newQty > $prod['stok_tersedia']) {
            return [
                'status' => false, 
                'message' => 'Stok tidak mencukupi. Tersedia hanya ' . $prod['stok_tersedia'] . ' unit.'
            ];
        }

        $_SESSION['cart'][$productId] = $newQty;
        return [
            'status' => true, 
            'message' => 'Berhasil menambahkan ' . htmlspecialchars($prod['nama_produk']) . ' ke keranjang.',
            'total_items' => self::getCount()
        ];
    }

    public static function update($productId, $qty) {
        $productId = (int)$productId;
        $qty = (int)$qty;

        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
            return ['status' => true, 'message' => 'Produk dihapus dari keranjang.'];
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT s.stok_tersedia FROM stok s WHERE s.produk_id = ?");
        $stmt->execute([$productId]);
        $stock = $stmt->fetchColumn();

        if ($qty > $stock) {
            $_SESSION['cart'][$productId] = (int)$stock;
            return ['status' => false, 'message' => 'Jumlah disesuaikan dengan sisa stok (' . $stock . ' unit).'];
        }

        $_SESSION['cart'][$productId] = $qty;
        return ['status' => true, 'message' => 'Jumlah produk berhasil diperbarui.'];
    }

    public static function remove($productId) {
        $productId = (int)$productId;
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }
        return ['status' => true, 'message' => 'Produk berhasil dihapus dari keranjang.'];
    }

    public static function clear() {
        $_SESSION['cart'] = [];
        return ['status' => true, 'message' => 'Keranjang dikosongkan.'];
    }

    public static function getCount() {
        if (!isset($_SESSION['cart'])) return 0;
        return array_sum($_SESSION['cart']);
    }
}

// Handle Direct AJAX / Form Requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'add') {
        $productId = $_POST['product_id'] ?? $_GET['product_id'] ?? 0;
        $qty = $_POST['qty'] ?? $_GET['qty'] ?? 1;
        echo json_encode(CartController::add($productId, $qty));
        exit;
    }

    if ($action === 'update') {
        $productId = $_POST['product_id'] ?? 0;
        $qty = $_POST['qty'] ?? 1;
        echo json_encode(CartController::update($productId, $qty));
        exit;
    }

    if ($action === 'remove') {
        $productId = $_POST['product_id'] ?? $_GET['product_id'] ?? 0;
        echo json_encode(CartController::remove($productId));
        exit;
    }

    if ($action === 'count') {
        echo json_encode(['count' => CartController::getCount()]);
        exit;
    }
}

