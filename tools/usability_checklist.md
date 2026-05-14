# TRAXTER CRM — Usabilidade (Mobile + Desktop)

## Fluxo crítico (cliente → proposta → projeto)
- Criar cliente com todos os campos e logo.
- Conferir a renderização da logo no perfil do cliente.
- Criar proposta para o cliente com 3+ serviços e validar cálculo do total.
- Alterar status da proposta e converter em projeto.
- Conferir que o projeto aparece no perfil do cliente com valor e status.

## Formulários (UX)
- Em cada formulário, validar que o campo de input é visualmente distinguível do card (borda + fundo diferentes).
- Verificar estados: normal, hover, foco e erro (ao enviar vazio / inválido).
- Conferir acessibilidade do teclado: Tab navega, Enter submete, foco sempre visível.

## Ações por ícone
- Verificar que todos os ícones possuem `aria-label`/texto para leitores de tela.
- Testar ações em telas pequenas: botões não devem ficar “colados” (toque confortável).

## Layout responsivo
- Mobile: sidebar não deve cobrir conteúdo; tabelas devem permitir scroll horizontal sem quebrar layout.
- Desktop: cards e tabelas devem alinhar sem espaços excessivos.

## Checklist WCAG (contraste)
- Rodar `php tools/contrast_test.php` e garantir status OK em todos os pares.
- Validar manualmente (mobile e desktop) contraste em:
  - input vs card (fundo do input vs fundo do container)
  - texto vs fundo do input
  - botões acentuados (texto vs cor #ee6c4d)
  - sidebar (texto branco vs #293241)

