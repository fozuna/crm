<?php
use App\Core\View;
use App\Core\UI;

$title = 'Projetos';
$base = (string) ($base ?? '');
$filters = is_array($filters ?? null) ? $filters : [];
$projects = is_array($projects ?? null) ? $projects : [];
$users = is_array($users ?? null) ? $users : [];

$status = (string) ($filters['status'] ?? '');
$phase = (string) ($filters['workflow_phase'] ?? '');
$owner = (int) ($filters['owner_user_id'] ?? 0);

$phases = [
    'planejamento' => 'Planejamento',
    'execucao' => 'Execução',
    'acompanhamento' => 'Acompanhamento',
    'entrega' => 'Entrega',
    'pos_venda' => 'Pós-venda',
];

$statusOptions = [
    'ativo' => 'ativo',
    'pausado' => 'pausado',
    'finalizado' => 'finalizado',
    'cancelado' => 'cancelado',
];

$selectedAttr = static fn (bool $selected): string => $selected ? 'selected' : '';
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Projetos</div>
    <div class="text-slate-600 mt-1">Workflow, tarefas e financeiro integrados</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/relatorios/financeiro') ?>" aria-label="Relatórios financeiros">
    <?= UI::icon('bar-chart-3') ?>
    <span class="sr-only">Relatórios</span>
  </a>
</div>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/projetos') ?>" class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div>
      <label class="tr-label">Status</label>
      <select name="status" class="mt-1 tr-input">
        <option value="" <?= $selectedAttr($status === '') ?>>Todos</option>
        <?php foreach ($statusOptions as $k => $lbl): ?>
          <option value="<?= View::e($k) ?>" <?= $selectedAttr($status === $k) ?>><?= View::e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Fase</label>
      <select name="workflow_phase" class="mt-1 tr-input">
        <option value="" <?= $selectedAttr($phase === '') ?>>Todas</option>
        <?php foreach ($phases as $k => $lbl): ?>
          <option value="<?= View::e($k) ?>" <?= $selectedAttr($phase === $k) ?>><?= View::e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Responsável</label>
      <select name="owner_user_id" class="mt-1 tr-input">
        <option value="0" <?= $selectedAttr($owner === 0) ?>>Todos</option>
        <?php foreach ($users as $u): ?>
          <?php $uid = (int) ($u['id'] ?? 0); ?>
          <option value="<?= View::e((string) $uid) ?>" <?= $selectedAttr($owner === $uid) ?>><?= View::e((string) ($u['name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-end">
      <button class="tr-btn w-full" type="submit">Filtrar</button>
    </div>
  </form>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">Projeto</th>
          <th class="text-left py-3 px-4">Cliente</th>
          <th class="text-left py-3 px-4">Fase</th>
          <th class="text-left py-3 px-4">Progresso</th>
          <th class="text-left py-3 px-4">Valor</th>
          <th class="text-right py-3 px-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($projects) === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="6">Nenhum projeto encontrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($projects as $p): ?>
          <?php
            $pid = (int) ($p['id'] ?? 0);
            $pct = (float) ($p['progress_percent'] ?? 0);
            $pPhase = (string) ($p['workflow_phase'] ?? '');
            $phaseLbl = $phases[$pPhase] ?? $pPhase;
            $projectTitle = (string) ($p['title'] ?? '');
            $projectStatus = (string) ($p['status'] ?? '');
            $clientName = (string) ($p['client_name'] ?? '');
            $total = (float) ($p['total'] ?? 0);
          ?>
          <tr class="border-t">
            <td class="px-4 py-3">
              <div class="font-semibold">#<?= View::e((string) $pid) ?> — <?= View::e($projectTitle) ?></div>
              <div class="text-slate-500">Status: <?= View::e($projectStatus) ?></div>
            </td>
            <td class="px-4 py-3"><?= View::e($clientName) ?></td>
            <td class="px-4 py-3">
              <span class="tr-badge"><?= View::e($phaseLbl) ?></span>
            </td>
            <td class="px-4 py-3">
              <div class="w-40">
                <div class="h-2 bg-slate-200 rounded">
                  <div class="h-2 bg-traxterAccent rounded" style="width: <?= max(0, min(100, $pct)) ?>%"></div>
                </div>
                <div class="text-xs text-slate-600 mt-1"><?= number_format($pct, 2, ',', '.') ?>%</div>
              </div>
            </td>
            <td class="px-4 py-3">R$ <?= number_format($total, 2, ',', '.') ?></td>
            <td class="px-4 py-3 text-right">
              <a class="tr-icon-btn" href="<?= View::e($base . '/projetos/' . $pid) ?>" aria-label="Abrir">
                <?= UI::icon('arrow-right') ?>
                <span class="sr-only">Abrir</span>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
