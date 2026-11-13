<?php
/**
 * Model: Stock & Delivery & Customer
 */
require_once __DIR__ . '/../config/database.php';

class Stock {
    public static function getLowStockCount() {
        $db = Database::getConnection();
        return (int)$db->query("SELECT COUNT(*) FROM stok s WHERE s.stok_tersedia <= s.stok_minimum")->fetchColumn();
    }
}

class Customer {
    public static function all() {
        $db = Database::getConnection();
        return $db->query("SELECT * FROM pelanggan ORDER BY nama ASC")->fetchAll();
    }

    public static function findById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM pelanggan WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }
}

class Delivery {
    public static function all() {
        $db = Database::getConnection();
        return $db->query("
            SELECT pg.*, p.kode_pesanan, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan, u.nama_lengkap as nama_kurir 
            FROM pengiriman pg 
            JOIN pesanan p ON pg.pesanan_id = p.id 
            JOIN pelanggan plg ON p.pelanggan_id = plg.id 
            LEFT JOIN users u ON pg.kurir_user_id = u.id 
            ORDER BY pg.id DESC
        ")->fetchAll();
    }
}

