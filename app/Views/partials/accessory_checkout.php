<?php
$unitPrefix = $unitPrefix ?? '';
$groupUnit = $unitPrefix !== '';
$selectedAccessories = $groupUnit ? ($draft['accessory_sent'] ?? []) : (session()->get('_ci_old_input')['post']['accessory_sent'] ?? []);
$selectedAccessories = is_array($selectedAccessories) ? $selectedAccessories : [];
?>
<section class="accessory-section">
  <?php if (! $groupUnit): ?>
  <span class="eyebrow">Pemeriksaan serah terima</span>
  <h2>Kelengkapan yang diserahkan</h2>
  <p class="muted accessory-help">Periksa barangnya, lalu ketuk baris yang ikut diserahkan. Kelengkapan opsional yang tidak dipilih tidak ditagih saat kembali.</p>
  <input type="hidden" name="accessory_checklist" value="1">
  <?php endif; ?>
  <input type="hidden" name="<?= $groupUnit ? $unitPrefix . '[accessory_template_hash]' : 'accessory_template_hash' ?>" value="<?= esc($accessory_template_hash) ?>">
  <?php foreach ($accessories as $entry): ?>
  <label class="accessory-check">
    <input type="checkbox" name="<?= $groupUnit ? $unitPrefix . '[accessory_sent]' : 'accessory_sent' ?>[<?= (int) $entry['id'] ?>]" value="1" <?= $entry['is_required'] ? 'required' : '' ?> <?php if ($entry['is_required']): ?>data-required-message="<?= esc($entry['name'] . ' wajib disertakan bersama unit. Periksa dan centang setelah diserahkan kepada peminjam.', 'attr') ?>"<?php endif; ?> <?= ($selectedAccessories[$entry['id']] ?? null) === '1' ? 'checked' : '' ?>>
    <span><b><?= esc($entry['name']) ?> × <?= (int) $entry['quantity'] ?></b><small><?= $entry['is_required'] ? 'Kelengkapan ini wajib disertakan bersama unit.' : 'Opsional disertakan; tetap harus kembali bila diserahkan' ?></small></span>
  </label>
  <?php endforeach; ?>
  <?php if (! $accessories): ?><p class="muted accessory-help">Unit belum memiliki daftar bawaan. Kelengkapan yang ikut dibawa dapat ditambahkan di bawah.</p><?php endif; ?>
</section>
<?php if ($groupUnit): ?>
<?= view('partials/accessory_editor', [
    'extra' => true, 'fieldPrefix' => $unitPrefix . '[extra_accessories]',
    'editorId' => 'extras-' . $groupItemId, 'draftExtras' => $draft['extra_accessories'] ?? [],
], ['saveData' => false]) ?>
<?php else: ?>
<?= view('partials/accessory_editor', ['extra' => true]) ?>
<label class="accessory-check accessory-confirm"><input type="checkbox" name="accessory_confirmed" value="1" required data-required-message="Konfirmasikan bahwa unit dan kelengkapan sudah diperiksa sebelum menyimpan peminjaman."><span>Saya sudah memeriksa unit dan kelengkapan yang diserahkan.</span></label>
<?php endif; ?>
