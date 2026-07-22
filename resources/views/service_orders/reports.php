<?php
use App\Core\UI;
use App\Core\View;
use App\Services\ServiceOrderStatus;
use App\Services\ServiceOrderType;

$title = 'Relatórios de Ordens de Serviço';
$filters = is_array($filters ?? null) ? $filters : [];
$report = is_array($report ?? null) ? $report : [];
$rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
$totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
$reportTotal = (int) ($report['total'] ?? count($rows));
$reportTruncated = ($report['truncated'] ?? false) === true;
$canManage = ($canManage ?? false) === true;
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Relatórios de OS</div>
    <div class="text-slate-600 mt-1">Consolidação operacional e financeira das ordens de serviço cadastradas.</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico') ?>" aria-label="Voltar para listagem">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/ordens-servico/relatorios') ?>" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="xl:col-span-2">
      <label class="tr-label">Pesquisar</label>
      <input name="q" class="mt-1 tr-input" value="<?= View::e((string) ($filters['q'] ?? '')) ?>" placeholder="OS, cliente, serviço ou responsável">
    </div>
    <div>
      <label class="tr-label">Status</label>
      <select name="status" class="mt-1 tr-input">
        <option value="">Todos</option>
        <?php foreach ($statusOptions as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= (string) ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Tipo</label>
      <select name="type" class="mt-1 tr-input">
        <option value="">Todos</option>
        <?php foreach ($typeOptions as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= (string) ($filters['type'] ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Cliente</label>
      <select name="client_id" class="mt-1 tr-input">
        <option value="0">Todos</option>
        <?php foreach ($clients as $client): ?>
          <?php $clientId = (int) ($client['id'] ?? 0); ?>
          <option value="<?= $clientId ?>" <?= (int) ($filters['client_id'] ?? 0) === $clientId ? 'selected' : '' ?>><?= View::e((string) ($client['company'] ?? ('Cliente #' . $clientId))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Responsável</label>
      <select name="assigned_user_id" class="mt-1 tr-input">
        <option value="0">Todos</option>
        <?php foreach ($users as $user): ?>
          <?php $userId = (int) ($user['id'] ?? 0); ?>
          <option value="<?= $userId ?>" <?= (int) ($filters['assigned_user_id'] ?? 0) === $userId ? 'selected' : '' ?>><?= View::e((string) ($user['name'] ?? 'Usuário #' . $userId)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Cobrança</label>
      <select name="billable" class="mt-1 tr-input">
        <option value="">Todos</option>
        <option value="1" <?= (string) ($filters['billable'] ?? '') === '1' ? 'selected' : '' ?>>Faturáveis</option>
        <option value="0" <?= (string) ($filters['billable'] ?? '') === '0' ? 'selected' : '' ?>>Não faturáveis</option>
      </select>
    </div>
    <div class="flex items-end justify-end">
      <button class="tr-btn" type="submit">Atualizar relatório</button>
    </div>
  </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Em aberto</div>
    <div class="text-2xl font-semibold mt-2"><?= (int) ($totals['aberto'] ?? 0) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Em andamento</div>
    <div class="text-2xl font-semibold mt-2"><?= (int) ($totals['em_andamento'] ?? 0) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Concluídos</div>
    <div class="text-2xl font-semibold mt-2"><?= (int) ($totals['concluido'] ?? 0) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Faturados</div>
    <div class="text-2xl font-semibold mt-2"><?= (int) ($totals['faturado'] ?? 0) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Valor faturado</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format((float) ($totals['valor_faturado'] ?? 0), 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Tempo médio</div>
    <div class="text-2xl font-semibold mt-2"><?= number_format((float) ($totals['tempo_medio_horas'] ?? 0), 2, ',', '.') ?> h</div>
  </div>
</div>

<?php if ($reportTruncated): ?>
  <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm">
    Este relatório encontrou <?= $reportTotal ?> OS para os filtros informados, mas está exibindo apenas as <?= count($rows) ?> mais recentes por limite de segurança de carga. Refine os filtros (período, cliente ou status) para ver o restante.
  </div>
<?php endif; ?>

<div class="mt-6 tr-card overflow-hidden">
  <div class="p-6 font-semibold">Ordens incluídas no relatório
    <span class="font-normal text-slate-600">(<?= count($rows) ?> de <?= $reportTotal ?>)</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">OS</th>
          <th class="text-left py-3 px-4">Serviço</th>
          <th class="text-left py-3 px-4">Cliente</th>
          <th class="text-left py-3 px-4">Responsável</th>
          <th class="text-left py-3 px-4">Tipo</th>
          <th class="text-left py-3 px-4">Status</th>
          <th class="text-left py-3 px-4">Aberta em</th>
          <th class="text-left py-3 px-4">Conclusão</th>
          <th class="text-left py-3 px-4">Valor final</th>
          <th class="text-left py-3 px-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="10">Nenhuma OS encontrada para os filtros informados.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $id = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $clientLabel = trim((string) ($row['client_company'] ?? '')) !== '' ? (string) $row['client_company'] : (trim((string) ($row['client_name'] ?? '')) !== '' ? (string) $row['client_name'] : 'Cliente não vinculado');
            $receivableId = (int) ($row['financial_receivable_id'] ?? 0);
          ?>
          <tr class="border-t">
            <td class="px-4 py-3 font-semibold">
              <a class="text-traxterAccent" href="<?= View::e($base . '/ordens-servico/' . $id) ?>"><?= View::e((string) ($row['numero_os'] ?? '')) ?></a>
            </td>
            <td class="px-4 py-3"><?= View::e((string) ($row['service_name'] ?? '')) ?></td>
            <td class="px-4 py-3"><?= View::e($clientLabel) ?></td>
            <td class="px-4 py-3"><?= View::e((string) ($row['assigned_user_name'] ?? 'Não definido')) ?></td>
            <td class="px-4 py-3"><?= View::e(ServiceOrderType::label((string) ($row['type'] ?? ''))) ?></td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold <?= View::e(ServiceOrderStatus::badgeClass($status)) ?>"><?= View::e(ServiceOrderStatus::label($status)) ?></span>
            </td>
            <td class="px-4 py-3"><?= View::e((string) ($row['opened_at'] ?? '')) ?></td>
            <td class="px-4 py-3"><?= View::e((string) ($row['completed_at'] ?? '—')) ?></td>
            <td class="px-4 py-3"><?= (int) ($row['billable'] ?? 0) === 1 ? 'R$ ' . number_format((float) ($row['final_amount'] ?? 0), 2, ',', '.') : 'Não faturável' ?></td>
            <td class="px-4 py-3"><?php require __DIR__ . '/_actions.php'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
