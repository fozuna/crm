<?php
use App\Core\View;
use App\Core\UI;

$title = 'Cliente - ' . (string)($client['name'] ?? '');
$status = (string)($client['status'] ?? 'lead');
$statusLabel = $status === 'ativo' ? 'Cliente ativo' : 'Lead';
$statusBadge = $status === 'ativo' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800';
$logoUrl = $base . '/clientes/' . (int)$client['id'] . '/logo';
$formatMoney = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return 'Nao informado';
    }
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
};
$formatDate = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'Nao informado';
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if ($date instanceof \DateTimeImmutable) {
        return $date->format('d/m/Y');
    }
    return $raw;
};
$hasHostingContract = (int) ($client['has_hosting_contract'] ?? 0) === 1;
$managesDomain = (int) ($client['manages_domain'] ?? 0) === 1;
$hostingRenewalDate = 'Nao informado';
if ($hasHostingContract && !empty($client['hosting_due_date']) && !empty($client['hosting_renewal_days'])) {
    $baseDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $client['hosting_due_date']);
    if ($baseDate instanceof \DateTimeImmutable) {
        $hostingRenewalDate = $baseDate->modify('+' . (int) $client['hosting_renewal_days'] . ' days')->format('d/m/Y');
    }
}
?>

<div class="flex items-start justify-between gap-4">
  <div class="flex items-center gap-4">
    <div class="w-16 h-16 rounded-xl border border-slate-200 bg-white overflow-hidden flex items-center justify-center">
      <?php if (!empty($hasLogo)): ?>
        <img src="<?= View::e($logoUrl) ?>" alt="Logo de <?= View::e((string)$client['name']) ?>" class="w-full h-full object-contain" loading="lazy">
      <?php else: ?>
        <div class="text-slate-400 text-xs font-semibold">LOGO</div>
      <?php endif; ?>
    </div>
    <div>
      <div class="text-2xl font-semibold flex items-center gap-3">
        <span><?= View::e((string)$client['name']) ?></span>
        <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold <?= View::e($statusBadge) ?>"><?= View::e($statusLabel) ?></span>
      </div>
      <div class="text-slate-600 mt-1"><?= View::e((string)($client['company'] ?? '')) ?></div>
    </div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-icon-btn" href="<?= View::e($base . '/clientes') ?>" aria-label="Voltar">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/clientes/' . $client['id'] . '/editar') ?>" aria-label="Editar">
      <?= UI::icon('edit') ?>
      <span class="sr-only">Editar</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
  <div class="lg:col-span-2 tr-card p-6">
    <div class="font-semibold">Cadastro</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div>
        <div class="text-xs font-semibold text-slate-600">Responsável</div>
        <div class="mt-1 text-slate-900"><?= View::e((string)($client['contact_person'] ?? '')) ?></div>
      </div>
      <div>
        <div class="text-xs font-semibold text-slate-600">E-mail</div>
        <div class="mt-1 text-slate-900"><?= View::e((string)($client['email'] ?? '')) ?></div>
      </div>
      <div>
        <div class="text-xs font-semibold text-slate-600">Telefone</div>
        <div class="mt-1 text-slate-900"><?= View::e((string)($client['phone'] ?? '')) ?></div>
      </div>
      <div>
        <div class="text-xs font-semibold text-slate-600">Empresa</div>
        <div class="mt-1 text-slate-900"><?= View::e((string)($client['company'] ?? '')) ?></div>
      </div>
      <div class="md:col-span-2">
        <div class="text-xs font-semibold text-slate-600">Indicação / projeto realizado</div>
        <div class="mt-1 text-slate-900"><?= View::e((string)($client['project_reference'] ?? '')) ?></div>
      </div>
      <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-xs font-semibold text-slate-600">Contrato de hospedagem</div>
        <div class="mt-2 text-slate-900"><?= $hasHostingContract ? 'Ativo' : 'Nao gerenciado' ?></div>
        <?php if ($hasHostingContract): ?>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3 text-sm">
            <div>
              <div class="text-xs font-semibold text-slate-600">Valor</div>
              <div class="mt-1 text-slate-900"><?= View::e($formatMoney($client['hosting_contract_amount'] ?? null)) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Vencimento</div>
              <div class="mt-1 text-slate-900"><?= View::e($formatDate($client['hosting_due_date'] ?? '')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Renovação sugerida</div>
              <div class="mt-1 text-slate-900"><?= View::e($hostingRenewalDate) ?></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="text-xs font-semibold text-slate-600">Registro de domínio</div>
        <div class="mt-2 text-slate-900"><?= $managesDomain ? 'Gerenciado pela equipe' : 'Nao gerenciado' ?></div>
        <?php if ($managesDomain): ?>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 text-sm">
            <div>
              <div class="text-xs font-semibold text-slate-600">Vencimento</div>
              <div class="mt-1 text-slate-900"><?= View::e($formatDate($client['domain_due_date'] ?? '')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Valor</div>
              <div class="mt-1 text-slate-900"><?= View::e($formatMoney($client['domain_amount'] ?? null)) ?></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Interações</div>
    <form method="post" action="<?= View::e($base . '/clientes/' . $client['id'] . '/interacoes') ?>" class="mt-4 space-y-3">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <div>
        <label class="tr-label">Tipo</label>
        <select name="kind" class="mt-1 tr-input">
          <option value="nota">Nota</option>
          <option value="email">E-mail</option>
          <option value="call">Call</option>
          <option value="meeting">Reunião</option>
        </select>
      </div>
      <div>
        <label class="tr-label">Registro</label>
        <textarea name="note" rows="3" class="mt-1 tr-input" placeholder="Ex.: alinhamos escopo, próximos passos..."></textarea>
      </div>
      <div class="flex justify-end">
        <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar interação">
          <?= UI::icon('save') ?>
          <span class="sr-only">Salvar</span>
        </button>
      </div>
    </form>

    <div class="mt-5 space-y-3">
      <?php foreach ($interactions as $it): ?>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
          <div class="flex items-center justify-between gap-3">
            <span class="tr-badge"><?= View::e((string)$it['kind']) ?></span>
            <span class="text-xs text-slate-500"><?= View::e((string)$it['created_at']) ?></span>
          </div>
          <div class="mt-2 text-sm text-slate-800 whitespace-pre-line"><?= View::e((string)$it['note']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (count($interactions) === 0): ?>
        <div class="text-sm text-slate-600">Sem interações registradas.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
  <div class="tr-card p-6 overflow-hidden">
    <div class="flex items-center justify-between">
      <div class="font-semibold">Projetos</div>
      <span class="text-sm text-slate-600"><?= count($projects) ?> total</span>
    </div>
    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-700">
          <tr>
            <th class="text-left py-2">Título</th>
            <th class="text-left py-2 w-28">Status</th>
            <th class="text-left py-2 w-36">Valor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projects as $p): ?>
            <tr class="border-t">
              <td class="py-2 pr-2 font-medium"><?= View::e((string)$p['title']) ?></td>
              <td class="py-2 pr-2"><span class="tr-badge"><?= View::e((string)$p['status']) ?></span></td>
              <td class="py-2 pr-2">R$ <?= number_format((float)$p['value'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($projects) === 0): ?>
            <tr><td class="py-4 text-slate-600" colspan="3">Nenhum projeto associado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tr-card p-6 overflow-hidden">
    <div class="flex items-center justify-between">
      <div class="font-semibold">Propostas</div>
      <a class="tr-icon-btn" href="<?= View::e($base . '/propostas') ?>" aria-label="Ir para propostas">
        <?= UI::icon('eye') ?>
        <span class="sr-only">Propostas</span>
      </a>
    </div>
    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-700">
          <tr>
            <th class="text-left py-2">Título</th>
            <th class="text-left py-2 w-28">Status</th>
            <th class="text-left py-2 w-36">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($proposals as $pr): ?>
            <tr class="border-t">
              <td class="py-2 pr-2">
                <a class="font-medium text-traxterAccent" href="<?= View::e($base . '/propostas/' . $pr['id']) ?>"><?= View::e((string)$pr['title']) ?></a>
              </td>
              <td class="py-2 pr-2"><span class="tr-badge"><?= View::e((string)$pr['status']) ?></span></td>
              <td class="py-2 pr-2">R$ <?= number_format((float)$pr['total'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (count($proposals) === 0): ?>
            <tr><td class="py-4 text-slate-600" colspan="3">Nenhuma proposta encontrada.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
