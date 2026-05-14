# Relatório financeiro — filtro padrão do mês atual

## Objetivo
Garantir que, ao abrir `/relatorios/financeiro`, o usuário visualize imediatamente as parcelas do **mês vigente**, sem depender de interação manual.

## Regras
1) Quando `from` e `to` não forem informados, o sistema aplica automaticamente:
   - `from = primeiro dia do mês atual`
   - `to = último dia do mês atual`
2) Não há projeção automática para meses futuros. A listagem respeita estritamente `from/to`.
3) O filtro de status `atrasado` é calculado por regra:
   - `due_date < hoje` e `status in (pendente, reaberto)`

## Onde foi implementado
- Normalização e aplicação do range padrão: [FinanceRevenueRepository.php](file:///c:/laragon/www/crmtraxter/gestor/app/Repositories/FinanceRevenueRepository.php)
- Tela do relatório (pré-preenche datas do mês atual e remove campo de projeção): [finance.php](file:///c:/laragon/www/crmtraxter/gestor/resources/views/reports/finance.php)

## Testes
- Comportamento padrão (mês atual), exclusão de futuros e respeito a filtros manuais:
  - [finance_default_month_behavior_test.php](file:///c:/laragon/www/crmtraxter/gestor/tools/finance_default_month_behavior_test.php)
