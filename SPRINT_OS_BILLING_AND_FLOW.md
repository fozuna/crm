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

## 19. Correção do faturamento parcelado (R$ 120 em vez de R$ 1.500, "1/3" com 1 título, saldo R$ 0,00)

Reportado em produção para OS-000002: `Valor da OS: R$ 1.500,00` / `Parcelas geradas: 1`, mas a linha da parcela mostrava `1/3 — R$ 120,00 — Recebido R$ 0,00 — Saldo R$ 0,00`. Investigado com `git log -p -S` sobre todo o histórico de `ServiceOrderService.php` — três causas raiz distintas, todas confirmadas por evidência, nenhuma por suposição.

### Causa raiz 1 — R$ 120 em vez de R$ 1.500

`ServiceOrderService::receivablePayload()` (introduzido em `3045eb4`, 2026-07-01; removido nesta sprint em `0ac8bd8`, 2026-08-14) mapeava:

```php
'original_amount' => round((float) ($data['base_amount'] ?? 0) + (float) ($data['surcharge_amount'] ?? 0), 2),
```

Sem o multiplicador de horas. A fórmula real do valor final da OS, em `ServiceOrderValidator::calculateFinalAmount()`, é:

```php
return round(($estimatedHours * $baseAmount) - $discountAmount + $surchargeAmount, 2);
```

Ou seja: o título legado usava `base_amount + surcharge_amount` (uma taxa/valor de hora, não um total), enquanto `final_amount` é `estimated_hours × base_amount − discount_amount + surcharge_amount`. Para `base_amount=120`, essa é exatamente a origem do "R$ 120,00" observado — um bug de fórmula no código antigo, não relacionado a esta sprint (que já o removeu por completo). O fluxo atual (`ServiceOrderBillingService`) nunca lê `base_amount`; usa exclusivamente `servicos_avulsos.final_amount`, recalculado no servidor a cada faturamento — já era assim desde a sprint anterior, e o teste `service_order_reparcel_module.php` agora prova isso explicitamente com um cenário onde `base_amount=120` e `final_amount=1500` coexistem propositalmente na mesma OS de teste.

### Causa raiz 2 — "Parcelas geradas: 1" com a linha mostrando "1/3"

`FinancialModuleController::payloadFromRequest()` sempre aceitou `installment_number`/`total_installments` de um `<input type="number">` livre no formulário genérico de edição de recebível (`resources/views/financial/receivables/form.php`), e `FinancialReceivableService::update()`/`assertReceivableData()` nunca validavam esses valores contra a quantidade real de títulos vinculados. Ou seja: bastava alguém digitar "3" no campo "Total de parcelas" de um único título para reproduzir exatamente o sintoma, sem nenhuma parcela adicional existir de fato. Corrigido: `FinancialReceivableService::update()` agora **descarta** qualquer `installment_number`/`total_installments` submetido sempre que o título tiver `service_order_id` preenchido — esses dois campos passam a ser alteráveis **somente** pelo fluxo de faturamento/reparcelamento da própria OS, nunca pela tela genérica de recebíveis. Escopo da trava: apenas títulos nascidos de OS (`service_order_id IS NOT NULL`); títulos manuais, de projeto ou de proposta continuam com o comportamento anterior, sem mudança de escopo.

### Causa raiz 3 — saldo R$ 0,00 com recebido R$ 0,00

Não é bug na fórmula. `FinancialReceivableRepository::create()` calcula `remaining_amount = max(0, original_amount + interest_amount + fine_amount - discount_amount)`. Um saldo zerado com `received_amount = 0` só é matematicamente possível quando `discount_amount >= original_amount` — plausível para o título legado de R$ 120 (criado antes desta sprint, quando o campo `discount_amount` era copiado 1:1 do valor da OS, que por sua vez pode ter tido um desconto igual ou maior). A confusão era só de exibição: a tabela Financeiro da OS não mostrava a coluna "Desconto", então um saldo zerado ao lado de "Recebido: R$ 0,00" parecia inconsistente sem visibilidade do desconto que o explica. Corrigido exibindo a coluna Desconto nas tabelas Financeiro (`form.php`, `show.php`, `billing.php`) e validado por teste que a fórmula é aplicada corretamente a cada nova parcela criada.

### Nova funcionalidade: reparcelamento seguro

