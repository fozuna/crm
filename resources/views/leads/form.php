<?php
use App\Core\UI;
use App\Core\View;

$lead = is_array($lead ?? null) ? $lead : [];
$errors = is_array($errors ?? null) ? $errors : [];
$stages = is_array($stages ?? null) ? $stages : [];
$histories = is_array($histories ?? null) ? $histories : [];
$interactions = is_array($interactions ?? null) ? $interactions : [];
$isEdit = (bool) ($isEdit ?? false);
$isConverted = !empty($lead['converted_at']);
$title = $isEdit ? 'Editar lead' : 'Novo lead';
$action = $isEdit ? ($base . '/leads/' . (int) ($lead['id'] ?? 0)) : ($base . '/leads');
$fieldError = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};
$readonly = $isConverted ? 'disabled' : '';
?>

<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
  <div>
    <div class="text-2xl font-semibold"><?= View::e($title) ?></div>
    <div class="text-slate-600 mt-1">Cadastro comercial independente do módulo de clientes ativos.</div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/leads') ?>">Voltar ao Kanban</a>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string) $error) ?></div>
<?php endif; ?>

<?php if ($isConverted): ?>
  <div class="mt-4 rounded bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    Este lead já foi convertido em cliente ativo e permanece disponível apenas para consulta e auditoria.
  </div>
