# Auditoria de Icones - Modulo de Projetos e Regressao Global

## Escopo

- Tela auditada inicialmente: `/gestor/projetos`
- Regressao estendida para `resources/views` de todo o CRM

## Achados Principais

- Os icones em branco da tela de projetos tinham causa raiz objetiva: nomes chamados nas views nao existiam no catalogo central de `App\Core\UI`.
- Itens encontrados sem definicao no helper:
  - `bar-chart-3`
  - `arrow-right`
  - `wallet`
- Efeito visual observado:
  - botoes e links com moldura renderizados corretamente
  - conteudo SVG vazio
  - acao visualmente sem identificacao, apesar do `aria-label` existir em parte dos casos

## Correcoes Aplicadas

- Catalogo central ampliado em `app/Core/UI.php` com os icones faltantes.
- `UI::icon()` passou a usar fallback seguro para evitar SVG vazio quando um nome desconhecido for usado futuramente.
- Auditoria estatica automatizada criada em `tools/icon_audit_regression_test.php` para verificar:
  - uso de nomes de icone nao catalogados
  - imagens sem atributo `alt`
  - controles `tr-icon-btn` sem `aria-label` ou `title`

## Regras de Acessibilidade Validadas

- Icones decorativos devem permanecer com `aria-hidden="true"` e `focusable="false"`.
- Botoes e links iconicos devem possuir `aria-label` ou `title`.
- Imagens devem possuir `alt`.
- Tooltips descritivos continuam sendo suportados via `title`.

## Resultado Esperado

- Nenhum icone de acao deve aparecer em branco na tela de projetos.
- Nenhuma chamada `UI::icon()` deve ficar sem definicao visual.
- Regressao futura pode ser identificada rapidamente pelo teste estatico.
