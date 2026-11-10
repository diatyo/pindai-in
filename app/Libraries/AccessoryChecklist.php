<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/** All writes must be inside the same item-row transaction as the loan/item workflow. */
class AccessoryChecklist
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function template(int $itemId): array
    {
        return $this->db->table('item_accessories')->where('item_id', $itemId)->orderBy('id')->get()->getResultArray();
    }

    public function templateHash(int $itemId, ?array $template = null): string
    {
        $rows = array_map(static fn ($r) => [(int) $r['id'], $r['name'], (int) $r['quantity'], (int) $r['is_required']], $template ?? $this->template($itemId));
        return hash('sha256', json_encode($rows));
    }

    private function rows($input): array
    {
        if ($input === null) { return []; }
        if (! is_array($input) || count($input) > 30) {
            throw new \DomainException('Maksimal 30 kelengkapan dalam satu daftar.');
        }
        $rows = $seen = [];
        foreach ($input as $row) {
            if (! is_array($row) || ! is_string($row['name'] ?? null)) {
                throw new \DomainException('Nama kelengkapan tidak valid.');
            }
            $name = trim($row['name']);
            $quantity = $row['quantity'] ?? null;
            if ($name === '' || mb_strlen($name) > 80 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
                throw new \DomainException('Nama kelengkapan wajib diisi, maksimal 80 karakter. Hapus baris yang tidak digunakan.');
            }
            if (! is_scalar($quantity) || ! ctype_digit((string) $quantity) || (int) $quantity < 1 || (int) $quantity > 99) {
                throw new \DomainException('Jumlah kelengkapan harus 1 sampai 99.');
            }
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $name), 'UTF-8');
            if (isset($seen[$key])) { throw new \DomainException('Nama kelengkapan tidak boleh berulang: ' . $name . '.'); }
            $seen[$key] = true;
            $rows[] = ['name' => $name, 'quantity' => (int) $quantity,
                'is_required' => ($row['is_required'] ?? '0') === '1' || ($row['is_required'] ?? 0) === 1 ? 1 : 0];
        }
        return $rows;
    }

    public function replaceTemplate(int $itemId, $input): string
    {
        $rows = $this->rows($input);
        $before = $this->rows($this->template($itemId));
        if ($this->templateValues($before) === $this->templateValues($rows)) {
            return '';
        }
        if (! $this->db->table('item_accessories')->where('item_id', $itemId)->delete()) {
            throw new \RuntimeException('Daftar kelengkapan gagal diperbarui.');
        }
        foreach ($rows as $row) {
            if (! $this->db->table('item_accessories')->insert($row + ['item_id' => $itemId])) {
                throw new \RuntimeException('Kelengkapan unit gagal disimpan.');
            }
        }
        return "Kelengkapan sebelumnya\n" . self::describeRows($before)
            . "\nKelengkapan sekarang\n" . self::describeRows($rows);
    }

    private function templateValues(array $rows): array
    {
        usort($rows, static fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $rows;
    }

    public static function describeRows(array $rows, bool $returned = false): string
    {
        if ($rows === []) return '(tidak ada kelengkapan)';
        return implode("\n", array_map(static fn ($row) => '• ' . $row['name'] . ' × ' . $row['quantity']
            . ($row['is_required'] ? ' (wajib)' : ' (opsional)')
            . ($returned ? ' · kembali ' . $row['quantity_returned'] . '/' . $row['quantity'] : ''), $rows));
    }

    public function checkout(int $itemId, array $post): array
    {
        $template = $this->template($itemId);
        $enabled = ($post['accessory_checklist'] ?? '') === '1';
        if (! $enabled) {
            if ($template !== [] || ! empty($post['extra_accessories'])) {
                throw new \DomainException('Muat ulang form untuk memeriksa kelengkapan unit.');
            }
            return ['enabled' => 0, 'rows' => []]; // Existing clients with no master checklist retain their legacy return flow.
        }
        if (($post['accessory_confirmed'] ?? '') !== '1') {
            throw new \DomainException('Konfirmasikan pemeriksaan unit dan kelengkapan sebelum menyimpan.');
        }
        $hash = $post['accessory_template_hash'] ?? '';
        if (! is_string($hash) || ! hash_equals($this->templateHash($itemId, $template), $hash)) {
            throw new \DomainException('Daftar kelengkapan berubah. Muat ulang halaman dan periksa kembali barang yang diserahkan.');
        }
        $selected = $post['accessory_sent'] ?? [];
        if (! is_array($selected)) { throw new \DomainException('Pilihan kelengkapan tidak valid.'); }
        $known = array_column($template, null, 'id');
        foreach ($selected as $id => $value) {
            if (! isset($known[$id]) || $value !== '1') { throw new \DomainException('Kelengkapan pilihan bukan milik unit ini.'); }
        }
        $rows = [];
        foreach ($template as $entry) {
            if (! isset($selected[$entry['id']])) {
                if ($entry['is_required']) { throw new \DomainException('Kelengkapan wajib belum diserahkan: ' . $entry['name'] . '.'); }
                continue;
            }
            $rows[] = ['source_accessory_id' => (int) $entry['id'], 'name' => $entry['name'],
                'quantity' => (int) $entry['quantity'], 'is_required' => (int) $entry['is_required']];
        }
        $extraInput = $post['extra_accessories'] ?? [];
        $extras = $this->rows($extraInput);
        $sentNames = array_map(static fn ($r) => mb_strtolower(preg_replace('/\s+/u', ' ', $r['name']), 'UTF-8'), $rows);
        foreach ($extras as $index => $row) {
            $original = array_values($extraInput)[$index];
            if (($original['sent'] ?? '') !== '1') { throw new \DomainException('Centang kelengkapan tambahan yang sudah diserahkan: ' . $row['name'] . '.'); }
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $row['name']), 'UTF-8');
            if (in_array($key, $sentNames, true)) { throw new \DomainException('Kelengkapan tambahan sudah ada dalam daftar: ' . $row['name'] . '.'); }
            $sentNames[] = $key;
            $rows[] = $row + ['source_accessory_id' => null];
        }
        if (count($rows) > 30) { throw new \DomainException('Maksimal 30 kelengkapan diserahkan per transaksi.'); }
        return ['enabled' => 1, 'rows' => $rows];
    }

    public function saveCheckout(int $loanId, array $rows): void
    {
        foreach ($rows as $row) {
            if (! $this->db->table('loan_accessories')->insert($row + ['loan_id' => $loanId, 'quantity_returned' => 0])) {
                throw new \RuntimeException('Rincian serah terima gagal disimpan.');
            }
        }
    }

    public function forLoan(int $loanId): array
    {
        return $this->db->table('loan_accessories')->where('loan_id', $loanId)->orderBy('id')->get()->getResultArray();
    }

    public function inspectReturn(int $loanId, array $post): array
    {
        if (($post['accessory_return_confirmed'] ?? '') !== '1') { throw new \DomainException('Konfirmasikan pemeriksaan pengembalian.'); }
        $input = $post['accessory_returned'] ?? [];
        if (! is_array($input)) { throw new \DomainException('Pemeriksaan kelengkapan tidak valid.'); }
        $rows = $this->forLoan($loanId);
        $known = array_column($rows, null, 'id');
        foreach ($input as $id => $value) {
            if (! isset($known[$id]) || ! is_scalar($value) || ! ctype_digit((string) $value)
                || (int) $value > (int) $known[$id]['quantity']) {
                throw new \DomainException('Jumlah pengembalian atau kelengkapan tidak sesuai transaksi.');
            }
        }
        $missing = [];
        foreach ($rows as &$row) {
            $row['quantity_returned'] = (int) ($input[$row['id']] ?? 0);
            if ($row['quantity_returned'] < $row['quantity']) { $missing[] = $row['name']; }
        }
        unset($row);
        return ['rows' => $rows, 'completeness' => $missing === [] ? 'lengkap' : 'tidak_lengkap', 'missing' => $missing];
    }

    private function event(int $accessoryId, int $quantity, string $kind, ?string $note, ?string $requestKey = null): void
    {
        if (! $this->db->table('loan_accessory_events')->insert([
            'loan_accessory_id' => $accessoryId, 'quantity' => $quantity, 'kind' => $kind, 'note' => $note,
            'user_id' => session()->get('user_id'), 'user_name' => session()->get('full_name') ?: 'Petugas',
            'request_key' => $requestKey, 'created_at' => date('Y-m-d H:i:s'),
        ])) { throw new \RuntimeException('Riwayat pemeriksaan gagal disimpan.'); }
    }

    public function saveReturn(array $rows): void
    {
        foreach ($rows as $row) {
            $quantity = (int) $row['quantity_returned'];
            if (! $this->db->table('loan_accessories')->where('id', $row['id'])->update(['quantity_returned' => $quantity])) {
                throw new \RuntimeException('Pemeriksaan pengembalian gagal disimpan.');
            }
            $this->event((int) $row['id'], $quantity, 'initial', null);
        }
    }

    public function hasBlockingMissing(int $itemId): bool
    {
        return $this->db->table('loan_accessories a')->join('loans l', 'l.id = a.loan_id')
            ->where('l.item_id', $itemId)->where('l.returned_at IS NOT NULL')->where('a.is_required', 1)
            ->where('a.quantity_returned < a.quantity', null, false)->countAllResults() > 0;
    }

    public function resolve(int $loanId, int $accessoryId, array $post): bool
    {
        $row = $this->db->table('loan_accessories')->where('id', $accessoryId)->where('loan_id', $loanId)->get()->getRowArray();
        $loan = $this->db->table('loans')->where('id', $loanId)->get()->getRow();
        if (! $row || ! $loan || ! $loan->returned_at) { throw new \DomainException('Tindak lanjut tidak sesuai transaksi yang sudah dikembalikan.'); }
        $key = $post['request_key'] ?? '';
        if (! is_string($key) || ! preg_match('/\A[a-f0-9]{32}\z/', $key)) { throw new \DomainException('Form tindak lanjut tidak valid. Muat ulang halaman.'); }
        $existing = $this->db->table('loan_accessory_events')->where('request_key', $key)->get()->getRow();
        if ($existing) {
            if ((int) $existing->loan_accessory_id !== $accessoryId) { throw new \DomainException('Form tindak lanjut tidak sesuai kelengkapan.'); }
            return false;
        }
        $quantity = $post['quantity'] ?? null;
        $kind = $post['kind'] ?? '';
        $note = is_string($post['note'] ?? null) ? trim($post['note']) : '';
        if (! is_scalar($quantity) || ! ctype_digit((string) $quantity) || (int) $quantity < 1
            || (int) $quantity > (int) $row['quantity'] - (int) $row['quantity_returned']) {
            throw new \DomainException('Jumlah penyelesaian melebihi kekurangan atau tidak valid.');
        }
        if (! in_array($kind, ['returned', 'replacement'], true) || $note === '' || mb_strlen($note) > 500) {
            throw new \DomainException('Pilih cara penyelesaian dan isi catatan maksimal 500 karakter.');
        }
        if (! $this->db->table('loan_accessories')->where('id', $accessoryId)
            ->update(['quantity_returned' => (int) $row['quantity_returned'] + (int) $quantity])) {
            throw new \RuntimeException('Tindak lanjut gagal disimpan.');
        }
        $this->event($accessoryId, (int) $quantity, $kind, $note, $key);
        return true;
    }

    public function events(int $loanId): array
    {
        return $this->db->table('loan_accessory_events e')->select('e.*, a.name')->join('loan_accessories a', 'a.id = e.loan_accessory_id')
            ->where('a.loan_id', $loanId)->orderBy('e.id', 'DESC')->get()->getResultArray();
    }
}
