<?php
use App\Core\View;

$title = 'Manual do Sistema';

$sections = [
    ['id' => 'visao-geral', 'title' => 'Visão geral'],
    ['id' => 'navegacao', 'title' => 'Navegação'],
    ['id' => 'propostas', 'title' => 'Propostas'],
    ['id' => 'projetos', 'title' => 'Projetos'],
    ['id' => 'financeiro', 'title' => 'Financeiro e parcelas'],
    ['id' => 'relatorios', 'title' => 'Relatórios financeiros'],
    ['id' => 'compatibilidade', 'title' => 'Compatibilidade, API e integrações'],
    ['id' => 'requisitos', 'title' => 'Requisitos técnicos'],
    ['id' => 'navegadores', 'title' => 'Navegadores suportados'],
    ['id' => 'seguranca', 'title' => 'Segurança e boas práticas'],
    ['id' => 'suporte', 'title' => 'Suporte e resolução de problemas'],
];
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Manual do Sistema</div>
    <div class="text-slate-600 mt-1">Guia rápido de uso, requisitos e compatibilidade</div>
  </div>
</div>

<div class="mt-6 grid grid-cols-1 lg:grid-cols-4 gap-6">
  <aside class="tr-card p-5 lg:sticky lg:top-6 h-fit">
    <label class="tr-label" for="manualSearch">Buscar no manual</label>
    <input id="manualSearch" class="mt-1 tr-input" placeholder="Digite para filtrar o índice">
    <div class="mt-4 text-sm font-semibold text-slate-700">Índice</div>
    <ul id="manualToc" class="mt-2 space-y-1 text-sm">
      <?php foreach ($sections as $s): ?>
        <li>
          <a class="block rounded px-2 py-1 hover:bg-slate-100 text-slate-700" href="#<?= View::e($s['id']) ?>" data-title="<?= View::e(strtolower($s['title'])) ?>"><?= View::e($s['title']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
    <div id="manualNoResults" class="hidden mt-3 text-sm text-slate-600">Nenhum item encontrado.</div>
  </aside>

  <section class="lg:col-span-3 space-y-6">
    <div id="visao-geral" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Visão geral</div>
      <div class="mt-2 text-sm text-slate-700 leading-6">
        Este sistema organiza o ciclo completo de vendas e execução: clientes, propostas, projetos e financeiro (parcelas e pagamentos).
        Use o menu lateral para navegar entre os módulos.
      </div>
    </div>

    <div id="navegacao" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Navegação</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li><span class="font-semibold">Dashboard</span>: visão geral dos indicadores.</li>
        <li><span class="font-semibold">Clientes</span>: cadastro e acompanhamento.</li>
        <li><span class="font-semibold">Propostas</span>: criação, itens e geração de PDF.</li>
        <li><span class="font-semibold">Projetos</span>: execução e marcos; inclui financeiro por projeto.</li>
        <li><span class="font-semibold">Relatórios</span>: visão consolidada de parcelas/pagamentos no período.</li>
      </ul>
    </div>

    <div id="propostas" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Propostas</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li>Cadastre itens e descreva escopo para gerar um PDF consistente.</li>
        <li>Se o catálogo de serviços estiver habilitado, selecione o serviço e ajuste valores conforme necessário.</li>
        <li>Itens marcados como bônus não entram no subtotal, mas aparecem na apresentação.</li>
      </ul>
    </div>

    <div id="projetos" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Projetos</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li>Projetos normalmente são criados a partir de propostas aprovadas.</li>
        <li>No financeiro do projeto, registre pagamentos e acompanhe o status das parcelas.</li>
      </ul>
    </div>

    <div id="financeiro" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Financeiro e parcelas</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li><span class="font-semibold">Editar</span>: ajusta vencimento, valor e status da parcela.</li>
        <li><span class="font-semibold">Baixar</span>: registra um pagamento para a parcela (com método, referência e observação opcionais).</li>
        <li><span class="font-semibold">Adiantar</span>: distribui um valor no projeto, criando antecipação conforme regra do módulo.</li>
        <li><span class="font-semibold">Excluir</span>: remove a parcela quando aplicável (ação irreversível).</li>
      </ul>
      <div class="mt-3 text-sm text-slate-700 leading-6">
        Valores monetários devem ser digitados no padrão pt-BR (ex: <span class="font-semibold">1.234,56</span>). O sistema aplica máscara e normalização no backend.
      </div>
    </div>

    <div id="relatorios" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Relatórios financeiros</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li>Ao abrir a página de relatório, o período padrão é o mês atual.</li>
        <li>Use filtros (datas, cliente, projeto e status) para refinar resultados.</li>
        <li>Exporte para PDF ou Excel para auditoria e compartilhamento.</li>
      </ul>
    </div>

    <div id="compatibilidade" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Compatibilidade, API e integrações</div>
      <div class="mt-2 text-sm text-slate-700 leading-6">
        Os mesmos dados do relatório financeiro também estão disponíveis via API, utilizando os mesmos filtros de período:
      </div>
      <div class="mt-2 text-sm">
        <div class="font-semibold text-slate-700">Endpoints principais</div>
        <ul class="mt-1 list-disc pl-5 space-y-1 text-slate-700">
          <li><span class="font-semibold">/api/finance/revenues/metrics</span> (KPIs do período)</li>
          <li><span class="font-semibold">/api/finance/revenues/installments</span> (parcelas)</li>
          <li><span class="font-semibold">/api/finance/revenues/payments</span> (pagamentos)</li>
        </ul>
      </div>
      <div class="mt-3 text-sm text-slate-700 leading-6">
        A API exige sessão autenticada e respeita permissões do usuário. Para integrações externas, recomenda-se um usuário dedicado e auditoria de acessos.
      </div>
    </div>

    <div id="requisitos" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Requisitos técnicos</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li>Conexão estável com a internet (ou rede interna, conforme hospedagem).</li>
        <li>Resolução recomendada: a partir de 1366×768 para melhor visualização de tabelas.</li>
        <li>JavaScript habilitado para recursos de carregamento dinâmico e busca.</li>
        <li>Cookies habilitados para manter a sessão autenticada.</li>
      </ul>
    </div>

    <div id="navegadores" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Navegadores suportados</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li>Google Chrome (versão atual e anterior)</li>
        <li>Microsoft Edge (versão atual e anterior)</li>
        <li>Mozilla Firefox (versão atual e anterior)</li>
        <li>Safari (macOS/iOS) (versão atual e anterior)</li>
      </ul>
      <div class="mt-3 text-sm text-slate-700 leading-6">
        Caso utilize extensões de bloqueio (adblock), desative para o domínio do sistema se houver falhas de carregamento.
      </div>
    </div>

    <div id="seguranca" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Segurança e boas práticas</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li>Não compartilhe credenciais e use senhas fortes.</li>
        <li>Finalize a sessão ao sair (botão “Sair”).</li>
        <li>Evite abrir o sistema em redes públicas sem VPN.</li>
        <li>Para auditoria, mantenha o módulo de auditoria habilitado e revise periodicamente.</li>
      </ul>
    </div>

    <div id="suporte" class="tr-card p-6 scroll-mt-24">
      <div class="text-lg font-semibold">Suporte e resolução de problemas</div>
      <ul class="mt-2 text-sm text-slate-700 leading-6 list-disc pl-5 space-y-1">
        <li>Se uma tela ficar “sem dados”, confirme o período do filtro e tente recarregar a página.</li>
        <li>Se algum botão não funcionar, verifique se o navegador está bloqueando popups ou scripts.</li>
        <li>Se persistir, registre o horário do problema, URL acessada e usuário, e encaminhe ao suporte técnico.</li>
      </ul>
    </div>
  </section>
</div>

<script>
  (function(){
    const input = document.getElementById('manualSearch');
    const toc = document.getElementById('manualToc');
    const no = document.getElementById('manualNoResults');
    if (!input || !toc || !no) return;

    function norm(s){
      return String(s || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
    }

    function apply(){
      const q = norm(input.value);
      let visible = 0;
      toc.querySelectorAll('a[data-title]').forEach(a => {
        const t = norm(a.getAttribute('data-title'));
        const show = q === '' ? true : (t.includes(q));
        a.parentElement.classList.toggle('hidden', !show);
        if (show) visible++;
      });
      no.classList.toggle('hidden', visible > 0);
    }

    input.addEventListener('input', apply);
    apply();
  })();
</script>
