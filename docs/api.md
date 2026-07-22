# API e Interface HTTP

## Modelo de acesso

O projeto expõe dois tipos de interface HTTP:

- rotas web server-side, que retornam HTML, redirects ou arquivos;
- rotas `/api/*`, que retornam JSON para consumo interno do frontend.

No estado atual:

- não há API pública geral com JWT ou bearer token;
- as APIs internas dependem de sessão autenticada;
- mutações exigem CSRF por campo `_csrf` ou header `X-CSRF-Token`;
- a exceção principal de acesso público é o fluxo de aprovação externa de ordem de serviço.

## Middleware e autorização

### Middleware base

- `auth`: exige sessão;
- `admin`: exige admin;
- `csrf`: exige token válido em mutações.

### Regras de papel

- `pm`: `admin` ou `pm`;
- `finance`: `admin` ou `finance`;
- `auditor`: `admin`, `auditor`, `finance` ou `pm`;
- `financeView`: `admin`, `finance` ou `auditor`.

## Endpoints públicos

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/install` | público | Exibe instalador |
| POST | `/install` | público + CSRF | Executa instalação inicial |
| GET | `/login` | público | Exibe landing page/login |
| POST | `/login` | público + CSRF | Autentica usuário |
| GET | `/os/aprovacao/{publicId}` | público | Exibe aprovação pública de OS |
| POST | `/os/aprovacao/{publicId}` | público | Registra decisão pública da OS |
| GET | `/empresa/ativo/{asset}` | público | Serve ativo público do perfil empresarial |

## Endpoints web autenticados

### Sessão e navegação

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| POST | `/logout` | auth + CSRF | Encerra sessão |
| GET | `/` | auth | Dashboard |
| GET | `/dashboard` | auth | Dashboard |
| GET | `/manual` | auth | Manual do sistema |

### Branding e empresa

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/branding` | auth | Edita branding legado |
| POST | `/branding` | auth + CSRF | Atualiza branding legado |
| GET | `/empresa` | auth + admin | Edita perfil empresarial |
| POST | `/empresa` | auth + admin + CSRF | Atualiza perfil empresarial |
| GET | `/empresa/auditoria` | auth + admin | Auditoria do perfil empresarial |
| GET | `/empresa/logo/{variant}` | auth | Retorna logo protegida |

### Serviços

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/servicos` | auth + pm | Lista serviços |
| GET | `/servicos/novo` | auth + pm | Formulário de serviço |
| POST | `/servicos` | auth + pm + CSRF | Cria serviço |
| GET | `/servicos/{id}/editar` | auth + pm | Edita serviço |
| POST | `/servicos/{id}` | auth + pm + CSRF | Atualiza serviço |

### Ordens de serviço

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/ordens-servico` | auth + auditor | Lista OS |
| GET | `/ordens-servico/relatorios` | auth + auditor | Relatórios de OS |
| GET | `/ordens-servico/nova` | auth + pm | Formulário de nova OS |
| POST | `/ordens-servico` | auth + pm + CSRF | Cria OS |
| GET | `/ordens-servico/{id}` | auth + auditor | Exibe OS |
| GET | `/ordens-servico/{id}/editar` | auth + auditor | Edita OS |
| POST | `/ordens-servico/{id}` | auth + pm + CSRF | Atualiza OS |
| POST | `/ordens-servico/{id}/status` | auth + pm + CSRF | Atualiza status |
| POST | `/ordens-servico/{id}/cancelar` | auth + pm + CSRF | Cancela OS |
| POST | `/ordens-servico/{id}/excluir` | auth + pm + CSRF | Exclui OS |
| POST | `/ordens-servico/{id}/aprovacao/gerar` | auth + pm + CSRF | Gera link de aprovação |
| GET | `/ordens-servico/{id}/aprovacao/comprovante` | auth + auditor | Baixa comprovante da aprovação |
| GET | `/ordens-servico/{id}/pdf` | auth + auditor | PDF da OS |
| GET | `/ordens-servico/{id}/anexos/{attachmentId}` | auth + auditor | Download de anexo |
| POST | `/ordens-servico/{id}/anexos/{attachmentId}/excluir` | auth + pm + CSRF | Exclui anexo |

