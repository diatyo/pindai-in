<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDisabledItemStatus extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('items', [
            'status' => ['type' => 'ENUM', 'constraint' => ['available', 'borrowed', 'damaged', 'in_handling', 'disabled'], 'default' => 'available'],
        ]);
        // Preserve the previous status of items archived with the older is_active-only flow.
        $items = $this->db->table('items')->where('is_active', 0)->where('status !=', 'disabled')->get()->getResult();
        foreach ($items as $item) {
            $this->db->table('item_status_history')->insert([
                'item_id' => $item->id, 'status_from' => $item->status, 'status_to' => 'disabled',
                'note' => 'Penyesuaian status barang arsip menjadi disabled.',
                'changed_by_name' => 'Sistem', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('items')->where('id', $item->id)->update(['status' => 'disabled']);
        }
    }

    public function down()
    {
        if ($this->db->table('items')->where('status', 'disabled')->countAllResults() > 0) {
            throw new \RuntimeException('Pulihkan barang arsip sebelum menghapus enum disabled.');
        }
        $this->forge->modifyColumn('items', [
            'status' => ['type' => 'ENUM', 'constraint' => ['available', 'borrowed', 'damaged', 'in_handling'], 'default' => 'available'],
        ]);
    }
}
