<?php
use App\Core\View;
use App\Core\UI;

$filters = is_array($filters ?? null) ? $filters : [];
$contracts = is_array($contracts ?? null) ? $contracts : [];
$summary = is_array($summary ?? null) ? $summary : [];
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Contratos</div>
    <div class="text-slate-600 mt-1">Gestão de contratos gerados a partir de propostas aprovadas.</div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-icon-btn" href="<?= View::e($base . '/contratos/templates') ?>" aria-label="Templates de contrato">
      <?= UI::icon('edit') ?>
      <span class="sr-only">Templates</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/contratos/relatorios') ?>" aria-label="Relatórios de contratos">
      <?= UI::icon('chart') ?>
      <span class="sr-only">Relatórios</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-6">
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Total</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['total_contracts'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Rascunhos</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['drafts'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Pendentes de assinatura</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['pending_signature'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Assinados</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['signed'] ?? 0) ?></div></div>
  <div class="tr-card p-5"><div class="text-sm text-slate-600">Vigentes</div><div class="text-2xl font-semibold mt-2"><?= (int) ($summary['active_contracts'] ?? 0) ?></div></div>
</div>

<form method="get" class="mt-6 tr-card p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
  <div>
    <label class="tr-label">Status</label>
    <select class="mt-1 tr-input" name="status">
      <option value="">Todos</option>
      <?php foreach (['rascunho','pendente_assinatura','assinado','vigente'] as $status): ?>
        <option value="<?= View::e($status) ?>" <?= (string) ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= View::e($status) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="tr-label">Proposta</label>
    <input class="mt-1 tr-input" type="number" min="1" name="proposal_id" value="<?= View::e((string) ($filters['proposal_id'] ?? '')) ?>">
  </div>
  <div>
    <label class="tr-label">Cliente</label>
    <input class="mt-1 tr-input" type="number" min="1" name="client_id" value="<?= View::e((string) ($filters['client_id'] ?? '')) ?>">
  </div>
  <div class="flex items-end justify-end">
    <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Filtrar contratos">
      <?= UI::icon('filter') ?>
      <span class="sr-only">Filtrar</span>
    </button>
  </div>
</form>

<div class="mt-6 tr-card p-6 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-slate-700">
      <tr>
        <th class="text-left py-2">Contrato</th>
        <th class="text-left py-2">Proposta</th>
        <th class="text-left py-2">Cliente</th>
        <th class="text-left py-2">Status</th>
        <th class="text-left py-2">Formalização</th>
        <th class="text-left py-2">Prazo</th>
        <th class="text-left py-2">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($contracts as $row): ?>
        <tr class="border-t">
          <td class="py-3 pr-3">
            <div class="font-semibold"><?= View::e((string) ($row['contract_number'] ?? '')) ?></div>
            <div class="text-xs text-slate-500"><?= View::e((string) ($row['title'] ?? '')) ?> · v<?= (int) ($row['current_version'] ?? 1) ?></div>
          </td>
          <td class="py-3 pr-3">#<?= (int) ($row['proposal_id'] ?? 0) ?> · <?= View::e((string) ($row['proposal_title'] ?? '')) ?></td>
          <td class="py-3 pr-3"><?= View::e((string) (($row['client_company'] ?? '') !== '' ? $row['client_company'] : ($row['client_name'] ?? ''))) ?></td>
          <td class="py-3 pr-3"><span class="tr-badge"><?= View::e((string) ($row['status'] ?? '')) ?></span></td>
          <td class="py-3 pr-3"><?= View::e((string) ($row['signature_mode'] ?? 'print')) ?></td>
          <td class="py-3 pr-3"><?= !empty($row['effective_date']) ? View::e(date('d/m/Y', strtotime((string) $row['effective_date']))) : '—' ?></td>
          <td class="py-3 pr-3">
            <a class="tr-icon-btn" href="<?= View::e($base . '/contratos/' . $row['id']) ?>" aria-label="Abrir contrato">
              <?= UI::icon('arrow-right') ?>
              <span class="sr-only">Abrir Contrato</span>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($contracts) === 0): ?>
        <tr><td class="py-4 text-slate-600" colspan="7">Nenhum contrato encontrado.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
