<?php
use App\Core\UI;
use App\Core\View;
use App\Services\InstallmentCharges;

$title = 'Relatórios financeiros';
$data = is_array($data ?? null) ? $data : [];
$filters = is_array($filters ?? null) ? $filters : [];

$legacy = is_array($data['legacy'] ?? null) ? $data['legacy'] : [];
$legacyDel = is_array($legacy['delinquency'] ?? null) ? $legacy['delinquency'] : [];
$metrics = is_array($data['metrics'] ?? null) ? $data['metrics'] : [];
$totals = is_array($metrics['totals'] ?? null) ? $metrics['totals'] : [];
$m = is_array($metrics['metrics'] ?? null) ? $metrics['metrics'] : [];
$cashflow = is_array($data['cashflow'] ?? null) ? $data['cashflow'] : [];
$installments = is_array($data['installments']['rows'] ?? null) ? $data['installments']['rows'] : [];
$payments = is_array($data['payments']['rows'] ?? null) ? $data['payments']['rows'] : [];
$installmentsPage = (int) ($data['installments']['page'] ?? 1);
$paymentsPage = (int) ($data['payments']['page'] ?? 1);
$perPage = (int) ($data['installments']['per_page'] ?? 30);
$perPage = max(1, $perPage);
$installmentsTotal = (int) ($data['installments']['total'] ?? 0);
$installmentsTotal = max($installmentsTotal, count($installments));

$from = (string) ($filters['from'] ?? '');
$to = (string) ($filters['to'] ?? '');
$projectId = (int) ($filters['project_id'] ?? 0);
$clientId = (int) ($filters['client_id'] ?? 0);
$status = (string) ($filters['status'] ?? '');
$sort = (string) ($filters['sort'] ?? 'due_date');
$direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

$fromDisplay = $from;
$toDisplay = $to;
$delinqRate = (float) ($m['delinquency_rate'] ?? 0);
$installmentsCount = count($installments);
$paymentsCount = count($payments);
$today = date('Y-m-d');

$statusOptions = [
  '' => 'Todos',
  'pendente' => 'Pendente',
  'reaberto' => 'Reaberto',
  'vencida' => 'Vencida',
  'pago' => 'Paga',
  'adiantado' => 'Adiantada',
  'cancelado' => 'Cancelada',
];
$sortOptions = [
  'due_date' => 'Vencimento',
  'installment_no' => 'Número da parcela',
  'project' => 'Projeto',
  'client' => 'Cliente',
  'status' => 'Status',
  'amount' => 'Valor',
  'paid_amount' => 'Valor pago',
  'open_amount' => 'Saldo em aberto',
];
$queryFilters = array_filter($filters, static function ($value): bool {
  if (is_int($value)) {
    return $value > 0;
  }
  return trim((string) $value) !== '';
});
$pageLink = static fn (array $extra) => $base . '/relatorios/financeiro?' . http_build_query(array_merge($queryFilters, $extra));

$maxBucket = 0.0;
foreach ($cashflow as $r) {
  $maxBucket = max($maxBucket, (float) ($r['open_amount'] ?? 0));
}
?>

