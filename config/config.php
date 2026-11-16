<?php
/**
 * Configuration & Core Helpers
 * AquaDepot - Sistem Informasi Manajemen Depot Air Mineral
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone Indonesia Barat
date_default_timezone_set('Asia/Jakarta');

// Autodetect Base URL
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Find project root folder relative to doc root
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $subDir = str_replace($docRoot, '', $appDir);
    $subDir = trim($subDir, '/');
    
    $base = $protocol . $host . ($subDir ? '/' . $subDir : '');
    define('BASE_URL', rtrim($base, '/'));
}

require_once __DIR__ . '/database.php';

/**
 * Generate full URL
 */
function base_url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

function url($path = '') {
    return base_url($path);
}

function asset($path = '') {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Get Product Image URL with intelligent fallback
 */
function product_image($imageName) {
    if (!empty($imageName)) {
        // 1. Check in uploads/products/
        $uploadPath = __DIR__ . '/../assets/uploads/products/' . $imageName;
        if (file_exists($uploadPath)) {
            return asset('uploads/products/' . $imageName);
        }

        // 2. Check in assets/images/ (e.g. svg variants)
        $svgName = pathinfo($imageName, PATHINFO_FILENAME) . '.svg';
        $imagePath = __DIR__ . '/../assets/images/' . $svgName;
        if (file_exists($imagePath)) {
            return asset('images/' . $svgName);
        }

        $directPath = __DIR__ . '/../assets/images/' . $imageName;
        if (file_exists($directPath)) {
            return asset('images/' . $imageName);
        }
    }
    return asset('images/default_product.svg');
}

/**
 * Redirect to specific path
 */
function redirect($path) {
    if (strpos($path, 'http') === 0) {
        header("Location: " . $path);
    } else {
        header("Location: " . base_url($path));
    }
    exit;
}

/**
 * Currency formatter (IDR)
 */
function format_rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Indonesian Date Formatter
 */
function tanggal_indo($datetime, $with_time = false) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($datetime);
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    
    $nama_hari = $hari[date('w', $timestamp)];
    $tgl = date('j', $timestamp);
    $bln = $bulan[(int)date('n', $timestamp)];
    $thn = date('Y', $timestamp);
    
    $result = "$nama_hari, $tgl $bln $thn";
    if ($with_time) {
        $result .= ' ' . date('H:i', $timestamp) . ' WIB';
    }
    return $result;
}

/**
 * Input sanitization
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF Protection
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('
            <div style="font-family: Arial; max-width: 500px; margin: 40px auto; padding: 20px; border: 1px solid #f5c6cb; background: #f8d7da; color: #721c24; border-radius: 8px;">
                <h3>403 Forbidden - Validasi Keamanan (CSRF) Gagal</h3>
                <p>Sesi Anda telah kedaluwarsa atau token keamanan tidak valid. Silakan muat ulang halaman dan coba kembali.</p>
                <a href="javascript:history.back()" style="color: #721c24; font-weight: bold;">Kembali</a>
            </div>
            ');
        }
    }
}

/**
 * Flash Messages
 */
function set_flash($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Authentication Helpers
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    if (is_string($roles)) {
        $roles = [$roles];
    }
    return in_array($_SESSION['user']['role_name'], $roles);
}

function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        redirect('auth/login.php');
    }
}

function require_role($allowed_roles) {
    require_login();
    if (!has_role($allowed_roles)) {
        set_flash('error', 'Akses ditolak. Anda tidak memiliki izin untuk membuka halaman tersebut.');
        // Redirect based on current role
        $role = $_SESSION['user']['role_name'];
        if ($role === 'admin') {
            redirect('admin/index.php');
        } elseif ($role === 'karyawan') {
            redirect('karyawan/index.php');
        } else {
            redirect('pelanggan/index.php');
        }
    }
}

/**
 * Badge helper for statuses
 */
