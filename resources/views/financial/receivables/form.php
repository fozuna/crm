<?php
use App\Core\UI;
use App\Core\View;

$receivable = is_array($receivable ?? null) ? $receivable : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$catalog = is_array($catalog ?? null) ? $catalog : [];
$contracts = is_array($contracts ?? null) ? $contracts : [];
$categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
$costCenters = is_array($catalog['cost_centers'] ?? null) ? $catalog['cost_centers'] : [];
$bankAccounts = is_array($catalog['bank_accounts'] ?? null) ? $catalog['bank_accounts'] : [];
$projectHelpMessage = trim((string) ($projectHelpMessage ?? ''));
$isEdit = (int) ($receivable['id'] ?? 0) > 0;
$title = $isEdit ? 'Editar Conta a Receber' : 'Nova Conta a Receber';
$selectedClientId = (int) ($receivable['client_id'] ?? 0);
$selectedProjectId = (int) ($receivable['project_id'] ?? 0);
?>

<div class="flex items-center justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold"><?= View::e($title) ?></div>
    <div class="text-slate-600 mt-1">Cadastro financeiro com parcelamento, recorrencia e rastreabilidade por projeto.</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/financeiro/recebiveis') ?>" aria-label="Voltar">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<?php if (trim((string) ($error ?? '')) !== ''): ?>
  <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= View::e((string) $error) ?></div>
<?php endif; ?>

