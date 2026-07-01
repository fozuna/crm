<?php
use App\Core\View;
use App\Core\UI;
$isEdit = is_array($proposal) && isset($proposal['id']);
$title = $isEdit ? 'Editar proposta' : 'Nova proposta';
$action = $isEdit ? ($base . '/propostas/' . $proposal['id']) : ($base . '/propostas');
$selectedClient = (int)($proposal['client_id'] ?? 0);
$leadPrefill = is_array($leadPrefill ?? null) ? $leadPrefill : null;
$leadSummary = is_array($leadPrefill['summary'] ?? null) ? $leadPrefill['summary'] : null;
$sourceLeadId = (int)($proposal['source_lead_id'] ?? ($leadPrefill['lead']['id'] ?? 0));
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$services = is_array($services ?? null) ? $services : [];
$paymentSelectedIndex = (int)($proposal['payment_selected_index'] ?? 0);
$paymentOptions = [];
if ($isEdit && !empty($proposal['payment_options'])) {
  $decoded = json_decode((string)$proposal['payment_options'], true);
  if (is_array($decoded)) {
    foreach ($decoded as $opt) {
      if (!is_array($opt)) {
        continue;
      }
      $snap = is_array($opt['snapshot'] ?? null) ? $opt['snapshot'] : [];
      $paymentOptions[] = [
        'method_id' => (int)($snap['method_id'] ?? 0),
        'label' => (string)($opt['label'] ?? ''),
        'discount_percent' => (string)($opt['discount_percent'] ?? ''),
        'type' => (string)($snap['type'] ?? 'avista'),
        'installments_count' => (string)($snap['installments_count'] ?? '1'),
        'interval_days' => (string)($snap['interval_days'] ?? '30'),
        'has_down_payment' => (string)($snap['has_down_payment'] ?? '0'),
        'down_payment_percent' => (string)($snap['down_payment_percent'] ?? '0'),
        'special_terms' => (string)($snap['special_terms'] ?? ''),
      ];
    }
  }
}

if (count($paymentOptions) === 0 && is_array($proposal['payment_option_method_id'] ?? null)) {
  $methodIds = $proposal['payment_option_method_id'];
  $labels = $proposal['payment_option_label'] ?? [];
  $discounts = $proposal['payment_option_discount_percent'] ?? [];
  $types = $proposal['payment_option_type'] ?? [];
  $inst = $proposal['payment_option_installments_count'] ?? [];
  $interval = $proposal['payment_option_interval_days'] ?? [];
  $hasDown = $proposal['payment_option_has_down_payment'] ?? [];
  $down = $proposal['payment_option_down_payment_percent'] ?? [];
  $terms = $proposal['payment_option_special_terms'] ?? [];

  foreach ($methodIds as $i => $mid) {
    if ($i >= 3) {
      break;
    }
    $paymentOptions[] = [
      'method_id' => (int) $mid,
      'label' => (string) ($labels[$i] ?? ''),
      'discount_percent' => (string) ($discounts[$i] ?? ''),
      'type' => (string) ($types[$i] ?? 'avista'),
      'installments_count' => (string) ($inst[$i] ?? '1'),
      'interval_days' => (string) ($interval[$i] ?? '30'),
      'has_down_payment' => (string) ($hasDown[$i] ?? '0'),
      'down_payment_percent' => (string) ($down[$i] ?? '0'),
      'special_terms' => (string) ($terms[$i] ?? ''),
    ];
  }
}
if (!$isEdit && count($paymentOptions) === 0) {
  $paymentOptions[] = [
    'method_id' => (int)($proposal['payment_method_id'] ?? 0),
    'label' => '',
    'discount_percent' => '',
    'type' => 'avista',
    'installments_count' => '1',
    'interval_days' => '30',
    'has_down_payment' => '0',
    'down_payment_percent' => '0',
    'special_terms' => '',
  ];
}
if (count($paymentOptions) === 0) {
  $paymentOptions[] = [
    'method_id' => 0,
    'label' => '',
    'discount_percent' => (string)($proposal['discount_percent'] ?? ''),
    'type' => 'avista',
    'installments_count' => '1',
    'interval_days' => '30',
    'has_down_payment' => '0',
    'down_payment_percent' => '0',
    'special_terms' => '',
  ];
}
$milestones = is_array($milestones ?? null) ? $milestones : [];
$items = is_array($items) ? $items : [];
if (!$isEdit && count($items) === 0) {
  $items = [['description' => '', 'qty' => 1, 'unit_price' => 0, 'total' => 0]];
}
if (!$isEdit && count($milestones) === 0) {
  $milestones = [['title' => '', 'due_date' => '', 'notes' => '', 'penalty_terms' => '']];
}
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold"><?= View::e($title) ?></div>
    <div class="text-slate-600 mt-1">Serviços, cálculo automático e conversão para projeto</div>
  </div>
  <a class="text-slate-700" href="<?= View::e($base . '/propostas') ?>">Voltar</a>
