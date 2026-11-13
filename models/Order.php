<?php
/**
 * Model: Order
 */
require_once __DIR__ . '/../config/database.php';

class Order {
    public static function findById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT p.*, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan 
            FROM pesanan p 
            JOIN pelanggan plg ON p.pelanggan_id = plg.id 
            WHERE p.id = ?
        ");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function getItems($orderId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT dp.*, prd.nama_produk, prd.satuan 
            FROM detail_pesanan dp 
            JOIN produk prd ON dp.produk_id = prd.id 
            WHERE dp.pesanan_id = ?
        ");
        $stmt->execute([(int)$orderId]);
        return $stmt->fetchAll();
    }

    public static function countByStatus($status) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM pesanan WHERE status_pesanan = ?");
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }
}

