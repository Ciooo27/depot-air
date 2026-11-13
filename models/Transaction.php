<?php
/**
 * Model: Transaction
 */
require_once __DIR__ . '/../config/database.php';

class Transaction {
    public static function findById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT t.*, u.nama_lengkap as nama_kasir, plg.nama as nama_pelanggan, plg.no_telepon as wa_pelanggan 
            FROM transaksi t 
            LEFT JOIN users u ON t.kasir_user_id = u.id 
            LEFT JOIN pelanggan plg ON t.pelanggan_id = plg.id 
            WHERE t.id = ?
        ");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function getItems($transactionId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM detail_transaksi WHERE transaksi_id = ?");
        $stmt->execute([(int)$transactionId]);
        return $stmt->fetchAll();
    }

    public static function getRevenueToday() {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_akhir), 0) FROM transaksi WHERE DATE(tanggal_transaksi) = CURDATE() AND status_pembayaran = 'lunas'");
        $stmt->execute();
        return (float)$stmt->fetchColumn();
    }
}

