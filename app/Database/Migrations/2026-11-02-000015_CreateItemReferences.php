<?php

namespace App\Database\Migrations;

use App\Models\ItemReferenceModel;
use CodeIgniter\Database\Migration;

class CreateItemReferences extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('item_references')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'type' => ['type' => 'VARCHAR', 'constraint' => 10],
                'name' => ['type' => 'VARCHAR', 'constraint' => 100],
                'name_key' => ['type' => 'VARCHAR', 'constraint' => 100],
                'sort_order' => ['type' => 'INT', 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['type', 'name_key']);
            $this->forge->createTable('item_references');
        }
        // Copy labels already in use as well as the previous defaults. Never rewrite items.
        foreach (ItemReferenceModel::DEFAULTS as $type => $defaults) {
            $existing = $this->db->table('items')->select($type)->distinct()->get()->getResultArray();
            $names = array_merge($defaults, array_column($existing, $type));
            foreach ($names as $index => $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $key = ItemReferenceModel::nameKey($name);
                if ($this->db->table('item_references')->where('type', $type)->where('name_key', $key)->countAllResults() === 0) {
                    $this->db->table('item_references')->insert([
                        'type' => $type, 'name' => $name, 'name_key' => $key, 'sort_order' => ($index + 1) * 10,
                        'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('item_references', true);
    }
}
