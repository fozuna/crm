<?php
use App\Core\UI;
use App\Core\View;

$title = 'Preview - Proposta #' . (int)$proposal['id'];
$company = (string)($branding['company_name'] ?? 'TRAXTER');
$logoPath = (string)($branding['logo_path'] ?? '');

$paymentOptions = is_array($paymentOptions ?? null) ? $paymentOptions : [];
$paymentSelectedIndex = (int)($paymentSelectedIndex ?? 0);

if (count($paymentOptions) === 0) {
  $paymentOptions = [[
    'label' => (string)($paymentSnapshot['method_name'] ?? 'Pagamento'),
    'total' => (float)($proposal['total'] ?? 0),
    'snapshot' => $paymentSnapshot,
  ]];
  $paymentSelectedIndex = 0;
}
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Preview da proposta</div>
    <div class="text-slate-600 mt-1">Documento antes de gerar o PDF</div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-icon-btn" href="<?= View::e($base . '/propostas/' . $proposal['id']) ?>" aria-label="Voltar">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
    <form method="post" action="<?= View::e($base . '/propostas/' . $proposal['id'] . '/pdf') ?>">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Gerar PDF">
        <?= UI::icon('pdf') ?>
        <span class="sr-only">Gerar PDF</span>
      </button>
    </form>
  </div>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="bg-traxterSidebar text-traxterText p-6 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="text-xl font-semibold"><?= View::e($company) ?></div>
    </div>
    <div class="text-sm text-white/80">Proposta #<?= (int)$proposal['id'] ?></div>
  </div>
  <div class="p-6 space-y-6">
    <div>
      <div class="text-lg font-semibold"><?= View::e((string)$proposal['title']) ?></div>
      <div class="text-slate-600 mt-1">Cliente: <?= View::e((string)($proposal['client_name'] ?? '')) ?></div>
    </div>

    <?php if (!empty($proposal['description'])): ?>
      <div>
        <div class="font-semibold">Descrição do projeto</div>
        <div class="mt-2 text-slate-700 whitespace-pre-line"><?= View::e((string)$proposal['description']) ?></div>
      </div>
    <?php endif; ?>

    <div>
      <div class="font-semibold">Serviços</div>
      <div class="mt-3 overflow-x-auto">
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
            <?php foreach ($items as $it): ?>
              <?php $bonus = (int)($it['is_bonus'] ?? 0) === 1; ?>
              <tr class="border-t">
                <td class="py-2 pr-2">
                  <div class="flex items-center gap-2">
                    <span><?= View::e((string)$it['description']) ?></span>
                    <?php if ($bonus): ?>
                      <span class="tr-badge">bônus</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="py-2 pr-2"><?= View::e((string)$it['qty']) ?></td>
                <td class="py-2 pr-2">R$ <?= number_format((float)$it['unit_price'], 2, ',', '.') ?></td>
                <td class="py-2 pr-2">R$ <?= number_format((float)$it['total'], 2, ',', '.') ?><?= $bonus ? ' (bônus)' : '' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="font-semibold">Resumo financeiro</div>
        <div class="mt-2 text-sm space-y-1">
          <div class="flex justify-between"><span class="text-slate-600">Subtotal</span><span class="font-semibold">R$ <?= number_format((float)$proposal['subtotal'], 2, ',', '.') ?></span></div>
          <div class="flex justify-between"><span class="text-slate-600">Desconto</span><span class="font-semibold">R$ <?= number_format((float)$proposal['discount_amount'], 2, ',', '.') ?></span></div>
          <div class="flex justify-between"><span class="text-slate-600">Total</span><span class="font-semibold">R$ <?= number_format((float)$proposal['total'], 2, ',', '.') ?></span></div>
        </div>
      </div>
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="font-semibold">Formas de pagamento</div>
        <div class="mt-3 space-y-4 text-sm">
          <?php foreach ($paymentOptions as $idx => $opt): ?>
            <?php
              $snap = is_array($opt['snapshot'] ?? null) ? $opt['snapshot'] : [];
              $schedule = is_array($snap['schedule'] ?? null) ? $snap['schedule'] : [];
              $tag = ((int)$idx === (int)$paymentSelectedIndex) ? ' (principal)' : '';
            ?>
            <div class="rounded-lg border border-slate-200 bg-white p-3">
              <div class="font-semibold text-slate-900"><?= View::e((string)($opt['label'] ?? ('Opção ' . ((int)$idx + 1)))) ?><?= View::e($tag) ?></div>
              <div class="text-slate-700 mt-1">Total: <span class="font-semibold">R$ <?= number_format((float)($opt['total'] ?? 0), 2, ',', '.') ?></span></div>
              <div class="mt-2">
                <?php if (count($schedule) > 0): ?>
                  <ul class="list-disc pl-5 space-y-1">
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
                  <div class="text-slate-600">Sem parcelamento calculado.</div>
                <?php endif; ?>
              </div>
              <?php if (!empty($snap['special_terms'])): ?>
                <div class="mt-2 text-slate-700 whitespace-pre-line"><?= View::e((string)$snap['special_terms']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <div class="font-semibold">Prazos</div>
        <div class="text-sm text-slate-700 mt-2">
          <div>Início: <?= !empty($proposal['delivery_start']) ? View::e(date('d/m/Y', strtotime((string)$proposal['delivery_start']))) : '—' ?></div>
          <div>Término: <?= !empty($proposal['delivery_end']) ? View::e(date('d/m/Y', strtotime((string)$proposal['delivery_end']))) : '—' ?></div>
        </div>
        <?php if (!empty($proposal['penalty_terms'])): ?>
          <div class="mt-3 text-sm text-slate-700 whitespace-pre-line"><?= View::e((string)$proposal['penalty_terms']) ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="font-semibold">Marcos</div>
        <div class="mt-2 text-sm text-slate-700">
          <?php if (count($milestones) > 0): ?>
            <ul class="list-disc pl-5 space-y-1">
              <?php foreach ($milestones as $m): ?>
                <li><?= View::e((string)$m['title']) ?><?= !empty($m['due_date']) ? (' (' . View::e(date('d/m/Y', strtotime((string)$m['due_date']))) . ')') : '' ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="text-slate-600">Sem marcos cadastrados.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($proposal['terms'])): ?>
      <div>
        <div class="font-semibold">Termos e condições</div>
        <div class="mt-2 text-slate-700 whitespace-pre-line"><?= View::e((string)$proposal['terms']) ?></div>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-6 border-t">
      <div>
        <div class="mt-6 border-t border-slate-300"></div>
        <div class="text-sm text-slate-600">Assinatura do cliente</div>
      </div>
      <div>
        <div class="mt-6 border-t border-slate-300"></div>
        <div class="text-sm text-slate-600">Assinatura da empresa</div>
      </div>
    </div>
  </div>
</div>

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
