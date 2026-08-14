# SPRINT — Parcelamento de Ordens de Serviço, Gestão de Parcelas e Correção do Fluxo de OS

Data: 2026-08-14

## 1. Contexto

O faturamento de uma Ordem de Serviço (OS) só suportava um único lançamento financeiro (1/1), criado/atualizado silenciosamente a cada `create()`/`update()` da OS quando `billable = 1` — sem etapa de definição de cobrança e sem suporte a parcelamento. Além disso, a tela principal `/ordens-servico` (destino do menu lateral) sempre mostrava "0 registros" mesmo com OS cadastradas, enquanto `/ordens-servico/relatorios` exibia os dados corretamente, forçando uma navegação indireta e confusa.

## 2. Problemas encontrados

- **P1 — Faturamento rígido**: não era possível parcelar o valor de uma OS; o lançamento financeiro nascia automaticamente a cada salvamento da OS, não no momento do faturamento.
- **P2 — Tela principal de OS vazia**: `/ordens-servico` não refletia os dados reais do banco.
- **P3 — Ausência de trava**: nada impedia reduzir o valor de um título financeiro abaixo do valor já recebido.

## 3. Arquitetura anterior

- Controller fino (`ServiceOrderController`) → Service (`ServiceOrderService`) → Repository (`ServiceOrderRepository`), mesmo padrão para o financeiro (`FinancialModuleController` → `FinancialReceivableService` → `FinancialReceivableRepository`).
- `ServiceOrderService::syncReceivable()`/`receivablePayload()` criavam/atualizavam **um único** `financial_accounts_receivable` (`installment_number=1/total_installments=1`) a cada `create()`/`update()` da OS, vinculado via `servicos_avulsos.financial_receivable_id` (coluna escalar, um-para-um).
- `updateStatus()` apenas carimbava `completed_at` para `concluido`/`faturado` — "Faturado" já era só um rótulo de status, o lançamento financeiro nascia antes disso.

## 4. Auditoria do financeiro (obrigatória, feita antes de qualquer alteração)

Confirmada como fonte oficial: `financial_accounts_receivable` + `financial_receipts` (nenhum modelo paralelo foi criado).

- **Parcelas — já existe estrutura?** Sim, a nível de coluna: `installment_number`/`total_installments`/`recurrence_group`/`source_installment_id` já existiam em `financial_accounts_receivable`. O que faltava era (a) o vínculo um-para-muitos com uma OS (só havia `servicos_avulsos.financial_receivable_id`, escalar) e (b) uma orquestração que gerasse N parcelas a partir de uma única OS com regras de arredondamento/periodicidade.
- **Edição de parcela em aberto?** Já existia e completa (`FinancialModuleController::edit/update` → `FinancialReceivableService::update()`): vencimento, valor, descrição, observação, status, forma de pagamento. **Gap real encontrado**: `update()` não bloqueava reduzir o valor abaixo do já recebido (`FinancialReceivableRepository::update()` apenas fazia `max(0, ...)` silenciosamente). Corrigido nesta sprint (seção 9).
- **Baixa de parcela (total/parcial)?** Já existia e completa (`FinancialReceivableService::registerReceipt()` → `recalculateSnapshot()`): valor recebido, data, forma de pagamento, observação, cálculo correto de saldo (`remaining_amount`), status derivado (`pending`/`partially_paid`/`paid`/`overdue`).
- **Estorno?** Já existia (`reverseReceipt()`), soft (`reversed_at`/`reversed_by`/`reversal_reason`), recalcula o saldo.
- **Trilha de auditoria?** Já existia em duplicidade consistente: `financial_audit_logs` (dedicada, com `before_data`/`after_data` JSON) e `audit_log` (genérica), ambas escritas por `FinancialReceivableService::writeAudit()` em toda mutação.
- **`finance_installments` (legado)**: confirmado vivo, mas exclusivo do fluxo de financiamento de Projetos (`FinanceController`), sem qualquer uso por Ordens de Serviço. Não foi reutilizado nem removido — exatamente como pedido no brief.

## 5. Funcionalidades financeiras já existentes (reutilizadas sem alteração)

Edição de recebível, baixa total/parcial, estorno, cálculo de saldo, ambas as trilhas de auditoria, geração de PDF do recebível/recibo, exportação Excel/CSV/PDF do relatório financeiro, Dashboard Financeiro — todos já liam `financial_accounts_receivable` diretamente, então nenhuma dessas telas precisou de lógica especial para reconhecer os títulos gerados por OS.

