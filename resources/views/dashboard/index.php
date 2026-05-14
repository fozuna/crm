<?php
use App\Core\View;

$title = 'Dashboard';
$clients = is_array($clients ?? null) ? $clients : [];
$fromDefault = date('Y-m-01');
$toDefault = date('Y-m-t');
?>

<div class="flex items-start justify-between">
  <div>
    <div class="text-2xl font-semibold">Dashboard</div>
    <div class="text-slate-600 mt-1">Indicadores e gráficos financeiros</div>
  </div>
  <div class="text-slate-600"><?= View::e($userName) ?></div>
</div>

<div class="mt-6 tr-card p-6">
  <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 w-full">
      <div>
        <label class="tr-label">De</label>
        <input id="dashFrom" type="date" class="mt-1 tr-input" value="<?= View::e($fromDefault) ?>">
      </div>
      <div>
        <label class="tr-label">Até</label>
        <input id="dashTo" type="date" class="mt-1 tr-input" value="<?= View::e($toDefault) ?>">
      </div>
      <div>
        <label class="tr-label">Cliente</label>
        <select id="dashClient" class="mt-1 tr-input">
          <option value="0">Todos</option>
          <?php foreach ($clients as $c): ?>
            <?php $id = (int)($c['id'] ?? 0); $label = trim((string)($c['company'] ?? '')); ?>
            <?php if ($id > 0): ?>
              <option value="<?= (int)$id ?>"><?= View::e($label !== '' ? $label : ('Cliente #' . $id)) ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Status</label>
        <select id="dashStatus" class="mt-1 tr-input">
          <option value="">Todos</option>
          <option value="pendente">pendente</option>
          <option value="reaberto">reaberto</option>
          <option value="vencida">vencida</option>
          <option value="pago">paga</option>
          <option value="adiantado">adiantada</option>
          <option value="cancelado">cancelada</option>
        </select>
      </div>
    </div>
    <div class="flex gap-2">
      <a id="dashReportLink" class="tr-btn" href="<?= View::e($base . '/relatorios/financeiro') ?>">Ver relatório</a>
      <button id="dashApply" type="button" class="tr-btn tr-btn--accent">Aplicar</button>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Parcelas pagas</div>
    <div id="kpiPaid" class="text-3xl font-semibold mt-2">—</div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Parcelas pendentes</div>
    <div id="kpiPending" class="text-3xl font-semibold mt-2">—</div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Parcelas vencidas</div>
    <div id="kpiOverdueCount" class="text-3xl font-semibold mt-2">—</div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Fluxo de caixa (acum.)</div>
    <div id="kpiCash" class="text-3xl font-semibold mt-2">—</div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
  <div class="tr-card p-6">
    <div class="font-semibold">Receitas vs Despesas (mensal)</div>
    <div class="mt-4" style="height: 220px">
      <canvas id="chartBar"></canvas>
    </div>
  </div>
  <div class="tr-card p-6">
    <div class="font-semibold">Distribuição (métodos)</div>
    <div class="mt-4" style="height: 220px">
      <canvas id="chartPie"></canvas>
    </div>
  </div>
</div>

<div class="tr-card p-6 mt-4">
  <div class="font-semibold">Evolução do fluxo de caixa</div>
  <div class="mt-4" style="height: 220px">
    <canvas id="chartLine"></canvas>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Propostas aprovadas</div>
    <div class="text-3xl font-semibold mt-2"><?= (int)$stats['approved_proposals'] ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Projetos ativos</div>
    <div class="text-3xl font-semibold mt-2"><?= (int)$stats['active_projects'] ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Valores a receber</div>
    <div class="text-3xl font-semibold mt-2">R$ <?= number_format((float)$stats['receivable'], 2, ',', '.') ?></div>
  </div>
</div>

