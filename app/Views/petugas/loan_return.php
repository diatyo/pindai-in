<?php $oldCondition = old_value('condition_in', 'good'); $oldCompleteness = old_value('completeness_in', 'lengkap'); ?>
<?= view('layouts/header', ['title' => 'Pengembalian Barang', 'subtitle' => 'Catat kondisi dan kelengkapan saat barang kembali.']) ?>
<div class="page form-desktop">
  <?php if (! empty($loan->loan_group_id)): ?>
  <a class="btn" href="<?= base_url('/petugas/loans/groups/' . $loan->loan_group_id) ?>">Kembali ke kelompok</a>
  <?php endif; ?>
  <div class="page-title">
    <div><span class="eyebrow"><?= esc($loan->loan_number) ?></span><h1>Pengembalian</h1></div>
    <span class="badge amber">Transaksi aktif</span>
  </div>

  <article class="card mt-18">
    <h3><?= esc($loan->item_name) ?></h3>
    <p class="item-unit-code">Kode unit · <?= esc($loan->item_code) ?></p>
    <div class="kv" style="margin-top:10px"><span>Peminjam</span><b><?= esc($loan->borrower_name) ?> · <?= $loan->borrower_type === 'teacher' ? 'Guru' : 'Siswa' ?></b></div>
    <div class="kv"><span>Batas kembali</span><b><?= esc(tgl_id($loan->due_at, true)) ?></b></div>
  </article>

  <form class="card mt-14" id="loanReturnForm" method="post" enctype="multipart/form-data" action="<?= base_url('/petugas/loans/' . $loan->id . '/return') ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label>Kondisi saat kembali <b>*</b></label>
      <div class="tabs">
        <?php foreach (['good' => 'Baik', 'minor_damage' => 'Rusak ringan', 'major_damage' => 'Rusak berat'] as $k => $v): ?>
        <label class="tab" style="cursor:pointer"><input type="radio" name="condition_in" value="<?= $k ?>" <?= $oldCondition === $k ? 'checked' : '' ?> hidden> <?= $v ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (! empty($loan->accessory_checklist)): ?>
      <?= view('partials/accessory_return') ?>
    <?php else: ?>
    <div class="field">
      <label>Kelengkapan <b>*</b></label>
      <div class="tabs">
        <?php foreach (['lengkap' => 'Lengkap', 'tidak_lengkap' => 'Tidak lengkap'] as $k => $v): ?>
        <label class="tab" style="cursor:pointer"><input type="radio" name="completeness_in" value="<?= $k ?>" <?= $oldCompleteness === $k ? 'checked' : '' ?> hidden> <?= $v ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="muted accessory-help">Transaksi lama belum memiliki daftar rinci. Hasil pemeriksaan umum tetap dicatat pada riwayat.</p>
    <?php endif; ?>
    <div class="field">
      <label>Foto pemeriksaan <b id="returnPhotoRequired" hidden>*</b></label>
      <div class="photo" data-photo role="button" tabindex="0"><input id="returnPhoto" type="file" name="return_photo" accept="image/*" capture="environment" hidden><small class="muted" id="returnPhotoHint">Opsional jika barang lengkap.</small></div>
    </div>
    <div class="field">
      <label>Catatan pemeriksaan <b id="returnNotesRequired" hidden>*</b></label>
      <textarea class="control" id="returnNotes" name="return_notes" rows="3" placeholder="Catat bagian atau kelengkapan yang tidak tersedia."><?= esc(old_value('return_notes')) ?></textarea>
    </div>
    <button class="btn gold full" type="submit">Konfirmasi Pengembalian</button>
    <p class="muted" id="returnRuleText" style="margin-top:10px;font-size:12px">Hanya barang dengan kondisi rusak yang diarahkan ke laporan kerusakan.</p>
  </form>
  <script>
    (() => {
      const completeness = document.querySelectorAll('[name="completeness_in"]');
      const condition = document.querySelectorAll('[name="condition_in"]');
      const photo = document.getElementById('returnPhoto');
      const photoRequired = document.getElementById('returnPhotoRequired');
      const photoHint = document.getElementById('returnPhotoHint');
      const notes = document.getElementById('returnNotes');
      const notesRequired = document.getElementById('returnNotesRequired');
      const rule = document.getElementById('returnRuleText');
      const sync = () => {
        const comp = document.querySelector('[name="completeness_in"]:checked') || document.querySelector('[data-completeness-result]');
        const cond = document.querySelector('[name="condition_in"]:checked');
        const incomplete = comp && comp.value === 'tidak_lengkap';
        const damaged = cond && cond.value !== 'good';
        const needProof = incomplete || damaged;
        photoRequired.hidden = !needProof;
        notesRequired.hidden = !needProof;
        notes.required = needProof;
        photo.setAttribute('aria-required', needProof ? 'true' : 'false');
        photoHint.textContent = damaged
          ? 'Wajib: lampirkan foto kerusakan.'
          : (incomplete ? 'Wajib: ambil foto barang dan kelengkapannya.' : 'Opsional jika barang lengkap.');
        rule.textContent = damaged
          ? 'Laporan kerusakan dibuat otomatis dari catatan dan foto ini. Tidak perlu mengisi form terpisah.'
          : (incomplete
            ? (document.querySelector('[data-accessory-return]')
              ? 'Kekurangan disimpan untuk tindak lanjut. Kelengkapan wajib yang belum selesai menahan peminjaman unit.'
              : 'Transaksi lama: foto dan catatan ketidaklengkapan disimpan pada riwayat; daftar rinci belum tersedia.')
            : 'Hanya barang dengan kondisi rusak yang membuat laporan kerusakan otomatis.');
      };
      completeness.forEach(input => input.addEventListener('change', sync));
      condition.forEach(input => input.addEventListener('change', sync));
      sync();
    })();
  </script>
</div>
<?= view('layouts/footer') ?>
