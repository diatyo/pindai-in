<?php

namespace App\Libraries;

use App\Models\ItemModel;
use App\Models\LoanModel;
use CodeIgniter\Database\BaseConnection;

final class LoanCheckout
{
    public const DETAIL_RULES = [
        'borrower_id' => ['label' => 'Peminjam', 'rules' => 'required|is_natural_no_zero'],
        'due_date' => ['label' => 'Tanggal rencana kembali', 'rules' => 'required|valid_date[Y-m-d]'],
        'due_time' => ['label' => 'Waktu rencana kembali', 'rules' => 'required|regex_match[/^(?:[01]\d|2[0-3]):[0-5]\d$/]'],
        'purpose' => ['label' => 'Keperluan', 'rules' => 'required|max_length[255]'],
        'usage_location' => ['label' => 'Lokasi penggunaan', 'rules' => 'required|max_length[100]'],
    ];

    private AccessoryChecklist $checklists;

    public function __construct(private BaseConnection $db, private ItemModel $items)
    {
        $this->checklists = new AccessoryChecklist($db);
    }

    /** Recheck availability and handover under the unit lock. */
    public function inspect(object $item, array $post): array
    {
        if ($this->items->stateFor($item)['status'] !== 'available') {
            throw new \DomainException('Unit ini tidak tersedia atau masih memiliki transaksi/kerusakan yang belum selesai.');
        }
        return $this->checklists->checkout((int) $item->id, $post);
    }

    public function record(object $item, object $borrower, array $details, array $checklist): int
    {
        $itemId = (int) $item->id;
        $loanId = model(LoanModel::class, true, $this->db)->insert($details + [
            'loan_number' => (new DocumentNumbers($this->db))->next('loan'),
            'item_id' => $itemId,
            'borrower_id' => (int) $borrower->id,
            'condition_out' => $item->condition,
            'processed_by' => (int) session()->get('user_id'),
            'accessory_checklist' => $checklist['enabled'],
        ]);
        if (! $loanId) {
            throw new \RuntimeException('Peminjaman gagal disimpan.');
        }

        $this->checklists->saveCheckout((int) $loanId, $checklist['rows']);
        $updated = $this->items->synchronizeState($item);
        log_status_history($itemId, $item->status, $updated->status, 'Dipinjam: ' . $details['purpose'], (int) $loanId);
        log_activity('peminjaman_dibuat', 'Peminjaman', $item->item_code,
            'Transaksi #' . $loanId . ' · ' . $item->item_name . "\nPeminjam: " . $borrower->name
            . "\nKeperluan: " . $details['purpose'] . "\nLokasi: " . $details['usage_location']
            . "\nBatas kembali: " . $details['due_at'] . "\nKelengkapan diserahkan\n"
            . AccessoryChecklist::describeRows($checklist['rows']));
        return (int) $loanId;
    }
}
