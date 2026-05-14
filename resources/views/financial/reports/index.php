<?php
use App\Core\UI;
use App\Core\View;

$title = 'Relatorios Financeiros Enterprise';
$filters = is_array($filters ?? null) ? $filters : [];
$reports = is_array($reports ?? null) ? $reports : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];

$receivables = is_array($reports['receivables'] ?? null) ? $reports['receivables'] : [];
$delinquency = is_array($reports['delinquency'] ?? null) ? $reports['delinquency'] : [];
$cashflow = is_array($reports['projected_cashflow'] ?? null) ? $reports['projected_cashflow'] : [];
$receiptsByPeriod = is_array($reports['receipts_by_period'] ?? null) ? $reports['receipts_by_period'] : [];
$receiptsByClient = is_array($reports['receipts_by_client'] ?? null) ? $reports['receipts_by_client'] : [];
$projectPerformance = is_array($reports['project_performance'] ?? null) ? $reports['project_performance'] : [];
$aging = is_array($reports['aging_list'] ?? null) ? $reports['aging_list'] : [];
$dre = is_array($reports['dre_simplified'] ?? null) ? $reports['dre_simplified'] : [];
$queryFilters = array_filter($filters, static function ($value): bool {
    if (is_int($value)) {
        return $value > 0;
    }
    return trim((string) $value) !== '';
});
?>

