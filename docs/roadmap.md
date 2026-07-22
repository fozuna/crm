# Roadmap Técnico Gerado da Análise

## Critério

Este roadmap não representa promessa de produto. Ele foi gerado a partir de lacunas e oportunidades observadas no código atual, sem alterar o comportamento do sistema.

## Alta prioridade

- ampliar a inspeção estrutural do banco para validar FKs, índices, triggers e enums além do conjunto mínimo atual;
- alinhar integralmente `database/schema.sql`, `database/upgrade.sql` e expectativas do código, especialmente em tabelas e enums sensíveis;
- reforçar documentação operacional de rollback e recuperação de banco;
- consolidar tratamento de erro e observabilidade em fluxos de manutenção e deploy;
- revisar referências lógicas sem FK formal em entidades críticas.

## Média prioridade

- formalizar catálogo completo de endpoints e payloads para consumo interno do frontend;
- ampliar cobertura da suíte de testes para contratos, projetos, financeiro corporativo e company profile;
- padronizar ainda mais mensagens de erro JSON entre módulos;
- reduzir duplicidade conceitual entre branding legado e company profile;
- documentar fluxos de e-mail e dependências reais de entrega.

## Baixa prioridade

- criar índice mestre navegável para documentação complementar legada em `docs/`;
- padronizar nomenclatura de alguns arquivos de documentação histórica;
- revisar organização de artefatos temporários e diagnósticos fora do fluxo principal;
- adicionar screenshots versionados ou guia visual se isso se tornar útil para onboarding.
