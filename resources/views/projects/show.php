<?php
use App\Core\View;
use App\Core\UI;

$title = 'Projeto #' . (int)$project['id'];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$milestones = is_array($milestones ?? null) ? $milestones : [];
$events = is_array($events ?? null) ? $events : [];
$history = is_array($history ?? null) ? $history : [];
$installments = is_array($installments ?? null) ? $installments : [];
$actorNames = is_array($actorNames ?? null) ? $actorNames : [];

$phases = [
  'planejamento' => 'Planejamento',
  'execucao' => 'Execução',
  'acompanhamento' => 'Acompanhamento',
  'entrega' => 'Entrega',
  'pos_venda' => 'Pós-venda',
];

$phase = (string)($project['workflow_phase'] ?? 'planejamento');
$pct = (float)($project['progress_percent'] ?? 0);

$totalOpen = 0.0;
$today = date('Y-m-d');
$overdueCount = 0;
foreach ($installments as $i) {
  $st = (string)($i['status'] ?? '');
  $due = (string)($i['due_date'] ?? '');
  $open = max(0, (float)($i['amount'] ?? 0) - (float)($i['paid_amount'] ?? 0));
  if (in_array($st, ['pendente','reaberto'], true)) {
    $totalOpen += $open;
    if ($due !== '' && $due < $today) {
      $overdueCount++;
    }
  }
}

