<?php
use App\Core\View;
use App\Core\UI;

$versions = is_array($versions ?? null) ? $versions : [];
$notifications = is_array($notifications ?? null) ? $notifications : [];
$body = trim((string) ($contract['rendered_body'] ?? ''));
$summary = json_decode((string) ($contract['rendered_summary'] ?? ''), true);
$summary = is_array($summary) ? $summary : [];
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold"><?= View::e((string) ($contract['contract_number'] ?? 'Contrato')) ?></div>
    <div class="text-slate-600 mt-1"><?= View::e((string) ($contract['title'] ?? '')) ?> · Proposta #<?= (int) ($contract['proposal_id'] ?? 0) ?></div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-icon-btn" href="<?= View::e($base . '/contratos') ?>" aria-label="Voltar">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/contratos/' . $contract['id'] . '/imprimir') ?>" target="_blank" aria-label="Imprimir contrato">
      <?= UI::icon('print') ?>
      <span class="sr-only">Imprimir</span>
    </a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/contratos/' . $contract['id'] . '/pdf') ?>" target="_blank" aria-label="PDF do contrato">
      <?= UI::icon('pdf') ?>
      <span class="sr-only">PDF</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Status</div>
    <div class="text-xl font-semibold mt-2"><?= View::e((string) ($contract['status'] ?? 'rascunho')) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Formalização</div>
    <div class="text-xl font-semibold mt-2"><?= View::e((string) ($contract['signature_mode'] ?? 'print')) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Valor da proposta</div>
    <div class="text-xl font-semibold mt-2">R$ <?= number_format((float) ($contract['proposal_total'] ?? 0), 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Versão atual</div>
    <div class="text-xl font-semibold mt-2">v<?= (int) ($contract['current_version'] ?? 1) ?></div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-6">
  <div class="xl:col-span-2 tr-card p-6">
    <div class="flex items-center justify-between gap-3">
      <div class="font-semibold">Conteúdo do contrato</div>
      <a class="text-sm text-traxterAccent" href="<?= View::e($base . '/propostas/' . $contract['proposal_id']) ?>">Abrir proposta vinculada</a>
    </div>
    <div class="mt-4 text-sm text-slate-800 whitespace-pre-line leading-7"><?= View::e($body) ?></div>
  </div>

  <div class="space-y-4">
    <div class="tr-card p-6">
      <div class="font-semibold">Fluxo de trabalho</div>
      <div class="mt-4 space-y-3">
        <?php if ((string) ($contract['status'] ?? '') === 'rascunho'): ?>
          <form method="post" action="<?= View::e($base . '/contratos/' . $contract['id'] . '/enviar-assinatura') ?>">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Enviar para assinatura">
              <?= UI::icon('refresh') ?>
              <span class="sr-only">Enviar para assinatura</span>
            </button>
          </form>
        <?php endif; ?>
        <?php if (in_array((string) ($contract['status'] ?? ''), ['rascunho','pendente_assinatura'], true)): ?>
          <form method="post" action="<?= View::e($base . '/contratos/' . $contract['id'] . '/assinar') ?>">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Marcar como assinado">
              <?= UI::icon('check') ?>
              <span class="sr-only">Marcar como assinado</span>
            </button>
          </form>
        <?php endif; ?>
        <?php if ((string) ($contract['status'] ?? '') === 'assinado'): ?>
          <form method="post" action="<?= View::e($base . '/contratos/' . $contract['id'] . '/vigencia') ?>">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Iniciar vigência">
              <?= UI::icon('check') ?>
              <span class="sr-only">Iniciar vigência</span>
            </button>
          </form>
        <?php endif; ?>
      </div>

      <div class="mt-5 text-sm text-slate-700 space-y-2">
        <div><strong>Assinatura:</strong> <?= View::e((string) ($contract['signature_provider'] ?? 'Nao iniciado')) ?></div>
        <div><strong>Referência:</strong> <?= View::e((string) ($contract['signature_reference'] ?? '—')) ?></div>
        <div><strong>URL:</strong> <?php if (!empty($contract['signature_url'])): ?><a class="text-traxterAccent" href="<?= View::e((string) $contract['signature_url']) ?>" target="_blank">abrir</a><?php else: ?>—<?php endif; ?></div>
        <div><strong>Enviado:</strong> <?= !empty($contract['sent_for_signature_at']) ? View::e((string) $contract['sent_for_signature_at']) : '—' ?></div>
        <div><strong>Assinado:</strong> <?= !empty($contract['signed_at']) ? View::e((string) $contract['signed_at']) : '—' ?></div>
        <div><strong>Vigência:</strong> <?= !empty($contract['effective_date']) ? View::e(date('d/m/Y', strtotime((string) $contract['effective_date']))) : '—' ?></div>
      </div>
    </div>

    <div class="tr-card p-6">
      <div class="font-semibold">Resumo</div>
      <div class="mt-4 text-sm text-slate-700 space-y-2">
        <div><strong>Cliente:</strong> <?= View::e((string) (($contract['client_company'] ?? '') !== '' ? $contract['client_company'] : ($contract['client_name'] ?? ''))) ?></div>
        <div><strong>Template:</strong> <?= View::e((string) ($contract['template_name'] ?? '')) ?></div>
        <div><strong>Prazo inicial:</strong> <?= !empty($summary['delivery_start']) ? View::e(date('d/m/Y', strtotime((string) $summary['delivery_start']))) : '—' ?></div>
        <div><strong>Prazo final:</strong> <?= !empty($summary['delivery_end']) ? View::e(date('d/m/Y', strtotime((string) $summary['delivery_end']))) : '—' ?></div>
        <div><strong>Critério:</strong> <?= View::e((string) ($contract['policy_reason'] ?? '')) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
  <div class="tr-card p-6">
    <div class="font-semibold">Versões</div>
    <div class="mt-4 space-y-3">
      <?php foreach ($versions as $version): ?>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 flex items-center justify-between gap-3">
          <div>
            <div class="font-semibold">Versão <?= (int) ($version['version'] ?? 1) ?></div>
            <div class="text-xs text-slate-500"><?= View::e((string) ($version['created_at'] ?? '')) ?></div>
          </div>
          <a class="tr-icon-btn" href="<?= View::e($base . '/contratos/' . $contract['id'] . '/versoes/' . $version['id'] . '/pdf') ?>" target="_blank" aria-label="PDF da versão <?= (int) ($version['version'] ?? 1) ?>">
            <?= UI::icon('pdf') ?>
            <span class="sr-only">PDF da versão <?= (int) ($version['version'] ?? 1) ?></span>
          </a>
        </div>
      <?php endforeach; ?>
      <?php if (count($versions) === 0): ?>
        <div class="text-sm text-slate-600">Nenhuma versão encontrada.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Notificações</div>
    <div class="mt-4 space-y-3">
      <?php foreach ($notifications as $notification): ?>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
          <div class="flex items-center justify-between gap-3">
            <span class="tr-badge"><?= View::e((string) ($notification['type'] ?? '')) ?></span>
            <span class="text-xs text-slate-500"><?= View::e((string) ($notification['status'] ?? '')) ?></span>
          </div>
          <div class="mt-2 text-sm text-slate-800"><?= View::e((string) ($notification['message'] ?? '')) ?></div>
          <div class="mt-2 text-xs text-slate-500"><?= View::e((string) ($notification['created_at'] ?? '')) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (count($notifications) === 0): ?>
        <div class="text-sm text-slate-600">Nenhuma notificação registrada.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
