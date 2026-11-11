<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Auth');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override('App\Controllers\Errors::show404');

// ------------------------------------------------------------------
// Autentikasi
// ------------------------------------------------------------------
$routes->get('/', 'Auth::index');
$routes->post('drive-oauth/channel', 'DriveOAuth::channel');
$routes->get('drive-oauth/start', 'DriveOAuth::start');
$routes->get('drive-oauth/result', 'DriveOAuth::result');
$routes->get('admin/backups/drive/callback', 'DriveOAuth::callback');
$routes->match(['get', 'post'], 'login', 'Auth::login');
$routes->get('logout', 'Auth::logout', ['filter' => 'auth']);
$routes->get('forgot', 'Auth::forgot');
$routes->match(['get', 'post'], 'account/password', 'Account::password', ['filter' => 'auth']);
$routes->get('uploads/(:segment)', 'Photos::show/$1', ['filter' => 'auth']);

// ------------------------------------------------------------------
// Petugas Pengelola Barang
// ------------------------------------------------------------------
$routes->group('petugas', ['filter' => ['auth', 'role:petugas']], static function ($routes) {
    $routes->get('/', 'Petugas\Dashboard::index');
    $routes->get('dashboard', 'Petugas\Dashboard::index');

    // Scan QR
    $routes->get('scan', 'Petugas\Scan::index');
    $routes->post('scan/lookup', 'Petugas\Scan::lookup');

    // Barang
    $routes->get('items', 'Petugas\Items::index');
    $routes->get('items/create', 'Petugas\Items::create');
    $routes->get('items/check-serial', 'Petugas\Items::checkSerial');
    $routes->post('items/store', 'Petugas\Items::store');
    $routes->get('items/(:num)', 'Petugas\Items::show/$1');
    $routes->get('items/(:num)/edit', 'Petugas\Items::edit/$1');
    $routes->post('items/(:num)/update', 'Petugas\Items::update/$1');
    $routes->post('items/(:num)/archive', 'Petugas\Items::archive/$1');
    $routes->post('items/(:num)/restore', 'Petugas\Items::restore/$1');
    $routes->post('items/(:num)/regenerate-qr', 'Petugas\Items::regenerateQr/$1');
    $routes->get('items/(:num)/qr-image', 'Petugas\Items::qrImage/$1');
    $routes->get('items/(:num)/qr-pdf', 'Petugas\Items::qrPdf/$1');
    $routes->get('items/qr-labels', 'Petugas\Items::qrLabels');
    $routes->get('items/qr-label/(:segment)', 'Petugas\Items::qrLabel/$1');
    $routes->get('items/(:num)/history', 'Petugas\Items::history/$1');

    // Peminjaman
    $routes->get('loans', 'Petugas\Loans::index');
    $routes->get('loans/groups/create', 'Petugas\LoanGroups::create');
    $routes->get('loans/groups/lookup', 'Petugas\LoanGroups::lookup');
    $routes->post('loans/groups/store', 'Petugas\LoanGroups::store');
    $routes->get('loans/groups/(:num)', 'Petugas\LoanGroups::show/$1');
    $routes->get('loans/create/(:num)', 'Petugas\Loans::create/$1');
    $routes->post('loans/store', 'Petugas\Loans::store');
    $routes->get('loans/(:num)', 'Petugas\Loans::show/$1');
    $routes->get('loans/(:num)/return', 'Petugas\Loans::returnForm/$1');
    $routes->post('loans/(:num)/return', 'Petugas\Loans::returnStore/$1');
    $routes->get('loans/(:num)/success', 'Petugas\Loans::success/$1');
    $routes->get('loans/active/(:num)', 'Petugas\Loans::activeByItem/$1');
    $routes->get('borrowers/search', 'Petugas\Loans::searchBorrowers');
    $routes->get('accessories', 'Petugas\Accessories::index');
    $routes->post('loans/(:num)/accessories/(:num)/resolve', 'Petugas\Accessories::resolve/$1/$2');

    // Kerusakan
    $routes->get('damages', 'Petugas\Damages::index');
    $routes->get('damages/create', 'Petugas\Damages::create');
    $routes->get('damages/create/(:num)', 'Petugas\Damages::create/$1');
    $routes->get('damages/create/(:num)/(:num)', 'Petugas\Damages::create/$1/$2');
    $routes->post('damages/store', 'Petugas\Damages::store');
    $routes->get('damages/(:num)', 'Petugas\Damages::show/$1');

    // Penanganan
    $routes->get('handling', 'Petugas\Handling::index');
    $routes->get('handling/create/(:num)', 'Petugas\Handling::create/$1');
    $routes->post('handling/store', 'Petugas\Handling::store');
    $routes->get('handling/(:num)', 'Petugas\Handling::show/$1');

    // Riwayat unit & aktivitas
    $routes->get('history', 'Petugas\History::index');
    $routes->get('activity', 'Petugas\Activity::index');

    // Laporan
    $routes->get('reports', 'Petugas\Reports::index');
    $routes->get('reports/print', 'Petugas\Reports::printReport');
    $routes->get('reports/pdf', 'Petugas\Reports::pdf');
});