<?php endif; ?>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,2fr),minmax(320px,1fr)]">
  <form method="post" action="<?= View::e($action) ?>" class="tr-card p-6 space-y-6">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <section class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Dados principais</h2>
        <?php if ($isEdit): ?>
          <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= View::e(\App\Services\LeadStages::color((string) ($lead['stage'] ?? ''))) ?>">
            <?= View::e(\App\Services\LeadStages::label((string) ($lead['stage'] ?? ''))) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="tr-label">Nome completo / Razão social</label>
          <input name="name" value="<?= View::e((string) ($lead['name'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('name') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('name')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Nome fantasia / empresa</label>
          <input name="company" value="<?= View::e((string) ($lead['company'] ?? '')) ?>" class="mt-1 tr-input" <?= $readonly ?>>
        </div>
        <div>
          <label class="tr-label">Responsável pelo contato</label>
          <input name="contact_person" value="<?= View::e((string) ($lead['contact_person'] ?? '')) ?>" class="mt-1 tr-input" <?= $readonly ?>>
        </div>
        <div>
          <label class="tr-label">Tipo de pessoa</label>
          <select id="person_type" name="person_type" class="mt-1 tr-input" required <?= $readonly ?>>
            <option value="pf" <?= (string) ($lead['person_type'] ?? 'pj') === 'pf' ? 'selected' : '' ?>>Pessoa física</option>
            <option value="pj" <?= (string) ($lead['person_type'] ?? 'pj') === 'pj' ? 'selected' : '' ?>>Pessoa jurídica</option>
          </select>
          <?php if ($fieldError('person_type') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('person_type')) ?></div><?php endif; ?>
        </div>
        <div>
          <label id="document_label" class="tr-label">CPF/CNPJ</label>
          <input id="document_number" name="document_number" value="<?= View::e((string) ($lead['document_number'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('document_number') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('document_number')) ?></div><?php endif; ?>
        </div>
        <div>
          <label id="date_label" class="tr-label">Data de abertura</label>
          <input id="birth_or_opening_date" name="birth_or_opening_date" type="date" value="<?= View::e((string) ($lead['birth_or_opening_date'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('birth_or_opening_date') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('birth_or_opening_date')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">E-mail principal</label>
          <input name="email" type="email" value="<?= View::e((string) ($lead['email'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('email') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('email')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Telefone principal</label>
          <input name="phone" value="<?= View::e((string) ($lead['phone'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('phone') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('phone')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Telefone secundário</label>
          <input name="secondary_phone" value="<?= View::e((string) ($lead['secondary_phone'] ?? '')) ?>" class="mt-1 tr-input" <?= $readonly ?>>
          <?php if ($fieldError('secondary_phone') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('secondary_phone')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Segmento de mercado</label>
          <input name="market_segment" value="<?= View::e((string) ($lead['market_segment'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('market_segment') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('market_segment')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Fonte de aquisição</label>
          <input name="acquisition_source" value="<?= View::e((string) ($lead['acquisition_source'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('acquisition_source') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('acquisition_source')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Estágio atual do Kanban</label>
          <select name="stage" class="mt-1 tr-input" required <?= $readonly ?>>
            <?php foreach ($stages as $value => $label): ?>
              <option value="<?= View::e((string) $value) ?>" <?= (string) ($lead['stage'] ?? '') === (string) $value ? 'selected' : '' ?>><?= View::e((string) $label) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($fieldError('stage') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('stage')) ?></div><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="space-y-4">
      <h2 class="text-lg font-semibold text-slate-900">Endereço</h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="tr-label">CEP</label>
          <input name="postal_code" value="<?= View::e((string) ($lead['postal_code'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('postal_code') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('postal_code')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Logradouro</label>
          <input name="street" value="<?= View::e((string) ($lead['street'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('street') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('street')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Número</label>
          <input name="street_number" value="<?= View::e((string) ($lead['street_number'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('street_number') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('street_number')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Complemento</label>
          <input name="address_complement" value="<?= View::e((string) ($lead['address_complement'] ?? '')) ?>" class="mt-1 tr-input" <?= $readonly ?>>
        </div>
        <div>
          <label class="tr-label">Bairro</label>
          <input name="neighborhood" value="<?= View::e((string) ($lead['neighborhood'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('neighborhood') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('neighborhood')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">Cidade</label>
          <input name="city" value="<?= View::e((string) ($lead['city'] ?? '')) ?>" class="mt-1 tr-input" required <?= $readonly ?>>
          <?php if ($fieldError('city') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('city')) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="tr-label">UF</label>
          <input name="state" maxlength="2" value="<?= View::e((string) ($lead['state'] ?? '')) ?>" class="mt-1 tr-input uppercase" required <?= $readonly ?>>
          <?php if ($fieldError('state') !== ''): ?><div class="mt-1 text-sm text-red-700"><?= View::e($fieldError('state')) ?></div><?php endif; ?>
        </div>
        <div class="md:col-span-2">
          <label class="tr-label">Observações</label>
          <textarea name="notes" rows="4" class="mt-1 tr-input" <?= $readonly ?>><?= View::e((string) ($lead['notes'] ?? '')) ?></textarea>
        </div>
      </div>
    </section>

    <?php if (!$isConverted): ?>
      <div class="flex items-center justify-end gap-3">
        <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar lead">
          <?= UI::icon('save') ?>
          <span class="sr-only">Salvar lead</span>
        </button>
      </div>
    <?php endif; ?>
  </form>

  <aside class="space-y-6">
    <?php if ($isEdit && !$isConverted): ?>
      <section class="tr-card p-6">
        <h2 class="text-lg font-semibold text-slate-900">Nova interação</h2>
        <p class="mt-1 text-sm text-slate-600">Registre e-mails, ligações, reuniões e notas do processo comercial.</p>
        <form method="post" action="<?= View::e($base . '/leads/' . (int) ($lead['id'] ?? 0) . '/interacoes') ?>" class="mt-4 space-y-3">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <div>
            <label class="tr-label">Tipo</label>
            <select name="kind" class="mt-1 tr-input">
              <option value="nota">Nota</option>
              <option value="email">E-mail</option>
              <option value="call">Ligação</option>
              <option value="meeting">Reunião</option>
            </select>
          </div>
          <div>
            <label class="tr-label">Descrição</label>
            <textarea name="note" rows="4" class="mt-1 tr-input" required></textarea>
          </div>
          <button class="tr-btn tr-btn--accent w-full" type="submit">Registrar interação</button>
        </form>
      </section>
    <?php endif; ?>

    <section class="tr-card p-6">
      <h2 class="text-lg font-semibold text-slate-900">Histórico do Kanban</h2>
      <div class="mt-4 space-y-3">
        <?php if (count($histories) === 0): ?>
          <div class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">Nenhuma movimentação registrada ainda.</div>
        <?php else: ?>
          <?php foreach ($histories as $history): ?>
            <div class="rounded-xl border border-slate-200 p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="text-sm font-semibold text-slate-900"><?= View::e((string) ($history['to_stage_label'] ?? '')) ?></div>
                <div class="text-xs text-slate-500"><?= View::e(date('d/m/Y H:i', strtotime((string) ($history['created_at'] ?? 'now')))) ?></div>
              </div>
              <div class="mt-1 text-sm text-slate-600">
                <?= View::e((string) ($history['from_stage_label'] ?? 'Inicial')) ?> -> <?= View::e((string) ($history['to_stage_label'] ?? '')) ?>
              </div>
              <div class="mt-1 text-xs uppercase tracking-wide text-slate-500">Ação: <?= View::e((string) ($history['action'] ?? 'move')) ?></div>
              <?php if (!empty($history['note'])): ?><div class="mt-2 text-sm text-slate-700"><?= nl2br(View::e((string) $history['note'])) ?></div><?php endif; ?>
              <div class="mt-2 text-xs text-slate-500">Responsável: <?= View::e((string) (($history['actor_name'] ?? '') !== '' ? $history['actor_name'] : 'Sistema')) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="tr-card p-6">
      <h2 class="text-lg font-semibold text-slate-900">Interações</h2>
      <div class="mt-4 space-y-3">
        <?php if (count($interactions) === 0): ?>
          <div class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">Nenhuma interação registrada.</div>
        <?php else: ?>
          <?php foreach ($interactions as $interaction): ?>
            <div class="rounded-xl border border-slate-200 p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="text-sm font-semibold text-slate-900"><?= View::e(strtoupper((string) ($interaction['kind'] ?? 'nota'))) ?></div>
                <div class="text-xs text-slate-500"><?= View::e(date('d/m/Y H:i', strtotime((string) ($interaction['created_at'] ?? 'now')))) ?></div>
              </div>
              <div class="mt-2 text-sm text-slate-700"><?= nl2br(View::e((string) ($interaction['note'] ?? ''))) ?></div>
              <div class="mt-2 text-xs text-slate-500">Responsável: <?= View::e((string) (($interaction['actor_name'] ?? '') !== '' ? $interaction['actor_name'] : 'Sistema')) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </aside>
</div>

<script>
  (function () {
    const personType = document.getElementById('person_type');
    const documentLabel = document.getElementById('document_label');
    const dateLabel = document.getElementById('date_label');

    function syncLabels() {
      const value = personType ? String(personType.value || 'pj') : 'pj';
      if (documentLabel) {
        documentLabel.textContent = value === 'pf' ? 'CPF' : 'CNPJ';
      }
      if (dateLabel) {
        dateLabel.textContent = value === 'pf' ? 'Data de nascimento' : 'Data de abertura';
      }
    }

    if (personType) {
      personType.addEventListener('change', syncLabels);
      syncLabels();
    }

    const toastType = <?= json_encode((string) ($toastType ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    const toastMessage = <?= json_encode((string) ($toastMessage ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    if (toastType && toastMessage && typeof window.trToast === 'function') {
      window.trToast(toastType, toastMessage);
    }
  })();
</script>