### Manutenção de banco

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/maintenance/db-upgrade/check` | auth + admin | Inspeciona necessidade de upgrade |
| POST | `/maintenance/db-upgrade/start` | auth + admin + CSRF | Inicia job de upgrade |
| GET | `/maintenance/db-upgrade/status/{jobId}` | auth + admin | Consulta status do upgrade |
| GET | `/maintenance/db-reset/plan` | auth + admin | Exibe plano de reset |
| POST | `/maintenance/db-reset/start` | auth + admin + CSRF | Inicia reset controlado |
| GET | `/maintenance/db-reset/status/{jobId}` | auth + admin | Consulta status do reset |

### Pagamentos, clientes e leads

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/pagamentos` | auth | Lista métodos de pagamento |
| GET | `/pagamentos/novo` | auth | Formulário de método de pagamento |
| POST | `/pagamentos` | auth + CSRF | Cria método de pagamento |
| GET | `/pagamentos/{id}/editar` | auth | Edita método de pagamento |
| POST | `/pagamentos/{id}` | auth + CSRF | Atualiza método de pagamento |
| POST | `/pagamentos/{id}/excluir` | auth + CSRF | Exclui método de pagamento |
| GET | `/clientes` | auth | Lista clientes |
| GET | `/clientes/novo` | auth | Formulário de cliente |
| POST | `/clientes` | auth + CSRF | Cria cliente |
| GET | `/clientes/{id}` | auth | Exibe cliente |
| GET | `/clientes/{id}/logo` | auth | Retorna logo do cliente |
| GET | `/clientes/{id}/editar` | auth | Edita cliente |
| POST | `/clientes/{id}` | auth + CSRF | Atualiza cliente |
| POST | `/clientes/{id}/excluir` | auth + CSRF | Exclui cliente |
| POST | `/clientes/{id}/interacoes` | auth + CSRF | Adiciona interação ao cliente |
| GET | `/leads` | auth + pm | Kanban/listagem de leads |
| GET | `/leads/novo` | auth + pm | Formulário de lead |
| POST | `/leads` | auth + pm + CSRF | Cria lead |
| GET | `/leads/{id}/editar` | auth + pm | Edita lead |
| POST | `/leads/{id}` | auth + pm + CSRF | Atualiza lead |
| POST | `/leads/{id}/interacoes` | auth + pm + CSRF | Adiciona interação ao lead |

### Propostas e contratos

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/propostas` | auth | Lista propostas |
| GET | `/propostas/nova` | auth | Formulário de proposta |
| POST | `/propostas` | auth + CSRF | Cria proposta |
| GET | `/propostas/{id}` | auth | Exibe proposta |
| GET | `/propostas/{id}/editar` | auth | Edita proposta |
| POST | `/propostas/{id}` | auth + CSRF | Atualiza proposta |
| POST | `/propostas/{id}/status` | auth + CSRF | Atualiza status da proposta |
| POST | `/propostas/{id}/converter` | auth + CSRF | Converte proposta em projeto |
| GET | `/propostas/{id}/preview` | auth | Preview da proposta |
| POST | `/propostas/{id}/pdf` | auth + CSRF | Gera PDF da proposta |
| GET | `/propostas/{id}/docs/{docId}` | auth | Download de documento da proposta |
| GET | `/propostas/{id}/pdf` | auth | Download/stream do PDF da proposta |
| POST | `/propostas/{proposalId}/contrato/gerar` | auth + pm + CSRF | Gera contrato da proposta |
| GET | `/contratos` | auth + auditor | Lista contratos |
| GET | `/contratos/relatorios` | auth + auditor | Relatórios de contratos |
| GET | `/contratos/templates` | auth + pm | Lista/edita templates |
| POST | `/contratos/templates/{id}` | auth + pm + CSRF | Atualiza template |
| GET | `/contratos/{id}` | auth + auditor | Exibe contrato |
| GET | `/contratos/{id}/imprimir` | auth + auditor | Visualização imprimível |
| GET | `/contratos/{id}/pdf` | auth + auditor | PDF do contrato |
| GET | `/contratos/{id}/versoes/{versionId}/pdf` | auth + auditor | PDF de versão do contrato |
| POST | `/contratos/{id}/assinar` | auth + pm + CSRF | Marca contrato como assinado |
| POST | `/contratos/{id}/vigencia` | auth + pm + CSRF | Marca vigência |
| POST | `/contratos/{id}/enviar-assinatura` | auth + pm + CSRF | Envia para assinatura |

### Projetos, auditoria e relatórios

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/projetos` | auth + auditor | Lista projetos |
| GET | `/projetos/{id}` | auth + auditor | Exibe projeto |
| GET | `/projetos/{id}/editar` | auth + pm | Edita projeto |
| POST | `/projetos/{id}` | auth + pm + CSRF | Atualiza projeto |
| POST | `/projetos/{id}/tarefas` | auth + pm + CSRF | Cria tarefa |
| POST | `/projetos/{id}/tarefas/{taskId}` | auth + pm + CSRF | Atualiza tarefa |
| POST | `/projetos/{id}/tarefas/{taskId}/excluir` | auth + pm + CSRF | Exclui tarefa |
| POST | `/projetos/{id}/marcos` | auth + pm + CSRF | Cria marco |
| POST | `/projetos/{id}/marcos/{milestoneId}/excluir` | auth + pm + CSRF | Exclui marco |
| GET | `/projetos/{id}/financeiro` | auth + finance | Financeiro do projeto |
| POST | `/projetos/{id}/financeiro/{installmentId}/pagar` | auth + finance + CSRF | Paga parcela |
| POST | `/projetos/{id}/financeiro/{installmentId}/cancelar` | auth + finance + CSRF | Solicita/efetiva cancelamento |
| POST | `/projetos/{id}/financeiro/{installmentId}/reabrir` | auth + finance + CSRF | Reabre parcela |
| POST | `/projetos/{id}/financeiro/cancelamentos/{requestId}/aprovar` | auth + admin + CSRF | Aprova cancelamento |
| POST | `/projetos/{id}/financeiro/cancelamentos/{requestId}/rejeitar` | auth + admin + CSRF | Rejeita cancelamento |
| GET | `/auditoria` | auth + auditor | Tela de auditoria |
| GET | `/relatorios/financeiro` | auth + auditor | Relatório financeiro |
| GET | `/relatorios/financeiro/export/pdf` | auth + auditor | Exporta relatório em PDF |
| GET | `/relatorios/financeiro/export/excel` | auth + auditor | Exporta relatório em Excel |

