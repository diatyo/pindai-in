<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['user_id', 'user_name', 'role', 'action', 'module', 'object', 'detail', 'ip_address'];
    protected $useTimestamps = false;

    public const SEARCH_MAX_LENGTH = 160;

    public static function normalizeKeyword(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $keyword = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        return trim(mb_substr($keyword, 0, self::SEARCH_MAX_LENGTH));
    }

    public function filtered(?string $q = null, ?string $module = null, ?string $from = null, ?string $to = null, ?int $limit = 100, ?int $userId = null)
    {
        $keyword = self::normalizeKeyword($q);
        if ($keyword !== '') {
            // Each word must match, but different words can match different fields.
            $escape = $this->db->likeEscapeChar;
            foreach (array_unique(explode(' ', $keyword)) as $word) {
                $literal = strtr($word, [$escape => $escape . $escape, '%' => $escape . '%', '_' => $escape . '_']);
                $this->groupStart()
                    ->like('object', $literal, 'both', true, true)
                    ->orLike('detail', $literal, 'both', true, true)
                    ->orLike('action', $literal, 'both', true, true)
                    ->orLike('module', $literal, 'both', true, true)
                    ->orLike('user_name', $literal, 'both', true, true)
                    ->groupEnd();
            }
        }
        if ($module && $module !== 'all') {
            $this->where('module', $module);
        }
        if ($from) {
            $this->where('created_at >=', $from . ' 00:00:00');
        }
        if ($to) {
            $this->where('created_at <=', $to . ' 23:59:59');
        }
        if ($userId !== null) {
            $this->where('user_id', $userId);
        }
        return $this->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->limit($limit);
    }
}
