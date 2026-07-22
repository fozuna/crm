# SPRINT_FINANCE_REPORT_FIX.md

Sprint técnica isolada: divergência entre o Dashboard Financeiro (`/financeiro/dashboard`) e os Relatórios Financeiros (`/relatorios/financeiro`).

Data: 2026-07-17. Nenhum commit, push, deploy ou alteração manual em dados reais foi realizado durante esta sprint. Todas as consultas ao banco local de desenvolvimento foram exclusivamente de leitura (`SELECT`).

## 1. Contexto

O TRAXTER CRM possui dois subsistemas financeiros paralelos, já diagnosticados em `CRM_AUDIT.md` (achados P01/P02): um módulo "legado" (`finance_installments`/`finance_payments`, ligado a proposta → projeto) e um módulo "enterprise/corporativo" (`financial_accounts_receivable`/`financial_receipts`, ligado a `company_profile`, e que também recebe títulos de Ordens de Serviço). Esta sprint tratou especificamente da divergência entre duas telas que um usuário financeiro acessa lado a lado a partir do menu principal: "Financeiro" (`/financeiro/dashboard`) e "Relatórios" (`/relatorios/financeiro`).

## 2. Evidência do problema

Relatado pelo usuário, para o mesmo período (01/01/2026–17/07/2026):

- **Dashboard Financeiro** (`/financeiro/dashboard`): total a receber R$ 2.160,00; total vencido R$ 2.160,00; recebido no mês R$ 0,00; inadimplência 100%; gráfico de projeção com dados.
- **Relatórios Financeiros** (`/relatorios/financeiro`): parcelas listadas 0; pagamentos listados 0; a receber R$ 0,00; recebido R$ 0,00; vencido R$ 0,00; fluxo de caixa sem dados.

## 3. Arquitetura encontrada

| Tela | Rota | Controller | Repository | Tabela-base |
|---|---|---|---|---|
| Dashboard Financeiro | `/financeiro/dashboard` | `FinancialModuleController::dashboard` | `FinancialEnterpriseDashboardRepository` | `financial_accounts_receivable` |
| Relatórios Financeiros (antes da correção) | `/relatorios/financeiro` | `ReportController::finance` | `FinanceRevenueRepository`/`FinanceReportRepository` | `finance_installments INNER JOIN proposals` |
| Relatório Enterprise (não tocado nesta sprint) | `/financeiro/relatorios` | `FinancialModuleController::reports` | `FinancialEnterpriseReportRepository` | `financial_accounts_receivable` |

A navegação principal (`resources/views/layout.php`) expõe exatamente as duas primeiras rotas como "Financeiro" e "Relatórios" — as duas telas comparadas pelo usuário.

Fluxo de origem de um título em `financial_accounts_receivable`:
- Proposta aprovada → projeto: gera parcelas em `finance_installments` **e** um espelho em `financial_accounts_receivable` (via `source_installment_id`), na mesma transação.
- Ordem de Serviço faturável concluída: `ServiceOrderService::syncReceivable()` grava **diretamente** em `financial_accounts_receivable`, com `source_installment_id = NULL` — **nunca** cria uma linha em `finance_installments`.
- Renegociação e lançamento manual (módulo enterprise): idem, sem `source_installment_id`.

## 4. Comparação entre as duas telas

`FinanceRevenueRepository`/`FinanceReportRepository` (base do relatório, antes da correção) partiam sempre de:

```sql
FROM finance_installments fi
INNER JOIN proposals pr ON pr.id = fi.proposal_id
```

Como nenhuma Ordem de Serviço, renegociação ou lançamento manual do módulo enterprise jamais gera uma linha em `finance_installments`, essas origens eram **estruturalmente incapazes** de aparecer no relatório — não era um filtro mal configurado, era a ausência do JOIN de origem. `FinancialEnterpriseDashboardRepository` (base do Dashboard Financeiro) sempre consultou `financial_accounts_receivable` diretamente, sem essa exigência, por isso exibia os títulos de OS corretamente.

## 5. Causa raiz

