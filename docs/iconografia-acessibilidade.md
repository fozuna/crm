# Padrao Global de Iconografia e Acessibilidade

## Objetivo

Padronizar todas as acoes visuais do CRM TRAXTER para uso predominante de icones modernos, intuitivos e consistentes, reduzindo variacoes entre telas e melhorando a leitura operacional do sistema.

## Regra Tecnica Aplicada

- Botoes e links de acao com classe `tr-btn` passam a ser convertidos globalmente em icones por camada de frontend em `resources/views/partials/head.php`.
- Botoes ja iconicos com classe `tr-icon-btn` permanecem no padrao, mas recebem reforco de acessibilidade quando necessario.
- Paginas standalone de preview e impressao foram ajustadas manualmente para manter o mesmo padrao visual.

## Acessibilidade WCAG

- `alt` e utilizado apenas em elementos `<img>`.
- Para botoes, links iconicos e SVG inline, o padrao correto e `aria-label`.
- SVGs decorativos recebem `aria-hidden="true"` e `focusable="false"`.
- Elementos iconicos recebem tambem `title` para apoio visual ao usuario em hover.

## Mapeamento Semantico Principal

- `Voltar`, `Retornar` -> `arrow-left`
- `Imprimir` -> `print`
- `PDF` -> `pdf`
- `Excel`, `XLSX`, `Planilha` -> `excel`
- `Preview`, `Visualizar`, `Detalhes`, `Abrir` -> `eye`
- `Baixar`, `Download`, `Comprovante` -> `download`
- `Editar`, `Alterar` -> `edit`
- `Excluir`, `Remover` -> `trash`
- `Novo`, `Nova`, `Adicionar`, `Criar`, `Duplicar` -> `plus`
- `Salvar`, `Gerar`, `Registrar`, `Entrar`, `Instalar` -> `save`
- `Filtrar`, `Aplicar filtro` -> `filter`
- `Buscar`, `Pesquisar` -> `search`
- `Dashboard`, `Painel` -> `chart`
- `Lista`, `Listagem`, `Recebiveis`, `Relatorios` -> `list`
- `Cancelar`, `Fechar` -> `x`
- `Aprovar`, `Confirmar`, `Pagar`, `Baixa` -> `check`
- `Renegociar`, `Estornar`, `Atualizar`, `Sincronizar`, `Reabrir` -> `refresh`

## Escopo da Implementacao

- Infraestrutura global em `resources/views/partials/head.php`
- Catalogo ampliado em `app/Core/UI.php`
- Ajustes manuais em:
  - `resources/views/financial/receipts/preview.php`
  - `resources/views/financial/receivables/print.php`

## Checklist de Verificacao

- Todos os botoes `tr-btn` passam a exibir apenas icones apos carga da interface.
- Todos os botoes iconicos devem possuir `aria-label`.
- Todos os SVGs iconicos devem estar ocultos para leitores de tela.
- Paginas de preview/impressao nao podem manter botoes textuais nas barras de acao.
- O foco visual deve permanecer visivel em navegacao por teclado.
