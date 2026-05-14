# Cadastro de Clientes

## Regras de negocio

### Campos basicos

- `Nome` e obrigatorio.
- `E-mail` continua opcional, mas quando informado precisa ser valido.
- `Status` aceita apenas `lead` ou `ativo`.

### Contrato de hospedagem

- O bloco e opcional.
- Quando o checkbox `Cliente possui contrato de hospedagem` estiver desmarcado:
  - nenhum campo adicional e obrigatorio
  - valor, vencimento e prazo podem permanecer vazios
- Quando o checkbox estiver marcado:
  - `Valor do contrato de hospedagem` passa a ser obrigatorio
  - `Data de vencimento da hospedagem` passa a ser obrigatoria
  - `Prazo de renovacao (dias)` passa a ser obrigatorio
- O prazo de renovacao:
  - possui default `45`
  - aceita apenas valores entre `1` e `45`
  - acima de `45` bloqueia o salvamento
- A data de vencimento da hospedagem nao pode estar no passado.
- A data sugerida de renovacao e calculada como:
  - `data de vencimento + prazo de renovacao`

### Registro de dominio

- O bloco e opcional.
- Quando o checkbox `Gerenciamos o registro de dominio do cliente` estiver desmarcado:
  - nenhum campo adicional e obrigatorio
- Quando o checkbox estiver marcado:
  - `Data de vencimento do dominio` passa a ser obrigatoria
  - `Valor do registro/renovacao de dominio` passa a ser obrigatorio
- A data de vencimento do dominio nao pode estar no passado.

## Front-end

- Os campos monetarios usam mascara progressiva no padrao brasileiro com `data-money="brl"`.
- Os grupos de hospedagem e dominio sao habilitados em tempo real no formulario.
- A sugestao de renovacao da hospedagem e recalculada imediatamente quando o usuario altera o vencimento ou o prazo.
- O layout dos novos campos usa grid responsivo para mobile e desktop.

## Persistencia

Novos campos adicionados na tabela `clients`:

- `has_hosting_contract`
- `hosting_contract_amount`
- `hosting_due_date`
- `hosting_renewal_days`
- `manages_domain`
- `domain_due_date`
- `domain_amount`

## Testes

- `php tools/client_validator_test.php`

Esse teste cobre:

- obrigatoriedade condicional
- datas passadas
- limite de prazo de renovacao
- normalizacao monetaria
- normalizacao de datas