## 6. Funcionalidades financeiras criadas

- Coluna `financial_accounts_receivable.service_order_id` (nullable, indexada, `FK ... ON DELETE SET NULL` para `servicos_avulsos`) — vínculo um-para-muitos que faltava.
- `FinancialReceivableRepository::listByServiceOrder()`.
- `FinancialReceivableService::createStandalone()` — cria um único título já totalmente especificado pelo chamador, sem passar pela expansão automática de `expandSchedule()` (ver seção 8 sobre por que isso era necessário).
- Salvaguarda em `FinancialReceivableService::update()`: bloqueia reduzir o valor de um título abaixo do já recebido, com mensagem explícita dos dois valores.
- `App\Services\ServiceOrderBillingService` — novo orquestrador do faturamento de OS (seção 8).

## 7. Nova regra de faturamento da OS

`ServiceOrderService::create()`/`update()` **não criam mais nenhum lançamento financeiro automaticamente**. O único ponto de entrada para gerar cobrança de uma OS passou a ser o fluxo explícito "Definir cobrança":

```
Concluído → Faturar OS → Definir cobrança → Confirmar → Criar contas a receber (transação) → OS = Faturado
```

`ServiceOrderService::updateStatus()` ganhou uma trava server-side: uma OS faturável só pode ir para `Faturado` se já possuir ao menos um título vinculado (via `service_order_id` ou o `financial_receivable_id` legado) — senão lança `RuntimeException` orientando a usar o fluxo de faturamento. OS não faturáveis continuam indo para `Faturado` normalmente, sem etapa financeira.

## 8. Parcelamento

`ServiceOrderBillingService::invoice()` (`app/Services/ServiceOrderBillingService.php`), chamado por `POST /ordens-servico/{id}/faturar`:

1. Recarrega a OS do banco (nunca confia em cliente/valor/status vindos do frontend).
2. Valida: `billable = 1`; nenhum título já vinculado à OS (trava de duplicidade); só então valida `status = concluído`.
3. Calcula o cronograma a partir de `mode` (`unico`/`parcelado`/`personalizado`):
   - **Único**: 1 parcela, valor = `final_amount` da OS recalculado no servidor (o campo "Valor" da tela é somente leitura).
   - **Parcelado**: quantidade + primeiro vencimento + periodicidade (mensal/quinzenal/semanal/personalizada em dias). Split igual com **arredondamento absorvido pela última parcela** — mesmo algoritmo já usado por `FinancialReceivableService::expandSchedule()` para o financiamento de projetos, generalizado aqui para as demais periodicidades.
   - **Personalizado**: linhas explícitas (valor/vencimento/descrição); a soma deve bater com o valor final da OS (tolerância de R$ 0,01), senão bloqueia com a diferença exata.
4. Em uma única transação: cria cada parcela via `FinancialReceivableService::createStandalone()`, vincula a 1ª à OS via `financial_receivable_id` (compatibilidade com a UI existente), muda o status da OS para `Faturado` (`ServiceOrderService::updateStatus()`) e grava histórico (`servicos_avulsos_historico`, ação `faturamento`). Qualquer falha faz rollback total — a OS nunca fica `Faturado` sem os lançamentos.

**Por que um método novo (`createStandalone`) em vez de reusar `FinancialReceivableService::create()` diretamente em loop?** `create()` sempre passa o payload por `expandSchedule()`, que reexpande qualquer payload com `total_installments > 1` usando sua própria matemática mensal — chamar `create()` uma vez por parcela já calculada faria cada parcela ser expandida de novo (parcela × parcela). `createStandalone()` foi extraído do mesmo `create()` (mesma validação `assertReceivableData()` e mesma auditoria via `writeAudit()`) para aceitar um único título já definitivo, sem reprocessamento.

## 9. Edição de parcelas

Reaproveitada integralmente (`/financeiro/recebiveis/{id}/editar`, já existente). Único ajuste: `FinancialReceivableService::update()` agora rejeita qualquer novo valor menor que `received_amount`, com a mensagem `"O novo valor (R$ X) não pode ser menor que o valor já recebido (R$ Y)."` — corrige o gap identificado na auditoria (seção 4) e vale tanto para títulos nascidos de OS quanto para os demais.