<form method="post" action="<?= View::e($isEdit ? ($base . '/financeiro/recebiveis/' . (int) $receivable['id']) : ($base . '/financeiro/recebiveis')) ?>" class="mt-6 space-y-6">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

  <div class="tr-card p-6">
    <div class="font-semibold">Vinculos e classificacao</div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
      <div>
        <label class="tr-label">Cliente</label>
        <select class="mt-1 tr-input" id="financialClientId" name="client_id" required>
          <option value="">Selecione</option>
          <?php foreach ($clients as $client): ?>
            <?php $id = (int) ($client['id'] ?? 0); $name = trim((string) (($client['company'] ?? '') !== '' ? $client['company'] : ($client['name'] ?? 'Cliente #' . $id))); ?>
            <option value="<?= $id ?>" <?= $selectedClientId === $id ? 'selected' : '' ?>><?= View::e($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Projeto</label>
        <select class="mt-1 tr-input" id="financialProjectId" name="project_id" data-selected-project-id="<?= $selectedProjectId ?>">
          <option value="0">Nao vincular</option>
          <?php foreach ($projects as $project): ?>
            <?php $id = (int) ($project['id'] ?? 0); ?>
            <option value="<?= $id ?>" <?= $selectedProjectId === $id ? 'selected' : '' ?>><?= View::e((string) ($project['title'] ?? 'Projeto #' . $id)) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="financialProjectHelp" class="tr-hint mt-1" aria-live="polite"><?= View::e($projectHelpMessage !== '' ? $projectHelpMessage : 'Selecione um cliente para listar apenas projetos vinculados a propostas aprovadas.') ?></div>
      </div>
      <div>
        <label class="tr-label">Contrato</label>
        <select class="mt-1 tr-input" name="contract_id">
          <option value="0">Nao vincular</option>
          <?php foreach ($contracts as $contract): ?>
            <?php $id = (int) ($contract['id'] ?? 0); ?>
            <option value="<?= $id ?>" <?= (int) ($receivable['contract_id'] ?? 0) === $id ? 'selected' : '' ?>>Contrato #<?= $id ?><?= (int) ($contract['proposal_id'] ?? 0) > 0 ? ' / Proposta #' . (int) $contract['proposal_id'] : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Status</label>
        <select class="mt-1 tr-input" name="status">
          <?php foreach (['pending' => 'Pendente', 'partially_paid' => 'Parcialmente pago', 'paid' => 'Pago', 'overdue' => 'Vencido', 'canceled' => 'Cancelado', 'renegotiated' => 'Renegociado'] as $key => $label): ?>
            <option value="<?= View::e($key) ?>" <?= (string) ($receivable['status'] ?? 'pending') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Categoria</label>
        <select class="mt-1 tr-input" name="category_id">
          <option value="0">Selecione</option>
          <?php foreach ($categories as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= (int) ($receivable['category_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= View::e((string) ($item['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Centro de custo</label>
        <select class="mt-1 tr-input" name="cost_center_id">
          <option value="0">Selecione</option>
          <?php foreach ($costCenters as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= (int) ($receivable['cost_center_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= View::e((string) ($item['name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Conta bancaria</label>
        <select class="mt-1 tr-input" name="bank_account_id">
          <option value="0">Selecione</option>
          <?php foreach ($bankAccounts as $item): ?>
            <?php $label = trim((string) (($item['bank_name'] ?? '') . ' - ' . ($item['account_name'] ?? ''))); ?>
            <option value="<?= (int) $item['id'] ?>" <?= (int) ($receivable['bank_account_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="tr-label">Competencia</label>
        <input class="mt-1 tr-input" type="date" name="competence_date" value="<?= View::e((string) ($receivable['competence_date'] ?? date('Y-m-d'))) ?>">
      </div>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Dados financeiros</div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
      <div class="xl:col-span-2">
        <label class="tr-label">Titulo</label>
        <input class="mt-1 tr-input" type="text" name="title" value="<?= View::e((string) ($receivable['title'] ?? '')) ?>" required>
      </div>
      <div>
        <label class="tr-label">Numero da fatura</label>
        <input class="mt-1 tr-input" type="text" name="invoice_number" value="<?= View::e((string) ($receivable['invoice_number'] ?? '')) ?>">
      </div>
      <div>
        <label class="tr-label">Referencia externa</label>
        <input class="mt-1 tr-input" type="text" name="external_reference" value="<?= View::e((string) ($receivable['external_reference'] ?? '')) ?>">
      </div>
      <div class="xl:col-span-4">
        <label class="tr-label">Descricao</label>
        <textarea class="mt-1 tr-input" name="description" rows="3"><?= View::e((string) ($receivable['description'] ?? '')) ?></textarea>
      </div>
      <div>
        <label class="tr-label">Valor original</label>
        <input class="mt-1 tr-input" data-money="brl" type="text" name="original_amount" value="<?= View::e(number_format((float) ($receivable['original_amount'] ?? 0), 2, ',', '.')) ?>" required>
      </div>
      <div>
        <label class="tr-label">Desconto</label>
        <input class="mt-1 tr-input" data-money="brl" type="text" name="discount_amount" value="<?= View::e(number_format((float) ($receivable['discount_amount'] ?? 0), 2, ',', '.')) ?>">
      </div>
      <div>
        <label class="tr-label">Juros</label>
        <input class="mt-1 tr-input" data-money="brl" type="text" name="interest_amount" value="<?= View::e(number_format((float) ($receivable['interest_amount'] ?? 0), 2, ',', '.')) ?>">
      </div>
      <div>
        <label class="tr-label">Multa</label>
        <input class="mt-1 tr-input" data-money="brl" type="text" name="fine_amount" value="<?= View::e(number_format((float) ($receivable['fine_amount'] ?? 0), 2, ',', '.')) ?>">
      </div>
      <div>
        <label class="tr-label">Data de emissao</label>
        <input class="mt-1 tr-input" type="date" name="issue_date" value="<?= View::e((string) ($receivable['issue_date'] ?? date('Y-m-d'))) ?>">
      </div>
      <div>
        <label class="tr-label">Data de vencimento</label>
        <input class="mt-1 tr-input" type="date" name="due_date" value="<?= View::e((string) ($receivable['due_date'] ?? date('Y-m-d'))) ?>" required>
      </div>
      <div>
        <label class="tr-label">Forma de pagamento</label>
        <input class="mt-1 tr-input" type="text" name="payment_method" value="<?= View::e((string) ($receivable['payment_method'] ?? '')) ?>" placeholder="PIX, boleto, TED...">
      </div>
      <div>
        <label class="tr-label">Canal de pagamento</label>
        <input class="mt-1 tr-input" type="text" name="payment_channel" value="<?= View::e((string) ($receivable['payment_channel'] ?? '')) ?>" placeholder="Banco, gateway, caixa...">
      </div>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Parcelamento e recorrencia</div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
      <div>
        <label class="tr-label">Numero da parcela</label>
        <input class="mt-1 tr-input" type="number" min="1" name="installment_number" value="<?= (int) ($receivable['installment_number'] ?? 1) ?>">
      </div>
      <div>
        <label class="tr-label">Total de parcelas</label>
        <input class="mt-1 tr-input" type="number" min="1" name="total_installments" value="<?= (int) ($receivable['total_installments'] ?? 1) ?>">
      </div>
      <div>
        <label class="tr-label">Recorrencia em meses</label>
        <input class="mt-1 tr-input" type="number" min="0" name="recurrence_interval_months" value="<?= (int) ($receivable['recurrence_interval_months'] ?? 0) ?>">
        <div class="tr-hint mt-1">Use `1` para mensal, `0` para sem recorrencia automatica.</div>
      </div>
      <div class="md:col-span-3">
        <label class="tr-label">Observacoes</label>
        <textarea class="mt-1 tr-input" name="notes" rows="4"><?= View::e((string) ($receivable['notes'] ?? '')) ?></textarea>
      </div>
    </div>
  </div>

  <div class="flex flex-wrap gap-2">
    <button class="tr-btn tr-btn--accent" type="submit"><?= $isEdit ? 'Salvar alteracoes' : 'Criar recebivel' ?></button>
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis') ?>">Cancelar</a>
  </div>
</form>

<script>
  (function () {
    const clientSelect = document.getElementById('financialClientId');
    const projectSelect = document.getElementById('financialProjectId');
    const help = document.getElementById('financialProjectHelp');
    const base = <?= json_encode($base, JSON_UNESCAPED_UNICODE) ?>;
    const initialSelectedProjectId = Number(projectSelect && projectSelect.getAttribute('data-selected-project-id') ? projectSelect.getAttribute('data-selected-project-id') : '0');

    function setHelp(message) {
      if (help) {
        help.textContent = message || '';
      }
    }

    function renderProjects(projects, selectedProjectId) {
      if (!projectSelect) {
        return;
      }

      projectSelect.innerHTML = '';
      const emptyOption = document.createElement('option');
      emptyOption.value = '0';
      emptyOption.textContent = 'Nao vincular';
      projectSelect.appendChild(emptyOption);

      projects.forEach(function (project) {
        const option = document.createElement('option');
        option.value = String(project.id || 0);
        option.textContent = String(project.title || ('Projeto #' + String(project.id || '0')));
        if (Number(option.value) === Number(selectedProjectId || 0)) {
          option.selected = true;
        }
        projectSelect.appendChild(option);
      });
    }

    function setLoading(loading) {
      if (!projectSelect) {
        return;
      }
      projectSelect.disabled = loading;
      if (loading) {
        setHelp('Carregando projetos aprovados do cliente...');
      }
    }

    function loadApprovedProjects(selectedProjectId) {
      if (!clientSelect || !projectSelect) {
        return;
      }

      const clientId = Number(clientSelect.value || '0');
      if (clientId <= 0) {
        renderProjects([], 0);
        setHelp('Selecione um cliente para listar apenas projetos vinculados a propostas aprovadas.');
        return;
      }

      setLoading(true);
      fetch(base + '/api/financial/clients/' + clientId + '/approved-projects', {
        headers: {
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      }).then(function (response) {
        if (!response.ok) {
          throw new Error('Falha ao carregar projetos aprovados.');
        }
        return response.json();
      }).then(function (payload) {
        const data = payload && payload.data ? payload.data : {};
        const projects = Array.isArray(data.projects) ? data.projects : [];
        renderProjects(projects, selectedProjectId);
        setHelp(String(data.message || (projects.length > 0 ? ('Foram encontrados ' + projects.length + ' projeto(s) aprovado(s) para este cliente.') : 'Este cliente nao possui projetos vinculados a propostas aprovadas.')));
      }).catch(function (error) {
        renderProjects([], 0);
        setHelp('Nao foi possivel carregar os projetos aprovados do cliente.');
        if (window.trToast) {
          window.trToast('error', error && error.message ? error.message : 'Erro ao carregar projetos aprovados.');
        }
      }).finally(function () {
        setLoading(false);
      });
    }

    if (clientSelect) {
      clientSelect.addEventListener('change', function () {
        loadApprovedProjects(0);
      });
    }

    if (clientSelect && Number(clientSelect.value || '0') > 0 && initialSelectedProjectId > 0) {
      loadApprovedProjects(initialSelectedProjectId);
    }
  })();
</script>