Implementado `ServiceOrderBillingService::reparcel()` — permite substituir a cobrança de uma OS já faturada por uma nova composição (ex.: corrigir um título legado incorreto como o de OS-000002, ou simplesmente mudar de 1x para 3x). **Estratégia de segurança escolhida** (dentre as sugeridas no pedido): bloquear completamente quando qualquer título atualmente vinculado à OS (via `service_order_id` **ou** o vínculo legado `financial_receivable_id`) já tiver `received_amount > 0` — a mensagem de erro identifica qual título e orienta a fazer a baixa/estorno pelo financeiro antes de reparcelar. Motivo da escolha: reaplicar recebimentos parciais sobre um cronograma novo é uma operação financeira de alto risco (qual parcela nova "recebe" o pagamento antigo?) que não tem uma resposta única correta — bloquear e exigir decisão manual no financeiro é a opção mais segura, evita perda/duplicação de dinheiro, e reaproveita a baixa/estorno já existentes em vez de inventar lógica nova. Quando nenhum título tem recebimento, o reparcelamento cancela (soft delete, auditado) os títulos antigos e cria a nova composição — tudo na mesma transação (`GET`/`POST /ordens-servico/{id}/faturar` passou a oferecer o botão "Reparcelar cobrança" quando aplicável; nova rota `POST /ordens-servico/{id}/reparcelar`).

Edição de vencimento/valor/descrição de uma parcela em aberto **já funcionava** via a tela genérica de recebíveis (`/financeiro/recebiveis/{id}/editar`, reaproveitada, com a nova trava de installment fields) — não foi necessária nenhuma tela nova para isso, só garantir que o link "Editar" esteja disponível a partir da OS (já estava em `form.php`; adicionado também em `show.php`).

### Arquivos alterados

- `app/Services/FinancialReceivableService.php` — trava de `installment_number`/`total_installments` para títulos de OS em `update()`.
- `app/Services/ServiceOrderBillingService.php` — novo método `reparcel()`; `linkedTitles()`/`createInstallmentPlan()` extraídos e reaproveitados por `invoice()` e `reparcel()`.
- `app/Controllers/ServiceOrderController.php` — novo método `reparcel()`; `billing()`/`invoice()` passam `canReparcel` para a view.
- `config/routes.php` — nova rota `POST /ordens-servico/{id}/reparcelar`.
- `resources/views/service_orders/billing.php` — oferece "Reparcelar cobrança" quando aplicável; tabela com coluna Desconto e labels de status.
- `resources/views/service_orders/form.php`, `resources/views/service_orders/show.php` — resumo (valor total/parcelas/recebido/saldo), coluna Desconto e labels de status na seção Financeiro.
- `tests/service_order_reparcel_module.php` (novo) — 26 verificações.
- `tests/run.php` — registra o novo arquivo.

### Alterações de banco

Nenhuma. Reaproveita integralmente `financial_accounts_receivable`/`financial_receipts` e a coluna `service_order_id` já criada na sprint anterior.

### Testes

`php tests/run.php`: **300 OK, 0 FAIL** (26 novas verificações). Cenário obrigatório validado explicitamente: OS com `final_amount=1500`, `base_amount=120` (propositalmente diferente, para provar que nunca é usado), faturada em 3 parcelas mensais a partir de 10/09/2026 → 3 títulos físicos de R$ 500,00 cada (nunca R$ 120,00), `total_installments=3` em cada um dos 3 títulos reais (nunca "1 gerado/3 informado"), vencimentos 10/09, 10/10, 10/11/2026 persistidos corretamente. Reparcelamento sem recebimento testado (2x, títulos antigos removidos, novos vinculados). Reparcelamento bloqueado com recebimento testado. Relatório Financeiro confirmado enxergando exatamente os títulos pós-reparcelamento, nunca os antigos substituídos.

### Validação manual

Smoke-test via `Router::dispatch()` real (transação nunca comitada) reproduzindo o cenário de OS-000002 (`base_amount=120`, `final_amount=1500`, faturada em 3x): página de faturamento renderiza com "Reparcelar cobrança" oferecido, "R$ 500,00" aparece 3 vezes, "120,00" não aparece em lugar nenhum da página; tela de edição da OS mostra a coluna "Desconto" e o rótulo de negócio "Pendente" (nunca `pending` cru).

### Pendência conhecida — título legado real em produção

A OS-000002 real de produção tem um título legado (id=3) criado pelo código antigo, com dados incorretos (`original_amount=120`, `total_installments=3` sem as 3 linhas físicas). Ele não foi alterado por esta correção — nenhum dado real de produção foi manipulado sem autorização. Ver **PARTE 3** da resposta final desta sprint para o SQL opcional (não executado) de diagnóstico e remediação segura desse título específico, e para a alternativa recomendada (resolver pela própria tela da OS, usando o novo "Reparcelar cobrança", depois que a coluna `service_order_id` e o código desta correção estiverem em produção).

### Confirmação

Nenhum commit, push ou deploy foi realizado automaticamente nesta correção.

## 20. Auditoria "hora técnica × valor final" — confirmação de que o código já estava correto, endurecimento de testes e plano de dados históricos