## 10. Baixas e recebimentos

Reaproveitados sem alteração (`/financeiro/recebiveis/{id}/baixa`, `/estornar`) — já suportavam baixa total, parcial e estorno com saldo calculado corretamente. A seção "Financeiro" da tela de OS (`resources/views/service_orders/form.php`) linka "Visualizar"/"Editar" diretamente para essas telas já existentes, em vez de duplicar a funcionalidade.

## 11. Correção da tela principal de OS

**Causa raiz confirmada por código**: `App\Core\View::render(string $view, array $data = [], ...)` tem um parâmetro chamado `$data` e faz `extract($data, EXTR_SKIP)`. `EXTR_SKIP` descarta silenciosamente qualquer chave do array de dados que colida com uma variável já existente no escopo — e `$data` (o array inteiro passado pelo controller) já ocupa esse nome. `ServiceOrderController::index()` passava a listagem paginada sob a chave literal `'data'`, que era descartada; a view caía no fallback `[]`/`0` e sempre mostrava "0 registros", mesmo com a query retornando linhas reais. `reports()` nunca teve esse problema por usar a chave `'report'`. Esse exato padrão de bug já havia sido corrigido uma vez para `ReportController::finance()` (`CRM_AUDIT.md`, achado P02) e o próprio `CRM_AUDIT.md` já registrava (sem corrigir) que `ServiceOrderController::index()` carregava o mesmo defeito — confirmado e corrigido nesta sprint.

**Correção aplicada**: chave renomeada para `'listing'` em `ServiceOrderController::index()` e em `resources/views/service_orders/index.php`. A tela principal ganhou também os indicadores operacionais (Em aberto/Em andamento/Concluídos/Faturados/Valor faturado/Tempo médio), alimentados por `ServiceOrderRepository::summary()` — uma agregação SQL sobre o conjunto completo filtrado (não a página nem o teto de segurança do relatório), reaproveitando os mesmos `baseFromWhere()`/`buildFilters()` já usados por `paginate()`.

**`/ordens-servico/relatorios` não foi removida nem redirecionada**: seu controller/view já funcionavam corretamente e o arquivo tem edições não relacionadas (integração WhatsApp) já em andamento na árvore de trabalho local, fora do escopo desta sprint. Como `/ordens-servico` passou a ter indicadores, filtros e paginação reais, `/relatorios` deixa de ser a única tela funcional — permanece disponível como visão secundária, sem gerar mais a sensação de tela principal vazia.

**Achado documentado, não corrigido (fora de escopo)**: o mesmo padrão de colisão de chave `'data'` também existe em `ServiceController::index()`/`services/index.php` (módulo de Catálogo de Serviços, não Ordens de Serviço) — já estava registrado em `CRM_AUDIT.md` e permanece pendente.

## 12. Rotas alteradas

```
GET  /ordens-servico/{id}/faturar   ServiceOrderController::billing   [auth, auditor]
POST /ordens-servico/{id}/faturar   ServiceOrderController::invoice   [auth, pm, csrf]
```

Nenhuma rota existente foi removida ou teve sua assinatura alterada.

## 13. Banco de dados

Migration aditiva, reversível e não destrutiva (schema.sql + upgrade.sql + `DbUpgradeRunner` para bancos já existentes):

```sql
ALTER TABLE financial_accounts_receivable
  ADD COLUMN service_order_id INT UNSIGNED NULL AFTER contract_id,
  ADD INDEX idx_financial_receivable_service_order (service_order_id),
  ADD CONSTRAINT fk_financial_receivable_service_order
    FOREIGN KEY (service_order_id) REFERENCES servicos_avulsos(id) ON DELETE SET NULL;
```

Rollback (não executado, documentado apenas): `ALTER TABLE financial_accounts_receivable DROP FOREIGN KEY fk_financial_receivable_service_order, DROP INDEX idx_financial_receivable_service_order, DROP COLUMN service_order_id;`. Nenhum campo redundante foi criado — `installment_number`/`total_installments`/`recurrence_group` já existiam e foram reutilizados; `origin_type`/`origin_id` genéricos não foram necessários porque a origem OS já é identificável via `service_order_id` (novo) + `external_reference = numero_os` (já existente).