### Financeiro corporativo

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/financeiro/dashboard` | auth + financeView | Dashboard financeiro |
| GET | `/financeiro/recebiveis` | auth + financeView | Lista contas a receber |
| GET | `/financeiro/recebiveis/novo` | auth + finance | Formulário de conta a receber |
| POST | `/financeiro/recebiveis` | auth + finance + CSRF | Cria conta a receber |
| GET | `/financeiro/recebiveis/{id}` | auth + financeView | Exibe conta a receber |
| GET | `/financeiro/recebiveis/{id}/editar` | auth + finance | Edita conta a receber |
| POST | `/financeiro/recebiveis/{id}` | auth + finance + CSRF | Atualiza conta a receber |
| POST | `/financeiro/recebiveis/{id}/excluir` | auth + finance + CSRF | Exclui conta a receber |
| POST | `/financeiro/recebiveis/{id}/duplicar` | auth + finance + CSRF | Duplica conta a receber |
| POST | `/financeiro/recebiveis/{id}/renegociar` | auth + finance + CSRF | Renegocia conta a receber |
| POST | `/financeiro/recebiveis/{id}/baixa` | auth + finance + CSRF | Registra baixa |
| POST | `/financeiro/recebiveis/{id}/estornar` | auth + finance + CSRF | Estorna baixa |
| GET | `/financeiro/recebiveis/{id}/imprimir` | auth + financeView | Visualização imprimível |
| GET | `/financeiro/recebiveis/{id}/recibos/{receiptId}/preview` | auth + financeView | Preview de recibo |
| GET | `/financeiro/recebiveis/{id}/recibos/{receiptId}/pdf` | auth + financeView | PDF de recibo |
| GET | `/financeiro/recebiveis/{id}/pdf` | auth + financeView | PDF da conta a receber |
| GET | `/financeiro/recebiveis/{id}/comprovantes/{receiptId}` | auth + financeView | Download de comprovante |
| GET | `/financeiro/relatorios` | auth + financeView | Relatórios financeiros |
| GET | `/financeiro/relatorios/export/csv` | auth + financeView | Exporta CSV |
| GET | `/financeiro/relatorios/export/excel` | auth + financeView | Exporta Excel |
| GET | `/financeiro/relatorios/export/pdf` | auth + financeView | Exporta PDF |

## APIs JSON internas

### Company profile

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/company-profile` | auth + admin | Consulta perfil empresarial |
| PUT | `/api/company-profile` | auth + admin + CSRF | Cria/atualiza perfil empresarial |
| DELETE | `/api/company-profile` | auth + admin + CSRF | Remove perfil empresarial |
| GET | `/api/company-profile/audit` | auth + admin | Consulta auditoria do perfil |
| POST | `/api/company-profile/logo` | auth + admin + CSRF | Faz upload de logo |