Reportado: OS com valor final R$ 480,00 e hora técnica (`base_amount`) R$ 120,00 gerando um recebível de R$ 120,00. Antes de tocar em qualquer código, foi feita uma nova auditoria completa e independente (não presumindo que a correção da seção 19 não tivesse funcionado).

### Veredito da auditoria

**O código atual (HEAD, a partir do commit `2212b4e`) já está correto — este não é um defeito vivo.** Confirmado por:
- Leitura de `ServiceOrderBillingService::invoice()`/`reparcel()`/`createInstallmentPlan()`/`buildInstallments()` (e todas as variantes único/parcelado/personalizado): a única fonte do valor é `$order['final_amount']`; nenhuma linha do arquivo referencia `base_amount`, `surcharge_amount`, `hourly_rate` ou `services.default_price`.
- `git log` completo de `ServiceOrderService.php`/`ServiceOrderBillingService.php`: nenhum commit após `0ac8bd8`/`2212b4e` reintroduziu o cálculo antigo.
- **Consulta somente-leitura real contra o banco de desenvolvimento** (nenhuma escrita): todo `financial_accounts_receivable` com `service_order_id` preenchido (ou seja, criado pelo fluxo atual) está zerado — a tabela ainda não tem nenhum título gerado pelo código novo neste ambiente — e os únicos 3 títulos divergentes encontrados (OS-000002/3/4) são vinculados exclusivamente pelo campo legado `financial_receivable_id`, criados em julho/2026, semanas antes de qualquer uma das duas correções (14/08/2026). Ou seja: **o sintoma relatado é a mesma classe de dado legado já documentada na seção 19, agora encontrada em outras OS, não uma regressão do código.**

### Campos (confirmação final)

- Hora técnica de referência: `servicos_avulsos.base_amount` (`DECIMAL(12,2)`) — nunca usada para faturar.
- Valor final faturável: `servicos_avulsos.final_amount` (`DECIMAL(12,2)`) — única fonte, calculado em `ServiceOrderValidator::calculateFinalAmount()` como `estimated_hours × base_amount − discount_amount + surcharge_amount`.
- Nenhum campo `origin_type`/`origin_id` genérico existe ou foi necessário — a origem "Ordem de Serviço" já é identificável via `financial_accounts_receivable.service_order_id` (criado na sprint anterior) + `external_reference = numero_os`.

### Testes adicionados (endurecimento, não correção)

Novo `tests/service_order_billing_value_source_module.php` (14 verificações), cobrindo exatamente os casos exigidos:
- OS de R$ 480,00 (hora técnica R$ 120,00) em pagamento único → título de R$ 480,00; recebimento parcial de R$ 180 → saldo R$ 300; quitação do restante → saldo R$ 0 e status `paid`.
- OS de R$ 550,00 (hora técnica R$ 120,00, propositalmente **não múltiplo** de 550, para descartar coincidência matemática) em 2 parcelas → 2 × R$ 275,00.
- OS, Financeiro e Relatório Financeiro (mesma fonte do Dashboard) exibindo o mesmo valor para o mesmo título.
- Alterar `base_amount`/`estimated_hours` da OS **depois** de faturada não retroage sobre o título já criado.
- **Trava estrutural anti-regressão**: o teste lê o código-fonte de `ServiceOrderBillingService.php` e falha se as strings `base_amount`, `hourly_rate`, `default_price` ou `$order['surcharge_amount']` aparecerem no arquivo, e falha se `$order['final_amount']` deixar de aparecer — qualquer regressão futura que volte a ler a hora técnica quebra a suíte imediatamente.

`php tests/run.php`: **314 OK, 0 FAIL** (14 novas verificações desta auditoria, além das 300 já existentes).

### Consulta de diagnóstico (somente leitura, validada, não altera dados)

```sql
SELECT
  so.id AS os_id,
  so.numero_os,
  so.final_amount AS valor_os,
  COALESCE(agg.total_titulo, legacy.original_amount, 0) AS financeiro_gerado,
  ROUND(so.final_amount - COALESCE(agg.total_titulo, legacy.original_amount, 0), 2) AS diferenca,
  COALESCE(agg.qtd_titulos, IF(legacy.id IS NOT NULL, 1, 0)) AS titulos_vinculados,
  COALESCE(agg.total_recebido, legacy.received_amount, 0) AS total_recebido
FROM servicos_avulsos so
LEFT JOIN (
  SELECT service_order_id,
         SUM(original_amount) AS total_titulo,
         SUM(received_amount) AS total_recebido,
         COUNT(*) AS qtd_titulos
  FROM financial_accounts_receivable
  WHERE deleted_at IS NULL AND service_order_id IS NOT NULL
  GROUP BY service_order_id
) agg ON agg.service_order_id = so.id
LEFT JOIN financial_accounts_receivable legacy
  ON legacy.id = so.financial_receivable_id AND legacy.deleted_at IS NULL
WHERE so.billable = 1
  AND so.deleted_at IS NULL
  AND (agg.total_titulo IS NOT NULL OR legacy.id IS NOT NULL)
  AND ABS(so.final_amount - COALESCE(agg.total_titulo, legacy.original_amount, 0)) > 0.01
ORDER BY diferenca DESC;
```