Aplicado no ambiente de desenvolvimento local via `php tools/db_sync.php --env=development` (pré-requisito documentado em `CLAUDE.md` para rodar a suíte de testes) — schema/upgrade aplicados sem erros.

## 14. Arquivos alterados

- `app/Services/ServiceOrderBillingService.php` (novo) — orquestração do faturamento.
- `app/Services/ServiceOrderService.php` — remove `syncReceivable()`/`receivablePayload()`; trava server-side em `updateStatus()`.
- `app/Services/FinancialReceivableService.php` — `createStandalone()` + salvaguarda de valor abaixo do recebido.
- `app/Repositories/FinancialReceivableRepository.php`, `app/DTOs/FinancialReceivableData.php` — suporte a `service_order_id` + `listByServiceOrder()`.
- `app/Repositories/ServiceOrderRepository.php` — `summary()`.
- `app/Controllers/ServiceOrderController.php` — `billing()`/`invoice()`, correção da chave `'listing'`, seção Financeiro em `edit()`.
- `config/routes.php`, `database/schema.sql`, `database/upgrade.sql`, `app/Services/DbUpgradeRunner.php`.
- `resources/views/service_orders/billing.php` (novo), `resources/views/service_orders/index.php`, `resources/views/service_orders/form.php`.
- `tests/service_order_billing_module.php` (novo), `tests/service_orders_listing_summary.php` (novo), `tests/run.php`.

## 15. Testes

`php tests/run.php` — suíte completa, 258 verificações, 0 falhas (após aplicar a migration local). Cobertura nova relevante:

- OS sem cobrança não gera financeiro; OS não concluída rejeita o faturamento sem alterar seu status.
- Pagamento único usa o valor final recalculado no servidor.
- Parcelado 3×: arredondamento exato (R$ 333,33/R$ 333,33/R$ 333,34, soma sem perda/criação de centavos) e vencimentos mensais corretos a partir do primeiro informado.
- Personalizado: soma correta aceita; soma divergente é bloqueada com a diferença exata e **não cria nenhum título parcial** (nem a OS muda de status).
- Faturar a mesma OS duas vezes é bloqueado (título único preservado).
- Alterar status diretamente para `Faturado` sem cobrança definida é bloqueado; após faturar corretamente, o status reflete `Faturado`.
- Títulos ficam vinculados à OS (`service_order_id`) e ao cliente correto.
- Baixa parcial calcula saldo corretamente; reduzir o valor do título abaixo do recebido é bloqueado; reduzir mantendo acima do recebido continua permitido.
- `ServiceOrderRepository::paginate()`/`summary()` batem exatamente com os registros inseridos e respeitam os mesmos filtros — regressão direta do bug da tela principal.

**Bug lateral encontrado e corrigido durante a execução dos testes** (não fazia parte do escopo original, mas comprometia a confiabilidade do código de saída da suíte): `tests/run.php` usava a mesma variável `$failures` tanto como seu próprio acumulador quanto como o nome usado por *cada* arquivo de teste requerido (`database_structure.php`, `service_orders_module.php`, etc.). Como `require` compartilha escopo, o `$failures = 0;` do início de cada arquivo requerido zerava o acumulador de `run.php` antes de somar o próprio total — o texto `FAIL-` continuava sendo impresso corretamente, mas o `exit()` final podia retornar `0` mesmo havendo falhas reais em qualquer arquivo que não fosse o último requerido. Corrigido renomeando o acumulador de `run.php` para `$totalFailures`. Foi assim que a falha real de ordenação de validações do item 8 (abaixo) foi detectada e corrigida.

**Bug real encontrado pelos próprios testes novos, corrigido**: `ServiceOrderBillingService::invoice()` validava `status === concluído` antes de checar duplicidade — então tentar faturar uma OS **já faturada** (status `faturado`, não mais `concluído`) disparava a mensagem errada ("conclua a OS") em vez de "já possui cobrança gerada". Corrigido invertendo a ordem: duplicidade é checada antes do status.

## 16. Riscos

