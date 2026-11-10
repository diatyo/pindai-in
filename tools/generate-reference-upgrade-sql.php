<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$output = $argv[1] ?? throw new RuntimeException('Folder output diperlukan.');
$autoload = $argv[2] ?? $root . '/vendor/autoload.php';
require $autoload;
require_once $root . '/app/Models/ItemReferenceModel.php';

$defaults = [];
$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";
foreach (\App\Models\ItemReferenceModel::DEFAULTS as $type => $names) {
    foreach ($names as $name) {
        $defaults[] = 'SELECT ' . $quote($type) . ' AS `type`, ' . $quote($name) . ' AS `name`';
    }
}
$defaultSelects = implode("\nUNION ALL\n", $defaults);
$sql = <<<'SQL'
-- PINDAI.IN: pemasangan Data Referensi A-Z.
-- Pilih DATABASE HOSTING yang benar di phpMyAdmin sebelum Import.
-- Backup database terlebih dahulu; jeda penggunaan aplikasi selama pemasangan.
-- Tabel lama: items dan migrations (tanpa prefix).
-- Menambah item_references dan satu catatan metadata migration.
-- Tidak mengubah/menghapus users, borrowers, items, loans, kerusakan, atau foto.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `item_references` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(10) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `name_key` VARCHAR(100) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT NOT NULL DEFAULT 1,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_references_type_name_key` (`type`, `name_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- DDL di atas berdiri sendiri (MySQL melakukan implicit commit untuk CREATE).
-- Pengisian dan metadata berikut berada dalam satu transaksi InnoDB.
START TRANSACTION;

-- Jangan mengisi ulang pilihan lama bila fitur sudah terpasang dan dikelola admin.
SET @pindai_reference_upgrade_needed = NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `version` = '2026-11-02-000015' AND `namespace` = 'App' AND `group` = 'default'
);