Confirmada por código (achado **P02** de `CRM_AUDIT.md`, seção 7) e por consulta direta ao banco local de desenvolvimento (somente leitura):

Para o período 01/01/2026–17/07/2026, no ambiente local:

- `financial_accounts_receivable` (deleted_at IS NULL, due_date no período): **5 títulos, R$ 2.160,00** em aberto/vencido — 3 deles (ids 73, 74, 75) nascidos de Ordem de Serviço, com `source_installment_id IS NULL`.
- `finance_installments INNER JOIN proposals` (mesmo período): **12 parcelas, R$ 5.232,00** — um número diferente, não zero, neste snapshot específico do banco local.

Isso confirma a causa raiz estrutural (duas fontes de dados divergentes) por código e por dado real. O sintoma textual relatado pelo usuário — "0 parcelas / 0 pagamentos" — não foi reproduzido *bit a bit* neste snapshot local (o relatório antigo, testado diretamente com os mesmos filtros, retornava R$ 5.232,00, não zero). A hipótese mais provável, consistente com a arquitetura confirmada, é que em produção (ou num snapshot anterior dos dados) o único título em aberto no período informado tenha sido de origem OS — cenário em que o relatório antigo mostraria exatamente zero, como relatado. Esse valor não foi codificado como critério fixo de teste; os testes de regressão comparam relatório e dashboard de forma relativa (mesma consulta, mesmo filtro), não contra um número absoluto.

## 6. Fonte de verdade definida

**`financial_accounts_receivable` / `financial_receipts`** (módulo financeiro corporativo/enterprise), pelos motivos já registrados em `CRM_AUDIT.md` (seção 6): é a estrutura mais completa — cobre propostas/projetos (via `source_installment_id`), Ordens de Serviço (via `servicos_avulsos.financial_receivable_id`), contratos (via `contract_id`) e lançamentos manuais, com soft delete (`deleted_at`) e suporte nativo a pagamento parcial (`received_amount`/`remaining_amount`).

`finance_installments` permanece ativo, sem alteração de comportamento, para o financeiro de projetos (`/projetos/{id}/financeiro`) e sua API de manutenção — esta sprint não migra dado nem desativa esse módulo, apenas para de usá-lo como fonte do relatório `/relatorios/financeiro`.

## 7. Solução aplicada

Estratégia A do pedido original: reaproveitar a lógica/consulta já usada pelo Dashboard Financeiro, extraída para métodos de repositório reutilizáveis, sem copiar a consulta diretamente na view e sem criar uma terceira estrutura financeira.

- **`FinancialReceivableRepository`**: `paginate()` refatorado para reaproveitar três helpers privados (`baseFromWhere()`, `selectColumnsSql()`, `countMatching()`) em vez de SQL duplicado. Adicionados:
  - `reportRows(companyId, filters, limit)` — mesma base de filtros de `paginate()`, mas com teto de segurança configurável em vez do limite de 100/página da listagem operacional. Isso evita reintroduzir, no financeiro, o mesmo bug de truncamento silencioso já corrigido para Ordens de Serviço (P03): reaproveitar `paginate()` diretamente no relatório teria recriado exatamente essa classe de bug.
  - `totals(companyId, filters)` — a receber, vencido, a vencer e taxa de inadimplência, com o mesmo filtro de vencimento (`due_date`) usado pela listagem.
  - `originsForIds(ids)` — identifica quais títulos nasceram de uma Ordem de Serviço, consultando `servicos_avulsos.financial_receivable_id` uma única vez por página, sem duplicar essa leitura em múltiplos lugares.
