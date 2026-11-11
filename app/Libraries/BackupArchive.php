<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;
use ZipArchive;

final class BackupArchive
{
    // Parents must precede dependent rows. The running installation owns the schema.
    private const TABLES = [
        'borrowers', 'users', 'settings', 'item_references', 'items', 'item_accessories',
        'document_sequences', 'loan_groups', 'loans', 'damage_reports', 'handling_actions',
        'item_status_history', 'loan_accessories', 'loan_accessory_events', 'activity_logs', 'auth_login_limits',
    ];
    private const PHOTO_FIELDS = ['items' => ['physical_photo', 'serial_photo'], 'loans' => ['return_photo'], 'damage_reports' => ['damage_photo']];
    private const MEDIA_LIMIT = 20 * 1024 * 1024;
    private const ROW_LIMIT = 1024 * 1024;

    public function __construct(private readonly BaseConnection $db, private readonly ?string $password)
    {
        helper('app');
    }

    public static function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public static function configured(): self
    {
        if (! self::available()) throw new DomainException('Aktifkan ekstensi ZIP di PHP agar backup dapat digunakan.');
        $password = (string) env('backup.password', '');
        if ($password !== '' && (strlen($password) < 24
            || ! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true)
            || ! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, false))) {
            throw new DomainException('Pengaturan kunci ZIP lama memerlukan minimal 24 karakter dan dukungan AES-256.');
        }
        return new self(\Config\Database::connect(null, false), $password === '' ? null : $password);
    }

    public function create(string $destination, bool $allowMissingMedia = false): void
    {
        $schema = $this->schema();
        $temp = BackupStorage::temporary();
        $sources = [];
        $zip = new ZipArchive();
        $opened = false;
        try {
            if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Paket ZIP tidak bisa dibuat.');
            $opened = true;
            if (! $this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')) throw new RuntimeException('Snapshot database tidak tersedia.');
            if (! $this->db->transBegin()) throw new RuntimeException('Snapshot database gagal dimulai.');
            $manifest = ['format' => 1, 'columns' => $schema, 'entries' => [], 'missingMedia' => []];

            foreach (self::TABLES as $table) {
                $source = BackupStorage::temporary();
                $sources[] = $source;
                $file = fopen($source, 'wb');
                if (! $file) throw new RuntimeException('File snapshot tidak bisa ditulis.');
                try {
                    $key = match ($table) { 'document_sequences' => 'prefix', 'auth_login_limits' => 'bucket_key', default => 'id' };
                    $last = null;
                    do {
                        $query = $this->db->table($table)->orderBy($key)->limit(500);
                        if ($last !== null) $query->where($key . ' >', $last);
                        $rows = $query->get()->getResultArray();
                        foreach ($rows as $row) {
                            $line = json_encode($row, JSON_THROW_ON_ERROR) . "\n";
                            if (strlen($line) >= self::ROW_LIMIT || fwrite($file, $line) !== strlen($line)) {
                                throw new RuntimeException('Baris snapshot gagal disimpan.');
                            }
                            $last = $row[$key];
                            foreach ($this->media($table, $row) as $path) {
                                $entry = 'media/' . $path;
                                if (isset($manifest['entries'][$entry]) || isset($manifest['missingMedia'][$path])) continue;
                                $sourcePath = $this->mediaSource($path);
                                $bytes = $sourcePath ? file_get_contents($sourcePath, false, null, 0, self::MEDIA_LIMIT + 1) : false;
                                if ($bytes === false || ! $this->validImage($path, $bytes)) {
                                    if ($allowMissingMedia) {
                                        $manifest['missingMedia'][$path] = true;
                                        continue;
                                    }
                                    throw new DomainException('Foto atau logo yang dirujuk tidak dapat dicadangkan: ' . $path);
                                }
                                $image = BackupStorage::temporary();
                                $sources[] = $image;
                                if (file_put_contents($image, $bytes) !== strlen($bytes) || ! $zip->addFile($image, $entry)) {
                                    throw new RuntimeException('Foto gagal dimasukkan ke ZIP.');
                                }
                                $this->encrypt($zip, $entry);
                                $manifest['entries'][$entry] = hash('sha256', $bytes);
                            }
                        }
                    } while (count($rows) === 500);
                } finally {
                    fclose($file);
                }
                $entry = 'data/' . $table . '.jsonl';
                if (! $zip->addFile($source, $entry)) throw new RuntimeException('Snapshot tabel gagal dimasukkan ke ZIP.');
                $this->encrypt($zip, $entry);
                $manifest['entries'][$entry] = hash_file('sha256', $source);
            }

            if (! $this->db->transStatus() || ! $this->db->transCommit()) throw new RuntimeException('Snapshot database gagal diselesaikan.');
            $this->addBytes($zip, 'manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
            if (! $zip->close()) throw new RuntimeException('Paket backup gagal ditutup.');
            $opened = false;
            if (! rename($temp, $destination)) throw new RuntimeException('Paket backup gagal disimpan.');
        } finally {
            if ($this->db->transDepth > 0) $this->db->transRollback();
            if ($opened) @$zip->close();
            foreach ($sources as $source) unlink($source);
            if (is_file($temp)) unlink($temp);
        }
    }

    public function restore(string $source, callable $beforeReplace, string $actor): void
    {
        $schema = $this->schema();
        $zip = new ZipArchive();
        if ($zip->open($source, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) throw new DomainException('File backup tidak valid.');
        $published = [];
        $committed = false;
        try {
            if ($this->password !== null) $zip->setPassword($this->password);
            $manifest = json_decode($zip->getFromName('manifest.json', 16 * 1024 * 1024) ?: 'null', true, 32, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? null) !== 1 || ($manifest['columns'] ?? null) !== $schema || ! is_array($manifest['entries'] ?? null)) {
                throw new DomainException('File backup tidak dapat dibuka. Periksa file, versi database, dan kunci jika memakai ZIP terenkripsi lama.');
            }
            $missing = $manifest['missingMedia'] ?? [];
            if (! is_array($missing)) throw new DomainException('Daftar foto yang hilang tidak valid.');
            if ($zip->numFiles !== count($manifest['entries']) + 1) throw new DomainException('Daftar isi paket backup tidak cocok.');

            foreach ($manifest['entries'] as $entry => $hash) {
                $isData = str_starts_with($entry, 'data/') && in_array(substr($entry, 5, -6), self::TABLES, true) && str_ends_with($entry, '.jsonl');
                if (! $isData && (! str_starts_with($entry, 'media/') || ! $this->validMediaPath(substr($entry, 6)))) {
                    throw new DomainException('Paket backup berisi file yang tidak dikenali.');
                }
                $stream = $zip->getStream($entry);
                if (! $stream) throw new DomainException('Paket backup tidak bisa dibaca.');
                try {
                    $context = hash_init('sha256');
                    hash_update_stream($context, $stream);
                    if (! is_string($hash) || ! hash_equals($hash, hash_final($context))) throw new DomainException('Isi paket backup rusak.');
                } finally {
                    fclose($stream);
                }
            }
            foreach (self::TABLES as $table) {
                if (! isset($manifest['entries']['data/' . $table . '.jsonl'])) throw new DomainException('Data tabel pada paket tidak lengkap.');
            }

            $beforeReplace();
            $oldVersions = array_column($this->db->table('users')->select('id, auth_version')->get()->getResultArray(), 'auth_version', 'id');
            if (! $this->db->query("SET SESSION sql_mode = CONCAT_WS(',', NULLIF(@@SESSION.sql_mode, ''), 'STRICT_ALL_TABLES')")) {
                throw new RuntimeException('Validasi database pemulihan tidak dapat diaktifkan.');
            }
            if (! $this->db->transBegin()) throw new RuntimeException('Transaksi pemulihan gagal dimulai.');
            foreach (array_reverse(self::TABLES) as $table) {
                if (! $this->db->table($table)->emptyTable()) throw new RuntimeException('Data lama gagal disiapkan untuk pemulihan.');
            }
            $hasAdmin = false;
            $restoredPaths = [];
            $restoreVersion = time();
            foreach (self::TABLES as $table) {
                $stream = $zip->getStream('data/' . $table . '.jsonl');
                if (! $stream) throw new DomainException('Data tabel tidak bisa dibaca.');
                try {
                    $batch = [];
                    while (($line = fgets($stream, self::ROW_LIMIT)) !== false) {
                        $row = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
                        if (! is_array($row) || array_keys($row) !== $schema[$table]) throw new DomainException('Kolom data backup tidak cocok.');
                        foreach ($this->media($table, $row) as $field => $path) {
                            if (($missing[$path] ?? false) === true) {
                                $row[$field] = null;
                                continue;
                            }
                            if (! isset($manifest['entries']['media/' . $path])) throw new DomainException('Foto yang dirujuk tidak lengkap.');
                            if (! isset($restoredPaths[$path])) $restoredPaths[$path] = $this->restoreMedia($zip, $path, $published);
                            $row[$field] = $table === 'settings' ? basename($restoredPaths[$path]) : $restoredPaths[$path];
                        }
                        if ($table === 'users') {
                            $hasAdmin = $hasAdmin || ($row['role'] === 'admin' && (bool) $row['is_active']);
                            $row['auth_version'] = max($restoreVersion, (int) $row['auth_version'], (int) ($oldVersions[$row['id']] ?? 0)) + 1;
                        }
                        $batch[] = $row;
                        if (count($batch) === 200) {
                            $this->insert($table, $batch);
                            $batch = [];
                        }
                    }
                    $this->insert($table, $batch);
                } finally {
                    fclose($stream);
                }
            }
            if (! $hasAdmin) throw new DomainException('Backup harus memiliki minimal satu akun Admin aktif.');
            $this->insert('activity_logs', [[
                'user_name' => $actor, 'role' => 'admin', 'action' => 'backup_dipulihkan', 'module' => 'Backup',
                'detail' => 'Database dan foto dipulihkan dari paket backup.', 'created_at' => date('Y-m-d H:i:s'),
            ]]);
            $committed = $this->db->transStatus() && $this->db->transCommit();
            if (! $committed) throw new RuntimeException('Pemulihan gagal disimpan.');
        } finally {
            if ($this->db->transDepth > 0) $this->db->transRollback();
            if (! $committed) foreach ($published as $file) unlink($file);
            $zip->close();
        }
    }

    private function schema(): array
    {
        if ($this->db->DBDriver !== 'MySQLi') throw new DomainException('Backup ini memerlukan MySQL atau MariaDB.');
        $engines = array_column($this->db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->getResultArray(), 'ENGINE', 'TABLE_NAME');
        $columns = [];
        foreach (self::TABLES as $table) {
            $name = $this->db->prefixTable($table);
            if (strcasecmp((string) ($engines[$name] ?? ''), 'InnoDB') !== 0) throw new DomainException('Tabel aplikasi belum lengkap atau bukan InnoDB.');
            $columns[$table] = $this->db->getFieldNames($name);
        }
        return $columns;
    }

    private function media(string $table, array $row): array
    {
        $paths = [];
        foreach (self::PHOTO_FIELDS[$table] ?? [] as $field) {
            if (! empty($row[$field])) $paths[$field] = $row[$field];
        }
        if ($table === 'settings' && isset(SchoolLetterhead::LOGOS[$row['setting_key']]) && ! empty($row['setting_value'])) {
            $paths['setting_value'] = 'branding/' . $row['setting_value'];
        }
        foreach ($paths as $path) {
            if (! is_string($path) || ! $this->validMediaPath($path)) throw new DomainException('Format referensi foto atau logo tidak valid.');
        }
        return $paths;
    }

    private function validMediaPath(string $path): bool
    {
        return preg_match('/\A(?:uploads\/[a-zA-Z0-9][a-zA-Z0-9._-]{0,230}\.(?:webp|png|jpe?g|avif|gif)|branding\/logo-[a-f0-9]{32}\.png)\z/i', $path) === 1;
    }

    private function mediaSource(string $path): ?string
    {
        if (str_starts_with($path, 'uploads/')) return stored_photo_file(basename($path));
        $file = SchoolLetterhead::logoFile(basename($path));
        return $file && is_file($file) && ! is_link($file) ? $file : null;
    }

    private function validImage(string $path, string $bytes): bool
    {
        if (strlen($bytes) > self::MEDIA_LIMIT) return false;
        $type = @getimagesizefromstring($bytes)[2] ?? 0;
        $allowed = ['webp' => IMAGETYPE_WEBP, 'png' => IMAGETYPE_PNG, 'jpg' => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG, 'avif' => IMAGETYPE_AVIF, 'gif' => IMAGETYPE_GIF];
        return $type === ($allowed[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? -1);
    }

    private function restoreMedia(ZipArchive $zip, string $path, array &$published): string
    {
        $bytes = $zip->getFromName('media/' . $path, self::MEDIA_LIMIT + 1);
        if ($bytes === false || ! $this->validImage($path, $bytes)) throw new DomainException('Isi gambar pada paket tidak valid.');
        $directory = dirname($path);
        $targetDir = WRITEPATH . $directory;
        if (is_link($targetDir) || (! is_dir($targetDir) && ! @mkdir($targetDir, 0750) && ! is_dir($targetDir))) {
            throw new RuntimeException('Folder pemulihan foto tidak bisa dibuat.');
        }
        $prefix = $directory === 'branding' ? 'logo-' : 'restored-';
        $relative = $directory . '/' . $prefix . bin2hex(random_bytes(16)) . '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $file = WRITEPATH . $relative;
        $published[] = $file;
        if (file_put_contents($file, $bytes) !== strlen($bytes)) throw new RuntimeException('Foto gagal dipulihkan.');
        chmod($file, 0600);
        return $relative;
    }

    private function addBytes(ZipArchive $zip, string $entry, string $bytes): void
    {
        if (! $zip->addFromString($entry, $bytes)) throw new RuntimeException('Isi paket gagal disimpan.');
        $this->encrypt($zip, $entry);
    }

    private function encrypt(ZipArchive $zip, string $entry): void
    {
        if ($this->password !== null && ! $zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $this->password)) {
            throw new RuntimeException('Enkripsi backup gagal.');
        }
    }

    private function insert(string $table, array $rows): void
    {
        if ($rows && $this->db->table($table)->insertBatch($rows) !== count($rows)) throw new RuntimeException('Baris backup gagal dipulihkan.');
    }
}
