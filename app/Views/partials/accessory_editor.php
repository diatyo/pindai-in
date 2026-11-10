<?php
$extra = $extra ?? false;
$prefix = $fieldPrefix ?? ($extra ? 'extra_accessories' : 'accessories');
$editorId = $editorId ?? $prefix;
$oldPost = session()->get('_ci_old_input')['post'] ?? session()->getFlashdata('old') ?? [];
$postedRows = $draftExtras ?? ($oldPost[$prefix] ?? null);
$editorRows = is_array($postedRows) ? array_slice(array_filter($postedRows, 'is_array'), 0, 30) : ($extra ? [] : ($accessories ?? []));
$composerOpen = ! $extra && ! $editorRows;
?>
<section class="accessory-section accessory-editor" data-accessory-editor data-prefix="<?= $prefix ?>" data-extra="<?= $extra ? '1' : '0' ?>">
  <div class="accessory-editor-heading"><h2><?= $extra ? 'Kelengkapan tambahan' : 'Kelengkapan bawaan unit' ?></h2><span class="accessory-editor-count" data-accessory-count aria-live="polite" <?= $editorRows ? '' : 'hidden' ?>><?= count($editorRows) ?> kelengkapan</span></div>
  <p class="muted accessory-help"><?= $extra ? 'Tambahkan barang yang ikut diserahkan pada peminjaman ini. Daftar ini akan diperiksa saat pengembalian.' : 'Barang pendamping, seperti charger, kabel, atau tas. Opsional diisi.' ?></p>
  <?php if (! $extra): ?><input type="hidden" name="accessory_editor" value="1"><?php endif; ?>
  <div class="accessory-saved-list" data-accessory-rows>
    <?php foreach (array_values($editorRows) as $index => $entry): ?>
      <?= view('partials/accessory_editor_row', ['prefix' => $prefix, 'index' => $index, 'entry' => $entry, 'extra' => $extra]) ?>
    <?php endforeach; ?>
  </div>
  <div class="accessory-undo" data-accessory-undo-panel hidden><span role="status" data-accessory-undo-message>Kelengkapan dihapus.</span><button class="accessory-undo-button" type="button" data-accessory-undo>Urungkan</button></div>
  <button type="button" class="btn accessory-open" data-accessory-open aria-expanded="<?= $composerOpen ? 'true' : 'false' ?>" aria-controls="<?= $editorId ?>-composer" <?= $composerOpen ? 'hidden' : '' ?>><span aria-hidden="true">+</span> Tambah kelengkapan</button>
  <div class="accessory-composer" id="<?= $editorId ?>-composer" data-accessory-composer <?= $composerOpen ? '' : 'hidden' ?> role="group" aria-labelledby="<?= $editorId ?>-composer-heading">
    <h3 id="<?= $editorId ?>-composer-heading" data-accessory-composer-heading>Tambah kelengkapan</h3>
    <div class="accessory-composer-fields">
      <label class="accessory-name-field" for="<?= $editorId ?>-draft-name">Nama kelengkapan<input class="control" id="<?= $editorId ?>-draft-name" type="text" maxlength="80" autocomplete="off" placeholder="Contoh: Charger" data-accessory-name></label>
      <label class="accessory-quantity-field" for="<?= $editorId ?>-draft-quantity">Jumlah<input class="control" id="<?= $editorId ?>-draft-quantity" type="number" inputmode="numeric" min="1" max="99" value="1" data-accessory-quantity></label>
    </div>
    <?php if (! $extra): ?>
    <div class="accessory-row-options">
      <label class="accessory-option"><input type="checkbox" data-accessory-required aria-describedby="<?= $editorId ?>-required-hint"><span>Wajib menyertai unit<small id="<?= $editorId ?>-required-hint">Jika belum kembali, unit tidak bisa dipinjam lagi.</small></span></label>
    </div>
    <?php endif; ?>
    <p class="accessory-composer-error" id="<?= $editorId ?>-draft-error" data-accessory-error role="alert" hidden></p>
    <div class="accessory-composer-actions"><button type="button" class="btn" data-accessory-add>Masukkan ke daftar</button><button type="button" class="btn accessory-cancel" data-accessory-cancel>Batal</button></div>
  </div>
  <p class="accessory-save-note"><?= $extra ? 'Daftar ini tersimpan saat peminjaman disimpan.' : 'Daftar ini tersimpan saat data barang disimpan.' ?></p>
  <div class="accessory-live" data-accessory-message role="status" aria-atomic="true"></div>
  <template data-accessory-template><?= view('partials/accessory_editor_row', ['prefix' => $prefix, 'index' => '__INDEX__', 'entry' => [], 'extra' => $extra]) ?></template>
  <?php if ($extra && ! isset($fieldPrefix)): ?><p class="accessory-save-note">Barang dengan kode inventaris sendiri dicatat sebagai peminjaman unit tersendiri.</p><?php endif; ?>
  <noscript><p>Aktifkan JavaScript untuk menambah atau mengubah kelengkapan.</p></noscript>
</section>
