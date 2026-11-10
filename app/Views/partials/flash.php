<?php if ($flashSuccess || $flashError || $flashInfo || ($flashWarning ?? null)): ?>
<div class="flash-stack">
  <?= view('partials/notification', ['type' => 'success', 'message' => $flashSuccess]) ?>
  <?= view('partials/notification', ['type' => 'error', 'message' => $flashError]) ?>
  <?= view('partials/notification', ['type' => 'info', 'message' => $flashInfo]) ?>
  <?= view('partials/notification', ['type' => 'warning', 'message' => $flashWarning ?? null]) ?>
</div>
<?php endif; ?>
