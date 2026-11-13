-- ==============================================================
-- DATABASE: aquadepot_db
-- SISTEM INFORMASI MANAJEMEN DEPOT AIR MINERAL (AquaDepot)
-- Alamat: Jl. Ki Penjawi 12-10, Sidorejo Lor, Kec. Sidorejo, Kota Salatiga, Jawa Tengah 50714
-- Produk: 2 Produk Utama (Air Galon RO & Air Galon BIO)
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `aquadepot_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aquadepot_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `riwayat_stok`;
DROP TABLE IF EXISTS `pembayaran`;
DROP TABLE IF EXISTS `pengiriman`;
DROP TABLE IF EXISTS `detail_pesanan`;
DROP TABLE IF EXISTS `pesanan`;
DROP TABLE IF EXISTS `detail_transaksi`;
DROP TABLE IF EXISTS `transaksi`;
DROP TABLE IF EXISTS `stok`;
DROP TABLE IF EXISTS `produk`;
DROP TABLE IF EXISTS `kategori`;
DROP TABLE IF EXISTS `karyawan`;
DROP TABLE IF EXISTS `pelanggan`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `pengaturan`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. Tabel: roles
-- --------------------------------------------------------
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_role` VARCHAR(50) NOT NULL UNIQUE,
  `deskripsi` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `nama_role`, `deskripsi`) VALUES
(1, 'admin', 'Administrator dengan hak akses penuh ke seluruh modul sistem'),
(2, 'karyawan', 'Karyawan operasional depot: kasir, proses pesanan, pengiriman, dan stok'),
(3, 'pelanggan', 'Pelanggan untuk pemesanan air online, tracking, dan riwayat belanja');

-- --------------------------------------------------------
-- 2. Tabel: users
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `no_telepon` VARCHAR(20) NULL,
  `status_aktif` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `foto_profil` VARCHAR(255) DEFAULT 'default.png',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_users_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password defaults:
-- admin: admin123 ($2y$12$wd9c9GvHbCDCW5RsAFHP9O/RVzoh7vIIylTDhwbdaBayaGP5atxhC)
-- karyawan: karyawan123 ($2y$12$5TakLO9Unqe7.U7/NdUFz./NZxeIyOkVhWmvn66H.VDDemwjlV0xq)
-- pelanggan: pelanggan123 ($2y$12$ZA/c97AcZ8lCxqXEwt8elOs.zF847qw0EI2.jz6ry.jqZLl4WaF0y)
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `nama_lengkap`, `no_telepon`, `status_aktif`, `foto_profil`) VALUES
(1, 1, 'admin', 'admin@aquadepot.id', '$2y$12$wd9c9GvHbCDCW5RsAFHP9O/RVzoh7vIIylTDhwbdaBayaGP5atxhC', 'Budi Santoso (Owner)', '081234567890', 'aktif', 'default.png'),
(2, 2, 'karyawan', 'karyawan@aquadepot.id', '$2y$12$5TakLO9Unqe7.U7/NdUFz./NZxeIyOkVhWmvn66H.VDDemwjlV0xq', 'Rian Hidayat (Operator)', '082198765432', 'aktif', 'default.png'),
(3, 3, 'pelanggan', 'pelanggan@gmail.com', '$2y$12$ZA/c97AcZ8lCxqXEwt8elOs.zF847qw0EI2.jz6ry.jqZLl4WaF0y', 'Siti Rahmawati', '085712345678', 'aktif', 'default.png'),
(4, 2, 'kurir_agus', 'agus@aquadepot.id', '$2y$12$5TakLO9Unqe7.U7/NdUFz./NZxeIyOkVhWmvn66H.VDDemwjlV0xq', 'Agus Prasetyo (Kurir)', '082233445566', 'aktif', 'default.png'),
(5, 3, 'ahmad_fauzi', 'ahmadfauzi@gmail.com', '$2y$12$ZA/c97AcZ8lCxqXEwt8elOs.zF847qw0EI2.jz6ry.jqZLl4WaF0y', 'Ahmad Fauzi', '081399887766', 'aktif', 'default.png');

