<?php
use App\Core\View;
use App\Core\UI;

$templates = is_array($templates ?? null) ? $templates : [];
$placeholders = is_array($placeholders ?? null) ? $placeholders : [];
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Templates de Contrato</div>
    <div class="text-slate-600 mt-1">Personalize o texto do contrato e os critérios automáticos de geração.</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/contratos') ?>" aria-label="Voltar para contratos">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-6">
  <div class="xl:col-span-2 space-y-4">
    <?php foreach ($templates as $template): ?>
      <?php
        $criteria = json_decode((string) ($template['auto_criteria_json'] ?? ''), true);
        $criteria = is_array($criteria) ? $criteria : [];
      ?>
      <form method="post" action="<?= View::e($base . '/contratos/templates/' . $template['id']) ?>" class="tr-card p-6 space-y-4">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <div class="flex items-center justify-between gap-4">
          <div class="font-semibold"><?= View::e((string) ($template['name'] ?? 'Template')) ?></div>
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" <?= (int) ($template['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
            <span>Template ativo</span>
          </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="tr-label">Nome</label>
            <input class="mt-1 tr-input" name="name" value="<?= View::e((string) ($template['name'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Formalização padrão</label>
            <select class="mt-1 tr-input" name="signature_mode_default">
              <option value="digital" <?= (string) ($template['signature_mode_default'] ?? '') === 'digital' ? 'selected' : '' ?>>Assinatura digital</option>
              <option value="print" <?= (string) ($template['signature_mode_default'] ?? '') === 'print' ? 'selected' : '' ?>>Impressão</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="tr-label">Descrição</label>
            <input class="mt-1 tr-input" name="description" value="<?= View::e((string) ($template['description'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Título do cabeçalho</label>
            <input class="mt-1 tr-input" name="header_title" value="<?= View::e((string) ($template['header_title'] ?? '')) ?>">
          </div>
          <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm">
              <input type="checkbox" name="require_signature_default" value="1" <?= (int) ($template['require_signature_default'] ?? 0) === 1 ? 'checked' : '' ?>>
              <span>Exigir assinatura por padrão</span>
            </label>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-4">
          <div class="font-semibold">Critérios automáticos</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="inline-flex items-center gap-2 text-sm">
              <input type="checkbox" name="criteria_enabled" value="1" <?= ($criteria['enabled'] ?? true) ? 'checked' : '' ?>>
              <span>Habilitar geração automática na aprovação</span>
            </label>
            <div>
              <label class="tr-label">Valor mínimo da proposta</label>
              <input class="mt-1 tr-input" name="criteria_min_total" data-money="brl" value="<?= View::e(number_format((float) ($criteria['min_total'] ?? 0), 2, ',', '.')) ?>">
            </div>
            <div>
              <label class="tr-label">IDs de clientes</label>
              <input class="mt-1 tr-input" name="criteria_required_client_ids" value="<?= View::e(implode(', ', (array) ($criteria['required_client_ids'] ?? []))) ?>" placeholder="Ex.: 1, 5, 18">
            </div>
            <div>
              <label class="tr-label">IDs de serviços</label>
              <input class="mt-1 tr-input" name="criteria_required_service_ids" value="<?= View::e(implode(', ', (array) ($criteria['required_service_ids'] ?? []))) ?>" placeholder="Ex.: 2, 7">
            </div>
            <div class="md:col-span-2">
              <label class="tr-label">Palavras-chave dos serviços</label>
              <input class="mt-1 tr-input" name="criteria_service_keywords" value="<?= View::e(implode(', ', (array) ($criteria['service_keywords'] ?? []))) ?>" placeholder="Ex.: mensalidade, suporte, desenvolvimento">
            </div>
          </div>
        </div>

        <div>
          <label class="tr-label">Corpo do template</label>
          <textarea class="mt-1 tr-input min-h-[320px]" name="body_template"><?= View::e((string) ($template['body_template'] ?? '')) ?></textarea>
        </div>
        <div>
          <label class="tr-label">Observações de rodapé</label>
          <textarea class="mt-1 tr-input min-h-[80px]" name="footer_notes"><?= View::e((string) ($template['footer_notes'] ?? '')) ?></textarea>
        </div>
        <div class="flex justify-end">
          <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar template">
            <?= UI::icon('save') ?>
            <span class="sr-only">Salvar</span>
          </button>
        </div>
      </form>
    <?php endforeach; ?>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Placeholders disponíveis</div>
    <ul class="mt-4 space-y-2 text-sm text-slate-700">
      <?php foreach ($placeholders as $placeholder): ?>
        <li><code><?= View::e((string) $placeholder) ?></code></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