<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
  <div>
    <div class="text-2xl font-semibold">Relatórios financeiros</div>
    <div class="text-slate-600 mt-1">Consulta consolidada de parcelas, recebimentos e inadimplência.</div>
  </div>
  <div class="text-sm text-slate-600">
    Parcelas listadas: <span class="font-semibold"><?= (int) $installmentsCount ?></span>
    | Pagamentos listados: <span class="font-semibold"><?= (int) $paymentsCount ?></span>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/relatorios/financeiro') ?>" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <div>
      <label class="tr-label">De</label>
      <input name="from" type="date" class="mt-1 tr-input" value="<?= View::e($fromDisplay) ?>" placeholder="YYYY-MM-DD">
    </div>
    <div>
      <label class="tr-label">Até</label>
      <input name="to" type="date" class="mt-1 tr-input" value="<?= View::e($toDisplay) ?>" placeholder="YYYY-MM-DD">
    </div>
    <div>
      <label class="tr-label">Projeto ID</label>
      <input name="project_id" class="mt-1 tr-input" value="<?= $projectId > 0 ? (int) $projectId : '' ?>" placeholder="ex: 12">
    </div>
    <div>
      <label class="tr-label">Cliente ID</label>
      <input name="client_id" class="mt-1 tr-input" value="<?= $clientId > 0 ? (int) $clientId : '' ?>" placeholder="ex: 5">
    </div>
    <div>
      <label class="tr-label">Status</label>
      <select name="status" class="mt-1 tr-input">
        <?php foreach ($statusOptions as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Ordenar parcelas por</label>
      <select name="sort" class="mt-1 tr-input">
        <?php foreach ($sortOptions as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Direção</label>
      <select name="direction" class="mt-1 tr-input">
        <option value="asc" <?= $direction === 'asc' ? 'selected' : '' ?>>Crescente</option>
        <option value="desc" <?= $direction === 'desc' ? 'selected' : '' ?>>Decrescente</option>
      </select>
    </div>
    <div class="flex items-end gap-2">
      <button class="tr-icon-btn tr-icon-btn--accent" type="submit" title="Atualizar relatório" aria-label="Atualizar relatório">
        <?= UI::icon('refresh', 'w-5 h-5') ?>
        <span class="sr-only">Atualizar relatório</span>
      </button>
      <a class="tr-icon-btn text-rose-600 hover:bg-rose-50" title="Exportar PDF" aria-label="Exportar PDF" href="<?= View::e($base . '/relatorios/financeiro/export/pdf?' . http_build_query($queryFilters)) ?>">
        <?= UI::icon('pdf', 'w-5 h-5') ?>
        <span class="sr-only">Exportar PDF</span>
      </a>
      <a class="tr-icon-btn text-emerald-700 hover:bg-emerald-50" title="Exportar Excel" aria-label="Exportar Excel" href="<?= View::e($base . '/relatorios/financeiro/export/excel?' . http_build_query($queryFilters)) ?>">
        <?= UI::icon('excel', 'w-5 h-5') ?>
        <span class="sr-only">Exportar Excel</span>
      </a>
    </div>
  </form>
  <div class="mt-4 text-sm text-slate-600">
    Use os filtros para refinar o período e exporte o resultado consolidado em PDF ou Excel (.xlsx).
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">A receber</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format((float) ($totals['receivable'] ?? 0), 2, ',', '.') ?></div>
    <div class="text-sm text-slate-600 mt-2">Saldo ainda em aberto no período filtrado.</div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Recebido</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format((float) ($totals['received'] ?? 0), 2, ',', '.') ?></div>
    <div class="text-sm text-slate-600 mt-2">Pagamentos registrados dentro do período informado.</div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Vencido</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format((float) ($totals['overdue'] ?? 0), 2, ',', '.') ?></div>
    <div class="text-sm text-slate-600 mt-2">Taxa de inadimplência: <span class="font-semibold"><?= number_format($delinqRate * 100, 2, ',', '.') ?>%</span></div>
  </div>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="p-6 font-semibold">Fluxo de caixa em aberto por mês</div>
  <div class="px-6 pb-2">
    <svg viewBox="0 0 600 160" class="w-full h-40">
      <?php
        $bars = array_values($cashflow);
        $count = max(1, count($bars));
        $gap = 8;
        $w = (600 - ($gap * ($count + 1))) / $count;
        $x = $gap;
      ?>
      <?php foreach ($bars as $r): ?>
        <?php
          $val = (float) ($r['open_amount'] ?? 0);
          $h = $maxBucket > 0 ? (int) round(($val / $maxBucket) * 120) : 0;
          $y = 140 - $h;
          $bucket = (string) ($r['bucket'] ?? '');
          $label = $bucket !== '' ? date('m/Y', strtotime($bucket)) : '—';
        ?>
        <rect x="<?= (int) $x ?>" y="<?= (int) $y ?>" width="<?= (int) $w ?>" height="<?= (int) $h ?>" fill="#ee6c4d"></rect>
        <text x="<?= (int) ($x + ($w / 2)) ?>" y="155" font-size="10" text-anchor="middle" fill="#334155"><?= View::e($label) ?></text>
        <?php $x += $w + $gap; ?>
      <?php endforeach; ?>
      <?php if (count($bars) === 0): ?>
        <text x="10" y="90" font-size="12" fill="#64748b">Sem dados no período.</text>
      <?php endif; ?>
    </svg>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">Mês</th>
          <th class="text-left py-3 px-4">Aberto</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($cashflow) === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="2">Sem dados no período.</td></tr>
        <?php endif; ?>
        <?php foreach ($cashflow as $r): ?>
          <?php
            $bucket = (string) ($r['bucket'] ?? '');
            $label = $bucket !== '' ? date('m/Y', strtotime($bucket)) : '—';
            $val = (float) ($r['open_amount'] ?? 0);
          ?>
          <tr class="border-t">
            <td class="px-4 py-3 font-semibold"><?= View::e($label) ?></td>
            <td class="px-4 py-3">R$ <?= number_format($val, 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="p-6 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <div class="font-semibold">Parcelas do relatório</div>
      <div class="text-sm text-slate-600">Tabela somente leitura com os dados financeiros consolidados.</div>
    </div>
    <div class="text-sm text-slate-600">
      Ordenação atual: <span class="font-semibold"><?= View::e($sortOptions[$sort] ?? 'Vencimento') ?></span>
      (<?= $direction === 'desc' ? 'decrescente' : 'crescente' ?>)
    </div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">ID</th>
          <th class="text-left py-3 px-4">Projeto ID</th>
          <th class="text-left py-3 px-4">Proposta ID</th>
          <th class="text-left py-3 px-4">Venc.</th>
          <th class="text-left py-3 px-4">Nº</th>
          <th class="text-left py-3 px-4">Projeto</th>
          <th class="text-left py-3 px-4">Cliente</th>
          <th class="text-left py-3 px-4">Status</th>
          <th class="text-left py-3 px-4">Valor</th>
          <th class="text-left py-3 px-4">Pago</th>
          <th class="text-left py-3 px-4">Aberto</th>
          <th class="text-left py-3 px-4">Pago em</th>
          <th class="text-left py-3 px-4">Cancelado em</th>
          <th class="text-left py-3 px-4">Multa</th>
          <th class="text-left py-3 px-4">Juros</th>
          <th class="text-left py-3 px-4">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($installmentsCount === 0): ?>
          <tr>
            <td class="px-4 py-6 text-slate-600" colspan="16">
              Sem parcelas para os filtros informados.
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($installments as $r): ?>
          <?php
            $due = (string) ($r['due_date'] ?? '');
            $dueTxt = $due !== '' ? date('d/m/Y', strtotime($due)) : '—';
            $open = (float) ($r['open_amount'] ?? 0);
            $st = (string) ($r['status'] ?? '');
            $paidAt = (string) ($r['paid_at'] ?? '');
            $paidTxt = $paidAt !== '' ? date('d/m/Y H:i', strtotime($paidAt)) : '—';
            $canceledAt = (string) ($r['canceled_at'] ?? '');
            $canceledTxt = $canceledAt !== '' ? date('d/m/Y H:i', strtotime($canceledAt)) : '—';
            if (($st === 'pendente' || $st === 'reaberto') && $due !== '' && $due < $today) {
              $st = 'vencida';
            } elseif ($st === 'pago' && $paidAt !== '' && $due !== '' && substr($paidAt, 0, 10) < $due) {
              $st = 'adiantada';
            }
            $charges = InstallmentCharges::compute($open, $due, $today);
            $penalty = (float) ($charges['penalty'] ?? 0);
            $interest = (float) ($charges['interest'] ?? 0);
            $total = (float) ($charges['total'] ?? $open);
            $clientCompany = trim((string) ($r['client_company'] ?? ''));
          ?>
          <tr class="border-t">
            <td class="px-4 py-3 whitespace-nowrap"><?= (int) ($r['id'] ?? 0) ?></td>
            <td class="px-4 py-3 whitespace-nowrap"><?= (int) ($r['project_id'] ?? 0) ?></td>
            <td class="px-4 py-3 whitespace-nowrap"><?= (int) ($r['proposal_id'] ?? 0) ?></td>
            <td class="px-4 py-3 whitespace-nowrap"><?= View::e($dueTxt) ?></td>
            <td class="px-4 py-3 whitespace-nowrap"><?= (int) ($r['installment_no'] ?? 0) ?></td>
            <td class="px-4 py-3"><?= View::e((string) ($r['project_title'] ?? '')) ?></td>
            <td class="px-4 py-3"><?= View::e($clientCompany !== '' ? $clientCompany : '—') ?></td>
            <td class="px-4 py-3"><?= View::e($st) ?></td>
            <td class="px-4 py-3">R$ <?= number_format((float) ($r['amount'] ?? 0), 2, ',', '.') ?></td>
            <td class="px-4 py-3">R$ <?= number_format((float) ($r['paid_amount'] ?? 0), 2, ',', '.') ?></td>
            <td class="px-4 py-3">R$ <?= number_format($open, 2, ',', '.') ?></td>
            <td class="px-4 py-3 whitespace-nowrap"><?= View::e($paidTxt) ?></td>
            <td class="px-4 py-3 whitespace-nowrap"><?= View::e($canceledTxt) ?></td>
            <td class="px-4 py-3">R$ <?= number_format($penalty, 2, ',', '.') ?></td>
            <td class="px-4 py-3">R$ <?= number_format($interest, 2, ',', '.') ?></td>
            <td class="px-4 py-3 font-semibold">R$ <?= number_format($total, 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="flex items-center justify-between px-6 py-4 border-t bg-slate-50 text-sm">
    <div class="text-slate-600">Página: <span class="font-semibold"><?= (int) $installmentsPage ?></span></div>
    <div class="flex items-center gap-2">
      <?php if ($installmentsPage > 1): ?>
        <a class="tr-btn" href="<?= View::e($pageLink(['ins_page' => $installmentsPage - 1])) ?>">Anterior</a>
      <?php endif; ?>
      <?php if (($installmentsPage * $perPage) < $installmentsTotal): ?>
        <a class="tr-btn" href="<?= View::e($pageLink(['ins_page' => $installmentsPage + 1])) ?>">Próxima</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="p-6 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <div class="font-semibold">Pagamentos do período</div>
      <div class="text-sm text-slate-600">Amostra dos recebimentos registrados para os filtros aplicados.</div>
    </div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">Pago em</th>
          <th class="text-left py-3 px-4">Projeto</th>
          <th class="text-left py-3 px-4">Cliente</th>
          <th class="text-left py-3 px-4">Método</th>
          <th class="text-left py-3 px-4">Valor</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($paymentsCount === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="5">Sem pagamentos para os filtros informados.</td></tr>
        <?php endif; ?>
        <?php foreach ($payments as $r): ?>
          <?php
            $paidAt = (string) ($r['paid_at'] ?? '');
            $paidTxt = $paidAt !== '' ? date('d/m/Y H:i', strtotime($paidAt)) : '—';
            $amt = (float) ($r['amount'] ?? 0);
            $clientCompany = trim((string) ($r['client_company'] ?? ''));
          ?>
          <tr class="border-t">
            <td class="px-4 py-3 whitespace-nowrap"><?= View::e($paidTxt) ?></td>
            <td class="px-4 py-3"><?= View::e((string) ($r['project_title'] ?? '')) ?></td>
            <td class="px-4 py-3"><?= View::e($clientCompany !== '' ? $clientCompany : '—') ?></td>
            <td class="px-4 py-3"><?= View::e((string) ($r['method'] ?? '')) ?></td>
            <td class="px-4 py-3">R$ <?= number_format($amt, 2, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="flex items-center justify-between px-6 py-4 border-t bg-slate-50 text-sm">
    <div class="text-slate-600">Página: <span class="font-semibold"><?= (int) $paymentsPage ?></span></div>
    <div class="flex items-center gap-2">
      <?php if ($paymentsPage > 1): ?>
        <a class="tr-btn" href="<?= View::e($pageLink(['pay_page' => $paymentsPage - 1])) ?>">Anterior</a>
      <?php endif; ?>
      <?php if ($paymentsCount === $perPage): ?>
        <a class="tr-btn" href="<?= View::e($pageLink(['pay_page' => $paymentsPage + 1])) ?>">Próxima</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <div class="text-sm text-slate-600">Legado</div>
  <div class="mt-2 text-sm text-slate-700">Inadimplência (legado): R$ <?= number_format((float) ($legacyDel['total'] ?? 0), 2, ',', '.') ?> | Parcelas em atraso: <?= (int) ($legacyDel['count'] ?? 0) ?></div>
</div>
