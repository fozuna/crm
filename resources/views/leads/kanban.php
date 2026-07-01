<?php
use App\Core\View;

$board = is_array($board ?? null) ? $board : [];
$q = (string) ($q ?? '');
$formatPhone = static function (?string $value): string {
    $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
    if (strlen($digits) === 11) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    }
    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    }
    return (string) $value;
};
?>

<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
  <div>
    <div class="text-2xl font-semibold">Kanban de Leads</div>
    <div class="text-slate-600 mt-1">Acompanhe o funil comercial, mova cards entre estágios e converta leads aprovados em clientes ativos.</div>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <a class="tr-btn tr-btn--accent" href="<?= View::e($base . '/leads/novo') ?>">Novo lead</a>
  </div>
</div>

<form method="get" action="<?= View::e($base . '/leads') ?>" class="mt-6 tr-card p-4">
  <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr),auto,auto]">
    <div>
      <label class="tr-label">Buscar lead</label>
      <input type="text" name="q" value="<?= View::e($q) ?>" class="mt-1 tr-input" placeholder="Nome, e-mail, telefone ou fonte">
    </div>
    <button class="tr-btn self-end" type="submit">Filtrar</button>
    <a class="tr-btn self-end" href="<?= View::e($base . '/leads') ?>">Limpar</a>
  </div>
</form>

<div class="mt-6 overflow-x-auto pb-2">
  <div id="kanbanBoard" class="grid min-w-[1260px] grid-cols-6 gap-4 xl:min-w-0 xl:grid-cols-6">
    <?php foreach ($board as $column): ?>
      <?php
        $stage = (string) ($column['stage'] ?? '');
        $label = (string) ($column['label'] ?? '');
        $items = is_array($column['items'] ?? null) ? $column['items'] : [];
      ?>
      <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-4 py-4">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-900"><?= View::e($label) ?></h2>
            <span class="inline-flex min-w-8 items-center justify-center rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600"><?= count($items) ?></span>
          </div>
        </header>
        <div class="kanban-column min-h-[280px] space-y-3 p-4" data-stage="<?= View::e($stage) ?>">
          <?php if (count($items) === 0): ?>
            <div class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-sm text-slate-400">Sem leads nesta etapa.</div>
          <?php endif; ?>
          <?php foreach ($items as $item): ?>
            <article
              class="kanban-card cursor-pointer rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-white"
              draggable="true"
              data-lead-id="<?= (int) ($item['id'] ?? 0) ?>"
              data-stage="<?= View::e((string) ($item['stage'] ?? '')) ?>"
              data-edit-url="<?= View::e($base . '/leads/' . (int) ($item['id'] ?? 0) . '/editar') ?>"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="text-sm font-semibold text-slate-900"><?= View::e((string) (($item['company'] ?? '') !== '' ? $item['company'] : ($item['name'] ?? 'Lead'))) ?></div>
                  <div class="mt-1 text-xs text-slate-500"><?= View::e((string) ($item['name'] ?? '')) ?></div>
                </div>
                <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">#<?= (int) ($item['id'] ?? 0) ?></span>
              </div>
              <div class="mt-4 space-y-2 text-sm text-slate-600">
                <div><span class="font-medium text-slate-700">Telefone:</span> <?= View::e($formatPhone((string) ($item['phone'] ?? ''))) ?></div>
                <div><span class="font-medium text-slate-700">Fonte:</span> <?= View::e((string) ($item['acquisition_source'] ?? '—')) ?></div>
                <div><span class="font-medium text-slate-700">Cadastro:</span> <?= View::e(date('d/m/Y', strtotime((string) ($item['created_at'] ?? 'now')))) ?></div>
              </div>
              <div class="mt-4 flex items-center justify-between">
                <span class="text-xs text-slate-400"><?= View::e((string) ($item['market_segment'] ?? '')) ?></span>
                <span class="text-xs font-medium text-slate-500">Abrir cadastro</span>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<div id="approveModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4">
  <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
    <div class="border-b border-slate-200 px-6 py-4">
      <div class="text-lg font-semibold text-slate-900">Confirmar conversão do lead</div>
      <div class="mt-1 text-sm text-slate-600">Revise os dados antes de criar o cliente ativo e concluir a aprovação.</div>
    </div>
    <div class="px-6 py-5 space-y-5">
      <div id="approveSummary" class="grid grid-cols-1 gap-3 rounded-2xl bg-slate-50 p-4 md:grid-cols-2"></div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="tr-label">E-mail de faturamento</label>
          <input id="billing_email" type="email" class="mt-1 tr-input" placeholder="financeiro@cliente.com.br">
        </div>
        <div>
          <label class="tr-label">Telefone de faturamento</label>
          <input id="billing_phone" type="text" class="mt-1 tr-input" placeholder="(00) 00000-0000">
        </div>
        <div class="md:col-span-2">
          <label class="tr-label">Observações contratuais</label>
          <textarea id="contract_notes" rows="3" class="mt-1 tr-input" placeholder="Pontos relevantes para contrato e onboarding"></textarea>
        </div>
        <div class="md:col-span-2">
          <label class="tr-label">Observações de faturamento</label>
          <textarea id="billing_notes" rows="3" class="mt-1 tr-input" placeholder="Regras de cobrança, centro de custo, dias preferenciais etc."></textarea>
        </div>
      </div>
    </div>
    <div class="flex items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
      <button id="approveCancel" type="button" class="tr-btn">Cancelar</button>
      <button id="approveConfirm" type="button" class="tr-btn tr-btn--accent">Converter em cliente</button>
    </div>
  </div>
