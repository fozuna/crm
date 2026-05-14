<?php
use App\Core\UI;
use App\Core\View;

$title = 'Branding';
$branding = is_array($branding ?? null) ? $branding : [];
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold">Branding</div>
    <div class="text-slate-600 mt-1">Módulo legado desativado após consolidação no perfil empresarial</div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/empresa') ?>">Abrir perfil empresarial</a>
    <a class="tr-icon-btn" href="<?= View::e($base . '/dashboard') ?>" aria-label="Voltar">
      <?= UI::icon('arrow-left') ?>
      <span class="sr-only">Voltar</span>
    </a>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string)$error) ?></div>
<?php endif; ?>

<div class="mt-6 tr-card p-6 space-y-5">
  <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    Os ativos de branding foram centralizados em `Empresa`. Esta tela permanece apenas para consulta histórica e manutenção técnica, evitando duplicidade de configuração.
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <div class="tr-label">Nome da marca</div>
      <div class="mt-1 tr-input bg-slate-50"><?= View::e((string)($branding['company_name'] ?? 'TRAXTER')) ?></div>
    </div>
    <div>
      <div class="tr-label">Tipografia</div>
      <div class="mt-1 tr-input bg-slate-50"><?= View::e((string)($branding['font_name'] ?? 'Helvetica')) ?></div>
    </div>
    <div>
      <div class="tr-label">Cor primária</div>
      <div class="mt-1 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
        <span class="inline-block h-6 w-6 rounded-full border border-slate-300" style="background: <?= View::e((string)($branding['primary_color'] ?? '#293241')) ?>"></span>
        <span class="text-sm text-slate-700"><?= View::e((string)($branding['primary_color'] ?? '#293241')) ?></span>
      </div>
    </div>
    <div>
      <div class="tr-label">Cor de destaque</div>
      <div class="mt-1 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
        <span class="inline-block h-6 w-6 rounded-full border border-slate-300" style="background: <?= View::e((string)($branding['accent_color'] ?? '#ee6c4d')) ?>"></span>
        <span class="text-sm text-slate-700"><?= View::e((string)($branding['accent_color'] ?? '#ee6c4d')) ?></span>
      </div>
    </div>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <div class="flex items-start justify-between gap-4">
    <div>
      <div class="text-lg font-semibold">Manutenção</div>
      <div class="text-slate-600 mt-1 text-sm">Atualização automática do schema do banco (upgrade.sql)</div>
    </div>
    <button id="dbUpgradeBtn" type="button" class="tr-btn" disabled>
      Atualizar banco
    </button>
  </div>
  <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="rounded border border-slate-200 bg-slate-50 p-4">
      <div class="text-sm font-semibold">Status</div>
      <div id="dbUpgradeStatus" class="mt-1 text-sm text-slate-700">Verificando…</div>
      <div id="dbUpgradeReasons" class="mt-2 text-xs text-slate-600"></div>
    </div>
    <div class="rounded border border-slate-200 bg-white p-4">
      <div class="text-sm font-semibold">Execução</div>
      <div class="mt-2 flex items-center gap-2">
        <div id="dbUpgradeSpinner" class="hidden w-4 h-4 rounded-full border-2 border-slate-300 border-t-slate-700 animate-spin"></div>
        <div id="dbUpgradeRunText" class="text-sm text-slate-700">Aguardando</div>
      </div>
      <pre id="dbUpgradeOutput" class="mt-3 text-xs bg-slate-950 text-slate-50 rounded p-3 overflow-auto max-h-56 hidden"></pre>
    </div>
  </div>
</div>

<div id="dbUpgradeModal" class="fixed inset-0 hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/50"></div>
  <div class="relative w-full max-w-lg rounded-xl bg-white shadow-lg p-6">
    <div class="text-lg font-semibold">Confirmar atualização do banco?</div>
    <div class="mt-2 text-sm text-slate-700">Isso executa o upgrade do schema. Recomendado realizar fora de horário de pico.</div>
    <div id="dbUpgradeModalDetails" class="mt-3 text-xs text-slate-600"></div>
    <div class="mt-6 flex justify-end gap-2">
      <button type="button" id="dbUpgradeCancel" class="tr-btn">Cancelar</button>
      <button type="button" id="dbUpgradeConfirm" class="tr-btn tr-icon-btn--accent">Executar</button>
    </div>
  </div>
</div>