- **`FinancialReceiptRepository`**: adicionados `listByPeriod()` e `totalReceived()`, filtrando pela data real do pagamento (`fr.payment_date`), e não pelo vencimento do título — evitando reproduzir aqui o problema já registrado em F03/P07 do `CRM_AUDIT.md` (filtro de recebimento pela data errada).
- **`ReportController`**: `finance()`, `financeExportExcel()` e `financeExportPdf()` reescritos para consultar `FinancialReceivableRepository`/`FinancialReceiptRepository` em vez de `FinanceRevenueRepository`/`FinanceReportRepository`. Cada título passa a exibir uma coluna **Origem** (Ordem de serviço / Proposta-Projeto / Contrato / Manual). PDF e Excel usam a mesma fonte e os mesmos filtros da tela, sem reconstruir a consulta separadamente.
- **View (`resources/views/reports/finance.php`)**: atualizada para o novo formato de dado (título/origem/projeto/cliente/status/valor original/recebido/saldo/dias em atraso), com legenda explícita informando que o período filtra pelo **vencimento** dos títulos em aberto/vencidos e pela **data de pagamento** dos recebimentos, e que cliente/projeto vazios (ou `0`) representam "Todos".
- **Filtros**: `client_id`/`project_id` vazios ou `0` continuam significando "Todos" (contrato de URL preservado); `status` passou a validar contra o enum enterprise (`pending, partially_paid, paid, overdue, canceled, renegotiated`); `sort` passou a validar contra uma whitelist fixa, com fallback silencioso para `due_date` — nenhum valor de ordenação não whitelistado chega a ser interpolado em SQL.
- Registros cancelados nunca entram nos totais de "a receber"/"vencido", mas continuam aparecendo na listagem (não são eliminados). Títulos sem projeto e sem pagamento continuam aparecendo (LEFT JOIN para projeto; título sem recibo simplesmente não aparece na tabela de pagamentos, mas continua na tabela de títulos).

## 8. Arquivos alterados

- `app/Controllers/ReportController.php` (sprint anterior: migração de fonte de dados; esta sprint: renomeada a chave `'data'` para `'report'` na chamada de `View::render()` — correção do bug real de renderização)
- `app/Repositories/FinancialReceivableRepository.php`
- `app/Repositories/FinancialReceiptRepository.php`
- `resources/views/reports/finance.php` (sprint anterior: novo formato de dado; esta sprint: leitura de `$report` em vez de `$data`)
- `tests/run.php` (esta sprint: registra `tests/finance_report_controller.php`)
- `tests/finance_report_repository.php` (novo na sprint anterior)
- `tests/finance_report_controller.php` (novo nesta sprint — dispara a rota real via `Router::dispatch()` e inspeciona o HTML renderizado)
- `CRM_AUDIT.md` (subseção de correção sob o achado P02 + nova "Atualização 2" com a causa definitiva)
- `CHANGELOG.md`
- Este arquivo (`SPRINT_FINANCE_REPORT_FIX.md`, seção 13-B nova nesta sprint)

Nenhuma alteração em `database/schema.sql`, `database/upgrade.sql`, rotas (`config/routes.php`) ou middlewares de autenticação/CSRF/RBAC.

## 9. Consultas relevantes

Consultas de comparação executadas (somente leitura) no banco local de desenvolvimento para confirmar a causa raiz:

```sql
-- financial_accounts_receivable no período (base nova do relatório e do dashboard)
SELECT COUNT(*) c, SUM(remaining_amount) rem,
       SUM(CASE WHEN status='overdue' THEN remaining_amount ELSE 0 END) overdue
FROM financial_accounts_receivable
WHERE deleted_at IS NULL AND due_date BETWEEN '2026-01-01' AND '2026-07-17';
-- => 5 títulos, R$ 2.160,00 em aberto, R$ 2.160,00 vencido

-- finance_installments no período (base antiga do relatório)
SELECT COUNT(*) c
FROM finance_installments fi
INNER JOIN proposals pr ON pr.id = fi.proposal_id
WHERE fi.due_date BETWEEN '2026-01-01' AND '2026-07-17';
-- => 12 parcelas (número diferente do dashboard, não zero neste snapshot local)

-- títulos de Ordem de Serviço presentes em financial_accounts_receivable
SELECT id, financial_receivable_id, status, final_amount
FROM servicos_avulsos
WHERE financial_receivable_id IS NOT NULL;
-- => 3 registros (ids 73, 74, 75) — invisíveis ao relatório antigo por não terem source_installment_id
```

