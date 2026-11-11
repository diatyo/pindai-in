<?php

namespace App\Controllers;

use App\Libraries\DriveOAuthBroker;
use DomainException;

/** Public HTTPS endpoints for the OAuth broker. No Google credentials are accepted from installations. */
final class DriveOAuth extends BaseController
{
    public function channel()
    {
        $this->privateResponse();
        if (! DriveOAuthBroker::enabled()) return $this->response->setStatusCode(404)->setJSON([]);
        // This channel is called by PHP servers. Browser requests use the separate launch endpoint.
        $type = strtolower(trim(explode(';', $this->request->getHeaderLine('Content-Type'))[0]));
        $body = (string) $this->request->getBody();
        if ($type !== 'application/json' || $this->request->hasHeader('Origin') || strlen($body) > 1024) {
            return $this->response->setStatusCode(400)->setJSON([]);
        }
        $data = json_decode($body, true, 8);
        if (! is_string($data['action'] ?? null) || ! is_string($data['key'] ?? null)
            || ! is_string($data['id'] ?? null)) {
            return $this->response->setStatusCode(400)->setJSON([]);
        }
        try {
            $result = (new DriveOAuthBroker())->channel($data['action'], $data['key'], $data['id'], $this->request->getIPAddress());
            return $this->response->setJSON($result);
        } catch (DomainException $e) {
            $status = in_array($e->getCode(), [410, 429], true) ? $e->getCode() : 400;
            return $this->response->setStatusCode($status)->setJSON([]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(503)->setJSON([]);
        }
    }

    public function start()
    {
        if (! DriveOAuthBroker::enabled()) return $this->page('Sambungan Google Drive belum tersedia.', 404);
        try {
            $id = $this->query('id');
            $flow = (new DriveOAuthBroker())->launch($id, $this->query('ticket'));
            session()->set('driveBrokerConsent', ['id' => $id, 'state' => $flow['state']]);
            return redirect()->to($flow['url'])->setHeader('Cache-Control', 'no-store')->setHeader('Referrer-Policy', 'no-referrer');
        } catch (\Throwable $e) {
            return $this->page('Tautan persetujuan berakhir. Mulai lagi dari halaman Backup & Pemulihan.', 400);
        }
    }

    public function callback()
    {
        if (! DriveOAuthBroker::enabled()) return $this->page('Sambungan Google Drive belum tersedia.', 404);
        $result = redirect()->to(base_url('drive-oauth/result'), 303)
            ->setHeader('Cache-Control', 'no-store')->setHeader('Referrer-Policy', 'no-referrer');
        try {
            $pending = session()->get('driveBrokerConsent');
            $state = $this->query('state');
            if (! $pending || ! DriveOAuthBroker::valid($state) || ! hash_equals($pending['state'], $state)) {
                return $result;
            }
            session()->remove('driveBrokerConsent');
            session()->close();
            (new DriveOAuthBroker())->finish($pending['id'], $state, $this->query('code'));
        } catch (\Throwable $e) {
            // The installation reads the pairing status through its authenticated channel.
        }
        return $result;
    }

    public function result()
    {
        if (! DriveOAuthBroker::enabled()) return $this->page('Sambungan Google Drive belum tersedia.', 404);
        return $this->page('Kembali ke tab Backup & Pemulihan untuk melihat status sambungan.');
    }

    private function query(string $name): string
    {
        $value = $this->request->getGet($name);
        return is_string($value) && strlen($value) <= 4096 ? $value : '';
    }

    private function privateResponse(): void
    {
        $this->response->setHeader('Cache-Control', 'no-store')->setHeader('Referrer-Policy', 'no-referrer');
    }

    private function page(string $message, int $status = 200)
    {
        $this->privateResponse();
        return $this->response->setStatusCode($status)->setBody(view('drive_oauth_result', ['message' => $message]));
    }
}
