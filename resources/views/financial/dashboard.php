<?php
use App\Core\UI;
use App\Core\View;

$title = 'Dashboard Financeiro';
$filters = is_array($filters ?? null) ? $filters : [];
$clients = is_array($clients ?? null) ? $clients : [];
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$totals = is_array($dashboard['totals'] ?? null) ? $dashboard['totals'] : [];
$projection = is_array($dashboard['projection'] ?? null) ? $dashboard['projection'] : [];
$topOverdueClients = is_array($dashboard['top_overdue_clients'] ?? null) ? $dashboard['top_overdue_clients'] : [];
$upcoming = is_array($dashboard['upcoming'] ?? null) ? $dashboard['upcoming'] : [];
$receiptsSeries = is_array($dashboard['receipts_series'] ?? null) ? $dashboard['receipts_series'] : [];

$totalReceivable = (float) ($totals['total_receivable'] ?? 0);
$totalOverdue = (float) ($totals['total_overdue'] ?? 0);
$totalReceivedMonth = (float) ($totals['total_received_month'] ?? 0);
$delinquencyRate = $totalReceivable > 0 ? round(($totalOverdue / $totalReceivable) * 100, 2) : 0.0;
$projectedTotal = 0.0;
foreach ($projection as $row) {
    $projectedTotal += (float) ($row['total'] ?? 0);
}
?>

<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
  <div>
    <div class="text-2xl font-semibold">Dashboard Financeiro</div>
    <div class="text-slate-600 mt-1">Indicadores de recebíveis, inadimplência, projeção e carteira por cliente.</div>
  </div>
  <div class="flex gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis') ?>">Recebíveis</a>
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/relatorios') ?>">Relatórios</a>
    <?php if (($canManage ?? false) === true): ?>
      <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/financeiro/recebiveis/novo') ?>" title="Nova conta a receber" aria-label="Nova conta a receber">
        <?= UI::icon('plus') ?>
        <span class="sr-only">Nova conta a receber</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/financeiro/dashboard') ?>" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
    <div>
      <label class="tr-label">Período inicial</label>
      <input class="mt-1 tr-input" type="date" name="from" value="<?= View::e((string) ($filters['from'] ?? date('Y-m-01'))) ?>">
    </div>
    <div>
      <label class="tr-label">Período final</label>
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
    <div class="md:col-span-2 flex items-end gap-2">
      <button class="tr-btn tr-btn--accent" type="submit">Aplicar filtros</button>
      <a class="tr-btn" href="<?= View::e($base . '/financeiro/dashboard') ?>">Limpar</a>
    </div>
  </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Total a receber</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format($totalReceivable, 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Total vencido</div>
    <div class="text-2xl font-semibold mt-2 text-red-700">R$ <?= number_format($totalOverdue, 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Recebido no mês</div>
    <div class="text-2xl font-semibold mt-2 text-emerald-700">R$ <?= number_format($totalReceivedMonth, 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Projeção mensal</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format($projectedTotal, 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Inadimplência</div>
    <div class="text-2xl font-semibold mt-2"><?= number_format($delinquencyRate, 2, ',', '.') ?>%</div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
  <div class="tr-card p-6">
    <div class="font-semibold">Projeção de recebimentos</div>
    <div class="text-sm text-slate-600 mt-1">Saldo previsto por competência.</div>
    <div class="mt-4" style="height: 260px">
      <canvas id="projectionChart"></canvas>
    </div>
  </div>
  <div class="tr-card p-6">
    <div class="font-semibold">Recebimentos realizados</div>
    <div class="text-sm text-slate-600 mt-1">Série de recebimentos efetivos por mês.</div>
    <div class="mt-4" style="height: 260px">
      <canvas id="receiptsChart"></canvas>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b">
      <div class="font-semibold">Ranking de clientes inadimplentes</div>
      <div class="text-sm text-slate-600 mt-1">Clientes com maior exposição vencida.</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Cliente</th>
            <th class="text-left p-3">Total vencido</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topOverdueClients as $row): ?>
            <?php $name = trim((string) (($row['client_company'] ?? '') !== '' ? $row['client_company'] : ($row['client_name'] ?? '—'))); ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e($name) ?></td>
              <td class="p-3 font-semibold text-red-700">R$ <?= number_format((float) ($row['total'] ?? 0), 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($topOverdueClients) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="2">Nenhum cliente inadimplente para os filtros aplicados.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tr-card overflow-hidden">
    <div class="p-6 border-b">
      <div class="font-semibold">Próximos vencimentos</div>
      <div class="text-sm text-slate-600 mt-1">Títulos com vencimento próximo para ação preventiva.</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-700">
          <tr>
            <th class="text-left p-3">Título</th>
            <th class="text-left p-3">Cliente</th>
            <th class="text-left p-3">Vencimento</th>
            <th class="text-left p-3">Saldo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcoming as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?= View::e((string) ($row['title'] ?? '')) ?></td>
              <td class="p-3"><?= View::e((string) ($row['client_company'] ?? '—')) ?></td>
              <td class="p-3 whitespace-nowrap"><?= View::e((string) ($row['due_date'] ?? '')) ?></td>
              <td class="p-3 font-semibold">R$ <?= number_format((float) ($row['remaining_amount'] ?? 0), 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($upcoming) === 0): ?>
            <tr><td class="p-6 text-slate-600" colspan="4">Nenhum vencimento próximo encontrado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  (function () {
    const projection = <?= json_encode($projection, JSON_UNESCAPED_UNICODE) ?>;
    const receipts = <?= json_encode($receiptsSeries, JSON_UNESCAPED_UNICODE) ?>;
    let chartLib = null;
    let projectionChart = null;
    let receiptsChart = null;

    function loadChartJs() {
      if (chartLib) return Promise.resolve(chartLib);
      return new Promise(function (resolve, reject) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        script.onload = function () {
          chartLib = window.Chart || null;
          resolve(chartLib);
        };
        script.onerror = reject;
        document.head.appendChild(script);
      });
    }

    function monthLabel(value) {
      if (!value) return '—';
      const date = new Date(String(value) + 'T00:00:00');
      return date.toLocaleDateString('pt-BR', { month: '2-digit', year: 'numeric' });
    }

    function amounts(rows) {
      return rows.map(function (item) { return Number(item && item.total ? item.total : 0); });
    }

    function labels(rows) {
      return rows.map(function (item) { return monthLabel(item && item.bucket ? item.bucket : ''); });
    }

    loadChartJs().then(function (Chart) {
      if (!Chart) return;

      projectionChart = new Chart(document.getElementById('projectionChart'), {
        type: 'bar',
        data: {
          labels: labels(projection),
          datasets: [{
            label: 'Saldo projetado',
            data: amounts(projection),
            backgroundColor: '#ee6c4d'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } }
        }
      });

      receiptsChart = new Chart(document.getElementById('receiptsChart'), {
        type: 'line',
        data: {
          labels: labels(receipts),
          datasets: [{
            label: 'Recebido',
            data: amounts(receipts),
            borderColor: '#16a34a',
            backgroundColor: 'rgba(22,163,74,0.12)',
            fill: true,
            tension: 0.25
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }).catch(function () {
      if (window.trToast) window.trToast('warning', 'Nao foi possivel carregar os graficos do dashboard financeiro.');
    });
  })();
</script>
