# Debug Session: production-500

Status: OPEN

## Sintoma
- Erro 500 ao acessar a aplicação em ambiente de produção.

## Hipóteses iniciais
- H1: o bootstrap falha por configuração ausente ou inconsistente no ambiente de produção.
- H2: a validação de estrutura do banco lança exceção não tratada corretamente no servidor web.
- H3: permissões de escrita em `storage/logs` ou `storage/cache` causam falha fatal durante a inicialização.
- H4: diferenças entre produção e staging em extensões PHP, versão do PHP ou configuração PDO geram erro fatal específico do ambiente.
- H5: algum include/autoload ou arquivo implantado está ausente/incompleto no deploy atual.

## Evidências
- E1: em `app/bootstrap.php`, o `set_exception_handler()` referenciava `$syncCommandForEnvironment()` sem capturar a variável com `use (...)`.
- E2: a reprodução local com `php debug-production-500-repro.php` confirmou que o fluxo agora responde sem fatal error e retorna a mensagem controlada de sincronização para `production`.
- E3: o arquivo `storage/logs/runtime-events.ndjson` passou a registrar `exception_handler_invoked` e `db_structure_out_of_sync_rendered` no mesmo cenário.
- E4: o teste de regressão `tests/production_error_handling.php` cobre o caso e passou com sucesso.

## Próximos passos
- Validar a correção em staging/produção com logs reais do servidor web.
- Publicar a correção e executar o preflight oficial antes de reabrir o acesso.
- Aguardar confirmação do usuário para limpeza dos artefatos de depuração.