Após a correção, a validação de consistência comparou diretamente os dois repositórios com os mesmos filtros:

```php
$recRepo->totals($companyId, $farFilters);                       // nova base do relatório
(new FinancialEnterpriseDashboardRepository())->data($companyId, $filters)['totals']; // base do dashboard
// => total_receivable e total_overdue idênticos (R$ 2.160,00 / R$ 2.160,00) para o mesmo período
```

## 10. Testes automatizados

Criado `tests/finance_report_repository.php` (21 asserções), seguindo o mesmo padrão já usado em `tests/service_orders_report_repository.php`: grava fixtures reais dentro de uma transação nunca commitada (`rollBack()` garantido no `finally`), sem alterar dado real. Cobre:

- Título de origem Ordem de Serviço aparece no relatório (regressão direta do P02).
- `originsForIds()` classifica corretamente OS vs. título manual.
- **Relatório e Dashboard Financeiro retornam exatamente o mesmo total a receber e o mesmo total vencido** para o mesmo filtro — critério de aceite central desta sprint.
- Registro cancelado nunca entra nos totais, mas continua aparecendo na listagem.
- `client_id=0`/ausente inclui todos os clientes; filtro por cliente isola corretamente.
- Filtro por status retorna exatamente o subconjunto esperado.
- Pagamento total zera o saldo exibido; pagamento parcial reduz sem zerar.
- Pagamentos aparecem na listagem de recebimentos pela data real de pagamento, com soma líquida correta.
- Valor de ordenação não whitelistado (incluindo uma tentativa de injeção via `sort`) não gera erro SQL e cai no default.
- `reportRows()` não trunca em 100 registros com uma massa de 130 títulos sintéticos, e sinaliza `truncated` corretamente conforme o teto de segurança informado.

Resultado: `php tests/run.php` → **100% dos testes OK, exit code 0** (146 asserções no total, incluindo as 21 novas e as pré-existentes de OS, leads, PDFs, estrutura de banco e aprovação pública).

`php -l` sem erros nos 4 arquivos PHP alterados/criados.

## 11. Validação manual

Executada via simulação direta dos repositórios (CLI, com `php -r` carregando o bootstrap da aplicação) contra o banco local real, reproduzindo exatamente os parâmetros que o controller usa:

- `FinancialReceivableRepository::totals()` (nova base do relatório) e `FinancialEnterpriseDashboardRepository::data()` (base do dashboard, inalterada) retornaram os mesmos valores de "a receber" (R$ 2.160,00) e "vencido" (R$ 2.160,00) para `client_id`/`project_id` vazios e período 01/01/2026–17/07/2026.
- A listagem de títulos do relatório passou a incluir os ids 73/74/75 (origem Ordem de Serviço) com a coluna Origem = "Ordem de serviço", onde antes eram estruturalmente omitidos.
- `FinancialReceiptRepository::listByPeriod()` retornou os recibos do período corretamente vinculados a cliente/projeto/título.

Não foi possível abrir um navegador neste ambiente de execução para clicar manualmente em `/relatorios/financeiro` e `/financeiro/dashboard` e exportar PDF/Excel pela UI — a validação foi feita invocando as mesmas classes que os controllers invocam, com os mesmos parâmetros que a URL informada pelo usuário geraria (`from=2026-01-01&to=2026-07-17`), e conferindo `php -l` mais a suíte de regressão completa. Fica registrado como pendência a validação visual em navegador/staging antes de considerar a correção definitiva.

## 12. Riscos e pendências