INSERT INTO `item_references` (`type`, `name`, `name_key`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT defaults_list.`type`, defaults_list.`name`, LOWER(TRIM(defaults_list.`name`)), 0, 1, NOW(), NOW()
FROM (
__DEFAULTS__
) AS defaults_list
WHERE @pindai_reference_upgrade_needed = 1
ON DUPLICATE KEY UPDATE `id` = `item_references`.`id`;

-- Ambil SEMUA nama kategori/lokasi dari barang hosting, termasuk barang arsip.
-- Nama/status referensi yang sudah ada dipertahankan bila SQL diulang.
INSERT INTO `item_references` (`type`, `name`, `name_key`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT 'category', MIN(TRIM(`category`)), LOWER(TRIM(`category`)), 0, 1, NOW(), NOW()
FROM `items`
WHERE @pindai_reference_upgrade_needed = 1 AND `category` IS NOT NULL AND TRIM(`category`) <> ''
GROUP BY LOWER(TRIM(`category`))
ON DUPLICATE KEY UPDATE `id` = `item_references`.`id`;

INSERT INTO `item_references` (`type`, `name`, `name_key`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT 'location', MIN(TRIM(`location`)), LOWER(TRIM(`location`)), 0, 1, NOW(), NOW()
FROM `items`
WHERE @pindai_reference_upgrade_needed = 1 AND `location` IS NOT NULL AND TRIM(`location`) <> ''
GROUP BY LOWER(TRIM(`location`))
ON DUPLICATE KEY UPDATE `id` = `item_references`.`id`;

-- Tandai migration yang sama dengan file PHP; hindari seeding ulang di kemudian hari.
INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT '2026-11-02-000015',
       CONCAT('App', CHAR(92), 'Database', CHAR(92), 'Migrations', CHAR(92), 'CreateItemReferences'),
       'default', 'App', UNIX_TIMESTAMP(), next_migration.next_batch
FROM (SELECT COALESCE(MAX(`batch`), 0) + 1 AS next_batch FROM `migrations`) AS next_migration
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations`
  WHERE `version` = '2026-11-02-000015' AND `namespace` = 'App' AND `group` = 'default'
);

COMMIT;

-- Jalankan 03-verifikasi-database.sql setelah Import untuk memeriksa hasilnya.
SQL;
$sql = str_replace('__DEFAULTS__', $defaultSelects, $sql) . "\n";

$preflight = <<<'SQL'
-- PEMERIKSAAN SAJA. Jalankan di tab SQL phpMyAdmin setelah memilih database hosting.
SELECT DATABASE() AS database_terpilih, VERSION() AS versi_database;
SHOW TABLES;
SELECT 'users' AS tabel, COUNT(*) AS jumlah FROM `users`
UNION ALL SELECT 'borrowers', COUNT(*) FROM `borrowers`
UNION ALL SELECT 'items', COUNT(*) FROM `items`
UNION ALL SELECT 'loans', COUNT(*) FROM `loans`
UNION ALL SELECT 'damage_reports', COUNT(*) FROM `damage_reports`
UNION ALL SELECT 'handling_actions', COUNT(*) FROM `handling_actions`
UNION ALL SELECT 'item_status_history', COUNT(*) FROM `item_status_history`
UNION ALL SELECT 'activity_logs', COUNT(*) FROM `activity_logs`
UNION ALL SELECT 'settings', COUNT(*) FROM `settings`;
SELECT `version`, `class`, `group`, `namespace`, `batch` FROM `migrations` ORDER BY `id` DESC LIMIT 3;
SQL;

$verify = <<<'SQL'
-- PEMERIKSAAN SAJA. Jumlah tabel lama harus sama dengan hasil 00-periksa-database.sql
-- selama tidak ada pengguna yang mencatat transaksi.
SELECT DATABASE() AS database_terpilih;
SHOW CREATE TABLE `item_references`;
SELECT `type`, COUNT(*) AS total, SUM(`is_active` = 1) AS aktif
FROM `item_references` GROUP BY `type`;

-- Hasil HARUS 0 baris: setiap kategori/lokasi yang dipakai memiliki referensi.
SELECT i.`id`, i.`category` AS kategori_belum_terdaftar FROM `items` i
LEFT JOIN `item_references` r ON r.`type` = 'category' AND r.`name_key` = LOWER(TRIM(i.`category`))
WHERE TRIM(COALESCE(i.`category`, '')) <> '' AND r.`id` IS NULL;
SELECT i.`id`, i.`location` AS lokasi_belum_terdaftar FROM `items` i
LEFT JOIN `item_references` r ON r.`type` = 'location' AND r.`name_key` = LOWER(TRIM(i.`location`))
WHERE TRIM(COALESCE(i.`location`, '')) <> '' AND r.`id` IS NULL;

-- Hasil HARUS 1 baris untuk migration referensi.
SELECT `version`, `class`, `group`, `namespace`, `batch` FROM `migrations`
WHERE `version` = '2026-11-02-000015' AND `namespace` = 'App' AND `group` = 'default';

SELECT 'users' AS tabel, COUNT(*) AS jumlah FROM `users`
UNION ALL SELECT 'borrowers', COUNT(*) FROM `borrowers`
UNION ALL SELECT 'items', COUNT(*) FROM `items`
UNION ALL SELECT 'loans', COUNT(*) FROM `loans`
UNION ALL SELECT 'damage_reports', COUNT(*) FROM `damage_reports`
UNION ALL SELECT 'handling_actions', COUNT(*) FROM `handling_actions`
UNION ALL SELECT 'item_status_history', COUNT(*) FROM `item_status_history`
UNION ALL SELECT 'activity_logs', COUNT(*) FROM `activity_logs`
UNION ALL SELECT 'settings', COUNT(*) FROM `settings`;
SQL;
if (! is_dir($output) && ! mkdir($output, 0775, true)) {
    throw new RuntimeException('Folder output tidak dapat dibuat.');
}
foreach (['00-periksa-database.sql' => $preflight, '01-tambah-tabel-referensi.sql' => $sql, '03-verifikasi-database.sql' => $verify] as $filename => $contents) {
    if (file_put_contents($output . '/' . $filename, rtrim($contents) . "\n") === false) {
        throw new RuntimeException('SQL tidak dapat ditulis.');
    }
}
echo "SQL dibuat dari default aplikasi; tanpa data/kredensial lokal.\n";
