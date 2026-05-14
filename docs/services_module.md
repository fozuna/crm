# Módulo de Serviços (Catálogo)

## Visão geral
O catálogo de serviços permite cadastrar e gerenciar serviços reutilizáveis para serem utilizados em propostas comerciais.
Ele suporta serviços normais e serviços marcados como **bônus**.

## Estrutura de dados
Tabela: `services`
- `name` (obrigatório, máx. 100)
- `default_price` (obrigatório, 2 casas)
- `active` (1/0)
- `description` (obrigatório, mínimo 50 caracteres)
- `is_bonus` (1/0)

Itens de proposta: `proposal_items`
- `service_id` (referência opcional ao catálogo)
- `is_bonus` (snapshot no item)
- `catalog_price` (snapshot do preço padrão do catálogo)
- `unit_price` e `total` continuam armazenando o valor real do item

## Regras de negócio
1) Serviços inativos não aparecem para seleção em novas propostas.
2) Serviços marcados como bônus:
   - O valor real aparece no item (unitário e total da linha).
   - O valor do bônus **não entra** no cálculo de `proposals.subtotal` e, portanto, não impacta `discount_amount` e `total`.
3) Nomes duplicados são bloqueados por validação e por índice único `uq_services_name`.
4) Listagem suporta pesquisa por nome/descrição, filtros por status/tipo e ordenação.

## UI (Admin/PM)
Rotas:
- `GET /servicos` lista com filtros
- `GET /servicos/novo` criar
- `GET /servicos/{id}/editar` editar

Indicadores:
- Inativo: linha com estilo atenuado.
- Bônus: badge "bônus".

## API (para integração)
### Listagem
`GET /api/services`
Parâmetros:
- `q` (string) pesquisa em nome/descrição
- `status` = `ativo|inativo` (opcional)
- `type` = `bonus|normal` (opcional)
- `sort` = `name_asc|name_desc|updated_desc`
- `page`, `per_page`

### Listagem ativa (para propostas)
`GET /api/services?active=1&include_bonus=1`
Retorna `rows` já ordenado por nome.

### Detalhe
`GET /api/services/{id}`

## Integração com Propostas
- O formulário de propostas permite selecionar um item do catálogo por linha.
- Quando selecionado:
  - A descrição do item é preenchida com a descrição do serviço (limitada a 255 chars).
  - O preço unitário é preenchido com o `default_price`.
  - Se o serviço for bônus, a linha é marcada como bônus e não entra no subtotal.

## Exemplos
Serviço normal:
- Nome: "Site institucional"
- Preço: 2500,00
- Bônus: não

Serviço bônus:
- Nome: "Hospedagem 3 meses"
- Preço: 300,00
- Bônus: sim

## Observação (relatório financeiro)
- O relatório financeiro aplica por padrão o filtro do **mês corrente** quando nenhum período é informado.