### Projetos

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/projects` | auth + auditor | Lista projetos em JSON |
| GET | `/api/projects/{id}` | auth + auditor | Detalha projeto |
| POST | `/api/proposals/{proposalId}/convert` | auth + pm + CSRF | Converte proposta em projeto |
| POST | `/api/projects/{id}/recalc` | auth + pm + CSRF | Recalcula progresso |
| POST | `/api/projects/{id}/tasks/{taskId}/status` | auth + pm + CSRF | Atualiza status de tarefa |

### Leads

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/leads/kanban` | auth + pm | Dados do kanban de leads |
| GET | `/api/leads/{id}` | auth + pm | Detalhe do lead |
| POST | `/api/leads/{id}/stage` | auth + pm + CSRF | Move estágio do lead |
| POST | `/api/leads/{id}/convert` | auth + pm + CSRF | Converte lead em cliente |

### Financeiro de projetos

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/projects/{id}/installments` | auth + finance | Lista parcelas do projeto |
| GET | `/api/installments/{id}/payments` | auth + finance | Lista pagamentos da parcela |
| POST | `/api/installments/{id}/payments` | auth + finance + CSRF | Adiciona pagamento |
| POST | `/api/installments/{id}/cancel` | auth + finance + CSRF | Cancela parcela |
| POST | `/api/installments/{id}/reopen` | auth + finance + CSRF | Reabre parcela |

### Receitas e dashboards

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/finance/revenues/metrics` | auth + financeView | Métricas de receita |
| GET | `/api/finance/revenues/cashflow` | auth + financeView | Fluxo de caixa |
| GET | `/api/finance/revenues/installments` | auth + financeView | Parcelas analíticas |
| GET | `/api/finance/revenues/payments` | auth + financeView | Pagamentos analíticos |
| GET | `/api/dashboard/finance` | auth + financeView | Dashboard financeiro resumido |

### Financeiro corporativo

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/financial/receivables` | auth + financeView | Lista contas a receber |
| GET | `/api/financial/clients/{clientId}/approved-projects` | auth + financeView | Projetos aprovados por cliente |
| GET | `/api/financial/receivables/{id}` | auth + financeView | Detalha conta a receber |
| POST | `/api/financial/receivables` | auth + finance + CSRF | Cria conta a receber |
| PUT | `/api/financial/receivables/{id}` | auth + finance + CSRF | Atualiza conta a receber |
| DELETE | `/api/financial/receivables/{id}` | auth + finance + CSRF | Exclui conta a receber |
| POST | `/api/financial/receivables/{id}/receipt` | auth + finance + CSRF | Registra recebimento |
| POST | `/api/financial/receivables/{id}/reverse` | auth + finance + CSRF | Estorna recebimento |
| GET | `/api/financial/dashboard` | auth + financeView | Dashboard financeiro |
| GET | `/api/financial/reports` | auth + financeView | Dados de relatórios financeiros |

### Manutenção financeira

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/finance/installments/{id}` | auth + financeView | Detalha parcela |
| POST | `/api/finance/installments/{id}/update` | auth + financeView + CSRF | Atualiza parcela |
| POST | `/api/finance/installments/{id}/delete` | auth + financeView + CSRF | Exclui parcela |
| POST | `/api/finance/installments/{id}/pay` | auth + financeView + CSRF | Paga parcela |
| POST | `/api/finance/installments/{id}/advance` | auth + financeView + CSRF | Adianta parcela |

### Auditoria e serviços

| Método | Rota | Acesso | Finalidade |
| --- | --- | --- | --- |
| GET | `/api/audit` | auth + auditor | Lista auditoria em JSON |
| GET | `/api/services` | auth + pm | Lista serviços em JSON |
| GET | `/api/services/{id}` | auth + pm | Detalha serviço em JSON |

## Formato de resposta

Padrão observado nas APIs:

- sucesso:
  - `{"ok": true, ...}`
- erro de validação/regra:
  - `{"ok": false, "error": "..."}`
- alguns endpoints usam `message` em vez de `error`, principalmente manutenção.

## Códigos de status observados

- `200` sucesso;
- `201` criação;
- `400` requisição inválida;
- `401` não autenticado;
- `403` sem permissão;
- `404` não encontrado;
- `409` conflito;
- `419` CSRF inválido;
- `422` erro de validação/regra;
- `500` erro interno.
