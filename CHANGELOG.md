# CHANGELOG

Este changelog foi reconstruído a partir do histórico Git observável no repositório local. O projeto ainda não utiliza versionamento semântico formal no estado atual.

## Pendente de commit (2026-08-14)

- Sprint "Parcelamento de Ordens de Serviço, Gestão de Parcelas e Correção do Fluxo de OS": o faturamento de uma OS deixa de criar automaticamente um lançamento financeiro único a cada `create()`/`update()` (`ServiceOrderService::syncReceivable()` removido) e passa a exigir um fluxo explícito "Definir cobrança" (`GET`/`POST /ordens-servico/{id}/faturar`, novo `App\Services\ServiceOrderBillingService`) com três modelos — pagamento único, parcelado (mensal/quinzenal/semanal/personalizada, com arredondamento absorvido pela última parcela) e personalizado (soma validada contra o valor final da OS). Nova coluna aditiva `financial_accounts_receivable.service_order_id` (um-para-muitos; antes só existia o vínculo escalar `servicos_avulsos.financial_receivable_id`). `ServiceOrderService::updateStatus()` bloqueia a transição direta para `Faturado` de OS faturáveis sem cobrança definida. `FinancialReceivableService::update()` ganhou salvaguarda contra reduzir o valor de um título abaixo do já recebido. Corrige também a causa raiz de `/ordens-servico` sempre mostrar "0 registros" (mesmo bug de colisão de chave `'data'` com `View::render()` já documentado em `CRM_AUDIT.md` para este controller, nunca corrigido até agora) e adiciona indicadores operacionais à tela principal via `ServiceOrderRepository::summary()`. Ver `SPRINT_OS_BILLING_AND_FLOW.md` para o diagnóstico e a validação completos.
- Corrige um bug de agregação em `tests/run.php` encontrado durante a validação da sprint acima: o acumulador `$failures` do próprio `run.php` tinha o mesmo nome da variável que cada arquivo de teste requerido reseta em sua própria primeira linha (escopo compartilhado por `require`), fazendo o código de saída (`exit()`) da suíte poder retornar `0` mesmo com falhas reais em qualquer arquivo que não fosse o último requerido — a falha continuava impressa como `FAIL-`, mas não necessariamente refletida no exit code. Corrigido renomeando o acumulador de `run.php` para `$totalFailures`.
- Corrige regressão real de produção reportada logo após o deploy da sprint acima, confirmada via `storage/logs/app.log`: `/ordens-servico/{id}/editar` quebrava (layout/sidebar/Tailwind ausentes, ícone SVG gigante sem estilo) para qualquer OS faturável que já tivesse um link de aprovação gerado. Causa raiz: `resources/views/service_orders/form.php` tinha DOIS cards "Aprovação digital" duplicados compartilhando a variável `$approvalBadge` com tipos incompatíveis — um array (definido na linha 41, nunca mais lido) e, mais abaixo, reatribuído para string dentro do card "Aprovação digital do cliente" (usado corretamente ali); um segundo card morto, inserido pelo commit `2eacb3e` (2026-07-08) e nunca removido, tentava ler essa mesma variável como array (`$approvalBadge['class']`), lançando `TypeError: Cannot access offset of type string on string` (form.php:427). Como a exceção interrompe o `require` do view file dentro do `ob_start()` de `View::render()`, o `layout.php` nunca chegava a envolver a resposta — daí a ausência total de CSS/sidebar e os ícones `UI::icon()` (sem `width`/`height`, só a classe Tailwind `w-5 h-5`) caindo no tamanho intrínseco gigante do navegador. Bug pré-existente desde 2026-07-08, não introduzido pela sprint de faturamento — só nunca havia sido percebido por exigir uma OS com aprovação já gerada. Corrigido removendo o card duplicado/morto (e as variáveis que só ele usava: `$approvalStatusMap`, `$formatDateTime`, `$canGenerateApproval`, `$approvalActionLabel`), mantendo o único card correto.
- Corrige `ServiceOrderController::show()`, que desde o commit `aa221ea` (2026-07-01) sempre redirecionava para `edit()` — nunca existiu uma tela de detalhes de verdade, então a ação "Visualizar" da listagem sempre abria "Editar". Implementada `resources/views/service_orders/show.php` (cabeçalho com número/serviço/status/ações Editar-PDF-Financeiro-Voltar, informações gerais, seção Financeiro reaproveitando as parcelas da OS, anexos com miniatura para imagens e ícone em tamanho normal para documentos, histórico em timeline), sem alterar nenhuma rota (`/ordens-servico/{id}` já apontava para `show()`) nem o link "Visualizar" da listagem (já apontava para a URL correta). Novo teste `tests/service_order_layout_module.php` dispara as rotas reais via `Router::dispatch()` reproduzindo o cenário exato de produção (OS faturável, recebível vinculado, aprovação gerada) para os dois defeitos.
- Corrige o faturamento parcelado de OS relatado para OS-000002 (`Valor da OS: R$ 1.500,00` exibindo um título de `R$ 120,00` marcado "1/3" com "Parcelas geradas: 1" e saldo `R$ 0,00` sem recebimento). Três causas raiz distintas, confirmadas por `git log -p -S`, nenhuma por suposição: (1) o antigo `ServiceOrderService::receivablePayload()` — já removido nesta sprint — usava `base_amount + surcharge_amount` sem o multiplicador de horas como valor do título, nunca `final_amount` (a fórmula real da OS); o fluxo atual já usa exclusivamente `final_amount`. (2) `FinancialReceivableService::update()` aceitava `installment_number`/`total_installments` de qualquer título sem validar contra a quantidade real de linhas vinculadas — corrigido: esses campos passam a ser ignorados em `update()` para qualquer título com `service_order_id`, só alteráveis pelo próprio fluxo de faturamento/reparcelamento da OS. (3) saldo zerado com recebido zerado era matematicamente consistente (implica `discount_amount >= original_amount`), só ficava confuso por falta da coluna "Desconto" na UI — corrigida a exibição. Nova funcionalidade: `ServiceOrderBillingService::reparcel()` (rota `POST /ordens-servico/{id}/reparcelar`), que permite corrigir/substituir a cobrança de uma OS já faturada — bloqueado com segurança sempre que qualquer título vinculado já tiver recebimento, exigindo baixa/estorno manual primeiro. Novo teste `tests/service_order_reparcel_module.php` (26 verificações) valida explicitamente o cenário obrigatório: OS de R$ 1.500,00 com `base_amount=120` faturada em 3 parcelas mensais gera 3 títulos físicos de R$ 500,00 cada (nunca R$ 120,00), com `total_installments` sempre batendo com a quantidade real de linhas. `php tests/run.php`: 300 OK, 0 FAIL. Ver `SPRINT_OS_BILLING_AND_FLOW.md`, seção 19, e `CRM_AUDIT.md`, achado P17.