<script>
  (function(){
    const base = <?= json_encode((string)$base, JSON_UNESCAPED_UNICODE) ?>;
    const csrf = <?= json_encode((string)$csrf, JSON_UNESCAPED_UNICODE) ?>;
    const btn = document.getElementById('dbUpgradeBtn');
    const statusEl = document.getElementById('dbUpgradeStatus');
    const reasonsEl = document.getElementById('dbUpgradeReasons');
    const spinner = document.getElementById('dbUpgradeSpinner');
    const runText = document.getElementById('dbUpgradeRunText');
    const outEl = document.getElementById('dbUpgradeOutput');
    const modal = document.getElementById('dbUpgradeModal');
    const modalDetails = document.getElementById('dbUpgradeModalDetails');
    const cancelBtn = document.getElementById('dbUpgradeCancel');
    const confirmBtn = document.getElementById('dbUpgradeConfirm');

    let lastInspect = null;
    let pollingTimer = null;

    function toast(type, message){
      if (typeof window.trToast === 'function') {
        window.trToast(type, message);
      }
    }

    function openModal(){
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
    function closeModal(){
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    async function getJson(url){
      const res = await fetch(url, { credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Falha na requisição');
      }
      return data;
    }

    async function postJson(url, body){
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(body).toString(),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Falha na requisição');
      }
      return data;
    }

    function renderInspect(inspect){
      lastInspect = inspect;
      const pending = !!inspect.pending;
      btn.disabled = !pending;
      statusEl.textContent = pending ? 'Atualização pendente detectada.' : 'Nenhuma atualização pendente.';

      const parts = [];
      const mt = Array.isArray(inspect.missing_tables) ? inspect.missing_tables : [];
      const mc = Array.isArray(inspect.missing_columns) ? inspect.missing_columns : [];
      const em = Array.isArray(inspect.enum_mismatches) ? inspect.enum_mismatches : [];
      if (mt.length) parts.push('Tabelas: ' + mt.join(', '));
      if (mc.length) parts.push('Colunas: ' + mc.join(', '));
      if (em.length) parts.push('Enums: ' + em.join(', '));
      reasonsEl.textContent = parts.length ? parts.join(' | ') : '';

      modalDetails.textContent = parts.length ? ('Pendências: ' + parts.join(' | ')) : '';
    }

    async function refreshInspect(){
      try {
        const data = await getJson(base + '/maintenance/db-upgrade/check');
        renderInspect(data);
      } catch (e) {
        btn.disabled = true;
        statusEl.textContent = 'Falha ao verificar pendências.';
        reasonsEl.textContent = String(e.message || e);
        toast('error', 'Falha ao verificar pendências do banco.');
      }
    }

    function setRunning(running){
      spinner.classList.toggle('hidden', !running);
      if (!running) {
        clearInterval(pollingTimer);
        pollingTimer = null;
      }
      btn.disabled = running || !(lastInspect && lastInspect.pending);
      confirmBtn.disabled = running;
      cancelBtn.disabled = running;
    }

    async function poll(jobId){
      const data = await getJson(base + '/maintenance/db-upgrade/status/' + jobId);
      const job = data.job || {};
      runText.textContent = job.status === 'running' ? 'Executando…' : (job.status === 'done' ? 'Concluído' : 'Falhou');
      outEl.classList.remove('hidden');
      outEl.textContent = JSON.stringify(job, null, 2);
      if (job.status !== 'running') {
        setRunning(false);
        closeModal();
        await refreshInspect();
        if (job.status === 'done') {
          toast('success', 'Atualização do banco concluída com sucesso.');
        } else {
          toast('error', 'Falha ao atualizar o banco.');
        }
      }
    }

    btn.addEventListener('click', function(){
      if (!lastInspect || !lastInspect.pending) return;
      openModal();
    });
    modal.addEventListener('click', function(e){
      if (e.target === modal || e.target === modal.firstElementChild) {
        closeModal();
      }
    });
    cancelBtn.addEventListener('click', function(){
      closeModal();
    });
    confirmBtn.addEventListener('click', async function(){
      setRunning(true);
      runText.textContent = 'Iniciando…';
      outEl.classList.add('hidden');
      outEl.textContent = '';
      try {
        const res = await postJson(base + '/maintenance/db-upgrade/start', { _csrf: csrf });
        const jobId = res.job_id;
        runText.textContent = 'Executando…';
        await poll(jobId);
        pollingTimer = setInterval(() => poll(jobId).catch(() => {}), 1200);
      } catch (e) {
        setRunning(false);
        runText.textContent = 'Falhou';
        outEl.classList.remove('hidden');
        outEl.textContent = String(e.message || e);
        toast('error', String(e.message || 'Falha ao iniciar o upgrade.'));
      }
    });

    refreshInspect();
  })();
</script>