-- --------------------------------------------------------
-- 3. Tabel: pelanggan
-- --------------------------------------------------------
CREATE TABLE `pelanggan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `kode_pelanggan` VARCHAR(20) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `no_telepon` VARCHAR(20) NOT NULL,
  `alamat` TEXT NOT NULL,
  `tipe_pelanggan` ENUM('umum', 'langganan', 'bisnis') NOT NULL DEFAULT 'umum',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pelanggan_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pelanggan` (`id`, `user_id`, `kode_pelanggan`, `nama`, `no_telepon`, `alamat`, `tipe_pelanggan`) VALUES
(1, 3, 'PLG-001', 'Siti Rahmawati', '085712345678', 'Jl. Ki Penjawi No. 15, Sidorejo Lor, Salatiga', 'langganan'),
(2, 5, 'PLG-002', 'Ahmad Fauzi', '081399887766', 'Jl. Diponegoro No. 45, Salatiga', 'bisnis'),
(3, NULL, 'PLG-003', 'Pelanggan Umum (Walk-in)', '080000000000', 'Beli Langsung di Depot', 'umum'),
(4, NULL, 'PLG-004', 'Warung Bu Joko', '081987654321', 'Jl. Fatmawati No. 8, Sidorejo, Salatiga', 'bisnis');

-- --------------------------------------------------------
-- 4. Tabel: karyawan
-- --------------------------------------------------------
CREATE TABLE `karyawan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `nip` VARCHAR(30) NOT NULL UNIQUE,
  `jabatan` VARCHAR(50) NOT NULL DEFAULT 'Operator Depot',
  `alamat` TEXT NULL,
  `gaji_pokok` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tanggal_masuk` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_karyawan_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `karyawan` (`id`, `user_id`, `nip`, `jabatan`, `alamat`, `gaji_pokok`, `tanggal_masuk`) VALUES
(1, 2, 'KRY-2024-001', 'Operator & Kasir', 'Jl. Ki Penjawi No. 20, Salatiga', 2800000.00, '2024-01-15'),
(2, 4, 'KRY-2024-002', 'Kurir Pengiriman', 'Jl. Sukowati No. 12, Salatiga', 2500000.00, '2024-03-01');

-- --------------------------------------------------------
-- 5. Tabel: kategori (2 Kategori untuk 2 Produk Utama)
-- --------------------------------------------------------
CREATE TABLE `kategori` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `deskripsi` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `kategori` (`id`, `nama_kategori`, `slug`, `deskripsi`) VALUES
(1, 'Air Galon RO', 'air-galon-ro', 'Air minum murni hasil penyaringan teknologi membran Reverse Osmosis (RO) 0.0001 mikron'),
(2, 'Air Galon Bio-Energy', 'air-galon-bio-energy', 'Air minum isi ulang berenergi dengan kandungan mineral alami esensial untuk metabolisme');

-- --------------------------------------------------------
-- 6. Tabel: produk (2 Produk Saja Sesuai Permintaan)
-- --------------------------------------------------------
CREATE TABLE `produk` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kategori_id` INT NOT NULL,
  `kode_produk` VARCHAR(30) NOT NULL UNIQUE,
  `nama_produk` VARCHAR(150) NOT NULL,
  `deskripsi` TEXT NULL,
  `harga_modal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `harga_jual` DECIMAL(12,2) NOT NULL,
  `satuan` VARCHAR(20) NOT NULL DEFAULT 'Galon (19L)',
  `gambar` VARCHAR(255) DEFAULT 'default_product.png',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('tersedia', 'habis', 'nonaktif') NOT NULL DEFAULT 'tersedia',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `produk` (`id`, `kategori_id`, `kode_produk`, `nama_produk`, `deskripsi`, `harga_modal`, `harga_jual`, `satuan`, `gambar`, `is_featured`, `status`) VALUES
