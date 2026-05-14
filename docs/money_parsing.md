# Parsing monetário (pt-BR)

## Problema
Entradas como `1.500,50` estavam sendo convertidas com `str_replace(',', '.', ...)`, virando `1.500.50` e resultando em `1.5` (baixa incorreta de valores).

## Solução
Foi centralizada a conversão em `Money::parseBRL()`:
- Suporta `R$ 1.500,50`, `1.500,50`, `1.000.000,00`, `1500,50`.
- Trata `.` como separador de milhar quando o padrão é `\d{1,3}(\.\d{3})+`.
- Mantém compatibilidade com valores no estilo `1.50` (decimal com ponto).

## Pontos corrigidos
- Pagamento de parcela via UI (`/projetos/{id}/financeiro`): [FinanceController.php](file:///c:/laragon/www/crmtraxter/gestor/app/Controllers/FinanceController.php)
- Pagamento de parcela via API (`POST /api/installments/{id}/payments`): [FinanceApiController.php](file:///c:/laragon/www/crmtraxter/gestor/app/Controllers/FinanceApiController.php)
- Cadastro/edição de serviços (preço padrão): [ServiceController.php](file:///c:/laragon/www/crmtraxter/gestor/app/Controllers/ServiceController.php)

## Máscara de input
- Inputs com `data-money="brl"` são mascarados no front (digitação vira `pt-BR` com 2 casas): [head.php](file:///c:/laragon/www/crmtraxter/gestor/resources/views/partials/head.php)

## Testes
- Parser unitário: [money_parse_test.php](file:///c:/laragon/www/crmtraxter/gestor/tools/money_parse_test.php)
- Integração pagamento (salva 1500.50 no banco): [payment_amount_integration_test.php](file:///c:/laragon/www/crmtraxter/gestor/tools/payment_amount_integration_test.php)
