<?php
use App\Core\UI;
use App\Core\View;
use App\Services\ServiceOrderAttachmentUploadService;
use App\Services\ServiceOrderStatus;
use App\Services\ServiceOrderType;

$title = 'Ordem de Serviço';
$serviceOrder = is_array($serviceOrder ?? null) ? $serviceOrder : [];
$attachments = is_array($attachments ?? null) ? $attachments : [];
$history = is_array($history ?? null) ? $history : [];
$receivables = is_array($receivables ?? null) ? $receivables : [];
$canManage = ($canManage ?? false) === true;
$id = (int) ($serviceOrder['id'] ?? 0);
$attachmentHelper = new ServiceOrderAttachmentUploadService();

$badgeClass = ServiceOrderStatus::badgeClass((string) ($serviceOrder['status'] ?? ''));
$badgeLabel = ServiceOrderStatus::label((string) ($serviceOrder['status'] ?? ''));
$clientLabel = trim((string) ($serviceOrder['client_company'] ?? '')) !== ''
    ? (string) $serviceOrder['client_company']
    : (trim((string) ($serviceOrder['client_name'] ?? '')) !== '' ? (string) $serviceOrder['client_name'] : 'Cliente não vinculado');

$plainText = static function (mixed $value, string $fallback = 'Não informado'): string {
    $text = trim(strip_tags((string) $value));
    return $text !== '' ? $text : $fallback;
};

