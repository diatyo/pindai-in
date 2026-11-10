<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['full_name', 'username', 'password_hash', 'must_change_password', 'role', 'borrower_id', 'position', 'is_active', 'last_login_at'];
    protected $useTimestamps = true;
    protected $beforeUpdate = ['bumpAuthVersion'];

    protected function bumpAuthVersion(array $event): array
    {
        $conditions = [];
        foreach (['password_hash', 'role', 'is_active', 'borrower_id', 'username', 'must_change_password'] as $field) {
            if (! array_key_exists($field, $event['data'])) {
                continue;
            }
            $column = $this->db->escapeIdentifiers($field);
            $value = $event['data'][$field];
            $conditions[] = $value === null ? $column . ' IS NOT NULL'
                : '(' . $column . ' IS NULL OR ' . $column . ' <> ' . $this->db->escape($value) . ')';
        }
        if ($conditions !== []) {
            // MySQL evaluates SET assignments left-to-right: compare old fields before changing them.
            $event['data'] = ['auth_version' => new \CodeIgniter\Database\RawSql(
                'auth_version + CASE WHEN ' . implode(' OR ', $conditions) . ' THEN 1 ELSE 0 END'
            )] + $event['data'];
        }
        return $event;
    }
}