function status_badge($status) {
    $badges = [
        // Pesanan / Pengiriman
        'menunggu_konfirmasi' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Menunggu Konfirmasi</span>',
        'diproses'            => '<span class="badge bg-info text-dark"><i class="bi bi-gear me-1"></i>Pesanan Diproses</span>',
        'siap_dikirim'        => '<span class="badge bg-primary"><i class="bi bi-box-seam me-1"></i>Siap Dikirim</span>',
        'sedang_dikirim'      => '<span class="badge bg-secondary"><i class="bi bi-truck me-1"></i>Sedang Dikirim</span>',
        'selesai'             => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Pesanan Selesai</span>',
        'dibatalkan'          => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Pesanan Dibatalkan</span>',
        
        // Pembayaran
        'lunas'               => '<span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Lunas</span>',
        'pending'             => '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>',
        'menunggu_verifikasi' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Verifikasi</span>',
        'ditolak'             => '<span class="badge bg-danger"><i class="bi bi-x-octagon me-1"></i>Ditolak</span>',
        'batal'               => '<span class="badge bg-danger"><i class="bi bi-x me-1"></i>Batal</span>',

        // Produk
        'tersedia'            => '<span class="badge bg-success">Tersedia</span>',
        'habis'               => '<span class="badge bg-danger">Stok Habis</span>',
        'nonaktif'            => '<span class="badge bg-secondary">Nonaktif</span>',

        // User
        'aktif'               => '<span class="badge bg-success">Aktif</span>',
        'nonaktif'            => '<span class="badge bg-danger">Nonaktif</span>',
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/**
 * Get Settings from Database
 */
function get_pengaturan() {
    static $settings = null;
    if ($settings === null) {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM pengaturan LIMIT 1");
            $settings = $stmt->fetch();
            if (!$settings) {
                $settings = [
                    'nama_depot' => 'AquaDepot',
                    'slogan' => 'Air Bersih & Higienis',
                    'alamat' => 'Jl. Ki Penjawi 12-10, Sidorejo Lor, Kec. Sidorejo, Kota Salatiga, Jawa Tengah 50714',
                    'no_telepon' => '081234567890',
                    'no_whatsapp' => '6281234567890',
                    'email' => 'info@aquadepot.id',
                    'jam_operasional' => '07.00 - 20.00 WIB',
                    'ongkos_kirim_default' => 3000,
                    'rekening_bank' => 'BCA: 123-456-7890 a/n AquaDepot',
                    'maps_embed' => '<iframe src="https://maps.google.com/maps?q=Jl.+Ki+Penjawi+12-10,+Sidorejo+Lor,+Kec.+Sidorejo,+Kota+Salatiga,+Jawa+Tengah+50714&t=&z=16&ie=UTF8&iwloc=&output=embed" width="100%" height="380" style="border:0;" allowfullscreen="" loading="lazy"></iframe>'
                ];
            }
        } catch (Exception $e) {
            $settings = [
                'nama_depot' => 'AquaDepot',
                'slogan' => 'Air Bersih & Higienis',
                'alamat' => 'Jl. Ki Penjawi 12-10, Sidorejo Lor, Kec. Sidorejo, Kota Salatiga, Jawa Tengah 50714',
                'no_telepon' => '081234567890',
                'no_whatsapp' => '6281234567890',
                'email' => 'info@aquadepot.id',
                'jam_operasional' => '07.00 - 20.00 WIB',
                'ongkos_kirim_default' => 3000,
                'rekening_bank' => 'BCA: 123-456-7890 a/n AquaDepot',
                'maps_embed' => '<iframe src="https://maps.google.com/maps?q=Jl.+Ki+Penjawi+12-10,+Sidorejo+Lor,+Kec.+Sidorejo,+Kota+Salatiga,+Jawa+Tengah+50714&t=&z=16&ie=UTF8&iwloc=&output=embed" width="100%" height="380" style="border:0;" allowfullscreen="" loading="lazy"></iframe>'
            ];
        }
    }
    return $settings;
}

/**
 * URL Google Maps Langsung Sesuai Alamat Depot
 * @param string|null $alamat Alamat kustom atau default dari pengaturan
 * @param string $type 'search' (lihat pin lokasi) atau 'dir' (navigasi rute petunjuk arah)
 * @return string Link Google Maps resmi
 */
function google_maps_url($alamat = null, $type = 'search') {
    if (!$alamat) {
        $p = get_pengaturan();
        $alamat = $p['alamat'] ?? 'Jl. Ki Penjawi 12-10, Sidorejo Lor, Kec. Sidorejo, Kota Salatiga, Jawa Tengah 50714';
    }
    $query = urlencode(trim($alamat));
    if ($type === 'dir') {
        return "https://www.google.com/maps/dir/?api=1&destination=" . $query;
    }
    return "https://www.google.com/maps/search/?api=1&query=" . $query;
}