<script>
  (function(){
    const base = <?= json_encode((string)$base, JSON_UNESCAPED_UNICODE) ?>;
    const fromEl = document.getElementById('dashFrom');
    const toEl = document.getElementById('dashTo');
    const clientEl = document.getElementById('dashClient');
    const statusEl = document.getElementById('dashStatus');
    const applyBtn = document.getElementById('dashApply');
    const reportLink = document.getElementById('dashReportLink');

    const kpiPaid = document.getElementById('kpiPaid');
    const kpiPending = document.getElementById('kpiPending');
    const kpiOverdue = document.getElementById('kpiOverdueCount');
    const kpiCash = document.getElementById('kpiCash');

    const fmtBRL = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v||0));

    let ChartLib = null;
    let barChart = null;
    let pieChart = null;
    let lineChart = null;

    function qs(){
      const p = new URLSearchParams();
      const f = String((fromEl && fromEl.value) ? fromEl.value : '').trim();
      const t = String((toEl && toEl.value) ? toEl.value : '').trim();
      const c = String((clientEl && clientEl.value) ? clientEl.value : '0').trim();
      const s = String((statusEl && statusEl.value) ? statusEl.value : '').trim();
      if (f) p.set('from', f);
      if (t) p.set('to', t);
      if (c && c !== '0') p.set('client_id', c);
      if (s) p.set('status', s);
      return p;
    }

    async function loadChartJs(){
      if (ChartLib) return ChartLib;
      await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
      });
      ChartLib = window.Chart;
      return ChartLib;
    }

    async function fetchJson(url){
      const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json || json.ok !== true) throw new Error((json && (json.error||json.message)) ? (json.error||json.message) : 'Falha ao carregar');
      return json;
    }

    function monthLabel(b){
      if (!b) return '—';
      const d = new Date(String(b) + 'T00:00:00');
      return d.toLocaleDateString('pt-BR', { month: '2-digit', year: 'numeric' });
    }

    function ensureCharts(months, revenue, expense, cash, pieLabels, pieValues){
      const labels = months.map(monthLabel);
      const Chart = ChartLib;

      if (!barChart) {
        barChart = new Chart(document.getElementById('chartBar'), {
          type: 'bar',
          data: { labels, datasets: [
            { label: 'Receitas', data: revenue, backgroundColor: '#3B82F6' },
            { label: 'Despesas', data: expense, backgroundColor: '#EF4444' },
          ]},
          options: { responsive: true, maintainAspectRatio: false, scales: { y: { ticks: { callback: (v) => 'R$ ' + Number(v||0).toLocaleString('pt-BR') } } } }
        });
      } else {
        barChart.data.labels = labels;
        barChart.data.datasets[0].data = revenue;
        barChart.data.datasets[1].data = expense;
        barChart.update();
      }

      if (!pieChart) {
        pieChart = new Chart(document.getElementById('chartPie'), {
          type: 'doughnut',
          data: { labels: pieLabels, datasets: [{ data: pieValues, backgroundColor: ['#3B82F6','#22C55E','#F59E0B','#A855F7','#14B8A6','#EF4444'] }] },
          options: { responsive: true, maintainAspectRatio: false }
        });
      } else {
        pieChart.data.labels = pieLabels;
        pieChart.data.datasets[0].data = pieValues;
        pieChart.update();
      }

      if (!lineChart) {
        lineChart = new Chart(document.getElementById('chartLine'), {
          type: 'line',
          data: { labels, datasets: [{ label: 'Fluxo acumulado', data: cash, borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.15)', fill: true, tension: 0.25 }] },
          options: { responsive: true, maintainAspectRatio: false, scales: { y: { ticks: { callback: (v) => 'R$ ' + Number(v||0).toLocaleString('pt-BR') } } } }
        });
      } else {
        lineChart.data.labels = labels;
        lineChart.data.datasets[0].data = cash;
        lineChart.update();
      }
    }

    async function load(){
      const p = qs();
      if (reportLink) reportLink.href = base + '/relatorios/financeiro?' + p.toString();
      try {
        await loadChartJs();
        const url = base + '/api/dashboard/finance?' + p.toString();
        const json = await fetchJson(url);
        const data = json.data || {};
        const k = data.kpis || {};
        const s = data.series || {};
        const pie = data.pie || {};

        if (kpiPaid) kpiPaid.textContent = String((k && k.paid != null) ? k.paid : '0');
        if (kpiPending) kpiPending.textContent = String((k && k.pending != null) ? k.pending : '0');
        if (kpiOverdue) kpiOverdue.textContent = String((k && k.overdue != null) ? k.overdue : '0');

        const cash = Array.isArray(s.cashflow) && s.cashflow.length > 0 ? s.cashflow[s.cashflow.length - 1] : 0;
        if (kpiCash) kpiCash.textContent = fmtBRL(cash);

        ensureCharts(
          Array.isArray(s.months) ? s.months : [],
          Array.isArray(s.revenue) ? s.revenue : [],
          Array.isArray(s.expense) ? s.expense : [],
          Array.isArray(s.cashflow) ? s.cashflow : [],
          Array.isArray(pie.labels) ? pie.labels : [],
          Array.isArray(pie.values) ? pie.values : [],
        );
      } catch (e) {
        if (kpiPaid) kpiPaid.textContent = '—';
        if (kpiPending) kpiPending.textContent = '—';
        if (kpiOverdue) kpiOverdue.textContent = '—';
        if (kpiCash) kpiCash.textContent = '—';
      }
    }

    if (applyBtn) applyBtn.addEventListener('click', load);
    load();
  })();
</script>