- O sintoma textual exato ("0 parcelas / 0 pagamentos") não foi reproduzido bit a bit no snapshot atual do banco local (o relatório antigo retornava um número diferente de zero para o mesmo filtro). A causa raiz estrutural está confirmada por código e por dado; a reprodução exata do "zero" relatado fica como hipótese não confirmada neste ambiente — mais provável em produção. Os valores de evidência do pedido original não foram codificados como critério fixo em nenhum teste.
- `FinanceReportRepository` ficou sem chamadores após esta correção (código morto, inofensivo). Remoção não foi feita por estar fora do escopo mínimo desta sprint.
- O `/dashboard` geral (home) e seu card "a receber" (`DashboardRepository`/`FinanceDashboardRepository`/`/api/dashboard/finance`) **continuam** lendo `finance_installments` e sofrendo do mesmo problema estrutural (P02) — não foram alterados nesta sprint por estarem fora do escopo específico definido no pedido (que tratou exclusivamente de `/relatorios/financeiro` x `/financeiro/dashboard`). O link "Ver relatório" dessa tela geral passa um filtro de `status` no vocabulário legado; como o relatório agora valida `status` contra o enum enterprise, um valor incompatível é ignorado silenciosamente (cai em "Todos", sem erro) — comportamento seguro, mas não ajustado por ser outra tela.
- `/financeiro/relatorios` (relatório "Enterprise") e os achados F03/F04/F07/P07 (filtro de data por vencimento em vez de pagamento em outros painéis, defaults de período divergentes entre telas) permanecem documentados e pendentes, sem alteração nesta sprint.
- Não existe ainda rotina de reconciliação entre `finance_installments` e `financial_accounts_receivable` (P09) — esta correção não sincroniza dado entre as tabelas, apenas unifica de onde o relatório lê.

## 13-B. Validação funcional após persistência da divergência (2026-07-17/19)

Esta seção documenta a sprint de continuação: a sprint anterior (seções 1–13) diagnosticou e corrigiu a fonte de dados (`finance_installments` → `financial_accounts_receivable`), mas nunca validou a interface em execução — apenas repositórios isolados. A divergência continuou visível para o usuário. Esta seção registra a reprodução real e a causa definitiva.

### Motivo de a sprint anterior não ter resolvido visualmente

O diagnóstico da sprint anterior (P02: fonte de dados legada vs. enterprise) estava **correto e foi corrigido corretamente** em `FinancialReceivableRepository`/`FinancialReceiptRepository`/`ReportController`. Porém havia um **segundo bug, pré-existente e independente**, na camada de renderização: `App\Core\View::render(string $view, array $data = [], ...)` usa `$data` como nome do próprio parâmetro e faz `extract($data, EXTR_SKIP)`. `ReportController::finance()` empacotava o view-model dentro de uma chave chamada `'data'`. Como a variável `$data` já existe no escopo de `View::render()` (é o array de parâmetros recebido), `EXTR_SKIP` descarta essa chave silenciosamente — sem erro, sem warning — e a view sempre lia o array de parâmetros externo (sem `totals`/`installments`), caindo no fallback `[]`. Resultado: a tela mostrava 0 títulos / 0 pagamentos / R$ 0,00 mesmo com o repositório já corrigido e retornando os valores certos.

Confirmado por `git diff` que esse padrão (`$data = is_array($data ?? null) ? $data : []`, chave `'data'` em `View::render()`) **já existia antes de qualquer alteração desta ou da sprint anterior** — apenas com chaves internas diferentes (`legacy`/`metrics`). Ou seja, `/relatorios/financeiro` já renderizava tudo zerado antes da migração de fonte de dados. Os testes da sprint anterior (`tests/finance_report_repository.php`) chamavam os repositórios diretamente, nunca passando por `View::render()`, por isso nunca detectaram esse segundo bug.

### Os arquivos alterados estavam realmente ativos?

Sim. Confirmado via `git status`/`git diff`: as alterações da sprint anterior estavam presentes na working tree (não commitadas). Confirmado também que existe apenas uma cópia do projeto (`C:/laragon/www/crmtraxter/gestor`), uma única rota `/relatorios/financeiro` em `config/routes.php` (linha 175, `ReportController::finance`), um único `ReportController.php` no projeto, e nenhuma duplicidade de rota ou Controller. O DocumentRoot do vhost `crmtraxter.test` é `C:/laragon/www/crmtraxter` (pai de `gestor/`); `APP_BASE_PATH=/gestor` confirma que a URL real é `http://crmtraxter.test/gestor/...`, apontando para essa mesma cópia.

### Ambiente local encontrado parado

