<?php

declare(strict_types=1);

$output = $argv[1] ?? throw new RuntimeException('Folder output diperlukan.');
$install = <<<'SQL'
-- PINDAI.IN: versi sesi akun + pembatas percobaan login.
-- Pilih database HOSTING yang benar. Backup dahulu dan jeda aplikasi.
-- Prasyarat: paket Data Referensi A-Z sudah terpasang (migration 000015).
-- Tidak mengganti password, role, username, status aktif, atau data inventaris.
SET NAMES utf8mb4;

SET @pindai_auth_column_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'auth_version'
);
SET @pindai_auth_ddl = IF(@pindai_auth_column_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `auth_version` INT UNSIGNED NOT NULL DEFAULT 1',
  'SELECT 1 AS auth_version_sudah_ada');
PREPARE pindai_auth_statement FROM @pindai_auth_ddl;
EXECUTE pindai_auth_statement;
DEALLOCATE PREPARE pindai_auth_statement;

CREATE TABLE IF NOT EXISTS `auth_login_limits` (
  `bucket_key` VARCHAR(64) NOT NULL,
  `scope` VARCHAR(10) NOT NULL,
  `failures` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_started` BIGINT NOT NULL,
  `blocked_until` BIGINT NOT NULL DEFAULT 0,
  `expires_at` BIGINT NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`bucket_key`),
  KEY `auth_login_limits_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- MySQL melakukan implicit commit untuk ALTER/CREATE. Metadata dicatat setelah DDL berhasil.
START TRANSACTION;
INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT '2026-11-02-000016',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'AddAuthSecurity'),
       'default', 'App', UNIX_TIMESTAMP(), next_migration.next_batch
FROM (SELECT COALESCE(MAX(`batch`), 0) + 1 AS next_batch FROM `migrations`) AS next_migration
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `version` = '2026-11-02-000016' AND `namespace` = 'App' AND `group` = 'default'
);
COMMIT;

-- Tidak ada UPDATE password/status/role dan tidak ada penghapusan isi tabel.
-- Jika diulang, nilai auth_version dan pembatas login yang sudah ada dipertahankan.
SQL;
$preflight = <<<'SQL'
-- PEMERIKSAAN SAJA. Pilih database hosting aplikasi terlebih dahulu.
SELECT DATABASE() AS database_terpilih;
SHOW COLUMNS FROM `users`;
SELECT `version`, `class`, `namespace`, `group` FROM `migrations`
WHERE `namespace` = 'App' AND `group` = 'default'
AND `version` IN ('2026-11-02-000015', '2026-11-02-000016');
SELECT 'users' AS tabel, COUNT(*) AS jumlah FROM `users`
UNION ALL SELECT 'borrowers', COUNT(*) FROM `borrowers`
UNION ALL SELECT 'items', COUNT(*) FROM `items`
UNION ALL SELECT 'loans', COUNT(*) FROM `loans`
UNION ALL SELECT 'damage_reports', COUNT(*) FROM `damage_reports`
UNION ALL SELECT 'handling_actions', COUNT(*) FROM `handling_actions`
UNION ALL SELECT 'item_status_history', COUNT(*) FROM `item_status_history`
UNION ALL SELECT 'activity_logs', COUNT(*) FROM `activity_logs`
UNION ALL SELECT 'settings', COUNT(*) FROM `settings`
UNION ALL SELECT 'item_references', COUNT(*) FROM `item_references`;
SQL;
$verify = <<<'SQL'
-- PEMERIKSAAN SAJA. Jalankan sebelum membuka kembali aplikasi.
SHOW COLUMNS FROM `users` LIKE 'auth_version';
SHOW CREATE TABLE `auth_login_limits`;
SELECT COUNT(*) AS versi_sesi_tidak_valid FROM `users` WHERE `auth_version` IS NULL OR `auth_version` < 1;
SELECT `auth_version`, COUNT(*) AS jumlah_akun FROM `users` GROUP BY `auth_version`;
SELECT COUNT(*) AS baris_pembatas_login FROM `auth_login_limits`;
SELECT `version`, `class`, `namespace`, `group` FROM `migrations`
WHERE `version` = '2026-11-02-000016' AND `namespace` = 'App' AND `group` = 'default';
SELECT 'users' AS tabel, COUNT(*) AS jumlah FROM `users`
UNION ALL SELECT 'borrowers', COUNT(*) FROM `borrowers`
UNION ALL SELECT 'items', COUNT(*) FROM `items`
UNION ALL SELECT 'loans', COUNT(*) FROM `loans`
UNION ALL SELECT 'damage_reports', COUNT(*) FROM `damage_reports`
UNION ALL SELECT 'handling_actions', COUNT(*) FROM `handling_actions`
UNION ALL SELECT 'item_status_history', COUNT(*) FROM `item_status_history`
UNION ALL SELECT 'activity_logs', COUNT(*) FROM `activity_logs`
UNION ALL SELECT 'settings', COUNT(*) FROM `settings`
UNION ALL SELECT 'item_references', COUNT(*) FROM `item_references`;
SQL;
if (! is_dir($output) && ! mkdir($output, 0775, true)) {
    throw new RuntimeException('Folder output gagal dibuat.');
}
foreach (['00-periksa-database.sql' => $preflight, '01-tambah-keamanan-login.sql' => $install, '03-verifikasi-database.sql' => $verify] as $file => $contents) {
    if (file_put_contents($output . '/' . $file, rtrim($contents) . "\n") === false) {
        throw new RuntimeException('SQL gagal ditulis.');
    }
}
echo "SQL keamanan dibuat tanpa kredensial atau data akun lokal.\n";
