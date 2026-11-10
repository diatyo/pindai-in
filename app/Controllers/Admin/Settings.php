<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\SchoolLetterhead;

class Settings extends BaseController
{
    private const LABELS = [
        'school_name' => 'Nama sekolah', 'school_address' => 'Alamat', 'school_phone' => 'Telepon',
        'kepala_sekolah_name' => 'Kepala sekolah', 'kepala_sekolah_nip' => 'NIP Kepala Sekolah', 'help_contact' => 'Kontak bantuan',
    ];

    private function settings(): array
    {
        return array_column(\Config\Database::connect()->table('settings')->get()->getResultArray(), 'setting_value', 'setting_key');
    }

    public function index()
    {
        $settings = $this->settings();
        return view('admin/settings', ['title' => 'Konfigurasi Sistem', 'settings' => $settings,
            'letterhead' => SchoolLetterhead::values($settings)]);
    }

    private function input(array $current): array
    {
        $rules = [];
        foreach (self::LABELS as $key => $label) {
            $rules[$key] = ['label' => $label, 'rules' => 'if_exist|permit_empty|max_length[500]'];
        }
        $rules['school_phone'] = [
            'label' => 'Telepon sekolah',
            'rules' => 'if_exist|permit_empty|max_length[40]|regex_match[/\A(?=.*[0-9])\+?[0-9() -]+\z/]',
            'errors' => [
                'regex_match' => 'Format telepon sekolah tidak valid. Gunakan angka, spasi, tanda kurung, tanda hubung, atau tanda + di awal. Contoh: (0771) 21720.',
                'max_length' => 'Telepon sekolah maksimal 40 karakter.',
            ],
        ];
        $rules['kepala_sekolah_nip']['rules'] .= '|regex_match[/\A[0-9]*\z/]';
        $rules['kepala_sekolah_nip']['errors']['regex_match'] = 'NIP Kepala Sekolah hanya boleh berisi angka (0–9).';
        foreach (SchoolLetterhead::TEXT as $key => $field) {
            $rules[$key] = ['label' => $field[0], 'rules' => 'if_exist|permit_empty|max_length[' . $field[2] . ']'];
        }
        foreach (SchoolLetterhead::SIZES as $key => $field) {
            $rules[$key] = ['label' => $field[0], 'rules' => 'if_exist|required|numeric|greater_than_equal_to[' . $field[2] . ']|less_than_equal_to[' . $field[3] . ']',
                'errors' => ['required' => '{field} wajib diisi.', 'numeric' => '{field} harus berupa angka.',
                    'greater_than_equal_to' => '{field} minimal ' . $field[2] . '.', 'less_than_equal_to' => '{field} maksimal ' . $field[3] . '.']];
        }
        foreach (SchoolLetterhead::LINES as $key => $line) {
            $rules[$key . '_bold'] = ['label' => 'Pilihan teks tebal', 'rules' => 'if_exist|in_list[0,1]',
                'errors' => ['in_list' => 'Pilihan teks tebal tidak valid.']];
        }
        if (! $this->validate($rules)) throw new \DomainException(implode(' ', $this->validator->getErrors()));
        $values = $current;
        foreach (array_keys($rules) as $key) {
            if ($this->request->getPost($key) !== null) $values[$key] = (string) $this->request->getPost($key);
        }
        $images = [];
        foreach (SchoolLetterhead::LOGOS as $key => $default) {
            $images[$key] = SchoolLetterhead::readUpload($this->request->getFile($key));
            if ($this->request->getPost('reset_' . $key) === '1') {
                if ($images[$key] !== null) throw new \DomainException('Pilih unggah logo baru atau kembalikan logo bawaan dalam satu penyimpanan.');
                $values[$key] = '';
            }
        }
        return [$values, $images];
    }

