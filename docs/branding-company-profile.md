# Branding Consolidado no Perfil Empresarial

## Diagnóstico

Antes desta consolidação, o sistema mantinha duas fontes de identidade visual:

- ` /branding `
- ` /empresa `

Isso gerava duplicidade de configuração para:

- nome de marca
- cores
- tipografia
- logos
- metadados visuais

## Solução

O padrão novo estabelece ` /empresa ` como fonte única de branding institucional e operacional.

### Fonte oficial

Os seguintes ativos passam a ser geridos exclusivamente no perfil empresarial:

- nome da marca
- tagline
- cores primária e de destaque
- tipografia base
- logos claro e escuro
- favicon
- imagem social/metatag
- meta title
- meta description
- meta keywords

### Regra da logo

- A logo antiga de ` /branding ` não é mais utilizada.
- PDFs, recibos, contratos e demais consumidores visuais devem usar apenas os logos existentes em ` /empresa `.
- O sistema prioriza `logo_dark` para superfícies claras e mantém fallback para `logo_light` quando necessário.

### Compatibilidade

- Valores antigos de `proposal_branding` continuam disponíveis apenas como legado técnico.
- Durante o upgrade, cores, tipografia e nome de marca são migrados para `company_profile`.
- A página ` /branding ` foi desativada para edição e mantida apenas como referência histórica e manutenção técnica.

## Fluxo

1. O administrador edita ` /empresa `.
2. O backend salva os dados institucionais e os ativos de branding no `company_profile`.
3. Os consumidores antigos passam a ler o branding consolidado por meio da camada de compatibilidade.
4. O frontend usa `company_profile` para:
   - menu e layout
   - metatags
   - favicon
   - PDFs e previews

## Riscos e observações

- Favicon e imagem social precisam permanecer acessíveis sem autenticação para evitar falhas de carregamento no navegador.
- A tipografia web é aplicada globalmente no frontend; no PDF a disponibilidade real depende das fontes suportadas pelo gerador.
- O conteúdo legado de `proposal_branding.logo_path` não deve ser reaproveitado.