$formatDate = static function (mixed $value, string $fallback = 'Não informado'): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return $fallback;
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? $raw : date('d/m/Y H:i', $timestamp);
};
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold"><?= View::e((string) ($serviceOrder['numero_os'] ?? '')) ?></div>
    <div class="text-slate-600 mt-1"><?= View::e((string) ($serviceOrder['service_name'] ?? '')) ?></div>
    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold mt-2 <?= View::e($badgeClass) ?>"><?= View::e($badgeLabel) ?></span>
  </div>
  <div class="flex items-center gap-2">
    <?php if ($canManage): ?>
      <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/editar') ?>" title="Editar" aria-label="Editar ordem de serviço">
        <?= UI::icon('edit') ?>
        <span class="sr-only">Editar</span>
      </a>
    <?php endif; ?>
    <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/pdf') ?>" title="Gerar PDF" aria-label="Gerar PDF da ordem de serviço" target="_blank" rel="noopener">
      <?= UI::icon('pdf') ?>
      <span class="sr-only">PDF</span>
    </a>
    <?php if ((int) ($serviceOrder['billable'] ?? 0) === 1): ?>
      <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/faturar') ?>" title="Financeiro" aria-label="Financeiro da ordem de serviço">
        <?= UI::icon('wallet') ?>
        <span class="sr-only">Financeiro</span>
      </a>
    <?php endif; ?>
    <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico') ?>" title="Voltar" aria-label="Voltar para listagem">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
  <div class="xl:col-span-2 space-y-6">
    <div class="tr-card p-6">
      <div class="font-semibold">Informações gerais</div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm">
        <div>
          <div class="text-xs font-semibold text-slate-600">Cliente</div>
          <div class="mt-1"><?= View::e($clientLabel) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Serviço</div>
          <div class="mt-1"><?= View::e((string) ($serviceOrder['service_name'] ?? '')) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Tipo</div>
          <div class="mt-1"><?= View::e(ServiceOrderType::label((string) ($serviceOrder['type'] ?? ''))) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Responsável</div>
          <div class="mt-1"><?= View::e((string) ($serviceOrder['assigned_user_name'] ?? 'Não definido')) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Status</div>
          <div class="mt-1"><span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold <?= View::e($badgeClass) ?>"><?= View::e($badgeLabel) ?></span></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Contato responsável</div>
          <div class="mt-1"><?= View::e($plainText($serviceOrder['contact_name'] ?? null)) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Data de abertura</div>
          <div class="mt-1"><?= View::e($formatDate($serviceOrder['opened_at'] ?? null)) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Previsão</div>
          <div class="mt-1"><?= View::e($formatDate($serviceOrder['due_at'] ?? null, 'Não definida')) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Data de conclusão</div>
          <div class="mt-1"><?= View::e($formatDate($serviceOrder['completed_at'] ?? null, 'Ainda não concluída')) ?></div>
        </div>
      </div>

      <div class="mt-4">
        <div class="text-xs font-semibold text-slate-600">Descrição da solicitação</div>
        <div class="mt-1 text-sm whitespace-pre-line"><?= nl2br(View::e($plainText($serviceOrder['request_description'] ?? null, 'Não informada'))) ?></div>
      </div>
      <?php if ($plainText($serviceOrder['executed_activities'] ?? null, '') !== ''): ?>
        <div class="mt-4">
          <div class="text-xs font-semibold text-slate-600">Atividades executadas</div>
          <div class="mt-1 text-sm whitespace-pre-line"><?= nl2br(View::e($plainText($serviceOrder['executed_activities'] ?? null))) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($plainText($serviceOrder['technical_notes'] ?? null, '') !== ''): ?>
        <div class="mt-4">
          <div class="text-xs font-semibold text-slate-600">Observações técnicas</div>
          <div class="mt-1 text-sm whitespace-pre-line"><?= nl2br(View::e($plainText($serviceOrder['technical_notes'] ?? null))) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($canManage && trim((string) ($serviceOrder['internal_notes'] ?? '')) !== ''): ?>
        <div class="mt-4">
          <div class="text-xs font-semibold text-slate-600">Observações internas</div>
          <div class="mt-1 text-sm whitespace-pre-line"><?= nl2br(View::e((string) $serviceOrder['internal_notes'])) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="tr-card p-6">
      <div class="font-semibold">Financeiro</div>
      <?php if ((int) ($serviceOrder['billable'] ?? 0) !== 1): ?>
        <div class="tr-hint mt-3">Esta Ordem de Serviço não possui cobrança.</div>
      <?php elseif ($receivables === []): ?>
        <div class="tr-hint mt-3">Cobrança ainda não gerada.</div>
      <?php else: ?>
        <?php
          $statusLabels = ['pending' => 'Pendente', 'partially_paid' => 'Parcialmente pago', 'paid' => 'Pago', 'overdue' => 'Vencido', 'canceled' => 'Cancelado', 'renegotiated' => 'Renegociado'];
          $receivedTotal = array_sum(array_map(static fn (array $r): float => (float) ($r['received_amount'] ?? 0), $receivables));
          $openTotal = array_sum(array_map(static fn (array $r): float => (float) ($r['remaining_amount'] ?? 0), $receivables));
        ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
          <div>
            <div class="text-xs font-semibold text-slate-600">Valor da OS</div>
            <div class="mt-1">R$ <?= number_format((float) ($serviceOrder['final_amount'] ?? 0), 2, ',', '.') ?></div>
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-600">Parcelas geradas</div>
            <div class="mt-1"><?= count($receivables) ?></div>
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-600">Total recebido</div>
            <div class="mt-1">R$ <?= number_format($receivedTotal, 2, ',', '.') ?></div>
          </div>
          <div>
            <div class="text-xs font-semibold text-slate-600">Saldo em aberto</div>
            <div class="mt-1">R$ <?= number_format($openTotal, 2, ',', '.') ?></div>
          </div>
        </div>
        <div class="overflow-x-auto mt-4">
          <table class="w-full text-sm">
            <thead class="text-slate-700">
              <tr>
                <th class="text-left py-2 px-2">Parcela</th>
                <th class="text-left py-2 px-2">Vencimento</th>
                <th class="text-right py-2 px-2">Valor</th>
                <th class="text-right py-2 px-2">Desconto</th>
                <th class="text-right py-2 px-2">Recebido</th>
                <th class="text-right py-2 px-2">Saldo</th>
                <th class="text-left py-2 px-2">Status</th>
                <th class="text-right py-2 px-2">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($receivables as $item): ?>
                <?php $receivableId = (int) ($item['id'] ?? 0); ?>
                <tr class="border-t">
                  <td class="py-2 px-2"><?= (int) ($item['installment_number'] ?? 1) ?>/<?= (int) ($item['total_installments'] ?? 1) ?></td>
                  <td class="py-2 px-2"><?= View::e((string) ($item['due_date'] ?? '')) ?></td>
                  <td class="py-2 px-2 text-right">R$ <?= number_format((float) ($item['original_amount'] ?? 0), 2, ',', '.') ?></td>
                  <td class="py-2 px-2 text-right">R$ <?= number_format((float) ($item['discount_amount'] ?? 0), 2, ',', '.') ?></td>
                  <td class="py-2 px-2 text-right">R$ <?= number_format((float) ($item['received_amount'] ?? 0), 2, ',', '.') ?></td>
                  <td class="py-2 px-2 text-right">R$ <?= number_format((float) ($item['remaining_amount'] ?? 0), 2, ',', '.') ?></td>
                  <td class="py-2 px-2"><?= View::e($statusLabels[(string) ($item['status'] ?? '')] ?? (string) ($item['status'] ?? '')) ?></td>
                  <td class="py-2 px-2 text-right">
                    <a class="tr-icon-btn" title="Visualizar recebível" href="<?= View::e($base . '/financeiro/recebiveis/' . $receivableId) ?>"><?= UI::icon('eye') ?><span class="sr-only">Visualizar recebível</span></a>
                    <?php if ($canManage): ?>
                      <a class="tr-icon-btn" title="Editar recebível" href="<?= View::e($base . '/financeiro/recebiveis/' . $receivableId . '/editar') ?>"><?= UI::icon('edit') ?><span class="sr-only">Editar recebível</span></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="tr-card p-6">
      <div class="flex items-center justify-between">
        <div class="font-semibold">Anexos</div>
        <span class="text-sm text-slate-600"><?= count($attachments) ?> total</span>
      </div>
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <?php foreach ($attachments as $attachment): ?>
          <?php
            $attachmentId = (int) ($attachment['id'] ?? 0);
            $previewUrl = $base . '/ordens-servico/' . $id . '/anexos/' . $attachmentId;
            $downloadUrl = $previewUrl . '?download=1';
          ?>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 flex items-center gap-3">
            <?php if ($attachmentHelper->isImage($attachment)): ?>
              <a href="<?= View::e($previewUrl) ?>" target="_blank" rel="noopener" class="shrink-0">
                <img src="<?= View::e($previewUrl) ?>" alt="<?= View::e((string) ($attachment['original_name'] ?? 'Imagem')) ?>" class="w-16 h-16 rounded-lg border border-slate-200 bg-white object-cover">
              </a>
            <?php else: ?>
              <div class="w-16 h-16 shrink-0 rounded-lg border border-slate-200 bg-white flex items-center justify-center text-slate-500">
                <?= UI::icon('folder', 'w-6 h-6') ?>
              </div>
            <?php endif; ?>
            <div class="min-w-0 flex-1">
              <div class="font-medium break-words text-sm"><?= View::e((string) ($attachment['original_name'] ?? 'Arquivo')) ?></div>
              <div class="text-xs text-slate-500 mt-1"><?= View::e(strtoupper((string) ($attachment['file_extension'] ?? ''))) ?> • <?= View::e((string) ($attachment['file_size'] ?? '0')) ?> bytes</div>
            </div>
            <a class="tr-icon-btn shrink-0" href="<?= View::e($downloadUrl) ?>" aria-label="Baixar anexo" title="Baixar">
              <?= UI::icon('download') ?>
              <span class="sr-only">Baixar</span>
            </a>
          </div>
        <?php endforeach; ?>
        <?php if (count($attachments) === 0): ?>
          <div class="text-sm text-slate-600">Nenhum anexo enviado até o momento.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="space-y-6">
    <div class="tr-card p-6">
      <div class="font-semibold">Histórico</div>
      <div class="mt-4 space-y-3 max-h-[40rem] overflow-y-auto pr-1">
        <?php foreach ($history as $item): ?>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-center justify-between gap-3">
              <span class="tr-badge"><?= View::e((string) ($item['action'] ?? 'evento')) ?></span>
              <span class="text-xs text-slate-500"><?= View::e((string) ($item['created_at'] ?? '')) ?></span>
            </div>
            <div class="mt-2 text-sm text-slate-800"><?= View::e((string) ($item['message'] ?? 'Atualização registrada.')) ?></div>
            <div class="text-xs text-slate-500 mt-1">Responsável: <?= View::e((string) ($item['actor_name'] ?? 'Sistema')) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (count($history) === 0): ?>
          <div class="text-sm text-slate-600">Nenhum evento registrado.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
