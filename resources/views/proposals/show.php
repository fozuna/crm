<?php
use App\Core\View;
use App\Core\UI;
$title = 'Proposta #' . (int)$proposal['id'];
$status = (string)$proposal['status'];
$projectId = (int)($projectId ?? 0);
$converted = (int)($proposal['converted_project'] ?? 0) === 1;
$docs = is_array($docs ?? null) ? $docs : [];
$milestones = is_array($milestones ?? null) ? $milestones : [];
$paymentSnapshot = is_array($paymentSnapshot ?? null) ? $paymentSnapshot : [];
$schedule = is_array($paymentSnapshot['schedule'] ?? null) ? $paymentSnapshot['schedule'] : [];
$contractSuggestion = is_array($contractSuggestion ?? null) ? $contractSuggestion : [];
$existingContract = is_array($contractSuggestion['contract'] ?? null) ? $contractSuggestion['contract'] : null;
$contractEligible = (bool) ($contractSuggestion['eligible'] ?? false);
$contractReason = (string) ($contractSuggestion['reason'] ?? 'Sem analise de contrato.');
$contractTemplate = is_array($contractSuggestion['template'] ?? null) ? $contractSuggestion['template'] : null;
$contractRequires = (int)($proposal['requires_contract'] ?? 0) === 1;
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Proposta #<?= (int)$proposal['id'] ?></div>
    <div class="text-slate-600 mt-1"><?= View::e((string)$proposal['client_name']) ?> • <?= View::e((string)$proposal['title']) ?></div>
  </div>
  <div class="flex items-center gap-3">
    <a class="tr-icon-btn" href="<?= View::e($base . '/propostas') ?>" aria-label="Voltar">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/propostas/' . $proposal['id'] . '/editar') ?>" aria-label="Editar">
      <?= UI::icon('edit') ?>
      <span class="sr-only">Editar</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/propostas/' . $proposal['id'] . '/preview') ?>" aria-label="Preview">
      <?= UI::icon('eye') ?>
      <span class="sr-only">Preview</span>
    </a>
    <form method="post" action="<?= View::e($base . '/propostas/' . $proposal['id'] . '/pdf') ?>">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <button class="tr-icon-btn" aria-label="Regenerar PDF">
        <?= UI::icon('refresh') ?>
        <span class="sr-only">Regenerar PDF</span>
      </button>
    </form>
    <a class="tr-icon-btn" href="<?= View::e($base . '/propostas/' . $proposal['id'] . '/pdf') ?>" target="_blank" aria-label="PDF">
      <?= UI::icon('pdf') ?>
      <span class="sr-only">PDF</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Status</div>
    <div class="text-lg font-semibold mt-1"><?= View::e($status) ?></div>
    <form method="post" action="<?= View::e($base . '/propostas/' . $proposal['id'] . '/status') ?>" class="mt-3 space-y-3">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <div class="flex items-center gap-2">
        <select name="status" class="tr-input text-sm">
          <?php foreach (['rascunho','enviada','aprovada','recusada'] as $s): ?>
            <option value="<?= View::e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= View::e($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Atualizar status">
          <?= UI::icon('refresh') ?>
          <span class="sr-only">Atualizar</span>
        </button>
      </div>
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 space-y-2">
        <div class="font-semibold text-slate-800">Contrato na aprovação</div>
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="requires_contract" value="1" <?= ($contractRequires || $contractEligible) ? 'checked' : '' ?>>
          <span>Gerar contrato ao aprovar a proposta</span>
        </label>
        <div class="text-xs text-slate-600"><?= View::e($contractReason) ?></div>
        <div>
          <label class="tr-label">Formalização padrão</label>
          <select name="contract_signature_mode" class="mt-1 tr-input text-sm">
            <option value="digital" <?= ($contractTemplate['signature_mode_default'] ?? 'digital') === 'digital' ? 'selected' : '' ?>>Assinatura digital</option>
            <option value="print" <?= ($contractTemplate['signature_mode_default'] ?? '') === 'print' ? 'selected' : '' ?>>Impressão</option>
          </select>
        </div>
      </div>
    </form>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Total</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format((float)$proposal['total'], 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Projeto</div>
    <?php if ($converted): ?>
      <div class="mt-2 text-slate-700 font-semibold">Já convertido</div>
      <?php if ($projectId > 0): ?>
        <div class="mt-2">
          <a class="tr-icon-btn" href="<?= View::e($base . '/projetos/' . $projectId) ?>" aria-label="Abrir projeto">
            <?= UI::icon('arrow-right') ?>
            <span class="sr-only">Abrir Projeto</span>
          </a>
        </div>
      <?php endif; ?>
    <?php elseif ($status === 'aprovada'): ?>
      <div class="mt-2 text-slate-600 text-sm">Cria um projeto e uma parcela inicial no financeiro.</div>
      <form method="post" action="<?= View::e($base . '/propostas/' . $proposal['id'] . '/converter') ?>" class="mt-3">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Converter em projeto">
          <?= UI::icon('check') ?>
          <span class="sr-only">Converter em Projeto</span>
        </button>
      </form>
    <?php else: ?>
      <div class="mt-2 text-slate-600 text-sm">Aprovação pendente para converter em projeto.</div>
    <?php endif; ?>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Contrato</div>
    <?php if ($existingContract !== null): ?>
      <div class="text-lg font-semibold mt-1"><?= View::e((string)($existingContract['status'] ?? 'rascunho')) ?></div>
      <div class="text-sm text-slate-600 mt-2"><?= View::e((string)($existingContract['contract_number'] ?? '')) ?></div>
      <div class="mt-3 flex items-center gap-2">
        <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/contratos/' . $existingContract['id']) ?>" aria-label="Abrir contrato">
          <?= UI::icon('arrow-right') ?>
          <span class="sr-only">Abrir Contrato</span>
        </a>
      </div>
    <?php elseif ($status === 'aprovada' && ($contractRequires || $contractEligible)): ?>
      <div class="mt-2 text-slate-600 text-sm">Proposta elegível para contrato com base na política ativa.</div>
      <form method="post" action="<?= View::e($base . '/propostas/' . $proposal['id'] . '/contrato/gerar') ?>" class="mt-3 flex items-center gap-2">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <input type="hidden" name="signature_mode" value="<?= View::e((string)($contractTemplate['signature_mode_default'] ?? 'digital')) ?>">
        <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Gerar contrato">
          <?= UI::icon('pdf') ?>
          <span class="sr-only">Gerar Contrato</span>
        </button>
      </form>
    <?php else: ?>
      <div class="mt-2 text-slate-600 text-sm">Contrato ainda não requerido para esta proposta.</div>
    <?php endif; ?>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
      <div class="font-semibold">Resumo financeiro</div>
      <div class="mt-3 text-sm space-y-1">
        <div class="flex justify-between"><span class="text-slate-600">Subtotal</span><span class="font-semibold">R$ <?= number_format((float)($proposal['subtotal'] ?? 0), 2, ',', '.') ?></span></div>
        <div class="flex justify-between"><span class="text-slate-600">Desconto</span><span class="font-semibold">R$ <?= number_format((float)($proposal['discount_amount'] ?? 0), 2, ',', '.') ?></span></div>
        <div class="flex justify-between"><span class="text-slate-600">Total</span><span class="font-semibold">R$ <?= number_format((float)($proposal['total'] ?? 0), 2, ',', '.') ?></span></div>
      </div>
      <div class="mt-4">
        <div class="font-semibold">Forma de pagamento</div>
        <div class="text-sm text-slate-700 mt-2"><?= View::e((string)($paymentSnapshot['method_name'] ?? '')) ?></div>
        <?php if (count($schedule) > 0): ?>
          <ul class="list-disc pl-5 mt-2 space-y-1 text-sm text-slate-700">
            <?php foreach ($schedule as $row): ?>
              <?php
                $kind = (string)($row['kind'] ?? 'parcela');
                $no = (int)($row['no'] ?? 0);
                $label = $kind === 'entrada' ? 'Entrada' : ($kind === 'avista' ? 'À vista' : ('Parcela ' . $no));
                $due = (string)($row['due_date'] ?? '');
                $dueTxt = $due !== '' ? date('d/m/Y', strtotime($due)) : '';
              ?>
              <li><?= View::e($label) ?> (<?= View::e($dueTxt) ?>): <span class="font-semibold">R$ <?= number_format((float)($row['amount'] ?? 0), 2, ',', '.') ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-sm text-slate-600 mt-2">Sem parcelamento calculado.</div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="font-semibold">Prazos e marcos</div>
      <div class="text-sm text-slate-700 mt-2">
        <div>Início: <?= !empty($proposal['delivery_start']) ? View::e(date('d/m/Y', strtotime((string)$proposal['delivery_start']))) : '—' ?></div>
        <div>Término: <?= !empty($proposal['delivery_end']) ? View::e(date('d/m/Y', strtotime((string)$proposal['delivery_end']))) : '—' ?></div>
      </div>
      <?php if (!empty($proposal['penalty_terms'])): ?>
        <div class="mt-3 text-sm text-slate-700 whitespace-pre-line"><?= View::e((string)$proposal['penalty_terms']) ?></div>
      <?php endif; ?>
      <div class="mt-4">
        <?php if (count($milestones) > 0): ?>
          <ul class="list-disc pl-5 space-y-1 text-sm text-slate-700">
            <?php foreach ($milestones as $m): ?>
              <li><?= View::e((string)$m['title']) ?><?= !empty($m['due_date']) ? (' (' . View::e(date('d/m/Y', strtotime((string)$m['due_date']))) . ')') : '' ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-sm text-slate-600">Sem marcos cadastrados.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($proposal['description'])): ?>
  <div class="mt-6 tr-card p-6">
    <div class="font-semibold">Descrição do projeto</div>
    <div class="text-slate-700 mt-3 whitespace-pre-line"><?= View::e((string)$proposal['description']) ?></div>
  </div>
<?php endif; ?>

<div class="mt-6 tr-card p-6">
  <div class="font-semibold">Serviços</div>
  <div class="mt-4 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-2">Descrição</th>
          <th class="text-left py-2 w-24">Qtd</th>
          <th class="text-left py-2 w-40">Valor</th>
          <th class="text-left py-2 w-40">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <?php $bonus = (int)($item['is_bonus'] ?? 0) === 1; ?>
          <tr class="border-t">
            <td class="py-2 pr-2">
              <div class="flex items-center gap-2">
                <span><?= View::e((string)$item['description']) ?></span>
                <?php if ($bonus): ?>
                  <span class="tr-badge">bônus</span>
                <?php endif; ?>
              </div>
            </td>
            <td class="py-2 pr-2"><?= View::e((string)$item['qty']) ?></td>
            <td class="py-2 pr-2">R$ <?= number_format((float)$item['unit_price'], 2, ',', '.') ?></td>
            <td class="py-2 pr-2">R$ <?= number_format((float)$item['total'], 2, ',', '.') ?><?= $bonus ? ' (bônus)' : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($proposal['notes'])): ?>
    <div class="mt-6">
      <div class="font-semibold">Observações</div>
      <div class="text-slate-700 mt-2 whitespace-pre-line"><?= View::e((string)$proposal['notes']) ?></div>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($proposal['terms'])): ?>
  <div class="mt-6 tr-card p-6">
    <div class="font-semibold">Termos e condições</div>
    <div class="text-slate-700 mt-3 whitespace-pre-line"><?= View::e((string)$proposal['terms']) ?></div>
  </div>
<?php endif; ?>

<?php if (count($docs) > 0): ?>
  <div class="mt-6 tr-card p-6">
    <div class="font-semibold">PDFs gerados</div>
    <div class="mt-3 flex flex-wrap gap-2">
      <?php foreach ($docs as $d): ?>
        <a class="tr-icon-btn" href="<?= View::e($base . '/propostas/' . $proposal['id'] . '/docs/' . $d['id']) ?>" aria-label="Abrir PDF v<?= (int)$d['version'] ?>">
          <?= UI::icon('pdf') ?>
          <span class="sr-only">PDF v<?= (int)$d['version'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