- A trava de arredondamento assume valores em BRL com 2 casas decimais; valores com mais casas nunca chegam à OS (campo já é `DECIMAL(12,2)`).
- `service_order_id` foi adicionado à `CREATE TABLE` de `upgrade.sql` na posição textual em que a tabela `financial_accounts_receivable` aparece nesse arquivo — anterior à declaração de `servicos_avulsos` no mesmo arquivo. Na prática isso nunca executa nessa ordem (`DbSyncRunner` sempre roda `schema.sql` primeiro, que já cria ambas as tabelas na ordem correta; o `CREATE TABLE IF NOT EXISTS` de `upgrade.sql` vira no-op), mas fica registrado como fragilidade textual caso `upgrade.sql` seja executado isoladamente contra um banco vazio.
- Ambientes de produção/homologação ainda não sincronizados precisarão rodar `php tools/deploy_preflight.php --env=<ambiente>` antes de liberar o acesso, conforme já exigido pelo `CLAUDE.md`.

## 17. Pendências

- `ServiceController::index()`/`services/index.php` têm o mesmo bug de colisão de chave `'data'` documentado em `CRM_AUDIT.md` e não corrigido (fora do escopo desta sprint, que era especificamente sobre Ordens de Serviço).
- Nenhuma integração de pagamento (PIX/boleto/NF) foi implementada — explicitamente fora de escopo.

## 18. Correção pós-deploy (2026-08-14) — layout quebrado em `/editar` e "Visualizar" abrindo "Editar"

Depois do deploy desta sprint em produção (e após aplicar a migration de `service_order_id`, ver seção 13), dois problemas foram reportados na tela de OS. Ambos foram investigados com evidência real (não suposição) antes de qualquer alteração.

### Causa raiz 1 — `TypeError` em `form.php:427` quebrando o layout inteiro

Confirmado via `storage/logs/app.log` de produção:

```
TypeError: Cannot access offset of type string on string in
.../resources/views/service_orders/form.php:427
```

`resources/views/service_orders/form.php` tinha **dois cards "Aprovação digital" duplicados** compartilhando a variável `$approvalBadge` com tipos incompatíveis:
- Linha 41 (setup): `$approvalBadge` era inicializado como **array** (`['label' => ..., 'class' => ...]`), mas nunca mais lido nesse formato.
- Linha ~362 (card "Aprovação digital do cliente", o único que sobrevive hoje): `$approvalBadge` era **reatribuído para string** via `match()` e usado corretamente logo em seguida.
- Linha 427 (um **segundo** card "Aprovação digital", morto/duplicado): tentava ler `$approvalBadge['class']`/`$approvalBadge['label']` como se ainda fosse array — mas por essa altura do arquivo a variável já era a string do card anterior, gerando o `TypeError`.

`git blame`/`git log -S` confirmam que esse segundo card foi introduzido pelo commit `2eacb3e` (2026-07-08), mais de um mês antes desta sprint, e nunca removido — **não é regressão da sprint de faturamento**, apenas nunca havia sido exercitado antes por só quebrar quando a OS já possui um link de aprovação gerado (`$approvalSummary !== null`).

**Por que isso derrubava o layout inteiro (sidebar, Tailwind, tudo)**: `App\Core\View::render()` faz `ob_start()`, `require $viewFile` (a view) e só depois `require $layoutFile` (o layout com `<head>`/sidebar). Como a exceção interrompe o `require` da view **dentro** desse buffer, o `layout.php` nunca chega a ser aplicado — só o HTML parcial que a view já tinha ecoado antes do crash (título, subtítulo, badge "Faturado") aparece na tela, sem nenhum CSS. Os ícones de `UI::icon()` (`<svg viewBox="0 0 24 24">` sem `width`/`height`, dependentes só da classe Tailwind `w-5 h-5`) caem no tamanho intrínseco padrão do navegador — daí o ícone gigante e roxo (cor herdada de link `:visited`) visto no print.

**Reprodução real, não só análise estática**: subimos um servidor local (`php -S` via `dev-router.php`) contra o código exatamente como estava (commit da sprint), criamos uma OS descartável faturável com um recebível legado vinculado e geramos um link de aprovação real via `ServiceOrderApprovalService::generateForServiceOrder()` — reproduzindo o `TypeError` de forma consistente antes da correção, e confirmando página saudável (55KB, sem "Erro interno") depois dela. Dados de teste e sessão fake removidos ao final.

**Correção**: removido o card duplicado/morto inteiro, junto das variáveis que só ele usava (`$approvalStatusMap`, `$formatDateTime`, `$canGenerateApproval`, `$approvalActionLabel` — todas mortas depois da remoção). O card "Aprovação digital do cliente" (o correto, já funcional) não foi tocado. A remoção também corrigiu um aninhamento de `<div>` quebrado que a inserção do card morto havia introduzido dentro do card "Anexos".

