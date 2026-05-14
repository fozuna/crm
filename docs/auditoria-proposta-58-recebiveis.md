# Auditoria da Proposta 58 e Correcao Global de Recebiveis

## Diagnostico

- Proposta auditada: `58`
- Projeto convertido: `55`
- Cliente: `Casa Nova`
- Valor total: `R$ 18.000,00`
- Regra comercial validada no `payment_snapshot`:
  - entrada de `R$ 6.300,00` em `2026-05-20`
  - 6 parcelas mensais de `R$ 1.950,00`
  - vencimentos de `2026-06-19` ate `2026-11-16`

### Itens auditados

- `proposal_items`: 1 item
- Descricao: `Desenvolvimento completo do modulo`
- Quantidade: `1`
- Valor unitario: `R$ 18.000,00`
- Desconto: `R$ 0,00`
- Total consolidado: `R$ 18.000,00`

### Resultado da auditoria

- O calculo comercial da proposta estava correto.
- O cronograma calculado pelo `PaymentPlanCalculator` estava correto.
- As parcelas legadas em `finance_installments` estavam corretas.
- O erro ocorria apenas na sincronizacao para `financial_accounts_receivable`.

## Causa Raiz

O servico `FinancialReceivableService` recebia cada parcela ja materializada do legado com `source_installment_id`, mas ainda assim reaplicava o parcelamento em `expandSchedule()`.

Efeito pratico:

- 7 parcelas legadas corretas viravam 49 recebiveis enterprise incorretos.
- A entrada de `R$ 6.300,00` era quebrada novamente em subtitulos indevidos.
- As parcelas mensais de `R$ 1.950,00` eram redivididas em valores como `R$ 278,57`.
- Os titulos ficavam com nomes duplicados, por exemplo `Parcela 1/7 - Parcela 2/7`.
- Os vencimentos eram empurrados indevidamente para meses futuros, chegando a `2027`.

## Solucao Implementada

### Backend

- `FinancialReceivableService::generateFromProject()` passou a delegar para uma sincronizacao especifica do legado.
- `expandSchedule()` agora preserva recebiveis unitarios quando o payload possui `source_installment_id`.
- Foi criado o fluxo `repairProjectReceivables()` para reparar projetos ja corrompidos.
- Foi criada logica para:
  - escolher um recebivel canonico por parcela legada
  - atualizar o canonico com os dados corretos
  - excluir logicamente duplicados seguros
  - preservar rastreabilidade por auditoria

### Repositorio

- `FinancialReceivableRepository` ganhou `listBySourceInstallment()`.
- O `update()` foi corrigido para persistir `source_installment_id`.
- O bind do repositorio foi ajustado para nao enviar `created_by` em `UPDATE`.

### Reparo de base

- Script operacional: `tools/repair_legacy_receivables.php`
- Resultado executado na base:
  - `updated: 7`
  - `deleted: 42`
  - `skipped: 0`

## Validacao Final

### Proposta 58

Estado final auditado via `tools/diag_proposal_58_summary.php`:

- `finance_installments`: `7` registros corretos
- `financial_accounts_receivable` ativos: `7`
- `financial_accounts_receivable` excluidos logicamente: `42`
- `enterprise_reports.receivables_count`: `7`
- `legacy_reports.installments_count`: `7`
- total em aberto validado: `R$ 18.000,00`

### Fluxo financeiro validado

- Entrada:
  - Parcela `1/7`
  - Valor `R$ 6.300,00`
  - Vencimento `2026-05-20`
- Parcelas mensais:
  - Parcelas `2/7` a `7/7`
  - Valor `R$ 1.950,00` cada
  - Vencimentos mensais corretos

### Relatorios

- Relatorio enterprise passou a retornar os 7 recebiveis ativos corretos.
- Fluxo de caixa projetado passou a refletir:
  - `2026-05`: `R$ 6.300,00`
  - `2026-06` a `2026-11`: `R$ 1.950,00` por mes
- Relatorio legado permaneceu consistente com 7 parcelas e total aberto de `R$ 18.000,00`.

## Escopo Global da Correcao

- A causa raiz foi corrigida no codigo, impedindo novas duplicacoes em propostas futuras.
- O script de reparo foi executado para localizar projetos historicos afetados.
- No estado atual da base auditada, nao restaram projetos com duplicidade ativa por `source_installment_id`.

## Testes Executados

- `php tools/financial_receivable_legacy_repair_test.php` -> `OK`
- `php tools/financial_receivable_service_nested_tx_test.php` -> `OK`
- `php tools/repair_legacy_receivables.php` -> `[]` apos o reparo, indicando ausencia de novos alvos
- `php tools/diag_proposal_58_summary.php` -> `OK`

## Impactos

- Corrige a exibicao de entrada e parcelas no modulo de contas a receber.
- Corrige a base usada pelos relatorios financeiros enterprise.
- Mantem compatibilidade com o financeiro legado.
- Preserva auditoria dos registros removidos via `deleted_at`, sem perda de historico.

## Riscos Possiveis

- Os 42 registros incorretos permanecem na base apenas como historico logico; consultas sem filtro de `deleted_at` podem voltar a mostrar dados obsoletos.
- Scripts de diagnostico antigos que ignorarem soft delete podem parecer inconsistentes.
- Qualquer rotina externa que leia diretamente `financial_accounts_receivable` deve sempre considerar `deleted_at IS NULL`.
