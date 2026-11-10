<?php
$errors = session()->getFlashdata('errors') ?? [];
$oldPost = session()->get('_ci_old_input')['post'] ?? [];
$borrower = $borrower ?? null;
$isGroup = $isGroup ?? false;
$title = $isGroup ? 'Peminjaman Kelompok' : 'Peminjaman Baru';
$formValue = static fn (string $key, string $default = ''): string => is_string($oldPost[$key] ?? null) ? $oldPost[$key] : old_value($key, $default);
$detailErrors = array_intersect_key($errors, array_flip(['purpose', 'usage_location', 'due_date', 'due_time']));
$dueAt = strtotime($formValue('due_date') . ' ' . $formValue('due_time'));
$step = $borrower ? ($detailErrors || $dueAt <= time() ? 2 : 3) : 1;
?>
<?= view('layouts/header', ['title' => $title, 'subtitle' => 'Pilih peminjam, isi detail, lalu periksa kelengkapan.']) ?>
<div class="page form-desktop borrow-page compact-borrow">
  <div class="page-title">
    <div><span class="eyebrow">Peminjaman</span><h1><?= esc($title) ?></h1></div>
    <?php if (! $isGroup): ?><span class="badge green">Tersedia</span><?php endif; ?>
  </div>

  <?php if (! $isGroup): ?>
  <article class="unit-ticket">
    <div class="unit-ticket-photo">
      <?php if ($item->physical_photo): ?>
      <img src="<?= esc(base_url($item->physical_photo), 'attr') ?>" alt="Foto <?= esc($item->item_code) ?>">
      <?php else: ?>
      <?= esc(substr($item->item_code, 0, 3)) ?>
      <?php endif; ?>
    </div>
    <div class="unit-ticket-copy">
      <span class="badge green">Siap dipinjam</span>
      <h3><?= esc($item->item_name) ?></h3>
      <p><?= esc($item->item_code) ?> · Kondisi <?= condition_label($item->condition) ?> · Lokasi <?= esc($item->location) ?></p>
    </div>
  </article>
  <?php endif; ?>

  <?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>

  <form id="loanCreateForm" data-step="<?= $step ?>" method="post" action="<?= base_url($isGroup ? '/petugas/loans/groups/store' : '/petugas/loans/store') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="borrower_id" id="borrower_id" value="<?= (int) ($borrower->id ?? 0) ?: '' ?>">
    <?php if ($isGroup): ?>
    <input type="hidden" name="request_key" value="<?= esc($requestKey, 'attr') ?>">
    <?php else: ?>
    <input type="hidden" name="item_id" value="<?= $item->id ?>">
    <input type="hidden" name="condition_out" value="<?= $item->condition ?>">
    <input type="hidden" name="completeness_out" value="lengkap">
    <?php endif; ?>

    <div class="loan-step" id="loanBorrowerStep" <?= $borrower ? 'hidden' : '' ?>>
    <section class="section-copy picker-intro">
      <span class="eyebrow">Langkah 1 dari 3 · Pilih peminjam</span>
      <h2 id="loanBorrowerHeading" tabindex="-1">Guru atau siswa siapa yang meminjam?</h2>
      <p class="muted">Pilih dari data guru/siswa. Akun login tidak diperlukan untuk menerima layanan peminjaman.</p>
    </section>

    <div class="borrower-choice quick-choice" id="borrowerChoices">
      <button type="button" class="borrower-option" data-type="teacher">
        <span class="choice-icon"><?= ui_icon('teacher','choice-svg') ?></span><span><small>Guru</small><strong>Pilih guru ›</strong></span>
      </button>
      <button type="button" class="borrower-option" data-type="student">
        <span class="choice-icon"><?= ui_icon('student','choice-svg') ?></span><span><small>Siswa</small><strong>Pilih siswa ›</strong></span>
      </button>
    </div>
    </div>

    <fieldset class="loan-step" id="loanDetailsStep" aria-labelledby="loanDetailsHeading" <?= $step === 2 ? '' : 'hidden' ?> <?= $step === 1 ? 'disabled' : '' ?>>
    <section class="section-copy loan-details-intro">
      <span class="eyebrow">Langkah 2 dari 3</span>
      <h2 id="loanDetailsHeading" tabindex="-1">Detail peminjaman</h2>
      <p class="muted">Tanggal dan waktu peminjaman dicatat otomatis oleh sistem.</p>
    </section>

    <article class="card selected-borrower" id="selectedBorrower">
      <div class="person-avatar" id="selectedInitial"><?= esc(initials($borrower->name ?? '')) ?></div>
      <div>
        <small class="muted">Peminjam dipilih</small>
        <b id="selectedName"><?= esc($borrower->name ?? '') ?></b>
        <p class="muted" id="selectedMeta"><?= $borrower ? esc(($borrower->type === 'student' ? 'Siswa · NISN ' : 'Guru · NIP ') . $borrower->identity_number . ($borrower->class_name ? ' · ' . $borrower->class_name : '')) : '' ?></p>
      </div>
      <button class="btn" type="button" id="changeLoanBorrower">Ganti peminjam</button>
    </article>

    <div class="form-section compact-fields">
      <div class="field">
        <label for="loanPurpose">Keperluan <b>*</b></label>
        <input class="control" id="loanPurpose" name="purpose" required maxlength="255" data-missing-message="Keperluan wajib diisi." value="<?= esc($formValue('purpose')) ?>" placeholder="Contoh: Presentasi belajar mengajar">
      </div>
      <div class="field">
        <label for="loanUsageLocation">Lokasi penggunaan <b>*</b></label>
        <input class="control" id="loanUsageLocation" name="usage_location" required maxlength="100" data-missing-message="Lokasi penggunaan wajib diisi." value="<?= esc($formValue('usage_location')) ?>" placeholder="Contoh: Ruang Kelas VIII A">
      </div>
      <div class="field">
        <label>Rencana kembali <b>*</b></label>
        <div class="date-pair">
          <input class="control" type="date" name="due_date" id="dueDate" required aria-label="Tanggal rencana kembali" data-missing-message="Tanggal rencana kembali wajib diisi." value="<?= esc($formValue('due_date', date('Y-m-d'))) ?>">
          <input class="control" type="time" name="due_time" id="dueTime" required aria-label="Jam rencana kembali" data-missing-message="Jam rencana kembali wajib diisi." value="<?= esc($formValue('due_time', '16:00')) ?>">
        </div>
      </div>
    </div>

    <div class="auto-note">
      <span>✓</span>
      <?php if ($isGroup): ?>
      <p>Keperluan, lokasi penggunaan, dan tenggat ini berlaku untuk semua unit dalam kelompok.</p>
      <?php else: ?>
      <p><b>Terisi otomatis:</b> unit, kondisi awal (<?= condition_label($item->condition) ?>), peminjam, NIP/NISN, serta waktu pinjam.</p>
      <?php endif; ?>
    </div>

    <div class="form-action">
      <button class="btn gold full" type="button" id="continueLoan"><?= $isGroup ? 'Lanjut ke daftar unit' : 'Lanjut ke kelengkapan' ?></button>
    </div>
    </fieldset>

    <fieldset class="loan-step" id="loanAccessoriesStep" aria-labelledby="loanAccessoriesHeading" <?= $step === 3 ? '' : 'hidden disabled' ?>>
    <section class="section-copy loan-step-header">
      <div>
        <span class="eyebrow">Langkah 3 dari 3</span>
        <h2 id="loanAccessoriesHeading" tabindex="-1"><?= $isGroup ? 'Scan dan periksa unit' : 'Periksa kelengkapan' ?></h2>
      </div>
      <button class="btn" type="button" id="backToLoanDetails">Kembali ke detail</button>
    </section>

    <?= $isGroup ? view('partials/loan_group_checkout', ['groupUnits' => $groupUnits, 'maxUnits' => $maxUnits]) : view('partials/accessory_checkout') ?>

    <div class="form-action">
      <button class="btn gold full" type="submit"<?= $isGroup ? ' id="saveLoanGroup" disabled' : '' ?>><?= $isGroup ? 'Simpan peminjaman kelompok' : 'Simpan peminjaman' ?></button>
    </div>
    </fieldset>
  </form>
</div>

<div class="modal" id="borrowerPicker">
  <div class="modal-box picker-box">
    <div class="picker-head">
      <div><span class="eyebrow">Data sekolah</span><h2 id="pickerHeadLabel">Guru</h2></div>
      <button class="round" data-close>×</button>
    </div>
    <input type="hidden" id="borrowerType" value="teacher">
    <div class="picker-tools">
      <input class="control" id="borrowerSearch" placeholder="Cari nama atau NIP/NISN">
      <select class="control" id="classFilter" hidden aria-label="Filter kelas">
        <option value="">Semua kelas</option>
        <?php foreach (model(\App\Models\BorrowerModel::class)->activeStudentClasses() as $k): ?>
        <option value="<?= esc($k, 'attr') ?>"><?= esc($k) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="person-list" id="borrowerList"></div>
    <h3 class="muted" id="borrowerEmpty" style="text-align:center;padding:18px 0">Tidak ditemukan</h3>
  </div>
</div>

<?= view('layouts/footer') ?>
