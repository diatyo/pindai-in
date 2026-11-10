<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\AuthSecurity;
use Config\Database;

class LoginLimiter
{
    private BaseConnection $db;
    private AuthSecurity $policy;
    private \Closure $clock;

    public function __construct(?BaseConnection $db = null, ?AuthSecurity $policy = null, ?callable $clock = null)
    {
        $this->db = $db ?? Database::connect();
        $this->policy = $policy ?? config(AuthSecurity::class);
        $this->clock = $clock ? \Closure::fromCallable($clock) : static fn () => time();
    }

    private function bucket(string $scope, string $value, int $now): array
    {
        $key = hash('sha256', $scope . "\0" . $value);
        $data = ['bucket_key' => $key, 'scope' => $scope, 'failures' => 0, 'window_started' => $now,
            'blocked_until' => 0, 'expires_at' => $now + $this->policy->failureWindow + $this->policy->retention,
            'updated_at' => date('Y-m-d H:i:s', $now)];
        if (! $this->db->table('auth_login_limits')->ignore(true)->insert($data)) {
            throw new \RuntimeException('Pencatat percobaan login tidak tersedia.');
        }
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('auth_login_limits'));
        $lock = $this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
        $query = $this->db->query('SELECT * FROM ' . $table . ' WHERE bucket_key = ?' . $lock, [$key]);
        if ($query === false || ! ($row = $query->getRowArray())) {
            throw new \RuntimeException('Pembatas login gagal dibaca.');
        }
        if (((int) $row['blocked_until'] > 0 && (int) $row['blocked_until'] <= $now)
            || ((int) $row['blocked_until'] === 0 && $now - (int) $row['window_started'] >= $this->policy->failureWindow)) {
            $row = array_replace($row, $data);
        }
        return $row;
    }

    private function save(array $row, int $now): void
    {
        $row['expires_at'] = max((int) $row['window_started'] + $this->policy->failureWindow, (int) $row['blocked_until']) + $this->policy->retention;
        $row['updated_at'] = date('Y-m-d H:i:s', $now);
        if (! $this->db->table('auth_login_limits')->where('bucket_key', $row['bucket_key'])->update($row)) {
            throw new \RuntimeException('Percobaan login gagal dicatat.');
        }
    }

    private function failed(array $row, int $limit, int $now): array
    {
        $row['failures'] = (int) $row['failures'] + 1;
        if ($row['failures'] >= $limit) {
            $row['blocked_until'] = $now + $this->policy->cooldown;
        }
        return $row;
    }

    /** Authentication runs under IP-then-account locks, including concurrent PHP requests. */
    public function attempt(string $username, string $ip, callable $authenticate): array
    {
        $username = trim($username);
        $now = (int) ($this->clock)();
        // Bounded cleanup occurs before row locks, so it cannot introduce lock-order cycles.
        $expired = array_column($this->db->table('auth_login_limits')->select('bucket_key')->where('expires_at <', $now)->limit(100)->get()->getResultArray(), 'bucket_key');
        if ($expired !== [] && ! $this->db->table('auth_login_limits')->whereIn('bucket_key', $expired)->where('expires_at <', $now)->delete()) {
            throw new \RuntimeException('Pembersihan pembatas login gagal.');
        }
        if (! $this->db->transBegin()) {
            throw new \RuntimeException('Transaksi login tidak tersedia.');
        }
        try {
            $ip = filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : 'unknown';
            $network = $this->bucket('ip', $ip, $now);
            $wait = max(0, (int) $network['blocked_until'] - $now);
            $account = null;
            if ($wait === 0) {
                // Resolve with the same DB collation as login, so case/accent aliases share a limit.
                $matched = $this->db->table('users')->select('id')->where('username', $username)->get()->getRow();
                $identity = $matched ? 'id:' . (int) $matched->id : 'name:' . mb_strtolower(trim($username), 'UTF-8');
                $account = $this->bucket('account', $identity, $now);
                $wait = max(0, (int) $account['blocked_until'] - $now);
            }
            if ($wait > 0) {
                $outcome = ['blocked' => true, 'retry_after' => $wait, 'ok' => false];
            } else {
                $outcome = $authenticate();
                if (! empty($outcome['ok'])) {
                    // Successful authentication clears only this account, not failures from its shared IP.
                    $account['failures'] = 0;
                    $account['blocked_until'] = 0;
                    $account['window_started'] = $now;
                } else {
                    $account = $this->failed($account, $this->policy->accountMaxFailures, $now);
                    $network = $this->failed($network, $this->policy->ipMaxFailures, $now);
                }
                $outcome['retry_after'] = max(0, (int) $account['blocked_until'] - $now, (int) $network['blocked_until'] - $now);
                $outcome['blocked'] = $outcome['retry_after'] > 0;
                $this->save($account, $now);
            }
            $this->save($network, $now);
            if (! $this->db->transStatus() || ! $this->db->transCommit()) {
                throw new \RuntimeException('Percobaan login gagal disimpan.');
            }
            return $outcome;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
