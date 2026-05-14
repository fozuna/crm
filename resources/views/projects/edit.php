<?php
use App\Core\View;

$title = 'Editar projeto #' . (int)$project['id'];
$users = is_array($users ?? null) ? $users : [];
$owner = (int)($project['owner_user_id'] ?? 0);
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Editar projeto</div>
    <div class="text-slate-600 mt-1">Atualize dados básicos e responsável</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/projetos/' . (int)$project['id']) ?>" aria-label="Voltar">
    <?= \App\Core\UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<div class="mt-6 tr-card p-6">
  <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id']) ?>" class="space-y-5">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
    <div>
      <label class="tr-label">Título</label>
      <input name="title" class="mt-1 tr-input" value="<?= View::e((string)$project['title']) ?>" required>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="tr-label">Status</label>
        <select name="status" class="mt-1 tr-input">
          <?php foreach (['ativo','pausado','finalizado','cancelado'] as $st): ?>
            <option value="<?= View::e($st) ?>" <?= (string)$project['status'] === $st ? 'selected' : '' ?>><?= View::e($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Início</label>
        <input name="start_date" class="mt-1 tr-input" value="<?= View::e((string)($project['start_date'] ?? '')) ?>" placeholder="YYYY-MM-DD">
      </div>
      <div>
        <label class="tr-label">Término</label>
        <input name="end_date" class="mt-1 tr-input" value="<?= View::e((string)($project['end_date'] ?? '')) ?>" placeholder="YYYY-MM-DD">
      </div>
    </div>
    <div>
      <label class="tr-label">Responsável</label>
      <select name="owner_user_id" class="mt-1 tr-input">
        <option value="0" <?= $owner === 0 ? 'selected' : '' ?>>—</option>
        <?php foreach ($users as $u): ?>
          <?php $uid = (int)($u['id'] ?? 0); ?>
          <option value="<?= $uid ?>" <?= $owner === $uid ? 'selected' : '' ?>><?= View::e((string)($u['name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Descrição</label>
      <textarea name="description" class="mt-1 tr-input min-h-32" placeholder="Escopo e detalhes do projeto"><?= View::e((string)($project['description'] ?? '')) ?></textarea>
    </div>
    <div class="flex justify-end">
      <button class="tr-btn" type="submit">Salvar</button>
    </div>
  </form>
</div>

