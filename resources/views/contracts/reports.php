<?php
use App\Core\View;

$contracts = is_array($contracts ?? null) ? $contracts : [];
$summary = is_array($summary ?? null) ? $summary : [];
?>

<div>
  <div class="text-2xl font-semibold">Relatórios de Contratos</div>
  <div class="text-slate-600 mt-1">Acompanhe contratos vinculados a propostas, assinaturas pendentes e vigências.</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-6">
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Total</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['total_contracts'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Pendentes</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['pending_signature'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Assinados</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['signed'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Vigentes</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['active_contracts'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Prontos para vigência</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['ready_to_activate'] ?? 0) ?></div></div>
</div>

<div class="mt-6 tr-card p-6 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-slate-700">
      <tr>
        <th class="text-left py-2">Contrato</th>
        <th class="text-left py-2">Proposta</th>
        <th class="text-left py-2">Cliente</th>
        <th class="text-left py-2">Status</th>
        <th class="text-left py-2">Assinado em</th>
        <th class="text-left py-2">Vigência</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($contracts as $row): ?>
        <tr class="border-t">
          <td class="py-3 pr-3 font-semibold"><?= View::e((string) ($row['contract_number'] ?? '')) ?></td>
          <td class="py-3 pr-3">#<?= (int) ($row['proposal_id'] ?? 0) ?> · <?= View::e((string) ($row['proposal_title'] ?? '')) ?></td>
          <td class="py-3 pr-3"><?= View::e((string) (($row['client_company'] ?? '') !== '' ? $row['client_company'] : ($row['client_name'] ?? ''))) ?></td>
          <td class="py-3 pr-3"><span class="tr-badge"><?= View::e((string) ($row['status'] ?? '')) ?></span></td>
          <td class="py-3 pr-3"><?= !empty($row['signed_at']) ? View::e((string) $row['signed_at']) : '—' ?></td>
          <td class="py-3 pr-3"><?= !empty($row['effective_date']) ? View::e(date('d/m/Y', strtotime((string) $row['effective_date']))) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($contracts) === 0): ?>
        <tr><td class="py-4 text-slate-600" colspan="6">Nenhum contrato disponível para relatório.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
