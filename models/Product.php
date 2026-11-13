<?php
/**
 * Model: Product
 */
require_once __DIR__ . '/../config/database.php';

class Product {
    public static function all($availableOnly = false) {
        $db = Database::getConnection();
        $sql = "
            SELECT p.*, s.stok_tersedia, s.stok_minimum, k.nama_kategori 
            FROM produk p 
            JOIN kategori k ON p.kategori_id = k.id 
            LEFT JOIN stok s ON p.id = s.produk_id 
        ";
        if ($availableOnly) {
            $sql .= " WHERE p.status = 'tersedia' ";
        }
        $sql .= " ORDER BY p.is_featured DESC, p.nama_produk ASC";
        return $db->query($sql)->fetchAll();
    }

    public static function findById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT p.*, s.stok_tersedia, s.stok_minimum, k.nama_kategori 
            FROM produk p 
            JOIN kategori k ON p.kategori_id = k.id 
            LEFT JOIN stok s ON p.id = s.produk_id 
            WHERE p.id = ?
        ");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function getFeatured($limit = 6) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT p.*, s.stok_tersedia, k.nama_kategori 
            FROM produk p 
            JOIN kategori k ON p.kategori_id = k.id 
            LEFT JOIN stok s ON p.id = s.produk_id 
            WHERE p.is_featured = 1 AND p.status = 'tersedia' 
            ORDER BY p.id ASC 
            LIMIT ?
        ");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

