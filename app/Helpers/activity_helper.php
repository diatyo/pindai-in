<?php

if (! function_exists('activity_ip_address')) {
    function activity_ip_address($request): string
    {
        if (! $request instanceof \CodeIgniter\HTTP\IncomingRequest) {
            return 'cli';
        }

        $remoteAddress = (string) $request->getServer('REMOTE_ADDR');
        $cloudflareAddress = trim($request->getHeaderLine('CF-Connecting-IP'));
        if (in_array($remoteAddress, ['127.0.0.1', '::1'], true) && filter_var($cloudflareAddress, FILTER_VALIDATE_IP)) {
            return $cloudflareAddress;
        }

        return $request->getIPAddress();
    }
}

if (! function_exists('log_activity')) {
    /**
     * Catat aktivitas pengguna untuk keperluan audit.
     *
     * @param string      $action  mis. 'login', 'barang_dibuat', 'peminjaman_dibuat'
     * @param string      $module  mis. 'Sesi', 'Barang', 'Peminjaman', 'Akun'
     * @param string|null $object  objek terkait, mis. 'PRO-001' atau nama akun
     * @param string|null $detail  keterangan tambahan
     */
    function log_activity(string $action, string $module, ?string $object = null, ?string $detail = null): void
    {
        $db = \Config\Database::connect();
        $request = \Config\Services::request();
        $userId = session()->get('user_id');
        $userName = session()->get('full_name') ?? 'Sistem';
        $role = session()->get('role') ?? '';
        $ip = activity_ip_address($request);
        $saved = $db->table('activity_logs')->insert([
            'user_id'    => $userId,
            'user_name'  => $userName,
            'role'       => $role,
            'action'     => $action,
            'module'     => $module,
            'object'     => $object,
            'detail'     => $detail,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (! $saved) {
            log_message('error', 'Pencatatan audit gagal untuk aksi {action} pada modul {module}.', ['action' => $action, 'module' => $module]);
            throw new \RuntimeException('Catatan audit gagal disimpan.');
        }
    }
}

if (! function_exists('log_status_history')) {
    /**
     * Catat perubahan status unit barang.
     */
    function log_status_history(int $itemId, string $from, string $to, ?string $note = null, ?int $loanId = null, ?int $damageReportId = null): void
    {
        $db = \Config\Database::connect();
        $saved = $db->table('item_status_history')->insert([
            'item_id'          => $itemId,
            'status_from'      => $from,
            'status_to'        => $to,
            'note'             => $note,
            'loan_id'          => $loanId,
            'damage_report_id' => $damageReportId,
            'changed_by'       => session()->get('user_id'),
            'changed_by_name'  => session()->get('full_name') ?? 'Sistem',
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
        if (! $saved) {
            throw new \RuntimeException('Riwayat status barang gagal disimpan.');
        }
    }
}

if (! function_exists('activity_changes')) {
    /**
     * Susun ringkasan perubahan field lama → baru untuk detail log aktivitas.
     *
     * @param object|array          $before  Data lama (row model atau array).
     * @param array<string,mixed>   $after   Data baru per field.
     * @param array<string,string>  $labels  Label tampil per field.
     * @param array<string,callable> $formatters Formatter opsional per field.
     */
    function activity_changes($before, array $after, array $labels, array $formatters = []): string
    {
        $arrow = '→';
        $before = (array) $before;
        $changes = [];
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            $format = $formatters[$field] ?? static fn ($v) => ($v === null || $v === '') ? '(kosong)' : (string) $v;
            if ((string) $oldValue !== (string) $newValue) {
                $label = $labels[$field] ?? $field;
                $changes[] = $label . ': ' . $format($oldValue) . ' ' . $arrow . ' ' . $format($newValue);
            }
        }

        return $changes ? implode("\n", $changes) : 'Tidak ada perubahan';
    }
}
