<?php
use App\Core\View;
use App\Core\UI;
$isEdit = is_array($client) && isset($client['id']);
$title = $isEdit ? 'Editar cliente' : 'Novo cliente';
$action = $isEdit ? ($base . '/clientes/' . $client['id']) : ($base . '/clientes');
$errors = is_array($errors ?? null) ? $errors : [];
$today = date('Y-m-d');
$fieldError = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};
$isChecked = static function (mixed $value): bool {
    $value = strtolower(trim((string) $value));
    return in_array($value, ['1', 'true', 'on', 'yes', 'sim'], true);
};
$hasHostingContract = $isChecked($client['has_hosting_contract'] ?? null);
$managesDomain = $isChecked($client['manages_domain'] ?? null);
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold"><?= View::e($title) ?></div>
    <div class="text-slate-600 mt-1">Dados básicos para propostas e contratos</div>
  </div>
  <a class="text-slate-700" href="<?= View::e($base . '/clientes') ?>">Voltar</a>
</div>

<?php if (!empty($error)): ?>
  <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string)$error) ?></div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="mt-6 tr-card p-6 space-y-4">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="tr-label">Nome</label>
      <input name="name" value="<?= View::e((string)($client['name'] ?? '')) ?>" class="mt-1 tr-input" required>
      <?php if ($fieldError('name') !== ''): ?>
        <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('name')) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <label class="tr-label">Empresa</label>
      <input name="company" value="<?= View::e((string)($client['company'] ?? '')) ?>" class="mt-1 tr-input">
    </div>
    <div>
      <label class="tr-label">E-mail</label>
      <input name="email" type="email" value="<?= View::e((string)($client['email'] ?? '')) ?>" class="mt-1 tr-input" autocomplete="email">
      <?php if ($fieldError('email') !== ''): ?>
        <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('email')) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <label class="tr-label">Telefone</label>
      <input name="phone" value="<?= View::e((string)($client['phone'] ?? '')) ?>" class="mt-1 tr-input" inputmode="tel">
    </div>
    <div>
      <label class="tr-label">Responsável</label>
      <input name="contact_person" value="<?= View::e((string)($client['contact_person'] ?? '')) ?>" class="mt-1 tr-input" placeholder="Nome da pessoa responsável">
    </div>
    <div>
      <label class="tr-label">Status</label>
      <?php $status = (string)($client['status'] ?? 'lead'); ?>
      <select name="status" class="mt-1 tr-input" required>
        <option value="lead" <?= $status === 'lead' ? 'selected' : '' ?>>Lead</option>
        <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Cliente ativo</option>
      </select>
      <div class="tr-hint mt-1">Use “Lead” para pré-venda e “Cliente ativo” após fechamento.</div>
      <?php if ($fieldError('status') !== ''): ?>
        <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('status')) ?></div>
      <?php endif; ?>
    </div>
    <div class="md:col-span-2">
      <label class="tr-label">Indicação / projeto realizado</label>
      <input name="project_reference" value="<?= View::e((string)($client['project_reference'] ?? '')) ?>" class="mt-1 tr-input" placeholder="Ex.: Indicado por Fulano / Site institucional 2026">
    </div>
    <div class="md:col-span-2">
      <label class="tr-label">Logo do cliente</label>
      <input name="logo" type="file" accept="image/*" class="mt-1 tr-input">
      <div class="tr-hint mt-1">A imagem será armazenada sem redimensionamento.</div>
    </div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 pt-2">
    <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-4">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Contrato de Hospedagem</h2>
          <p class="text-sm text-slate-600 mt-1">Campos opcionais para controle de vencimento e renovação da hospedagem.</p>
        </div>
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
          <input id="has_hosting_contract" type="checkbox" name="has_hosting_contract" value="1" <?= $hasHostingContract ? 'checked' : '' ?>>
          <span>Cliente possui contrato de hospedagem</span>
        </label>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="tr-label">Valor do contrato de hospedagem</label>
          <input
            id="hosting_contract_amount"
            name="hosting_contract_amount"
            value="<?= View::e((string) ($client['hosting_contract_amount'] ?? '')) ?>"
            class="mt-1 tr-input"
            type="text"
            inputmode="numeric"
            data-money="brl"
            placeholder="0,00"
            <?= $hasHostingContract ? '' : 'disabled' ?>
          >
          <div class="tr-hint mt-1">Formato monetário em tempo real no padrão brasileiro.</div>
          <?php if ($fieldError('hosting_contract_amount') !== ''): ?>
            <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('hosting_contract_amount')) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <label class="tr-label">Data de vencimento da hospedagem</label>
          <input
            id="hosting_due_date"
            name="hosting_due_date"
            value="<?= View::e((string) ($client['hosting_due_date'] ?? '')) ?>"
            class="mt-1 tr-input"
            type="date"
            min="<?= View::e($today) ?>"
            <?= $hasHostingContract ? '' : 'disabled' ?>
          >
          <div class="tr-hint mt-1">Use uma data futura no formato exibido pelo seletor.</div>
          <?php if ($fieldError('hosting_due_date') !== ''): ?>
            <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('hosting_due_date')) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <label class="tr-label">Prazo de renovação (dias)</label>
          <input
            id="hosting_renewal_days"
            name="hosting_renewal_days"
            value="<?= View::e((string) ($client['hosting_renewal_days'] ?? '45')) ?>"
            class="mt-1 tr-input"
            type="number"
            min="1"
            max="45"
            step="1"
            <?= $hasHostingContract ? '' : 'disabled' ?>
          >
          <div class="tr-hint mt-1">Default 45 dias, com faixa permitida entre 1 e 45.</div>
          <?php if ($fieldError('hosting_renewal_days') !== ''): ?>
            <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('hosting_renewal_days')) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <label class="tr-label">Data de renovação sugerida</label>
          <input
            id="hosting_renewal_suggested_date"
            value="<?= View::e((string) ($client['hosting_renewal_suggested_date'] ?? '')) ?>"
            class="mt-1 tr-input bg-slate-100"
            type="date"
            readonly
            disabled
          >
          <div id="hosting_renewal_feedback" class="tr-hint mt-1">A sugestão é calculada automaticamente conforme vencimento + prazo.</div>
        </div>
      </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-4">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Registro de Domínio</h2>
          <p class="text-sm text-slate-600 mt-1">Campos opcionais para controle do domínio gerenciado pela equipe.</p>
        </div>
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
          <input id="manages_domain" type="checkbox" name="manages_domain" value="1" <?= $managesDomain ? 'checked' : '' ?>>
          <span>Gerenciamos o registro de domínio do cliente</span>
        </label>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="tr-label">Data de vencimento do domínio</label>
          <input
            id="domain_due_date"
            name="domain_due_date"
            value="<?= View::e((string) ($client['domain_due_date'] ?? '')) ?>"
            class="mt-1 tr-input"
            type="date"
            min="<?= View::e($today) ?>"
            <?= $managesDomain ? '' : 'disabled' ?>
          >
          <div class="tr-hint mt-1">Não são permitidas datas passadas.</div>
          <?php if ($fieldError('domain_due_date') !== ''): ?>
            <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('domain_due_date')) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <label class="tr-label">Valor do registro/renovação de domínio</label>
          <input
            id="domain_amount"
            name="domain_amount"
            value="<?= View::e((string) ($client['domain_amount'] ?? '')) ?>"
            class="mt-1 tr-input"
            type="text"
            inputmode="numeric"
            data-money="brl"
            placeholder="0,00"
            <?= $managesDomain ? '' : 'disabled' ?>
          >
          <div class="tr-hint mt-1">Campo obrigatório apenas quando o gerenciamento do domínio estiver ativo.</div>
          <?php if ($fieldError('domain_amount') !== ''): ?>
            <div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('domain_amount')) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="flex items-center justify-end gap-3">
    <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar">
      <?= UI::icon('save') ?>
      <span class="sr-only">Salvar</span>
    </button>
  </div>
