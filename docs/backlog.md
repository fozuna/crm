# Backlog Técnico Sugerido

## Objetivo

Este backlog lista evoluções coerentes com a arquitetura atual do projeto. São sugestões de continuidade baseadas no código existente, não funcionalidades já implementadas.

## Plataforma

- criar rotina padronizada de limpeza de artefatos temporários e debug;
- centralizar helpers de observabilidade e diagnóstico;
- introduzir health checks operacionais além do preflight.

## Segurança

- adicionar proteção explícita contra força bruta no login;
- revisar padronização de auditoria por domínio;
- ampliar política de validação e sanitização para entradas ricas.

## Banco de dados

- validar automaticamente FKs e triggers no guard estrutural;
- mapear e tratar referências lógicas sem integridade formal;
- formalizar catálogo de seeds idempotentes.

## Comercial

- ampliar documentação e testes de transição lead -> proposta -> contrato;
- revisar consistência de snapshots comerciais e seus pontos de atualização.

## Projetos e operação

- expandir documentação de automações de projeto;
- ampliar rastreabilidade de marcos, tarefas e eventos em relatórios.

## Financeiro

- ampliar cobertura de testes para renegociação, baixa, estorno e relatórios;
- consolidar documentação do relacionamento entre parcelas e contas a receber.

## UI e experiência

- documentar biblioteca visual viva dos componentes `tr-*`;
- padronizar ainda mais comportamento de feedback e loading entre telas.

## Documentação

- manter `CLAUDE.md` e `docs/*.md` como fonte primária de contexto para IA;
- revisar a cada mudança estrutural os arquivos `banco.md`, `api.md`, `deploy.md` e `seguranca.md`.