// ------------------------------------------------------------------
// Admin
// ------------------------------------------------------------------
$routes->group('admin', ['filter' => ['auth', 'role:admin']], static function ($routes) {
    $routes->get('/', 'Admin\Dashboard::index');
    $routes->get('dashboard', 'Admin\Dashboard::index');

    $routes->get('users', 'Admin\Users::index');
    $routes->post('users/store', 'Admin\Users::store');
    $routes->post('users/generate-borrowers', 'Admin\Users::generateBorrowers');
    $routes->post('users/generate-borrowers-batch', 'Admin\Users::generateBorrowersBatch');
    $routes->post('users/(:num)/update', 'Admin\Users::update/$1');
    $routes->post('users/(:num)/reset-password', 'Admin\Users::resetPassword/$1');
$routes->post('users/(:num)/toggle-active', 'Admin\Users::toggleActive/$1');
$routes->post('users/(:num)/delete', 'Admin\Users::delete/$1');

    $routes->get('teachers', 'Admin\Borrowers::teachers');
    $routes->get('students', 'Admin\Borrowers::students');
    $routes->post('borrowers/store', 'Admin\Borrowers::store');
    $routes->post('borrowers/(:num)/update', 'Admin\Borrowers::update/$1');
    $routes->post('borrowers/(:num)/delete', 'Admin\Borrowers::delete/$1');
    $routes->post('borrowers/(:num)/restore', 'Admin\Borrowers::restore/$1');
$routes->post('borrowers/import', 'Admin\Borrowers::import');
$routes->get('borrowers/template', 'Admin\Borrowers::template');

    $routes->get('activity', 'Admin\Activity::index');
    $routes->get('activity/pdf', 'Admin\Activity::pdf');

    $routes->get('settings', 'Admin\Settings::index');
    $routes->post('settings/save', 'Admin\Settings::save');
    $routes->post('settings/preview', 'Admin\Settings::preview');
    $routes->get('backups', 'Admin\Backups::index');
    $routes->post('backups/create', 'Admin\Backups::create');
    $routes->get('backups/download/(:segment)', 'Admin\Backups::download/$1');
    $routes->post('backups/upload/(:segment)', 'Admin\Backups::queue/$1');
    $routes->post('backups/restore', 'Admin\Backups::restore');
    $routes->post('backups/drive/connect', 'Admin\Backups::connectDrive');
    $routes->post('backups/drive/poll', 'Admin\Backups::pollDrive');
    $routes->post('backups/drive/disconnect', 'Admin\Backups::disconnectDrive');
    $routes->get('references', 'Admin\References::index');
    $routes->post('references/save', 'Admin\References::save');
    $routes->post('references/(:num)/toggle', 'Admin\References::toggle/$1');
    $routes->post('references/(:num)/delete', 'Admin\References::delete/$1');
});

// ------------------------------------------------------------------
// Kepala Sekolah (baca saja)
// ------------------------------------------------------------------
$routes->group('kepala', ['filter' => ['auth', 'role:kepala_sekolah']], static function ($routes) {
    $routes->get('/', 'Kepala\Dashboard::index');
    $routes->get('dashboard', 'Kepala\Dashboard::index');
    $routes->get('items', 'Kepala\Items::index');
    $routes->get('items/(:num)', 'Kepala\Items::show/$1');
    $routes->get('loans/(:num)', 'Kepala\Reports::loan/$1');
    $routes->get('damages/(:num)', 'Kepala\Reports::damage/$1');
    $routes->get('reports/loans', 'Kepala\Reports::loans');
    $routes->get('reports/damages', 'Kepala\Reports::damages');
    $routes->get('reports/handling', 'Kepala\Reports::handling');
    $routes->get('activity', 'Kepala\Reports::activity');
    $routes->get('reports/pdf', 'Kepala\Reports::pdf');
});

// ------------------------------------------------------------------
// Peminjam (guru/siswa, baca saja)
// ------------------------------------------------------------------
$routes->group('peminjam', ['filter' => ['auth', 'role:peminjam']], static function ($routes) {
    $routes->get('/', 'Peminjam\Dashboard::index');
    $routes->get('dashboard', 'Peminjam\Dashboard::index');
    $routes->get('loans', 'Peminjam\Loans::active');
    $routes->get('history', 'Peminjam\Loans::history');
    $routes->get('loans/(:num)', 'Peminjam\Loans::show/$1');
    $routes->get('profile', 'Peminjam\Profile::index');
});
