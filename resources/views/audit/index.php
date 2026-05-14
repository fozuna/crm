<?php
use App\Core\View;

$title = 'Auditoria';
$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$actorNames = is_array($actorNames ?? null) ? $actorNames : [];

$entityType = (string)($filters['entity_type'] ?? '');
$entityId = (int)($filters['entity_id'] ?? 0);
$limit = (int)($filters['limit'] ?? 200);
?>

<div class="text-2xl font-semibold">Auditoria</div>
<div class="text-slate-600 mt-1">Logs de operações críticas</div>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/auditoria') ?>" class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div>
      <label class="tr-label">Entidade</label>
      <select name="entity_type" class="mt-1 tr-input">
        <option value="" <?= $entityType === '' ? 'selected' : '' ?>>Todas</option>
        <?php foreach (['proposal','project','installment','payment'] as $t): ?>
          <option value="<?= View::e($t) ?>" <?= $entityType === $t ? 'selected' : '' ?>><?= View::e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">ID</label>
      <input name="entity_id" class="mt-1 tr-input" value="<?= $entityId > 0 ? (int)$entityId : '' ?>" placeholder="Ex: 12">
    </div>
    <div>
      <label class="tr-label">Limite</label>
      <input name="limit" class="mt-1 tr-input" value="<?= (int)$limit ?>">
    </div>
    <div class="flex items-end">
      <button class="tr-btn w-full" type="submit">Aplicar</button>
    </div>
  </form>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">Quando</th>
          <th class="text-left py-3 px-4">Quem</th>
          <th class="text-left py-3 px-4">Entidade</th>
          <th class="text-left py-3 px-4">Ação</th>
          <th class="text-left py-3 px-4">Dados</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="5">Sem logs.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $aid = (int)($r['actor_id'] ?? 0);
            $who = $aid > 0 ? (string)($actorNames[$aid] ?? ('#' . $aid)) : 'sistema';
            $dataTxt = json_encode((array)($r['data'] ?? []), JSON_UNESCAPED_UNICODE);
          ?>
          <tr class="border-t">
            <td class="px-4 py-3 text-slate-700"><?= View::e((string)$r['created_at']) ?></td>
            <td class="px-4 py-3"><?= View::e($who) ?></td>
            <td class="px-4 py-3"><?= View::e((string)$r['entity_type']) ?> #<?= (int)$r['entity_id'] ?></td>
            <td class="px-4 py-3 font-semibold"><?= View::e((string)$r['action']) ?></td>
            <td class="px-4 py-3"><div class="text-xs text-slate-600 max-w-xl truncate"><?= View::e((string)$dataTxt) ?></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

