<?php
use App\Core\UI;
use App\Core\View;
use App\Services\ServiceOrderStatus;
use App\Services\ServiceOrderType;

$title = 'Ordens de Serviço';
$filters = is_array($filters ?? null) ? $filters : [];
$data = is_array($data ?? null) ? $data : [];
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$page = (int) ($data['page'] ?? 1);
$pages = (int) ($data['pages'] ?? 1);
$total = (int) ($data['total'] ?? 0);
$ok = trim((string) ($ok ?? ''));
$error = trim((string) ($error ?? ''));

$linkWith = static function (array $baseParams, array $override): string {
    $params = array_merge($baseParams, $override);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 0) {
            unset($params[$key]);
        }
    }
    return '?' . http_build_query($params);
};

$baseParams = [
    'q' => (string) ($filters['q'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
    'type' => (string) ($filters['type'] ?? ''),
    'client_id' => (int) ($filters['client_id'] ?? 0),
    'assigned_user_id' => (int) ($filters['assigned_user_id'] ?? 0),
    'billable' => (string) ($filters['billable'] ?? ''),
    'sort' => (string) ($filters['sort'] ?? 'opened_desc'),
];
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Ordens de Serviço</div>
    <div class="text-slate-600 mt-1">Controle de demandas pontuais, anexos, histórico e faturamento por cliente.</div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/relatorios') ?>" aria-label="Relatórios de OS">
      <?= UI::icon('chart') ?>
      <span class="sr-only">Relatórios</span>
    </a>
    <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/ordens-servico/nova') ?>" aria-label="Nova ordem de serviço">
      <?= UI::icon('plus') ?>
      <span class="sr-only">Nova</span>
    </a>
  </div>
</div>

<?php if ($ok !== ''): ?>
  <div class="mt-6 tr-card p-4 border border-emerald-200 bg-emerald-50 text-emerald-700 text-sm"><?= View::e($ok) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="mt-6 tr-card p-4 border border-red-200 bg-red-50 text-red-700 text-sm"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/ordens-servico') ?>" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="xl:col-span-2">
      <label class="tr-label">Pesquisar</label>
      <input name="q" class="mt-1 tr-input" value="<?= View::e((string) ($filters['q'] ?? '')) ?>" placeholder="Número da OS, cliente, serviço ou responsável">
    </div>
    <div>
      <label class="tr-label">Status</label>
      <select name="status" class="mt-1 tr-input">
        <option value="">Todos</option>
        <?php foreach ($statusOptions as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= (string) ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Tipo</label>
      <select name="type" class="mt-1 tr-input">
        <option value="">Todos</option>
        <?php foreach ($typeOptions as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= (string) ($filters['type'] ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Cliente</label>
      <select name="client_id" class="mt-1 tr-input">
        <option value="0">Todos</option>
        <?php foreach ($clients as $client): ?>
          <?php $clientId = (int) ($client['id'] ?? 0); ?>
          <option value="<?= $clientId ?>" <?= (int) ($filters['client_id'] ?? 0) === $clientId ? 'selected' : '' ?>><?= View::e((string) ($client['company'] ?? ('Cliente #' . $clientId))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Responsável</label>
      <select name="assigned_user_id" class="mt-1 tr-input">
        <option value="0">Todos</option>
        <?php foreach ($users as $user): ?>
          <?php $userId = (int) ($user['id'] ?? 0); ?>
          <option value="<?= $userId ?>" <?= (int) ($filters['assigned_user_id'] ?? 0) === $userId ? 'selected' : '' ?>><?= View::e((string) ($user['name'] ?? 'Usuário #' . $userId)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Cobrança</label>
      <select name="billable" class="mt-1 tr-input">
        <option value="">Todos</option>
        <option value="1" <?= (string) ($filters['billable'] ?? '') === '1' ? 'selected' : '' ?>>Faturáveis</option>
        <option value="0" <?= (string) ($filters['billable'] ?? '') === '0' ? 'selected' : '' ?>>Não faturáveis</option>
      </select>
    </div>
    <div>
      <label class="tr-label">Ordenar</label>
      <select name="sort" class="mt-1 tr-input">
        <option value="opened_desc" <?= (string) ($filters['sort'] ?? '') === 'opened_desc' ? 'selected' : '' ?>>Abertura recente</option>
        <option value="opened_asc" <?= (string) ($filters['sort'] ?? '') === 'opened_asc' ? 'selected' : '' ?>>Abertura antiga</option>
        <option value="due_asc" <?= (string) ($filters['sort'] ?? '') === 'due_asc' ? 'selected' : '' ?>>Previsão próxima</option>
        <option value="due_desc" <?= (string) ($filters['sort'] ?? '') === 'due_desc' ? 'selected' : '' ?>>Previsão distante</option>
        <option value="client_asc" <?= (string) ($filters['sort'] ?? '') === 'client_asc' ? 'selected' : '' ?>>Cliente</option>
        <option value="status_asc" <?= (string) ($filters['sort'] ?? '') === 'status_asc' ? 'selected' : '' ?>>Status</option>
      </select>
    </div>
    <div class="flex items-end justify-end gap-2 md:col-span-2 xl:col-span-1">
      <button class="tr-btn" type="submit">Filtrar</button>
    </div>
  </form>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="p-6 flex items-center justify-between">
    <div class="font-semibold">Listagem</div>
    <div class="text-sm text-slate-600"><?= $total ?> registros</div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">OS</th>
          <th class="text-left py-3 px-4">Serviço</th>
          <th class="text-left py-3 px-4">Cliente</th>
          <th class="text-left py-3 px-4">Responsável</th>
          <th class="text-left py-3 px-4">Tipo</th>
          <th class="text-left py-3 px-4">Status</th>
          <th class="text-left py-3 px-4">Valor</th>
          <th class="text-left py-3 px-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="8">Nenhuma ordem de serviço encontrada.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $id = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $type = (string) ($row['type'] ?? '');
            $badgeClass = ServiceOrderStatus::badgeClass($status);
            $clientLabel = trim((string) ($row['client_company'] ?? '')) !== '' ? (string) $row['client_company'] : (string) ($row['client_name'] ?? '');
          ?>
          <tr class="border-t">
            <td class="px-4 py-3">
              <div class="font-semibold"><?= View::e((string) ($row['numero_os'] ?? '')) ?></div>
              <div class="text-xs text-slate-500 mt-1"><?= View::e((string) ($row['opened_at'] ?? '')) ?></div>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium"><?= View::e((string) ($row['service_name'] ?? '')) ?></div>
              <div class="text-xs text-slate-500 mt-1"><?= View::e((string) ($row['contact_name'] ?? '')) ?></div>
            </td>
            <td class="px-4 py-3"><?= View::e($clientLabel) ?></td>
            <td class="px-4 py-3"><?= View::e((string) ($row['assigned_user_name'] ?? 'Não definido')) ?></td>
            <td class="px-4 py-3"><?= View::e(ServiceOrderType::label($type)) ?></td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold <?= View::e($badgeClass) ?>"><?= View::e(ServiceOrderStatus::label($status)) ?></span>
            </td>
            <td class="px-4 py-3"><?= (int) ($row['billable'] ?? 0) === 1 ? 'R$ ' . number_format((float) ($row['final_amount'] ?? 0), 2, ',', '.') : 'Não faturável' ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/editar') ?>" aria-label="Editar OS">
                  <?= UI::icon('edit') ?>
                  <span class="sr-only">Editar</span>
                </a>
                <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/pdf') ?>" aria-label="PDF da OS" target="_blank" rel="noopener">
                  <?= UI::icon('pdf') ?>
                  <span class="sr-only">PDF</span>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-6 flex items-center justify-between">
  <div class="text-sm text-slate-600">Página <?= $page ?> de <?= $pages ?></div>
  <div class="flex gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/ordens-servico' . $linkWith($baseParams, ['page' => max(1, $page - 1)])) ?>">Anterior</a>
    <a class="tr-btn" href="<?= View::e($base . '/ordens-servico' . $linkWith($baseParams, ['page' => min($pages, $page + 1)])) ?>">Próxima</a>
  </div>
</div>