- Investiga uma nova denúncia de "hora técnica sendo usada como valor faturável" (OS de R$ 480,00 gerando recebível de R$ 120,00) e confirma, por auditoria independente com consulta somente-leitura real contra o banco de desenvolvimento, que o código atual (a partir dos commits `0ac8bd8`/`2212b4e`) **já usa exclusivamente `final_amount`** — não é uma regressão. Os 3 títulos divergentes encontrados no ambiente local (OS-000002/3/4) são dados legados de julho/2026, vinculados só pelo campo `financial_receivable_id`, sem nenhum recebimento, corrigíveis pela própria função "Reparcelar cobrança" já existente. Adiciona `tests/service_order_billing_value_source_module.php` (14 verificações), incluindo um cenário anti-falso-positivo (OS de R$ 550,00, hora técnica R$ 120,00 — não múltiplo — em 2 parcelas de R$ 275,00) e uma trava estrutural que falha a suíte caso `ServiceOrderBillingService.php` volte a referenciar `base_amount`/`hourly_rate`/`default_price`. Documenta consulta SQL de diagnóstico (somente leitura, validada) para localizar OS com `final_amount` divergente da soma dos recebíveis vinculados, e um plano (não executado) para correção dos registros históricos, distinguindo títulos sem recebimento (corrigíveis via "Reparcelar cobrança") de títulos com recebimento (exigem tratamento manual controlado pelo financeiro). `php tests/run.php`: 314 OK, 0 FAIL. Nenhum dado histórico foi alterado. Ver `SPRINT_OS_BILLING_AND_FLOW.md`, seção 20, e `CRM_AUDIT.md`, achado P17 (atualização).

## Pendente de commit (2026-07-22)

- Corrige dois defeitos visuais reais dos PDFs, encontrados após o redesign acima: (1) `ProfessionalPdf::toJpeg()` não compunha o PNG sobre um fundo antes de converter para JPEG — como JPEG não tem canal alfa, a cor de preenchimento "transparente" dos logos (preta, por convenção do `LogoProcessor`) aparecia como um bloco preto sólido atrás do logo em todo PDF que o incluísse; agora a imagem é composta sobre um fundo branco antes da codificação. (2) A justificação de parágrafos em `ProposalPdfGenerator` (`Descrição do projeto`, `Termos e condições`, `Observações`) nunca era aplicada na prática: a quebra de linha usava contagem de caracteres, que produz linhas bem mais curtas que a largura real disponível, e o gap resultante sempre excedia o limite de word-spacing aceito pela função de justificação, então o texto sempre saía alinhado à esquerda apesar do código de justificação existir. Adiciona `PdfStandardTheme::wrapJustified()` (quebra de linha por largura medida + word-spacing calculado por linha) e aplica em `ProposalPdfGenerator` e `ContractPdfGenerator` (corpo do contrato).
- Redesenha a identidade visual dos PDFs gerados pelo sistema (propostas, contratos, contas a receber, recibos, ordens de serviço e comprovantes de aprovação de OS), com base no padrão tipográfico de `TRAXTER-RH.pdf`: cabeçalho "clean" sem bloco de cor sólida (fio fino + traço de destaque, no lugar da barra colorida de página inteira), título de documento padronizado (rótulo + título + fio de destaque + metadados), títulos de seção com régua na cor de destaque, e cabeçalho de tabela preenchido com a cor primária e texto branco em negrito (substituindo cabeçalhos cinza-claro com borda). Todas as mudanças centralizadas em `App\Services\PdfStandardTheme` (`documentTitleBlock()`, `sectionHeading()`, `tableHeaderRow()`, paleta neutra compartilhada) e aplicadas nos 6 geradores de PDF. Corrige também a lógica de seleção de logo no cabeçalho do PDF (`resolveHeaderLogoPath`), que agora sempre usa o "logo escuro" (fundo claro), já que o cabeçalho deixou de ter fundo colorido. `tests/pdfs_all.php`, que validava esses geradores mas não era executado pela suíte principal, foi corrigido (usava `exit()` em vez de `return`, o que mascararia falhas de testes anteriores) e registrado em `tests/run.php`.
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