Reproduz a mesma regra de vínculo já usada pelo próprio sistema (`ServiceOrderController::receivablesForOrder()`): título(s) via `service_order_id` quando existirem, senão o vínculo legado `financial_receivable_id`. Só lista OS que já têm alguma cobrança gerada (não sinaliza OS ainda não faturadas). Executada no ambiente de desenvolvimento local (leitura, sem alterações): **3 OS divergentes encontradas** (OS-000002, OS-000003, OS-000004 — os mesmos 3 títulos legados de julho/2026), todas com `total_recebido = 0.00`.

### Plano para correção dos registros históricos (documentado, NÃO executado)

Distinção obrigatória por título:

**Sem nenhum recebimento** (é o caso das 3 OS encontradas no ambiente local) — caminho já pronto e seguro: um administrador abre `/ordens-servico/{id}/faturar` para a OS sinalizada pela consulta acima; como nenhum título vinculado tem `received_amount > 0`, o botão **"Reparcelar cobrança"** (seção 19) fica disponível — o título antigo incorreto é cancelado (soft delete, auditado) e a composição correta (único ou parcelado, à escolha do operador) é criada a partir de `final_amount`. Nenhum código novo é necessário; é literalmente o cenário para o qual a funcionalidade foi construída.

**Com algum recebimento** (nenhum caso encontrado localmente, mas pode existir em produção) — **não usar reparcelamento automático** (ele já bloqueia essa situação por design). Procedimento manual recomendado, sob supervisão financeira: manter o título original intocado (preserva o histórico do recebimento já feito); lançar um título complementar, pela tela `/financeiro/recebiveis/novo`, com a diferença (`valor_final_da_OS − valor_do_título_original`), descrição explícita referenciando a correção e a OS de origem, vinculado ao mesmo `service_order_id`/cliente. Decisão registrada aqui como recomendação; execução fica a critério do financeiro, título a título, nunca em lote automático.

### Riscos

- A consulta de diagnóstico não é executada automaticamente em nenhum fluxo do sistema (nem em testes, nem em rotina agendada) — é uma consulta avulsa, para uso manual do administrador via phpMyAdmin/SSH.
- Nenhum dado histórico foi alterado, cancelado ou recriado por esta auditoria.

### Confirmação

Nenhum commit, push, deploy ou alteração automática de dados históricos foi realizado nesta auditoria.

## 21. Resultado final

- OS pode ser faturada em pagamento único, parcelado (com arredondamento correto) ou personalizado (com validação de soma).
- Parcelas são criadas em `financial_accounts_receivable` (fonte oficial), vinculadas à OS e ao cliente.
- Duplicidade de faturamento é bloqueada.
- Edição e baixa de parcela (total/parcial) continuam funcionando via as telas financeiras já existentes, agora com a salvaguarda de valor mínimo.
- `/ordens-servico` exibe as OS reais, com indicadores e paginação corretos.
- `/ordens-servico/{id}` (Visualizar) exibe uma tela de detalhes de verdade, sem redirecionar para a edição; `/ordens-servico/{id}/editar` não quebra mais o layout para OS faturáveis com aprovação gerada (seção 18).
- O valor final da OS (nunca `base_amount`) é a única fonte do faturamento; uma OS de R$ 1.500,00 em 3 parcelas gera exatamente 3 títulos físicos de R$ 500,00; `total_installments` sempre corresponde à quantidade real de títulos e não pode mais ser editado livremente para um título de OS; saldo/desconto ficaram visíveis na interface; reparcelamento seguro (bloqueado quando há recebimento) permite corrigir uma cobrança incorreta (seção 19).
- Auditoria independente confirmou que a regra "valor final, nunca hora técnica" já estava corretamente implementada em todo o código atual (nenhuma regressão); os casos ainda relatados são dados legados de julho/2026, identificáveis pela consulta de diagnóstico da seção 20 e corrigíveis pelo próprio "Reparcelar cobrança" quando sem recebimento; trava estrutural nos testes impede regressão futura (seção 20).
- Suíte de testes: 314 OK, 0 FAIL.
- Nenhum commit, push, deploy ou alteração automática de dados históricos foi realizado.