<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
  <div>
    <div class="text-2xl font-semibold">Relatorios Financeiros Enterprise</div>
    <div class="text-slate-600 mt-1">Visao consolidada da carteira, inadimplencia, aging, recebimentos e desempenho por projeto.</div>
  </div>
  <div class="flex gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/dashboard') ?>">Dashboard</a>
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis') ?>">Recebiveis</a>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/financeiro/relatorios') ?>" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
    <div>
      <label class="tr-label">Periodo inicial</label>
      <input class="mt-1 tr-input" type="date" name="from" value="<?= View::e((string) ($filters['from'] ?? date('Y-m-01'))) ?>">
    </div>
    <div>
      <label class="tr-label">Periodo final</label>
      <input class="mt-1 tr-input" type="date" name="to" value="<?= View::e((string) ($filters['to'] ?? date('Y-m-t'))) ?>">
    </div>
    <div>
      <label class="tr-label">Cliente</label>
      <select class="mt-1 tr-input" name="client_id">
        <option value="0">Todos</option>
        <?php foreach ($clients as $client): ?>
          <?php $id = (int) ($client['id'] ?? 0); $name = trim((string) (($client['company'] ?? '') !== '' ? $client['company'] : ($client['name'] ?? 'Cliente #' . $id))); ?>
          <option value="<?= $id ?>" <?= (int) ($filters['client_id'] ?? 0) === $id ? 'selected' : '' ?>><?= View::e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Projeto</label>
      <select class="mt-1 tr-input" name="project_id">
        <option value="0">Todos</option>
        <?php foreach ($projects as $project): ?>
          <?php $id = (int) ($project['id'] ?? 0); ?>
          <option value="<?= $id ?>" <?= (int) ($filters['project_id'] ?? 0) === $id ? 'selected' : '' ?>><?= View::e((string) ($project['title'] ?? 'Projeto #' . $id)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-end gap-2">
      <button class="tr-btn tr-btn--accent" type="submit">Aplicar</button>
      <a class="tr-icon-btn text-emerald-700" href="<?= View::e($base . '/financeiro/relatorios/export/excel?' . http_build_query($queryFilters)) ?>" title="Exportar Excel" aria-label="Exportar Excel">
        <?= UI::icon('excel') ?>
        <span class="sr-only">Exportar Excel</span>
      </a>
      <a class="tr-icon-btn text-rose-600" href="<?= View::e($base . '/financeiro/relatorios/export/pdf?' . http_build_query($queryFilters)) ?>" title="Exportar PDF" aria-label="Exportar PDF">
        <?= UI::icon('pdf') ?>
        <span class="sr-only">Exportar PDF</span>
      </a>
      <a class="tr-icon-btn" href="<?= View::e($base . '/financeiro/relatorios/export/csv?' . http_build_query($queryFilters)) ?>" title="Exportar CSV" aria-label="Exportar CSV">
        <?= UI::icon('save') ?>
        <span class="sr-only">Exportar CSV</span>
      </a>
    </div>
  </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Titulos no relatorio</div>
    <div class="text-2xl font-semibold mt-2"><?= count($receivables) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Clientes inadimplentes</div>
    <div class="text-2xl font-semibold mt-2"><?= count($delinquency) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Receita bruta</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format((float) ($dre['gross_revenue'] ?? 0), 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Resultado financeiro</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format((float) ($dre['financial_result'] ?? 0), 2, ',', '.') ?></div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b font-semibold">Contas a receber</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Titulo</th>
            <th class="text-left p-3">Cliente</th>
            <th class="text-left p-3">Projeto</th>
            <th class="text-left p-3">Venc.</th>
            <th class="text-left p-3">Saldo</th>
            <th class="text-left p-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receivables as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e((string) ($row['title'] ?? '')) ?></td>
              <td class="p-3"><?= View::e((string) ($row['client_company'] ?? '—')) ?></td>
              <td class="p-3"><?= View::e((string) ($row['project_title'] ?? '—')) ?></td>
              <td class="p-3 whitespace-nowrap"><?= View::e((string) ($row['due_date'] ?? '')) ?></td>
              <td class="p-3 font-semibold">R$ <?= number_format((float) ($row['remaining_amount'] ?? 0), 2, ',', '.') ?></td>
              <td class="p-3"><?= View::e((string) ($row['status'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($receivables) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="6">Sem contas a receber no filtro informado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b font-semibold">Inadimplencia por cliente</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Cliente</th>
            <th class="text-left p-3">Titulos</th>
            <th class="text-left p-3">Total vencido</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($delinquency as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e((string) ($row['client_company'] ?? '—')) ?></td>
              <td class="p-3"><?= (int) ($row['qty'] ?? 0) ?></td>
              <td class="p-3 font-semibold text-red-700">R$ <?= number_format((float) ($row['total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($delinquency) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="3">Sem inadimplencia para os filtros aplicados.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b font-semibold">Fluxo de caixa projetado</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Competencia</th>
            <th class="text-left p-3">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cashflow as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e((string) ($row['bucket'] ?? '')) ?></td>
              <td class="p-3 font-semibold">R$ <?= number_format((float) ($row['total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($cashflow) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="2">Sem fluxo projetado para o periodo.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b font-semibold">Recebimentos por periodo</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Data</th>
            <th class="text-left p-3">Total recebido</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receiptsByPeriod as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e((string) ($row['period'] ?? '')) ?></td>
              <td class="p-3 font-semibold">R$ <?= number_format((float) ($row['total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($receiptsByPeriod) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="2">Sem recebimentos no periodo informado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b font-semibold">Recebimentos por cliente</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Cliente</th>
            <th class="text-left p-3">Total recebido</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receiptsByClient as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e((string) ($row['client_company'] ?? '—')) ?></td>
              <td class="p-3 font-semibold">R$ <?= number_format((float) ($row['total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($receiptsByClient) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="2">Sem recebimentos por cliente no periodo.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b font-semibold">Desempenho financeiro por projeto</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Projeto</th>
            <th class="text-left p-3">Recebido</th>
            <th class="text-left p-3">Em aberto</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projectPerformance as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e((string) ($row['project_title'] ?? '—')) ?></td>
              <td class="p-3 font-semibold text-emerald-700">R$ <?= number_format((float) ($row['received'] ?? 0), 2, ',', '.') ?></td>
              <td class="p-3 font-semibold text-amber-700">R$ <?= number_format((float) ($row['open_total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($projectPerformance) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="3">Sem desempenho consolidado para o filtro informado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
  <div class="tr-card p-6">
    <div class="font-semibold">Aging list</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm">
      <div class="rounded-lg border border-slate-200 p-4">
        <div class="text-slate-600">Atual</div>
        <div class="text-xl font-semibold mt-2">R$ <?= number_format((float) ($aging['current_bucket'] ?? 0), 2, ',', '.') ?></div>
      </div>
      <div class="rounded-lg border border-slate-200 p-4">
        <div class="text-slate-600">1 a 30 dias</div>
        <div class="text-xl font-semibold mt-2">R$ <?= number_format((float) ($aging['bucket_30'] ?? 0), 2, ',', '.') ?></div>
      </div>
      <div class="rounded-lg border border-slate-200 p-4">
        <div class="text-slate-600">31 a 60 dias</div>
        <div class="text-xl font-semibold mt-2">R$ <?= number_format((float) ($aging['bucket_60'] ?? 0), 2, ',', '.') ?></div>
      </div>
      <div class="rounded-lg border border-slate-200 p-4">
        <div class="text-slate-600">61 a 90 dias</div>
        <div class="text-xl font-semibold mt-2">R$ <?= number_format((float) ($aging['bucket_90'] ?? 0), 2, ',', '.') ?></div>
      </div>
      <div class="rounded-lg border border-slate-200 p-4 md:col-span-2">
        <div class="text-slate-600">Acima de 90 dias</div>
        <div class="text-xl font-semibold mt-2 text-red-700">R$ <?= number_format((float) ($aging['bucket_90_plus'] ?? 0), 2, ',', '.') ?></div>
      </div>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">DRE simplificado</div>
    <div class="grid grid-cols-1 gap-4 mt-4 text-sm">
      <div class="rounded-lg border border-slate-200 p-4">
        <div class="text-slate-600">Receita bruta</div>
        <div class="text-xl font-semibold mt-2">R$ <?= number_format((float) ($dre['gross_revenue'] ?? 0), 2, ',', '.') ?></div>
      </div>
      <div class="rounded-lg border border-slate-200 p-4">
        <div class="text-slate-600">Descontos</div>
        <div class="text-xl font-semibold mt-2 text-amber-700">R$ <?= number_format((float) ($dre['discounts'] ?? 0), 2, ',', '.') ?></div>
      </div>
      <div class="rounded-lg border border-slate-200 p-4">
        <div class="text-slate-600">Resultado financeiro</div>
        <div class="text-xl font-semibold mt-2 text-emerald-700">R$ <?= number_format((float) ($dre['financial_result'] ?? 0), 2, ',', '.') ?></div>
      </div>
    </div>
  </div>
</div>
