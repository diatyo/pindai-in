<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class Borrowers extends BaseController
{
    public function teachers()
    {
        return $this->listing('teacher');
    }

    public function students()
    {
        return $this->listing('student');
    }

    private function listing(string $type)
    {
        $q = trim((string) $this->request->getGet('q'));
        $class = trim((string) $this->request->getGet('class'));
        $archive = $this->request->getGet('view') === 'archive';
        $model = model('App\Models\BorrowerModel')->where('type', $type)->where('is_active', $archive ? 0 : 1);

        if ($q !== '') {
            $model->groupStart()->like('name', $q)->orLike('identity_number', $q)->groupEnd();
        }
        if ($type === 'student' && $class !== '') {
            $model->where('class_name', $class);
        }
        $rows = $model->orderBy('name', 'ASC')->paginate(per_page(10));
        $pager = $model->pager;

        return view('admin/borrowers', [
            'title' => 'Data ' . ($type === 'teacher' ? 'Guru' : 'Siswa'),
            'type' => $type,
            'rows' => $rows,
            'pager' => $pager,
            'q' => $q,
            'class' => $class,
            'archive' => $archive,
            'total' => $pager->getTotal(),
        ]);
    }

    public function store()
    {
        return $this->auditedChange(function () {
            $type = (string) $this->request->getPost('type');
            $identityLabel = $type === 'teacher' ? 'NIP' : 'NISN';
            $rules = [
                'type' => ['label' => 'Jenis data', 'rules' => 'required|in_list[teacher,student]'],
                'identity_number' => ['label' => $identityLabel, 'rules' => 'required|max_length[30]'],
                'name' => ['label' => 'Nama lengkap', 'rules' => 'required|max_length[120]'],
            ];
            if ($type === 'teacher') {
                $rules['identity_number']['rules'] = 'required|regex_match[/\A[0-9]{18}\z/]';
                $rules['identity_number']['errors'] = ['regex_match' => 'NIP wajib tepat 18 digit angka.'];
                $rules['name']['rules'] = 'required|regex_match[/\S/]|max_length[120]';
                $rules['gender'] = ['label' => 'Jenis kelamin', 'rules' => 'required|in_list[Laki-laki,Perempuan]'];
                $rules['phone'] = ['label' => 'No. HP', 'rules' => 'required|regex_match[/\A[0-9]+\z/]|min_length[10]', 'errors' => ['regex_match' => 'No. HP hanya boleh berisi angka (0-9).', 'min_length' => 'No. HP minimal 10 digit angka.']];
            }
            if ($type === 'student') {
                $rules['identity_number']['rules'] = 'required|regex_match[/\A[0-9]{10}\z/]';
                $rules['identity_number']['errors'] = ['regex_match' => 'NISN wajib tepat 10 digit angka.'];
            }
            if (! $this->validate($rules)) {
                return redirect()->back()->withInput()->with('borrower_create_error', true)->with('errors', $this->validator->getErrors());
            }

            $exists = model('App\Models\BorrowerModel')->where('type', $type)->where('identity_number', (string) $this->request->getPost('identity_number'))->first();
            if ($exists) {
                return redirect()->back()->withInput()->with('borrower_create_error', true)->with('errors', ['identity_number' => $type === 'teacher' ? 'NIP sudah terdaftar.' : 'NISN sudah terdaftar.']);
            }

            if (! model('App\Models\BorrowerModel')->insert([
                'type' => $type,
                'identity_number' => $this->request->getPost('identity_number'),
                'name' => $this->request->getPost('name'),
                'gender' => $this->request->getPost('gender') ?: null,
                'class_name' => $type === 'student' ? $this->request->getPost('class_name') : null,
                'phone' => $type === 'teacher' ? ($this->request->getPost('phone') ?: null) : null,
                'is_active' => 1,
            ])) {

                throw new \RuntimeException('Data gagal disimpan.');

            }

            log_activity($type === 'teacher' ? 'guru_ditambah' : 'siswa_ditambah', $type === 'teacher' ? 'Guru' : 'Siswa', (string) $this->request->getPost('name'), 'Data ' . ($type === 'teacher' ? 'guru' : 'siswa') . ' ditambahkan');
            return redirect()->to($this->typePath($type))->with('success', 'Data berhasil ditambahkan.');
        });
    }

    public function update(int $id)
    {
        return $this->auditedChange(function () use ($id) {
            $row = model('App\Models\BorrowerModel')->find($id);
            if (! $row) {
                return redirect()->back()->with('error', 'Data tidak ditemukan.');
            }

            if ($row->type === 'teacher') {
                $rules = [
                    'identity_number' => ['label' => 'NIP', 'rules' => 'required|regex_match[/\A[0-9]{18}\z/]', 'errors' => ['regex_match' => 'NIP wajib tepat 18 digit angka.']],
                    'phone' => ['label' => 'No. HP', 'rules' => 'required|regex_match[/\A[0-9]+\z/]|min_length[10]', 'errors' => ['regex_match' => 'No. HP hanya boleh berisi angka (0-9).', 'min_length' => 'No. HP minimal 10 digit angka.']],
                ];
                if (! $this->validate($rules)) {
                    return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
                }
            }

            if ($row->type === 'teacher') {
                $duplicate = model('App\Models\BorrowerModel')->where('type', 'teacher')
                    ->where('identity_number', (string) $this->request->getPost('identity_number'))
                    ->where('id !=', $id)->first();
                if ($duplicate) {
                    return redirect()->back()->withInput()->with('error', 'NIP sudah terdaftar. Gunakan NIP yang berbeda.');
                }
            }

            $data = [
                'identity_number' => $this->request->getPost('identity_number'),
                'name' => $this->request->getPost('name'),
                'gender' => $this->request->getPost('gender') ?: null,
                'class_name' => $row->type === 'student' ? $this->request->getPost('class_name') : null,
                'phone' => $row->type === 'teacher' ? ($this->request->getPost('phone') ?: null) : null,
                'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            ];

            $statusFmt = static fn ($v) => (int) $v === 1 ? 'Aktif' : 'Nonaktif';
            $detail = activity_changes($row, $data, [
                'identity_number' => $row->type === 'teacher' ? 'NIP' : 'NISN',
                'name' => 'Nama',
                'gender' => 'Jenis kelamin',
                'class_name' => 'Kelas',
                'phone' => 'Telepon',
                'is_active' => 'Status',
            ], ['is_active' => $statusFmt]);

            if (! model('App\Models\BorrowerModel')->update($id, $data)) {

                throw new \RuntimeException('Data gagal disimpan.');

            }

            log_activity('data_diperbarui', $row->type === 'teacher' ? 'Guru' : 'Siswa', (string) $this->request->getPost('name'), $detail);
            return redirect()->back()->with('success', 'Data berhasil diperbarui.');
        });
    }

    /** Mengembalikan data master; status akun peminjam terkait tidak diubah. */
    public function restore(int $id)
    {
        return $this->auditedChange(function () use ($id) {
            $row = model('App\Models\BorrowerModel')->find($id);
            if (! $row) {
                return redirect()->back()->with('error', 'Data arsip tidak ditemukan.');
            }
            if ($row->is_active) {
                return redirect()->to($this->typePath($row->type))->with('info', 'Data sudah aktif.');
            }

            if (! model('App\Models\BorrowerModel')->update($id, ['is_active' => 1])) {

                throw new \RuntimeException('Data gagal disimpan.');

            }
            log_activity('data_diaktifkan_kembali', $row->type === 'teacher' ? 'Guru' : 'Siswa', $row->name, 'Data dikembalikan dari arsip; status akun login tidak diubah');
            return redirect()->to($this->typePath($row->type))->with('success', 'Data berhasil diaktifkan kembali. Status akun login tidak diubah.');
        });
    }

    public function delete(int $id)
    {
        return $this->auditedChange(function () use ($id) {
            $row = model('App\Models\BorrowerModel')->find($id);
            if (! $row) {
                return redirect()->back()->with('error', 'Data tidak ditemukan.');
            }
            $label = $row->type === 'teacher' ? 'Guru' : 'Siswa';
            $used = (int) model('App\Models\LoanModel')->where('borrower_id', $id)->countAllResults();
            if ($used > 0) {
                if (! model('App\Models\BorrowerModel')->update($id, ['is_active' => 0])) {
                    throw new \RuntimeException('Data gagal disimpan.');
                }
                log_activity('data_dinonaktifkan', $label, $row->name, 'Dinonaktifkan karena memiliki riwayat transaksi');
                return redirect()->back()->with('success', 'Data dinonaktifkan dan dipindahkan ke Arsip karena memiliki riwayat transaksi.');
            }

            // Tanpa transaksi: hapus akun login tertaut lebih dulu agar FK borrower tidak menahan penghapusan.
            $db = \Config\Database::connect();
            $db->table('users')->where('borrower_id', $id)->delete();
            if (! model('App\Models\BorrowerModel')->delete($id)) {
                throw new \RuntimeException('Data gagal disimpan.');
            }

            log_activity('data_dihapus', $label, $row->name, 'Data dan akun login terkait dihapus permanen');
            return redirect()->back()->with('success', 'Data berhasil dihapus.');
        });
    }

    public function import()
    {
        return $this->doImport();
    }

    /** Unduh template XLSX sesuai jenis (guru/siswa) dengan header dan satu baris contoh. */
    public function template()
    {
        $type = $this->request->getGet('type') === 'teacher' ? 'teacher' : 'student';
        if ($type === 'teacher') {
            $header = ['NIP', 'Nama', 'JenisKelamin', 'NoHP'];
            $sample = ['999999999999999991', 'Contoh Nama Guru', 'L', ''];
            $fileName = 'template-data-guru.xlsx';
        } else {
            $header = ['NISN', 'Nama', 'JenisKelamin', 'Kelas'];
            $sample = ['0123456789', 'Contoh Nama Siswa', 'P', 'VIII A'];
            $fileName = 'template-data-siswa.xlsx';
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');
        // Kolom identitas (A) dan No. HP (D untuk guru) sebagai Text agar angka nol depan tidak hilang.
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        if ($type === 'teacher') {
            $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('@');
        }
        foreach ($header as $i => $value) {
            $sheet->setCellValue([$i + 1, 1], $value);
        }
        $sheet->setCellValueExplicit('A2', $sample[0], DataType::TYPE_STRING);
        $sheet->setCellValue('B2', $sample[1]);
        $sheet->setCellValue('C2', $sample[2]);
        $sheet->setCellValueExplicit('D2', $sample[3], DataType::TYPE_STRING);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new XlsxWriter($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $body = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->setBody($body);
    }

    private function doImport()
    {
        return $this->auditedChange(function () {
            $type = (string) $this->request->getPost('type');
            $file = $this->request->getFile('import_file');
            $ext = $file ? strtolower($file->getExtension()) : '';
            if (! in_array($type, ['teacher', 'student'], true) || ! $file || ! $file->isValid() || ! in_array($ext, ['xlsx', 'xls'], true)) {
                return redirect()->back()->with('error', 'File tidak valid. Gunakan file Excel (.xlsx).');
            }

            if ($ext === 'xlsx' && ! class_exists(\ZipArchive::class)) {
                log_message('error', 'Impor Excel gagal: ekstensi PHP ZIP (ZipArchive) belum aktif.');
                return redirect()->back()->with('error', 'Impor Excel belum tersedia karena ekstensi ZIP pada server belum aktif. Aktifkan ekstensi ZIP PHP, lalu restart server.');
            }

            try {
                $reader = IOFactory::createReaderForFile($file->getTempName());
                $reader->setReadDataOnly(true);
                $sheet = $reader->load($file->getTempName())->getActiveSheet();
                $rows = $sheet->toArray(null, true, false, false);
            } catch (\Throwable $e) {
                log_message('error', 'Gagal membaca file Excel: {exception}', ['exception' => $e]);
                return redirect()->back()->with('error', 'Gagal membaca file Excel. Pastikan format sesuai template.');
            }

            $expectedHeader = $type === 'teacher'
                ? ['nip', 'nama', 'jeniskelamin', 'nohp']
                : ['nisn', 'nama', 'jeniskelamin', 'kelas'];
            $header = array_map(static fn ($value) => strtolower(trim((string) $value)), array_shift($rows) ?? []);
            // Abaikan kolom kosong di ujung yang hanya memiliki format Excel.
            while ($header !== [] && end($header) === '') {
                array_pop($header);
            }
            if ($header !== $expectedHeader) {
                $label = $type === 'teacher' ? 'guru' : 'siswa';
                $columns = $type === 'teacher' ? 'NIP, Nama, JenisKelamin, NoHP' : 'NISN, Nama, JenisKelamin, Kelas';
                return redirect()->back()->with('error', 'Template impor ' . $label . ' tidak sesuai. Header wajib berurutan: ' . $columns . '. Unduh dan gunakan template ' . $label . '.');
            }
            $added = 0;
            $updated = 0;
            $skipped = 0;
            $invalidTeacher = 0;
            $invalidStudent = 0;
            $borrowerModel = model('App\Models\BorrowerModel');
            foreach ($rows as $row) {
                $identity = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                $genderInput = strtoupper(trim((string) ($row[2] ?? '')));
                $gender = match ($genderInput) {
                    'L', 'LAKI-LAKI', 'LAKI LAKI' => 'Laki-laki',
                    'P', 'PEREMPUAN' => 'Perempuan',
                    default => null,
                };
                if ($identity === '' || $name === '' || $gender === null) {
                    $skipped++;
                    if ($type === 'student') {
                        $invalidStudent++;
                    }
                    continue;
                }

                $data = [
                    'name' => $name,
                    'gender' => $gender,
                ];
                $data[$type === 'student' ? 'class_name' : 'phone'] = isset($row[3]) ? (trim((string) $row[3]) ?: null) : null;

                if ($type === 'teacher' && (
                    preg_match('/\A[0-9]{18}\z/', $identity) !== 1
                    || preg_match('/\A[0-9]{10,}\z/', (string) $data['phone']) !== 1
                )) {
                    $skipped++;
                    $invalidTeacher++;
                    continue;
                }

                if ($type === 'student' && (
                    preg_match('/\A[0-9]{10}\z/', $identity) !== 1
                    || mb_strlen($name) > 120
                    || $data['class_name'] === null
                    || mb_strlen($data['class_name']) > 30
                )) {
                    $skipped++;
                    $invalidStudent++;
                    continue;
                }

                $exists = $borrowerModel->where('type', $type)->where('identity_number', $identity)->first();
                if ($exists) {
                    $hasChanges = false;
                    foreach ($data as $field => $value) {
                        if ((string) ($exists->{$field} ?? '') !== (string) ($value ?? '')) {
                            $hasChanges = true;
                            break;
                        }
                    }
                    if ($hasChanges) {
                        if (! $borrowerModel->update((int) $exists->id, $data)) {
                            throw new \RuntimeException('Data gagal disimpan.');
                        }
                        log_activity('data_diimpor_diperbarui', $type === 'teacher' ? 'Guru' : 'Siswa', $name,
                            'ID data #' . $exists->id . "\n" . activity_changes($exists, $data, ['name' => 'Nama', 'gender' => 'Jenis kelamin', 'class_name' => 'Kelas', 'phone' => 'Telepon']));
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }
                if (! $borrowerModel->insert(array_merge([
                    'type' => $type,
                    'identity_number' => $identity,
                ], $data, [
                    'is_active' => 1,
                ]))) {
                    throw new \RuntimeException('Data gagal disimpan.');
                }
                log_activity('data_diimpor_ditambah', $type === 'teacher' ? 'Guru' : 'Siswa', $name,
                    'ID data #' . $borrowerModel->getInsertID() . "\n" . activity_changes([], $data, ['name' => 'Nama', 'gender' => 'Jenis kelamin', 'class_name' => 'Kelas', 'phone' => 'Telepon']));
                $added++;
            }

            log_activity('impor_data', $type === 'teacher' ? 'Guru' : 'Siswa', '', $added . ' ditambahkan, ' . $updated . ' diperbarui, ' . $skipped . ' dilewati');
            $message = 'Impor selesai: ' . $added . ' data ditambahkan, ' . $updated . ' diperbarui, ' . $skipped . ' dilewati.';
            if ($invalidTeacher > 0) {
                $message .= ' ' . $invalidTeacher . ' baris ditolak karena NIP wajib tepat 18 digit angka dan No. HP wajib minimal 10 digit angka.';
            }
            if ($invalidStudent > 0) {
                $message .= ' ' . $invalidStudent . ' baris siswa ditolak: NISN wajib tepat 10 digit angka, nama wajib diisi (maksimal 120 karakter), jenis kelamin L/P atau Laki-laki/Perempuan, dan kelas wajib diisi (maksimal 30 karakter).';
            }
            return redirect()->back()->with('success', $message);
        });
    }

    private function typePath(string $type): string
    {
        return $type === 'teacher' ? '/admin/teachers' : '/admin/students';
    }
}