</div>

<script>
  (function () {
    const base = <?= json_encode((string) $base, JSON_UNESCAPED_UNICODE) ?>;
    const csrf = <?= json_encode((string) $csrf, JSON_UNESCAPED_UNICODE) ?>;
    const board = document.getElementById('kanbanBoard');
    const modal = document.getElementById('approveModal');
    const modalSummary = document.getElementById('approveSummary');
    const approveCancel = document.getElementById('approveCancel');
    const approveConfirm = document.getElementById('approveConfirm');
    const billingEmail = document.getElementById('billing_email');
    const billingPhone = document.getElementById('billing_phone');
    const contractNotes = document.getElementById('contract_notes');
    const billingNotes = document.getElementById('billing_notes');
    let draggedCard = null;
    let approvingLeadId = 0;

    function toast(type, message) {
      if (typeof window.trToast === 'function') {
        window.trToast(type, message);
        return;
      }
      window.alert(message);
    }

    function api(url, payload) {
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify(Object.assign({_csrf: csrf}, payload || {}))
      }).then(async function (res) {
        const json = await res.json().catch(function () { return null; });
        if (!res.ok || !json || json.ok !== true) {
          throw new Error(json && (json.error || json.message) ? (json.error || json.message) : 'Falha ao processar a ação.');
        }
        return json.data || {};
      });
    }

    function openApproveModal(lead) {
      approvingLeadId = Number(lead && lead.id ? lead.id : 0);
      if (!approvingLeadId) {
        return;
      }
      modalSummary.innerHTML = '';
      [
        ['Lead', lead.company || lead.name || '—'],
        ['Contato', lead.contact_person || lead.name || '—'],
        ['Documento', lead.document_number || '—'],
        ['E-mail', lead.email || '—'],
        ['Telefone', lead.phone || '—'],
        ['Fonte', lead.acquisition_source || '—'],
        ['Segmento', lead.market_segment || '—'],
        ['Endereço', [lead.street, lead.street_number, lead.neighborhood, lead.city, lead.state].filter(Boolean).join(', ') || '—']
      ].forEach(function (row) {
        const div = document.createElement('div');
        div.className = 'rounded-xl border border-slate-200 bg-white px-3 py-3';
        div.innerHTML = '<div class="text-xs uppercase tracking-wide text-slate-400">' + row[0] + '</div><div class="mt-1 text-sm font-medium text-slate-800">' + String(row[1]).replace(/</g, '&lt;') + '</div>';
        modalSummary.appendChild(div);
      });
      billingEmail.value = lead.email || '';
      billingPhone.value = lead.phone || '';
      contractNotes.value = '';
      billingNotes.value = '';
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeApproveModal() {
      approvingLeadId = 0;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    if (approveCancel) {
      approveCancel.addEventListener('click', closeApproveModal);
    }
    if (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          closeApproveModal();
        }
      });
    }

    if (approveConfirm) {
      approveConfirm.addEventListener('click', function () {
        if (!approvingLeadId) {
          return;
        }
        approveConfirm.disabled = true;
        api(base + '/api/leads/' + approvingLeadId + '/convert', {
          billing_email: billingEmail.value || '',
          billing_phone: billingPhone.value || '',
          contract_notes: contractNotes.value || '',
          billing_notes: billingNotes.value || ''
        }).then(function (data) {
          toast('success', 'Lead convertido com sucesso.');
          const clientId = Number(data && data.client_id ? data.client_id : 0);
          if (clientId > 0) {
            window.location.href = base + '/clientes/' + clientId;
            return;
          }
          window.location.reload();
        }).catch(function (error) {
          toast('error', error.message || 'Não foi possível converter o lead.');
        }).finally(function () {
          approveConfirm.disabled = false;
          closeApproveModal();
        });
      });
    }

    function bindCard(card) {
      card.addEventListener('dragstart', function () {
        draggedCard = card;
        card.classList.add('opacity-60');
      });
      card.addEventListener('dragend', function () {
        card.classList.remove('opacity-60');
        draggedCard = null;
      });
      card.addEventListener('click', function (event) {
        if (event.target.closest('button, a, input, textarea, select')) {
          return;
        }
        const url = card.dataset.editUrl || '';
        if (url) {
          window.location.href = url;
        }
      });
    }

    document.querySelectorAll('.kanban-card').forEach(bindCard);

    document.querySelectorAll('.kanban-column').forEach(function (column) {
      column.addEventListener('dragover', function (event) {
        event.preventDefault();
        column.classList.add('bg-slate-50');
      });
      column.addEventListener('dragleave', function () {
        column.classList.remove('bg-slate-50');
      });
      column.addEventListener('drop', function (event) {
        event.preventDefault();
        column.classList.remove('bg-slate-50');
        if (!draggedCard) {
          return;
        }
        const leadId = Number(draggedCard.dataset.leadId || 0);
        const fromStage = String(draggedCard.dataset.stage || '');
        const toStage = String(column.dataset.stage || '');
        if (!leadId || !toStage || fromStage === toStage) {
          return;
        }

        if (toStage === 'aprovado') {
          fetch(base + '/api/leads/' + leadId, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
          }).then(function (res) {
            return res.json();
          }).then(function (json) {
            if (!json || json.ok !== true || !json.data || !json.data.lead) {
              throw new Error('Não foi possível carregar os dados do lead para aprovação.');
            }
            openApproveModal(json.data.lead);
          }).catch(function (error) {
            toast('error', error.message || 'Falha ao abrir aprovação.');
          });
          return;
        }

        api(base + '/api/leads/' + leadId + '/stage', {stage: toStage}).then(function (data) {
          if (toStage === 'proposta_enviada') {
            const redirectUrl = data && data.redirect_url ? String(data.redirect_url) : '';
            if (!redirectUrl) {
              toast('warning', 'Lead movido para Proposta Enviada, mas o redirecionamento não foi preparado. Tente abrir a proposta novamente.');
              window.location.href = base + '/propostas/nova?lead_id=' + leadId;
              return;
            }
            window.location.href = redirectUrl;
            return;
          }
          window.location.reload();
        }).catch(function (error) {
          const fallback = toStage === 'proposta_enviada'
            ? 'Falha ao mover o lead para Proposta Enviada. Revise o lead e tente novamente.'
            : 'Falha ao mover o lead.';
          toast('error', error.message || fallback);
        });
      });
    });
  })();
</script>