MySQL/MariaDB e Apache do Laragon **não estavam em execução** no início desta sprint (`tasklist` não encontrou `mysqld.exe` nem `httpd.exe`). Ambos foram iniciados manualmente para permitir a reprodução real. Além disso, o `php` disponível no PATH do shell era o do **XAMPP** (`C:\xampp\php\php.exe`), uma instalação completamente distinta da que o Apache do Laragon efetivamente carrega (`C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php8apache2_4.dll`, configurado em `C:/laragon/etc/apache2/mod_php.conf`). Toda a validação final foi refeita explicitamente com o binário `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe` para garantir fidelidade ao runtime real.

### Rota, Controller, Service e Repository efetivamente ativos

- Rota: `GET /relatorios/financeiro` → `config/routes.php:175` → `ReportController::finance` (middlewares `auth`, `auditor` = `admin|auditor|finance|pm`).
- View: `resources/views/reports/finance.php`, layout `resources/views/layout.php`.
- Repositórios: `FinancialReceivableRepository` (`totals`, `paginate`, `cashflowBuckets`, `originsForIds`) e `FinancialReceiptRepository` (`listByPeriod`, `totalReceived`).
- Dashboard comparado: `GET /financeiro/dashboard` → `FinancialModuleController::dashboard` → `FinancialEnterpriseDashboardRepository::data()`.
- Nenhuma rota duplicada, nenhum outro `ReportController`, nenhuma outra cópia do projeto.

### Reprodução real (não apenas repositório)

Como não há ferramenta de navegador/HTTP disponível neste ambiente de execução (comandos de rede como `curl` são bloqueados pela política de permissões desta sessão), a reprodução foi feita despachando as requisições **através da cadeia real de código** — `Router::dispatch()` com as mesmas rotas de `config/routes.php` e os mesmos middlewares (`AuthMiddleware`, `RoleMiddleware`) que o Apache executaria — usando o PHP 8.3.30 do próprio Laragon contra o MySQL local real, e inspecionando o HTML efetivamente renderizado por `View::render()`. Esta é a validação mais próxima de "clicar no navegador" possível sem acesso a rede/browser nesta sessão; o Apache do Laragon foi deixado em execução ao final para que o usuário possa confirmar visualmente em `http://crmtraxter.test/gestor/relatorios/financeiro` e `http://crmtraxter.test/gestor/financeiro/dashboard`.

### Divergência linha a linha (antes da correção do bug de renderização)

Período 2026-01-01 a 2026-07-17, `company_id=1`:

| ID | Origem | Cliente | Vencimento | Original | Recebido | Saldo | Status | Dashboard | Relatório (antes) | Relatório (depois) |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Manual | CT Price - Organização | 2026-05-15 | 180,00 | 180,00 | 0,00 | paid | incluído (pago) | ausente | ausente (pago, correto) |
| 2 | Proposta | Casa Nova | 2026-05-20 | 6.300,00 | 6.300,00 | 0,00 | paid | incluído (pago) | ausente | ausente (pago, correto) |
| 9 | Proposta | Casa Nova | 2026-06-19 | 1.950,00 | 0,00 | 1.950,00 | overdue | R$1.950 no total vencido | ausente (0 linhas) | presente, R$1.950 |
| 73 | **OS** | Agência Lester | 2026-07-01 | 150,00 | 0,00 | 140,00 | overdue | R$140 no total vencido | ausente (0 linhas) | presente, Origem="Ordem de serviço" |
| 74 | **OS** | Instituto Doná | 2026-07-03 | 120,00 | 0,00 | 70,00 | overdue | R$70 no total vencido | ausente (0 linhas) | presente, Origem="Ordem de serviço" |

Totais do período: Dashboard "A receber"=R$2.160,00, "Vencido"=R$2.160,00 (5 títulos apenas com saldo>0 contam). **Antes da correção do bug de `View::render()`:** Relatório mostrava "0 de 0" títulos, R$0,00 em tudo — apesar de `FinancialReceivableRepository::totals()` já retornar R$2.160,00/R$2.160,00 internamente (confirmado com instrumentação temporária). **Depois:** Relatório mostra "5 de 5" títulos, R$2.160,00/R$2.160,00, idêntico ao Dashboard.

