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
$approvalSummary = is_array($approvalSummary ?? null) ? $approvalSummary : null;
$isEdit = (bool) ($isEdit ?? false);
$error = trim((string) ($error ?? ''));
$id = (int) ($serviceOrder['id'] ?? 0);
$action = $isEdit ? ($base . '/ordens-servico/' . $id) : ($base . '/ordens-servico');
$attachmentHelper = new ServiceOrderAttachmentUploadService();

$badgeClass = ServiceOrderStatus::badgeClass((string) ($serviceOrder['status'] ?? ''));
$badgeLabel = ServiceOrderStatus::label((string) ($serviceOrder['status'] ?? ''));
$toastType = trim((string) ($toastType ?? ''));
$toastMessage = trim((string) ($toastMessage ?? ''));
$approvalStatus = (string) ($approvalSummary['status'] ?? '');
$canGenerateApproval = $isEdit && in_array((string) ($serviceOrder['status'] ?? ''), [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true);
$formatDateTime = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'Nao registrado';
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? $raw : date('d/m/Y H:i', $timestamp);
};
$approvalStatusMap = [
    'pendente' => ['label' => 'Pendente', 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
    'aprovada' => ['label' => 'Aprovada', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    'ajustes_solicitados' => ['label' => 'Ajustes solicitados', 'class' => 'border-orange-200 bg-orange-50 text-orange-700'],
    'expirada' => ['label' => 'Expirada', 'class' => 'border-slate-200 bg-slate-100 text-slate-700'],
    'revogada' => ['label' => 'Revogada', 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
];
$approvalBadge = $approvalStatusMap[$approvalStatus] ?? ['label' => 'Nao gerado', 'class' => 'border-slate-200 bg-slate-100 text-slate-700'];
$approvalActionLabel = $approvalSummary === null ? 'Gerar link' : 'Gerar novo link';
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold"><?= $isEdit ? 'Editar ordem de serviço' : 'Nova ordem de serviço' ?></div>
    <div class="text-slate-600 mt-1">Cadastro independente para demandas pontuais com histórico, anexos e integração financeira.</div>
  </div>
  <div class="flex items-center gap-2">
    <?php if ($isEdit): ?>
      <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= View::e($badgeClass) ?>"><?= View::e($badgeLabel) ?></span>
      <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/pdf') ?>" aria-label="PDF da ordem de serviço" target="_blank" rel="noopener">
        <?= UI::icon('pdf') ?>
        <span class="sr-only">PDF</span>
      </a>
    <?php endif; ?>
    <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico') ?>" aria-label="Voltar para listagem">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
  </div>
</div>

<?php if ($error !== ''): ?>
  <div class="mt-6 tr-card p-4 border border-red-200 bg-red-50 text-red-700 text-sm"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
  <div class="xl:col-span-2">
    <form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="tr-card p-6 space-y-6" id="serviceOrderForm">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

      <div>
        <div class="font-semibold">Identificação</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <div>
            <label class="tr-label">Número da OS</label>
            <input class="mt-1 tr-input bg-slate-100" value="<?= View::e((string) ($serviceOrder['numero_os'] ?? 'Será gerado automaticamente')) ?>" readonly>
          </div>
          <div>
            <label class="tr-label">Nome do serviço</label>
            <input name="service_name" class="mt-1 tr-input" required maxlength="190" value="<?= View::e((string) ($serviceOrder['service_name'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Cliente</label>
            <select name="client_id" id="clientSelect" class="mt-1 tr-input" required>
              <option value="0">Selecione</option>
              <?php foreach ($clients as $client): ?>
                <?php $clientId = (int) ($client['id'] ?? 0); ?>
                <option
                  value="<?= $clientId ?>"
                  data-contact="<?= View::e((string) ($client['contact_person'] ?? '')) ?>"
                  <?= (int) ($serviceOrder['client_id'] ?? 0) === $clientId ? 'selected' : '' ?>
                ><?= View::e((string) (($client['company'] ?? '') !== '' ? $client['company'] : ($client['name'] ?? ('Cliente #' . $clientId)))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="tr-label">Contato responsável</label>
            <input name="contact_name" id="contactNameInput" class="mt-1 tr-input" maxlength="190" value="<?= View::e((string) ($serviceOrder['contact_name'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Responsável interno</label>
            <select name="assigned_user_id" class="mt-1 tr-input">
              <option value="">Selecione</option>
              <?php foreach ($users as $user): ?>
                <?php $userId = (int) ($user['id'] ?? 0); ?>
                <option value="<?= $userId ?>" <?= (int) ($serviceOrder['assigned_user_id'] ?? 0) === $userId ? 'selected' : '' ?>><?= View::e((string) ($user['name'] ?? 'Usuário #' . $userId)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div>
        <div class="font-semibold">Classificação e status</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          <div>
            <label class="tr-label">Tipo</label>
            <select name="type" id="typeSelect" class="mt-1 tr-input" required>
              <?php foreach ($typeOptions as $key => $label): ?>
                <option value="<?= View::e($key) ?>" <?= (string) ($serviceOrder['type'] ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="typeOtherWrap" class="<?= (string) ($serviceOrder['type'] ?? '') === ServiceOrderType::OUTRO ? '' : 'hidden' ?>">
            <label class="tr-label">Descrição do tipo</label>
            <input name="type_other_description" class="mt-1 tr-input" maxlength="190" value="<?= View::e((string) ($serviceOrder['type_other_description'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Status</label>
            <select name="status" class="mt-1 tr-input" required>
              <?php foreach ($statusOptions as $key => $label): ?>
                <option value="<?= View::e($key) ?>" <?= (string) ($serviceOrder['status'] ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div>
        <div class="font-semibold">Descrição técnica</div>
        <div class="mt-4 space-y-4">
          <div><?= renderEditorField('request_description', 'Descrição completa da solicitação', (string) ($serviceOrder['request_description'] ?? '')) ?></div>
          <div><?= renderEditorField('executed_activities', 'Atividades executadas', (string) ($serviceOrder['executed_activities'] ?? '')) ?></div>
          <div><?= renderEditorField('technical_notes', 'Observações técnicas', (string) ($serviceOrder['technical_notes'] ?? '')) ?></div>
        </div>
      </div>

      <div>
        <div class="font-semibold">Tempo de execução</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          <div>
            <label class="tr-label">Data de abertura</label>
            <input type="datetime-local" name="opened_at" class="mt-1 tr-input" value="<?= View::e((string) ($serviceOrder['opened_at'] ?? '')) ?>" required>
          </div>
          <div>
            <label class="tr-label">Data prevista</label>
            <input type="datetime-local" name="due_at" class="mt-1 tr-input" value="<?= View::e((string) ($serviceOrder['due_at'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Data de conclusão</label>
            <input type="datetime-local" name="completed_at" class="mt-1 tr-input" value="<?= View::e((string) ($serviceOrder['completed_at'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Horas previstas</label>
            <input name="estimated_hours" id="estimatedHoursInput" class="mt-1 tr-input" inputmode="decimal" min="0" placeholder="0,00" value="<?= View::e((string) ($serviceOrder['estimated_hours'] ?? '')) ?>">
          </div>
          <div>
            <label class="tr-label">Horas executadas</label>
            <input name="executed_hours" id="executedHoursInput" class="mt-1 tr-input" inputmode="decimal" min="0" placeholder="0,00" value="<?= View::e((string) ($serviceOrder['executed_hours'] ?? '')) ?>">
          </div>
        </div>
      </div>

      <div>
        <div class="font-semibold">Financeiro</div>
        <div class="mt-4">
          <label class="flex items-center gap-2">
            <input type="checkbox" id="billableToggle" name="billable" value="1" <?= (int) ($serviceOrder['billable'] ?? 0) === 1 ? 'checked' : '' ?> class="rounded border-slate-300">
            <span class="text-sm">Este serviço gera cobrança ao cliente</span>
          </label>
        </div>
        <div id="billableFields" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 <?= (int) ($serviceOrder['billable'] ?? 0) === 1 ? '' : 'hidden' ?>">
          <div>
            <label class="tr-label">Serviço base</label>
            <select name="base_service_id" class="mt-1 tr-input">
              <option value="">Selecione</option>
              <?php foreach ($services as $service): ?>
                <?php $serviceId = (int) ($service['id'] ?? 0); ?>
                <option
                  value="<?= $serviceId ?>"
                  data-price="<?= View::e((string) ($service['default_price'] ?? '0')) ?>"
                  <?= (int) ($serviceOrder['base_service_id'] ?? 0) === $serviceId ? 'selected' : '' ?>
                ><?= View::e((string) ($service['name'] ?? 'Serviço #' . $serviceId)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="tr-label">Valor base</label>
            <input name="base_amount" id="baseAmountInput" class="mt-1 tr-input" inputmode="decimal" min="0" value="<?= View::e((string) ($serviceOrder['base_amount'] ?? '0,00')) ?>">
          </div>
          <div>
            <label class="tr-label">Desconto</label>
            <input name="discount_amount" id="discountAmountInput" class="mt-1 tr-input" inputmode="decimal" min="0" value="<?= View::e((string) ($serviceOrder['discount_amount'] ?? '0,00')) ?>">
          </div>
          <div>
            <label class="tr-label">Acréscimo</label>
            <input name="surcharge_amount" id="surchargeAmountInput" class="mt-1 tr-input" inputmode="decimal" min="0" value="<?= View::e((string) ($serviceOrder['surcharge_amount'] ?? '0,00')) ?>">
          </div>
          <div>
            <label class="tr-label">Valor final</label>
            <input name="final_amount" id="finalAmountInput" class="mt-1 tr-input bg-slate-100" value="<?= View::e((string) ($serviceOrder['final_amount'] ?? '0,00')) ?>" readonly>
          </div>
          <?php if ($isEdit && (int) ($serviceOrder['financial_receivable_id'] ?? 0) > 0): ?>
            <div>
              <label class="tr-label">Lançamento financeiro</label>
              <a class="tr-btn mt-1" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $serviceOrder['financial_receivable_id']) ?>">Abrir recebível vinculado</a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="font-semibold">Anexos e observações internas</div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
          <div>
            <label class="tr-label">Upload múltiplo</label>
            <input type="file" name="attachments[]" multiple class="mt-1 tr-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp,.zip">
            <div class="tr-hint mt-2">Formatos aceitos: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, WEBP e ZIP. Limite de 10MB por arquivo.</div>
          </div>
          <div>
            <label class="tr-label">Observações internas</label>
            <textarea name="internal_notes" rows="5" class="mt-1 tr-input" placeholder="Essas observações não aparecem no PDF enviado ao cliente."><?= View::e((string) ($serviceOrder['internal_notes'] ?? '')) ?></textarea>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap justify-end gap-2">
        <a class="tr-btn" href="<?= View::e($base . '/ordens-servico') ?>">Voltar</a>
        <button class="tr-btn tr-icon-btn--accent" type="submit">Salvar</button>
      </div>
    </form>

    <?php if ($isEdit): ?>
      <div class="tr-card p-4 mt-4">
        <div class="font-semibold">Ações rápidas</div>
        <div class="flex flex-wrap gap-2 mt-4">
          <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/status') ?>">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <input type="hidden" name="status" value="<?= View::e(ServiceOrderStatus::EM_ANDAMENTO) ?>">
            <button class="tr-btn" type="submit">Marcar em andamento</button>
          </form>
          <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/cancelar') ?>" onsubmit="return window.confirm('Cancelar esta OS?');">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <input type="hidden" name="reason" value="Cancelamento solicitado pela tela da OS.">
            <button class="tr-btn" type="submit">Cancelar OS</button>
          </form>
          <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/excluir') ?>" onsubmit="return window.confirm('Excluir esta OS?');">
            <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
            <button class="tr-btn" type="submit">Excluir</button>
          </form>
          <?php if (in_array((string) ($serviceOrder['status'] ?? ''), [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true)): ?>
            <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/aprovacao/gerar') ?>">
              <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
              <button class="tr-btn tr-icon-btn--accent" type="submit"><?= $approvalSummary === null ? 'Gerar link de aprovação' : 'Reenviar link de aprovação' ?></button>
            </form>
          <?php endif; ?>
          <?php if ($approvalSummary !== null && (string) ($approvalSummary['proof_pdf_path'] ?? '') !== ''): ?>
            <a class="tr-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/aprovacao/comprovante') ?>" target="_blank" rel="noopener">Abrir comprovante</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="space-y-6">
    <div class="tr-card p-6">
      <div class="font-semibold">Resumo rápido</div>
      <div class="grid grid-cols-1 gap-3 mt-4 text-sm">
        <div>
          <div class="text-xs font-semibold text-slate-600">Número</div>
          <div class="mt-1"><?= View::e((string) ($serviceOrder['numero_os'] ?? 'Será gerado automaticamente')) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Tipo</div>
          <div class="mt-1"><?= View::e(ServiceOrderType::label((string) ($serviceOrder['type'] ?? ''))) ?></div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Status</div>
          <div class="mt-1">
            <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold <?= View::e($badgeClass) ?>"><?= View::e($badgeLabel) ?></span>
          </div>
        </div>
        <div>
          <div class="text-xs font-semibold text-slate-600">Financeiro</div>
          <div class="mt-1"><?= (int) ($serviceOrder['billable'] ?? 0) === 1 ? 'Cobrança ativa' : 'Sem cobrança' ?></div>
        </div>
      </div>
    </div>

    <?php if ($isEdit): ?>
      <div class="tr-card p-6">
        <div class="flex items-center justify-between gap-3">
          <div class="font-semibold">Aprovação digital do cliente</div>
          <?php if ($approvalSummary !== null): ?>
            <?php
              $approvalBadge = match ((string) ($approvalSummary['status'] ?? 'pendente')) {
                  'aprovada' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                  'ajustes_solicitados' => 'bg-amber-50 border-amber-200 text-amber-800',
                  'expirada' => 'bg-rose-50 border-rose-200 text-rose-800',
                  'revogada' => 'bg-slate-100 border-slate-300 text-slate-700',
                  default => 'bg-sky-50 border-sky-200 text-sky-800',
              };
              $approvalLabel = match ((string) ($approvalSummary['status'] ?? 'pendente')) {
                  'aprovada' => 'Aprovada',
                  'ajustes_solicitados' => 'Ajustes solicitados',
                  'expirada' => 'Expirada',
                  'revogada' => 'Revogada',
                  default => 'Pendente',
              };
            ?>
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= View::e($approvalBadge) ?>"><?= View::e($approvalLabel) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($approvalSummary === null): ?>
          <div class="mt-4 text-sm text-slate-600">
            Nenhum link externo foi gerado ainda. Ao concluir ou faturar a OS, o sistema poderá enviar um link seguro de aprovação por e-mail ao cliente.
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 gap-3 mt-4 text-sm">
            <div>
              <div class="text-xs font-semibold text-slate-600">Validade do link</div>
              <div class="mt-1"><?= View::e((string) ($approvalSummary['token_expires_at'] ?? '')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Último acesso</div>
              <div class="mt-1"><?= View::e((string) ($approvalSummary['token_last_access_at'] ?? 'Ainda não acessado')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">E-mail do cliente</div>
              <div class="mt-1"><?= View::e((string) (($approvalSummary['client_billing_email'] ?? '') !== '' ? $approvalSummary['client_billing_email'] : ($approvalSummary['client_email'] ?? 'Não informado'))) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">E-mail enviado em</div>
              <div class="mt-1"><?= View::e((string) ($approvalSummary['email_sent_at'] ?? 'Pendente')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Manifestante</div>
              <div class="mt-1"><?= View::e((string) ($approvalSummary['requester_name'] ?? 'Ainda não informado')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Geolocalização aproximada</div>
              <div class="mt-1"><?= View::e((string) ($approvalSummary['actor_geo_summary'] ?? 'Ainda não registrada')) ?></div>
            </div>
            <?php if ((string) ($approvalSummary['justification'] ?? '') !== ''): ?>
              <div>
                <div class="text-xs font-semibold text-slate-600">Justificativa do cliente</div>
                <div class="mt-1 whitespace-pre-line"><?= View::e((string) $approvalSummary['justification']) ?></div>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="tr-card p-6">
    <?php if ($isEdit): ?>
      <div class="tr-card p-6">
        <div class="flex items-center justify-between gap-3">
          <div class="font-semibold">Aprovação digital</div>
          <span class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-semibold <?= View::e($approvalBadge['class']) ?>"><?= View::e($approvalBadge['label']) ?></span>
        </div>

        <?php if ($approvalSummary !== null): ?>
          <div class="grid grid-cols-1 gap-3 mt-4 text-sm">
            <div>
              <div class="text-xs font-semibold text-slate-600">Validade do link</div>
              <div class="mt-1"><?= View::e($formatDateTime((string) ($approvalSummary['token_expires_at'] ?? ''))) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Primeiro acesso</div>
              <div class="mt-1"><?= View::e($formatDateTime((string) ($approvalSummary['first_access_at'] ?? ''))) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Decisão do cliente</div>
              <div class="mt-1"><?= View::e($formatDateTime((string) ($approvalSummary['decision_at'] ?? ''))) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Solicitante</div>
              <div class="mt-1"><?= View::e((string) (($approvalSummary['requester_name'] ?? '') !== '' ? $approvalSummary['requester_name'] : 'Nao informado')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">E-mail do cliente</div>
              <div class="mt-1"><?= View::e((string) (($approvalSummary['requester_email'] ?? $approvalSummary['client_billing_email'] ?? $approvalSummary['client_email'] ?? '') !== '' ? ($approvalSummary['requester_email'] ?? $approvalSummary['client_billing_email'] ?? $approvalSummary['client_email']) : 'Nao informado')) ?></div>
            </div>
            <div>
              <div class="text-xs font-semibold text-slate-600">Disparo por e-mail</div>
              <div class="mt-1"><?= View::e($formatDateTime((string) ($approvalSummary['email_sent_at'] ?? ''))) ?></div>
            </div>
          </div>

          <?php if (trim((string) ($approvalSummary['justification'] ?? '')) !== ''): ?>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
              <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Justificativa</div>
              <div class="mt-2 whitespace-pre-line"><?= View::e((string) $approvalSummary['justification']) ?></div>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="mt-4 text-sm text-slate-600">
            Nenhum link de aprovação foi gerado para esta ordem de serviço.
          </div>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-2">
          <?php if ($canGenerateApproval): ?>
            <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/aprovacao/gerar') ?>" onsubmit="return window.confirm('Gerar um novo link de aprovação para o cliente?');">
              <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
              <button class="tr-btn tr-icon-btn--accent" type="submit"><?= View::e($approvalActionLabel) ?></button>
            </form>
          <?php else: ?>
            <div class="text-xs text-slate-500">
              O link externo so pode ser gerado quando a OS estiver concluida ou faturada.
            </div>
          <?php endif; ?>

          <?php if ($approvalSummary !== null && trim((string) ($approvalSummary['proof_pdf_path'] ?? '')) !== ''): ?>
            <a class="tr-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/aprovacao/comprovante') ?>" target="_blank" rel="noopener">Comprovante PDF</a>
          <?php endif; ?>
        </div>

        <div class="tr-hint mt-3">
          O token nao fica armazenado em texto puro; para reenviar ao cliente, gere um novo link.
        </div>
      </div>
    <?php endif; ?>

      <div class="flex items-center justify-between">
        <div class="font-semibold">Anexos</div>
        <span class="text-sm text-slate-600"><?= count($attachments) ?> total</span>
      </div>
      </div>
      <div class="mt-4 space-y-3">
        <?php foreach ($attachments as $attachment): ?>
          <?php
            $attachmentId = (int) ($attachment['id'] ?? 0);
            $previewUrl = $base . '/ordens-servico/' . $id . '/anexos/' . $attachmentId;
            $downloadUrl = $previewUrl . '?download=1';
          ?>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-medium break-words"><?= View::e((string) ($attachment['original_name'] ?? 'Arquivo')) ?></div>
                <div class="text-xs text-slate-500 mt-1">
                  <?= View::e((string) ($attachment['uploaded_by_name'] ?? 'Sistema')) ?>
                  • <?= View::e((string) ($attachment['created_at'] ?? '')) ?>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <a class="tr-icon-btn" href="<?= View::e($downloadUrl) ?>" aria-label="Baixar anexo">
                  <?= UI::icon('download') ?>
                  <span class="sr-only">Baixar</span>
                </a>
                <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/anexos/' . $attachmentId . '/excluir') ?>" onsubmit="return window.confirm('Remover este anexo?');">
                  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                  <button class="tr-icon-btn" type="submit" aria-label="Excluir anexo">
                    <?= UI::icon('trash') ?>
                    <span class="sr-only">Excluir</span>
                  </button>
                </form>
              </div>
            </div>
            <div class="text-xs text-slate-500 mt-2"><?= View::e((string) ($attachment['mime_type'] ?? '')) ?> • <?= View::e((string) ($attachment['file_size'] ?? '0')) ?> bytes</div>
            <?php if ($attachmentHelper->isImage($attachment)): ?>
              <a href="<?= View::e($previewUrl) ?>" target="_blank" rel="noopener" class="block mt-3">
                <img src="<?= View::e($previewUrl) ?>" alt="<?= View::e((string) ($attachment['original_name'] ?? 'Imagem')) ?>" class="w-full rounded-lg border border-slate-200 bg-white object-contain max-h-48">
              </a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (count($attachments) === 0): ?>
          <div class="text-sm text-slate-600">Nenhum anexo enviado até o momento.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="tr-card p-6">
      <div class="flex items-center justify-between">
        <div class="font-semibold">Histórico</div>
        <span class="text-sm text-slate-600"><?= count($history) ?> eventos</span>
      </div>
      <div class="mt-4 space-y-3 max-h-[32rem] overflow-y-auto pr-1">
        <?php foreach ($history as $item): ?>
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex items-center justify-between gap-3">
              <span class="tr-badge"><?= View::e((string) ($item['action'] ?? 'evento')) ?></span>
              <span class="text-xs text-slate-500"><?= View::e((string) ($item['created_at'] ?? '')) ?></span>
            </div>
            <div class="mt-2 text-sm text-slate-800"><?= View::e((string) ($item['message'] ?? 'Atualização registrada.')) ?></div>
            <?php if (!empty($item['field_name'])): ?>
              <div class="text-xs text-slate-500 mt-2">
                Campo: <?= View::e((string) $item['field_name']) ?>
                <?php if (($item['old_value'] ?? null) !== null || ($item['new_value'] ?? null) !== null): ?>
                  • de <?= View::e((string) ($item['old_value'] ?? '')) ?> para <?= View::e((string) ($item['new_value'] ?? '')) ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="text-xs text-slate-500 mt-1">Responsável: <?= View::e((string) ($item['actor_name'] ?? 'Sistema')) ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (count($history) === 0): ?>
          <div class="text-sm text-slate-600">Nenhuma alteração registrada ainda.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($toastType !== '' && $toastMessage !== ''): ?>
  <script>
    (function(){
      if (window.trToast) {
        window.trToast(<?= json_encode($toastMessage, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($toastType, JSON_UNESCAPED_UNICODE) ?>);
      }
    })();
  </script>
<?php endif; ?>

<?php
function renderEditorField(string $name, string $label, string $value): string
{
    $sanitizedHtml = (new \App\Services\ServiceOrderRichText())->sanitize($value);
    $escapedLabel = View::e($label);
    return '
      <div>
        <label class="tr-label">' . $escapedLabel . '</label>
        <div class="mt-1 rounded-xl border border-slate-300 bg-white overflow-hidden" data-editor-field="' . View::e($name) . '">
          <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
            <button type="button" class="tr-icon-btn editor-action" data-command="bold" aria-label="Negrito">B</button>
            <button type="button" class="tr-icon-btn editor-action" data-command="italic" aria-label="Itálico"><em>I</em></button>
            <button type="button" class="tr-icon-btn editor-action" data-command="insertUnorderedList" aria-label="Lista">•</button>
            <button type="button" class="tr-icon-btn editor-action" data-command="createLink" aria-label="Link">#</button>
          </div>
          <div class="min-h-[10rem] px-3 py-3 focus:outline-none" contenteditable="true" data-editor-content="1">' . $sanitizedHtml . '</div>
        </div>
        <input type="hidden" name="' . View::e($name) . '" value="' . View::e($value) . '" data-editor-input="' . View::e($name) . '">
        <div class="tr-hint mt-2">Use listas, negrito, itálico e links para enriquecer o texto.</div>
      </div>';
}
?>

<script>
  (function(){
    const form = document.getElementById('serviceOrderForm');
    if (!form) return;

    const typeSelect = document.getElementById('typeSelect');
    const typeOtherWrap = document.getElementById('typeOtherWrap');
    const clientSelect = document.getElementById('clientSelect');
    const contactNameInput = document.getElementById('contactNameInput');
    const billableToggle = document.getElementById('billableToggle');
    const billableFields = document.getElementById('billableFields');
    const estimatedHoursInput = document.getElementById('estimatedHoursInput');
    const executedHoursInput = document.getElementById('executedHoursInput');
    const baseAmountInput = document.getElementById('baseAmountInput');
    const discountAmountInput = document.getElementById('discountAmountInput');
    const surchargeAmountInput = document.getElementById('surchargeAmountInput');
    const finalAmountInput = document.getElementById('finalAmountInput');
    const baseServiceSelect = form.querySelector('select[name="base_service_id"]');

    function parseLocaleNumber(value) {
      let raw = String(value || '').trim().replace(/\s+/g, '');
      if (raw === '') return 0;
      const hasComma = raw.includes(',');
      const hasDot = raw.includes('.');
      if (hasComma && hasDot) {
        raw = raw.replace(/\./g, '').replace(',', '.');
      } else if (hasComma) {
        raw = raw.replace(',', '.');
      }
      const amount = Number(raw);
      return Number.isFinite(amount) ? amount : 0;
    }

    function fmtMoney(value) {
      return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
    }

    function sanitizeNonNegative(input, formatter) {
      if (!input) return 0;
      const parsed = parseLocaleNumber(input.value);
      const value = parsed < 0 ? 0 : parsed;
      input.setCustomValidity(parsed < 0 ? 'Informe um valor maior ou igual a zero.' : '');
      if (typeof formatter === 'function') {
        input.value = formatter(value);
      } else if (parsed < 0) {
        input.value = String(value).replace('.', ',');
      }
      return value;
    }

    function toggleTypeOther() {
      if (!typeSelect || !typeOtherWrap) return;
      typeOtherWrap.classList.toggle('hidden', typeSelect.value !== 'outro');
    }

    function fillClientContact() {
      if (!clientSelect || !contactNameInput || String(contactNameInput.value || '').trim() !== '') return;
      const option = clientSelect.options[clientSelect.selectedIndex];
      const contact = option ? String(option.getAttribute('data-contact') || '').trim() : '';
      if (contact !== '') contactNameInput.value = contact;
    }

    function toggleBillable() {
      if (!billableToggle || !billableFields) return;
      billableFields.classList.toggle('hidden', !billableToggle.checked);
      if (!billableToggle.checked && finalAmountInput) {
        finalAmountInput.value = '0,00';
      }
      calcFinal();
    }

    function calcFinal() {
      if (!finalAmountInput) return;
      if (!billableToggle.checked) {
        finalAmountInput.value = '0,00';
        return;
      }
      const estimatedHours = sanitizeNonNegative(estimatedHoursInput);
      const baseAmount = sanitizeNonNegative(baseAmountInput);
      const discountAmount = sanitizeNonNegative(discountAmountInput);
      const surchargeAmount = sanitizeNonNegative(surchargeAmountInput);
      const total = (estimatedHours * baseAmount) - discountAmount + surchargeAmount;
      finalAmountInput.value = fmtMoney(total);
    }

    function fillBasePrice() {
      if (!baseServiceSelect || !baseAmountInput) return;
      const option = baseServiceSelect.options[baseServiceSelect.selectedIndex];
      const rawPrice = option ? String(option.getAttribute('data-price') || '').trim() : '';
      if (rawPrice !== '' && parseLocaleNumber(baseAmountInput.value) === 0) {
        baseAmountInput.value = fmtMoney(Number(rawPrice));
        calcFinal();
      }
    }

    function normalizeOnBlur(input, formatter) {
      if (!input) return;
      input.addEventListener('blur', () => {
        sanitizeNonNegative(input, formatter);
        calcFinal();
      });
    }

    function syncEditors() {
      document.querySelectorAll('[data-editor-field]').forEach((wrapper) => {
        const name = wrapper.getAttribute('data-editor-field');
        const content = wrapper.querySelector('[data-editor-content]');
        const input = document.querySelector('[data-editor-input="' + name + '"]');
        if (!content || !input) return;
        input.value = content.innerHTML.trim();
      });
    }

    document.querySelectorAll('.editor-action').forEach((button) => {
      button.addEventListener('click', () => {
        const wrapper = button.closest('[data-editor-field]');
        const content = wrapper ? wrapper.querySelector('[data-editor-content]') : null;
        if (!content) return;
        content.focus();
        const command = button.getAttribute('data-command');
        if (command === 'createLink') {
          const url = window.prompt('Informe a URL do link:', 'https://');
          if (!url) return;
          document.execCommand('createLink', false, url);
        } else {
          document.execCommand(command, false, null);
        }
        syncEditors();
      });
    });

    document.querySelectorAll('[data-editor-content]').forEach((content) => {
      content.addEventListener('input', syncEditors);
      content.addEventListener('blur', syncEditors);
    });

    form.addEventListener('submit', syncEditors);

    if (typeSelect) typeSelect.addEventListener('change', toggleTypeOther);
    if (clientSelect) clientSelect.addEventListener('change', fillClientContact);
    if (billableToggle) billableToggle.addEventListener('change', toggleBillable);
    if (baseServiceSelect) baseServiceSelect.addEventListener('change', fillBasePrice);
    [estimatedHoursInput, baseAmountInput, discountAmountInput, surchargeAmountInput].forEach((input) => {
      if (input) input.addEventListener('input', calcFinal);
    });
    normalizeOnBlur(estimatedHoursInput);
    normalizeOnBlur(executedHoursInput);
    normalizeOnBlur(baseAmountInput, fmtMoney);
    normalizeOnBlur(discountAmountInput, fmtMoney);
    normalizeOnBlur(surchargeAmountInput, fmtMoney);

    toggleTypeOther();
    fillClientContact();
    toggleBillable();
    calcFinal();
    syncEditors();
  })();
</script>
