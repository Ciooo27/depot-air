<?php
/**
 * Model: User
 */
require_once __DIR__ . '/../config/database.php';

class User {
    public static function findById($id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT u.*, r.nama_role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }

    public static function findByUsernameOrEmail($username) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT u.*, r.nama_role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? OR u.email = ?");
        $stmt->execute([$username, $username]);
        return $stmt->fetch();
    }

    public static function allEmployees() {
        $db = Database::getConnection();
        return $db->query("SELECT k.*, u.username, u.email, u.nama_lengkap, u.no_telepon, u.status_aktif FROM karyawan k JOIN users u ON k.user_id = u.id ORDER BY k.id ASC")->fetchAll();
    }
}

