<?php $errors = session()->getFlashdata('errors') ?? []; ?>
<?php $oldStatus = old_value('status', 'in_progress'); ?>
<?= view('layouts/header', ['title' => 'Keterangan Penanganan', 'subtitle' => 'Catat keterangan dan perbarui status penanganan.']) ?>
<div class="page form-desktop">
  <div class="page-title">
    <div><span class="eyebrow"><?= esc($report->item_code) ?></span><h1>Keterangan Penanganan</h1></div>
    <span class="badge <?= damage_status_badge($report->status) ?>"><?= damage_status_label($report->status) ?></span>
  </div>

  <article class="card mt-18">
    <span class="badge red"><?= esc($report->item_code) ?></span>
    <h3 style="margin-top:8px"><?= esc($report->description) ?></h3>
    <small class="muted">Dilaporkan <?= esc(tgl_id_panjang($report->discovered_at)) ?> oleh <?= esc($report->reported_by_name ?? '—') ?> · Kondisi unit saat ini: <?= condition_label($report->item_condition) ?></small>
  </article>

  <?php if ($actions): ?>
  <article class="card timeline">
    <?php foreach ($actions as $a): ?>
    <div class="event"><b><?= esc($a->action_description) ?></b><small><?= esc(tgl_id($a->action_date)) ?> · <?= esc($a->executor_name) ?> · <?= $a->status === 'completed' ? 'Selesai' : ($a->status === 'unrepairable' ? 'Tidak dapat diperbaiki' : 'Berjalan') ?><?= $a->result_condition ? ' · Hasil: ' . condition_label($a->result_condition) : '' ?></small></div>
    <?php endforeach; ?>
  </article>
  <?php endif; ?>

  <?php if ($report->status === 'completed' || $report->status === 'unrepairable'): ?>
  <article class="card"><p class="muted" style="margin:0">Penanganan laporan ini telah <?= $report->status === 'completed' ? 'selesai' : 'dihentikan (tidak dapat diperbaiki)' ?>. Riwayat keterangan tetap tersimpan.</p></article>
  <?php else: ?>
  <form class="card mt-14" method="post" action="<?= base_url('/petugas/handling/store') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="damage_report_id" value="<?= $report->id ?>">
<?= view('partials/notification', ['type' => 'error', 'title' => 'Periksa kembali isian', 'messages' => $errors]) ?>
    <div class="field">
      <label>Status penanganan <b>*</b></label>
      <select class="control" name="status" id="handlingStatus" required>
        <option value="in_progress" <?= $oldStatus === 'in_progress' ? 'selected' : '' ?>>Dalam Penanganan</option>
        <option value="completed" <?= $oldStatus === 'completed' ? 'selected' : '' ?>>Selesai</option>
        <option value="unrepairable" <?= $oldStatus === 'unrepairable' ? 'selected' : '' ?>>Tidak dapat diperbaiki</option>
      </select>
    </div>
    <div class="field">
      <label>Keterangan <b>*</b></label>
      <textarea class="control" name="action_description" rows="4" placeholder="Jelaskan pemeriksaan, perbaikan, hasil, atau alasan tidak dapat diperbaiki."><?= esc(old_value('action_description')) ?></textarea>
    </div>
    <div class="grid2">
      <div class="field"><label>Tanggal penanganan <b>*</b></label><input class="control" type="date" name="action_date" value="<?= esc(date('Y-m-d')) ?>"></div>
      <div class="field"><label>Pelaksana <b>*</b></label><input class="control" name="executor_name" placeholder="Contoh: TU Pengurus Barang"></div>
    </div>
    <div class="field" id="resultConditionField" <?= $oldStatus === 'completed' ? '' : 'hidden' ?>>
      <label>Hasil kondisi setelah penanganan</label>
      <select class="control" name="result_condition" id="resultCondition" <?= $oldStatus === 'completed' ? 'required' : 'disabled' ?>>
        <option value="">Belum diketahui</option>
        <option value="good" <?= old_value('result_condition') === 'good' ? 'selected' : '' ?>>Baik</option>
        <option value="minor_damage" <?= old_value('result_condition') === 'minor_damage' ? 'selected' : '' ?>>Rusak ringan</option>
        <option value="major_damage" <?= old_value('result_condition') === 'major_damage' ? 'selected' : '' ?>>Rusak berat</option>
      </select>
      <small class="muted">Wajib diisi saat status Selesai. Unit menjadi Tersedia jika kondisinya baik, tidak sedang dipinjam, dan seluruh laporan kerusakannya sudah selesai.</small>
    </div>
    <button class="btn gold full" type="submit">Simpan Keterangan</button>
  </form>
  <script>
    (() => {
      const status = document.getElementById('handlingStatus');
      const field = document.getElementById('resultConditionField');
      const result = document.getElementById('resultCondition');
      const sync = () => {
        const completed = status.value === 'completed';
        field.hidden = !completed;
        result.disabled = !completed;
        result.required = completed;
        if (!completed) result.value = '';
      };
      status.addEventListener('change', sync);
      sync();
    })();
  </script>
  <?php endif; ?>
</div>
<?= view('layouts/footer') ?>