(1, 1, 'PRD-RO-01', 'Air Galon RO (Reverse Osmosis)', 'Air murni hasil penyaringan membran Reverse Osmosis 0.0001 mikron disterilkan dengan UV ganda & Ozon aktif. TDS rendah, higienis, dan rasa sangat segar.', 2500.00, 6000.00, 'Galon (19L)', 'product_ro.svg', 1, 'tersedia'),
(2, 2, 'PRD-BIO-02', 'Air Galon BIO (Bio-Energy Mineral)', 'Air minum isi ulang diperkaya mineral esensial alami dan resonansi bio-energy hexagonal yang menjaga kebugaran tubuh serta melancarkan metabolisme keluarga.', 3000.00, 8000.00, 'Galon (19L)', 'product_bio.svg', 1, 'tersedia');

-- --------------------------------------------------------
-- 7. Tabel: stok
-- --------------------------------------------------------
CREATE TABLE `stok` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `produk_id` INT NOT NULL UNIQUE,
  `stok_tersedia` INT NOT NULL DEFAULT 0,
  `stok_minimum` INT NOT NULL DEFAULT 10,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_stok_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `stok` (`id`, `produk_id`, `stok_tersedia`, `stok_minimum`) VALUES
(1, 1, 150, 15),
(2, 2, 100, 15);

-- --------------------------------------------------------
-- 10. Tabel: pesanan
-- --------------------------------------------------------
CREATE TABLE `pesanan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_pesanan` VARCHAR(50) NOT NULL UNIQUE,
  `pelanggan_id` INT NOT NULL,
  `tanggal_pesanan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metode_pengambilan` ENUM('antar', 'ambil_sendiri') NOT NULL DEFAULT 'antar',
  `alamat_pengiriman` TEXT NULL,
  `catatan` TEXT NULL,
  `metode_pembayaran` ENUM('tunai', 'transfer', 'cod', 'qris') NOT NULL DEFAULT 'cod',
  `total_harga` DECIMAL(12,2) NOT NULL,
  `biaya_pengiriman` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_bayar` DECIMAL(12,2) NOT NULL,
  `status_pesanan` ENUM('menunggu_konfirmasi', 'diproses', 'siap_dikirim', 'sedang_dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu_konfirmasi',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pesanan_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pesanan` (`id`, `kode_pesanan`, `pelanggan_id`, `tanggal_pesanan`, `metode_pengambilan`, `alamat_pengiriman`, `catatan`, `metode_pembayaran`, `total_harga`, `biaya_pengiriman`, `total_bayar`, `status_pesanan`, `created_at`) VALUES
