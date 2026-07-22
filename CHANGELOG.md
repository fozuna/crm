# CHANGELOG

Este changelog foi reconstruído a partir do histórico Git observável no repositório local. O projeto ainda não utiliza versionamento semântico formal no estado atual.

## Pendente de commit (2026-07-22)

- Sprint "Substituição da tela de login pela nova landing TRAXTER": reestiliza `resources/views/auth/login.php` (landing institucional + modal de login) para um tema grafite/laranja com tipografia Archivo/Inter/IBM Plex Mono, mantendo fluxo, dados reais e arquitetura já existentes (nenhuma rota, controller ou middleware alterado). Remove a possibilidade de conteúdo fictício (sem seção de preços, sem depoimentos/cases placeholder) e adiciona `tests/landing_login_page.php` (registrado em `tests/run.php`) para impedir regressão desse tipo de conteúdo. Ver `LANDING_INTEGRATION.md` para o detalhamento completo.

## Pendente de commit (2026-07-19)

- Corrige a causa real de `/relatorios/financeiro` continuar divergente de `/financeiro/dashboard` mesmo após a correção de fonte de dados de 2026-07-17 (abaixo): `App\Core\View::render()` usa `$data` como nome do próprio parâmetro e faz `extract($data, EXTR_SKIP)`, que descarta silenciosamente qualquer chave `'data'` passada no array de parâmetros — `ReportController::finance()` empacotava o view-model exatamente sob essa chave, então a view sempre renderizava com o array de parâmetros externo (sem `totals`/`installments`), mostrando 0 títulos/R$0,00 mesmo com o repositório já corrigido. Bug pré-existente à correção de 2026-07-17, não introduzido por ela. Corrigido renomeando a chave para `'report'` em `ReportController::finance()` e em `resources/views/reports/finance.php`. Novo teste `tests/finance_report_controller.php` dispara a rota real via `Router::dispatch()` e inspeciona o HTML renderizado (não apenas o retorno do repositório) para evitar regressão silenciosa desta classe de bug. Ver `SPRINT_FINANCE_REPORT_FIX.md`, seção 13-B, e `CRM_AUDIT.md`, achado P02 "Atualização 2".
- Achado novo registrado (não corrigido, fora de escopo): o mesmo padrão de `View::render()` com chave `'data'` também existe em `ServiceOrderController::index()` e `ServiceController::index()`, sujeitando as telas de listagem de Ordens de Serviço e de Serviços ao mesmo bug estrutural.

## Pendente de commit (2026-07-17)

- Corrige P02 (`CRM_AUDIT.md`): `/relatorios/financeiro` lia exclusivamente `finance_installments` (módulo financeiro legado, ligado a proposta → projeto), o que o tornava estruturalmente incapaz de exibir qualquer título nascido de Ordem de Serviço, renegociação ou lançamento manual do módulo financeiro corporativo — por isso a tela podia mostrar totais divergentes (inclusive zerados) enquanto `/financeiro/dashboard`, que já lia `financial_accounts_receivable` diretamente, mostrava os valores reais. `ReportController::finance()`/`financeExportExcel()`/`financeExportPdf()` agora reaproveitam `FinancialReceivableRepository`/`FinancialReceiptRepository` (mesma fonte do Dashboard Financeiro), com uma coluna "Origem" explícita (Ordem de serviço / Proposta-Projeto / Contrato / Manual). Ver `SPRINT_FINANCE_REPORT_FIX.md` para o diagnóstico e a validação completos.
- Adiciona `FinancialReceivableRepository::reportRows()` (sem o teto de 100 linhas de `paginate()`, usado apenas pela listagem operacional) para evitar reintroduzir no financeiro o mesmo bug de truncamento silencioso já corrigido para Ordens de Serviço em P03.
- Adiciona `FinancialReceiptRepository::listByPeriod()`/`totalReceived()`, filtrando recebimentos pela data real de pagamento (`payment_date`), não pelo vencimento do título.

## Pendente de commit (2026-07-16)

- Corrige P03 (`CRM_AUDIT.md`): o relatório de Ordens de Serviço (`/ordens-servico/relatorios`) pedia 500 linhas ao repositório, mas `ServiceOrderRepository::paginate()` capava silenciosamente em 100, fazendo OS mais antigas "sumirem" do relatório e dos KPIs sem qualquer aviso. Adicionado `ServiceOrderRepository::reportRows()`, um método de relatório dedicado sem o teto de paginação operacional, com teto de segurança de 2000 registros e sinalização explícita de truncamento na tela quando esse teto é atingido.
- Corrige bug correlato encontrado durante a investigação do P03: o filtro de texto (`q`) da listagem e do relatório de OS reutilizava o mesmo placeholder nomeado `:q` cinco vezes na mesma query, o que quebra com erro 500 sob prepares nativos (`ATTR_EMULATE_PREPARES => false`). Corrigido com placeholders distintos por ocorrência.

## 2026-07-08

### `b6d1d44`

- Corrige o handler do erro 500 em produção.

### `fe870fe`

- Corrige o ambiente do sincronizador em produção.

### `2eacb3e`

- Automatiza a sincronização do banco e padroniza PDFs de propostas.

## 2026-07-03

### `d58fd23`

- Transforma a tela de login em landing page institucional.

## 2026-07-01

### `8b793ed`

- Torna constraints de projetos e financeiro idempotentes.

### `94c19d0`

- Torna o upgrade de usuários idempotente.

### `cd67494`

- Torna o upgrade de propostas idempotente.

### `7c2197c`

- Corrige o upgrade de clientes para execução idempotente.

### `aa221ea`

- Corrige cálculo financeiro das ordens de serviço e padroniza PDFs.

### `3045eb4`

- Implementa o módulo de ordens de serviço e valida o upgrade do banco.

### `08c4b2f`

- Implementa o fluxo de leads para propostas e atualiza o upgrade SQL.

## 2026-05-22

### `59643d9`

- Altera o ícone de recebíveis para cifrão.
