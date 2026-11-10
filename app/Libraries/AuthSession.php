<?php

namespace App\Libraries;

use App\Models\BorrowerModel;
use App\Models\UserModel;

class AuthSession
{
    private function stamp(object $user): string
    {
        return hash('sha256', json_encode([$user->password_hash, $user->role, (int) ($user->borrower_id ?? 0)]));
    }

    public function establish(object $user, ?object $borrower = null): void
    {
        session()->regenerate(true);
        session()->set([
            'is_logged_in' => true, 'user_id' => (int) $user->id,
            'full_name' => $user->full_name, 'username' => $user->username,
            'role' => $user->role, 'position' => $user->position,
            'must_change_password' => (bool) $user->must_change_password,
            'borrower_id' => $borrower ? (int) $borrower->id : null,
            'borrower_type' => $borrower->type ?? null,
            'auth_version' => (int) $user->auth_version, 'auth_stamp' => $this->stamp($user),
        ]);
        session()->remove(['_ci_old_input', 'old']);
    }

    /** Read the authoritative user on every protected request; never trust stale role/session data. */
    public function currentUser(): ?object
    {
        if (! session()->get('is_logged_in')) {
            return null;
        }
        $user = model(UserModel::class)->find((int) session()->get('user_id'));
        $stamp = (string) session()->get('auth_stamp');
        if (! $user || ! $user->is_active
            || (int) session()->get('auth_version') !== (int) ($user->auth_version ?? 0)
            || $stamp === '' || ! hash_equals($this->stamp($user), $stamp)
            || $user->role !== session()->get('role')) {
            $this->revoke();
            return null;
        }
        $borrower = null;
        if ($user->role === 'peminjam') {
            $borrower = $user->borrower_id ? model(BorrowerModel::class)->find((int) $user->borrower_id) : null;
            if (! $borrower || ! $borrower->is_active || (int) session()->get('borrower_id') !== (int) $borrower->id) {
                $this->revoke();
                return null;
            }
        }
        session()->set([
            'full_name' => $user->full_name, 'username' => $user->username,
            'position' => $user->position, 'must_change_password' => (bool) $user->must_change_password,
            'borrower_type' => $borrower->type ?? null,
        ]);
        return $user;
    }

    public function revoke(): void
    {
        if (session()->get('is_logged_in')) {
            helper('activity');
            try {
                log_activity('sesi_dicabut', 'Sesi', (string) session()->get('username'), 'Sesi tidak lagi valid. Pengguna diminta masuk kembali.');
            } catch (\Throwable $e) {
                log_message('error', 'Audit pencabutan sesi gagal disimpan.');
            }
        }
        // Also discard one-time reset credentials and previous form data from this browser.
        session()->remove(array_keys(session()->get()));
        session()->regenerate(true);
    }
}
