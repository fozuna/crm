<?php
use App\Core\View;

$approval = is_array($approval ?? null) ? $approval : null;
$error = trim((string) ($error ?? ''));
$success = trim((string) ($success ?? ''));
$submitted = ($submitted ?? false) === true;
$token = trim((string) ($token ?? ''));

$pageTitle = 'Aprovacao digital da ordem de servico';
$pageDescription = 'Manifestacao digital segura do cliente para aprovacao de ordem de servico.';
$pageRobots = 'noindex,nofollow';

$status = (string) ($approval['status'] ?? '');
$statusMap = [
    'pendente' => ['label' => 'Pendente', 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
    'aprovada' => ['label' => 'Aprovada', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    'ajustes_solicitados' => ['label' => 'Ajustes solicitados', 'class' => 'border-orange-200 bg-orange-50 text-orange-700'],
    'expirada' => ['label' => 'Expirada', 'class' => 'border-slate-200 bg-slate-100 text-slate-700'],
    'revogada' => ['label' => 'Revogada', 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
];
$badge = $statusMap[$status] ?? ['label' => 'Link invalido', 'class' => 'border-slate-200 bg-slate-100 text-slate-700'];
$canSubmit = $approval !== null && !$submitted && $status === 'pendente' && $token !== '' && $error === '';

$formatDateTime = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'Nao informado';
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? $raw : date('d/m/Y H:i', $timestamp);
};

$formatMoney = static function (float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
};

$clientLabel = '';
if ($approval !== null) {
    $clientLabel = trim((string) (($approval['client_company'] ?? '') !== '' ? $approval['client_company'] : ($approval['client_name'] ?? 'Cliente')));
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
  body{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%)}
  .public-shell{min-height:100vh}
  .public-card{box-shadow:0 20px 60px rgba(15,23,42,.08)}
  .decision-card input:checked + span{border-color:var(--tr-accent);background:rgba(238,108,77,.08);color:#0f172a}
</style>
</head>
<body class="text-slate-900 antialiased">
  <main class="public-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
      <div class="mb-6 text-center">
        <div class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Manifestacao digital segura</div>
        <h1 class="mt-3 text-3xl font-semibold text-traxterSidebar sm:text-4xl">Aprovacao da ordem de servico</h1>
        <p class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
          Revise os dados abaixo e registre sua manifestacao com seguranca. O link pode ser utilizado uma unica vez.
        </p>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1.05fr_.95fr]">
        <section class="public-card tr-card border border-slate-200 p-6 sm:p-8">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <div class="text-sm font-semibold text-slate-500">Resumo da ordem de servico</div>
              <div class="mt-1 text-2xl font-semibold text-traxterSidebar">
                <?= View::e((string) ($approval['numero_os'] ?? 'Nao disponivel')) ?>
              </div>
            </div>
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= View::e($badge['class']) ?>">
              <?= View::e($badge['label']) ?>
            </span>
          </div>

          <?php if ($success !== ''): ?>
            <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">
              <?= View::e($success) ?>
            </div>
          <?php endif; ?>

          <?php if ($error !== ''): ?>
            <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
              <?= View::e($error) ?>
            </div>
          <?php endif; ?>

          <?php if ($approval !== null): ?>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cliente</div>
                <div class="mt-2 text-sm font-medium text-slate-900"><?= View::e($clientLabel !== '' ? $clientLabel : 'Nao informado') ?></div>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Servico</div>
                <div class="mt-2 text-sm font-medium text-slate-900"><?= View::e((string) ($approval['service_name'] ?? 'Nao informado')) ?></div>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valor final</div>
                <div class="mt-2 text-sm font-medium text-slate-900"><?= View::e($formatMoney((float) ($approval['final_amount'] ?? 0))) ?></div>
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Validade</div>
                <div class="mt-2 text-sm font-medium text-slate-900"><?= View::e($formatDateTime((string) ($approval['token_expires_at'] ?? ''))) ?></div>
              </div>
            </div>

            <div class="mt-6 space-y-5">
              <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descricao da solicitacao</div>
                <div class="mt-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700">
                  <?= nl2br(View::e(trim(strip_tags((string) ($approval['request_description'] ?? ''))) !== '' ? trim(strip_tags((string) ($approval['request_description'] ?? ''))) : 'Nao informada')) ?>
                </div>
              </div>

              <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Atividades executadas</div>
                <div class="mt-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700">
                  <?= nl2br(View::e(trim(strip_tags((string) ($approval['executed_activities'] ?? ''))) !== '' ? trim(strip_tags((string) ($approval['executed_activities'] ?? ''))) : 'Nao informadas')) ?>
                </div>
              </div>

              <?php if (trim((string) ($approval['technical_notes'] ?? '')) !== ''): ?>
                <div>
                  <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observacoes tecnicas</div>
                  <div class="mt-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700">
                    <?= nl2br(View::e(trim(strip_tags((string) $approval['technical_notes'])))) ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
              Nao foi possivel recuperar os dados da ordem de servico para este link.
            </div>
          <?php endif; ?>
        </section>

        <aside class="public-card tr-card border border-slate-200 p-6 sm:p-8">
          <div class="text-lg font-semibold text-traxterSidebar">Registrar manifestacao</div>
          <p class="mt-2 text-sm leading-6 text-slate-600">
            Informe seus dados para auditoria e escolha se a ordem de servico esta aprovada ou se precisa de ajustes.
          </p>

          <?php if ($canSubmit): ?>
            <form method="post" action="<?= View::e($base . '/os/aprovacao/' . rawurlencode((string) ($approval['public_id'] ?? ''))) ?>" class="mt-6 space-y-5">
              <input type="hidden" name="token" value="<?= View::e($token) ?>">

              <div class="grid gap-3 sm:grid-cols-2">
                <label class="decision-card block cursor-pointer">
                  <input class="sr-only" type="radio" name="decision" value="approve" checked>
                  <span class="block rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-700 transition">
                    Aprovar a OS
                  </span>
                </label>
                <label class="decision-card block cursor-pointer">
                  <input class="sr-only" type="radio" name="decision" value="request_adjustments">
                  <span class="block rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-700 transition">
                    Solicitar ajustes
                  </span>
                </label>
              </div>

              <div>
                <label class="tr-label" for="requester_name">Seu nome</label>
                <input id="requester_name" name="requester_name" class="mt-1 tr-input" maxlength="190" required value="<?= View::e((string) ($approval['requester_name'] ?? $approval['client_contact_person'] ?? '')) ?>">
              </div>

              <div>
                <label class="tr-label" for="requester_email">Seu e-mail</label>
                <input id="requester_email" name="requester_email" type="email" class="mt-1 tr-input" maxlength="190" value="<?= View::e((string) ($approval['requester_email'] ?? $approval['client_billing_email'] ?? $approval['client_email'] ?? '')) ?>">
              </div>

              <div>
                <label class="tr-label" for="requester_phone">Seu telefone</label>
                <input id="requester_phone" name="requester_phone" class="mt-1 tr-input" maxlength="60" value="<?= View::e((string) ($approval['requester_phone'] ?? $approval['client_billing_phone'] ?? $approval['client_phone'] ?? '')) ?>">
              </div>

              <div id="justificationWrap" class="hidden">
                <label class="tr-label" for="justification">Justificativa dos ajustes</label>
                <textarea id="justification" name="justification" rows="5" class="mt-1 tr-input" placeholder="Descreva o que precisa ser ajustado para a aprovacao."></textarea>
                <div class="tr-hint mt-2">A justificativa e obrigatoria quando houver solicitacao de ajustes.</div>
              </div>

              <button class="tr-btn tr-icon-btn--accent w-full justify-center" type="submit" data-no-iconify="true">Registrar manifestacao</button>
            </form>
          <?php else: ?>
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
              <?php if ($submitted): ?>
                Esta manifestacao ja foi concluida com sucesso e registrada no sistema.
              <?php elseif ($approval !== null && $status !== 'pendente'): ?>
                Este link nao aceita novas acoes porque a ordem de servico ja possui uma manifestacao registrada.
              <?php else: ?>
                O formulario nao esta disponivel para este link no momento.
              <?php endif; ?>
            </div>

            <?php if ($approval !== null && trim((string) ($approval['decision_at'] ?? '')) !== ''): ?>
              <div class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-700">
                <div>
                  <span class="font-semibold text-slate-900">Responsavel:</span>
                  <?= View::e((string) (($approval['requester_name'] ?? '') !== '' ? $approval['requester_name'] : 'Nao informado')) ?>
                </div>
                <div>
                  <span class="font-semibold text-slate-900">Data:</span>
                  <?= View::e($formatDateTime((string) ($approval['decision_at'] ?? ''))) ?>
                </div>
                <?php if (trim((string) ($approval['justification'] ?? '')) !== ''): ?>
                  <div>
                    <span class="font-semibold text-slate-900">Justificativa:</span>
                    <?= View::e((string) $approval['justification']) ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs leading-5 text-slate-500">
            O registro inclui data, horario, IP e informacoes tecnicas para rastreabilidade da manifestacao.
          </div>
        </aside>
      </div>
    </div>
  </main>

  <script>
    (function(){
      const radios = Array.from(document.querySelectorAll('input[name="decision"]'));
      const wrap = document.getElementById('justificationWrap');
      const field = document.getElementById('justification');

      function syncDecision(){
        if (!wrap || !field) return;
        const selected = radios.find((radio) => radio.checked);
        const requiresJustification = selected && selected.value === 'request_adjustments';
        wrap.classList.toggle('hidden', !requiresJustification);
        field.required = !!requiresJustification;
      }

      radios.forEach((radio) => radio.addEventListener('change', syncDecision));
      syncDecision();
    })();
  </script>
</body>
</html>