$tasksByPhase = [];
foreach ($tasks as $t) {
  $k = (string)($t['phase'] ?? '');
  if (!isset($tasksByPhase[$k])) {
    $tasksByPhase[$k] = [];
  }
  $tasksByPhase[$k][] = $t;
}
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Projeto #<?= (int)$project['id'] ?></div>
    <div class="text-slate-600 mt-1"><?= View::e((string)$project['client_name']) ?> • <?= View::e((string)$project['title']) ?></div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-icon-btn" href="<?= View::e($base . '/projetos') ?>" aria-label="Voltar">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/editar') ?>" aria-label="Editar">
      <?= UI::icon('edit') ?>
      <span class="sr-only">Editar</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/financeiro') ?>" aria-label="Financeiro">
      <?= UI::icon('wallet') ?>
      <span class="sr-only">Financeiro</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Fase</div>
    <div class="text-lg font-semibold mt-1"><?= View::e($phases[$phase] ?? $phase) ?></div>
    <div class="mt-3 text-xs text-slate-600">Progresso</div>
    <div class="mt-2 h-2 bg-slate-200 rounded">
      <div class="h-2 bg-traxterAccent rounded" style="width: <?= max(0, min(100, $pct)) ?>%"></div>
    </div>
    <div class="text-xs text-slate-600 mt-1"><?= number_format($pct, 2, ',', '.') ?>%</div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Financeiro em aberto</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format($totalOpen, 2, ',', '.') ?></div>
    <div class="text-sm text-slate-600 mt-2">Atrasos: <span class="font-semibold"><?= (int)$overdueCount ?></span></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Status</div>
    <div class="text-lg font-semibold mt-1"><?= View::e((string)$project['status']) ?></div>
    <div class="text-sm text-slate-600 mt-2">Valor: <span class="font-semibold">R$ <?= number_format((float)($project['total'] ?? 0), 2, ',', '.') ?></span></div>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <div class="font-semibold">Workflow</div>
  <div class="mt-4 grid grid-cols-1 md:grid-cols-5 gap-2">
    <?php foreach ($phases as $k => $lbl): ?>
      <?php $active = $k === $phase; ?>
      <div class="rounded border px-3 py-2 <?= $active ? 'bg-slate-900 text-white border-slate-900' : 'bg-white border-slate-200 text-slate-700' ?>">
        <div class="text-sm font-semibold"><?= View::e($lbl) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
  <div class="tr-card p-6">
    <div class="font-semibold">Tarefas</div>
    <div class="mt-4 space-y-5">
      <?php foreach ($phases as $k => $lbl): ?>
        <?php $list = $tasksByPhase[$k] ?? []; ?>
        <div>
          <div class="text-sm font-semibold text-slate-700"><?= View::e($lbl) ?></div>
          <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/tarefas') ?>" class="mt-2 flex items-center gap-2">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <input type="hidden" name="phase" value="<?= View::e($k) ?>">
            <input name="title" class="tr-input text-sm" placeholder="Nova tarefa">
            <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Adicionar tarefa">
              <?= UI::icon('plus') ?>
              <span class="sr-only">Adicionar</span>
            </button>
          </form>
          <div class="mt-2 space-y-2">
            <?php if (count($list) === 0): ?>
              <div class="text-sm text-slate-500">Sem tarefas.</div>
            <?php endif; ?>
            <?php foreach ($list as $t): ?>
              <?php
                $tid = (int)$t['id'];
                $st = (string)$t['status'];
              ?>
              <div class="rounded border border-slate-200 bg-white p-3 flex items-start justify-between gap-3">
                <div>
                  <div class="font-semibold text-slate-900"><?= View::e((string)$t['title']) ?></div>
                  <div class="text-xs text-slate-600 mt-1">Status: <?= View::e($st) ?></div>
                </div>
                <div class="flex items-center gap-2">
                  <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/tarefas/' . $tid) ?>" class="flex items-center gap-2">
                  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                  <select name="status" class="tr-input text-sm">
                    <?php foreach (['pendente','em_andamento','concluida','cancelada'] as $opt): ?>
                      <option value="<?= View::e($opt) ?>" <?= $opt === $st ? 'selected' : '' ?>><?= View::e($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar tarefa">
                    <?= UI::icon('save') ?>
                    <span class="sr-only">Salvar</span>
                  </button>
                  </form>
                  <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/tarefas/' . $tid . '/excluir') ?>">
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <button class="tr-icon-btn" aria-label="Excluir tarefa">
                      <?= UI::icon('trash') ?>
                      <span class="sr-only">Excluir</span>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="space-y-4">
    <div class="tr-card p-6">
      <div class="font-semibold">Marcos</div>
      <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/marcos') ?>" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-2">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <input name="title" class="tr-input text-sm md:col-span-2" placeholder="Novo marco">
        <input name="due_date" class="tr-input text-sm" placeholder="YYYY-MM-DD">
        <div class="md:col-span-3 flex justify-end">
          <button class="tr-btn" type="submit">Adicionar marco</button>
        </div>
      </form>
      <div class="mt-4">
        <?php if (count($milestones) === 0): ?>
          <div class="text-sm text-slate-600">Sem marcos.</div>
        <?php else: ?>
          <ul class="space-y-2 text-sm">
            <?php foreach ($milestones as $m): ?>
              <?php $mid = (int)($m['id'] ?? 0); ?>
              <li class="rounded border border-slate-200 bg-white p-3">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="font-semibold"><?= View::e((string)$m['title']) ?></div>
                    <div class="text-slate-600 mt-1">
                      Vencimento: <?= !empty($m['due_date']) ? View::e(date('d/m/Y', strtotime((string)$m['due_date']))) : '—' ?> • Status: <?= View::e((string)$m['status']) ?>
                    </div>
                  </div>
                  <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/marcos/' . $mid . '/excluir') ?>">
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <button class="tr-icon-btn" aria-label="Excluir marco">
                      <?= UI::icon('trash') ?>
                      <span class="sr-only">Excluir</span>
                    </button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="tr-card p-6">
      <div class="font-semibold">Timeline</div>
      <div class="mt-4 space-y-2">
        <?php if (count($events) === 0): ?>
          <div class="text-sm text-slate-600">Sem eventos.</div>
        <?php endif; ?>
        <?php foreach ($events as $e): ?>
          <?php
            $aid = (int)($e['created_by'] ?? 0);
            $who = $aid > 0 ? (string)($actorNames[$aid] ?? ('#' . $aid)) : 'sistema';
          ?>
          <div class="rounded border border-slate-200 bg-white p-3">
            <div class="text-sm font-semibold"><?= View::e((string)$e['message']) ?></div>
            <div class="text-xs text-slate-600 mt-1"><?= View::e($who) ?> • <?= View::e((string)$e['created_at']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