</div>

<?php if (!empty($error)): ?>
  <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string)$error) ?></div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 space-y-4">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
  <?php if ($sourceLeadId > 0): ?>
    <input type="hidden" name="source_lead_id" value="<?= (int)$sourceLeadId ?>">
  <?php endif; ?>

  <?php if ($leadPrefill !== null): ?>
    <div class="tr-card p-6 space-y-4">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <div class="text-lg font-semibold text-slate-900">Dados importados do lead</div>
          <div class="mt-1 text-sm text-slate-600">A proposta foi iniciada a partir do pipeline comercial. Os campos abaixo foram preenchidos automaticamente para evitar redigitação.</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <?php if (!empty($leadPrefill['retry_url'])): ?>
            <a class="tr-btn" href="<?= View::e((string)$leadPrefill['retry_url']) ?>">Tentar novamente</a>
          <?php endif; ?>
          <?php if (!empty($leadPrefill['back_url'])): ?>
            <a class="tr-btn" href="<?= View::e((string)$leadPrefill['back_url']) ?>">Voltar ao lead</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($leadSummary !== null): ?>
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.4fr),minmax(0,1fr)]">
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div><div class="text-xs uppercase tracking-wide text-slate-400">Lead</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['company'] ?? '')) ?></div></div>
              <div><div class="text-xs uppercase tracking-wide text-slate-400">Contato</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['contact'] ?? '')) ?></div></div>
              <div><div class="text-xs uppercase tracking-wide text-slate-400">Documento</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['document'] ?? '')) ?></div></div>
              <div><div class="text-xs uppercase tracking-wide text-slate-400">Cliente base</div><div class="mt-1 text-sm font-medium text-slate-800">#<?= (int)($leadSummary['client_id'] ?? 0) ?></div></div>
              <div><div class="text-xs uppercase tracking-wide text-slate-400">E-mail</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['email'] ?? '')) ?></div></div>
              <div><div class="text-xs uppercase tracking-wide text-slate-400">Telefone</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['phone'] ?? '')) ?></div></div>
              <div><div class="text-xs uppercase tracking-wide text-slate-400">Segmento</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['segment'] ?? '')) ?></div></div>
              <div><div class="text-xs uppercase tracking-wide text-slate-400">Origem</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['source'] ?? '')) ?></div></div>
              <div class="md:col-span-2"><div class="text-xs uppercase tracking-wide text-slate-400">Endereço</div><div class="mt-1 text-sm font-medium text-slate-800"><?= View::e((string)($leadSummary['address'] ?? '')) ?></div></div>
              <?php if (!empty($leadSummary['notes'])): ?>
                <div class="md:col-span-2"><div class="text-xs uppercase tracking-wide text-slate-400">Necessidades / contexto comercial</div><div class="mt-1 text-sm text-slate-700 whitespace-pre-wrap"><?= View::e((string)$leadSummary['notes']) ?></div></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4">
            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="text-sm font-semibold text-slate-900">Histórico do funil</div>
              <div class="mt-3 space-y-3">
                <?php foreach (($leadSummary['history'] ?? []) as $row): ?>
                  <div class="rounded-xl bg-slate-50 p-3">
                    <div class="text-sm font-medium text-slate-800"><?= View::e((string)($row['title'] ?? '')) ?></div>
                    <div class="text-xs text-slate-500 mt-1"><?= View::e((string)($row['meta'] ?? '')) ?></div>
                    <?php if (!empty($row['detail'])): ?><div class="text-sm text-slate-700 mt-2"><?= View::e((string)$row['detail']) ?></div><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="text-sm font-semibold text-slate-900">Interações recentes</div>
              <div class="mt-3 space-y-3">
                <?php foreach (($leadSummary['interactions'] ?? []) as $row): ?>
                  <div class="rounded-xl bg-slate-50 p-3">
                    <div class="text-sm font-medium text-slate-800"><?= View::e((string)($row['title'] ?? '')) ?></div>
                    <div class="text-xs text-slate-500 mt-1"><?= View::e((string)($row['meta'] ?? '')) ?></div>
                    <?php if (!empty($row['detail'])): ?><div class="text-sm text-slate-700 mt-2 whitespace-pre-wrap"><?= View::e((string)$row['detail']) ?></div><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="tr-card p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="tr-label">Cliente</label>
      <select name="client_id" class="mt-1 tr-input" required>
        <option value="">Selecione</option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === $selectedClient) ? 'selected' : '' ?>><?= View::e((string)$c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Título</label>
      <input name="title" value="<?= View::e((string)($proposal['title'] ?? '')) ?>" class="mt-1 tr-input" required>
    </div>
    <div class="md:col-span-2">
      <label class="tr-label">Observações</label>
      <textarea name="notes" rows="3" class="mt-1 tr-input"><?= View::e((string)($proposal['notes'] ?? '')) ?></textarea>
    </div>
    <div class="md:col-span-2">
      <label class="tr-label">Descrição detalhada do projeto</label>
      <textarea name="description" rows="6" class="mt-1 tr-input" placeholder="Escopo, entregáveis, premissas..."><?= View::e((string)($proposal['description'] ?? '')) ?></textarea>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Pagamento</div>
    <div class="flex items-center justify-between mt-4">
      <div class="text-sm text-slate-600">Adicione até 3 opções e marque a principal.</div>
      <button type="button" id="addPaymentOption" class="tr-icon-btn" aria-label="Adicionar opção">
        <?= UI::icon('plus') ?>
        <span class="sr-only">Adicionar opção</span>
      </button>
    </div>

    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-sm" id="paymentOptionsTable">
        <thead class="text-slate-700">
          <tr>
            <th class="text-left py-2 w-24">Principal</th>
            <th class="text-left py-2">Forma</th>
            <th class="text-left py-2">Descrição</th>
            <th class="text-left py-2 w-28">Desconto</th>
            <th class="text-left py-2 w-28">Tipo</th>
            <th class="text-left py-2 w-28">Parcelas</th>
            <th class="text-left py-2 w-28">Intervalo</th>
            <th class="text-left py-2 w-28">Entrada</th>
            <th class="text-left py-2 w-32">% Entrada</th>
            <th class="text-left py-2">Condições</th>
            <th class="text-left py-2 w-32">Valor</th>
            <th class="py-2 w-16"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paymentOptions as $i => $opt): ?>
            <tr class="border-t paymentOptionRow" data-index="<?= (int)$i ?>">
              <td class="py-2 pr-2">
                <label class="inline-flex items-center gap-2">
                  <input type="radio" name="payment_selected_index" value="<?= (int)$i ?>" <?= ((int)$i === $paymentSelectedIndex) ? 'checked' : '' ?>>
                  <span class="text-sm font-semibold">Opção</span>
                </label>
              </td>
              <td class="py-2 pr-2">
                <select name="payment_option_method_id[]" class="tr-input poMethod" required>
                  <option value="">Selecione</option>
                  <?php foreach ($paymentMethods as $pm): ?>
                    <option
                      value="<?= (int)$pm['id'] ?>"
                      data-type="<?= View::e((string)$pm['type']) ?>"
                      data-installments="<?= View::e((string)$pm['installments_count']) ?>"
                      data-interval="<?= View::e((string)$pm['interval_days']) ?>"
                      data-has-down="<?= (int)$pm['has_down_payment'] ?>"
                      data-down="<?= View::e((string)$pm['down_payment_percent']) ?>"
                      <?= ((int)$pm['id'] === (int)$opt['method_id']) ? 'selected' : '' ?>
                    ><?= View::e((string)$pm['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="py-2 pr-2"><input name="payment_option_label[]" value="<?= View::e((string)$opt['label']) ?>" class="tr-input poLabel" placeholder="Ex.: PIX / Boleto / Cartão"></td>
              <td class="py-2 pr-2"><input name="payment_option_discount_percent[]" value="<?= View::e((string)$opt['discount_percent']) ?>" class="tr-input poDiscount" inputmode="decimal" placeholder="0"></td>
              <td class="py-2 pr-2">
                <select name="payment_option_type[]" class="tr-input poType">
                  <option value="avista" <?= ((string)$opt['type'] === 'avista') ? 'selected' : '' ?>>À vista</option>
                  <option value="parcelado" <?= ((string)$opt['type'] === 'parcelado') ? 'selected' : '' ?>>Parcelado</option>
                </select>
              </td>
              <td class="py-2 pr-2"><input name="payment_option_installments_count[]" value="<?= View::e((string)$opt['installments_count']) ?>" class="tr-input poInstallments" inputmode="numeric"></td>
              <td class="py-2 pr-2"><input name="payment_option_interval_days[]" value="<?= View::e((string)$opt['interval_days']) ?>" class="tr-input poInterval" inputmode="numeric"></td>
              <td class="py-2 pr-2">
                <select name="payment_option_has_down_payment[]" class="tr-input poHasDown">
                  <option value="0" <?= ((string)$opt['has_down_payment'] === '0') ? 'selected' : '' ?>>Sem</option>
                  <option value="1" <?= ((string)$opt['has_down_payment'] === '1') ? 'selected' : '' ?>>Com</option>
                </select>
              </td>
              <td class="py-2 pr-2"><input name="payment_option_down_payment_percent[]" value="<?= View::e((string)$opt['down_payment_percent']) ?>" class="tr-input poDown" inputmode="decimal" placeholder="0"></td>
              <td class="py-2 pr-2"><input name="payment_option_special_terms[]" value="<?= View::e((string)$opt['special_terms']) ?>" class="tr-input poTerms" placeholder="Condições especiais"></td>
              <td class="py-2 pr-2"><span class="font-semibold poValue">R$ 0,00</span></td>
              <td class="py-2 text-right">
                <button type="button" class="tr-icon-btn removePaymentOption" aria-label="Remover opção"><?= UI::icon('trash') ?><span class="sr-only">Remover</span></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
      <div class="text-sm font-semibold text-slate-800">Resumo financeiro</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3 text-sm">
        <div>
          <div class="text-slate-600">Subtotal</div>
          <div class="font-semibold" id="subtotalEl">R$ 0,00</div>
        </div>
        <div>
          <div class="text-slate-600">Desconto</div>
          <div class="font-semibold" id="discountEl">R$ 0,00</div>
        </div>
        <div>
          <div class="text-slate-600">Total</div>
          <div class="font-semibold" id="totalEl">R$ 0,00</div>
        </div>
      </div>
      <div class="mt-4">
        <div class="text-sm font-semibold text-slate-800">Parcelamento</div>
        <div class="text-sm text-slate-700 mt-2" id="scheduleEl">Selecione a opção principal.</div>
      </div>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="flex items-center justify-between">
      <div class="font-semibold">Serviços</div>
      <button type="button" id="addItem" class="tr-icon-btn" aria-label="Adicionar item">
        <?= UI::icon('plus') ?>
        <span class="sr-only">Adicionar</span>
      </button>
    </div>

    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-sm" id="itemsTable">
        <thead class="text-slate-700">
          <tr>
            <th class="text-left py-2 w-56">Catálogo</th>
            <th class="text-left py-2">Descrição</th>
            <th class="text-left py-2 w-24">Qtd</th>
            <th class="text-left py-2 w-40">Valor</th>
            <th class="text-left py-2 w-40">Total</th>
            <th class="py-2 w-16"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php $rowBonus = (int)($item['is_bonus'] ?? 0) === 1; ?>
            <tr class="border-t itemRow" data-bonus="<?= $rowBonus ? '1' : '0' ?>">
              <td class="py-2 pr-2">
                <select name="item_service_id[]" class="w-full tr-input itemService">
                  <option value="0">Manual</option>
                  <?php foreach ($services as $s): ?>
                    <?php
                      $sid = (int)($s['id'] ?? 0);
                      $sName = (string)($s['name'] ?? '');
                      $sPrice = (float)($s['default_price'] ?? 0);
                      $sDesc = (string)($s['description'] ?? '');
                      $sBonus = (int)($s['is_bonus'] ?? 0) === 1;
                      $sActive = (int)($s['active'] ?? 1) === 1;
                      $selected = (int)($item['service_id'] ?? 0) === $sid;
                      $disabled = !$sActive && !$selected;
                      $label = $sName . ($sBonus ? ' (bônus)' : '') . (!$sActive ? ' (inativo)' : '');
                    ?>
                    <option value="<?= $sid ?>" data-price="<?= View::e((string)$sPrice) ?>" data-desc="<?= View::e(mb_substr($sDesc, 0, 255)) ?>" data-bonus="<?= $sBonus ? '1' : '0' ?>" <?= $selected ? 'selected' : '' ?> <?= $disabled ? 'disabled' : '' ?>><?= View::e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="py-2 pr-2">
                <input name="item_description[]" value="<?= View::e((string)($item['description'] ?? '')) ?>" class="w-full tr-input" required>
              </td>
              <td class="py-2 pr-2">
                <input name="item_qty[]" value="<?= View::e((string)($item['qty'] ?? 1)) ?>" class="w-full tr-input itemQty" inputmode="decimal" required>
              </td>
              <td class="py-2 pr-2">
                <input name="item_unit_price[]" value="<?= View::e((string)($item['unit_price'] ?? 0)) ?>" class="w-full tr-input itemPrice" inputmode="decimal" required>
              </td>
              <td class="py-2 pr-2">
                <div class="itemTotal text-slate-800">R$ 0,00</div>
              </td>
              <td class="py-2 text-right">
                <button type="button" class="tr-icon-btn removeItem" aria-label="Remover item">
                  <?= UI::icon('trash') ?>
                  <span class="sr-only">Remover</span>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex items-center justify-end gap-3">
      <div class="text-slate-600">Total</div>
      <div class="text-xl font-semibold" id="grandTotal">R$ 0,00</div>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="flex items-center justify-between">
      <div class="font-semibold">Prazos de entrega</div>
      <button type="button" id="addMilestone" class="tr-icon-btn" aria-label="Adicionar marco">
        <?= UI::icon('plus') ?>
        <span class="sr-only">Adicionar marco</span>
      </button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="tr-label">Início estimado</label>
        <input name="delivery_start" type="date" value="<?= View::e((string)($proposal['delivery_start'] ?? '')) ?>" class="mt-1 tr-input" id="deliveryStart">
      </div>
      <div>
        <label class="tr-label">Término estimado</label>
        <input name="delivery_end" type="date" value="<?= View::e((string)($proposal['delivery_end'] ?? '')) ?>" class="mt-1 tr-input" id="deliveryEnd">
      </div>
      <div class="md:col-span-2">
        <label class="tr-label">Penalidades por atraso</label>
        <textarea name="penalty_terms" rows="3" class="mt-1 tr-input" placeholder="Ex.: multa de 2% + 1% ao mês..."><?= View::e((string)($proposal['penalty_terms'] ?? '')) ?></textarea>
      </div>
    </div>
    <div class="mt-5 overflow-x-auto">
      <table class="w-full text-sm" id="milestonesTable">
        <thead class="text-slate-700">
          <tr>
            <th class="text-left py-2">Marco</th>
            <th class="text-left py-2 w-40">Data</th>
            <th class="text-left py-2">Notas</th>
            <th class="text-left py-2">Penalidade</th>
            <th class="py-2 w-16"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($milestones as $m): ?>
            <tr class="border-t milestoneRow">
              <td class="py-2 pr-2"><input name="milestone_title[]" value="<?= View::e((string)($m['title'] ?? '')) ?>" class="w-full tr-input"></td>
              <td class="py-2 pr-2"><input name="milestone_due_date[]" type="date" value="<?= View::e((string)($m['due_date'] ?? '')) ?>" class="w-full tr-input"></td>
              <td class="py-2 pr-2"><input name="milestone_notes[]" value="<?= View::e((string)($m['notes'] ?? '')) ?>" class="w-full tr-input"></td>
              <td class="py-2 pr-2"><input name="milestone_penalty[]" value="<?= View::e((string)($m['penalty_terms'] ?? '')) ?>" class="w-full tr-input"></td>
              <td class="py-2 text-right">
                <button type="button" class="tr-icon-btn removeMilestone" aria-label="Remover marco"><?= UI::icon('trash') ?><span class="sr-only">Remover</span></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Termos e condições</div>
    <textarea name="terms" rows="6" class="mt-4 tr-input" placeholder="Termos, condições, escopo, aceite, assinatura..."><?= View::e((string)($proposal['terms'] ?? '')) ?></textarea>
  </div>

  <div class="flex items-center justify-end gap-3">
    <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar proposta">
      <?= UI::icon('save') ?>
      <span class="sr-only">Salvar proposta</span>
    </button>
  </div>
</form>

<template id="itemTemplate">
  <tr class="border-t itemRow">
    <td class="py-2 pr-2">
      <select name="item_service_id[]" class="w-full tr-input itemService">
        <option value="0">Manual</option>
        <?php foreach ($services as $s): ?>
          <?php
            $sid = (int)($s['id'] ?? 0);
            $sName = (string)($s['name'] ?? '');
            $sPrice = (float)($s['default_price'] ?? 0);
            $sDesc = (string)($s['description'] ?? '');
            $sBonus = (int)($s['is_bonus'] ?? 0) === 1;
            $label = $sName . ($sBonus ? ' (bônus)' : '');
          ?>
          <option value="<?= $sid ?>" data-price="<?= View::e((string)$sPrice) ?>" data-desc="<?= View::e(mb_substr($sDesc, 0, 255)) ?>" data-bonus="<?= $sBonus ? '1' : '0' ?>"><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td class="py-2 pr-2"><input name="item_description[]" class="w-full tr-input" required></td>
    <td class="py-2 pr-2"><input name="item_qty[]" value="1" class="w-full tr-input itemQty" inputmode="decimal" required></td>
    <td class="py-2 pr-2"><input name="item_unit_price[]" value="0" class="w-full tr-input itemPrice" inputmode="decimal" required></td>
    <td class="py-2 pr-2"><div class="itemTotal text-slate-800">R$ 0,00</div></td>
    <td class="py-2 text-right"><button type="button" class="tr-icon-btn removeItem" aria-label="Remover item"><?= UI::icon('trash') ?><span class="sr-only">Remover</span></button></td>
  </tr>
</template>

<template id="milestoneTemplate">
  <tr class="border-t milestoneRow">
    <td class="py-2 pr-2"><input name="milestone_title[]" class="w-full tr-input"></td>
    <td class="py-2 pr-2"><input name="milestone_due_date[]" type="date" class="w-full tr-input"></td>
    <td class="py-2 pr-2"><input name="milestone_notes[]" class="w-full tr-input"></td>
    <td class="py-2 pr-2"><input name="milestone_penalty[]" class="w-full tr-input"></td>
    <td class="py-2 text-right"><button type="button" class="tr-icon-btn removeMilestone" aria-label="Remover marco"><?= UI::icon('trash') ?><span class="sr-only">Remover</span></button></td>
  </tr>
</template>

<script>
  const tableBody = document.querySelector('#itemsTable tbody');
  const addItemBtn = document.getElementById('addItem');
  const tpl = document.getElementById('itemTemplate');
  const grandTotalEl = document.getElementById('grandTotal');
  const subtotalEl = document.getElementById('subtotalEl');
  const discountEl = document.getElementById('discountEl');
  const totalEl = document.getElementById('totalEl');
  const scheduleEl = document.getElementById('scheduleEl');
  const paymentOptionsBody = document.querySelector('#paymentOptionsTable tbody');
  const addPaymentOptionBtn = document.getElementById('addPaymentOption');
  const deliveryStartEl = document.getElementById('deliveryStart');

  const milestonesBody = document.querySelector('#milestonesTable tbody');
  const addMilestoneBtn = document.getElementById('addMilestone');
  const milestoneTpl = document.getElementById('milestoneTemplate');

  const isEdit = <?= $isEdit ? 'true' : 'false' ?>;
  const basePath = <?= json_encode((string)$base, JSON_UNESCAPED_UNICODE) ?>;
  const toastType = <?= json_encode((string)($toastType ?? ''), JSON_UNESCAPED_UNICODE) ?>;
  const toastMessage = <?= json_encode((string)($toastMessage ?? ''), JSON_UNESCAPED_UNICODE) ?>;

  const parseNumber = (v) => {
    if (typeof v !== 'string') return 0;
    const n = parseFloat(v.replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  };

  const formatBRL = (n) => {
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  };

  const addDays = (dateStr, days) => {
    const d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + Math.max(0, days));
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  };

  const formatBRDate = (dateStr) => {
    if (!dateStr) return '';
    const [y,m,d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
  };

  if (toastType && toastMessage && typeof window.trToast === 'function') {
    window.trToast(toastType, toastMessage);
  }

  const recalc = () => {
    let subtotal = 0;
    document.querySelectorAll('.itemRow').forEach(row => {
      const qtyEl = row.querySelector('.itemQty');
      const priceEl = row.querySelector('.itemPrice');
      const qty = parseNumber((qtyEl && qtyEl.value) ? qtyEl.value : '0');
      const price = parseNumber((priceEl && priceEl.value) ? priceEl.value : '0');
      const line = Math.max(0, qty) * Math.max(0, price);
      const isBonus = (row.dataset.bonus || '0') === '1';
      if (!isBonus) {
        subtotal += line;
      }
      const totalEl = row.querySelector('.itemTotal');
      if (totalEl) totalEl.textContent = isBonus ? ('Bônus: ' + formatBRL(line)) : formatBRL(line);
    });

    const baseDate = ((deliveryStartEl && deliveryStartEl.value) ? deliveryStartEl.value : new Date().toISOString().slice(0,10));

    const rows = Array.from(document.querySelectorAll('.paymentOptionRow'));
    if (rows.length === 0) {
      subtotalEl.textContent = formatBRL(subtotal);
      discountEl.textContent = formatBRL(0);
      totalEl.textContent = formatBRL(subtotal);
      grandTotalEl.textContent = formatBRL(subtotal);
      scheduleEl.textContent = 'Selecione a opção principal.';
      return;
    }

    let primaryRow = rows.find(r => {
      const radio = r.querySelector('input[type="radio"][name="payment_selected_index"]');
      return !!(radio && radio.checked);
    });
    if (!primaryRow) {
      primaryRow = rows[0];
      const radio = primaryRow.querySelector('input[type="radio"][name="payment_selected_index"]');
      if (radio) radio.checked = true;
    }

    const computeForRow = (row) => {
      const typeEl = row.querySelector('.poType');
      const discountEl = row.querySelector('.poDiscount');
      const hasDownEl = row.querySelector('.poHasDown');
      const downEl = row.querySelector('.poDown');
      const instEl = row.querySelector('.poInstallments');
      const intervalEl = row.querySelector('.poInterval');

      const type = String((typeEl && typeEl.value) ? typeEl.value : 'avista');
      const discountPercent = parseNumber(String((discountEl && discountEl.value != null) ? discountEl.value : '0'));
      const hasDown = String((hasDownEl && hasDownEl.value) ? hasDownEl.value : '0') === '1';
      const down = parseNumber(String((downEl && downEl.value != null) ? downEl.value : '0'));
      const installments = parseInt(String((instEl && instEl.value != null) ? instEl.value : '1'), 10) || 1;
      const interval = parseInt(String((intervalEl && intervalEl.value != null) ? intervalEl.value : '30'), 10) || 30;

      const dAmount = subtotal * (Math.max(0, discountPercent) / 100);
      const t = Math.max(0, subtotal - dAmount);

      const schedule = [];
      if (type === 'avista') {
        schedule.push(`À vista (${formatBRDate(baseDate)}): ${formatBRL(t)}`);
      } else {
        let remaining = t;
        let offset = 0;
        if (hasDown && down > 0) {
          const downAmount = Math.min(t, t * (Math.max(0, down) / 100));
          remaining = t - downAmount;
          schedule.push(`Entrada (${formatBRDate(baseDate)}): ${formatBRL(downAmount)}`);
          offset = interval;
        }
        const count = Math.max(1, installments);
        const base = Math.floor((remaining / count) * 100) / 100;
        let sum = 0;
        for (let i = 1; i <= count; i++) {
          sum += base;
          schedule.push(`Parcela ${i} (${formatBRDate(addDays(baseDate, offset + interval * (i - 1)))}): ${formatBRL(base)}`);
        }
        const diff = Math.round((remaining - sum) * 100) / 100;
        if (diff !== 0) {
          schedule[schedule.length - 1] = schedule[schedule.length - 1].replace(/:\s.*$/, `: ${formatBRL(base + diff)}`);
        }
      }

      const valueEl = row.querySelector('.poValue');
      if (valueEl) valueEl.textContent = formatBRL(t);

      return { subtotal, discountAmount: dAmount, total: t, schedule };
    };

    rows.forEach(r => {
      computeForRow(r);
    });

    const primary = computeForRow(primaryRow);
    subtotalEl.textContent = formatBRL(primary.subtotal);
    discountEl.textContent = formatBRL(primary.discountAmount);
    totalEl.textContent = formatBRL(primary.total);
    grandTotalEl.textContent = formatBRL(primary.total);
    scheduleEl.innerHTML = '<ul class="list-disc pl-5 space-y-1">' + primary.schedule.map(r => `<li>${r}</li>`).join('') + '</ul>';
  };

  const bindRow = (row) => {
    const svc = row.querySelector('.itemService');
    if (svc) {
      svc.addEventListener('change', () => {
        const opt = svc.options[svc.selectedIndex];
        const bonus = !!(opt && opt.dataset && (opt.dataset.bonus || '0') === '1');
        row.dataset.bonus = bonus ? '1' : '0';
        const desc = (opt && opt.dataset && opt.dataset.desc) ? opt.dataset.desc : '';
        const price = (opt && opt.dataset && opt.dataset.price) ? opt.dataset.price : '';
        const descInput = row.querySelector('input[name="item_description[]"]');
        const priceInput = row.querySelector('.itemPrice');
        if (descInput && desc) {
          descInput.value = desc;
        }
        if (priceInput && price) {
          priceInput.value = String(price).replace('.', ',');
        }
        recalc();
      });
    }
    row.querySelectorAll('.itemQty,.itemPrice').forEach(input => {
      input.addEventListener('input', recalc);
    });
    const removeBtn = row.querySelector('.removeItem');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        if (document.querySelectorAll('.itemRow').length > 1) {
          row.remove();
          recalc();
        }
      });
    }
  };

  document.querySelectorAll('.itemRow').forEach(bindRow);

  const refreshServices = async () => {
    try {
      const res = await fetch(basePath + '/api/services?active=1&include_bonus=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const json = await res.json().catch(() => null);
      const rows = json && Array.isArray(json.rows) ? json.rows : [];
      if (!Array.isArray(rows) || rows.length === 0) return;

      const servicesMap = new Map(rows.map(r => [String(r.id), r]));

      document.querySelectorAll('select.itemService').forEach(sel => {
        const current = String(sel.value || '0');
        const keep = current !== '0' && !servicesMap.has(current) && sel.selectedOptions && sel.selectedOptions.length ? sel.selectedOptions[0] : null;

        sel.innerHTML = '';
        const manual = document.createElement('option');
        manual.value = '0';
        manual.textContent = 'Manual';
        sel.appendChild(manual);

        if (keep) {
          const opt = document.createElement('option');
          opt.value = current;
          opt.textContent = keep.textContent || ('Serviço #' + current);
          opt.selected = true;
          sel.appendChild(opt);
        }

        rows.forEach(r => {
          const id = String(r.id || '0');
          const opt = document.createElement('option');
          opt.value = id;
          const bonus = Number(r.is_bonus || 0) === 1;
          opt.dataset.price = String(r.default_price || '0');
          opt.dataset.desc = String(r.description || '').slice(0, 255);
          opt.dataset.bonus = bonus ? '1' : '0';
          opt.textContent = String(r.name || '') + (bonus ? ' (bônus)' : '');
          if (id === current) opt.selected = true;
          sel.appendChild(opt);
        });
      });

      document.querySelectorAll('.itemRow').forEach(row => {
        const sel = row.querySelector('select.itemService');
        if (!sel) return;
        const opt = sel.options[sel.selectedIndex];
        if (!opt) return;
        row.dataset.bonus = (opt.dataset.bonus || '0') === '1' ? '1' : '0';
      });

      recalc();
    } catch (e) {
    }
  };

  refreshServices();

  addItemBtn.addEventListener('click', () => {
    const row = tpl.content.firstElementChild.cloneNode(true);
    tableBody.appendChild(row);
    bindRow(row);
    refreshServices();
    recalc();
  });

  recalc();

  const bindMilestoneRow = (row) => {
    const removeBtn = row.querySelector('.removeMilestone');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        if (document.querySelectorAll('.milestoneRow').length > 1) {
          row.remove();
        }
      });
    }
  };

  document.querySelectorAll('.milestoneRow').forEach(bindMilestoneRow);

  addMilestoneBtn.addEventListener('click', () => {
    const row = milestoneTpl.content.firstElementChild.cloneNode(true);
    milestonesBody.appendChild(row);
    bindMilestoneRow(row);
  });

  if (deliveryStartEl) deliveryStartEl.addEventListener('change', recalc);

  const reindexPaymentOptions = () => {
    const rows = Array.from(document.querySelectorAll('.paymentOptionRow'));
    rows.forEach((row, idx) => {
      row.dataset.index = String(idx);
      const radio = row.querySelector('input[type="radio"][name="payment_selected_index"]');
      if (radio) radio.value = String(idx);
    });
  };

  const bindPaymentRow = (row) => {
    const methodSel = row.querySelector('.poMethod');
    const inputs = row.querySelectorAll('input,select');
    inputs.forEach(i => i.addEventListener('input', recalc));
    inputs.forEach(i => i.addEventListener('change', recalc));

    if (methodSel) methodSel.addEventListener('change', () => {
      if (!isEdit) {
        const opt = methodSel.selectedOptions && methodSel.selectedOptions.length ? methodSel.selectedOptions[0] : null;
        if (opt && opt.dataset) {
          const typeEl = row.querySelector('.poType');
          const instEl = row.querySelector('.poInstallments');
          const intEl = row.querySelector('.poInterval');
          const hasDownEl = row.querySelector('.poHasDown');
          const downEl = row.querySelector('.poDown');

          if (typeEl && !typeEl.value) typeEl.value = opt.dataset.type || 'avista';
          if (instEl && (!instEl.value || instEl.value === '0')) instEl.value = opt.dataset.installments || '1';
          if (intEl && (!intEl.value || intEl.value === '0')) intEl.value = opt.dataset.interval || '30';
          if (hasDownEl) hasDownEl.value = (opt.dataset.hasDown || '0');
          if (downEl && (!downEl.value || downEl.value === '0')) downEl.value = opt.dataset.down || '0';
        }
      }
      recalc();
    });

    const removeBtn = row.querySelector('.removePaymentOption');
    if (removeBtn) removeBtn.addEventListener('click', () => {
      const rows = document.querySelectorAll('.paymentOptionRow');
      if (rows.length <= 1) return;
      row.remove();
      reindexPaymentOptions();
      recalc();
    });
  };

  document.querySelectorAll('.paymentOptionRow').forEach(bindPaymentRow);

  if (addPaymentOptionBtn) addPaymentOptionBtn.addEventListener('click', () => {
    const rows = document.querySelectorAll('.paymentOptionRow');
    if (rows.length >= 3) return;
    const clone = rows[0].cloneNode(true);
    clone.querySelectorAll('input').forEach(i => {
      if (i.type === 'radio') {
        i.checked = false;
        return;
      }
      i.value = '';
    });
    clone.querySelectorAll('select').forEach(s => {
      s.selectedIndex = 0;
    });
    const valueEl = clone.querySelector('.poValue');
    if (valueEl) valueEl.textContent = 'R$ 0,00';
    paymentOptionsBody.appendChild(clone);
    reindexPaymentOptions();
    bindPaymentRow(clone);
    recalc();
  });

</script>
