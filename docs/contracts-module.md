# Modulo de Contratos

## Diagnostico

Antes desta implementacao, o CRM possuia:

- aprovacao de propostas
- geracao de PDF de proposta
- conversao de proposta aprovada em projeto

Mas nao havia:

- decisao formal de exigencia de contrato na aprovacao
- templates de contrato personalizaveis
- workflow de contrato
- versionamento proprio de contratos
- relatorio operacional de contratos

## Arquitetura

### Entidades principais

- `contract_templates`
  - template editavel
  - criterios automaticos de elegibilidade
  - modo padrao de formalizacao
- `contracts`
  - vinculo direto com `proposal_id`
  - status do contrato
  - modo de assinatura
  - arquivo PDF atual
  - snapshot da proposta
- `contract_versions`
  - historico de regeneracoes
  - snapshots de template e proposta por versao
- `contract_notifications`
  - fila/log de notificacoes automaticas de assinatura e formalizacao

### Servicos

- `ContractPolicyService`
  - decide se a proposta deve sugerir contrato
  - considera valor, cliente, servico e palavras-chave
- `ContractTemplateEngine`
  - preenche placeholders com dados da proposta aprovada
- `ContractService`
  - orquestra aprovacao, geracao, versionamento, notificacoes e workflow
- `ContractPdfGenerator`
  - gera PDF do contrato com base no corpo renderizado

## Regras de negocio

- Apenas propostas aprovadas podem gerar contrato.
- A aprovacao pode marcar manualmente a geracao do contrato.
- O template ativo tambem pode sugerir contrato automaticamente com base em:
  - valor minimo da proposta
  - clientes especificos
  - servicos especificos
  - palavras-chave dos servicos
- Cada proposta possui no maximo um contrato principal, com multiplas versoes.
- O workflow do contrato segue:
  - `rascunho`
  - `pendente_assinatura`
  - `assinado`
  - `vigente`
- O envio para assinatura cria notificacao automatica.
- Quando nao houver provedor externo configurado, o sistema usa impressao como fallback operacional.

## Placeholders do template

- `{{proposal_id}}`
- `{{proposal_title}}`
- `{{proposal_total}}`
- `{{proposal_terms}}`
- `{{proposal_notes}}`
- `{{delivery_start}}`
- `{{delivery_end}}`
- `{{client_name}}`
- `{{client_company}}`
- `{{client_email}}`
- `{{client_phone}}`
- `{{company_legal_name}}`
- `{{company_trade_name}}`
- `{{company_cnpj}}`
- `{{company_email}}`
- `{{company_website}}`
- `{{services_summary}}`
- `{{payment_schedule}}`
- `{{milestones_summary}}`
- `{{contract_number}}`
- `{{signature_mode}}`
- `{{current_date}}`

## Fluxo operacional

1. A proposta e aprovada.
2. O operador decide se o contrato deve ser gerado.
3. O sistema registra a decisao na proposta.
4. Se aprovado para contrato, o modulo gera:
   - corpo do contrato
   - snapshot da proposta
   - PDF versionado
   - registro principal do contrato
5. O contrato pode ser:
   - enviado para assinatura
   - marcado como assinado
   - promovido para vigente

## Testes

- `php tools/contract_policy_service_test.php`
- `php tools/contract_template_engine_test.php`

## Riscos conhecidos

- A notificacao automatica e registrada internamente; disparo real por e-mail depende de infraestrutura externa futura.
- A assinatura digital usa URL de provedor externo apenas quando `CONTRACT_SIGNATURE_PROVIDER_URL` estiver configurado.
- O PDF do contrato e textual e orientado a formalizacao; customizacoes visuais mais complexas podem exigir evolucao posterior do gerador.