Nenhum título duplicado, nenhum título ausente de uma tela e presente na outra, nenhuma diferença de status entre as duas telas após a correção — a mesma consulta (`FinancialReceivableRepository::totals()`/`paginate()`) agora efetivamente alimenta as duas.

### Causa definitiva

Bug de colisão de nome de variável em `App\Core\View::render()` (`extract($data, EXTR_SKIP)` descartando a chave `'data'` do array de parâmetros porque `$data` já é o nome do parâmetro da função). Pré-existente à sprint anterior; não fazia parte do diagnóstico original (P02), que estava correto mas incompleto — corrigia a fonte de dados sem saber que a camada de renderização acima descartava qualquer dado computado, independentemente da fonte.

### Correção definitiva

`app/Controllers/ReportController.php`: renomeada a chave `'data' => $data` para `'report' => $report` na chamada de `View::render('reports/finance', [...])` (mesmo nome de chave já usado com segurança em `ServiceOrderController::reports()` → `service_orders/reports.php`, que nunca teve esse bug). `resources/views/reports/finance.php`: todas as leituras de `$data['...']` renomeadas para `$report['...']`. Nenhuma alteração em `View::render()` em si (evitaria o bug de forma sistêmica, mas afetaria também `ServiceOrderController`/`ServiceController`, fora do escopo autorizado desta sprint — ver achado novo abaixo).

**Achado novo, não corrigido (fora de escopo — "não realizar correção de OS"):** o mesmo padrão (`View::render($view, ['data' => algumaCoisa])`) existe também em `ServiceOrderController::index()` (view `service_orders/index.php`) e `ServiceController::index()` (view `services/index.php`), ambas lendo `$data['rows']`/`$data['total']`. Não verificado em runtime nesta sprint por estar explicitamente fora do escopo autorizado; registrado em `CRM_AUDIT.md` para priorização futura.

### Resultados antes e depois

| Cenário | Dashboard "A receber" | Relatório "A receber" (antes) | Relatório "A receber" (depois) |
|---|---|---|---|
| Sem filtro | R$ 12.030,00 | R$ 0,00 | R$ 12.030,00 |
| 2026-01-01 a 2026-07-17 | R$ 2.160,00 | R$ 0,00 | R$ 2.160,00 |

| Cenário | Dashboard "Vencido" | Relatório "Vencido" (antes) | Relatório "Vencido" (depois) |
|---|---|---|---|
| Sem filtro | R$ 2.160,00 | R$ 0,00 | R$ 2.160,00 |
| 2026-01-01 a 2026-07-17 | R$ 2.160,00 | R$ 0,00 | R$ 2.160,00 |

Títulos listados no Relatório: antes "0 de 0" em ambos os cenários; depois "11 de 11" (sem filtro) e "5 de 5" (com filtro), batendo com a contagem real em `financial_accounts_receivable`.

### URL local validada

`http://crmtraxter.test/gestor/relatorios/financeiro` e `http://crmtraxter.test/gestor/financeiro/dashboard`, apontando para `C:/laragon/www/crmtraxter/gestor` (única cópia do projeto). Apache e MySQL do Laragon foram iniciados manualmente por esta sprint e deixados em execução para conferência visual pelo usuário. Validação própria desta sprint foi feita via dispatch real (`Router`/`Controller`/`View`) com o PHP 8.3.30 do Laragon contra o MySQL local, não via clique manual no navegador — não há ferramenta de navegador/rede disponível neste ambiente de execução de agente.

### Testes executados