(1, 'ORD-20261108-001', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'antar', 'Jl. Ki Penjawi No. 15, Sidorejo Lor, Salatiga', 'Tolong galon ditaruh di teras depan', 'cod', 18000.00, 3000.00, 21000.00, 'selesai', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'ORD-20261109-001', 2, DATE_SUB(NOW(), INTERVAL 4 HOUR), 'antar', 'Jl. Diponegoro No. 45, Salatiga', 'Kirim siang sebelum jam 12', 'transfer', 32000.00, 3000.00, 35000.00, 'sedang_dikirim', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(3, 'ORD-20261109-002', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), 'antar', 'Jl. Ki Penjawi No. 15, Sidorejo Lor, Salatiga', 'Isi ulang 2 galon RO', 'cod', 12000.00, 3000.00, 15000.00, 'diproses', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(4, 'ORD-20261109-003', 2, NOW(), 'ambil_sendiri', NULL, 'Ambil nanti sore pulang kerja', 'tunai', 24000.00, 0.00, 24000.00, 'menunggu_konfirmasi', NOW());

-- --------------------------------------------------------
-- 11. Tabel: detail_pesanan
-- --------------------------------------------------------
CREATE TABLE `detail_pesanan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pesanan_id` INT NOT NULL,
  `produk_id` INT NOT NULL,
  `harga_satuan` DECIMAL(12,2) NOT NULL,
  `jumlah` INT NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL,
  CONSTRAINT `fk_detail_pesanan_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_detail_pesanan_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `detail_pesanan` (`id`, `pesanan_id`, `produk_id`, `harga_satuan`, `jumlah`, `subtotal`) VALUES
(1, 1, 1, 6000.00, 3, 18000.00),
(2, 2, 2, 8000.00, 4, 32000.00),
(3, 3, 1, 6000.00, 2, 12000.00),
(4, 4, 2, 8000.00, 3, 24000.00);

-- --------------------------------------------------------
-- 8. Tabel: transaksi
-- --------------------------------------------------------
CREATE TABLE `transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_transaksi` VARCHAR(50) NOT NULL UNIQUE,
  `pesanan_id` INT NULL,
  `pelanggan_id` INT NULL,
  `kasir_user_id` INT NOT NULL,
  `tanggal_transaksi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_item` INT NOT NULL DEFAULT 0,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `diskon` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `biaya_pengiriman` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_akhir` DECIMAL(12,2) NOT NULL,
  `metode_pembayaran` ENUM('tunai', 'transfer', 'cod', 'qris') NOT NULL DEFAULT 'tunai',
  `jumlah_bayar` DECIMAL(12,2) NOT NULL,
  `kembalian` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status_pembayaran` ENUM('lunas', 'pending', 'batal') NOT NULL DEFAULT 'lunas',
  `catatan` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_transaksi_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_pelanggan` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_kasir` FOREIGN KEY (`kasir_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `transaksi` (`id`, `kode_transaksi`, `pesanan_id`, `pelanggan_id`, `kasir_user_id`, `tanggal_transaksi`, `total_item`, `subtotal`, `diskon`, `biaya_pengiriman`, `total_akhir`, `metode_pembayaran`, `jumlah_bayar`, `kembalian`, `status_pembayaran`, `catatan`, `created_at`) VALUES
(1, 'TRX-20261108-001', 1, 1, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 3, 18000.00, 0.00, 3000.00, 21000.00, 'cod', 25000.00, 4000.00, 'lunas', 'Pesanan online selesai diantar', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'TRX-20261108-002', NULL, 3, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 4, 24000.00, 0.00, 0.00, 24000.00, 'tunai', 50000.00, 26000.00, 'lunas', 'Beli isi ulang langsung di tempat', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'TRX-20261109-001', NULL, 4, 1, DATE_SUB(NOW(), INTERVAL 5 HOUR), 10, 60000.00, 5000.00, 0.00, 55000.00, 'tunai', 100000.00, 45000.00, 'lunas', 'Warung Bu Joko ambil 10 galon RO (Diskon mitra)', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(4, 'TRX-20261109-002', NULL, 3, 2, DATE_SUB(NOW(), INTERVAL 2 HOUR), 2, 16000.00, 0.00, 0.00, 16000.00, 'qris', 16000.00, 0.00, 'lunas', 'Beli 2 galon BIO bayar QRIS depot', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- --------------------------------------------------------
-- 9. Tabel: detail_transaksi
-- --------------------------------------------------------
CREATE TABLE `detail_transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaksi_id` INT NOT NULL,
  `produk_id` INT NOT NULL,
  `nama_produk` VARCHAR(150) NOT NULL,
  `harga_satuan` DECIMAL(12,2) NOT NULL,
  `jumlah` INT NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL,
  CONSTRAINT `fk_detail_transaksi_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_detail_transaksi_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `detail_transaksi` (`id`, `transaksi_id`, `produk_id`, `nama_produk`, `harga_satuan`, `jumlah`, `subtotal`) VALUES
(1, 1, 1, 'Air Galon RO (Reverse Osmosis)', 6000.00, 3, 18000.00),
(2, 2, 1, 'Air Galon RO (Reverse Osmosis)', 6000.00, 4, 24000.00),
(3, 3, 1, 'Air Galon RO (Reverse Osmosis)', 6000.00, 10, 60000.00),
(4, 4, 2, 'Air Galon BIO (Bio-Energy Mineral)', 8000.00, 2, 16000.00);

-- --------------------------------------------------------
-- 12. Tabel: pengiriman
-- --------------------------------------------------------
CREATE TABLE `pengiriman` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pesanan_id` INT NOT NULL UNIQUE,
  `kurir_user_id` INT NULL,
  `kode_pengiriman` VARCHAR(50) NOT NULL UNIQUE,
  `tanggal_kirim` DATETIME NULL,
  `tanggal_selesai` DATETIME NULL,
  `status_pengiriman` ENUM('menunggu_konfirmasi', 'diproses', 'siap_dikirim', 'sedang_dikirim', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu_konfirmasi',
  `alamat_tujuan` TEXT NOT NULL,
  `catatan_kurir` TEXT NULL,
  `bukti_pengiriman` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pengiriman_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pengiriman_kurir` FOREIGN KEY (`kurir_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pengiriman` (`id`, `pesanan_id`, `kurir_user_id`, `kode_pengiriman`, `tanggal_kirim`, `tanggal_selesai`, `status_pengiriman`, `alamat_tujuan`, `catatan_kurir`) VALUES
(1, 1, 4, 'DELIV-20261108-001', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 23 HOUR), 'selesai', 'Jl. Ki Penjawi No. 15, Sidorejo Lor, Salatiga', 'Diterima oleh Ibu Siti'),
(2, 2, 4, 'DELIV-20261109-001', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, 'sedang_dikirim', 'Jl. Diponegoro No. 45, Salatiga', 'Kurir menuju lokasi dengan motor roda tiga'),
(3, 3, NULL, 'DELIV-20261109-002', NULL, NULL, 'diproses', 'Jl. Ki Penjawi No. 15, Sidorejo Lor, Salatiga', 'Menunggu pengemasan galon');

-- --------------------------------------------------------
-- 13. Tabel: pembayaran
-- --------------------------------------------------------
CREATE TABLE `pembayaran` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaksi_id` INT NULL,
  `pesanan_id` INT NULL,
  `kode_pembayaran` VARCHAR(50) NOT NULL UNIQUE,
  `metode` ENUM('tunai', 'transfer', 'cod', 'qris') NOT NULL,
  `jumlah` DECIMAL(12,2) NOT NULL,
  `status` ENUM('lunas', 'menunggu_verifikasi', 'ditolak', 'dibatalkan') NOT NULL DEFAULT 'lunas',
  `bukti_transfer` VARCHAR(255) NULL,
  `tanggal_bayar` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diverifikasi_oleh` INT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pembayaran_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pembayaran_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pembayaran_verifikator` FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pembayaran` (`id`, `transaksi_id`, `pesanan_id`, `kode_pembayaran`, `metode`, `jumlah`, `status`, `bukti_transfer`, `tanggal_bayar`, `diverifikasi_oleh`) VALUES
(1, 1, 1, 'PAY-20261108-001', 'cod', 21000.00, 'lunas', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 2),
(2, NULL, 2, 'PAY-20261109-001', 'transfer', 35000.00, 'lunas', 'bukti_trf_001.jpg', DATE_SUB(NOW(), INTERVAL 3 HOUR), 1),
(3, 2, NULL, 'PAY-20261108-002', 'tunai', 24000.00, 'lunas', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 2),
(4, 3, NULL, 'PAY-20261109-002', 'tunai', 55000.00, 'lunas', NULL, DATE_SUB(NOW(), INTERVAL 5 HOUR), 1),
(5, 4, NULL, 'PAY-20261109-003', 'qris', 16000.00, 'lunas', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR), 2);

-- --------------------------------------------------------
-- 14. Tabel: riwayat_stok
-- --------------------------------------------------------
CREATE TABLE `riwayat_stok` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `produk_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `jenis` ENUM('masuk', 'keluar', 'penyesuaian') NOT NULL,
  `jumlah` INT NOT NULL,
  `stok_sebelum` INT NOT NULL,
  `stok_sesudah` INT NOT NULL,
  `keterangan` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_riwayat_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_riwayat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `riwayat_stok` (`id`, `produk_id`, `user_id`, `jenis`, `jumlah`, `stok_sebelum`, `stok_sesudah`, `keterangan`, `created_at`) VALUES
(1, 1, 1, 'masuk', 100, 67, 167, 'Refill pasokan tangki sumber pegunungan utama', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, 2, 'keluar', 3, 167, 164, 'Pesanan online ORD-20261108-001 selesai', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 1, 2, 'keluar', 4, 164, 160, 'Transaksi kasir langsung TRX-20261108-002', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 1, 1, 'keluar', 10, 160, 150, 'Penjualan grosir Warung Bu Joko TRX-20261109-001', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(5, 2, 1, 'masuk', 50, 52, 102, 'Restock pasokan bahan bio-energy mineral', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 2, 2, 'keluar', 2, 102, 100, 'Transaksi kasir langsung TRX-20261109-002', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- --------------------------------------------------------
-- 15. Tabel: pengaturan (Depot Profile & System Config)
-- Alamat: Jl. Ki Penjawi 12-10, Sidorejo Lor, Kec. Sidorejo, Kota Salatiga, Jawa Tengah 50714
-- --------------------------------------------------------
CREATE TABLE `pengaturan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_depot` VARCHAR(100) NOT NULL DEFAULT 'AquaDepot',
  `slogan` VARCHAR(255) DEFAULT 'Air Minum Bersih, Sehat, dan Higienis untuk Keluarga Anda',
  `alamat` TEXT NOT NULL,
  `no_telepon` VARCHAR(20) DEFAULT '081234567890',
  `no_whatsapp` VARCHAR(20) DEFAULT '6281234567890',
  `email` VARCHAR(100) DEFAULT 'info@aquadepot.id',
  `jam_operasional` VARCHAR(100) DEFAULT 'Senin - Minggu: 07.00 - 20.00 WIB',
  `ongkos_kirim_default` DECIMAL(12,2) DEFAULT 3000.00,
  `rekening_bank` TEXT DEFAULT 'Bank BCA: 123-456-7890 a.n. AquaDepot\nBank Mandiri: 987-654-3210 a.n. AquaDepot',
  `maps_embed` TEXT DEFAULT '<iframe src=\"https://maps.google.com/maps?q=Jl.+Ki+Penjawi+12-10,+Sidorejo+Lor,+Kec.+Sidorejo,+Kota+Salatiga,+Jawa+Tengah+50714&t=&z=15&ie=UTF8&iwloc=&output=embed\" width=\"100%\" height=\"350\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\"></iframe>',
  `logo` VARCHAR(255) DEFAULT 'logo.png',
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `pengaturan` (`id`, `nama_depot`, `slogan`, `alamat`, `no_telepon`, `no_whatsapp`, `email`, `jam_operasional`, `ongkos_kirim_default`, `rekening_bank`, `maps_embed`) VALUES
(1, 'AquaDepot', 'Air Minum Bersih, Sehat, dan Higienis untuk Keluarga Anda', 'Jl. Ki Penjawi 12-10, Sidorejo Lor, Kec. Sidorejo, Kota Salatiga, Jawa Tengah 50714', '081234567890', '6281234567890', 'kontak@aquadepot.id', 'Buka Setiap Hari: 07:00 - 20:00 WIB', 3000.00, 'BCA: 8830-123-456 (a/n AquaDepot)\nMandiri: 157-00-9876543-2 (a/n AquaDepot)\nBRI: 0206-01-002345-50-8 (a/n AquaDepot)', '<iframe src=\"https://maps.google.com/maps?q=Jl.+Ki+Penjawi+12-10,+Sidorejo+Lor,+Kec.+Sidorejo,+Kota+Salatiga,+Jawa+Tengah+50714&t=&z=15&ie=UTF8&iwloc=&output=embed\" width=\"100%\" height=\"350\" style=\"border:0;\" allowfullscreen=\"\" loading=\"lazy\"></iframe>');