</form>

<script>
  (function () {
    const hostingToggle = document.getElementById('has_hosting_contract');
    const hostingAmount = document.getElementById('hosting_contract_amount');
    const hostingDueDate = document.getElementById('hosting_due_date');
    const hostingRenewalDays = document.getElementById('hosting_renewal_days');
    const hostingRenewalSuggestedDate = document.getElementById('hosting_renewal_suggested_date');
    const hostingRenewalFeedback = document.getElementById('hosting_renewal_feedback');

    const domainToggle = document.getElementById('manages_domain');
    const domainDueDate = document.getElementById('domain_due_date');
    const domainAmount = document.getElementById('domain_amount');

    function setGroupState(toggle, fields) {
      const enabled = !!(toggle && toggle.checked);
      fields.forEach(function (field) {
        if (!field) {
          return;
        }
        field.disabled = !enabled;
        if (field.dataset.requiredWhenChecked === '1') {
          field.required = enabled;
        }
        if (!enabled && field.dataset.clearWhenDisabled === '1') {
          field.value = '';
        }
      });
    }

    function formatBrDate(isoDate) {
      if (!isoDate || !/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) {
        return '';
      }
      const parts = isoDate.split('-');
      return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function updateHostingRenewalSuggestion() {
      if (!hostingToggle || !hostingToggle.checked) {
        if (hostingRenewalSuggestedDate) {
          hostingRenewalSuggestedDate.value = '';
        }
        if (hostingRenewalFeedback) {
          hostingRenewalFeedback.textContent = 'A sugestão é calculada automaticamente conforme vencimento + prazo.';
          hostingRenewalFeedback.className = 'tr-hint mt-1';
        }
        if (hostingRenewalDays) {
          hostingRenewalDays.setCustomValidity('');
        }
        return;
      }

      const dueDate = hostingDueDate ? String(hostingDueDate.value || '') : '';
      const renewalDays = hostingRenewalDays ? Number(hostingRenewalDays.value || 0) : 0;

      if (!dueDate || !Number.isFinite(renewalDays) || renewalDays < 1) {
        if (hostingRenewalSuggestedDate) {
          hostingRenewalSuggestedDate.value = '';
        }
        if (hostingRenewalDays) {
          hostingRenewalDays.setCustomValidity('');
        }
        return;
      }

      if (renewalDays > 45) {
        if (hostingRenewalDays) {
          hostingRenewalDays.setCustomValidity('O prazo de renovação não pode ultrapassar 45 dias.');
          hostingRenewalDays.reportValidity();
        }
        if (hostingRenewalFeedback) {
          hostingRenewalFeedback.textContent = 'Prazo inválido: o limite máximo para renovação é de 45 dias.';
          hostingRenewalFeedback.className = 'mt-1 text-sm text-red-700';
        }
        if (hostingRenewalSuggestedDate) {
          hostingRenewalSuggestedDate.value = '';
        }
        return;
      }

      if (hostingRenewalDays) {
        hostingRenewalDays.setCustomValidity('');
      }

      const baseDate = new Date(dueDate + 'T12:00:00');
      if (Number.isNaN(baseDate.getTime())) {
        if (hostingRenewalSuggestedDate) {
          hostingRenewalSuggestedDate.value = '';
        }
        return;
      }

      baseDate.setDate(baseDate.getDate() + renewalDays);
      const year = baseDate.getFullYear();
      const month = String(baseDate.getMonth() + 1).padStart(2, '0');
      const day = String(baseDate.getDate()).padStart(2, '0');
      const suggested = year + '-' + month + '-' + day;

      if (hostingRenewalSuggestedDate) {
        hostingRenewalSuggestedDate.value = suggested;
      }
      if (hostingRenewalFeedback) {
        hostingRenewalFeedback.textContent = 'Renovação sugerida para ' + formatBrDate(suggested) + '.';
        hostingRenewalFeedback.className = 'tr-hint mt-1';
      }
    }

    [hostingAmount, hostingDueDate, hostingRenewalDays].forEach(function (field) {
      if (field) {
        field.dataset.requiredWhenChecked = '1';
      }
    });

    [domainDueDate, domainAmount].forEach(function (field) {
      if (field) {
        field.dataset.requiredWhenChecked = '1';
      }
    });

    function syncStates() {
      setGroupState(hostingToggle, [hostingAmount, hostingDueDate, hostingRenewalDays]);
      setGroupState(domainToggle, [domainDueDate, domainAmount]);
      updateHostingRenewalSuggestion();
    }

    if (hostingToggle) {
      hostingToggle.addEventListener('change', syncStates);
    }
    if (domainToggle) {
      domainToggle.addEventListener('change', syncStates);
    }
    if (hostingDueDate) {
      hostingDueDate.addEventListener('input', updateHostingRenewalSuggestion);
      hostingDueDate.addEventListener('change', updateHostingRenewalSuggestion);
    }
    if (hostingRenewalDays) {
      hostingRenewalDays.addEventListener('input', updateHostingRenewalSuggestion);
      hostingRenewalDays.addEventListener('change', updateHostingRenewalSuggestion);
    }

    syncStates();
  })();
</script>