    public function save()
    {
        $db = \Config\Database::connect();
        $createdFiles = [];
        try {
            if (! $db->transBegin()) throw new \RuntimeException('Transaksi tidak tersedia.');
            $table = $db->escapeIdentifiers($db->prefixTable('settings'));
            $lock = $db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
            $before = array_column($db->query('SELECT * FROM ' . $table . ' ORDER BY setting_key' . $lock)->getResultArray(), 'setting_value', 'setting_key');
            [$values, $images] = $this->input($before);
            foreach ($images as $key => $png) {
                if ($png === null) continue;
                $values[$key] = 'logo-' . bin2hex(random_bytes(16)) . '.png';
                $path = SchoolLetterhead::logoFile($values[$key]);
                if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0755, true) && ! is_dir(dirname($path))) {
                    throw new \RuntimeException('Folder logo tidak dapat dibuat.');
                }
                $createdFiles[] = $path;
                if (file_put_contents($path, $png, LOCK_EX) === false) throw new \RuntimeException('Logo gagal disimpan.');
            }
            foreach ($values as $key => $value) {
                if (array_key_exists($key, $before) && (string) $before[$key] === (string) $value) continue;
                $data = ['setting_value' => (string) $value, 'updated_at' => date('Y-m-d H:i:s')];
                $saved = array_key_exists($key, $before)
                    ? $db->table('settings')->where('setting_key', $key)->update($data)
                    : $db->table('settings')->insert($data + ['setting_key' => $key, 'created_at' => date('Y-m-d H:i:s')]);
                if (! $saved) throw new \RuntimeException('Pengaturan gagal disimpan.');
            }
            $labels = self::LABELS;
            foreach (SchoolLetterhead::TEXT + SchoolLetterhead::SIZES as $key => $field) $labels[$key] = $field[0];
            $formatters = [];
            $lineNumber = 0;
            foreach (SchoolLetterhead::LINES as $key => $line) {
                $labels[$key . '_bold'] = 'Baris ' . (++$lineNumber) . ' — Tebal';
                $formatters[$key . '_bold'] = static fn ($value) => (string) $value === '1' ? 'Ya' : 'Tidak';
            }
            $detail = activity_changes($before, array_intersect_key($values, $labels), $labels, $formatters);
            foreach (SchoolLetterhead::LOGOS as $key => $default) {
                if (($before[$key] ?? '') === ($values[$key] ?? '')) continue;
                $message = ($key === 'letterhead_city_logo' ? 'Logo daerah' : 'Logo sekolah')
                    . (empty($values[$key]) ? ' dikembalikan ke bawaan.' : ' diganti.');
                $detail = ($detail === 'Tidak ada perubahan' ? '' : $detail . "\n") . $message;
            }
            log_activity('konfigurasi_diperbarui', 'Konfigurasi', 'Pengaturan sekolah', $detail);
            if (! $db->transStatus() || ! $db->transCommit()) throw new \RuntimeException('Pengaturan dan audit gagal disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            foreach ($createdFiles as $path) if (is_file($path)) @unlink($path);
            if (! $e instanceof \DomainException) log_message('error', 'Pengaturan sekolah gagal: {message}', ['message' => $e->getMessage()]);
            return redirect()->to('/admin/settings')->withInput()->with('error', $e instanceof \DomainException ? $e->getMessage() : 'Pengaturan gagal disimpan. Silakan coba lagi.');
        }
        foreach (SchoolLetterhead::LOGOS as $key => $default) {
            $oldFile = SchoolLetterhead::logoFile($before[$key] ?? '');
            if ($oldFile && ($before[$key] ?? '') !== ($values[$key] ?? '') && is_file($oldFile)) @unlink($oldFile);
        }
        return redirect()->to('/admin/settings')->with('success', 'Konfigurasi sistem berhasil disimpan.');
    }

    public function preview()
    {
        try {
            [$settings, $images] = $this->input($this->settings());
            $values = SchoolLetterhead::values($settings);
            $logos = [];
            foreach (SchoolLetterhead::LOGOS as $key => $default) $logos[$key] = SchoolLetterhead::logoData($key, $values, $images[$key]);
            $html = view('admin/letterhead_preview', ['letterhead' => $values, 'logos' => $logos, 'settings' => $settings]);
            $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $pdf->loadHtml($html);
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();
            log_activity('kop_dipratinjau', 'Konfigurasi', 'Kop laporan PDF', 'Pratinjau tanpa menyimpan perubahan pengaturan.');
            return $this->response->setContentType('application/pdf')->setHeader('Content-Disposition', 'inline; filename="Pratinjau-Kop.pdf"')
                ->setHeader('X-CSRF-Token', csrf_hash())
                ->setHeader('Cache-Control', 'no-store, private')->setBody($pdf->output());
        } catch (\Throwable $e) {
            if (! $e instanceof \DomainException) log_message('error', 'Pratinjau kop gagal: {message}', ['message' => $e->getMessage()]);
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode($e instanceof \DomainException ? 422 : 500)->setHeader('X-CSRF-Token', csrf_hash())
                    ->setJSON(['message' => $e instanceof \DomainException ? $e->getMessage() : 'Pratinjau PDF gagal dibuat.']);
            }
            return redirect()->to('/admin/settings')->withInput()->with('error', $e instanceof \DomainException ? $e->getMessage() : 'Pratinjau PDF gagal dibuat.');
        }
    }
}
