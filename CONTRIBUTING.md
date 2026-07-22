# CONTRIBUTING

## Objetivo

Este projeto prioriza estabilidade operacional, retrocompatibilidade e deploy manual controlado. Qualquer contribuição deve respeitar o estado arquitetural atual do sistema.

## Princípios obrigatórios

- Não alterar comportamento funcional sem necessidade clara.
- Não quebrar a paridade entre `database/schema.sql` e `database/upgrade.sql`.
- Não publicar código sem sincronizar o banco antes.
- Não substituir prepared statements por concatenação SQL.
- Não remover controles de CSRF, sessão, papéis ou auditoria.
- Não introduzir framework, Composer ou build frontend sem decisão arquitetural explícita.

## Fluxo recomendado de trabalho

1. Ler `CLAUDE.md` e a documentação em `docs/`.
2. Identificar o módulo afetado em `app/Controllers`, `app/Services`, `app/Repositories` e `resources/views`.
3. Avaliar impactos em:
   - segurança;
   - performance;
   - compatibilidade;
   - banco;
   - APIs;
   - permissões;
   - auditoria.
4. Se houver alteração estrutural:
   - atualizar `database/schema.sql`;
   - atualizar `database/upgrade.sql`;
   - validar `tools/db_sync.php` e `tools/deploy_preflight.php`.
5. Executar:

```bash
php tools/db_sync.php --env=development
php tests/run.php
```

6. Atualizar a documentação afetada.

## Padrão de código

- usar `declare(strict_types=1);`;
- preferir classes `final`;
- manter controllers finos;
- concentrar regra de negócio em services;
- concentrar SQL em repositories;
- escapar HTML com `View::e()`;
- responder JSON com `Response::json()`;
- manter mensagens e documentação em português brasileiro.

## Banco de dados

Esta regra é obrigatória:

- nenhuma alteração que dependa de banco pode ser disponibilizada sem sincronização prévia do banco;
- toda mudança estrutural deve manter compatibilidade entre código, `schema.sql` e `upgrade.sql`;
- a inicialização da aplicação pode bloquear o ambiente se a estrutura estiver divergente.

## Testes

A suíte atual é customizada em PHP puro e deve continuar funcional:

```bash
php tests/run.php
```

Testes existentes cobrem:

- estrutura do banco;
- leads;
- ordens de serviço;
- aprovação pública de OS;
- geração de PDFs;
- tratamento de erro em produção.

## Documentação

Sempre que uma mudança afetar arquitetura, fluxos, banco, deploy, segurança, UI ou dependências:

- atualizar `README.md`;
- atualizar `CLAUDE.md` quando a mudança impactar contexto global;
- atualizar os arquivos correspondentes em `docs/`.

## Commits

Padrões observados no histórico atual:

- mensagens diretas em português;
- branch principal `main`;
- fluxo prático com commits diretos no branch principal.

Use mensagens objetivas, por exemplo:

- `Corrige handler do erro 500 em produção`
- `Automatiza banco e padroniza PDFs de propostas`
- `Implementa módulo de ordens de serviço`

## O que evitar

- refatorações amplas sem necessidade;
- renomeações em cascata sem ganho real;
- mudanças silenciosas em permissões;
- dependências externas não documentadas;
- escrita direta de arquivos sensíveis fora de `storage/`.