### Causa raiz 2 — "Visualizar" sempre abria "Editar"

`ServiceOrderController::show()` existe desde o commit `aa221ea` (2026-07-01) e sempre foi um redirect puro para `edit()`:

```php
public function show(Request $request, array $params): void
{
    $id = (int) ($params['id'] ?? 0);
    Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar');
}
```

**Nunca existiu uma tela de detalhes de verdade** — não é regressão desta sprint nem da anterior. O link "Visualizar" em `_actions.php` e a rota `GET /ordens-servico/{id}` já estavam corretos; só faltava a implementação real. `Router::match()` usa regex ancorada (`^...$`) com `{id}` mapeado para `[^/]+` (não casa `/`), então não há conflito entre `/ordens-servico/{id}` e `/ordens-servico/{id}/editar` — confirmado por leitura direta do roteador, descartando a hipótese de colisão de rotas.

**Correção**: `show()` agora carrega a OS (404 se não existir), seus anexos, histórico e parcelas financeiras, e renderiza a nova `resources/views/service_orders/show.php` — cabeçalho (número, serviço, badge de status, ações Editar/PDF/Financeiro/Voltar), informações gerais, seção Financeiro (mesma tabela de parcelas do `form.php`, ou "Cobrança ainda não gerada"/"não possui cobrança"), anexos (miniatura para imagens, ícone em tamanho normal — não gigante — para documentos, com nome/extensão/tamanho/download) e histórico em timeline. Nenhuma rota mudou.

### Arquivos alterados nesta correção

- `resources/views/service_orders/form.php` — remoção do card "Aprovação digital" duplicado/morto e das variáveis órfãs.
- `app/Controllers/ServiceOrderController.php` — `show()` deixou de redirecionar; agora carrega dados e renderiza a view de detalhes.
- `resources/views/service_orders/show.php` (novo) — tela de detalhes.
- `tests/service_order_layout_module.php` (novo) — dispara `/ordens-servico/{id}/editar` e `/ordens-servico/{id}` via `Router::dispatch()` real, reproduzindo o cenário exato de produção (OS faturável + recebível + aprovação gerada), e cobre OS inexistente em ambas as rotas.
- `tests/run.php` — registra o novo arquivo de teste.

### Testes

`php tests/run.php`: **273 OK, 0 FAIL** (era 258 antes desta correção; 15 novas verificações, sendo 14 do novo arquivo + o registro em `run.php`).

### Validação manual

Reproduzido e verificado via requisição HTTP real em processo local (sessão autenticada simulada, sem alterar dados reais — OS e link de aprovação de teste removidos ao final):
- `/ordens-servico/{id}/editar` de uma OS faturável com aprovação gerada: antes da correção, `TypeError` + layout quebrado; depois, página completa (57KB), sem "Erro interno", um único card de aprovação.
- `/ordens-servico/{id}` (Visualizar): antes, redirecionava para `/editar`; depois, renderiza a tela de detalhes de verdade (sem `<form>` de edição, com as seções Informações gerais/Financeiro/Anexos/Histórico).
- `/ordens-servico/999999999` e `/ordens-servico/999999999/editar`: 404 tratado (`"Ordem de serviço não encontrada."`) em ambas.

### Confirmação

Nenhum commit, push ou deploy foi realizado automaticamente nesta correção.

## 19. Resultado final

- OS pode ser faturada em pagamento único, parcelado (com arredondamento correto) ou personalizado (com validação de soma).
- Parcelas são criadas em `financial_accounts_receivable` (fonte oficial), vinculadas à OS e ao cliente.
- Duplicidade de faturamento é bloqueada.
- Edição e baixa de parcela (total/parcial) continuam funcionando via as telas financeiras já existentes, agora com a salvaguarda de valor mínimo.
- `/ordens-servico` exibe as OS reais, com indicadores e paginação corretos.
- `/ordens-servico/{id}` (Visualizar) exibe uma tela de detalhes de verdade, sem redirecionar para a edição; `/ordens-servico/{id}/editar` não quebra mais o layout para OS faturáveis com aprovação gerada (seção 18).
- Suíte de testes: 273 OK, 0 FAIL.
- Nenhum commit, push ou deploy foi realizado automaticamente.