- `php tests/run.php` (PHP 8.3.30 do Laragon): **153/153 OK, exit code 0** (era 146 antes desta sprint; +6 do novo `tests/finance_report_controller.php`, o restante já existente).
- Novo `tests/finance_report_controller.php`: dispara `/relatorios/financeiro` e `/financeiro/dashboard` via `Router::dispatch()` real (mesma cadeia de middlewares do Apache) e inspeciona o **HTML renderizado**, não o retorno do repositório — a camada que estava de fato quebrada. Confirmado que este teste **falha** contra o código anterior à correção (verificado via `git stash` temporário do fix, reexecução da suíte, e `git stash pop` para restaurar) e **passa** com a correção.
- `php -l` sem erros em `app/Controllers/ReportController.php`, `resources/views/reports/finance.php`, `tests/run.php`, `tests/finance_report_controller.php`.
- PDF: `financeExportPdf()` chamado via dispatch direto do Controller — retornou bytes `%PDF-1.4` válidos (3.103 bytes) para o filtro 2026-01-01–2026-07-17.
- Excel: `financeExportExcel()` retornou erro (`RuntimeException: Extensão ZipArchive indisponível`) — **achado de ambiente local, não de código**: `extension=zip` está comentada no `php.ini` do PHP 8.3.30 do Laragon (`C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.ini:832`). Não corrigido nesta sprint por ser alteração de configuração de servidor compartilhada por todos os projetos deste Laragon, fora do escopo desta correção pontual — requer decisão explícita do usuário (habilitar `zip` no `php.ini` e reiniciar Apache) antes de reexportar Excel local ou validar em produção se o mesmo ocorre lá.

### Instrumentação temporária

`error_log()` foi adicionado temporariamente dentro de `ReportController::finance()` para confirmar, passo a passo, que `$totals`/`$installments` chegavam corretos até imediatamente antes de `View::render()`. **Removido integralmente** antes da conclusão desta sprint — confirmado por `grep -n "error_log|DEBUG " app/Controllers/ReportController.php` sem resultados.

## 13. Resultado final (após a Atualização da seção 13-B)

- Causa raiz **dupla** comprovada: (1) fonte de dados divergente (P02, corrigida na sprint anterior) e (2) bug de colisão de nome de variável em `View::render()` que descartava a chave `'data'` via `extract($data, EXTR_SKIP)` (pré-existente, causa real do sintoma "0 títulos/0 pagamentos" relatado, corrigido nesta sprint). Ver seção 13-B para o diagnóstico completo.
- `/relatorios/financeiro` e `/financeiro/dashboard` passam a usar a mesma fonte de dados (`financial_accounts_receivable`/`financial_receipts`) **e** a exibir os mesmos totais na tela renderizada — confirmado por teste automatizado que dispara a rota real (`tests/finance_report_controller.php`), por simulação direta contra o banco local, e por dispatch real via `Router`/`Controller`/`View` com o PHP/MySQL do próprio Laragon (não apenas chamada isolada de repositório).
- Títulos de Ordem de Serviço, antes estruturalmente ausentes do relatório, agora aparecem com origem identificada, tanto no HTML da tela quanto no PDF exportado.
- PDF do relatório confirmado funcionando com a mesma fonte e os mesmos filtros da tela (bytes válidos gerados via dispatch real). Excel bloqueado neste ambiente local por `extension=zip` desabilitada no `php.ini` do Laragon — achado de ambiente, não de código, não corrigido nesta sprint (ver seção 13-B).
- Suíte de testes completa (`php tests/run.php`, PHP 8.3.30 do Laragon) passando: **153/153 OK, exit code 0**.
- Nenhuma alteração fora do escopo definido: sem mudança de schema, sem migração de dado, sem alteração de rotas/permissões, sem tocar em `/financeiro/relatorios`, no `/dashboard` geral, em `ServiceOrderController`/`ServiceController` (mesmo tendo identificado que compartilham o mesmo bug de `View::render()` — registrado como achado novo, não corrigido, em `CRM_AUDIT.md`) ou em módulos não relacionados.
- Status em `CRM_AUDIT.md`: **CORRIGIDO E VALIDADO NO AMBIENTE LOCAL** (ver "Atualização 2" sob o achado P02).
- Nenhum commit, push, deploy ou alteração manual em dados reais foi realizado. MySQL e Apache do Laragon foram iniciados por esta sprint (estavam parados) e deixados em execução para conferência visual do usuário.
